<?php

defined('BASEPATH') || exit('No direct script access allowed');
require_once APPPATH . 'libraries/Customer_match.php';
require_once APPPATH . 'libraries/Property_rules.php';

class Mdl_client_quick_create extends CI_Model
{
    private const SESSION_KEY = 'customer_quick_create_requests';

    public function new_request(): string
    {
        $requests = $this->session->userdata(self::SESSION_KEY) ?: [];
        $key = bin2hex(random_bytes(16));
        $requests[$key] = [];
        // Bound session storage. Expired dialogs must reload; they never blindly retry a save.
        $this->session->set_userdata(self::SESSION_KEY, array_slice($requests, -40, null, true));
        return $key;
    }

    public function create(array $input, string $key, bool $separate = false, string $matchVersion = ''): array
    {
        [$data, $errors] = $this->validate($input);
        if ($errors) {
            return ['success' => 0, 'validation_errors' => $errors];
        }
        $requests = $this->session->userdata(self::SESSION_KEY) ?: [];
        if (!preg_match('/^[a-f0-9]{32}$/', $key) || !array_key_exists($key, $requests)) {
            throw new RuntimeException('This form has expired. Close and reopen Create Invoice before saving.');
        }
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if (!empty($requests[$key]['result'])) {
            if (!hash_equals($requests[$key]['fingerprint'], $fingerprint)) {
                throw new RuntimeException('This customer was already saved. Return to the invoice before creating another customer.');
            }
            return $requests[$key]['result'];
        }
        $engines = $this->db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ip_clients','ip_user_clients','ip_service_properties')")->result_array();
        if (count($engines) !== 3 || array_filter($engines, fn($r) => $r['ENGINE'] !== 'InnoDB') || !service_properties()->installed()) {
            throw new RuntimeException('Customer creation is temporarily unavailable. Contact the administrator.');
        }
        if ((int)$this->db->query("SELECT GET_LOCK('ip:customer-review', 10) AS acquired")->row()->acquired !== 1) {
            throw new RuntimeException('Another customer is being saved. Please try again.');
        }
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $this->check($this->db->trans_begin());
            $matches = [];
            foreach ($this->db->order_by('client_id')->get('ip_clients')->result_array() as $customer) {
                if (Customer_match::matches($customer, $data['name'], $data['email'], $data['phone'])) {
                    $matches[] = ['id' => (int)$customer['client_id'], 'text' => trim($customer['client_name'] . ' ' . ($customer['client_surname'] ?? '')), 'email' => $customer['client_email'], 'phone' => $customer['client_phone'], 'active' => (bool)$customer['client_active']];
                }
            }
            $currentVersion = hash('sha256', json_encode([$data, $matches], JSON_THROW_ON_ERROR));
            if ($matches && (!$separate || !hash_equals($currentVersion, $matchVersion))) {
                $this->db->trans_rollback();
                return ['success' => 0, 'matches' => $matches, 'match_version' => $currentVersion, 'message' => 'A customer with similar details already exists. Choose one below or confirm this is a different customer.'];
            }
            $now = gmdate('Y-m-d H:i:s');
            $fields = ['client_name' => $data['name'], 'client_surname' => '', 'client_email' => $data['email'], 'client_phone' => $data['phone'], 'client_active' => 1, 'client_country' => 'US', 'client_language' => get_setting('default_language') ?: 'english', 'client_date_created' => $now, 'client_date_modified' => $now];
            if ($data['billing']) {
                foreach (['address_1', 'address_2', 'city', 'state', 'zip', 'country'] as $field) {
                    $fields['client_' . $field] = $data['billing'][$field];
                }
            }
            $this->check($this->db->insert('ip_clients', $fields));
            $id = (int)$this->db->insert_id();
            foreach ($this->db->get_where('ip_users', ['user_all_clients' => 1])->result() as $account) {
                $this->check($this->db->insert('ip_user_clients', ['user_id' => $account->user_id, 'client_id' => $id]));
            }
            if ($data['service']) {
                service_properties()->save_property($id, null, $data['service']);
            }
            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('Unable to confirm the save. Retry with the same details to check the result.');
            }
            $result = ['success' => 1, 'client' => ['id' => $id, 'text' => $data['name']]];
            $requests[$key] = ['fingerprint' => $fingerprint, 'result' => $result];
            $this->session->set_userdata(self::SESSION_KEY, $requests);
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->db_debug = $previousDebug;
            $this->db->query("SELECT RELEASE_LOCK('ip:customer-review')");
        }
    }

    private function validate(array $input): array
    {
        $errors = [];
        if (isset($input['same_billing']) && !in_array($input['same_billing'], ['0','1'], true)) { $errors['same_billing'] = 'Choose whether the billing address is the same.'; }
        $read = static function (string $field, int $max, string $default = '') use ($input, &$errors): string {
            $value = $input[$field] ?? $default;
            if (!is_string($value) || mb_strlen($value) > $max || preg_match('/[\x00-\x1f<>]/u', $value)) {
                $errors[$field] = 'Enter a valid value of up to ' . $max . ' characters.';
                return '';
            }
            return trim($value);
        };
        $name = $read('full_name', 150);
        $email = $read('email', 254);
        $phone = $read('phone', 30);
        if ($name === '') { $errors['full_name'] = 'Enter the customer’s full name.'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Enter a valid email address.'; }
        if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) { $errors['phone'] = 'Enter a valid phone number.'; }
        $addresses = [];
        foreach (['service', 'billing'] as $prefix) {
            if ($prefix === 'billing' && ($input['same_billing'] ?? '1') === '1') {
                $addresses['billing'] = $addresses['service'];
                continue;
            }
            $address = [];
            foreach (['address_1' => 150, 'address_2' => 150, 'city' => 100, 'state' => 100, 'zip' => 20, 'country' => 2] as $field => $max) {
                $address[$field] = $read($prefix . '_' . $field, $max, $field === 'country' ? 'US' : '');
            }
            $started = implode('', array_intersect_key($address, array_flip(['address_1','address_2','city','state','zip']))) !== '';
            if (!$started) { $addresses[$prefix] = null; continue; }
            foreach (['address_1','city','state','zip','country'] as $field) {
                if ($address[$field] === '') { $errors[$prefix . '_' . $field] = 'Complete this address field, or clear the address to add it later.'; }
            }
            try { $addresses[$prefix] = Property_rules::address($address); }
            catch (InvalidArgumentException $e) { $errors[$prefix . '_address_1'] = $e->getMessage(); $addresses[$prefix] = null; }
        }
        return [['name' => $name, 'email' => $email, 'phone' => $phone, 'service' => $addresses['service'], 'billing' => $addresses['billing']], $errors];
    }

    private function check($ok): void
    {
        if (!$ok) { throw new RuntimeException('Unable to save the customer. No changes were saved; please try again.'); }
    }
}
