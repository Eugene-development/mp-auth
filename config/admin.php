<?php

$adminEmails = array_values(array_filter(array_map(
    static fn (string $email): string => strtolower(trim($email)),
    explode(',', (string) env('ADMIN_EMAILS', env('ADMIN_EMAIL', '')))
)));

$devEmail = strtolower(trim((string) env('DEV_ADMIN_EMAIL', 'admin@megapak.local')));
if (env('APP_ENV') === 'local' && $devEmail !== '') {
    $adminEmails[] = $devEmail;
    $adminEmails = array_values(array_unique($adminEmails));
}

return [
    'emails' => $adminEmails,
    'dev_email' => $devEmail,
    'dev_password' => env('DEV_ADMIN_PASSWORD'),
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl_minutes' => (int) env('JWT_TTL', 480),
    'jwt_issuer' => env('JWT_ISSUER', 'mp-auth'),
    'jwt_audience' => env('JWT_AUDIENCE', 'mp-front-admin'),
];
