<?php
/**
 * Menu items: custom post type, categories, price/dietary meta, and display shortcodes.
 */

defined( 'ABSPATH' ) || exit;

const RC_DIETARY = array(
	'vegetarian'  => 'Vegetarian',
	'vegan'       => 'Vegan',
	'gluten-free' => 'Gluten-free',
	'spicy'       => 'Spicy',
);

add_action( 'init', function () {
	register_post_type( 'menu_item', array(
		'labels'        => array(
			'name'          => 'Menu Items',
			'singular_name' => 'Menu Item',
			'add_new_item'  => 'Add New Menu Item',
			'edit_item'     => 'Edit Menu Item',
			'all_items'     => 'All Menu Items',
		),
		'public'        => true,
		'show_in_rest'  => true,
		'has_archive'   => false,
		'menu_icon'     => 'dashicons-carrot',
		'menu_position' => 5,
		'rewrite'       => array( 'slug' => 'dish' ),
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
	) );

	register_taxonomy( 'menu_category', 'menu_item', array(
		'labels'            => array(
			'name'          => 'Menu Categories',
			'singular_name' => 'Menu Category',
		),
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'menu-category' ),
	) );

	$meta = array(
		'rc_price'    => 'string',
		'rc_dietary'  => 'string', // comma-separated keys of RC_DIETARY
		'rc_featured' => 'boolean',
	);
	foreach ( $meta as $key => $type ) {
		register_post_meta( 'menu_item', $key, array(
			'type'          => $type,
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
		) );
	}
	// Display order of categories on the menu page.
	register_term_meta( 'menu_category', 'rc_order', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true ) );
} );

/* ---------------------------------------------------------------------------
 * Admin: meta box + list columns
 * ------------------------------------------------------------------------- */

add_action( 'add_meta_boxes_menu_item', function () {
	add_meta_box( 'rc-menu-details', 'Dish details', 'rc_render_menu_meta_box', 'menu_item', 'side', 'high' );
} );

function rc_render_menu_meta_box( $post ) {
	wp_nonce_field( 'rc_menu_meta', 'rc_menu_nonce' );
	$price   = get_post_meta( $post->ID, 'rc_price', true );
	$dietary = rc_item_dietary( $post->ID );
	$feature = get_post_meta( $post->ID, 'rc_featured', true );
	?>
	<p><label>Price<br><input name="rc_price" value="<?php echo esc_attr( $price ); ?>" placeholder="12.50" style="width:100%"></label></p>
	<p>Dietary</p>
	<?php foreach ( RC_DIETARY as $key => $label ) : ?>
		<label style="display:block"><input type="checkbox" name="rc_dietary[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $dietary, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
	<?php endforeach; ?>
	<p><label><input type="checkbox" name="rc_featured" value="1" <?php checked( $feature ); ?>> Feature on home page</label></p>
	<?php
}

add_action( 'save_post_menu_item', function ( $post_id ) {
	if ( ! isset( $_POST['rc_menu_nonce'] ) || ! wp_verify_nonce( $_POST['rc_menu_nonce'], 'rc_menu_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$price   = preg_replace( '/[^\d.,]/', '', wp_unslash( $_POST['rc_price'] ?? '' ) );
	$dietary = array_intersect( (array) ( $_POST['rc_dietary'] ?? array() ), array_keys( RC_DIETARY ) );
	update_post_meta( $post_id, 'rc_price', $price );
	update_post_meta( $post_id, 'rc_dietary', implode( ',', $dietary ) );
	update_post_meta( $post_id, 'rc_featured', ! empty( $_POST['rc_featured'] ) );
} );

add_filter( 'manage_menu_item_posts_columns', function ( $cols ) {
	$cols['rc_price']    = 'Price';
	$cols['rc_featured'] = 'Featured';
	return $cols;
} );

add_action( 'manage_menu_item_posts_custom_column', function ( $col, $post_id ) {
	if ( 'rc_price' === $col ) {
		echo esc_html( rc_format_price( get_post_meta( $post_id, 'rc_price', true ) ) );
	} elseif ( 'rc_featured' === $col ) {
		echo get_post_meta( $post_id, 'rc_featured', true ) ? '★' : '';
	}
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

function rc_item_dietary( $post_id ) {
	return array_filter( explode( ',', (string) get_post_meta( $post_id, 'rc_dietary', true ) ) );
}

function rc_format_price( $price ) {
	return '' === (string) $price ? '' : rc_info( 'currency' ) . $price;
}

/** Menu categories in display order. */
function rc_menu_categories() {
	$terms = get_terms( array( 'taxonomy' => 'menu_category', 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort( $terms, fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'rc_order', true ) <=> (int) get_term_meta( $b->term_id, 'rc_order', true ) );
	return $terms;
}

function rc_render_menu_item( WP_Post $item ) {
	$tags = '';
	foreach ( rc_item_dietary( $item->ID ) as $key ) {
		$tags .= sprintf( '<li class="rc-tag rc-tag--%s">%s</li>', esc_attr( $key ), esc_html( RC_DIETARY[ $key ] ?? $key ) );
	}
	return sprintf(
		'<li class="rc-menu-item"><div class="rc-menu-item__head"><h3 class="rc-menu-item__name">%s</h3><span class="rc-menu-item__dots" aria-hidden="true"></span><span class="rc-menu-item__price">%s</span></div>%s%s</li>',
		esc_html( get_the_title( $item ) ),
		esc_html( rc_format_price( get_post_meta( $item->ID, 'rc_price', true ) ) ),
		$item->post_excerpt ? '<p class="rc-menu-item__desc">' . esc_html( $item->post_excerpt ) . '</p>' : '',
		$tags ? '<ul class="rc-tags">' . $tags . '</ul>' : ''
	);
}

/* ---------------------------------------------------------------------------
 * [restaurant_menu category="slug"]  Full menu grouped by category.
 * ------------------------------------------------------------------------- */

add_shortcode( 'restaurant_menu', function ( $atts ) {
	$atts  = shortcode_atts( array( 'category' => '' ), $atts );
	$terms = rc_menu_categories();
	if ( $atts['category'] ) {
		$terms = array_filter( $terms, fn( $t ) => $t->slug === $atts['category'] );
	}

	$out = '<div class="rc-menu">';
	foreach ( $terms as $term ) {
		$items = get_posts( array(
			'post_type'      => 'menu_item',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array( array( 'taxonomy' => 'menu_category', 'terms' => $term->term_id ) ),
		) );
		if ( ! $items ) {
			continue;
		}
		$out .= sprintf( '<section class="rc-menu-section" id="%s"><h2 class="rc-menu-section__title">%s</h2>', esc_attr( $term->slug ), esc_html( $term->name ) );
		if ( $term->description ) {
			$out .= '<p class="rc-menu-section__desc">' . esc_html( $term->description ) . '</p>';
		}
		$out .= '<ul class="rc-menu-list">' . implode( '', array_map( 'rc_render_menu_item', $items ) ) . '</ul></section>';
	}
	return $out . '</div>';
} );

/* ---------------------------------------------------------------------------
 * [restaurant_menu_highlights count="3"]  Featured dishes as cards.
 * ------------------------------------------------------------------------- */

add_shortcode( 'restaurant_menu_highlights', function ( $atts ) {
	$atts  = shortcode_atts( array( 'count' => 3 ), $atts );
	$items = get_posts( array(
		'post_type'      => 'menu_item',
		'posts_per_page' => (int) $atts['count'],
		'meta_key'       => 'rc_featured',
		'meta_value'     => '1',
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	) );

	$out = '<ul class="rc-highlights">';
	foreach ( $items as $item ) {
		$thumb = get_the_post_thumbnail( $item, 'medium_large', array( 'class' => 'rc-highlight__img', 'loading' => 'lazy' ) );
		$out  .= sprintf(
			'<li class="rc-highlight">%s<div class="rc-highlight__body"><h3>%s</h3><p>%s</p><span class="rc-highlight__price">%s</span></div></li>',
			$thumb ?: '<div class="rc-highlight__img rc-highlight__img--placeholder" aria-hidden="true"></div>',
			esc_html( get_the_title( $item ) ),
			esc_html( $item->post_excerpt ),
			esc_html( rc_format_price( get_post_meta( $item->ID, 'rc_price', true ) ) )
		);
	}
	return $out . '</ul>';
} );
