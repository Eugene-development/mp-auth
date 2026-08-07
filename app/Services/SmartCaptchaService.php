<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Серверная верификация токена Yandex SmartCaptcha.
 *
 * Токен, полученный на фронте (виджет invisible-капчи), проверяется здесь
 * через официальный эндпоинт Яндекса. Только после успешной проверки заявка
 * считается поданной живым пользователем и доходит до отправки письма.
 *
 * Логика ответа Яндекса:
 *  - HTTP 200 + {"status": "ok"}            → токен валиден.
 *  - HTTP 200 + {"status": "failed", ...}   → проверка не пройдена.
 *  - HTTP 4xx (например, 403 при невалидном/просроченном токене) → не пройдена.
 *
 * Режим fail-open: если секрет не задан (стейджинг/локалка), верификация
 * пропускается и возвращается true. Это сознательное решение — не ломать
 * отправку заявок при отсутствии ключа, защита включается автоматически
 * как только ключ прописан в окружении.
 *
 * @see https://yandex.cloud/en/docs/smartcaptcha/concepts/validation
 */
class SmartCaptchaService
{
    /**
     * Проверить токен капчи.
     *
     * @param  string|null $token Токен из запроса (captcha_token).
     * @param  string|null $ip    IP посетителя (для аналитики Яндекса, опционально).
     * @return bool               true — проверка пройдена (или fail-open).
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.smartcaptcha.secret');

        // Fail-open: без секретного ключа защита не активна.
        // Это позволяет не ломать dev/staging, где ключ может быть не прописан.
        if (empty($secret)) {
            Log::info('SmartCaptcha: secret key not set, skipping verification (fail-open).');
            return true;
        }

        // Нет токена — жёсткий отказ на этом уровне. Решение «принять заявку без
        // токена» принимается отдельно и осознанно — см. allowWithoutToken().
        if (empty($token)) {
            return false;
        }

        $serverUrl = config('services.smartcaptcha.server_url');

        $payload = [
            'secret' => $secret,
            'token' => $token,
        ];
        // IP опционален, но улучшает оценку риска на стороне Яндекса.
        if ($ip) {
            $payload['ip'] = $ip;
        }

        try {
            $response = Http::asForm()->timeout(5)->post($serverUrl, $payload);

            // 4xx — невалидный/просроченный токен и т.п. Явный отказ.
            if (!$response->successful()) {
                Log::warning('SmartCaptcha: verification HTTP error', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $data = $response->json();

            return ($data['status'] ?? null) === 'ok';
        } catch (\Throwable $e) {
            // Сетевой сбой / таймаут: отказываем, чтобы не пропустить бота при
            // недоступности сервиса, но логируем для расследования.
            Log::error('SmartCaptcha: verification request failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Активна ли защита (задан серверный ключ).
     */
    public function isEnabled(): bool
    {
        return !empty(config('services.smartcaptcha.secret'));
    }

    /**
     * Можно ли принять заявку БЕЗ токена капчи.
     *
     * Ситуация: у посетителя не загрузился captcha.js (ad-block режет домен
     * Яндекса, сбой CDN). Токена он не получит никогда, и жёсткий отказ означает
     * потерянный лид — для формы заявки это дороже, чем риск пропустить спам.
     *
     * Компромисс: принимаем, но под жёстким лимитом по IP (по умолчанию 2 в час).
     * Массовая накрутка через этот путь становится бессмысленной, а живой человек
     * с ad-block свою одну заявку отправит. Каждый такой случай пишется в лог,
     * письмо помечается — чтобы менеджер видел, что проверка не проходила.
     *
     * Полностью отключается через SMARTCAPTCHA_ALLOW_UNVERIFIED=false.
     *
     * @param  string|null $ip IP посетителя (без IP лимит не построить → отказ).
     */
    public function allowWithoutToken(?string $ip): bool
    {
        if (!config('services.smartcaptcha.allow_unverified')) {
            return false;
        }

        if (empty($ip)) {
            return false;
        }

        $maxPerHour = max(1, (int) config('services.smartcaptcha.allow_unverified_per_hour', 2));

        // attempt() сам инкрементирует счётчик и возвращает false при исчерпании.
        return (bool) RateLimiter::attempt(
            'captcha-unverified:' . sha1($ip),
            $maxPerHour,
            fn () => true,
            3600
        );
    }

    /**
     * Извлекает IP клиента из запроса с учётом доверенных прокси.
     * mp-front ходит через CDN/гейтвей, поэтому реальный IP — в X-Forwarded-For.
     */
    public function clientIp($request): ?string
    {
        // Учитываем доверенные proxy (см. TrustProxies middleware в Laravel).
        $ip = $request->ip();
        return $ip ?: null;
    }
}
