#!/bin/bash

# Run init file
php init.php

# Run 10 parallel instances
for i in {0..16}; do
  php index.php $i &
done

# Wait for all instances to finish
wait

# Download Images
php downloads.php