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
