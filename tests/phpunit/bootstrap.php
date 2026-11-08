<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '0');
ini_set('log_errors', '0');

$root = dirname(dirname(__DIR__));

require_once __DIR__ . '/Harness.php';
require_once $root . '/statsbestmanufacturers.php';
