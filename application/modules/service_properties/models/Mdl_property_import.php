<?php

defined('BASEPATH') || exit('No direct script access allowed');
require_once APPPATH . 'libraries/Property_import_note.php';
/** CLI-only import of an explicitly reviewed manifest. No customer messages are sent. */
class Mdl_property_import extends CI_Model
{
    public function run(array $manifest, bool $apply = false): array
    {
        $m = service_properties();
        if ( ! $m->installed()) {
            throw new RuntimeException('Install the property schema first.');
        }
        $m->lock('approved-import');
        $result = [];
        // Preflight every entry before writing any customer data.
        foreach ($manifest as $entry) {
            $r = $this->db->get_where('ip_client_update_requests', ['request_id' => $entry['request_id'], 'request_reference' => $entry['reference']])->row_array();
            if ( ! $r) {
                throw new RuntimeException('Reviewed request not found.');
            }
            foreach ($entry['expected'] as $k => $v) {
                if ( ! array_key_exists($k, $r) || (string) $r[$k] !== (string) $v) {
                    throw new RuntimeException('A reviewed submission changed; stop and review.');
                }
            }
            foreach ($entry['properties'] as $address) {
                Property_rules::address($address);
            }
            if ( ! empty($entry['client_id'])) {
                $c = $this->db->get_where('ip_clients', ['client_id' => $entry['client_id']])->row_array();
                foreach ($entry['fields'] as $k => $v) {
                    if ((string) ($c[$k] ?? '') !== (string) $v) {
                        throw new RuntimeException('An existing customer changed; stop and review.');
                    }
                }
            } else {
                $matches = $this->db->group_start()->where('client_email', $entry['fields']['client_email'])->or_where('client_name', $entry['fields']['client_name'])->group_end()->get('ip_clients')->result_array();
                if (count($matches) > 1) {
                    throw new RuntimeException('Ambiguous customer match; stop and review.');
                }
                if ($matches) {
                    foreach ($entry['fields'] as $k => $v) {
                        if ((string) ($matches[0][$k] ?? '') !== (string) $v) {
                            throw new RuntimeException('Potential duplicate customer needs review.');
                        }
                    }
                }
            }
        }
        foreach ($manifest as $entry) {
            $cid = (int) ($entry['client_id'] ?? 0);
            if ( ! $cid) {
                $c   = $this->db->get_where('ip_clients', ['client_email' => $entry['fields']['client_email'], 'client_name' => $entry['fields']['client_name']])->row();
                $cid = $c ? (int) $c->client_id : 0;
            }
            if ( ! $apply) {
                $result[] = ['reference' => $entry['reference'], 'client_id' => $cid ?: null, 'action' => $cid ? 'add missing properties' : 'create customer and properties', 'property_count' => count($entry['properties'])];
                continue;
            }
            if ( ! $cid) {
                $fields                         = $entry['fields'];
                $fields['client_date_created']  = gmdate('Y-m-d H:i:s');
                $fields['client_date_modified'] = $fields['client_date_created'];
                if ( ! $this->db->insert('ip_clients', $fields)) {
                    throw new RuntimeException('Customer creation failed; inspect before retry.');
                }
                $cid = (int) $this->db->insert_id();
            }
            $created = [];
            foreach ($entry['properties'] as $index => $address) {
                $source    = $entry['reference'] . ':' . ($index + 1);
                $canonical = Property_rules::address($address);
                $p         = $this->db->get_where('ip_service_properties', ['client_id' => $cid, 'source_reference' => $source])->row_array();
                if ( ! $p) {
                    $equivalent = $this->db->get_where('ip_service_properties', ['client_id' => $cid, 'active' => 1, 'address_hash' => Property_rules::hash($canonical)])->row_array();
                    if ($equivalent) {
                        $pid = (int) $equivalent['property_id'];
                    } else {
                        $pid = $m->save_property($cid, null, $canonical, 0, true, $source);
                    }
                } else {
                    if (Property_rules::hash($p) !== Property_rules::hash($canonical) || ! $p['active']) {
                        throw new RuntimeException('Previously imported property changed; inspect before retry.');
                    }
                    $pid = (int) $p['property_id'];
                }
                $created[] = $pid;
            }
            $note       = Property_import_note::format($entry);
            $legacyNote = 'Service properties imported from ' . $entry['reference'] . '. Original submitted details: ' . json_encode($entry['expected'], JSON_THROW_ON_ERROR);
            if ( ! $this->db->get_where('ip_client_notes', ['client_id' => $cid, 'client_note' => $note])->row()) {
                $legacy = $this->db->get_where('ip_client_notes', ['client_id' => $cid, 'client_note' => $legacyNote])->row();
                $saved  = $legacy
                    ? $this->db->where('client_note_id', $legacy->client_note_id)->update('ip_client_notes', ['client_note' => $note])
                    : $this->db->insert('ip_client_notes', ['client_id' => $cid, 'client_note_date' => gmdate('Y-m-d'), 'client_note' => $note]);
                if ( ! $saved) {
                    throw new RuntimeException('Source note save failed; request remains pending.');
                }
            }
            $current = $this->db->get_where('ip_clients', ['client_id' => $cid])->row_array();
            foreach ($entry['fields'] as $k => $v) {
                if ((string) $current[$k] !== (string) $v) {
                    throw new RuntimeException('Customer verification failed.');
                }
            }
            if ( ! empty($entry['resolve'])) {
                $r    = $this->db->get_where('ip_client_update_requests', ['request_id' => $entry['request_id']])->row();
                $note = 'Created client #' . $cid . ' with ' . count($created) . ' service properties after owner approval. Original submission preserved in client notes; billing address remains the primary address.';
                if ($r->status === 'pending') {
                    if ( ! $this->db->where(['request_id' => $entry['request_id'], 'status' => 'pending'])->update('ip_client_update_requests', ['status' => 'resolved', 'resolution_note' => $note, 'resolved_at' => gmdate('Y-m-d H:i:s'), 'resolved_by' => null])) {
                        throw new RuntimeException('Resolution failed.');
                    }
                } elseif ($r->resolution_note !== $note) {
                    throw new RuntimeException('Request was resolved differently; inspect before retry.');
                }
            }
            $result[] = ['reference' => $entry['reference'], 'client_id' => $cid, 'property_ids' => $created];
        }

        return $result;
    }
}
