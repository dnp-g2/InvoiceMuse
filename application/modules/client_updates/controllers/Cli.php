<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Cli extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();

        if ( ! is_cli()) {
            show_404();
        }

        $this->load->database();
    }

    public function install(): void
    {
        $sql_path = dirname(__DIR__) . '/sql/install.sql';
        $sql      = file_get_contents($sql_path);

        if ($sql === false || trim($sql) === '') {
            fwrite(STDERR, "Unable to read client update schema.\n");
            exit(1);
        }

        if ( ! $this->db->query($sql)) {
            fwrite(STDERR, "Unable to install client update schema.\n");
            exit(1);
        }

        if ( ! $this->db->field_exists('additional_service_addresses', 'ip_client_update_requests')) {
            $this->db->query(
                'ALTER TABLE `ip_client_update_requests` ADD `additional_service_addresses` LONGTEXT DEFAULT NULL AFTER `service_zip`'
            );
        }

        fwrite(STDOUT, "Client update queue schema is installed.\n");
    }
    /** Repeatable upgrade; run in maintenance before enabling review actions. */
    public function upgrade_review(): void
    {
        foreach (['ip_clients', 'ip_client_notes', 'ip_user_clients', 'ip_client_update_requests', 'ip_service_properties'] as $table) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('Install customer submissions and service properties first.');
            }
        }
        foreach (['applied_client_id' => 'INT DEFAULT NULL', 'applied_at' => 'DATETIME DEFAULT NULL', 'applied_by' => 'INT DEFAULT NULL'] as $column => $definition) {
            if (!$this->db->field_exists($column, 'ip_client_update_requests') && !$this->db->query('ALTER TABLE ip_client_update_requests ADD `' . $column . '` ' . $definition)) {
                throw new RuntimeException('Customer review schema upgrade failed.');
            }
        }
        foreach (['ip_clients', 'ip_client_notes', 'ip_user_clients', 'ip_client_update_requests', 'ip_service_properties'] as $table) {
            $row = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->row();
            if ($row->ENGINE !== 'InnoDB' && !$this->db->query('ALTER TABLE `' . $table . '` ENGINE=InnoDB')) {
                throw new RuntimeException('Customer transaction support upgrade failed.');
            }
        }
        echo "Customer review schema ready.\n";
    }

}
