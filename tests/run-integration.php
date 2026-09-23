<?php

chdir(__DIR__ . '/..');
$args            = $_SERVER['argv'];
$_SERVER['argv'] = ['index.php', 'property_test'];
$_SERVER['argc'] = 2;
ob_start();
require 'index.php';
ob_end_clean();
exit((new PHPUnit\TextUI\Application())->run(array_merge(['phpunit'], array_slice($args, 1))));
