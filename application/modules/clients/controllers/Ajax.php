<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author      InvoicePlane Developers & Contributors
 * @copyright   Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license     https://invoiceplane.com/license.txt
 * @link        https://invoiceplane.com
 */

#[AllowDynamicProperties]
class Ajax extends Admin_Controller
{
    public $ajax_controller = true;

    public function quick_create()
    {
        if ($this->input->method() !== 'post') { show_404(); return; }
        property_csrf_header();
        $this->load->model('clients/mdl_client_quick_create');
        try {
            $key = $this->input->post('request_id');
            $version = $this->input->post('match_version') ?? '';
            if (!is_string($key) || !is_string($version)) { throw new InvalidArgumentException('Invalid customer form.'); }
            $response = $this->mdl_client_quick_create->create($this->input->post(), $key, $this->input->post('separate_customer') === '1', $version);
            if ($response['success'] === 1) { $response['next_request_id'] = $this->mdl_client_quick_create->new_request(); }
        } catch (Throwable $e) {
            $response = ['success' => 0, 'message' => $e instanceof RuntimeException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to save the customer. Retry with the same details to check the result.'];
        }
        $response['new_token'] = $this->security->get_csrf_hash();
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }

    public function name_query()
    {
        // Load the model & helper
        $this->load->model('clients/mdl_clients');

        $response = [];

        // Get the post input
        $query                   = $this->input->get('query');
        $permissiveSearchClients = $this->input->get('permissive_search_clients');

        if (empty($query)) {
            $this->json_encode_ajax($response);
        }

        // Search for chars "in the middle" of clients names
        $moreClientsQuery = $permissiveSearchClients ? '%' : '';

        // Search for clients
        // Strip LIKE wildcards from user input, then pass the pattern as a bound
        // value so CodeIgniter escapes it — never concatenate input into the SQL.
        $searchTerm    = str_replace(['%', '_'], '', (string) $query);
        $searchPattern = $moreClientsQuery . $searchTerm . '%';

        $clients = $this->mdl_clients
            ->where('client_active', 1)
            ->having('client_name LIKE', $searchPattern)
            ->or_having('client_surname LIKE', $searchPattern)
            ->or_having('client_fullname LIKE', $searchPattern)
            ->order_by('client_name')
            ->get()
            ->result();

        foreach ($clients as $client) {
            $response[] = [
                'id'   => $client->client_id,
                'text' => htmlsc(format_client($client, false)),
            ];
        }

        // Return the results
        $this->json_encode_ajax($response);
    }

    /**
     * Get the latest clients.
     */
    public function get_latest()
    {
        // Load the model & helper
        $this->load->model('clients/mdl_clients');

        $response = [];

        $clients = $this->mdl_clients
            ->where('client_active', 1)
            ->limit(5)
            ->order_by('client_date_created')
            ->get()
            ->result();

        foreach ($clients as $client) {
            $response[] = [
                'id'   => $client->client_id,
                'text' => htmlsc(format_client($client, false)),
            ];
        }

        // Return the results
        $this->json_encode_ajax($response);
    }

    public function save_preference_permissive_search_clients()
    {
        $this->load->model('mdl_settings');
        $permissiveSearchClients = $this->input->get('permissive_search_clients');

        if ( ! preg_match('!^[0-1]{1}$!', $permissiveSearchClients)) {
            exit;
        }

        $this->mdl_settings->save('enable_permissive_search_clients', $permissiveSearchClients);
    }

    public function delete_client_note()
    {
        $this->output->set_status_header(410);
        header('X-CSRF-Token: ' . $this->security->get_csrf_hash());
        echo json_encode(['success' => 0, 'message' => 'Notes can now be archived. Reload the customer page.']);
    }

    public function archive_client_note()
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }
        header('X-CSRF-Token: ' . $this->security->get_csrf_hash());
        $action = $this->input->post('action');
        $this->load->model('clients/mdl_client_notes');
        $success = in_array($action, ['archive', 'restore'], true)
            && $this->mdl_client_notes->set_archived((int)$this->input->post('client_id'), (int)$this->input->post('client_note_id'), $action === 'archive');
        echo json_encode(['success' => (int)$success, 'new_token' => $this->security->get_csrf_hash(), 'message' => $success ? '' : 'Unable to update this customer note. Reload and try again.']);
    }

    public function save_client_note()
    {
        header('X-CSRF-Token: ' . $this->security->get_csrf_hash());
        $this->load->model('clients/mdl_client_notes');

        if ($this->mdl_client_notes->run_validation()) {
            $this->mdl_client_notes->save();

            $response = [
                'success'   => 1,
                'new_token' => $this->security->get_csrf_hash(),
            ];
        } else {
            $this->load->helper('json_error');
            $response = [
                'success'           => 0,
                'new_token'         => $this->security->get_csrf_hash(),
                'validation_errors' => json_errors(),
            ];
        }

        $this->json_encode_ajax($response);
    }

    public function load_client_notes()
    {
        header('X-CSRF-Token: ' . $this->security->get_csrf_hash());
        $this->load->model('clients/mdl_client_notes');
        $data = [
            'client_notes' => $this->mdl_client_notes->where(
                'client_id',
                $this->input->post('client_id')
            )->get()->result(),
        ];

        $this->layout->load_view('clients/partial_notes', $data);
    }
}
