<?php
/**
 * Title: Visit us (hours, address, map)
 * Slug: restaurant-theme/visit
 * Categories: restaurant, contact
 * Description: Opening hours and contact from Settings → Restaurant, plus a map.
 */
?>
<!-- wp:group {"align":"wide","className":"rt-section rt-visit","layout":{"type":"constrained","contentSize":"1180px"}} -->
<div class="wp-block-group alignwide rt-section rt-visit">
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"40%"} -->
		<div class="wp-block-column" style="flex-basis:40%">
			<!-- wp:paragraph {"className":"rt-eyebrow"} -->
			<p class="rt-eyebrow">Visit us</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading">Hours &amp; location</h2>
			<!-- /wp:heading -->
			<!-- wp:shortcode -->[restaurant_hours]<!-- /wp:shortcode -->
			<!-- wp:shortcode -->[restaurant_contact]<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"width":"60%"} -->
		<div class="wp-block-column" style="flex-basis:60%">
			<!-- wp:shortcode -->[restaurant_map]<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
