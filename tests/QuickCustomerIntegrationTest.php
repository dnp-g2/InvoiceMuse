<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
final class QuickCustomerIntegrationTest extends TestCase
{
    private $ci; private $m;
    protected function setUp(): void {
        if(getenv('PROPERTY_INTEGRATION')!=='1'){$this->markTestSkipped('Isolated fixture required.');}
        $this->ci=&get_instance();$this->ci->load->model('clients/mdl_client_quick_create');$this->m=$this->ci->mdl_client_quick_create;
    }
    private function data(array $extra=[]): array {return array_replace(['full_name'=>'Quick '.bin2hex(random_bytes(6)),'email'=>'','phone'=>'','same_billing'=>'1'],$extra);}
    private function create(array $data): array {return $this->m->create($data,$this->m->new_request());}
    #[Test] public function it_creates_name_only_without_matching_blank_contacts(): void {
        $r=$this->create($this->data()); self::assertSame(1,$r['success']);$c=$this->ci->db->get_where('ip_clients',['client_id'=>$r['client']['id']])->row(); self::assertSame(1,(int)$c->client_active);self::assertCount(0,service_properties()->properties((int)$c->client_id));
    }
    #[Test] public function it_creates_property_and_copies_billing_address(): void {
        $data=$this->data(['service_address_1'=>'200 New Lane','service_city'=>'Springfield','service_state'=>'IL','service_zip'=>'62701']);$r=$this->create($data);self::assertSame(1,$r['success']);$id=$r['client']['id'];self::assertCount(1,service_properties()->properties($id));self::assertSame('200 New Lane',$this->ci->db->get_where('ip_clients',['client_id'=>$id])->row()->client_address_1);
    }
    #[Test] public function it_saves_separate_billing(): void {
        $r=$this->create($this->data(['same_billing'=>'0','service_address_1'=>'200 Service Lane','service_city'=>'Springfield','service_state'=>'IL','service_zip'=>'62701','billing_address_1'=>'10 Billing Lane','billing_city'=>'Springfield','billing_state'=>'IL','billing_zip'=>'62701']));self::assertSame(1,$r['success']);self::assertSame('10 Billing Lane',$this->ci->db->get_where('ip_clients',['client_id'=>$r['client']['id']])->row()->client_address_1);
    }
    #[Test] public function it_validates_partial_addresses_and_email_before_writing(): void {
        $before=$this->ci->db->count_all('ip_clients');$r=$this->create($this->data(['email'=>'broken','service_address_1'=>'10 Partial Lane']));self::assertSame(0,$r['success']);self::assertArrayHasKey('email',$r['validation_errors']);self::assertArrayHasKey('service_city',$r['validation_errors']);self::assertSame($before,$this->ci->db->count_all('ip_clients'));
    }
    #[Test] public function it_rejects_blank_name_and_structured_input(): void {
        $r=$this->create(['full_name'=>['bad'],'email'=>['bad']]);self::assertSame(0,$r['success']);self::assertArrayHasKey('full_name',$r['validation_errors']);
    }
    #[Test] public function it_replays_the_same_request_without_duplicates(): void {
        $key=$this->m->new_request();$data=$this->data();$a=$this->m->create($data,$key);$b=$this->m->create($data,$key);self::assertSame($a,$b);self::assertSame(1,$this->ci->db->where('client_name',$data['full_name'])->count_all_results('ip_clients'));
    }
    #[Test] public function it_rejects_changed_payload_for_completed_request(): void {
        $key=$this->m->new_request();$this->m->create($this->data(),$key);$this->expectException(RuntimeException::class);$this->m->create($this->data(),$key);
    }
    #[Test] public function it_requires_confirmation_for_duplicate_and_rechecks_on_retry(): void {
        $data=$this->data();$first=$this->create($data);$key=$this->m->new_request();$conflict=$this->m->create($data,$key);self::assertSame(0,$conflict['success']);self::assertSame($first['client']['id'],$conflict['matches'][0]['id']);
        $second=$this->m->create($data,$key,true,$conflict['match_version']);self::assertSame(1,$second['success']);self::assertNotSame($first['client']['id'],$second['client']['id']);
        $stale=$this->m->create($data,$this->m->new_request(),true,$conflict['match_version']);self::assertSame(0,$stale['success']);self::assertCount(2,$stale['matches']);
    }
    #[Test] public function it_includes_inactive_matches_without_reactivating(): void {
        $data=$this->data();$r=$this->create($data);$id=$r['client']['id'];$this->ci->db->where('client_id',$id)->update('ip_clients',['client_active'=>0]);$conflict=$this->create($data);self::assertFalse($conflict['matches'][0]['active']);self::assertSame(0,(int)$this->ci->db->get_where('ip_clients',['client_id'=>$id])->row()->client_active);
    }
    #[Test] public function it_rolls_back_customer_when_property_insert_fails(): void {
        $before=$this->ci->db->count_all('ip_clients');$this->ci->db->query("CREATE TRIGGER quick_test_failure BEFORE INSERT ON ip_service_properties FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
        try {$this->create($this->data(['service_address_1'=>'200 Fail Lane','service_city'=>'Springfield','service_state'=>'IL','service_zip'=>'62701']));self::fail('Expected failure');}catch(RuntimeException $e){self::assertSame($before,$this->ci->db->count_all('ip_clients'));}finally{$this->ci->db->query('DROP TRIGGER quick_test_failure');}
    }
    #[Test] public function it_rejects_unissued_request_key(): void {
        $this->expectException(RuntimeException::class);$this->m->create($this->data(),str_repeat('a',32));
    }
}
