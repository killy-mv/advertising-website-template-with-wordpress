<?php
/**
 * Plugin Name: Page Cache Purge
 * Description: Clears Nginx's FastCGI page cache when content changes, so visitors never see stale pages.
 * Version:     1.0.0
 *
 * How it works: Nginx stores each cached page as a file named md5(cache key), in the
 * directory shared with this container (NGINX_CACHE_PATH). To purge a URL we rebuild
 * the key exactly like `fastcgi_cache_key` in nginx/conf.d/cache.conf and delete the file.
 * Nginx then treats the page as a MISS and renders it fresh on the next request.
 *
 * Purges are collected during the request and run once at shutdown, so saving a post
 * that fires many hooks still deletes each file only once.
 *
 * Other layers (e.g. Cloudflare) can hook `rc_page_cache_purged` to purge the same URLs.
 */

defined( 'ABSPATH' ) || exit;

final class RC_Page_Cache {

	/** Post types whose change can affect every page (header, footer, templates, styles). */
	const GLOBAL_TYPES = array( 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_block' );

	/** @var array<string, true> */
	private static $urls = array();
	private static $all  = false;

	public static function path() {
		return rtrim( (string) getenv( 'NGINX_CACHE_PATH' ), '/' );
	}

	public static function enabled() {
		return self::path() && is_dir( self::path() );
	}

	/** Cache file for a URL. Mirrors: fastcgi_cache_key "$request_method$host$request_uri"; levels=1:2 */
	public static function file_for( $url, $method = 'GET' ) {
		$parts = wp_parse_url( $url );
		$uri   = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$hash  = md5( $method . strtolower( $parts['host'] ?? '' ) . $uri );
		return self::path() . '/' . substr( $hash, -1 ) . '/' . substr( $hash, -3, 2 ) . '/' . $hash;
	}

	public static function queue_url( $url ) {
		if ( $url ) {
			self::$urls[ $url ] = true;
		}
	}

	public static function queue_all() {
		self::$all = true;
	}

	/** Every public URL that shows this post. */
	public static function queue_post( $post ) {
		$post = get_post( $post );
		if ( ! $post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( in_array( $post->post_type, self::GLOBAL_TYPES, true ) ) {
			self::queue_all();
			return;
		}
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		self::queue_url( get_permalink( $post ) );
		self::queue_url( home_url( '/' ) ); // front page lists news and featured dishes

		if ( 'post' === $post->post_type && get_option( 'page_for_posts' ) ) {
			self::queue_url( get_permalink( get_option( 'page_for_posts' ) ) );
		}
		if ( 'menu_item' === $post->post_type && ( $menu = get_page_by_path( 'menu' ) ) ) {
			self::queue_url( get_permalink( $menu ) );
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			foreach ( wp_get_post_terms( $post->ID, $taxonomy ) as $term ) {
				if ( ! is_wp_error( $link = get_term_link( $term ) ) ) {
					self::queue_url( $link );
				}
			}
		}
	}

	/** Runs once at the end of the request. */
	public static function run() {
		if ( ! self::$all && ! self::$urls ) {
			return;
		}
		if ( self::enabled() ) {
			if ( self::$all ) {
				self::delete_all();
			} else {
				foreach ( array_keys( self::$urls ) as $url ) {
					foreach ( array( 'GET', 'HEAD' ) as $method ) {
						@unlink( self::file_for( $url, $method ) ); // phpcs:ignore -- missing file is fine
					}
				}
			}
		}
		do_action( 'rc_page_cache_purged', self::$all ? 'all' : array_keys( self::$urls ) );
		self::$urls = array();
		self::$all  = false;
	}

	/** @return int Number of cache files deleted. */
	public static function delete_all() {
		$count = 0;
		if ( ! self::enabled() ) {
			return $count;
		}
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::path(), FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( $file->isFile() && @unlink( $file->getPathname() ) ) { // phpcs:ignore
				++$count;
			}
		}
		return $count;
	}

	public static function count() {
		if ( ! self::enabled() ) {
			return 0;
		}
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::path(), FilesystemIterator::SKIP_DOTS ) );
		return iterator_count( new CallbackFilterIterator( $files, fn( $f ) => $f->isFile() ) );
	}
}

/* ---------------------------------------------------------------------------
 * What triggers a purge
 * ------------------------------------------------------------------------- */

// Publishing, updating, or unpublishing anything public.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' === $new || 'publish' === $old ) {
		RC_Page_Cache::queue_post( $post );
	}
}, 10, 3 );

// Before trashing/deleting, while the permalink still resolves.
add_action( 'wp_trash_post', array( 'RC_Page_Cache', 'queue_post' ) );
add_action( 'before_delete_post', array( 'RC_Page_Cache', 'queue_post' ) );

// Menu item price/dietary edited on its own (e.g. via REST, without a post save).
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $key ) {
	if ( str_starts_with( $key, 'rc_' ) && 'publish' === get_post_status( $post_id ) ) {
		RC_Page_Cache::queue_post( $post_id );
	}
}, 10, 3 );

// Comments shown on a post.
add_action( 'transition_comment_status', function ( $new, $old, $comment ) {
	RC_Page_Cache::queue_post( $comment->comment_post_ID );
}, 10, 3 );
add_action( 'comment_post', function ( $id, $approved ) {
	if ( 1 === $approved ) {
		RC_Page_Cache::queue_post( get_comment( $id )->comment_post_ID );
	}
}, 10, 2 );

// Site-wide changes: header, footer, hours, navigation, theme, plugins, URLs.
foreach ( array( 'restaurant_info', 'blogname', 'blogdescription', 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'date_format', 'time_format' ) as $option ) {
	add_action( "update_option_$option", array( 'RC_Page_Cache', 'queue_all' ) );
}
foreach ( array( 'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'edited_term', 'delete_term' ) as $hook ) {
	add_action( $hook, array( 'RC_Page_Cache', 'queue_all' ) );
}

add_action( 'shutdown', array( 'RC_Page_Cache', 'run' ) );

/* ---------------------------------------------------------------------------
 * Admin bar: "Purge page cache" button
 * ------------------------------------------------------------------------- */

add_action( 'admin_bar_menu', function ( WP_Admin_Bar $bar ) {
	if ( ! current_user_can( 'manage_options' ) || ! RC_Page_Cache::enabled() ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'rc-purge-page-cache',
		'title' => 'Purge page cache',
		'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=rc_purge_page_cache' ), 'rc_purge_page_cache' ),
	) );
}, 100 );

add_action( 'admin_post_rc_purge_page_cache', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rc_purge_page_cache' );
	RC_Page_Cache::queue_all();
	wp_safe_redirect( wp_get_referer() ?: admin_url() );
	exit;
} );

/* ---------------------------------------------------------------------------
 * WP-CLI: wp page-cache status | flush | purge <url>...
 * ------------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'page-cache', new class() {
		/** Show whether the cache directory is reachable and how many pages are cached. */
		public function status() {
			if ( ! RC_Page_Cache::enabled() ) {
				WP_CLI::error( 'Cache directory not found. Is NGINX_CACHE_PATH set and the volume mounted?' );
			}
			WP_CLI::log( 'Path:   ' . RC_Page_Cache::path() );
			WP_CLI::log( 'Cached: ' . RC_Page_Cache::count() . ' files' );
		}

		/** Delete every cached page. */
		public function flush() {
			$count = RC_Page_Cache::delete_all();
			do_action( 'rc_page_cache_purged', 'all' );
			WP_CLI::success( "Deleted $count cached pages." );
		}

		/**
		 * Delete specific URLs.
		 *
		 * <url>...
		 * : Full URLs, e.g. http://localhost:8080/menu/
		 */
		public function purge( $args ) {
			foreach ( $args as $url ) {
				WP_CLI::log( $url . ' -> ' . RC_Page_Cache::file_for( $url ) . ( file_exists( RC_Page_Cache::file_for( $url ) ) ? ' (cached)' : ' (not cached)' ) );
				RC_Page_Cache::queue_url( $url );
			}
			RC_Page_Cache::run();
			WP_CLI::success( 'Purged.' );
		}
	} );
}
