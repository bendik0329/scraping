#!/bin/bash

# load .env file
source .env
echo "BATCH_COUNT: $BATCH_COUNT"

# Run init file
# echo "Initialize Database..."
# php initialize.php

# Run 10 parallel instances
echo "Running 10 parallel scraping instances..."
for i in {0..$BATCH_COUNT-1}; do
  php main.php $i &
done

# Wait for all instances to finish
echo "Waiting for all instances to finish..."
wait

# Download Images
# echo "Downloading Images..."
# php downloads.php