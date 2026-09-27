<?php
/**
 * Title: Menu highlights
 * Slug: restaurant-theme/menu-highlights
 * Categories: restaurant
 * Description: Featured dishes (tick "Feature on home page" on a menu item).
 */
?>
<!-- wp:group {"align":"wide","className":"rt-section","layout":{"type":"constrained","contentSize":"1180px"}} -->
<div class="wp-block-group alignwide rt-section">
	<!-- wp:paragraph {"className":"rt-eyebrow"} -->
	<p class="rt-eyebrow">Chef's picks</p>
	<!-- /wp:paragraph -->
	<!-- wp:heading -->
	<h2 class="wp-block-heading">Signature dishes</h2>
	<!-- /wp:heading -->
	<!-- wp:shortcode -->[restaurant_menu_highlights count="3"]<!-- /wp:shortcode -->
	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/menu/">See the full menu</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
