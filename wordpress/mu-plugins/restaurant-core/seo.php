<?php
/**
 * SEO basics: meta description, Open Graph tags, and schema.org Restaurant JSON-LD.
 */

defined( 'ABSPATH' ) || exit;

function rc_meta_description() {
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = $post->post_excerpt ?: wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		if ( $text = trim( preg_replace( '/\s+/', ' ', $text ) ) ) {
			return wp_trim_words( $text, 30, '…' );
		}
	}
	return get_bloginfo( 'description' );
}

add_action( 'wp_head', function () {
	$title = wp_get_document_title();
	$desc  = rc_meta_description();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$image = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'large' ) : '';

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 1 );

/* schema.org/Restaurant on the front page: helps search engines show hours, address, menu. */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$info   = rc_info();
	$schema = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Restaurant',
		'name'                => get_bloginfo( 'name' ),
		'description'         => get_bloginfo( 'description' ),
		'url'                 => home_url( '/' ),
		'telephone'           => $info['phone'],
		'email'               => $info['email'],
		'servesCuisine'       => $info['cuisine'],
		'priceRange'          => $info['price_range'],
		'acceptsReservations' => true,
		'address'             => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $info['street'],
			'addressLocality' => $info['city'],
			'addressRegion'   => $info['region'],
			'postalCode'      => $info['postal'],
			'addressCountry'  => $info['country'],
		),
	);

	if ( $menu = get_page_by_path( 'menu' ) ) {
		$schema['hasMenu'] = get_permalink( $menu );
	}

	foreach ( $info['hours'] as $day => $h ) {
		if ( $h['open'] && $h['close'] ) {
			$schema['openingHoursSpecification'][] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => RC_DAYS[ $day ],
				'opens'     => $h['open'],
				'closes'    => $h['close'],
			);
		}
	}

	$schema = array_filter( $schema, fn( $v ) => '' !== $v );
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
} );
