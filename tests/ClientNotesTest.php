<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientNotesTest extends TestCase
{
    private $ci;

    private int $note;

    protected function setUp(): void
    {
        if (getenv('PROPERTY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Requires isolated integration database.');
        }
        $this->ci = &get_instance();
        $this->ci->load->model('clients/mdl_client_notes');
        $this->ci->db->insert('ip_client_notes', ['client_id' => 1, 'client_note' => 'Original note with <markup> & newlines' . "\nSecond line", 'client_note_date' => '2026-01-01']);
        $this->note = (int) $this->ci->db->insert_id();
    }

    #[Test]
    public function it_preserves_original_note_and_date_through_repeated_archive_and_restore(): void
    {
        $m      = $this->ci->mdl_client_notes;
        $before = $this->ci->db->get_where('ip_client_notes', ['client_note_id' => $this->note])->row_array();
        self::assertTrue($m->set_archived(1, $this->note, true));
        $first = $this->ci->db->get_where('ip_client_notes', ['client_note_id' => $this->note])->row_array();
        self::assertNotNull($first['client_note_archived_at']);
        self::assertTrue($m->set_archived(1, $this->note, true));
        self::assertSame($first, $this->ci->db->get_where('ip_client_notes', ['client_note_id' => $this->note])->row_array());
        self::assertTrue($m->set_archived(1, $this->note, false));
        self::assertTrue($m->set_archived(1, $this->note, false));
        self::assertSame($before, $this->ci->db->get_where('ip_client_notes', ['client_note_id' => $this->note])->row_array());
    }

    #[Test]
    public function it_rejects_wrong_customer_and_permanent_deletion(): void
    {
        $m = $this->ci->mdl_client_notes;
        self::assertFalse($m->set_archived(2, $this->note, true));
        self::assertFalse($m->delete($this->note));
        self::assertNotNull($this->ci->db->get_where('ip_client_notes', ['client_note_id' => $this->note])->row());
    }
}
