<?php
/**
 * Plugin Name: Cloudflare Purge
 * Description: When the Nginx page cache is purged, purge the same URLs from Cloudflare's edge cache.
 * Version:     1.0.0
 *
 * Needs CF_ZONE_ID and CF_API_TOKEN (token permission: Zone → Cache Purge → Purge).
 * Does nothing when they're empty, e.g. during local development.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rc_page_cache_purged', 'rc_cloudflare_purge' );

/**
 * @param string|string[] $what 'all' or a list of URLs.
 */
function rc_cloudflare_purge( $what ) {
	$zone  = getenv( 'CF_ZONE_ID' );
	$token = getenv( 'CF_API_TOKEN' );
	if ( ! $zone || ! $token ) {
		return;
	}

	// The API accepts up to 30 URLs per call on the Free plan.
	$bodies = 'all' === $what
		? array( array( 'purge_everything' => true ) )
		: array_map( fn( $chunk ) => array( 'files' => $chunk ), array_chunk( array_values( (array) $what ), 30 ) );

	foreach ( $bodies as $body ) {
		$response = wp_remote_post( "https://api.cloudflare.com/client/v4/zones/$zone/purge_cache", array(
			'timeout' => 5,
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			error_log( 'Cloudflare purge failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ) ) );
		}
	}
}
