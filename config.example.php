<?php
// Copy this file to config.php and fill in your values.
// config.php is excluded from git — never commit it.

// ---------------------------------------------------------------------------
// Database (from Dreamhost panel → MySQL Databases)
// ---------------------------------------------------------------------------
define('DB_HOST', 'mysql.yourdomain.com');   // Dreamhost MySQL hostname
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// ---------------------------------------------------------------------------
// Admin password
// Generate the hash by running this command on any machine with PHP:
//   php -r "echo password_hash('your_chosen_password', PASSWORD_DEFAULT) . PHP_EOL;"
// Paste the resulting string (starting with $2y$) below.
// ---------------------------------------------------------------------------
define('ADMIN_PASSWORD_HASH', '$2y$10$REPLACE_THIS_WITH_YOUR_GENERATED_HASH');

// ---------------------------------------------------------------------------
// Site identity
// ---------------------------------------------------------------------------
define('SITE_TITLE', "Andy's Wishlist");
