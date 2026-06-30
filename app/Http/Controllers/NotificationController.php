<?php

namespace App\Http\Controllers;

use App\Services\SmartCaptchaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Exception;

class NotificationController extends Controller
{
    /**
     * Send invoice request notification email
     *
     * @param  Request              $request
     * @param  SmartCaptchaService  $captcha  Внедряется через DI-контейнер Laravel.
     */
    public function sendInvoiceRequest(Request $request, SmartCaptchaService $captcha)
    {
        try {
            Log::info('Invoice request notification received', [
                'company_name' => $request->input('company_name'),
                'contact_person' => $request->input('contact_person'),
                'email' => $request->input('email'),
            ]);

            // Антибот-проверка №1: серверная верификация токена SmartCaptcha.
            // До неё заявка не проходит валидацию и не доходит до отправки письма.
            // Формат ответа совпадает с прочими ошибками валидации (errors[]), чтобы
            // фронт отобразил сообщение штатным образом.
            if (!$captcha->verify($request->input('captcha_token'), $captcha->clientIp($request))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не пройдена проверка безопасности.',
                    'errors' => ['captcha' => ['Подтвердите, что вы не робот.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $request->validate([
                'company_name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'phone' => 'required|string|max:50',
                'email' => 'required|email|max:255',
                'comment' => 'nullable|string|max:2000',
                'source_url' => 'nullable|string|max:500',
                // captcha_token не валидируется здесь — он уже проверен выше.
                'captcha_token' => 'nullable|string',
            ]);

            // Get manager/admin email from env
            $adminEmail = env('ADMIN_EMAIL', 'info@megapak.top');

            // Prepare email content
            $emailData = [
                'company_name' => $request->company_name,
                'contact_person' => $request->contact_person,
                'phone' => $request->phone,
                'email' => $request->email,
                'comment' => $request->comment ?? 'Не указано',
                'source_url' => $request->source_url ?? 'Не указано',
                'submitted_at' => now()->setTimezone('Europe/Moscow')->format('d.m.Y H:i:s') . ' (МСК)',
            ];

            // Send email using a Blade template
            Mail::send('emails.invoice-request', $emailData, function ($msg) use ($adminEmail) {
                $msg->to($adminEmail)
                    ->subject('Новая заявка: Получить счет с НДС (МегаПак)');
            });

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
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Некорректные данные.',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (Exception $e) {
            Log::error('Invoice request notification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
     *
     * @param  Request              $request
     * @param  SmartCaptchaService  $captcha
     */
    public function sendPriceRequest(Request $request, SmartCaptchaService $captcha)
    {
        try {
            Log::info('Price request notification received', [
                'name' => $request->input('name'),
                'contact' => $request->input('contact'),
            ]);

            // Антибот-проверка №1: серверная верификация токена SmartCaptcha.
            if (!$captcha->verify($request->input('captcha_token'), $captcha->clientIp($request))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не пройдена проверка безопасности.',
                    'errors' => ['captcha' => ['Подтвердите, что вы не робот.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'contact' => 'required|string|max:255',
                'comment' => 'nullable|string|max:2000',
                'source_url' => 'nullable|string|max:500',
                'captcha_token' => 'nullable|string',
            ]);

            // Get manager/admin email from env
            $adminEmail = env('ADMIN_EMAIL', 'info@megapak.top');

            // Prepare email content
            $emailData = [
                'name' => $request->name,
                'contact' => $request->contact,
                'comment' => $request->comment ?? 'Не указано',
                'source_url' => $request->source_url ?? 'Не указано',
                'submitted_at' => now()->setTimezone('Europe/Moscow')->format('d.m.Y H:i:s') . ' (МСК)',
            ];

            // Send email using a Blade template
            Mail::send('emails.price-request', $emailData, function ($msg) use ($adminEmail) {
                $msg->to($adminEmail)
                    ->subject('Новая заявка: Запрос цены (МегаПак)');
            });

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
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Некорректные данные.',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (Exception $e) {
            Log::error('Price request notification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить уведомление.',
                'errors' => ['general' => ['Произошла ошибка при отправке.']],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
