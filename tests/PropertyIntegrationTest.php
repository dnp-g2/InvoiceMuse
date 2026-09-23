<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PropertyIntegrationTest extends TestCase
{
    private $ci;

    private $m;

    private int $client;

    private int $pid;

    protected function setUp(): void
    {
        if (getenv('PROPERTY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set PROPERTY_INTEGRATION=1 with the isolated fixture.');
        }
        $this->ci = &get_instance();
        $this->m  = service_properties();
        $this->ci->db->insert('ip_clients', ['client_name' => 'Fixture ' . uniqid(), 'client_date_created' => date('Y-m-d H:i:s'), 'client_date_modified' => date('Y-m-d H:i:s'), 'client_address_1' => '100 Billing Lane', 'client_city' => 'Springfield', 'client_state' => 'IL', 'client_zip' => '62701', 'client_country' => 'US']);
        $this->client = (int) $this->ci->db->insert_id();
        $this->pid    = $this->m->save_property($this->client, null, $this->address());
    }

    #[Test]
    public function it_defaults_single_property_and_preserves_snapshot_after_edit(): void
    {
        $id    = $this->doc();
        $items = $this->save($id, [$this->line()]);
        self::assertSame($this->pid, $items[0]->item_service_property_id);
        $original = $items[0]->item_service_address;
        $this->m->save_property($this->client, $this->pid, $this->address('999 Changed Road'), 1);
        $items = $this->save($id, $items, 1, 2);
        self::assertSame($original, $items[0]->item_service_address);
        $items = $this->save($id, $items, 1, 3, true);
        self::assertStringContainsString('999 Changed Road', $items[0]->item_service_address);
    }

    #[Test]
    public function it_rejects_duplicate_property_and_allows_archival(): void
    {
        $this->m->save_property($this->client, $this->pid, $this->address(), 1, false);
        $new = $this->m->save_property($this->client, null, $this->address());
        self::assertNotSame($this->pid, $new);
        $this->expectException(InvalidArgumentException::class);
        $this->m->save_property($this->client, $this->pid, $this->address(), 2, true);
    }

    #[Test]
    public function it_requires_selection_for_multiple_properties_before_issue(): void
    {
        $this->m->save_property($this->client, null, $this->address('300 Other Road'));
        $id    = $this->doc();
        $items = $this->save($id, [$this->line()]);
        self::assertNotEmpty($this->m->problems('invoice', $id));
        $this->expectException(InvalidArgumentException::class);
        $this->m->before_save('invoice', $id, $items, 2, 2);
    }

    #[Test]
    public function it_rejects_property_from_another_customer(): void
    {
        $other = $this->m->save_property(2, null, $this->address(uniqid() . ' Other Road'));
        $id    = $this->doc();
        $this->expectException(InvalidArgumentException::class);
        $this->m->before_save('invoice', $id, [$this->line($other)], 1, 1);
    }

    #[Test]
    public function it_rejects_stale_document_revisions(): void
    {
        $id = $this->doc();
        $this->save($id, [$this->line()]);
        $this->expectException(RuntimeException::class);
        $this->m->before_save('invoice', $id, [], 1, 1);
    }

    #[Test]
    public function it_blocks_publication_after_partial_save_and_recovers_on_retry(): void
    {
        $id = $this->doc();
        $this->m->before_save('invoice', $id, [$this->line()], 1, 1);
        self::assertStringContainsString('did not finish', implode(' ', $this->m->problems('invoice', $id)));
        $this->m->release('invoice', $id);
        $this->save($id, [$this->line()]);
        self::assertSame([], $this->m->problems('invoice', $id));
    }

    #[Test]
    public function it_freezes_issued_properties_and_preserves_archived_history(): void
    {
        $id    = $this->doc();
        $items = $this->save($id, [$this->line()], 2);
        $this->m->save_property($this->client, $this->pid, $this->address(), 1, false);
        self::assertSame([], $this->m->problems('invoice', $id));
        self::assertNotEmpty($this->m->problems('invoice', $id, true));
        $this->expectException(InvalidArgumentException::class);
        $this->m->before_save('invoice', $id, $items, 2, 2, true);
    }

    #[Test]
    public function it_copies_quotes_and_credits_with_same_customer_snapshots(): void
    {
        $quote   = $this->doc('quote');
        $items   = $this->save($quote, [$this->line()], 1, 1, false, 'quote');
        $invoice = $this->doc();
        $fields  = $this->m->copy_fields('quote', $quote, 'invoice', $invoice, $items[0]);
        self::assertSame($items[0]->item_service_address, $fields['item_service_address']);
        $other = $this->doc('invoice', 2);
        self::assertNull($this->m->copy_fields('quote', $quote, 'invoice', $other, $items[0])['item_service_property_id']);
    }

    #[Test]
    public function it_rejects_a_line_owned_by_another_document(): void
    {
        $id    = $this->doc();
        $items = $this->save($id, [$this->line()]);
        $other = $this->doc();
        $this->expectException(InvalidArgumentException::class);
        $this->m->before_save('invoice', $other, $items, 1, 1);
    }

    #[Test]
    public function it_preserves_native_amounts_when_copying_and_crediting_multiple_properties(): void
    {
        $this->ci->load->model(['invoices/mdl_invoices', 'invoices/mdl_items', 'quotes/mdl_quotes', 'quotes/mdl_quote_items']);
        $pid2   = $this->m->save_property($this->client, null, $this->address('300 Other Road'));
        $source = $this->doc();
        $this->ci->db->insert('ip_invoice_amounts', ['invoice_id' => $source]);
        $discount = ['amount' => 0, 'percent' => 0, 'item' => 0, 'items_subtotal' => 625];
        foreach ([[300, $this->pid], [75, $this->pid], [200, $pid2], [50, $pid2]] as $n => $pair) {
            $property = $this->ci->db->get_where('ip_service_properties', ['property_id' => $pair[1]])->row_array();
            $row      = ['invoice_id' => $source, 'item_name' => 'Service ' . $n, 'item_description' => 'Synthetic job', 'item_quantity' => 1, 'item_price' => $pair[0], 'item_discount_amount' => 0, 'item_tax_rate_id' => 0, 'item_order' => $n + 1, 'item_service_property_id' => $pair[1], 'item_service_address' => json_encode(Property_rules::address($property))];
            $this->ci->mdl_items->save(null, $row, $discount);
        }
        $amount = $this->ci->db->get_where('ip_invoice_amounts', ['invoice_id' => $source])->row();
        self::assertEquals(625, $amount->invoice_total);
        $copy = $this->doc();
        $this->ci->db->insert('ip_invoice_amounts', ['invoice_id' => $copy]);
        $this->ci->mdl_invoices->copy_invoice($source, $copy);
        self::assertCount(4, $this->m->items('invoice', $copy));
        self::assertSame([], $this->m->problems('invoice', $copy));
        self::assertEquals(625, $this->ci->db->get_where('ip_invoice_amounts', ['invoice_id' => $copy])->row()->invoice_total);
        $credit = $this->doc();
        $this->ci->db->insert('ip_invoice_amounts', ['invoice_id' => $credit]);
        $this->ci->mdl_invoices->copy_credit_invoice($source, $credit);
        self::assertEquals(-625, $this->ci->db->get_where('ip_invoice_amounts', ['invoice_id' => $credit])->row()->invoice_total);
        self::assertSame(array_column($this->m->items('invoice', $source), 'item_service_address'), array_column($this->m->items('invoice', $credit), 'item_service_address'));
    }

    #[Test]
    public function it_imports_a_customer_with_two_properties_exactly_once(): void
    {
        if ( ! $this->ci->db->table_exists('ip_client_update_requests')) {
            $this->ci->db->query(file_get_contents(APPPATH . 'modules/client_updates/sql/install.sql'));
        }
        $name     = 'Import Fixture ' . uniqid();
        $ref      = 'BAM-' . mb_strtoupper(bin2hex(random_bytes(4)));
        $expected = ['request_reference' => $ref, 'full_name' => $name, 'service_address_1' => '600 Fixture Lane', 'service_city' => 'Springfield', 'service_state' => 'IL', 'service_zip' => '62701', 'phone' => '5550101234', 'email' => uniqid() . '@example.invalid', 'submitted_at' => gmdate('Y-m-d H:i:s')];
        $this->ci->db->insert('ip_client_update_requests', $expected);
        $rid      = (int) $this->ci->db->insert_id();
        $fields   = ['client_name' => $name, 'client_surname' => '', 'client_address_1' => '600 Fixture Lane', 'client_address_2' => '', 'client_city' => 'Springfield', 'client_state' => 'IL', 'client_zip' => '62701', 'client_country' => 'US', 'client_email' => $expected['email'], 'client_phone' => '+15550101234', 'client_active' => 1];
        $manifest = [['request_id' => $rid, 'reference' => $ref, 'client_id' => null, 'expected' => $expected, 'fields' => $fields, 'properties' => [$this->address('600 Fixture Lane'), $this->address('700 Fixture Lane')], 'resolve' => true]];
        $this->ci->load->model('service_properties/mdl_property_import');
        $import = $this->ci->mdl_property_import;
        $before = $this->ci->db->count_all('ip_clients');
        $import->run($manifest, false);
        self::assertSame($before, $this->ci->db->count_all('ip_clients'));
        $first = $import->run($manifest, true);
        $again = $import->run($manifest, true);
        self::assertSame($first, $again);
        self::assertSame($before + 1, $this->ci->db->count_all('ip_clients'));
        self::assertCount(2, $this->m->properties($first[0]['client_id']));
        self::assertSame('resolved', $this->ci->db->get_where('ip_client_update_requests', ['request_id' => $rid])->row()->status);
    }

    #[Test]
    public function it_keeps_legacy_documents_outside_property_enforcement(): void
    {
        $cutoff = $this->ci->db->get('ip_property_installation')->row()->invoice_cutoff;
        $id     = $this->doc();
        $this->ci->db->where(['document_type' => 'invoice', 'document_id' => $id])->delete('ip_property_documents');
        $this->ci->db->where('singleton', 1)->update('ip_property_installation', ['invoice_cutoff' => $id]);
        try {
            self::assertNull($this->m->state('invoice', $id));
            self::assertSame([], $this->m->problems('invoice', $id));
            self::assertNotEmpty($this->m->problems('invoice', $id, true));
        } finally {
            $this->ci->db->where('singleton', 1)->update('ip_property_installation', ['invoice_cutoff' => $cutoff]);
        }
    }

    #[Test]
    public function it_clears_properties_when_a_draft_changes_customer(): void
    {
        $id = $this->doc();
        $this->save($id, [$this->line()]);
        $this->m->change_client('invoice', $id, 2);
        self::assertNull($this->m->items('invoice', $id)[0]->item_service_property_id);
        self::assertNotEmpty($this->m->problems('invoice', $id));
        $this->ci->db->where('invoice_id', $id)->update('ip_invoices', ['client_id' => 2]);
        $this->m->finish('invoice', $id, true);
        self::assertSame(2, (int) $this->m->state('invoice', $id)->client_id);
        self::assertNotEmpty($this->m->problems('invoice', $id));
    }

    #[Test]
    public function it_rejects_stale_deletion_before_modifying_a_line(): void
    {
        $id    = $this->doc();
        $items = $this->save($id, [$this->line()]);
        $this->expectException(RuntimeException::class);
        $this->m->delete_line('invoice', $id, (int) $items[0]->item_id, 1);
    }

    #[Test]
    public function it_preserves_native_tax_discount_and_partial_payment_totals(): void
    {
        $this->ci->load->model(['invoices/mdl_items', 'invoices/mdl_invoice_amounts']);
        $this->ci->config->set_item('legacy_calculation', false);
        $this->ci->db->insert('ip_tax_rates', ['tax_rate_name' => 'Synthetic 6%', 'tax_rate_percent' => 6]);
        $tax = (int) $this->ci->db->insert_id();
        $id  = $this->doc();
        $this->ci->db->where('invoice_id', $id)->update('ip_invoices', ['invoice_discount_percent' => 10]);
        $this->ci->db->insert('ip_invoice_amounts', ['invoice_id' => $id]);
        $this->ci->db->insert('ip_payments', ['invoice_id' => $id, 'payment_date' => date('Y-m-d'), 'payment_amount' => 50, 'payment_method_id' => 0]);
        $discount = ['amount' => 0, 'percent' => 10, 'item' => 0, 'items_subtotal' => 200];
        $line     = $this->m->before_save('invoice', $id, [(object) ['item_name' => 'Taxed service', 'item_quantity' => 2, 'item_price' => 100, 'item_discount_amount' => 5, 'item_tax_rate_id' => $tax, 'item_order' => 1]], 1, 1)[0];
        $item     = $this->ci->mdl_items->save(null, $line, $discount);
        $this->m->verify_line('invoice', $id, $item, $line);
        $this->m->finish('invoice', $id, true);
        $amount = $this->ci->db->get_where('ip_invoice_amounts', ['invoice_id' => $id])->row();
        self::assertEquals(170, $amount->invoice_item_subtotal);
        self::assertEquals(10.2, $amount->invoice_item_tax_total);
        self::assertEquals(180.2, $amount->invoice_total);
        self::assertEquals(130.2, $amount->invoice_balance);
    }

    #[Test]
    public function it_archives_and_restores_only_the_owned_current_property(): void
    {
        $this->m->set_active($this->client, $this->pid, 1, false);
        self::assertSame(0, (int) $this->m->properties($this->client)[0]['active']);
        $this->m->set_active($this->client, $this->pid, 2, true);
        self::assertSame(1, (int) $this->m->properties($this->client)[0]['active']);
        $this->expectException(InvalidArgumentException::class);
        $this->m->set_active(2, $this->pid, 3, false);
    }

    #[Test]
    public function it_rejects_archiving_from_a_stale_screen(): void
    {
        $this->m->save_property($this->client, $this->pid, $this->address('Changed Road'), 1);
        $this->expectException(RuntimeException::class);
        $this->m->set_active($this->client, $this->pid, 1, false);
    }

    #[Test]
    public function it_accepts_explicit_staged_removals_without_deleting_during_validation(): void
    {
        $id = $this->doc();
        $items = $this->save($id, [$this->line(), $this->line()]);
        $deleted = [(int)$items[0]->item_id];
        $remaining = [$items[1]];
        $this->ci->load->model('invoices/mdl_invoice_editor');
        $document = $this->ci->db->get_where('ip_invoices', ['invoice_id' => $id])->row();
        $this->ci->mdl_invoice_editor->validate($document, $remaining, $deleted);
        $prepared = $this->m->before_save('invoice', $id, $remaining, 2, 1, false, $deleted);
        self::assertCount(1, $prepared);
        self::assertCount(2, $this->m->items('invoice', $id));
        $this->m->finish('invoice', $id, false);
    }

    #[Test]
    public function it_rejects_staged_removals_from_issued_invoices(): void
    {
        $id = $this->doc();
        $items = $this->save($id, [$this->line(), $this->line()], 2);
        try {
            $this->m->before_save('invoice', $id, [$items[1]], 2, 2, false, [(int)$items[0]->item_id]);
            self::fail('Issued charge removal was accepted.');
        } catch (InvalidArgumentException $e) {
            self::assertCount(2, $this->m->items('invoice', $id));
            self::assertSame('ready', $this->m->state('invoice', $id)->save_state);
        }
    }

    #[Test]
    public function it_rejects_omitted_duplicate_and_foreign_removals_before_any_write(): void
    {
        $id = $this->doc();
        $items = $this->save($id, [$this->line(), $this->line()]);
        $other = $this->doc();
        $foreign = $this->save($other, [$this->line()]);
        $this->ci->load->model('invoices/mdl_invoice_editor');
        $document = $this->ci->db->get_where('ip_invoices', ['invoice_id' => $id])->row();
        foreach ([[], [(int)$foreign[0]->item_id], [(int)$items[0]->item_id, (int)$items[0]->item_id], [(int)$items[1]->item_id]] as $deleted) {
            try {
                $this->ci->mdl_invoice_editor->validate($document, [$items[1]], $deleted);
                self::fail('Invalid removal was accepted.');
            } catch (InvalidArgumentException $e) {
                self::assertCount(2, $this->m->items('invoice', $id));
            }
        }
    }

    #[Test]
    public function it_rejects_stale_staged_removals_without_changing_rows(): void
    {
        $id = $this->doc();
        $items = $this->save($id, [$this->line(), $this->line()]);
        try {
            $this->m->before_save('invoice', $id, [$items[1]], 1, 1, false, [(int)$items[0]->item_id]);
            self::fail('Stale removal was accepted.');
        } catch (RuntimeException $e) {
            self::assertCount(2, $this->m->items('invoice', $id));
            self::assertSame('ready', $this->m->state('invoice', $id)->save_state);
        }
    }

    #[Test]
    public function it_rejects_read_only_charge_edits_and_removals(): void
    {
        $id = $this->doc();
        $items = $this->save($id, [$this->line()]);
        $this->ci->load->model('invoices/mdl_invoice_editor');
        $document = $this->ci->db->get_where('ip_invoices', ['invoice_id' => $id])->row();
        $document->is_read_only = 1;
        $original = $this->ci->config->item('disable_read_only');
        $this->ci->config->set_item('disable_read_only', false);
        try {
            foreach ([false, true] as $remove) {
                $items[0]->item_price = 999;
                try {
                    $this->ci->mdl_invoice_editor->validate($document, $remove ? [] : $items, $remove ? [(int)$items[0]->item_id] : []);
                    self::fail('Read-only change was accepted.');
                } catch (InvalidArgumentException $e) {
                    self::assertEquals(300, $this->m->items('invoice', $id)[0]->item_price);
                }
            }
        } finally {
            $this->ci->config->set_item('disable_read_only', $original);
        }
    }

    private function address(string $street = '200 Service Road'): array
    {
        return ['address_1' => $street, 'city' => 'Springfield', 'state' => 'IL', 'zip' => '62701', 'country' => 'US'];
    }

    private function doc(string $type = 'invoice', ?int $client = null): int
    {
        $this->ci->db->insert('ip_' . $type . 's', ['client_id' => $client ?? $this->client, 'user_id' => 1, 'invoice_group_id' => (int) $this->ci->db->order_by('invoice_group_id')->get('ip_invoice_groups')->row()->invoice_group_id, $type . '_status_id' => 1, $type . '_date_created' => date('Y-m-d'), $type . '_date_modified' => date('Y-m-d H:i:s'), $type . '_date_' . ($type === 'invoice' ? 'due' : 'expires') => date('Y-m-d'), $type . '_url_key' => bin2hex(random_bytes(16))]);
        $id = (int) $this->ci->db->insert_id();
        $this->m->state($type, $id);

        return $id;
    }

    private function line(int $pid = 0, int $id = 0): object
    {
        return (object) ['item_id' => $id, 'item_name' => 'Tree trimming', 'item_quantity' => 1, 'item_price' => 300, 'item_service_property_id' => $pid, 'item_order' => 1];
    }

    private function save(int $id, array $items, int $status = 1, int $revision = 1, bool $refresh = false, string $type = 'invoice'): array
    {
        $items = $this->m->before_save($type, $id, $items, $revision, $status, $refresh);
        foreach ($items as $item) {
            $row = (array) $item;
            $iid = $row['item_id'];
            unset($row['item_id']);
            if ($iid) {
                $this->ci->db->where('item_id', $iid)->update('ip_' . $type . '_items', $row);
            } else {
                $row['item_date_added'] = date('Y-m-d');
                $this->ci->db->insert('ip_' . $type . '_items', $row);
                $item->item_id = (int) $this->ci->db->insert_id();
            }
        }
        $this->ci->db->where($type . '_id', $id)->update('ip_' . $type . 's', [$type . '_status_id' => $status]);
        $this->m->finish($type,$id,true);

        return $items;
    }
}
