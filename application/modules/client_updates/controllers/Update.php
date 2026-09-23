<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Update extends Base_Controller
{
    private const SESSION_FORM_STARTED = 'client_update_form_started_at';

    private const SESSION_LAST_SUBMIT = 'client_update_last_submitted_at';

    public function __construct()
    {
        parent::__construct();

        $this->load->helper('client_updates/client_update');
        $this->assert_allowed_host();
        $this->load->model('client_updates/mdl_client_updates');
        $this->set_security_headers();
    }

    public function index(): void
    {
        if ( ! $this->mdl_client_updates->is_installed()) {
            show_error('The client update form is temporarily unavailable.', 503);
        }

        $this->mdl_client_updates->purge_expired();
        $errors                       = [];
        $additional_service_addresses = [];
        $turnstile                    = $this->turnstile_config();

        if ($this->input->method() === 'post') {
            if ((string) $this->input->post('website') !== '') {
                $this->complete_without_storing();
            }

            $this->set_validation_rules();

            $started_at = (int) $this->session->userdata(self::SESSION_FORM_STARTED);
            $last_submit = (int) $this->session->userdata(self::SESSION_LAST_SUBMIT);

            if ($started_at === 0 || time() - $started_at < 2) {
                $errors[] = 'Please wait a moment, then submit the form again.';
            }

            if ($last_submit > 0 && time() - $last_submit < 60) {
                $errors[] = 'A request was just submitted. Please wait before trying again.';
            }

            $form_is_valid = $this->form_validation->run();
            if ($form_is_valid && preg_match('/^[0-9+().\-\s]{7,30}$/', $this->posted('phone')) !== 1) {
                $errors[] = 'Please enter a valid phone number.';
            }

            [$additional_service_addresses, $address_errors] = $this->additional_service_addresses();
            $errors = array_merge($errors, $address_errors);

            if ($form_is_valid && $errors === [] && $turnstile['enabled'] && ! $this->turnstile_is_valid($turnstile['secret_key'])) {
                $errors[] = 'Please complete the security check and try again.';
            }

            if ($form_is_valid && $errors === []) {
                $reference = $this->new_reference();
                $same       = $this->input->post('mailing_same_as_service') === '1';

                $request = [
                    'request_reference'            => $reference,
                    'full_name'                    => $this->posted('full_name'),
                    'service_address_1'            => $this->posted('service_address_1'),
                    'service_address_2'            => $this->nullable_posted('service_address_2'),
                    'service_city'                 => $this->posted('service_city'),
                    'service_state'                => $this->posted('service_state'),
                    'service_zip'                  => $this->posted('service_zip'),
                    'additional_service_addresses' => $additional_service_addresses === []
                        ? null
                        : json_encode($additional_service_addresses, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'mailing_same_as_service'      => $same ? 1 : 0,
                    'mailing_address_1'            => $same ? null : $this->nullable_posted('mailing_address_1'),
                    'mailing_address_2'            => $same ? null : $this->nullable_posted('mailing_address_2'),
                    'mailing_city'                 => $same ? null : $this->nullable_posted('mailing_city'),
                    'mailing_state'                => $same ? null : $this->nullable_posted('mailing_state'),
                    'mailing_zip'                  => $same ? null : $this->nullable_posted('mailing_zip'),
                    'phone'                        => $this->posted('phone'),
                    'email'                        => mb_strtolower($this->posted('email')),
                    'status'                       => 'pending',
                    'submitted_at'                 => gmdate('Y-m-d H:i:s'),
                ];

                $request_id = $this->mdl_client_updates->create($request);
                if ($request_id < 1) {
                    log_message('error', 'Client update could not be saved.');
                    show_error('The client update form is temporarily unavailable.', 503);
                }

                try {
                    $this->send_notifications($request_id, $request, $additional_service_addresses);
                } catch (Throwable) {
                    log_message('error', "Client update email failed for {$reference}: unexpected error.");
                }

                $this->session->set_userdata(self::SESSION_LAST_SUBMIT, time());
                $this->session->unset_userdata(self::SESSION_FORM_STARTED);
                $this->session->set_flashdata('client_update_reference', $reference);
                redirect($this->public_url('/update/thank-you'));
            }
        } else {
            $this->session->set_userdata(self::SESSION_FORM_STARTED, time());
        }

        $this->load->view('client_updates/public_form', [
            'extra_errors'                => $errors,
            'additional_service_addresses' => $additional_service_addresses,
            'turnstile_enabled'           => $turnstile['enabled'],
            'turnstile_site_key'          => $turnstile['site_key'],
        ]);
    }

    public function thank_you(): void
    {
        $this->load->view('client_updates/thank_you', [
            'reference' => (string) $this->session->flashdata('client_update_reference'),
        ]);
    }

    private function set_validation_rules(): void
    {
        $this->form_validation->set_rules('full_name', 'Full name', 'trim|required|max_length[150]');
        $this->form_validation->set_rules('service_address_1', 'Service street address', 'trim|required|max_length[150]');
        $this->form_validation->set_rules('service_address_2', 'Service address line 2', 'trim|max_length[150]');
        $this->form_validation->set_rules('service_city', 'Service city', 'trim|required|max_length[100]');
        $this->form_validation->set_rules('service_state', 'Service state', 'trim|required|max_length[100]');
        $this->form_validation->set_rules('service_zip', 'Service ZIP code', 'trim|required|max_length[20]');
        $this->form_validation->set_rules('phone', 'Phone number', 'trim|required|max_length[30]');
        $this->form_validation->set_rules('email', 'Email address', 'trim|required|valid_email|max_length[254]');

        if ($this->input->post('mailing_same_as_service') !== '1') {
            $this->form_validation->set_rules('mailing_address_1', 'Mailing street address', 'trim|required|max_length[150]');
            $this->form_validation->set_rules('mailing_address_2', 'Mailing address line 2', 'trim|max_length[150]');
            $this->form_validation->set_rules('mailing_city', 'Mailing city', 'trim|required|max_length[100]');
            $this->form_validation->set_rules('mailing_state', 'Mailing state', 'trim|required|max_length[100]');
            $this->form_validation->set_rules('mailing_zip', 'Mailing ZIP code', 'trim|required|max_length[20]');
        }
    }

    private function posted(string $field): string
    {
        return trim((string) $this->input->post($field));
    }

    private function nullable_posted(string $field): ?string
    {
        $value = $this->posted($field);

        return $value !== '' ? $value : null;
    }

    private function additional_service_addresses(): array
    {
        $posted = $this->input->post('additional_properties');

        if ($posted === null || $posted === '') {
            return [[], []];
        }

        if ( ! is_array($posted)) {
            return [[], ['The additional service addresses are invalid.']];
        }

        $errors = [];
        if (count($posted) > 9) {
            $errors[] = 'You may enter up to 10 service addresses.';
        }

        $addresses = [];
        foreach (array_slice($posted, 0, 9) as $index => $address) {
            if ( ! is_array($address)) {
                $errors[] = 'Additional service address ' . ($index + 2) . ' is invalid.';
                continue;
            }

            $normalized = [];
            foreach (['address_1', 'address_2', 'city', 'state', 'zip'] as $field) {
                $value = $address[$field] ?? '';
                if ( ! is_scalar($value)) {
                    $errors[] = 'Additional service address ' . ($index + 2) . ' contains an invalid value.';
                    $value = '';
                }
                $normalized[$field] = trim((string) $value);
            }

            if (implode('', $normalized) === '') {
                continue;
            }

            $number = $index + 2;
            if ($normalized['address_1'] === '' || mb_strlen($normalized['address_1']) > 150) {
                $errors[] = "Service address {$number}: enter a street address of 150 characters or fewer.";
            }
            if (mb_strlen($normalized['address_2']) > 150) {
                $errors[] = "Service address {$number}: address line 2 must be 150 characters or fewer.";
            }
            if ($normalized['city'] === '' || mb_strlen($normalized['city']) > 100) {
                $errors[] = "Service address {$number}: enter a city of 100 characters or fewer.";
            }
            if ($normalized['state'] === '' || mb_strlen($normalized['state']) > 100) {
                $errors[] = "Service address {$number}: enter a state of 100 characters or fewer.";
            }
            if ($normalized['zip'] === '' || mb_strlen($normalized['zip']) > 20) {
                $errors[] = "Service address {$number}: enter a ZIP code of 20 characters or fewer.";
            }

            $addresses[] = $normalized;
        }

        return [$addresses, $errors];
    }

    private function turnstile_config(): array
    {
        $site_key   = function_exists('env') ? trim((string) env('TURNSTILE_SITE_KEY', '')) : '';
        $secret_key = function_exists('env') ? trim((string) env('TURNSTILE_SECRET_KEY', '')) : '';

        return [
            'enabled'    => $site_key !== '' && $secret_key !== '',
            'site_key'   => $site_key,
            'secret_key' => $secret_key,
        ];
    }

    private function send_notifications(int $request_id, array $request, array $additional_service_addresses): void
    {
        $reference = (string) $request['request_reference'];

        $this->load->helper(['mailer', 'mailer/phpmailer']);
        if ( ! mailer_configured()) {
            log_message('error', "Client update emails skipped for {$reference}: mailer is not configured.");

            return;
        }

        $profile = client_update_profile();
        $from    = trim((string) get_setting('smtp_mail_from'));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $from = $profile['email_from'];
        }
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            log_message('error', "Client update emails skipped for {$reference}: no valid sender address is configured.");

            return;
        }

        $internal_recipients = [
            'primary recipient' => $profile['email_to'],
            'copy recipient'    => $profile['email_copy_to'],
        ];

        try {
            $internal_message = $this->load->view('client_updates/email_notification', [
                'request'                      => $request,
                'additional_service_addresses' => $additional_service_addresses,
                'review_url'                   => $this->public_url('/client-updates/view/' . $request_id),
            ], true);

            foreach ($internal_recipients as $label => $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                    log_message('error', "Client update email skipped for {$reference}: {$label} is not configured.");
                    continue;
                }

                $this->deliver_email(
                    $from,
                    $recipient,
                    'Client information update: ' . $reference,
                    $internal_message,
                    $reference,
                    $label
                );
            }
        } catch (Throwable) {
            log_message('error', "Internal client update emails failed for {$reference}: rendering error.");
        }

        try {
            $customer_message = $this->load->view('client_updates/customer_confirmation_email', [
                'first_name' => $this->first_name((string) $request['full_name']),
                'request'    => $request,
            ], true);

            $this->deliver_email(
                $from,
                (string) $request['email'],
                'We received your information update: ' . $reference,
                $customer_message,
                $reference,
                'customer confirmation'
            );
        } catch (Throwable) {
            log_message('error', "Client update confirmation failed for {$reference}: rendering error.");
        }
    }

    private function first_name(string $full_name): string
    {
        $parts = preg_split('/\s+/u', trim($full_name), 2);

        return is_array($parts) && isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'there';
    }

    private function deliver_email(
        string $from,
        string $recipient,
        string $subject,
        string $message,
        string $reference,
        string $label
    ): void {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            log_message('error', "Client update email skipped for {$reference}: {$label} address is invalid.");

            return;
        }

        try {
            $sent = phpmail_send(
                [$from, client_update_profile()['business_name']],
                $recipient,
                $subject,
                $message
            );
        } catch (Throwable) {
            log_message('error', "Client update email failed for {$reference}: {$label} transport exception.");

            return;
        }

        if ( ! $sent) {
            log_message('error', "Client update email failed for {$reference}: {$label}.");
        }
    }

    private function turnstile_is_valid(string $secret_key): bool
    {
        $token = trim((string) $this->input->post('cf-turnstile-response'));
        if ($token === '') {
            return false;
        }

        $handle = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        if ($handle === false) {
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $secret_key,
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);

        $response = curl_exec($handle);
        $status   = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ( ! is_string($response) || $status !== 200) {
            return false;
        }

        $result       = json_decode($response, true);
        $hostname     = is_array($result) ? mb_strtolower((string) ($result['hostname'] ?? '')) : '';
        $allowed_host = client_update_profile()['host'];

        return is_array($result)
            && ($result['success'] ?? false) === true
            && ($result['action'] ?? '') === 'client_update'
            && $allowed_host !== ''
            && $hostname === $allowed_host;
    }

    private function new_reference(): string
    {
        do {
            $reference = client_update_profile()['reference_prefix'] . '-' . mb_strtoupper(bin2hex(random_bytes(4)));
        } while ($this->mdl_client_updates->reference_exists($reference));

        return $reference;
    }

    private function complete_without_storing(): void
    {
        $this->session->set_flashdata('client_update_reference', '');
        redirect($this->public_url('/update/thank-you'));
    }

    private function public_url(string $path): string
    {
        return 'https://' . (string) $this->input->server('HTTP_HOST') . $path;
    }

    private function assert_allowed_host(): void
    {
        $host         = mb_strtolower(explode(':', (string) $this->input->server('HTTP_HOST'))[0]);
        $allowed_host = client_update_profile()['host'];

        if ($allowed_host !== '' && $host === $allowed_host) {
            return;
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production' && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return;
        }

        show_404();
    }

    private function set_security_headers(): void
    {
        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Referrer-Policy: no-referrer')
            ->set_header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; connect-src https://challenges.cloudflare.com; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }
}
