#!/bin/bash

# Run init file
echo "Initialize Database..."
php init.php

# Run 10 parallel instances
echo "Running 10 parallel scraping instances..."
for i in {0..0}; do
  php index.php $i &
done

# Wait for all instances to finish
echo "Waiting for all instances to finish..."
wait

# Download Images
echo "Downloading Images..."
php downloads.php