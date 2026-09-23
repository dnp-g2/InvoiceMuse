<?php

defined('BASEPATH') || exit('No direct script access allowed');
require_once APPPATH . 'libraries/Property_rules.php';

class Mdl_service_properties extends CI_Model
{
    private ?bool $available = null;

    private array $locks = [];

    public function installed(): bool
    {
        return $this->available ??= $this->db->table_exists('ip_property_installation') && $this->db->count_all('ip_property_installation') === 1;
    }

    public function document(string $type, int $id): ?object
    {
        $this->type($type);

        return $this->db->get_where('ip_' . $type . 's', [$type . '_id' => $id])->row() ?: null;
    }

    public function lock(string $name): void
    {
        $name = 'ip:' . $name;
        if (isset($this->locks[$name])) {
            return;
        }
        if ((int) $this->db->query('SELECT GET_LOCK(?, 5) acquired', [$name])->row()->acquired !== 1) {
            throw new RuntimeException('Another edit is in progress. Please retry.');
        }
        $this->locks[$name] = true;
        register_shutdown_function(function () use ($name) {
            $this->unlock($name);
        });
    }

    public function release(string $type, int $id): void
    {
        $this->unlock('ip:' . $type . ':' . $id);
    }

    public function properties(int $client, bool $active = false): array
    {
        if ( ! $this->installed()) {
            return [];
        }
        $this->db->where('client_id', $client);
        if ($active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('property_id')->get('ip_service_properties')->result_array();
    }

    public function save_property(int $client, ?int $id, array $input, int $revision = 0, bool $active = true, ?string $source = null): int
    {
        if ( ! $this->installed() || ! $this->db->get_where('ip_clients', ['client_id' => $client])->row()) {
            throw new InvalidArgumentException('Customer not found.');
        }
        $this->lock('client:' . $client);
        try {
            $data = Property_rules::address($input);
            $hash = Property_rules::hash($data);
            if ($id) {
                $old = $this->db->get_where('ip_service_properties', ['property_id' => $id, 'client_id' => $client])->row();
                if ( ! $old || (int) $old->revision !== $revision) {
                    throw new RuntimeException('Property changed. Reload before editing.');
                }
            }
            if ($active) {
                $dup = $this->db->get_where('ip_service_properties', ['client_id' => $client, 'active' => 1, 'address_hash' => $hash])->result();
                foreach ($dup as $p) {
                    if ((int) $p->property_id !== $id) {
                        throw new InvalidArgumentException('This customer already has that active service address.');
                    }
                }
            }
            $data += ['client_id' => $client, 'active' => (int) $active, 'address_hash' => $hash, 'updated_at' => gmdate('Y-m-d H:i:s'), 'revision' => $revision + 1];
            if ($id) {
                $ok = $this->db->where('property_id', $id)->update('ip_service_properties', $data);
            } else {
                $data['created_at']       = $data['updated_at'];
                $data['source_reference'] = $source;
                $ok                       = $this->db->insert('ip_service_properties', $data);
                $id                       = (int) $this->db->insert_id();
            }
            if ( ! $ok) {
                throw new RuntimeException('Unable to save property.');
            }

            return $id;
        } finally {
            $this->unlock('ip:client:' . $client);
        }
    }

    public function set_active(int $client, int $id, int $revision, bool $active): void
    {
        $this->lock('client:' . $client);
        try {
            $property = $this->db->get_where('ip_service_properties', ['client_id' => $client, 'property_id' => $id])->row_array();
            if ( ! $property) {
                throw new InvalidArgumentException('Property not found for this customer.');
            }
            $this->save_property($client, $id, $property, $revision, $active);
        } finally {
            $this->unlock('ip:client:' . $client);
        }
    }

    public function state(string $type, int $id): ?object
    {
        $this->type($type);
        if ( ! $this->installed()) {
            return null;
        }
        $state = $this->db->get_where('ip_property_documents', ['document_type' => $type, 'document_id' => $id])->row();
        if ( ! $state) {
            $cutoff = $this->db->get('ip_property_installation')->row();
            if ($id <= (int) $cutoff->{$type . '_cutoff'}) {
                return null;
            }
            $doc = $this->document($type, $id);
            if ( ! $doc) {
                throw new InvalidArgumentException('Document not found.');
            }
            $this->db->query('INSERT IGNORE INTO ip_property_documents (document_type,document_id,client_id,billing_snapshot) VALUES (?,?,?,?)', [$type, $id, $doc->client_id, $this->billing((int) $doc->client_id)]);
            $state = $this->db->get_where('ip_property_documents', ['document_type' => $type, 'document_id' => $id])->row();
        }

        return $state ?: null;
    }

    public function items(string $type, int $id): array
    {
        $this->type($type);

        return $this->db->order_by('item_order')->get_where('ip_' . $type . '_items', [$type . '_id' => $id])->result();
    }

    public function before_save(string $type, int $id, array $items, int $revision, int $status, bool $refresh = false, array $deletedIds = []): array
    {
        $state = $this->state($type, $id);
        foreach ($items as $item) {
            if ( ! is_object($item)) {
                throw new InvalidArgumentException('Invalid line item.');
            } unset($item->item_service_address);
        }
        if ( ! $state) {
            foreach ($items as $item) {
                unset($item->item_service_property_id);
            }

            return $items;
        }
        $this->lock($type . ':' . $id);
        $state = $this->state($type, $id);
        $doc   = $this->document($type, $id);
        if ((int) $state->revision !== $revision) {
            throw new RuntimeException('This document changed. Reload it before saving.');
        }
        $oldItems = array_column($this->db->get_where('ip_' . $type . '_items', [$type . '_id' => $id])->result_array(), null, 'item_id');
        $frozen   = (bool) $state->published || (int) $doc->{$type . '_status_id'} !== 1;
        if ($deletedIds && ($type !== 'invoice' || $frozen)) {
            throw new InvalidArgumentException('Issued property lines cannot be removed.');
        }
        foreach ($deletedIds as $deletedId) {
            if (!isset($oldItems[$deletedId])) {
                throw new InvalidArgumentException('Line not found. Reload before saving.');
            }
        }
        if ($frozen && $refresh) {
            throw new InvalidArgumentException('Issued document addresses cannot be refreshed.');
        }
        $properties = array_column($this->properties((int) $doc->client_id), null, 'property_id');
        $active     = array_values(array_filter($properties, fn ($p) => (int) $p['active'] === 1));
        $seen       = [];
        foreach ($items as $item) {
            if (empty($item->item_name)) {
                if ( ! empty($item->item_id)) {
                    throw new InvalidArgumentException('Saved lines need an item name; use Delete to remove a draft line.');
                } if ( ! empty($item->item_quantity) || ! empty($item->item_price)) {
                    throw new InvalidArgumentException('Every charge needs an item name.');
                } continue;
            }
            $iid = (int) ($item->item_id ?? 0);
            $old = $iid ? ($oldItems[$iid] ?? null) : null;
            if ($iid && ( ! $old || isset($seen[$iid]))) {
                throw new InvalidArgumentException('Invalid or duplicate invoice/quote line.');
            }
            if ($iid) {
                if (in_array($iid, $deletedIds, true)) {
                    throw new InvalidArgumentException('A charge cannot be saved and removed together.');
                }
                $seen[$iid] = true;
            }
            $item->{$type . '_id'} = $id;
            $pid                   = (int) ($item->item_service_property_id ?? 0);
            if ( ! $pid && ! $frozen && count($active) === 1) {
                $pid = (int) $active[0]['property_id'];
            }
            if ($frozen && ( ! $old || $pid !== (int) $old['item_service_property_id'])) {
                throw new InvalidArgumentException('Property assignments on issued documents cannot change. Copy to a draft instead.');
            }
            if ($pid) {
                $p = $properties[$pid] ?? null;
                if ( ! $p || ( ! $p['active'] && ! $frozen && ($status !== 1 || ! $old || $pid !== (int) $old['item_service_property_id']))) {
                    throw new InvalidArgumentException('Select an active property belonging to this customer.');
                }
                $item->item_service_address = ( ! $refresh && $old && $pid === (int) $old['item_service_property_id'] && $old['item_service_address']) ? $old['item_service_address'] : json_encode(Property_rules::address($p), JSON_THROW_ON_ERROR);
            } else {
                $item->item_service_address = null;
                if ($status !== 1) {
                    throw new InvalidArgumentException('Assign every line to a service property before issuing.');
                }
            }
            $item->item_service_property_id = $pid ?: null;
        }
        if ($status !== 1 && ! array_filter($items, fn ($i) => ! empty($i->item_name))) {
            throw new InvalidArgumentException('Add a charge before issuing.');
        }
        // Existing lines omitted from a stale/malicious payload cannot be silently published.
        if (count($seen) + count(array_unique($deletedIds)) !== count($oldItems)) {
            throw new InvalidArgumentException('Document lines changed. Reload before saving.');
        }
        $data = ['save_state' => 'saving'];
        if ($refresh) {
            $data['billing_snapshot'] = $this->billing((int) $doc->client_id);
        }
        $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', $data);

        return $items;
    }

    public function verify_line(string $type, int $id, $itemId, object $expected): void
    {
        if ( ! $this->state($type, $id)) {
            return;
        }
        $row = $this->db->get_where('ip_' . $type . '_items', [$type . '_id' => $id, 'item_id' => (int) $itemId])->row();
        if ( ! $row) {
            throw new RuntimeException('Line save failed. Reload and review this draft.');
        }
        foreach ((array) $expected as $key => $value) {
            if ($key === 'item_date_added') {
                continue;
            } // Managed by InvoicePlane when inserting a new line.
            if ( ! property_exists($row, $key) || ((string) $row->{$key} !== (string) $value && ! (is_numeric($row->{$key}) && is_numeric($value) && (float) $row->{$key} === (float) $value))) {
                throw new RuntimeException('Line verification failed for ' . preg_replace('/[^a-zA-Z0-9_]/', '', $key) . '. Reload and review this draft.');
            }
        }
    }

    public function finish(string $type, int $id, bool $success): ?int
    {
        $state = $this->state($type, $id);
        if ( ! $state) {
            return null;
        }
        if ($success) {
            $doc = $this->document($type, $id);
            if ( ! $doc || (int) $doc->client_id !== (int) $state->client_id) {
                throw new RuntimeException('Customer save did not finish. Reload and review this draft.');
            }
            $revision = (int) $state->revision + 1;
            $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', ['save_state' => 'ready', 'revision' => $revision, 'published' => (int) ((bool) $state->published || (int) $doc->{$type . '_status_id'} !== 1)]);
            $this->release($type, $id);

            return $revision;
        }
        $this->release($type, $id);

        return (int) $state->revision;
    }

    public function problems(string $type, int $id, bool $activeRequired = false): array
    {
        $s = $this->state($type, $id);
        if ( ! $s) {
            return $activeRequired && $this->installed() ? ['Copy this legacy invoice to a new draft and assign service properties before making it recurring.'] : [];
        }
        if ($s->save_state !== 'ready') {
            return ['A previous save did not finish. Review and save this draft again.'];
        }
        $doc = $this->document($type, $id);
        if ( ! $doc || (int) $doc->client_id !== (int) $s->client_id) {
            return ['Customer save did not finish. Reload and review this draft.'];
        }
        $items  = $this->items($type, $id);
        $issues = [];
        if ( ! $items) {
            $issues[] = 'Add at least one charge and its service property.';
        }
        foreach ($items as $item) {
            if ( ! $item->item_service_property_id || ! json_decode($item->item_service_address ?? '', true)) {
                $issues[] = 'Assign every charge to a service property.';
                continue;
            }
            $p = $this->db->get_where('ip_service_properties', ['property_id' => $item->item_service_property_id, 'client_id' => $s->client_id])->row();
            if ( ! $p || (($activeRequired || ! $s->published) && ! $p->active)) {
                $issues[] = 'A selected property is archived or missing. Select an active property.';
            }
        }

        return array_values(array_unique($issues));
    }

    public function assert_publishable(string $type, int $id): void
    {
        $issues = $this->problems($type, $id);
        if ($issues) {
            throw new RuntimeException(implode(' ', $issues));
        }
    }

    public function publish(string $type, int $id): void
    {
        if ( ! $this->state($type, $id)) {
            return;
        }
        $this->lock($type . ':' . $id);
        $this->assert_publishable($type, $id);
        $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', ['published' => 1]);
    }

    public function change_client(string $type, int $id, int $client): void
    {
        $state = $this->state($type, $id);
        if ( ! $state) {
            return;
        }
        $this->lock($type . ':' . $id);
        $state = $this->state($type, $id);
        $doc   = $this->document($type, $id);
        if ($state->published || (int) $doc->{$type . '_status_id'} !== 1) {
            throw new InvalidArgumentException('Only an unissued draft can change customer.');
        }
        $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', ['save_state' => 'saving']);
        $this->db->where($type . '_id', $id)->update('ip_' . $type . '_items', ['item_service_property_id' => null, 'item_service_address' => null]);
        $billing = $this->billing($client);
        $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', ['client_id' => $client, 'billing_snapshot' => $billing, 'revision' => (int) $state->revision + 1]);
    }

    public function delete_line(string $type, int $id, int $item, int $revision): void
    {
        $state = $this->state($type, $id);
        if ( ! $state) {
            return;
        }
        $this->lock($type . ':' . $id);
        $state = $this->state($type, $id);
        if ((int) $state->revision !== $revision) {
            throw new RuntimeException('Document changed. Reload before deleting a line.');
        }
        $doc = $this->document($type, $id);
        if ($state->published || (int) $doc->{$type . '_status_id'} !== 1) {
            throw new InvalidArgumentException('Issued property lines cannot be removed.');
        }
        if ( ! $this->db->get_where('ip_' . $type . '_items', [$type . '_id' => $id, 'item_id' => $item])->row()) {
            throw new InvalidArgumentException('Line not found.');
        }
        $this->db->where(['document_type' => $type, 'document_id' => $id])->update('ip_property_documents', ['save_state' => 'saving']);
    }

    public function begin_copy(string $sourceType, int $source, string $targetType, int $target): void
    {
        if ( ! $this->state($targetType, $target)) {
            return;
        }
        $this->lock($sourceType . ':' . $source);
        $this->lock($targetType . ':' . $target);
        $this->db->where(['document_type' => $targetType, 'document_id' => $target])->update('ip_property_documents', ['save_state' => 'saving']);
    }

    public function copy_fields(string $sourceType, int $source, string $targetType, int $target, object $item): array
    {
        $targetDoc = $this->document($targetType, $target);
        $sourceDoc = $this->document($sourceType, $source);
        if ( ! $this->state($targetType, $target)) {
            return [];
        }
        if ((int) $targetDoc->client_id !== (int) $sourceDoc->client_id) {
            return ['item_service_property_id' => null, 'item_service_address' => null];
        }

        return ['item_service_property_id' => $item->item_service_property_id ?? null, 'item_service_address' => $item->item_service_address ?? null];
    }

    public function copy_billing(string $sourceType, int $source, string $targetType, int $target): void
    {
        $s = $this->state($sourceType, $source);
        $t = $this->state($targetType, $target);
        if ($s && $t && (int) $s->client_id === (int) $t->client_id) {
            $this->db->where(['document_type' => $targetType, 'document_id' => $target])->update('ip_property_documents', ['billing_snapshot' => $s->billing_snapshot]);
        }
    }

    private function type(string $type): string
    {
        if ( ! in_array($type, ['invoice', 'quote'], true)) {
            throw new InvalidArgumentException('Invalid document type.');
        }

        return $type;
    }

    private function unlock(string $name): void
    {
        if (isset($this->locks[$name])) {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$name]);
            unset($this->locks[$name]);
        }
    }

    private function billing(int $client): string
    {
        $row = $this->db->get_where('ip_clients', ['client_id' => $client])->row_array();
        $out = [];
        foreach (['name', 'surname', 'company', 'address_1', 'address_2', 'city', 'state', 'zip', 'country'] as $k) {
            $out['client_' . $k] = $row['client_' . $k] ?? '';
        }

        return json_encode($out, JSON_THROW_ON_ERROR);
    }
}
