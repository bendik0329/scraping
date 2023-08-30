#!/bin/bash

# Run 10 parallel instances
for i in {0..4}; do
  php index.php $i &
done

# Wait for all instances to finish
wait
