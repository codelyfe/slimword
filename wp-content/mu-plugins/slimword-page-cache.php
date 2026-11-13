<?php
/**
 * Plugin Name: Slim WordPress Page Cache
 * Description: Saves public HTML for visitors who are not logged in.
 */

add_action( 'template_redirect', 'slimword_page_cache_start', 0 );
add_action( 'shutdown', 'slimword_page_cache_store', 0 );
add_action( 'save_post', 'slimword_page_cache_flush' );
add_action( 'deleted_post', 'slimword_page_cache_flush' );
add_action( 'switch_theme', 'slimword_page_cache_flush' );
add_action( 'activated_plugin', 'slimword_page_cache_flush' );
add_action( 'deactivated_plugin', 'slimword_page_cache_flush' );

/**
 * Start capturing a public page.
 */
function slimword_page_cache_start() {
	if ( ! slimword_page_cache_allowed() ) {
		return;
	}
	ob_start();
	$GLOBALS['slimword_page_cache_buffer'] = true;
}

/**
 * Write the captured page.
 */
function slimword_page_cache_store() {
	if ( empty( $GLOBALS['slimword_page_cache_buffer'] ) ) {
		return;
	}
	$html = ob_get_clean();
	if ( ! is_string( $html ) || $html === '' || http_response_code() !== 200 ) {
		return;
	}
	$dir = ABSPATH . 'private/cache/pages';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	$hash = sha1( $host . $path );
	file_put_contents( $dir . '/' . $hash . '.html', $html, LOCK_EX );
	file_put_contents( $dir . '/' . $hash . '.url', $host . $path, LOCK_EX );
}

/**
 * @return bool
 */
function slimword_page_cache_allowed() {
	if ( is_file( ABSPATH . 'private/cache/page-cache.off' ) ) {
		return false;
	}
	if ( is_user_logged_in() || is_admin() || is_preview() || is_feed() || is_search() || is_404() ) {
		return false;
	}
	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' || ! empty( $_GET ) ) {
		return false;
	}
	return true;
}

/**
 * Delete saved pages.
 */
function slimword_page_cache_flush() {
	$dir = ABSPATH . 'private/cache/pages';
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$files = scandir( $dir );
	if ( ! is_array( $files ) ) {
		return;
	}
	foreach ( $files as $file ) {
		if ( substr( $file, -5 ) === '.html' || substr( $file, -4 ) === '.url' ) {
			unlink( $dir . '/' . $file );
		}
	}
}
