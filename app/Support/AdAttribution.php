<?php

namespace App\Support;

final class AdAttribution
{
    /**
     * Извлекает ID объявления только для трафика Яндекс Директа.
     *
     * Поддерживает рекомендуемый `utm_ad_id={ad_id}`, числовой
     * `utm_content={ad_id}` и составной `utm_content=...|aid|{ad_id}|...`.
     *
     * @param  array<string, mixed>  $utm
     */
    public static function yandexAdId(array $utm): ?string
    {
        $source = trim((string) ($utm['utm_source'] ?? ''));
        if (! preg_match('/(?:^|[._-])(yandex|ya|direct)(?:$|[._-])/i', $source)) {
            return null;
        }

        $explicit = trim((string) ($utm['utm_ad_id'] ?? ''));
        if (preg_match('/^\d{1,32}$/', $explicit)) {
            return $explicit;
        }

        $content = trim((string) ($utm['utm_content'] ?? ''));
        if (preg_match('/^\d{1,32}$/', $content)) {
            return $content;
        }

        if (preg_match('/(?:^|[|;,:\s])(?:ad_id|aid)[|=:._-](\d{1,32})(?:$|[|;,:\s])/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
