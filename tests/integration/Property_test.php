<?php

defined('BASEPATH') || exit('No direct script access allowed');
class Property_test extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        if ( ! is_cli() || ENVIRONMENT !== 'testing') {
            fwrite(STDERR, 'Fixture requires testing CLI; got ' . ENVIRONMENT . ' / ' . (int) is_cli() . "\n");
            exit(1);
        }$this->load->database();
        $this->load->library('session');
        $this->load->helper(['url', 'form', 'trans', 'settings', 'date', 'number', 'invoice', 'client', 'country', 'echo']);
        $this->config->load('invoice_plane');
    }

    public function index()
    {
        $this->load->model('settings/mdl_settings');
        $this->mdl_settings->load_settings();
        set_language('english');
    }

    public function fix_login()
    {
        $this->db->where('user_email', 'admin@example.invalid')->update('ip_users', ['user_psalt' => bin2hex(random_bytes(11))]);
        echo "Test login ready.\n";
    }

    /** Synthetic catalog entries for invoice workspace browser tests. */
    public function invoice_workspace_fixture()
    {
        $this->index();
        $this->mdl_settings->save('projects_enabled', '1');
        $this->mdl_settings->save('read_only_toggle', '2');
        $this->mdl_settings->save('system_theme', 'invoiceplane_blue');
        $this->db->insert('ip_products', ['family_id' => 0, 'product_sku' => 'IW-' . substr(uniqid(), -8), 'product_name' => 'Workspace test service', 'product_description' => 'Synthetic catalog charge', 'product_price' => 45, 'purchase_price' => 0, 'tax_rate_id' => 0]);
        $product = (int)$this->db->insert_id();
        $this->db->insert('ip_tasks', ['project_id' => 0, 'task_name' => 'Workspace test task', 'task_description' => 'Synthetic completed task', 'task_price' => 35, 'task_finish_date' => date('Y-m-d'), 'task_status' => 3]);
        $task = (int)$this->db->insert_id();
        echo json_encode(['product' => $product, 'task' => $task]);
    }

    public function review_fixture(): void
    {
        $this->index();
        $reference = 'UI-' . bin2hex(random_bytes(6));
        $data = ['request_reference' => $reference, 'full_name' => 'Review Browser ' . $reference, 'phone' => '2025550109', 'email' => strtolower($reference) . '@example.invalid', 'service_address_1' => '123 Browser Lane', 'service_city' => 'Springfield', 'service_state' => 'IL', 'service_zip' => '62701', 'mailing_same_as_service' => 1, 'status' => 'pending', 'submitted_at' => gmdate('Y-m-d H:i:s')];
        $this->db->insert('ip_client_update_requests', $data);
        $id = (int)$this->db->insert_id();
        $data['request_reference'] .= 'B';
        $data['full_name'] = '<script>alert(1)</script> Duplicate test';
        $data['phone'] = '2025550199';
        $this->db->insert('ip_client_update_requests', $data);
        $duplicate = (int)$this->db->insert_id();
        $data['request_reference'] = 'UI-' . bin2hex(random_bytes(6));
        $data['email'] = strtolower($data['request_reference']) . '@example.invalid';
        $this->db->insert('ip_client_update_requests', $data);
        $dismiss = (int)$this->db->insert_id();
        echo json_encode(compact('id', 'duplicate', 'dismiss'));
    }

    public function install()
    {
        $this->load->model('setup/mdl_setup');
        if ( ! $this->db->table_exists('ip_clients')) {
            $this->mdl_setup->install_tables();
        }
        $this->load->helper('directory');
        if ( ! $this->mdl_setup->upgrade_tables()) {
            echo json_encode($this->mdl_setup->errors);
            throw new RuntimeException('Fixture schema failed');
        }
        $this->load->model('settings/mdl_settings');
        $this->mdl_settings->load_settings();
        $this->mdl_settings->save('default_language', 'english');
        $this->mdl_settings->save('email_send_method', '');
        $secret = bin2hex(random_bytes(18));
        file_put_contents('/tmp/ip-test-login', $secret);
        chmod('/tmp/ip-test-login', 0600);
        $this->db->insert('ip_users', ['user_type' => 1, 'user_active' => 1, 'user_name' => 'Test Administrator', 'user_email' => 'admin@example.invalid', 'user_password' => password_hash($secret, PASSWORD_DEFAULT), 'user_psalt' => bin2hex(random_bytes(11)), 'user_company' => 'Test Company', 'user_date_created' => gmdate('Y-m-d H:i:s'), 'user_date_modified' => gmdate('Y-m-d H:i:s'), 'user_language' => 'english']);
        foreach (['Sample Customer', 'Other Customer'] as $name) {
            $this->db->insert('ip_clients', ['client_name' => $name, 'client_surname' => '', 'client_date_created' => gmdate('Y-m-d H:i:s'), 'client_date_modified' => gmdate('Y-m-d H:i:s'), 'client_address_1' => '100 Billing Lane', 'client_city' => 'Springfield', 'client_state' => 'IL', 'client_zip' => '62701', 'client_country' => 'US']);
        }
        echo "Synthetic fixture installed.\n";
    }
}
