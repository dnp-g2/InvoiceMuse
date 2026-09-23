<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Mdl_dashboard extends CI_Model
{
    /** Read-only dashboard data. No reconciliation, publication or submission cleanup. */
    public function overview(): array
    {
        $this->load->model('invoices/mdl_invoices');
        $this->load->model('quotes/mdl_quotes');
        $this->load->model('tasks/mdl_tasks');
        $unpaid = Mdl_Invoices::collection_condition();
        $overdue = Mdl_Invoices::collection_condition(true);
        $totals = $this->db->query("SELECT
            SUM(CASE WHEN $unpaid THEN 1 ELSE 0 END) AS unpaid_count,
            COALESCE(SUM(CASE WHEN $unpaid THEN ip_invoice_amounts.invoice_balance ELSE 0 END),0) AS unpaid_amount,
            SUM(CASE WHEN $overdue THEN 1 ELSE 0 END) AS overdue_count,
            COALESCE(SUM(CASE WHEN $overdue THEN ip_invoice_amounts.invoice_balance ELSE 0 END),0) AS overdue_amount,
            SUM(CASE WHEN ip_invoices.invoice_status_id=1 THEN 1 ELSE 0 END) AS draft_count,
            COUNT(*) AS invoice_count
            FROM ip_invoices LEFT JOIN ip_invoice_amounts ON ip_invoice_amounts.invoice_id=ip_invoices.invoice_id")->row();
        $dates = $this->db->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-01') AS month_start, DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01') AS next_month")->row();
        $payments = $this->db->select('COALESCE(SUM(payment_amount),0) AS received', false)
            ->where('payment_date >=', $dates->month_start)->where('payment_date <', $dates->next_month)->get('ip_payments')->row();
        $projectsEnabled = get_setting('projects_enabled') == 1;
        $data = [
            'totals' => $totals,
            'active_customers' => (int) $this->db->where('client_active', 1)->count_all_results('ip_clients'),
            'updates_available' => $this->db->table_exists('ip_client_update_requests'),
            'pending_updates' => 0,
            'payments_received' => $payments->received,
            'payment_month' => date('F Y', strtotime($dates->month_start)),
            'invoices' => $this->mdl_invoices->order_by('ip_invoices.invoice_date_created', 'DESC')->order_by('ip_invoices.invoice_time_created', 'DESC')->order_by('ip_invoices.invoice_id', 'DESC')->limit(5)->get()->result(),
            'invoice_statuses' => $this->mdl_invoices->statuses(),
            'quotes' => $this->mdl_quotes->order_by('ip_quotes.quote_date_created', 'DESC')->order_by('ip_quotes.quote_id', 'DESC')->limit(5)->get()->result(),
            'quote_count' => (int) $this->db->count_all('ip_quotes'),
            'quote_statuses' => $this->mdl_quotes->statuses(),
            'projects_enabled' => $projectsEnabled,
            'projects' => [], 'tasks' => [], 'project_count' => 0, 'task_count' => 0,
            'task_statuses' => $this->mdl_tasks->statuses(),
        ];
        if ($data['updates_available']) {
            $data['pending_updates'] = (int) $this->db->where('status', 'pending')->count_all_results('ip_client_update_requests');
        }
        if ($projectsEnabled) {
            $data['project_count'] = (int) $this->db->count_all('ip_projects');
            $data['task_count'] = (int) $this->db->count_all('ip_tasks');
            $data['projects'] = $this->db->select('ip_projects.*,ip_clients.client_name,ip_clients.client_surname,ip_clients.client_title')
                ->join('ip_clients', 'ip_clients.client_id=ip_projects.client_id', 'left')->order_by('project_id', 'DESC')->limit(5)->get('ip_projects')->result();
            $data['tasks'] = $this->db->select('ip_tasks.*,ip_projects.project_name')->join('ip_projects', 'ip_projects.project_id=ip_tasks.project_id', 'left')
                ->order_by('task_id', 'DESC')->limit(5)->get('ip_tasks')->result();
        }
        return $data;
    }
}
