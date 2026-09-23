<?php

// Minimal CodeIgniter stand-ins for loading Mdl_Templates and template_helper.php without the framework.

defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');

class CI_Model
{
    public object $load;

    public object $db;

    public function __construct()
    {
        $this->load = new class () {
            public function helper(string $name): void {}
        };

        // Returns the rows in $GLOBALS['template_test_rows'][<table>] for any query.
        $this->db = new class () {
            public function select(string $columns): self
            {
                return $this;
            }

            public function get(string $table): object
            {
                return new class ($GLOBALS['template_test_rows'][$table] ?? []) {
                    public function __construct(private array $rows) {}

                    public function result(): array
                    {
                        return $this->rows;
                    }
                };
            }
        };
    }
}

function log_message(string $level, string $message): void {}

function get_setting(string $key): string
{
    return $GLOBALS['template_test_settings'][$key] ?? '';
}
