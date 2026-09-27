<?php
/**
 * Plugin Name: Restaurant Core
 * Description: Menu items, restaurant info, reservations, and SEO schema for the restaurant template.
 * Version:     1.0.0
 *
 * Lives in mu-plugins so content types survive theme switches and can't be deactivated by accident.
 */

defined( 'ABSPATH' ) || exit;

define( 'RC_DIR', __DIR__ . '/restaurant-core' );

require RC_DIR . '/info.php';
require RC_DIR . '/menu.php';
require RC_DIR . '/reservations.php';
require RC_DIR . '/seo.php';
