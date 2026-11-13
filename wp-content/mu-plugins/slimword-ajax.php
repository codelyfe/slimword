<?php
/**
 * Plugin Name: Slim WordPress Ajax
 * Description: Runs plugin Ajax actions without loading the WordPress admin.
 */

add_action( 'wp_loaded', 'slimword_light_ajax', 999 );

/**
 * Plugin Ajax hooks are already registered by the time WordPress finishes loading.
 * Core admin actions are registered later, after admin.php, so those keep the normal path.
 */
function slimword_light_ajax() {
	if ( ! wp_doing_ajax() ) {
		return;
	}
	$action = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : '';
	if ( '' === $action ) {
		return;
	}

	$hook = is_user_logged_in() ? 'wp_ajax_' . $action : 'wp_ajax_nopriv_' . $action;
	if ( ! has_action( $hook ) ) {
		slimword_ajax_note( $action, 'admin' );
		return;
	}
	slimword_ajax_note( $action, 'light' );

	send_origin_headers();
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Robots-Tag: noindex' );
	send_nosniff_header();
	nocache_headers();

	do_action( $hook );
	wp_die( '0' );
}

/**
 * @param string $action Ajax action.
 * @param string $path   light or admin.
 */
function slimword_ajax_note( $action, $path ) {
	$log   = get_option( 'slimword_ajax_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'time'   => time(),
		'action' => substr( $action, 0, 80 ),
		'path'   => $path,
	);
	update_option( 'slimword_ajax_log', array_slice( $log, -40 ), false );
}
