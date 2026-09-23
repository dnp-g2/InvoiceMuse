<?php

defined('BASEPATH') || exit('No direct script access allowed');
require_once APPPATH . 'libraries/Property_rules.php';
require_once APPPATH . 'libraries/Customer_match.php';
require_once APPPATH . 'libraries/Property_import_note.php';

/** Admin-reviewed submission application. All writes commit together. */
class Mdl_client_update_review extends CI_Model
{
    private const TABLES = ['ip_clients', 'ip_client_notes', 'ip_user_clients', 'ip_client_update_requests', 'ip_service_properties'];

    public function ready(): bool
    {
        foreach (['applied_client_id', 'applied_at', 'applied_by'] as $column) {
            if (!$this->db->field_exists($column, 'ip_client_update_requests')) {
                return false;
            }
        }
        if (!service_properties()->installed()) {
            return false;
        }
        $tables = $this->db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->result();
        $ready = [];
        foreach ($tables as $table) {
            if (in_array($table->TABLE_NAME, self::TABLES, true) && $table->ENGINE === 'InnoDB') {
                $ready[] = $table->TABLE_NAME;
            }
        }
        return count($ready) === count(self::TABLES);
    }

    public function review(int $id, int $client = 0): array
    {
        $request = $this->db->get_where('ip_client_update_requests', ['request_id' => $id])->row_array();
        if (!$request) {
            throw new InvalidArgumentException('Submission not found.');
        }
        $clients = $this->db->order_by('client_name')->get('ip_clients')->result_array();
        $selected = null;
        $matches = [];
        foreach ($clients as $row) {
            if ((int)$row['client_id'] === $client) {
                $selected = $row;
            }
            if (Customer_match::matches($row, $request['full_name'], $request['email'], $request['phone'])) {
                $matches[] = $row;
            }
        }
        if ($client && !$selected) {
            throw new InvalidArgumentException('Customer not found.');
        }
        $prefix = (int)$request['mailing_same_as_service'] === 1 ? 'service_' : 'mailing_';
        $groups = [
            'name' => ['client_name' => trim($request['full_name']), 'client_surname' => ''],
            'email' => ['client_email' => trim($request['email'])],
            'phone' => ['client_phone' => trim($request['phone'])],
            'billing' => [
                'client_address_1' => $request[$prefix . 'address_1'], 'client_address_2' => $request[$prefix . 'address_2'] ?? '',
                'client_city' => $request[$prefix . 'city'], 'client_state' => $request[$prefix . 'state'],
                'client_zip' => $request[$prefix . 'zip'], 'client_country' => 'US',
            ],
            'active' => ['client_active' => 1],
        ];
        $addresses = [[
            'address_1' => $request['service_address_1'], 'address_2' => $request['service_address_2'] ?? '',
            'city' => $request['service_city'], 'state' => $request['service_state'], 'zip' => $request['service_zip'], 'country' => 'US',
        ]];
        $validation_errors = [];
        try {
            $additional = json_decode($request['additional_service_addresses'] ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($additional) || count($additional) > 9) {
                throw new InvalidArgumentException('Invalid additional service addresses.');
            }
            foreach ($additional as $address) {
                if (!is_array($address)) {
                    throw new InvalidArgumentException('Invalid service address.');
                }
                $addresses[] = $address + ['country' => 'US'];
            }
        } catch (Throwable $e) {
            $validation_errors[] = 'The additional service addresses are invalid. Correct the source information before applying this request.';
        }
        $properties = $client ? service_properties()->properties($client) : [];
        $additions = [];
        $seen = [];
        foreach ($properties as $property) {
            if ($property['active']) {
                $seen[Property_rules::hash($property)] = true;
            }
        }
        foreach ($addresses as $index => $address) {
            try {
                $address = Property_rules::address($address);
            } catch (InvalidArgumentException $e) {
                $validation_errors[] = $e->getMessage();
                continue;
            }
            $hash = Property_rules::hash($address);
            if (!isset($seen[$hash])) {
                $additions[] = ['address' => $address, 'source' => $request['request_reference'] . ':' . ($index + 1)];
                $seen[$hash] = true;
            }
        }
        $version = hash('sha256', json_encode([$request, $selected, $properties, $matches], JSON_THROW_ON_ERROR));
        return compact('request', 'clients', 'selected', 'matches', 'groups', 'additions', 'version', 'validation_errors');
    }

    public function apply(int $id, int $client, array $chosen, string $version, int $user, bool $separate = false): int
    {
        if (!$this->ready()) {
            throw new RuntimeException('Customer review installation is incomplete. No changes were saved.');
        }
        if ((int)$this->db->query("SELECT GET_LOCK('ip:customer-review', 10) AS acquired")->row()->acquired !== 1) {
            throw new RuntimeException('Another customer review is being saved. Please retry.');
        }
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $clientLock = false;
        try {
            if ($client) {
                if ((int)$this->db->query('SELECT GET_LOCK(?, 10) AS acquired', ['ip:client:' . $client])->row()->acquired !== 1) {
                    throw new RuntimeException('Customer is being edited. Please retry.');
                }
                $clientLock = true;
            }
            $this->check($this->db->trans_begin());
            $request = $this->db->query('SELECT * FROM ip_client_update_requests WHERE request_id=? FOR UPDATE', [$id])->row_array();
            if (!$request) {
                throw new InvalidArgumentException('Submission not found.');
            }
            if (!empty($request['applied_client_id'])) {
                $saved = (int)$request['applied_client_id'];
                if (!$this->db->get_where('ip_clients', ['client_id' => $saved])->row()) {
                    throw new RuntimeException('The previously linked customer no longer exists. Review manually.');
                }
                $this->check($this->db->where('request_id', $id)->update('ip_client_update_requests', ['status' => 'resolved']));
                $this->commit();
                return $saved;
            }
            if ($client) {
                $this->db->query('SELECT client_id FROM ip_clients WHERE client_id=? FOR UPDATE', [$client]);
            }
            $review = $this->review($id, $client);
            if (!hash_equals($review['version'], $version)) {
                throw new RuntimeException('This review changed. Reload and review it again before saving.');
            }
            if ($review['validation_errors']) {
                throw new InvalidArgumentException(implode(' ', $review['validation_errors']));
            }
            if (!$client && $review['matches'] && !$separate) {
                throw new InvalidArgumentException('Choose an existing customer or confirm this is a different customer.');
            }
            if (array_diff($chosen, array_keys($review['groups']))) {
                throw new InvalidArgumentException('Invalid customer fields.');
            }
            $fields = [];
            foreach ($review['groups'] as $key => $values) {
                if (!$client || in_array($key, $chosen, true)) {
                    $fields = array_merge($fields, $values);
                }
            }
            if (!filter_var($review['groups']['email']['client_email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('The submitted email address is invalid.');
            }
            $billing = $review['groups']['billing'];
            Property_rules::address(['address_1' => $billing['client_address_1'], 'address_2' => $billing['client_address_2'], 'city' => $billing['client_city'], 'state' => $billing['client_state'], 'zip' => $billing['client_zip'], 'country' => 'US']);
            if (trim($review['groups']['name']['client_name']) === '') {
                throw new InvalidArgumentException('A customer name is required.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $fields['client_date_modified'] = $now;
            if (!$client) {
                $fields['client_date_created'] = $now;
                $fields['client_language'] = get_setting('default_language') ?: 'english';
                $this->check($this->db->insert('ip_clients', $fields));
                $client = (int)$this->db->insert_id();
                foreach ($this->db->get_where('ip_users', ['user_all_clients' => 1])->result() as $account) {
                    $this->check($this->db->insert('ip_user_clients', ['user_id' => $account->user_id, 'client_id' => $client]));
                }
            } else {
                $this->check($this->db->where('client_id', $client)->update('ip_clients', $fields));
            }
            foreach ($review['additions'] as $addition) {
                service_properties()->save_property($client, null, $addition['address'], 0, true, $addition['source']);
            }
            $note = Property_import_note::format(['reference' => $request['request_reference'], 'expected' => $request]);
            if ($request['status'] === 'resolved') {
                $note .= "\n\nPrevious resolution: " . ($request['resolved_at'] ?? '') . ' UTC. ' . ($request['resolution_note'] ?: 'No note recorded.');
            }
            $this->check($this->db->insert('ip_client_notes', ['client_id' => $client, 'client_note_date' => gmdate('Y-m-d'), 'client_note' => $note]));
            $this->check($this->db->where('request_id', $id)->update('ip_client_update_requests', [
                'status' => 'resolved', 'applied_client_id' => $client, 'applied_at' => $now, 'applied_by' => $user,
                'resolution_note' => 'Saved to customer #' . $client . '. Original submission preserved in customer notes.',
                'resolved_at' => $now, 'resolved_by' => $user,
            ]));
            $this->commit();
            return $client;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->db_debug = $previousDebug;
            if ($clientLock) {
                $this->db->query('SELECT RELEASE_LOCK(?)', ['ip:client:' . $client]);
            }
            $this->db->query("SELECT RELEASE_LOCK('ip:customer-review')");
        }
    }

    public function dismiss(int $id, string $version, string $note, int $user): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Customer review installation is incomplete.');
        }
        if (trim($note) === '' || mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('Enter a reason of up to 1,000 characters.');
        }
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->check($this->db->trans_begin());
        try {
            $this->db->query('SELECT request_id FROM ip_client_update_requests WHERE request_id=? FOR UPDATE', [$id]);
            $review = $this->review($id);
            if (!hash_equals($review['version'], $version) || $review['request']['status'] !== 'pending' || !empty($review['request']['applied_client_id'])) {
                throw new RuntimeException('This request changed. Reload before dismissing it.');
            }
            $this->check($this->db->where('request_id', $id)->update('ip_client_update_requests', ['status' => 'resolved', 'resolution_note' => 'Dismissed without customer changes: ' . trim($note), 'resolved_by' => $user, 'resolved_at' => gmdate('Y-m-d H:i:s')]));
            $this->commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->db_debug = $previousDebug;
        }
    }

    private function check($ok): void
    {
        if (!$ok) {
            throw new RuntimeException('Unable to save the customer. No changes were saved; please retry.');
        }
    }

    private function commit(): void
    {
        if (!$this->db->trans_status() || !$this->db->trans_commit()) {
            throw new RuntimeException('Unable to complete the save. Reload to check the result before retrying.');
        }
    }
}
