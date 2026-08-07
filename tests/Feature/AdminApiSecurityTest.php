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
}
