<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Mdl_Client_Updates extends CI_Model
{
    private const TABLE = 'ip_client_update_requests';

    public function is_installed(): bool
    {
        return $this->db->table_exists(self::TABLE);
    }

    public function create(array $data): int
    {
        $this->db->insert(self::TABLE, $data);

        return (int) $this->db->insert_id();
    }

    public function reference_exists(string $reference): bool
    {
        return $this->db
            ->where('request_reference', $reference)
            ->count_all_results(self::TABLE) > 0;
    }

    public function get_request(int $id): ?object
    {
        $request = $this->db
            ->where('request_id', $id)
            ->get(self::TABLE)
            ->row();

        return $request ?: null;
    }

    public function get_by_status(string $status): array
    {
        return $this->db
            ->where('status', $status)
            ->order_by('submitted_at', 'DESC')
            ->limit(250)
            ->get(self::TABLE)
            ->result();
    }

    public function pending_count(): int
    {
        if ( ! $this->is_installed()) {
            return 0;
        }

        return (int) $this->db
            ->where('status', 'pending')
            ->count_all_results(self::TABLE);
    }

    public function resolve(int $id, int $user_id, string $note): bool
    {
        $this->db
            ->set('status', 'resolved')
            ->set('resolution_note', $note !== '' ? $note : null)
            ->set('resolved_at', gmdate('Y-m-d H:i:s'))
            ->set('resolved_by', $user_id)
            ->where('request_id', $id)
            ->where('status', 'pending')
            ->update(self::TABLE);

        return $this->db->affected_rows() === 1;
    }

    public function reopen(int $id): bool
    {
        $this->db
            ->set('status', 'pending')
            ->set('resolution_note', null)
            ->set('resolved_at', null)
            ->set('resolved_by', null)
            ->where('request_id', $id)
            ->where('status', 'resolved')
            ->update(self::TABLE);

        return $this->db->affected_rows() === 1;
    }

    public function purge_expired(): int
    {
        if ( ! $this->is_installed()) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - (90 * 86400));
        $this->db
            ->where('status', 'resolved')
            ->where('resolved_at IS NOT NULL', null, false)
            ->where('resolved_at <', $cutoff)
            ->delete(self::TABLE);

        return $this->db->affected_rows();
    }
}
