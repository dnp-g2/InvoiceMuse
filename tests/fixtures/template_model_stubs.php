<?php

// Minimal CodeIgniter stand-ins for loading Mdl_Templates and template_helper.php without the framework.

defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');

class CI_Model
{
    public object $load;

    public function __construct()
    {
        $this->load = new class () {
            public function helper(string $name): void {}
        };
    }
}

function log_message(string $level, string $message): void {}

function get_setting(string $key): string
{
    return $GLOBALS['template_test_settings'][$key] ?? '';
}
