<?php

require_once __DIR__ . '/../application/libraries/Property_rules.php';
if (getenv('PROPERTY_INTEGRATION') === '1' && ! defined('BASEPATH')) {
    global $CFG, $LANG, $BM, $EXT, $UNI, $URI, $RTR, $OUT, $SEC, $IN;
    chdir(__DIR__ . '/..');
    $_SERVER['argv'] = ['index.php', 'property_test'];
    $_SERVER['argc'] = 2;
    ob_start();
    require 'index.php';
    ob_end_clean();
}
