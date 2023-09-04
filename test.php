<?php
require_once('vendor/autoload.php');
require_once  __DIR__ . '/utils/constants.php';

$numParallel = 5;
$states = array_chunk(STATE_LIST, $numParallel);

print_r($states);

$pid = pcntl_fork();