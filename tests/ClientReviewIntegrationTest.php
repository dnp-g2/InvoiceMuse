<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class ClientReviewIntegrationTest extends TestCase
{
    private $ci;
    private $m;

    protected function setUp(): void
    {
        if (getenv('PROPERTY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Requires isolated integration fixture.');
        }
        $this->ci = &get_instance();
        $this->ci->load->model(['client_updates/mdl_client_update_review', 'client_updates/mdl_client_updates']);
        $this->m = $this->ci->mdl_client_update_review;
    }

    private function request(array $extra = []): int
    {
        $ref = 'TEST-' . bin2hex(random_bytes(6));
        $data = array_replace([
            'request_reference' => $ref, 'full_name' => 'Review ' . $ref, 'email' => strtolower($ref) . '@example.invalid', 'phone' => '202555' . random_int(1000, 9999),
            'service_address_1' => '100 Test Lane', 'service_address_2' => '', 'service_city' => 'Springfield', 'service_state' => 'IL', 'service_zip' => '62701',
            'mailing_same_as_service' => 1, 'status' => 'pending', 'submitted_at' => gmdate('Y-m-d H:i:s'),
        ], $extra);
        $this->ci->db->insert('ip_client_update_requests', $data);
        return (int)$this->ci->db->insert_id();
    }

    private function apply(int $id, int $client = 0, array $fields = []): int
    {
        return $this->m->apply($id, $client, $fields, $this->m->review($id, $client)['version'], 1, true);
    }

    #[Test]
    public function it_creates_active_customer_properties_note_and_resolves_atomically(): void
    {
        $id = $this->request(); $client = $this->apply($id);
        $r = $this->m->review($id)['request'];
        $c = $this->ci->db->get_where('ip_clients', ['client_id' => $client])->row();
        self::assertSame('resolved', $r['status']);
        self::assertSame($client, (int)$r['applied_client_id']);
        self::assertSame(1, (int)$c->client_active);
        self::assertSame('100 Test Lane', $c->client_address_1);
        self::assertCount(1, service_properties()->properties($client));
        self::assertSame(1, $this->ci->db->where('client_id', $client)->count_all_results('ip_client_notes'));
    }

    #[Test]
    public function it_preserves_separate_billing_and_deduplicates_submitted_properties(): void
    {
        $id = $this->request(['mailing_same_as_service' => 0, 'mailing_address_1' => '50 Billing Road', 'mailing_city' => 'Springfield', 'mailing_state' => 'IL', 'mailing_zip' => '62701', 'additional_service_addresses' => json_encode([
            ['address_1' => '100 Test Lane', 'city' => 'Springfield', 'state' => 'IL', 'zip' => '62701'],
            ['address_1' => '200 Test Lane', 'city' => 'Springfield', 'state' => 'IL', 'zip' => '62701'],
        ])]);
        $client = $this->apply($id);
        self::assertSame('50 Billing Road', $this->ci->db->get_where('ip_clients', ['client_id' => $client])->row()->client_address_1);
        self::assertCount(2, service_properties()->properties($client));
    }

    #[Test]
    public function it_retries_and_recloses_without_duplicate_records(): void
    {
        $id = $this->request(); $version = $this->m->review($id)['version'];
        $client = $this->m->apply($id, 0, [], $version, 1, true);
        self::assertSame($client, $this->m->apply($id, 0, [], $version, 1, true));
        $this->ci->mdl_client_updates->reopen($id);
        self::assertSame($client, $this->m->apply($id, 0, [], $version, 1, true));
        self::assertCount(1, service_properties()->properties($client));
        self::assertSame(1, $this->ci->db->where('client_id', $client)->count_all_results('ip_client_notes'));
    }

    #[Test]
    public function it_recovers_previously_resolved_submission(): void
    {
        $id = $this->request(['status' => 'resolved', 'resolution_note' => 'Prior manual review', 'resolved_at' => gmdate('Y-m-d H:i:s')]);
        $client = $this->apply($id);
        self::assertGreaterThan(0, $client);
        self::assertStringContainsString('Prior manual review', $this->ci->db->get_where('ip_client_notes', ['client_id' => $client])->row()->client_note);
    }

    #[Test]
    public function it_requires_explicit_duplicate_acknowledgement(): void
    {
        $id = $this->request(); $client = $this->apply($id);
        $r = $this->m->review($id)['request'];
        $duplicate = $this->request(['email' => $r['email']]);
        self::assertNotEmpty($this->m->review($duplicate)['matches']);
        $this->expectException(InvalidArgumentException::class);
        $this->m->apply($duplicate, 0, [], $this->m->review($duplicate)['version'], 1);
    }

    #[Test]
    public function it_updates_only_selected_fields_and_keeps_old_properties(): void
    {
        $first = $this->request(); $client = $this->apply($first);
        $old = $this->ci->db->get_where('ip_clients', ['client_id' => $client])->row_array();
        $this->ci->db->where('client_id', $client)->update('ip_clients', ['client_active' => 0]);
        $id = $this->request(['full_name' => 'New Proposed Name', 'service_address_1' => '300 New Lane', 'email' => $old['client_email'], 'phone' => '2025550101']);
        self::assertNotEmpty($this->m->review($id)['matches']);
        $this->apply($id, $client, ['phone']);
        $current = $this->ci->db->get_where('ip_clients', ['client_id' => $client])->row_array();
        self::assertSame($old['client_name'], $current['client_name']);
        self::assertSame($old['client_address_1'], $current['client_address_1']);
        self::assertSame('2025550101', $current['client_phone']);
        self::assertSame('0', (string)$current['client_active']);
        self::assertCount(2, service_properties()->properties($client));
    }

    #[Test]
    public function it_rejects_stale_request_review(): void
    {
        $id = $this->request(); $version = $this->m->review($id)['version'];
        $this->ci->mdl_client_updates->resolve($id, 1, 'Changed elsewhere');
        $this->expectException(RuntimeException::class);
        $this->m->apply($id, 0, [], $version, 1, true);
    }

    #[Test]
    public function it_rejects_stale_customer_review(): void
    {
        $client = $this->apply($this->request()); $id = $this->request();
        $version = $this->m->review($id, $client)['version'];
        $this->ci->db->where('client_id', $client)->update('ip_clients', ['client_phone' => '9995550000']);
        $this->expectException(RuntimeException::class);
        $this->m->apply($id, $client, ['phone'], $version, 1);
    }

    #[Test]
    public function it_dismisses_only_with_a_reason_without_creating_customer(): void
    {
        $id = $this->request(); $count = $this->ci->db->count_all('ip_clients');
        $this->m->dismiss($id, $this->m->review($id)['version'], 'Duplicate submission', 1);
        self::assertSame($count, $this->ci->db->count_all('ip_clients'));
        self::assertSame('resolved', $this->m->review($id)['request']['status']);
        self::assertNull($this->m->review($id)['request']['applied_client_id']);
    }

    #[Test]
    public function it_rejects_blank_dismissal_reason(): void
    {
        $id = $this->request();
        $this->expectException(InvalidArgumentException::class);
        $this->m->dismiss($id, $this->m->review($id)['version'], ' ', 1);
    }

    #[Test]
    public function it_rolls_back_customer_property_and_note_when_resolution_write_fails(): void
    {
        $id = $this->request(); $version = $this->m->review($id)['version'];
        $tables = ['ip_clients', 'ip_client_notes', 'ip_service_properties', 'ip_user_clients'];
        $before = array_map(fn($table) => $this->ci->db->count_all($table), $tables);
        $this->ci->db->query("CREATE TRIGGER review_test_failure BEFORE UPDATE ON ip_client_update_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
        try {
            $this->m->apply($id, 0, [], $version, 1, true);
            self::fail('Expected injected failure');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Unable to save', $e->getMessage());
        } finally {
            $this->ci->db->query('DROP TRIGGER review_test_failure');
        }
        self::assertSame($before, array_map(fn($table) => $this->ci->db->count_all($table), $tables));
        self::assertSame('pending', $this->m->review($id)['request']['status']);
        self::assertNull($this->m->review($id)['request']['applied_client_id']);
    }

    #[Test]
    public function it_rejects_invalid_addresses_before_any_customer_write(): void
    {
        $id = $this->request(['service_city' => '']);
        $before = $this->ci->db->count_all('ip_clients');
        try { $this->apply($id); self::fail('Expected validation error'); }
        catch (InvalidArgumentException $e) { self::assertSame($before, $this->ci->db->count_all('ip_clients')); }
    }
    #[Test]
    public function it_allows_dismissal_of_malformed_submission_without_importing_it(): void
    {
        $id = $this->request(['additional_service_addresses' => '{broken']);
        $review = $this->m->review($id);
        self::assertNotEmpty($review['validation_errors']);
        $this->m->dismiss($id, $review['version'], 'Invalid submission', 1);
        self::assertSame('resolved', $this->m->review($id)['request']['status']);
        self::assertNull($this->m->review($id)['request']['applied_client_id']);
    }

    #[Test]
    public function it_lists_ambiguous_matches_and_reuses_existing_property(): void
    {
        $one = $this->request(); $first = $this->apply($one);
        $row = $this->m->review($one)['request'];
        $second = $this->apply($this->request(['email' => $row['email']]));
        $id = $this->request(['email' => $row['email']]);
        $matches = array_column($this->m->review($id)['matches'], 'client_id');
        self::assertContains((string)$first, array_map('strval', $matches));
        self::assertContains((string)$second, array_map('strval', $matches));
        $this->apply($id, $first);
        self::assertCount(1, service_properties()->properties($first));
    }

}
