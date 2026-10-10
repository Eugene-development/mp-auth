<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Support\YandexConversionExport;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class YandexConversionExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.timezone', 'Europe/Moscow');
    }

    public function test_it_builds_yandex_csv_rows_from_calls_and_emails(): void
    {
        $call = $this->lead(15, 'offline_call', '+7 (999) 555-11-22', '2026-08-08 10:30:00');
        $email = $this->lead(16, 'offline_email', ' SALES@Example.RU ', '2026-08-08 11:45:00');

        $lines = iterator_to_array(app(YandexConversionExport::class)->lines([$call, $email]));

        $this->assertSame("id;create_date_time;emails;phones;order_status\r\n", $lines[0]);
        $this->assertSame("mp-offline-15;2026-08-08 10:30:00;;79995551122;IN_PROGRESS\r\n", $lines[1]);
        $this->assertSame("mp-offline-16;2026-08-08 11:45:00;sales@example.ru;;IN_PROGRESS\r\n", $lines[2]);
    }

    public function test_it_skips_rows_without_a_valid_yandex_identifier(): void
    {
        $invalid = $this->lead(17, 'offline_email', 'not-an-email', '2026-08-08 12:00:00');
        $formula = $this->lead(18, 'offline_email', '=cmd@example.com', '2026-08-08 12:05:00');

        $lines = iterator_to_array(app(YandexConversionExport::class)->lines([$invalid, $formula]));

        $this->assertCount(1, $lines);
    }

    public function test_period_is_limited_to_yandex_maximum_conversion_age(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-08 15:00:00', 'Europe/Moscow'));

        [$start, $end] = app(YandexConversionExport::class)->periodRange('year', '2026-08-08');

        $this->assertSame('2026-04-17 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-08 15:00:00', $end->format('Y-m-d H:i:s'));

        CarbonImmutable::setTestNow();
    }

    private function lead(int $id, string $type, string $contact, string $createdAt): Lead
    {
        $lead = new Lead([
            'channel' => 'offline',
            'type' => $type,
            'contact' => $contact,
        ]);
        $lead->id = $id;
        $lead->created_at = CarbonImmutable::parse($createdAt, 'Europe/Moscow');

        return $lead;
    }
}
