#!/bin/bash

# Create a lock file to ensure initialization is run only once
LOCK_FILE=".init_lock"

if [ ! -f $LOCK_FILE ]; then
  php init.php
  touch $LOCK_FILE
fi

# Run 10 parallel instances
for i in {0..1}; do
  php index.php $i &
done

# Wait for all instances to finish
wait

# Remove the lock file
rm -f $LOCK_FILE
