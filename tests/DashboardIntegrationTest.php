<?php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class DashboardIntegrationTest extends TestCase
{
    private $ci;
    private array $tables = ['ip_invoices','ip_invoice_amounts','ip_payments','ip_quotes','ip_quote_amounts','ip_projects','ip_tasks','ip_clients'];
    protected function setUp(): void
    {
        if (getenv('PROPERTY_INTEGRATION') !== '1') { $this->markTestSkipped('Requires isolated testing database.'); }
        $this->ci = &get_instance();
        $this->ci->load->model('dashboard/mdl_dashboard');
        foreach ($this->tables as $table) { $schema=array_values($this->ci->db->query("SHOW CREATE TABLE `$table`")->row_array())[1]; $this->ci->db->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema)); }
    }
    protected function tearDown(): void
    {
        if (!$this->ci) { return; }
        foreach (array_reverse($this->tables) as $table) { $this->ci->db->query("DROP TEMPORARY TABLE IF EXISTS $table"); }
    }
    private function invoice(int $status, int $balance, string $due, int $sign=1, int $parent=0): int
    {
        $this->ci->db->insert('ip_invoices', ['user_id'=>1,'client_id'=>1,'invoice_group_id'=>3,'invoice_status_id'=>$status,'invoice_date_created'=>date('Y-m-d'),'invoice_date_due'=>$due,'invoice_date_modified'=>date('Y-m-d H:i:s'),'invoice_number'=>uniqid('DASH'),'invoice_terms'=>'','invoice_url_key'=>bin2hex(random_bytes(16)),'creditinvoice_parent_id'=>$parent]);
        $id=(int)$this->ci->db->insert_id();
        $this->ci->db->insert('ip_invoice_amounts',['invoice_id'=>$id,'invoice_sign'=>(string)$sign,'invoice_total'=>100,'invoice_paid'=>100-$balance,'invoice_balance'=>$balance]);
        return $id;
    }
    #[Test]
    public function it_handles_an_empty_dashboard_without_writes(): void
    {
        $before=count($this->ci->db->queries);
        $data=$this->ci->mdl_dashboard->overview();
        self::assertSame(0,(int)$data['totals']->invoice_count);
        self::assertSame(0.0,(float)$data['totals']->unpaid_amount);
        self::assertSame(0.0,(float)$data['payments_received']);
        self::assertSame([], $data['invoices']);
        foreach(array_slice($this->ci->db->queries,$before) as $sql) { self::assertDoesNotMatchRegularExpression('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i',$sql); }
    }
    #[Test]
    public function it_matches_collection_lists_and_separates_drafts_credits_and_zero_balances(): void
    {
        $this->ci->db->insert('ip_clients',['client_id'=>1,'client_name'=>'Dashboard fixture','client_date_created'=>date('Y-m-d H:i:s'),'client_date_modified'=>date('Y-m-d H:i:s')]);
        $dates=$this->ci->db->query('SELECT CURDATE() today, CURDATE()-INTERVAL 1 DAY yesterday, CURDATE()+INTERVAL 1 DAY tomorrow')->row();
        $overdue=$this->invoice(2,70,$dates->yesterday);
        $future=$this->invoice(3,50,$dates->tomorrow);
        $today=$this->invoice(2,20,$dates->today);
        $this->invoice(1,200,$dates->yesterday);$this->invoice(4,0,$dates->yesterday);$this->invoice(2,0,$dates->yesterday);
        $this->invoice(2,100,$dates->yesterday,-1);$this->invoice(2,100,$dates->yesterday,1,$overdue);
        $data=$this->ci->mdl_dashboard->overview();
        self::assertSame(3,(int)$data['totals']->unpaid_count);self::assertSame(140.0,(float)$data['totals']->unpaid_amount);
        self::assertSame(1,(int)$data['totals']->overdue_count);self::assertSame(70.0,(float)$data['totals']->overdue_amount);
        self::assertSame(1,(int)$data['totals']->draft_count);self::assertCount(5,$data['invoices']);
        $unpaid=array_map('intval',array_column($this->ci->mdl_invoices->is_unpaid()->get()->result(),'invoice_id'));sort($unpaid);$expected=[$overdue,$future,$today];sort($expected);self::assertSame($expected,$unpaid);
        self::assertSame([$overdue],array_map('intval',array_column($this->ci->mdl_invoices->is_overdue()->get()->result(),'invoice_id')));
    }
    #[Test]
    public function it_uses_payment_dates_and_signed_amounts_at_month_boundaries(): void
    {
        $dates=$this->ci->db->query("SELECT DATE_FORMAT(CURDATE(),'%Y-%m-01') first_day,LAST_DAY(CURDATE()) last_day,DATE_FORMAT(CURDATE(),'%Y-%m-01')-INTERVAL 1 DAY previous_day,LAST_DAY(CURDATE())+INTERVAL 1 DAY next_day")->row();
        foreach ([[$dates->first_day,10],[$dates->last_day,20],[$dates->last_day,-5],[$dates->previous_day,99],[$dates->next_day,77]] as [$date,$amount]) {
            $this->ci->db->insert('ip_payments',['invoice_id'=>1,'payment_date'=>$date,'payment_amount'=>$amount,'payment_method_id'=>1,'payment_note'=>'Fixture']);
        }
        self::assertSame(25.0,(float)$this->ci->mdl_dashboard->overview()['payments_received']);
    }
}
