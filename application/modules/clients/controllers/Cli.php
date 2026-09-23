<?php

defined('BASEPATH') || exit('No direct script access allowed');
class Cli extends MX_Controller
{
    public function install_note_archive(): void
    {
        if ( ! is_cli()) {
            show_404();

            return;
        }
        $this->load->database();
        if ( ! $this->db->field_exists('client_note_archived_at', 'ip_client_notes')) {
            if ( ! $this->db->query('ALTER TABLE ip_client_notes ADD client_note_archived_at DATETIME NULL DEFAULT NULL')) {
                throw new RuntimeException('Note archive migration failed.');
            }
        }
        echo "Customer note archive schema ready.\n";
    }
}
