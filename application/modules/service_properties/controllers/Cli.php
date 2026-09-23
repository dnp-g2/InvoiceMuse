<?php

defined('BASEPATH') || exit('No direct script access allowed');
class Cli extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        if ( ! is_cli()) {
            show_404();
            exit;
        }$this->load->database();
    }

    public function install(): void
    {
        $sql = file_get_contents(dirname(__DIR__) . '/sql/install.sql');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                if ( ! $this->db->query($statement)) {
                    throw new RuntimeException('Schema creation failed.');
                }
            }
        }
        foreach (['ip_invoice_items', 'ip_quote_items'] as $table) {
            foreach (['item_service_property_id' => 'INT UNSIGNED DEFAULT NULL', 'item_service_address' => 'LONGTEXT DEFAULT NULL'] as $column => $definition) {
                if ( ! $this->db->field_exists($column, $table) && ! $this->db->query('ALTER TABLE `' . $table . '` ADD `' . $column . '` ' . $definition)) {
                    throw new RuntimeException('Item schema update failed.');
                }
            }
        }
        $invoice = (int) $this->db->select_max('invoice_id')->get('ip_invoices')->row()->invoice_id;
        $quote   = (int) $this->db->select_max('quote_id')->get('ip_quotes')->row()->quote_id;
        $this->db->query('INSERT IGNORE INTO ip_property_installation(singleton,invoice_cutoff,quote_cutoff) VALUES (1,?,?)', [$invoice, $quote]);
        echo "Service properties schema ready; existing documents remain unchanged.\n";
    }

    public function import(string $mode = 'dry-run'): void
    {
        if ( ! in_array($mode, ['dry-run', 'apply'], true)) {
            throw new InvalidArgumentException('Use dry-run or apply.');
        }
        $path = getenv('PROPERTY_IMPORT_FILE');
        if ( ! $path || ! is_file($path)) {
            throw new InvalidArgumentException('Set PROPERTY_IMPORT_FILE to the reviewed manifest outside the web root.');
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if ( ! is_array($manifest) || ! $manifest) {
            throw new InvalidArgumentException('Empty manifest.');
        }
        $this->load->model('service_properties/mdl_property_import');
        echo json_encode($this->mdl_property_import->run($manifest, $mode === 'apply'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),"\n";
    }
}
