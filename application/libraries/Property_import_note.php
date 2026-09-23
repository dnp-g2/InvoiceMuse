<?php

/** Human-readable source details; original submissions remain in the requests table. */
class Property_import_note
{
    public static function format(array $entry): string
    {
        $source  = $entry['expected'];
        $address = static function (array $row, string $prefix = ''): string {
            $parts = [];
            foreach (['address_1', 'address_2', 'city', 'state', 'zip'] as $key) {
                $value = trim((string) ($row[$prefix . $key] ?? ''));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }

            return implode(', ', $parts);
        };
        $lines = ['Customer information received', ''];
        foreach (['full_name' => 'Name', 'phone' => 'Phone', 'email' => 'Email'] as $key => $label) {
            if ( ! empty($source[$key])) {
                $lines[] = $label . ': ' . $source[$key];
            }
        }
        $lines[]    = '';
        $lines[]    = 'Service property 1: ' . $address($source, 'service_');
        $additional = $source['additional_service_addresses'] ?? [];
        if (is_string($additional)) {
            $additional = json_decode($additional ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        }
        foreach (($additional ?? []) as $index => $property) {
            $lines[] = 'Service property ' . ($index + 2) . ': ' . $address($property);
        }
        $lines[] = 'Mailing address: ' . ( ! empty($source['mailing_same_as_service']) ? 'Same as service property 1' : ($address($source, 'mailing_') ?: 'Not provided'));
        $lines[] = '';
        $lines[] = 'Submission reference: ' . $entry['reference'];
        if ( ! empty($source['submitted_at'])) {
            $lines[] = 'Submitted: ' . $source['submitted_at'];
        }

        return implode("\n", $lines);
    }
}
