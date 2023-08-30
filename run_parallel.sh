#!/bin/bash

# Run the initialization step only once
php initialize.php

# Run 10 parallel instances
for i in {0..9}; do
  php index.php $i &
done

# Wait for all instances to finish
wait
