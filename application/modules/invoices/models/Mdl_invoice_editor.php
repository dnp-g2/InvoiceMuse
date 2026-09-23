<?php

defined('BASEPATH') || exit('No direct script access allowed');

/** Validation shared by the staged editor and its save endpoint. */
class Mdl_invoice_editor extends CI_Model
{
    public function validate(object $invoice, array $items, array $deletedIds): void
    {
        $old = array_column($this->db->get_where('ip_invoice_items', ['invoice_id' => $invoice->invoice_id])->result_array(), null, 'item_id');
        $readOnly = $invoice->is_read_only && !$this->config->item('disable_read_only');
        $seen = [];
        foreach ($deletedIds as $id) {
            if (!is_int($id) || $id < 1 || !isset($old[$id]) || isset($seen[$id]) || $readOnly) {
                throw new InvalidArgumentException('This charge cannot be removed. Reload and review the invoice.');
            }
            $seen[$id] = true;
        }
        foreach ($items as $item) {
            if (!is_object($item)) {
                throw new InvalidArgumentException('Invalid charge.');
            }
            $id = (int) ($item->item_id ?? 0);
            if ($id && (!isset($old[$id]) || isset($seen[$id]))) {
                throw new InvalidArgumentException('Document lines changed. Reload before saving.');
            }
            if ($id) $seen[$id] = true;
            if (empty($item->item_name)) {
                if ($id || !empty($item->item_quantity) || !empty($item->item_price) || !empty($item->item_description)) {
                    throw new InvalidArgumentException('Every charge needs a service name.');
                }
                continue;
            }
            $item->invoice_id = (int) $invoice->invoice_id;
            if ($readOnly) {
                if (!$id) throw new InvalidArgumentException('This invoice is read only.');
                foreach (['item_name', 'item_description', 'item_product_id', 'item_task_id', 'item_product_unit_id', 'item_tax_rate_id', 'item_order', 'item_is_recurring'] as $field) {
                    if ($field === 'item_is_recurring' && ($old[$id][$field] ?? null) === null && (string)($item->$field ?? '') === '1') continue;
                    if (property_exists($item, $field) && (string) $item->$field !== (string) ($old[$id][$field] ?? '')) {
                        // Null numeric IDs are displayed as zero in native selects.
                        if (!in_array($field, ['item_product_id', 'item_task_id', 'item_product_unit_id', 'item_tax_rate_id'], true) || (int)$item->$field !== (int)($old[$id][$field] ?? 0)) {
                            throw new InvalidArgumentException('This invoice is read only.');
                        }
                    }
                }
                foreach (['item_quantity', 'item_price', 'item_discount_amount'] as $field) {
                    if ((float) standardize_amount($item->$field ?? 0) !== (float) $old[$id][$field]) {
                        throw new InvalidArgumentException('This invoice is read only.');
                    }
                }
            }
        }
        if (count($seen) !== count($old)) {
            throw new InvalidArgumentException('Document lines changed. Reload before saving.');
        }
        if ($readOnly) {
            if ($this->input->post('property_refresh') === '1') throw new InvalidArgumentException('This invoice is read only.');
            foreach (['invoice_number', 'invoice_password', 'invoice_terms'] as $field) {
                if ((string)$this->input->post($field) !== (string)$invoice->$field) {
                    throw new InvalidArgumentException('This invoice is read only.');
                }
            }
            foreach (['invoice_date_created', 'invoice_date_due'] as $field) {
                if (date_to_mysql($this->input->post($field)) !== $invoice->$field) {
                    throw new InvalidArgumentException('This invoice is read only.');
                }
            }
            foreach (['invoice_discount_amount', 'invoice_discount_percent'] as $field) {
                if ((float)standardize_amount($this->input->post($field)) !== (float)$invoice->$field) {
                    throw new InvalidArgumentException('This invoice is read only.');
                }
            }
            if ((int)$invoice->invoice_status_id === 4 && ((int)$this->input->post('invoice_status_id') !== 4 || (int)$this->input->post('payment_method') !== (int)$invoice->payment_method)) {
                throw new InvalidArgumentException('This paid invoice is read only.');
            }
        }
    }

    public function remove_lines(int $invoiceId, array $ids): void
    {
        $this->load->model(['invoices/mdl_items', 'tasks/mdl_tasks']);
        foreach ($ids as $id) {
            $line = $this->db->get_where('ip_invoice_items', ['invoice_id' => $invoiceId, 'item_id' => $id])->row();
            if (!$line || !$this->mdl_items->delete($id, false) || $this->db->get_where('ip_invoice_items', ['item_id' => $id])->num_rows()) {
                throw new RuntimeException('Removal did not finish. Reload and review this invoice before saving again.');
            }
            if ($line->item_task_id && !$this->db->get_where('ip_invoice_items', ['item_task_id' => $line->item_task_id])->num_rows()) {
                $this->mdl_tasks->update_status(3, $line->item_task_id);
            }
        }
    }
}
