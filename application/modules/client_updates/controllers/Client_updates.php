<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Client_updates extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('client_updates/mdl_client_updates');
        $this->load->model('client_updates/mdl_client_update_review');

        if ( ! $this->mdl_client_updates->is_installed()) {
            show_error('The client update queue has not been installed.', 503);
        }
    }

    public function index(string $status = 'pending'): void
    {
        if ( ! in_array($status, ['pending', 'resolved'], true)) {
            show_404();
        }

        $this->mdl_client_updates->purge_expired();
        $this->layout->set([
            'records'       => $this->mdl_client_updates->get_by_status($status),
            'status'        => $status,
            'pending_count' => $this->mdl_client_updates->pending_count(),
        ]);
        $this->layout->buffer('content', 'client_updates/index');
        $this->layout->render();
    }

    public function view(int $id): void
    {
        $request = $this->mdl_client_updates->get_request($id);

        if ($request === null) {
            show_404();
        }

        $additional_service_addresses = json_decode((string) ($request->additional_service_addresses ?? ''), true);

        $target = $this->input->get('customer');
        $target = is_string($target) && ctype_digit($target) ? (int)$target : 0;
        try {
            $review = $this->mdl_client_update_review->review($id, $target);
            $dismiss_version = $this->mdl_client_update_review->review($id)['version'];
        } catch (Throwable $e) {
            $this->session->set_flashdata('alert_error', 'Unable to review this submission. Check its customer and address details.');
            redirect('client-updates');
            return;
        }
        $this->layout->set([
            'review' => $review,
            'dismiss_version' => $dismiss_version,
            'review_ready' => $this->mdl_client_update_review->ready(),
            'request'                      => $request,
            'additional_service_addresses' => is_array($additional_service_addresses) ? $additional_service_addresses : [],
        ]);
        $this->layout->buffer('content', 'client_updates/view');
        $this->layout->render();
    }

    public function resolve(int $id): void
    {
        $this->require_post();
        $this->session->set_flashdata('alert_error', 'Review the customer details and use Save and resolve. Nothing was changed.');

        redirect('client-updates/view/' . $id);
    }

    public function apply(int $id): void
    {
        $this->require_post();
        try {
            $target = $this->input->post('customer_id');
            $version = $this->input->post('review_version');
            $chosen = $this->input->post('fields') ?? [];
            if (!is_string($target) || !ctype_digit($target) || !is_string($version) || !is_array($chosen) || array_filter($chosen, fn($v) => !is_string($v))) {
                throw new InvalidArgumentException('Invalid review. Reload and try again.');
            }
            $this->mdl_client_update_review->apply($id, (int)$target, $chosen, $version, (int)$this->session->userdata('user_id'), $this->input->post('separate_customer') === '1');
            $this->session->set_flashdata('alert_success', 'Customer saved and submission resolved. Use View customer below to open the account.');
        } catch (Throwable $e) {
            $this->session->set_flashdata('alert_error', $e instanceof RuntimeException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to save this review. No changes were saved.');
        }
        redirect('client-updates/view/' . $id);
    }

    public function dismiss(int $id): void
    {
        $this->require_post();
        try {
            $version = $this->input->post('review_version');
            $note = $this->input->post('resolution_note');
            if (!is_string($version) || !is_string($note)) {
                throw new InvalidArgumentException('Enter a reason for dismissing this request.');
            }
            $this->mdl_client_update_review->dismiss($id, $version, $note, (int)$this->session->userdata('user_id'));
            $this->session->set_flashdata('alert_success', 'Submission dismissed. No customer details were changed.');
        } catch (Throwable $e) {
            $this->session->set_flashdata('alert_error', $e instanceof RuntimeException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to dismiss this request.');
        }
        redirect('client-updates/view/' . $id);
    }

    public function reopen(int $id): void
    {
        $this->require_post();

        if ($this->mdl_client_updates->reopen($id)) {
            $this->session->set_flashdata('alert_success', 'Client update request reopened.');
        }

        redirect('client-updates/view/' . $id);
    }

    private function require_post(): void
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
    }
}
