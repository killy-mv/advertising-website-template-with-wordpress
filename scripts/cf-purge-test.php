<?php
/**
 * Dry-run of the Cloudflare purge: intercepts the HTTP call instead of sending it.
 * docker compose run --rm -e CF_ZONE_ID=test -e CF_API_TOKEN=test wpcli eval-file /scripts/cf-purge-test.php
 */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	WP_CLI::log( "POST $url" );
	WP_CLI::log( '  ' . $args['body'] );
	return array( 'response' => array( 'code' => 200 ), 'body' => '{"success":true}', 'headers' => array(), 'cookies' => array() );
}, 10, 3 );

WP_CLI::log( '1) Editing a dish (targeted purge):' );
$dish = get_page_by_path( 'pho-ga', OBJECT, 'menu_item' );
update_post_meta( $dish->ID, 'rc_price', get_post_meta( $dish->ID, 'rc_price', true ) . '0' );
RC_Page_Cache::run();
update_post_meta( $dish->ID, 'rc_price', substr( get_post_meta( $dish->ID, 'rc_price', true ), 0, -1 ) );
RC_Page_Cache::run();

WP_CLI::log( '2) Changing opening hours (site-wide purge):' );
RC_Page_Cache::queue_all();
RC_Page_Cache::run();
