<?php
/**
 * Extra wp-config settings, loaded via WORDPRESS_CONFIG_EXTRA in docker-compose.yml.
 * Values come from environment variables so the same image works locally and in production.
 */

// Site URL from .env: switch localhost <-> public domain without touching the database.
if ( getenv( 'SITE_URL' ) ) {
	define( 'WP_HOME', rtrim( getenv( 'SITE_URL' ), '/' ) );
	define( 'WP_SITEURL', WP_HOME );
}

define( 'WP_ENVIRONMENT_TYPE', getenv( 'WP_ENVIRONMENT_TYPE' ) ?: 'production' );

// No theme/plugin code editor in wp-admin.
define( 'DISALLOW_FILE_EDIT', true );

// Redis object cache (used by the Redis Object Cache plugin's object-cache.php drop-in).
if ( getenv( 'REDIS_HOST' ) ) {
	define( 'WP_REDIS_HOST', getenv( 'REDIS_HOST' ) );
	define( 'WP_REDIS_PORT', 6379 );
	define( 'WP_REDIS_PREFIX', 'restaurant:' ); // namespace keys if Redis is shared
	define( 'WP_REDIS_TIMEOUT', 1 );
	define( 'WP_REDIS_READ_TIMEOUT', 1 );
	define( 'WP_REDIS_MAXTTL', 86400 );         // nothing lives longer than a day
	define( 'WP_REDIS_GRACEFUL', true );        // Redis down = slower site, not a broken one
}
