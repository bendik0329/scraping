<?php
require_once('vendor/autoload.php');
require_once  __DIR__ . '/utils/constants.php';

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

$numParallel = 5;
$pids = [];
$chunks = array_chunk(STATE_LIST, $numParallel);

print_r($chunks);

foreach ($chunks as $chunk) {
  $pid = pcntl_fork();

  if ($pid == -1) {
      // Fork failed
      die('Could not fork');
  } elseif ($pid == 0) {
      // Child process
      // Create a new WebDriver instance
      // $driver = RemoteWebDriver::create($host, $capabilities);

      // Process each state in the chunk
      foreach ($chunk as $state) {
          // Add your code here to control the Selenium instance for each state
          // For example, navigate to a website and perform actions
          echo "Processing state: $state\n";
      }

      // Quit the WebDriver instance
      $driver->quit();

      exit(); // Exit the child process
  } else {
      // Parent process
      $pids[] = $pid;
  }
}

// Wait for all child processes to finish
foreach ($pids as $pid) {
  pcntl_waitpid($pid, $status);
}