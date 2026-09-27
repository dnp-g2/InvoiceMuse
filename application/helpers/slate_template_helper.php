<?php

defined('BASEPATH') || exit('No direct script access allowed');

/** Only the recipient-facing payment columns; internal notes and references stay private. */
function slate_payment_rows(int $invoice_id): array
{
    $ci = &get_instance();

    return $ci->db
        ->select('p.payment_date, p.payment_amount, m.payment_method_name')
        ->from('ip_payments p')
        ->join('ip_payment_methods m', 'm.payment_method_id = p.payment_method_id', 'left')
        ->where('p.invoice_id', $invoice_id)
        ->order_by('p.payment_date', 'ASC')
        ->order_by('p.payment_id', 'ASC')
        ->get()->result();
}

/** A configured logo must be a local raster image inside uploads. */
function slate_logo(bool $pdf): string
{
    $name      = (string) get_setting('invoice_logo');
    $directory = FCPATH . 'uploads/';
    if ( ! validate_safe_filename($name)['valid']
        || ! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif'], true)
        || ! validate_file_in_directory($directory . $name, $directory)) {
        return '';
    }

    $source = $pdf ? $directory . $name : base_url('uploads/' . implode('/', array_map('rawurlencode', explode('/', $name))));

    return '<img class="slate-logo" src="' . html_escape($source) . '" alt="' . html_escape(trans('invoice_logo')) . '">';
}

/** Return plain address lines; encoding happens at the output boundary. */
function slate_address(object $document, string $prefix): array
{
    $lines = [];
    foreach (['address_1', 'address_2'] as $field) {
        if ( ! empty($document->{$prefix . '_' . $field})) {
            $lines[] = $document->{$prefix . '_' . $field};
        }
    }
    $locality = implode(', ', array_filter([
        $document->{$prefix . '_city'} ?? '',
        trim(($document->{$prefix . '_state'} ?? '') . ' ' . ($document->{$prefix . '_zip'} ?? '')),
    ]));
    if ($locality !== '') {
        $lines[] = $locality;
    }
    if ( ! empty($document->{$prefix . '_country'})) {
        $lines[] = get_country_name(trans('cldr'), $document->{$prefix . '_country'});
    }

    return $lines;
}

/** The saved property address supersedes one "Location:" line in the description. */
function slate_service_details(object $item, bool $properties_enabled): array
{
    $description = (string) ($item->item_description ?? '');
    if ( ! $properties_enabled) {
        return [$description, ''];
    }
    $address = json_decode((string) ($item->item_service_address ?? ''), true);
    if ( ! is_array($address)) {
        return [$description, ''];
    }
    $location = Property_rules::text($address);
    if ($location === '') {
        return [$description, ''];
    }
    $known = array_filter([$location, trim((string) ($address['label'] ?? '')), trim((string) ($address['address_1'] ?? ''))]);

    return [slate_strip_location_line($description, $known), $location];
}

/**
 * Remove one line: the first one naming the saved property (full text, label or
 * street) when present, otherwise the first "Location:" line. Any further
 * "Location:" lines are customer text and stay.
 */
function slate_strip_location_line(string $description, array $known): string
{
    $lines = preg_split('/\R/u', $description);
    $index = null;
    foreach ($lines as $i => $line) {
        if ( ! preg_match('/^\s*Location\s*:\s*(.*)$/iu', $line, $match)) {
            continue;
        }
        if ($index === null) {
            $index = $i;
        }
        if (in_array(mb_strtolower(trim($match[1])), array_map('mb_strtolower', $known), true)) {
            $index = $i;
            break;
        }
    }
    if ($index !== null) {
        unset($lines[$index]);
    }

    return trim(implode("\n", $lines));
}

/**
 * mPDF cannot split a table cell between pages, and one tall cell shrinks the
 * font size of the entire items table. Bounding each row keeps the font legible.
 * Keep every character, including paragraph breaks, in the returned chunks.
 */
function slate_description_chunks(string $description, bool $pdf): array
{
    if ( ! $pdf || $description === '') {
        return [$description];
    }
    $chunks = [];
    $chunk  = '';
    $tokens = preg_split('/(\s+)/u', $description, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    foreach ($tokens ?: [$description] as $token) {
        foreach (mb_str_split($token, 320) as $part) {
            $candidate = $chunk . $part;
            $lines     = mb_strlen($candidate) / 38 + preg_match_all('/\R/u', $candidate);
            if ($chunk !== '' && $lines > 12) {
                $chunks[] = $chunk;
                $chunk    = '';
            }
            $chunk .= $part;
        }
    }
    if ($chunk !== '') {
        $chunks[] = $chunk;
    }

    return $chunks;
}

/** Preserve the application's legacy/current discount ordering and stored totals. */
function slate_summary_rows(object $document, string $type, array $tax_rates, bool $legacy): array
{
    $rows     = [];
    $percent  = $document->{$type . '_discount_percent'} ?? 0;
    $amount   = $document->{$type . '_discount_amount'} ?? 0;
    $discount = null;
    if ((float) $percent != 0 || (float) $amount != 0) {
        $discount = [trans('discount'), (float) $percent != 0 ? html_escape(format_amount($percent)) . '%' : format_currency($amount)];
    }
    if ( ! $legacy && $discount) {
        $rows[] = $discount;
    }
    $item_tax = $document->{$type . '_item_tax_total'} ?? 0;
    if ($discount || $tax_rates || (float) $item_tax != 0) {
        $rows[] = [trans('subtotal'), format_currency($document->{$type . '_item_subtotal'})];
    }
    if ((float) $item_tax != 0) {
        $rows[] = [trans('item_tax'), format_currency($item_tax)];
    }
    foreach ($tax_rates as $tax) {
        $rows[] = [
            $tax->{$type . '_tax_rate_name'} . ' (' . format_amount($tax->{$type . '_tax_rate_percent'}) . '%)',
            format_currency($tax->{$type . '_tax_rate_amount'}),
        ];
    }
    if ($legacy && $discount) {
        $rows[] = $discount;
    }
    $rows[] = [trans('total'), format_currency($document->{$type . '_total'}), 'total'];

    return $rows;
}
