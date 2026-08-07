<?php

namespace Tests\Unit;

use App\Support\AdAttribution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdAttributionTest extends TestCase
{
    /** @return array<string, array{array<string, string>, string|null}> */
    public static function attributionCases(): array
    {
        return [
            'explicit id' => [['utm_source' => 'yandex', 'utm_ad_id' => '123456'], '123456'],
            'numeric content' => [['utm_source' => 'yandex_direct', 'utm_content' => '98765'], '98765'],
            'direct template' => [['utm_source' => 'ya', 'utm_content' => 'cid|1|gid|2|aid|34567|adp|no'], '34567'],
            'organic source is ignored' => [['utm_source' => 'google', 'utm_ad_id' => '123456'], null],
            'non-numeric id is ignored' => [['utm_source' => 'yandex', 'utm_ad_id' => '<script>'], null],
        ];
    }

    /** @param array<string, string> $utm */
    #[DataProvider('attributionCases')]
    public function test_it_extracts_only_valid_yandex_ad_ids(array $utm, ?string $expected): void
    {
        $this->assertSame($expected, AdAttribution::yandexAdId($utm));
    }
}
