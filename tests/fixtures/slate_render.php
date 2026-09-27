<?php

// Standalone rendering fixture: real templates/helpers, in-memory application data.
defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');
defined('APPPATH') || define('APPPATH', dirname(__DIR__, 2) . '/application/');
defined('FCPATH') || define('FCPATH', getenv('SLATE_ASSET_ROOT') ?: dirname(__DIR__, 2) . '/');
defined('CUSTOM_TEMPLATES_FOLDER') || define('CUSTOM_TEMPLATES_FOLDER', '');

class CI_Model {}

final class SlateFixtureQuery
{
    public array $calls = [];

    public function __call(string $method, array $args): self
    {
        $this->calls[] = [$method, $args];

        return $this;
    }

    public function result(): array
    {
        return $GLOBALS['slate_fixture']['payments'];
    }
}

final class SlateFixtureProperties
{
    public int $published = 0;

    public function state($type, $id): ?object
    {
        return $GLOBALS['slate_fixture']['properties'] ? (object) ['billing_snapshot' => json_encode(['client_name' => 'Sample Property Group'])] : null;
    }

    public function lock($key): void {}

    public function problems($type, $id): array
    {
        return $GLOBALS['slate_fixture']['problems'];
    }

    public function publish($type, $id): void
    {
        $this->published++;
    }
}

function &get_instance(): object
{
    return $GLOBALS['slate_ci'];
}
function get_setting($name, $default = null, $escape = false)
{
    return $GLOBALS['slate_fixture']['settings'][$name] ?? $default;
}
function trans($key, $id = '', $default = null)
{
    return $GLOBALS['slate_language'][$key] ?? $default ?? $key;
}
function html_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function htmlsc($value): string
{
    return html_escape($value);
}
function base_url($path = ''): string
{
    return '/' . ltrim($path, '/');
}
function site_url($path = ''): string
{
    return '/index.php/' . ltrim($path, '/');
}
function get_country_name($locale, $code): string
{
    return $code === 'US' ? 'United States' : $code;
}
function date_from_mysql($value, $long = false): string
{
    return date($long ? 'F j, Y' : 'm/d/Y', strtotime($value));
}
function _csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="fixture-token">';
}
function show_error($message, $status = 500, $title = ''): never
{
    throw new RuntimeException($message, $status);
}
function log_message($level, $message): void {}
function invoice_qrcode($id): string
{
    return '<img alt="Payment QR" src="data:image/png;base64,' . base64_encode(file_get_contents(FCPATH . 'uploads/slate-fixture.png')) . '">';
}

require_once APPPATH . 'helpers/slate_template_helper.php';
require_once APPPATH . 'helpers/service_properties_helper.php';
require_once APPPATH . 'helpers/file_security_helper.php';
require_once APPPATH . 'helpers/number_helper.php';
require_once APPPATH . 'helpers/client_helper.php';
require_once APPPATH . 'helpers/mpdf_helper.php';
require_once APPPATH . 'helpers/template_helper.php';
require_once APPPATH . 'libraries/Property_rules.php';
require_once APPPATH . 'modules/invoices/models/Mdl_templates.php';
require APPPATH . 'language/english/ip_lang.php';
$GLOBALS['slate_language'] = $lang;

function slate_fixture_reset(): void
{
    $GLOBALS['slate_fixture'] = [
        'settings' => [
            'currency_code'         => 'USD', 'currency_symbol' => '$', 'currency_symbol_placement' => 'before',
            'thousands_separator'   => ',', 'decimal_point' => '.', 'tax_rate_decimal_places' => 2,
            'default_item_decimals' => 0, 'enable_online_payments' => 1,
        ],
        'session' => [], 'payments' => [], 'properties' => false, 'problems' => [],
    ];
    $model = new class () {
        public function get_by_id($id): object
        {
            return clone $GLOBALS['slate_document'];
        }

        public function where(...$args): self
        {
            return $this;
        }

        public function get(): self
        {
            return $this;
        }

        public function result(): array
        {
            return $GLOBALS['slate_items'];
        }
    };
    $GLOBALS['slate_ci'] = (object) [
        'load' => new class () {
            public function helper($name): void {}

            public function model($name): void {}
        },
        'session' => new class () {
            public function userdata($name)
            {
                return $GLOBALS['slate_fixture']['session'][$name] ?? null;
            }
        },
        'mdl_settings' => new class () {
            public function setting($name)
            {
                return get_setting($name);
            }
        },
        'db'                     => new SlateFixtureQuery(),
        'mdl_service_properties' => new SlateFixtureProperties(),
        'mdl_invoices'           => $model, 'mdl_quotes' => $model, 'mdl_items' => $model, 'mdl_quote_items' => $model,
    ];
}

function slate_fixture_data(string $type = 'invoice'): array
{
    $document = (object) [
        'client_name'               => 'Sample Property Group', 'client_surname' => '', 'client_title' => '',
        'client_address_1'          => '125 Garden Avenue', 'client_city' => 'Port Saint Lucie', 'client_state' => 'FL', 'client_zip' => '34986',
        'client_phone'              => '(772) 555-0142', 'client_email' => 'accounts@example.invalid',
        'user_company'              => 'Northfield Home Services', 'user_name' => 'Northfield Home Services',
        'user_address_1'            => 'PO Box 100', 'user_city' => 'Port Saint Lucie', 'user_state' => 'FL', 'user_zip' => '34985', 'user_country' => 'US',
        'user_phone'                => '(772) 555-0199', 'user_email' => 'hello@example.invalid', 'user_remittance_text' => '',
        $type . '_id'               => 17, $type . '_number' => $type === 'invoice' ? '0920170' : 'EST-0017',
        $type . '_date_created'     => '2026-02-11', 'invoice_date_due' => '2026-02-18', 'quote_date_expires' => '2026-03-11',
        $type . '_total'            => 450, $type . '_item_subtotal' => 450, $type . '_item_tax_total' => 0,
        $type . '_discount_percent' => 0, $type . '_discount_amount' => 0,
        'invoice_balance'           => 450, 'invoice_paid' => 0, 'invoice_status_id' => 2, 'quote_status_id' => 2,
        'invoice_terms'             => "Thank you for your business.\nPayment is due by the date shown above.",
        'notes'                     => "This estimate is valid until the expiration date shown above.\nPlease approve the estimate to schedule the work.",
    ];
    $items = [];
    foreach ([['Dryer service', 'Dryer pickup, installation, and haul-away', 150], ['Bathroom ventilation', 'Bathroom fan replacement', 300], ['Kitchen appliance service', 'Kitchen oven installation and haul-away', 0]] as [$name, $description, $amount]) {
        $items[] = (object) [
            'item_name'     => $name, 'item_description' => $description . "\nLocation: 125 Garden Avenue",
            'item_quantity' => 1, 'item_product_unit' => '', 'item_price' => $amount, 'item_discount' => 0,
            'item_total'    => $amount, 'item_subtotal' => $amount,
        ];
    }

    return [
        $type              => $document, 'items' => $items, $type . '_tax_rates' => [], 'legacy_calculation' => false,
        'payment_method'   => null, 'show_item_discounts' => false,
        $type . '_url_key' => 'fixture-document-key', 'attachments' => [], 'flash_message' => '',
    ];
}

function slate_fixture_render(string $type, bool $pdf, array $data): string
{
    $GLOBALS['slate_document'] = $data[$type];
    $GLOBALS['slate_items']    = $data['items'];
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        if ($type === 'invoice') {
            if ($pdf) {
                include APPPATH . 'views/invoice_templates/pdf/Slate.php';
            } else {
                include APPPATH . 'views/invoice_templates/public/Slate_Web.php';
            }
        } else {
            if ($pdf) {
                include APPPATH . 'views/quote_templates/pdf/Slate.php';
            } else {
                include APPPATH . 'views/quote_templates/public/Slate_Web.php';
            }
        }

        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}
