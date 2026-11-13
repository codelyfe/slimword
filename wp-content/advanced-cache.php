<?php
/**
 * Serve a saved public page before WordPress boots.
 */
if ( ! defined( 'ABSPATH' ) || PHP_SAPI === 'cli' ) {
	return;
}
if ( is_file( ABSPATH . 'private/cache/page-cache.off' ) ) {
	return;
}
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
	return;
}
$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = (string) parse_url( $uri, PHP_URL_PATH );
if ( preg_match( '#/(wp-admin|wp-login|wp-cron|xmlrpc|admin-ajax|cart|checkout|my-account)#', $path ) ) {
	return;
}
$skip_file = ABSPATH . 'private/cache/nocache.txt';
if ( is_file( $skip_file ) ) {
	foreach ( preg_split( '/\r\n|\r|\n/', (string) file_get_contents( $skip_file ) ) as $line ) {
		$line = trim( $line );
		if ( $line !== '' && strpos( $path, $line ) !== false ) {
			return;
		}
	}
}
if ( ! empty( $_GET ) ) {
	return;
}
foreach ( $_COOKIE ?? array() as $name => $value ) {
	unset( $value );
	$name = (string) $name;
	if ( strpos( $name, 'wordpress_logged_in_' ) === 0 || strpos( $name, 'wordpress_sec_' ) === 0 || strpos( $name, 'comment_author' ) === 0 ) {
		return;
	}
}
$ttl  = 600;
$ttl_file = ABSPATH . 'private/cache/page-ttl.txt';
if ( is_file( $ttl_file ) ) {
	$ttl = max( 60, (int) file_get_contents( $ttl_file ) );
}
$key  = sha1( ( $_SERVER['HTTP_HOST'] ?? '' ) . $path );
$file = ABSPATH . 'private/cache/pages/' . $key . '.html';
if ( is_file( $file ) && ( time() - (int) filemtime( $file ) ) < $ttl ) {
	$hits = ABSPATH . 'private/cache/page-hits.txt';
	$count = is_file( $hits ) ? (int) file_get_contents( $hits ) : 0;
	file_put_contents( $hits, (string) ( $count + 1 ), LOCK_EX );
	header( 'X-Slimword-Cache: hit' );
	readfile( $file );
	exit;
}
