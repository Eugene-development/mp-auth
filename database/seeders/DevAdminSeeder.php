<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DevAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('Dev admin was not created outside the local environment.');

            return;
        }

        $email = (string) config('admin.dev_email');
        $password = (string) config('admin.dev_password', '');

        if ($email === '' || $password === '') {
            throw new RuntimeException('DEV_ADMIN_EMAIL and DEV_ADMIN_PASSWORD must be configured.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Dev Admin',
                'password' => $password,
            ],
        );

        $this->command?->info("Dev admin {$email} is ready.");
    }
}
