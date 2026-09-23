<?php

defined('BASEPATH') || exit('No direct script access allowed');
class Service_properties extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['service_properties', 'country']);
    }

    public function index(int $client): void
    {
        $m   = service_properties();
        $row = $this->db->get_where('ip_clients', ['client_id' => $client])->row();
        if ( ! $row || ! $m->installed()) {
            show_404();

            return;
        }
        $this->layout->set(['client' => $row, 'properties' => $m->properties($client), 'countries' => get_country_list('en'), 'property_form_error' => $this->session->flashdata('property_form_error')]);
        $this->layout->buffer('content', 'service_properties/index');
        $this->layout->render();
    }

    public function save(int $client): void
    {
        if ($this->input->method() !== 'post') {
            show_404();

            return;
        }
        try {
            service_properties()->save_property($client, (int) $this->input->post('property_id') ?: null, (array) $this->input->post('address'), (int) $this->input->post('revision'), $this->input->post('active') === '1');
            $message = 'Service property saved. Existing documents retain their saved addresses.';
            if ($this->inline_response($client, true, $message)) {
                return;
            }
            $this->session->set_flashdata('alert_success', $message);
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($this->inline_response($client, false, $e->getMessage())) {
                return;
            }
            $this->session->set_flashdata('alert_error', $e->getMessage());
            $this->session->set_flashdata('property_form_error', ['id' => (int) $this->input->post('property_id'), 'revision' => (int) $this->input->post('revision'), 'address' => (array) $this->input->post('address')]);
        }
        redirect('service-properties/client/' . $client);
    }

    public function status(int $client): void
    {
        if ($this->input->method() !== 'post') {
            show_404();

            return;
        }
        try {
            $action = $this->input->post('action');
            if ( ! in_array($action, ['archive', 'restore'], true)) {
                throw new InvalidArgumentException('Invalid property action.');
            }
            service_properties()->set_active($client, (int) $this->input->post('property_id'), (int) $this->input->post('revision'), $action === 'restore');
            $message = $action === 'restore' ? 'Property restored.' : 'Property archived. Past invoices and quotes are preserved.';
            if ($this->inline_response($client, true, $message)) {
                return;
            }
            $this->session->set_flashdata('alert_success', $message);
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($this->inline_response($client, false, $e->getMessage())) {
                return;
            }
            $this->session->set_flashdata('alert_error', $e->getMessage());
        }
        redirect('service-properties/client/' . $client);
    }

    public function preview(string $type, int $id): void
    {
        if ( ! in_array($type, ['invoice', 'quote'], true)) {
            show_404();

            return;
        }
        $this->load->helper('pdf');
        // Only this authenticated endpoint permits clearly labelled incomplete previews.
        define('PROPERTY_ADMIN_PREVIEW', true);
        if ($type === 'invoice') {
            generate_invoice_pdf($id);
        } else {
            generate_quote_pdf($id);
        }
    }

    private function inline_response(int $client, bool $success, string $message): bool
    {
        if ($this->input->post('inline_properties') !== '1') {
            return false;
        }
        $html = '';
        if ($success) {
            $html = $this->load->view('service_properties/cards', [
                'client'              => $this->db->get_where('ip_clients', ['client_id' => $client])->row(),
                'properties'          => service_properties()->properties($client),
                'countries'           => get_country_list('en'),
                'property_form_error' => null,
            ], true);
        }
        header('Content-Type: application/json; charset=UTF-8');
        header('X-CSRF-Token: ' . $this->security->get_csrf_hash());
        echo json_encode(['success' => (int) $success, 'message' => $message, 'html' => $html, 'new_token' => $this->security->get_csrf_hash()]);

        return true;
    }
}
