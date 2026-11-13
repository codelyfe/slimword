<?php
/**
 * Sample config for Slim WordPress on SQLite.
 * Copy this file to wp-config.php.
 */

define( 'DB_ENGINE', 'sqlite' );
define( 'DB_NAME', 'slimword' );
define( 'DB_USER', 'sqlite' );
define( 'DB_PASSWORD', 'sqlite' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_PATH', __DIR__ . '/private/database.sqlite' );

define( 'AUTH_KEY', 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY', 'put your unique phrase here' );
define( 'LOGGED_IN_KEY', 'put your unique phrase here' );
define( 'NONCE_KEY', 'put your unique phrase here' );
define( 'AUTH_SALT', 'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT', 'put your unique phrase here' );
define( 'NONCE_SALT', 'put your unique phrase here' );

$table_prefix = 'wp_';

define( 'WP_POST_REVISIONS', 3 );
define( 'AUTOSAVE_INTERVAL', 120 );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'WP_DEBUG', false );

require_once __DIR__ . '/wp-content/slimword-https.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'WP_CACHE', true );

require_once ABSPATH . 'wp-settings.php';
