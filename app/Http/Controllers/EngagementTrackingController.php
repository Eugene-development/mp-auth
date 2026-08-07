<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Серверный гейт клик-целей вовлечения (cta_click_*): защита от накрутки ботами.
 *
 * Модель: фронт перед отправкой цели в Метрику спрашивает у сервера «можно?».
 * Бот-ферма, гоняющая реальную страницу в браузерах, вынуждена ходить сюда и
 * упирается в лимиты; прямой вызов reachGoal из стороннего скрипта этим гейтом
 * не прикроешь (клиент доверенный по определению) — но такие атаки теряют смысл
 * для массовой накрутки вовлечения.
 *
 * Два этапа:
 *  1. issueNonce() — выдаёт сессионный токен, привязанный к IP (выдача троттлится
 *     на уровне маршрута, чтобы не плодить тысячи сессий с одного IP).
 *  2. track() — проверяет nonce + per-session/per-IP лимиты, логирует событие
 *     в канал engagement (доверенный серверный счёт) и возвращает ok/429.
 *
 * Nonce и счётчики лежат в дефолтном cache-store (CACHE_STORE=database).
 */
class EngagementTrackingController extends Controller
{
    /** Срок жизни nonce, минуты. */
    private const NONCE_TTL_MIN = 30;

    /** Максимум целей с одной сессии (nonce) за 60с. */
    private const MAX_PER_NONCE = 15;

    /** Максимум целей с одного IP за 60с (поверх route-level throttle). */
    private const MAX_PER_IP = 60;

    /** Белый список имён целей — всё прочее отбрасывается. */
    private const ALLOWED_GOALS = [
        'cta_click_price',
        'cta_click_order',
        'cta_click_invoice_bochki',
        'cta_click_invoice_vedra',
        'cta_click_invoice_evrokuby',
        'cta_click_invoice_kanistry',
        'cta_click_kp_home',
        'cta_click_kp_contacts',
        'phone_click',
        // Составная цель: последовательность «главная → каталог → скролл →
        // карусель/фото канистры» собирается на клиенте (catalogFunnel.ts),
        // сюда приходит уже готовый результат.
        'catalog_kanistry_interest',
    ];

    /**
     * Выдаёт nonce для атрибуции клик-целей.
     * Выдача ограничена на уровне маршрута (throttle по IP).
     */
    public function issueNonce(Request $request)
    {
        $token = Str::random(48);

        Cache::put(
            $this->nonceKey($token),
            ['ip' => $request->ip()],
            now()->addMinutes(self::NONCE_TTL_MIN)
        );

        return response()->json(['token' => $token]);
    }

    /**
     * Валидирует клик-цель.
     *
     * 200 {ok:true}  — цель можно учесть (фронт шлёт reachGoal).
     * 429 {reason}   — превышен per-session или per-IP лимит (цель подавить).
     * 422            — неизвестная цель / невалидный или просроченный nonce.
     */
    public function track(Request $request)
    {
        $goal = (string) $request->input('goal', '');
        $token = (string) $request->input('token', '');

        if (! in_array($goal, self::ALLOWED_GOALS, true)) {
            return response()->json(['ok' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! Cache::has($this->nonceKey($token))) {
            // Нет/просрочен/ни разу не выдавался — не атрибутируем.
            return response()->json(['ok' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // per-session: одна сессия не может залить цели массово.
        if (! RateLimiter::attempt($this->nonceGoalKey($token), self::MAX_PER_NONCE, fn () => true, 60)) {
            return response()->json(['ok' => false, 'reason' => 'session_rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // per-IP: дополнительная страховка поверх route-level throttle.
        $ip = $request->ip();
        if ($ip && ! RateLimiter::attempt($this->ipGoalKey($ip), self::MAX_PER_IP, fn () => true, 60)) {
            return response()->json(['ok' => false, 'reason' => 'ip_rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Доверенный серверный счёт (не зависит от клиентской reachGoal).
        Log::channel('engagement')->info('engagement', [
            'goal' => $goal,
            'ip' => $ip,
            'ua' => $request->userAgent(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function nonceKey(string $token): string
    {
        return 'eng-nonce:'.$token;
    }

    private function nonceGoalKey(string $token): string
    {
        return 'eng-goal:'.$token;
    }

    private function ipGoalKey(?string $ip): string
    {
        return 'eng-goal-ip:'.($ip ?? 'unknown');
    }
}
