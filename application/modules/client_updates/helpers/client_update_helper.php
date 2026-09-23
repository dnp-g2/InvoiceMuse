<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Business details for the public client update form and its emails, read from ipconfig.php.
 * Every value is optional except CLIENT_UPDATE_HOST, which the public form requires in production.
 */
function client_update_profile(): array
{
    static $profile;
    if ($profile !== null) {
        return $profile;
    }

    $read = static fn (string $key, string $default = ''): string => function_exists('env')
        ? trim((string) env('CLIENT_UPDATE_' . $key, $default))
        : $default;

    $phone  = $read('CONTACT_PHONE');
    $prefix = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($read('REFERENCE_PREFIX', 'REQ')));

    return $profile = [
        'business_name'    => $read('BUSINESS_NAME') ?: 'Our company',
        'contact_name'     => $read('CONTACT_NAME') ?: 'our office',
        'contact_phone'    => $phone,
        'contact_tel'      => preg_replace('/[^0-9+]/', '', $phone),
        'logo'             => $read('LOGO'),
        'host'             => mb_strtolower($read('HOST')),
        'email_from'       => $read('EMAIL_FROM'),
        'email_to'         => $read('EMAIL_TO'),
        'email_copy_to'    => $read('EMAIL_COPY_TO'),
        'reference_prefix' => $prefix !== '' ? $prefix : 'REQ',
    ];
}
