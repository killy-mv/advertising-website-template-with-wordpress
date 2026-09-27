<?php
/**
 * Title: Latest news & events
 * Slug: restaurant-theme/news
 * Categories: restaurant, query
 * Description: The three most recent posts (promotions, events).
 */
?>
<!-- wp:group {"align":"wide","className":"rt-section","layout":{"type":"constrained","contentSize":"1180px"}} -->
<div class="wp-block-group alignwide rt-section">
	<!-- wp:paragraph {"className":"rt-eyebrow"} -->
	<p class="rt-eyebrow">What's on</p>
	<!-- /wp:paragraph -->
	<!-- wp:heading -->
	<h2 class="wp-block-heading">News &amp; events</h2>
	<!-- /wp:heading -->
	<!-- wp:query {"queryId":2,"query":{"perPage":3,"postType":"post","order":"desc","orderBy":"date","inherit":false}} -->
	<div class="wp-block-query">
		<!-- wp:post-template {"className":"rt-cards","layout":{"type":"grid","columnCount":3}} -->
			<!-- wp:post-date {"className":"rt-eyebrow"} /-->
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<!-- wp:post-excerpt {"excerptLength":20} /-->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
