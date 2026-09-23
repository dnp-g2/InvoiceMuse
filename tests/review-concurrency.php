<?php
// Isolated CLI test: separate database connections must create only one customer.
putenv('PROPERTY_INTEGRATION=1');
$args = $argv;
require __DIR__ . '/bootstrap.php';
if (ENVIRONMENT !== 'testing' || !is_cli()) { exit(1); }
$ci = &get_instance();
$ci->load->model('client_updates/mdl_client_update_review');
$m = $ci->mdl_client_update_review;
if (($args[1] ?? '') === 'seed') {
    $ref = 'RACE-' . bin2hex(random_bytes(5));
    $ci->db->insert('ip_client_update_requests', ['request_reference'=>$ref,'full_name'=>'Race '.$ref,'email'=>strtolower($ref).'@example.invalid','phone'=>'2025550111','service_address_1'=>'100 Race Lane','service_city'=>'Springfield','service_state'=>'IL','service_zip'=>'62701','mailing_same_as_service'=>1,'status'=>'pending','submitted_at'=>gmdate('Y-m-d H:i:s')]);
    $id = (int)$ci->db->insert_id();
    echo json_encode(['id'=>$id,'version'=>$m->review($id)['version']]),"\n";
} elseif (($args[1] ?? '') === 'apply') {
    echo $m->apply((int)$args[2],0,[],$args[3],1,true),"\n";
} elseif (($args[1] ?? '') === 'verify') {
    $r = $m->review((int)$args[2])['request'];
    $cid = (int)$r['applied_client_id'];
    $clients = $ci->db->where('client_email',$r['email'])->count_all_results('ip_clients');
    $notes = $ci->db->where('client_id',$cid)->count_all_results('ip_client_notes');
    $properties = count(service_properties()->properties($cid));
    if ($clients !== 1 || $notes !== 1 || $properties !== 1) { throw new RuntimeException('Concurrent duplicate'); }
    echo "Concurrent requests produced one customer, one property and one note.\n";
}
