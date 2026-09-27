<?php
/**
 * Restaurant Theme setup.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_editor_style( 'assets/css/theme.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
	$path = get_theme_file_path( 'assets/css/theme.css' );
	wp_enqueue_style( 'restaurant-theme', get_theme_file_uri( 'assets/css/theme.css' ), array(), (string) filemtime( $path ) );
} );

add_action( 'init', function () {
	register_block_pattern_category( 'restaurant', array( 'label' => __( 'Restaurant', 'restaurant-theme' ) ) );
} );
