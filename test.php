<?php
require_once('vendor/autoload.php');

$numParallel = 5;
$states = array_chunk(STATE_LIST, $numParallel);

print_r($states);

$pid = pcntl_fork();