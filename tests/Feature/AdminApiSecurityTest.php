<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminApiSecurityTest extends TestCase
{
    public function test_leads_cannot_be_read_without_a_jwt(): void
    {
        $this->getJson('/api/admin/leads')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_offline_lead_cannot_be_created_without_a_jwt(): void
    {
        $this->postJson('/api/admin/leads/offline', [
            'name' => 'Test',
            'contact' => '+7 999 000-00-00',
        ])->assertUnauthorized();
    }

    public function test_offline_conversions_cannot_be_exported_without_a_jwt(): void
    {
        $this->getJson('/api/admin/leads/offline/export?period=day&date=2026-08-08')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
