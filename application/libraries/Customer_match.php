<?php

/** Shared, conservative matching for administrator-reviewed customer creation. */
class Customer_match
{
    public static function matches(array $customer, string $name, string $email, string $phone): bool
    {
        $fullName = trim($customer['client_name'] . ' ' . ($customer['client_surname'] ?? ''));
        return (self::text($name) !== '' && self::text($fullName) === self::text($name))
            || (self::text($email) !== '' && self::text($customer['client_email'] ?? '') === self::text($email))
            || (self::phone($phone) !== '' && in_array(self::phone($phone), [self::phone($customer['client_phone'] ?? ''), self::phone($customer['client_mobile'] ?? '')], true));
    }

    private static function text(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value ?? '')));
    }

    private static function phone(?string $value): string
    {
        $digits = preg_replace('/\D/', '', $value ?? '');
        return strlen($digits) === 11 && $digits[0] === '1' ? substr($digits, 1) : $digits;
    }
}
