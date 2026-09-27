<?php
/**
 * Title: Hero
 * Slug: restaurant-theme/hero
 * Categories: restaurant, banner
 * Description: Headline, intro, call-to-action buttons and a signature dish image.
 */
?>
<!-- wp:group {"align":"full","className":"rt-hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull rt-hero">
	<!-- wp:columns {"verticalAlignment":"center","align":"wide"} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:paragraph {"className":"rt-eyebrow"} -->
			<p class="rt-eyebrow">Vietnamese kitchen · Since 2012</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1} -->
			<h1 class="wp-block-heading">Broth simmered for 12 hours. Herbs picked this morning.</h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"large","className":"rt-lede"} -->
			<p class="rt-lede has-large-font-size">Family recipes from Saigon, served in the heart of the city. Come hungry, leave happy.</p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/reservations/">Book a table</a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"is-style-outline"} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/menu/">View the menu</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
			<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"rt-hero__img"} -->
			<figure class="wp-block-image size-full rt-hero__img"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/hero-bowl.svg' ) ); ?>" alt="A steaming bowl of pho with fresh herbs"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
