<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\SmartCaptchaService;
use App\Support\AdAttribution;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    /**
     * Антибот-гейт заявки по SmartCaptcha.
     *
     * Три исхода:
     *  - токен есть и валиден          → passed, verified;
     *  - токена нет (виджет не загрузился у клиента: ad-block, сбой CDN) →
     *    passed без verified, но только пока не исчерпан жёсткий лимит по IP
     *    (см. SmartCaptchaService::allowWithoutToken). Иначе — отказ;
     *  - токен есть, но невалиден/просрочен → отказ (это либо бот, либо повтор).
     *
     * @return array{passed: bool, verified: bool}
     */
    private function captchaGate(Request $request, SmartCaptchaService $captcha): array
    {
        $token = $request->input('captcha_token');
        $ip = $captcha->clientIp($request);

        if (! empty($token)) {
            return [
                'passed' => $captcha->verify($token, $ip),
                'verified' => true,
            ];
        }

        // Защита выключена (нет секретного ключа — dev/staging): verify() сам
        // отдаст fail-open, заявка считается проверенной условно.
        if (! $captcha->isEnabled()) {
            return ['passed' => true, 'verified' => true];
        }

        if ($captcha->allowWithoutToken($ip)) {
            Log::warning('Lead accepted without captcha token (fail-open, IP-limited)', [
                'ip' => $ip,
                'source_url' => $request->input('source_url'),
            ]);

            return ['passed' => true, 'verified' => false];
        }

        Log::warning('Lead rejected: no captcha token and IP limit exhausted', ['ip' => $ip]);

        return ['passed' => false, 'verified' => false];
    }

    /**
     * Единый отказ по капче. Формат совпадает с прочими ошибками валидации
     * (errors[]), чтобы фронт отобразил сообщение штатным образом.
     */
    private function captchaRejected()
    {
        return response()->json([
            'success' => false,
            'message' => 'Не пройдена проверка безопасности.',
            'errors' => ['captcha' => ['Подтвердите, что вы не робот.']],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Пометка темы письма для заявок, прошедших без проверки капчи.
     *
     * @param  array{passed: bool, verified: bool}  $gate
     */
    private function subjectSuffix(array $gate): string
    {
        return $gate['verified'] ? '' : ' [без проверки капчи]';
    }

    /**
     * Send invoice request notification email
     *
     * @param  SmartCaptchaService  $captcha  Внедряется через DI-контейнер Laravel.
     */
    public function sendInvoiceRequest(Request $request, SmartCaptchaService $captcha)
    {
        $lead = null;

        try {
            Log::info('Invoice request notification received', [
                'company_name' => $request->input('company_name'),
                'contact_person' => $request->input('contact_person'),
                'email' => $request->input('email'),
            ]);

            // Антибот-проверка №1: серверная верификация токена SmartCaptcha.
            // До неё заявка не проходит валидацию и не доходит до отправки письма.
            $gate = $this->captchaGate($request, $captcha);
            if (! $gate['passed']) {
                return $this->captchaRejected();
            }

            $validated = $request->validate([
                'company_name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'phone' => 'required|string|max:50',
                'email' => 'required|email|max:255',
                'comment' => 'nullable|string|max:2000',
                'source_url' => 'nullable|string|max:500',
                // Атрибуция рекламного трафика (Яндекс Директ и др.). Необязательный
                // массив — фронт шлёт UTM, захваченные при заходе (см. mp-front $lib/utm).
                'utm' => 'nullable|array:utm_source,utm_medium,utm_campaign,utm_campaign_id,utm_ad_id,utm_content,utm_term',
                'utm.utm_source' => 'nullable|string|max:255',
                'utm.utm_medium' => 'nullable|string|max:255',
                'utm.utm_campaign' => 'nullable|string|max:255',
                'utm.utm_campaign_id' => 'nullable|string|max:255',
                'utm.utm_ad_id' => 'nullable|string|max:255',
                'utm.utm_content' => 'nullable|string|max:255',
                'utm.utm_term' => 'nullable|string|max:255',
                // captcha_token не валидируется здесь — он уже проверен выше.
                'captcha_token' => 'nullable|string|max:4096',
            ]);

            $utm = is_array($validated['utm'] ?? null) ? $validated['utm'] : [];

            // Get manager/admin email from env
            $adminEmail = env('ADMIN_EMAIL', 'info@megapak.top');

            // Prepare email content
            $emailData = [
                'company_name' => $validated['company_name'],
                'contact_person' => $validated['contact_person'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'comment' => $validated['comment'] ?? 'Не указано',
                'source_url' => $validated['source_url'] ?? 'Не указано',
                'utm' => $utm,
                'submitted_at' => now()->setTimezone('Europe/Moscow')->format('d.m.Y H:i:s').' (МСК)',
            ];

            // Сначала фиксируем заявку: даже если SMTP временно недоступен,
            // менеджер увидит её в админке со статусом failed.
            $lead = Lead::query()->create([
                'channel' => 'online',
                'type' => 'invoice',
                'delivery_status' => 'pending',
                'ad_id' => AdAttribution::yandexAdId($utm),
                'company_name' => $validated['company_name'],
                'contact_person' => $validated['contact_person'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'comment' => $validated['comment'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
                'utm' => $utm ?: null,
                'captcha_verified' => $gate['verified'],
            ]);

            // Пометка в теме, если заявка прошла без проверки капчи (у клиента не
            // загрузился виджет) — менеджер видит, что доверие к заявке ниже.
            $subject = 'Новая заявка: Получить счет с НДС (МегаПак)'.$this->subjectSuffix($gate);

            // Send email using a Blade template
            Mail::send('emails.invoice-request', $emailData, function ($msg) use ($adminEmail, $subject) {
                $msg->to($adminEmail)
                    ->subject($subject);
            });

            $lead->update(['delivery_status' => 'sent']);

            Log::info('Invoice request notification sent successfully', [
                'company_name' => $request->company_name,
                'to' => $adminEmail,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Запрос успешно отправлен.',
            ]);

        } catch (ValidationException $e) {
            Log::warning('Invoice request notification validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Некорректные данные.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (Exception $e) {
            if ($lead) {
                try {
                    $lead->update(['delivery_status' => 'failed']);
                } catch (Exception) {
                    // Не маскируем исходную ошибку вторичным сбоем обновления статуса.
                }
            }

            Log::error('Invoice request notification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить уведомление.',
                'errors' => ['general' => ['Произошла ошибка при отправке.']],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Send price request notification email
     */
    public function sendPriceRequest(Request $request, SmartCaptchaService $captcha)
    {
        $lead = null;

        try {
            Log::info('Price request notification received', [
                'name' => $request->input('name'),
                'contact' => $request->input('contact'),
            ]);

            // Антибот-проверка №1: серверная верификация токена SmartCaptcha.
            $gate = $this->captchaGate($request, $captcha);
            if (! $gate['passed']) {
                return $this->captchaRejected();
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'contact' => 'required|string|max:255',
                'comment' => 'nullable|string|max:2000',
                'source_url' => 'nullable|string|max:500',
                'utm' => 'nullable|array:utm_source,utm_medium,utm_campaign,utm_campaign_id,utm_ad_id,utm_content,utm_term',
                'utm.utm_source' => 'nullable|string|max:255',
                'utm.utm_medium' => 'nullable|string|max:255',
                'utm.utm_campaign' => 'nullable|string|max:255',
                'utm.utm_campaign_id' => 'nullable|string|max:255',
                'utm.utm_ad_id' => 'nullable|string|max:255',
                'utm.utm_content' => 'nullable|string|max:255',
                'utm.utm_term' => 'nullable|string|max:255',
                'captcha_token' => 'nullable|string|max:4096',
            ]);

            $utm = is_array($validated['utm'] ?? null) ? $validated['utm'] : [];

            // Get manager/admin email from env
            $adminEmail = env('ADMIN_EMAIL', 'info@megapak.top');

            // Prepare email content
            $emailData = [
                'name' => $validated['name'],
                'contact' => $validated['contact'],
                'comment' => $validated['comment'] ?? 'Не указано',
                'source_url' => $validated['source_url'] ?? 'Не указано',
                'utm' => $utm,
                'submitted_at' => now()->setTimezone('Europe/Moscow')->format('d.m.Y H:i:s').' (МСК)',
            ];

            $lead = Lead::query()->create([
                'channel' => 'online',
                'type' => 'price',
                'delivery_status' => 'pending',
                'ad_id' => AdAttribution::yandexAdId($utm),
                'name' => $validated['name'],
                'contact' => $validated['contact'],
                'comment' => $validated['comment'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
                'utm' => $utm ?: null,
                'captcha_verified' => $gate['verified'],
            ]);

            $subject = 'Новая заявка: Запрос цены (МегаПак)'.$this->subjectSuffix($gate);

            // Send email using a Blade template
            Mail::send('emails.price-request', $emailData, function ($msg) use ($adminEmail, $subject) {
                $msg->to($adminEmail)
                    ->subject($subject);
            });

            $lead->update(['delivery_status' => 'sent']);

            Log::info('Price request notification sent successfully', [
                'name' => $request->name,
                'to' => $adminEmail,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Запрос цены успешно отправлен.',
            ]);

        } catch (ValidationException $e) {
            Log::warning('Price request notification validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Некорректные данные.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (Exception $e) {
            if ($lead) {
                try {
                    $lead->update(['delivery_status' => 'failed']);
                } catch (Exception) {
                    // Не маскируем исходную ошибку вторичным сбоем обновления статуса.
                }
            }

            Log::error('Price request notification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить уведомление.',
                'errors' => ['general' => ['Произошла ошибка при отправке.']],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
