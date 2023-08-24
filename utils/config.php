<?php

define( 'IN_MAIN', true );

// Enabled so we can do a header() redirect (e.g., in index.php)
ob_start();

session_start();

// Master DB
define( 'DB_HOST', 'localhost' );
define( 'DB_USER', 'bendik' );
define( 'DB_PASS', 'Gwgh!9971014' );
define( 'DB_NAME', 'zillows' );
