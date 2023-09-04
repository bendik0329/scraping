<?php
require 'vendor/autoload.php';

use parallel\Runtime;

$states = ['state1', 'state2', 'state3', 'state4', 'state5', 'state6', 'state7', 'state8', 'state9', 'state10'];
$batchSize = 5; // Number of states to process simultaneously

$runtime = new Runtime();

$chunks = array_chunk($states, $batchSize); // Divide the states into chunks

$runtime->run(function () use ($chunks) {
  foreach ($chunks as $chunk) {
    $threads = [];
    foreach ($chunk as $state) {
      $thread = new \parallel\Thread('scrapeProperties', [$state]);
      $threads[] = $thread;
      $thread->start();
    }

    foreach ($threads as $thread) {
      $thread->join();
    }
  }
});

function scrapeProperties($state)
{
  // Your scraping logic for a single state goes here
  // ...
  echo "Scraping properties for $state\n";
  sleep(5); // Simulating scraping process
  echo "Finished scraping properties for $state\n";
}
