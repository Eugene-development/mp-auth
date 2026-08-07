<?php

namespace App\Services;

use App\Models\User;

final class AdminAccess
{
    public function allows(User $user): bool
    {
        $allowedEmails = config('admin.emails', []);

        return is_array($allowedEmails)
            && in_array(strtolower($user->email), $allowedEmails, true);
    }
}
