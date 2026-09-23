<?php

/** Pure address/grouping rules shared by web views and tests. */
class Property_rules
{
    /** Full US state names, so "Texas" and "TX" in the state field hash alike. */
    private const US_STATES = [
        'alabama' => 'al', 'alaska' => 'ak', 'arizona' => 'az', 'arkansas' => 'ar', 'california' => 'ca',
        'colorado' => 'co', 'connecticut' => 'ct', 'delaware' => 'de', 'district of columbia' => 'dc',
        'florida' => 'fl', 'georgia' => 'ga', 'hawaii' => 'hi', 'idaho' => 'id', 'illinois' => 'il',
        'indiana' => 'in', 'iowa' => 'ia', 'kansas' => 'ks', 'kentucky' => 'ky', 'louisiana' => 'la',
        'maine' => 'me', 'maryland' => 'md', 'massachusetts' => 'ma', 'michigan' => 'mi', 'minnesota' => 'mn',
        'mississippi' => 'ms', 'missouri' => 'mo', 'montana' => 'mt', 'nebraska' => 'ne', 'nevada' => 'nv',
        'new hampshire' => 'nh', 'new jersey' => 'nj', 'new mexico' => 'nm', 'new york' => 'ny',
        'north carolina' => 'nc', 'north dakota' => 'nd', 'ohio' => 'oh', 'oklahoma' => 'ok', 'oregon' => 'or',
        'pennsylvania' => 'pa', 'rhode island' => 'ri', 'south carolina' => 'sc', 'south dakota' => 'sd',
        'tennessee' => 'tn', 'texas' => 'tx', 'utah' => 'ut', 'vermont' => 'vt', 'virginia' => 'va',
        'washington' => 'wa', 'west virginia' => 'wv', 'wisconsin' => 'wi', 'wyoming' => 'wy',
    ];

    public static function address(array $input): array
    {
        $out = [];
        foreach (['label' => 100, 'address_1' => 150, 'address_2' => 150, 'city' => 100, 'state' => 100, 'zip' => 20, 'country' => 2] as $key => $max) {
            if (isset($input[$key]) && ! is_scalar($input[$key])) {
                throw new InvalidArgumentException('Invalid address value.');
            }
            $out[$key] = trim((string) ($input[$key] ?? ($key === 'country' ? 'US' : '')));
            if (mb_strlen($out[$key]) > $max || preg_match('/[\x00-\x1f<>]/u', $out[$key])) {
                throw new InvalidArgumentException('Invalid or overly long address field: ' . $key);
            }
        }
        foreach (['address_1', 'city', 'state', 'zip', 'country'] as $key) {
            if ($out[$key] === '') {
                throw new InvalidArgumentException('Complete the service address: ' . $key);
            }
        }
        $out['country'] = mb_strtoupper($out['country']);
        if ( ! preg_match('/^[A-Z]{2}$/', $out['country'])) {
            throw new InvalidArgumentException('Use a two-letter country code.');
        }

        return $out;
    }

    public static function hash(array $address): string
    {
        unset($address['label']);
        // The word map is part of every stored address_hash; changing an entry changes existing hashes.
        $map   = ['southeast' => 'se', 'southwest' => 'sw', 'northeast' => 'ne', 'northwest' => 'nw', 'boulevard' => 'blvd', 'drive' => 'dr', 'road' => 'rd', 'street' => 'st', 'court' => 'ct', 'avenue' => 'ave', 'saint' => 'st', 'florida' => 'fl'];
        $parts = [];
        foreach (['address_1', 'address_2', 'city', 'state', 'zip', 'country'] as $key) {
            $words = preg_split('/\s+/', trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($address[$key] ?? ''))));
            $state = $key === 'state' ? (self::US_STATES[implode(' ', $words)] ?? null) : null;
            $parts[] = $state ?? implode(' ', array_map(fn ($s) => $map[$s] ?? $s, $words));
        }

        return hash('sha256', implode('|', $parts));
    }

    public static function text(array $a): string
    {
        return implode(', ', array_filter(array_map(fn ($k) => (string) ($a[$k] ?? ''), ['label', 'address_1', 'address_2', 'city', 'state', 'zip', 'country']), fn ($v) => $v !== ''));
    }

    public static function groups(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $snapshot = json_decode($item->item_service_address ?? '', true);
            $key      = (string) ($item->item_service_property_id ?? 0) . '|' . ($item->item_service_address ?? '');
            if ( ! isset($groups[$key])) {
                $groups[$key] = ['address' => $snapshot, 'items' => [], 'total' => 0.0];
            }
            $groups[$key]['items'][] = $item;
            $groups[$key]['total'] += (float) ($item->item_total ?? 0);
        }

        return array_values($groups);
    }
}
