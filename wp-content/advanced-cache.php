<?php
/**
 * Serve a saved public page before WordPress boots.
 */
if ( ! defined( 'ABSPATH' ) || PHP_SAPI === 'cli' ) {
	return;
}
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
	return;
}
$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = (string) parse_url( $uri, PHP_URL_PATH );
if ( preg_match( '#/(wp-admin|wp-login|wp-cron|xmlrpc|admin-ajax)#', $path ) ) {
	return;
}
if ( ! empty( $_GET ) ) {
	return;
}
foreach ( $_COOKIE ?? array() as $name => $value ) {
	unset( $value );
	if ( strpos( (string) $name, 'wordpress_logged_in_' ) === 0 || strpos( (string) $name, 'comment_author' ) === 0 ) {
		return;
	}
}
$key  = sha1( ( $_SERVER['HTTP_HOST'] ?? '' ) . $path );
$file = ABSPATH . 'private/cache/pages/' . $key . '.html';
if ( is_file( $file ) && ( time() - (int) filemtime( $file ) ) < 600 ) {
	header( 'X-Slimword-Cache: hit' );
	readfile( $file );
	exit;
}
