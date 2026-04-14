<?php
define( 'WP_CACHE', false ); // Added by WP Rocket

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $_SERVER['HTTPS'] = 'on';

/**
 * HCP CarePharma — env-driven base configuration.
 *
 * Every secret (DB credentials, salts) is read from the process
 * environment. In local dev those come from docker-compose via the `.env`
 * file; in staging/prod they come from App Service configuration or
 * webserver-level env vars. This file contains NO production values.
 *
 * @package WordPress
 */

// ** MySQL settings ** //
define( 'DB_NAME',     getenv('WORDPRESS_DB_NAME')     ?: '' );
define( 'DB_USER',     getenv('WORDPRESS_DB_USER')     ?: '' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: '' );
define( 'DB_HOST',     getenv('WORDPRESS_DB_HOST')     ?: 'db:3306' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

/**#@+
 * Authentication unique keys and salts — all from env.
 * Generate dev values with:  curl -s https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         getenv('WORDPRESS_AUTH_KEY')         ?: '' );
define( 'SECURE_AUTH_KEY',  getenv('WORDPRESS_SECURE_AUTH_KEY')  ?: '' );
define( 'LOGGED_IN_KEY',    getenv('WORDPRESS_LOGGED_IN_KEY')    ?: '' );
define( 'NONCE_KEY',        getenv('WORDPRESS_NONCE_KEY')        ?: '' );
define( 'AUTH_SALT',        getenv('WORDPRESS_AUTH_SALT')        ?: '' );
define( 'SECURE_AUTH_SALT', getenv('WORDPRESS_SECURE_AUTH_SALT') ?: '' );
define( 'LOGGED_IN_SALT',   getenv('WORDPRESS_LOGGED_IN_SALT')   ?: '' );
define( 'NONCE_SALT',       getenv('WORDPRESS_NONCE_SALT')       ?: '' );
/**#@-*/

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'tbstwp_';

// Debugging — all booleans read from env, default on in dev.
define( 'WP_DEBUG',         filter_var( getenv('WORDPRESS_DEBUG')         ?: '1', FILTER_VALIDATE_BOOLEAN ) );
define( 'WP_DEBUG_DISPLAY', filter_var( getenv('WORDPRESS_DEBUG_DISPLAY') ?: '0', FILTER_VALIDATE_BOOLEAN ) );
define( 'SCRIPT_DEBUG',     filter_var( getenv('WORDPRESS_SCRIPT_DEBUG')  ?: '1', FILTER_VALIDATE_BOOLEAN ) );
define( 'WP_DEBUG_LOG',     true ); // writes to /wp-content/debug.log
define( 'DISALLOW_FILE_EDIT', false );

/* That's all, stop editing! Happy publishing. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
