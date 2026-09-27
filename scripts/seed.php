<?php
/**
 * Demo content for the restaurant template.
 * Run with: docker compose run --rm wpcli eval-file /scripts/seed.php
 *
 * Idempotent: existing pages, dishes and posts (matched by slug) are left untouched,
 * so it's safe to re-run and it never overwrites the owner's edits.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

/* ---------- Site settings ---------- */

update_option( 'blogdescription', 'Vietnamese street food, slow-cooked with love' );
update_option( 'timezone_string', 'America/New_York' );
update_option( 'date_format', 'F j, Y' );
update_option( 'time_format', 'H:i' );

if ( ! get_option( 'restaurant_info' ) ) {
	$hours = array_fill_keys( array( 'tue', 'wed', 'thu' ), array( 'open' => '11:00', 'close' => '22:00' ) );
	$hours['mon'] = array( 'open' => '', 'close' => '' );
	$hours['fri'] = array( 'open' => '11:00', 'close' => '23:00' );
	$hours['sat'] = array( 'open' => '10:00', 'close' => '23:00' );
	$hours['sun'] = array( 'open' => '10:00', 'close' => '21:00' );
	update_option( 'restaurant_info', array(
		'phone'       => '(555) 010-2468',
		'email'       => 'hello@saigontable.example',
		'street'      => '128 Orchard Street',
		'city'        => 'New York',
		'region'      => 'NY',
		'postal'      => '10002',
		'country'     => 'US',
		'cuisine'     => 'Vietnamese',
		'price_range' => '$$',
		'currency'    => '$',
		'map_query'   => '128 Orchard Street, New York, NY 10002',
		'hours'       => $hours,
	) );
}

/* ---------- Helpers ---------- */

function seed_post( $type, $slug, $args ) {
	$existing = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) );
	if ( $existing ) {
		return $existing[0]->ID;
	}
	$id = wp_insert_post( array_merge( array( 'post_type' => $type, 'post_name' => $slug, 'post_status' => 'publish' ), $args ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	WP_CLI::log( "Created $type: $slug" );
	return $id;
}

function pattern( $slug ) {
	return "<!-- wp:pattern {\"slug\":\"restaurant-theme/$slug\"} /-->";
}

function block_p( $text ) {
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
}

function block_shortcode( $code ) {
	return "<!-- wp:shortcode -->$code<!-- /wp:shortcode -->";
}

/* ---------- Clean up WordPress defaults ---------- */

foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page', 'privacy-policy' => 'page' ) as $slug => $type ) {
	$post = get_page_by_path( $slug, OBJECT, $type );
	if ( $post && 'draft' !== $post->post_status ) {
		wp_delete_post( $post->ID, true );
		WP_CLI::log( "Deleted default $type: $slug" );
	}
}

/* ---------- Pages ---------- */

$pages = array(
	'home'         => array(
		'post_title'   => 'Home',
		'post_content' => implode( "\n\n", array_map( 'pattern', array( 'hero', 'menu-highlights', 'about', 'news', 'visit', 'reserve-cta' ) ) ),
	),
	'menu'         => array(
		'post_title'   => 'Menu',
		'post_excerpt' => 'Everything is made in-house, every day. Ask us about allergies — we are happy to adapt most dishes.',
		'menu_order'   => 1,
		'post_content' => block_shortcode( '[restaurant_menu]' ),
	),
	'about'        => array(
		'post_title'   => 'About',
		'post_excerpt' => 'Three generations, one ladle, and a lot of broth.',
		'menu_order'   => 2,
		'post_content' => implode( "\n\n", array(
			block_p( 'Grandma Lan sold pho from a street cart in District 1, Saigon, for thirty years. Her secret was patience: bones roasted until deep brown, then simmered overnight with charred ginger, onion, star anise and cinnamon.' ),
			block_p( 'In 2012 her granddaughter Mai opened Saigon Table with that same recipe — and the cart\'s old ladle, which still hangs above the pass. Today our kitchen makes everything in-house: broths, sauces, pickles, even the baguettes for our bánh mì.' ),
			block_p( 'We buy herbs and greens from farms within 100 miles, and we change a few dishes with the seasons. Whether you are here for a quick lunch or a long dinner with family, pull up a chair.' ),
		) ),
	),
	'news'         => array(
		'post_title' => 'News',
		'menu_order' => 3,
	),
	'visit'        => array(
		'post_title'   => 'Visit',
		'post_excerpt' => 'Find us on Orchard Street, five minutes from Delancey St station.',
		'menu_order'   => 4,
		'post_content' => pattern( 'visit' ),
	),
	'reservations' => array(
		'post_title'   => 'Reservations',
		'post_excerpt' => 'Request a table and we will confirm within a few hours. For same-day bookings, please call us.',
		'menu_order'   => 5,
		'post_content' => block_shortcode( '[restaurant_reservation_form]' ),
	),
);

$page_ids = array();
foreach ( $pages as $slug => $args ) {
	$page_ids[ $slug ] = seed_post( 'page', $slug, $args );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['home'] );
update_option( 'page_for_posts', $page_ids['news'] );

/* ---------- Navigation (header menu) ---------- */

// WordPress auto-creates a "Navigation" listing every page on first render; replace that fallback.
$navs = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'numberposts' => -1 ) );
$nav  = $navs[0] ?? null;
if ( ! $nav || '<!-- wp:page-list /-->' === trim( $nav->post_content ) ) {
	$links = '';
	foreach ( array( 'menu', 'about', 'news', 'visit' ) as $slug ) {
		$links .= sprintf(
			'<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->',
			get_the_title( $page_ids[ $slug ] ),
			$page_ids[ $slug ],
			wp_make_link_relative( get_permalink( $page_ids[ $slug ] ) )
		);
	}
	wp_insert_post( array( 'ID' => $nav->ID ?? 0, 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Header', 'post_content' => $links ) );
	WP_CLI::log( 'Created navigation: Header' );
}

/* ---------- Menu ---------- */

$categories = array(
	'starters' => array( 'Starters', 'Small plates to share.' ),
	'pho'      => array( 'Phở & Noodle Soups', 'Our 12-hour broths, served with fresh herbs, lime and chili.' ),
	'mains'    => array( 'Rice & Noodle Plates', '' ),
	'banh-mi'  => array( 'Bánh Mì', 'On baguettes baked in-house every morning.' ),
	'drinks'   => array( 'Drinks', '' ),
	'desserts' => array( 'Desserts', '' ),
);

$term_ids = array();
$order    = 0;
foreach ( $categories as $slug => [ $name, $desc ] ) {
	$term = term_exists( $slug, 'menu_category' ) ?: wp_insert_term( $name, 'menu_category', array( 'slug' => $slug, 'description' => $desc ) );
	$term_ids[ $slug ] = (int) $term['term_id'];
	update_term_meta( $term_ids[ $slug ], 'rc_order', ++$order );
}

// [ category, name, slug, description, price, dietary, featured ]
$dishes = array(
	array( 'starters', 'Gỏi Cuốn', 'goi-cuon', 'Fresh rice paper rolls with shrimp, pork, vermicelli and herbs. Peanut hoisin dip.', '8.50', 'gluten-free', false ),
	array( 'starters', 'Chả Giò', 'cha-gio', 'Crispy pork and taro spring rolls, lettuce wraps, nước chấm.', '9.00', '', false ),
	array( 'starters', 'Tofu Rolls', 'tofu-rolls', 'Rice paper rolls with lemongrass tofu, mango and mint.', '8.00', 'vegan,gluten-free', false ),
	array( 'starters', 'Chicken Wings', 'chicken-wings', 'Fish-sauce caramel glaze, fried garlic, Thai chili.', '11.00', 'spicy', false ),
	array( 'pho', 'Phở Bò Đặc Biệt', 'pho-bo-dac-biet', 'The house special: rare steak, brisket, meatballs and tendon in beef broth.', '17.50', 'gluten-free', true ),
	array( 'pho', 'Phở Gà', 'pho-ga', 'Poached chicken in a clear, gingery chicken broth.', '15.00', 'gluten-free', false ),
	array( 'pho', 'Phở Chay', 'pho-chay', 'Mushroom and charred-vegetable broth, tofu, bok choy.', '14.50', 'vegan,gluten-free', false ),
	array( 'pho', 'Bún Bò Huế', 'bun-bo-hue', 'Spicy lemongrass beef noodle soup from Huế, with pork hock.', '17.00', 'spicy', true ),
	array( 'mains', 'Bún Chả Hà Nội', 'bun-cha-ha-noi', 'Grilled pork patties and belly, vermicelli, herbs, warm dipping broth.', '18.00', '', true ),
	array( 'mains', 'Cơm Tấm', 'com-tam', 'Broken rice with grilled pork chop, egg meatloaf, pickles.', '17.00', '', false ),
	array( 'mains', 'Cà Ri Gà', 'ca-ri-ga', 'Coconut chicken curry with sweet potato, served with baguette.', '16.50', 'spicy', false ),
	array( 'banh-mi', 'Bánh Mì Thịt Nướng', 'banh-mi-thit-nuong', 'Lemongrass grilled pork, pâté, pickled daikon & carrot, cilantro.', '11.00', '', false ),
	array( 'banh-mi', 'Bánh Mì Chay', 'banh-mi-chay', 'Five-spice tofu, vegan mayo, pickles, jalapeño.', '10.00', 'vegan,spicy', false ),
	array( 'drinks', 'Cà Phê Sữa Đá', 'ca-phe-sua-da', 'Phin-dripped dark roast over ice with condensed milk.', '5.50', 'vegetarian', false ),
	array( 'drinks', 'Trà Đá', 'tra-da', 'Iced jasmine tea, free refills.', '3.00', 'vegan', false ),
	array( 'drinks', 'Nước Mía', 'nuoc-mia', 'Fresh-pressed sugarcane juice with kumquat.', '6.00', 'vegan', false ),
	array( 'desserts', 'Chè Ba Màu', 'che-ba-mau', 'Three-color dessert: mung bean, red bean, pandan jelly, coconut cream.', '7.00', 'vegan,gluten-free', false ),
	array( 'desserts', 'Bánh Flan', 'banh-flan', 'Vietnamese caramel custard with a splash of coffee.', '6.50', 'vegetarian,gluten-free', false ),
);

foreach ( $dishes as $i => [ $cat, $name, $slug, $desc, $price, $dietary, $featured ] ) {
	$id = seed_post( 'menu_item', $slug, array( 'post_title' => $name, 'post_excerpt' => $desc, 'menu_order' => $i ) );
	if ( ! get_post_meta( $id, 'rc_price', true ) ) {
		wp_set_object_terms( $id, $term_ids[ $cat ], 'menu_category' );
		update_post_meta( $id, 'rc_price', $price );
		update_post_meta( $id, 'rc_dietary', $dietary );
		update_post_meta( $id, 'rc_featured', $featured );
	}
}

/* ---------- News posts ---------- */

$news = array(
	array( 'lunar-new-year-tasting-menu', 'Lunar New Year tasting menu', '-10 days', 'Celebrate Tết with a seven-course family-style menu: bánh chưng, braised pork with egg, and lucky red sticky rice. Book early — seats go fast.' ),
	array( 'pho-happy-hour', 'New: Phở happy hour, weekdays 3–5 pm', '-20 days', 'Half-size bowls of any phở for $8 and $4 Vietnamese coffee, Tuesday to Friday between 3 and 5 pm.' ),
	array( 'cooking-class-banh-mi', 'Cooking class: build the perfect bánh mì', '-35 days', 'Join chef Mai on the last Sunday of the month to learn pickling, pâté and the secret to a crackly baguette. Tickets include lunch.' ),
);
foreach ( $news as [ $slug, $title, $when, $body ] ) {
	seed_post( 'post', $slug, array(
		'post_title'   => $title,
		'post_date'    => wp_date( 'Y-m-d H:i:s', strtotime( $when ) ),
		'post_excerpt' => $body,
		'post_content' => block_p( $body ),
	) );
}

WP_CLI::success( 'Demo content ready.' );
