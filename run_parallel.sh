#!/bin/bash

# Run init file
php init.php

# Run 10 parallel instances
for i in {0..0}; do
  php scraping.php $i &
done

# Wait for all instances to finish
wait