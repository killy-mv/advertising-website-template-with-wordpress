<?php
/**
 * Front-end performance tweaks.
 */

defined( 'ABSPATH' ) || exit;

// Save resized copies of uploaded JPEG/PNG photos as WebP (typically 25-35% smaller).
// The original upload is kept untouched.
add_filter( 'image_editor_output_format', function ( $formats ) {
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
} );

// Remove emoji detection script + styles (~15 KB): modern browsers render emoji natively.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );
