@php
    /**
     * Блок атрибуции рекламного трафика в письме-заявке.
     *
     * Показывается отдельным блоком, если заявка пришла с Яндекс Директа
     * (utm_source равен yandex/ya/direct и т.п.). Выводим ключевые UTM,
     * по которым менеджер видит кампанию, объявление (ad_id) и ключевое слово.
     *
     * Переиспользует классы из <style> родительского письма (.message-block,
     * .info-block, .info-row, .info-label, .info-value), цвет акцента —
     * янтарный, чтобы визуально отделить платный трафик от Organic.
     */
    $utm = $utm ?? [];

    $isYandex = is_array($utm)
        && !empty($utm['utm_source'])
        && in_array(strtolower((string) $utm['utm_source']), ['yandex', 'ya', 'direct', 'yandexdirect', 'yandex.direct', 'ya.direct'], true);

    $utmFields = [
        'utm_campaign'    => 'Кампания',
        'utm_campaign_id' => 'ID кампании',
        'utm_content'     => 'Объявление (ad_id)',
        'utm_term'        => 'Ключевое слово',
        'utm_medium'      => 'Тип трафика',
        'utm_source'      => 'Источник',
    ];
@endphp

@if($isYandex)
            <div class="message-block" style="background: #fff4e6; border-left-color: #f59e0b;">
                <h3 style="color: #b45309;">🎯 Рекламный трафик: Яндекс&nbsp;Директ</h3>
                <div class="info-block" style="background: #ffffff; margin: 14px 0 0;">
                    @foreach($utmFields as $key => $label)
                        @if(!empty($utm[$key]))
                            <div class="info-row">
                                <span class="info-label">{{ $label }}:</span>
                                <span class="info-value" style="font-family: monospace; font-size: 12px;">{{ $utm[$key] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
@endif
