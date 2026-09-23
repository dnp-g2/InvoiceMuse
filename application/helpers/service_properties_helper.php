<?php

defined('BASEPATH') || exit('No direct script access allowed');
function service_properties()
{
    $ci = &get_instance();
    $ci->load->model('service_properties/mdl_service_properties');

    return $ci->mdl_service_properties;
}
function property_csrf_header(): void
{
    $ci = &get_instance();
    header('X-CSRF-Token: ' . $ci->security->get_csrf_hash());
}

function property_json_error(Throwable $error): void
{
    // Exceptions here contain only curated validation messages, never database errors.
    $ci = &get_instance();
    property_csrf_header();
    header('Content-Type: text/plain; charset=UTF-8');
    echo json_encode(['success' => 0, 'validation_errors' => ['service_property' => $error->getMessage()]]);
    exit;
}
function property_require_publishable(string $type, int $id): void
{
    try {
        service_properties()->assert_publishable($type, $id);
    } catch (RuntimeException $e) {
        show_error(html_escape($e->getMessage()), 409, 'Service property review required');
    }
}
function property_prepare_render(string $type, object $document, array &$items, bool $preview = false): bool
{
    $m     = service_properties();
    $id    = (int) $document->{$type . '_id'};
    $state = $m->state($type, $id);
    if ( ! $state) {
        return false;
    }
    // Hold the document lock through rendering so a concurrent edit cannot mix snapshots and amounts.
    $m->lock($type . ':' . $id);
    $ci = &get_instance();
    $ci->load->model($type . 's/mdl_' . $type . 's');
    $fresh = $ci->{'mdl_' . $type . 's'}->get_by_id($id);
    foreach ((array) $fresh as $key => $value) {
        $document->{$key} = $value;
    }
    $itemModel = $type === 'invoice' ? 'mdl_items' : 'mdl_quote_items';
    $ci->load->model($type . 's/' . $itemModel);
    $items  = $ci->{$itemModel}->where($type . '_id', $id)->get()->result();
    $state  = $m->state($type, $id);
    $issues = $m->problems($type, $id);
    if ($issues && ! $preview) {
        show_error(html_escape(implode(' ', $issues)), 409, 'Service property review required');
    }
    $document->property_incomplete = (bool) $issues;
    foreach (json_decode($state->billing_snapshot, true) as $k => $v) {
        $document->{$k} = $v;
    }
    if ( ! $preview && ! $issues) {
        $m->publish($type, $id);
    }

    return true;
}
function property_render_rows(array $items, bool $enabled, bool $publicAmounts = false): array
{
    if ( ! $enabled) {
        return $items;
    }$rows = [];
    foreach (Property_rules::groups($items) as $g) {
        $rows[] = (object) ['property_row' => 'header', 'text' => $g['address'] ? Property_rules::text($g['address']) : 'Service property required'];
        foreach ($g['items'] as $item) {
            $rows[] = $item;
        }
        $amount = $publicAmounts ? array_sum(array_map(fn ($i) => (float) $i->item_subtotal - (float) $i->item_discount, $g['items'])) : $g['total'];
        $rows[] = (object) ['property_row' => 'total', 'amount' => $amount];
    }

    return $rows;
}
function property_special_row(object $item, int $columns): bool
{
    if ( ! isset($item->property_row)) {
        return false;
    }
    if ($item->property_row === 'header') {
        echo '<tr style="background:#eef3ef"><td colspan="' . (int) $columns . '"><strong>Service property: ' . html_escape($item->text) . '</strong></td></tr>';
    } else {
        echo '<tr><td colspan="' . ((int) $columns - 1) . '" style="text-align:right"><strong>Property line total</strong></td><td style="text-align:right"><strong>' . format_currency($item->amount) . '</strong></td></tr>';
    }

    return true;
}
