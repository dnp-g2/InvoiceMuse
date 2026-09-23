<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Installs that applied the original one-line 043_1.7.2.sql never received the
 * column added to that file later; 046 backfills it.
 */
#[Group('integration')]
final class SchemaBackfillIntegrationTest extends TestCase
{
    private $ci;

    protected function setUp(): void
    {
        if (getenv('PROPERTY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set PROPERTY_INTEGRATION=1 with the isolated fixture.');
        }
        $this->ci = &get_instance();
        $this->ci->load->model('setup/mdl_setup');
    }

    protected function tearDown(): void
    {
        if (isset($this->ci) && ! $this->ci->db->field_exists('user_passwordreset_token_expiry', 'ip_users')) {
            $this->ci->db->query('ALTER TABLE `ip_users` ADD COLUMN `user_passwordreset_token_expiry` DATETIME NULL DEFAULT NULL AFTER `user_passwordreset_token`');
        }
    }

    #[Test]
    public function it_adds_the_reset_token_expiry_column_when_043_ran_before_it_existed(): void
    {
        if ($this->ci->db->field_exists('user_passwordreset_token_expiry', 'ip_users')) {
            $this->ci->db->query('ALTER TABLE `ip_users` DROP COLUMN `user_passwordreset_token_expiry`');
        }
        $this->ci->db->data_cache = [];

        $this->ci->mdl_setup->upgrade_046_1_7_3();

        $column = $this->ci->db->query("SHOW COLUMNS FROM `ip_users` LIKE 'user_passwordreset_token_expiry'")->row();
        self::assertNotNull($column);
        self::assertSame('datetime', mb_strtolower($column->Type));
        self::assertSame('YES', $column->Null);
    }

    #[Test]
    public function it_is_a_no_op_when_the_column_already_exists(): void
    {
        $this->ci->db->data_cache = [];
        $this->ci->mdl_setup->upgrade_046_1_7_3();
        $this->ci->mdl_setup->upgrade_046_1_7_3();

        self::assertTrue($this->ci->db->field_exists('user_passwordreset_token_expiry', 'ip_users'));
        self::assertSame([], $this->ci->mdl_setup->errors);
    }
}
