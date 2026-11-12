<?php
/**
 * Redirect to HTTPS only when this host actually accepts TLS.
 * Plain HTTP stays in place when nothing is listening on 443.
 */

if ( PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' ) {
	return;
}

/**
 * @return bool
 */
function slimword_request_is_https() {
	if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) {
		return true;
	}
	if ( isset( $_SERVER['SERVER_PORT'] ) && '443' === (string) $_SERVER['SERVER_PORT'] ) {
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) {
		return true;
	}
	if ( ! empty( $_SERVER['HTTP_FRONT_END_HTTPS'] ) && 'on' === strtolower( (string) $_SERVER['HTTP_FRONT_END_HTTPS'] ) ) {
		return true;
	}
	return false;
}

/**
 * @return string
 */
function slimword_request_host() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	$host = strtolower( trim( $host ) );
	if ( '' === $host || ! preg_match( '/^(?:\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::\d+)?$/', $host ) ) {
		return '';
	}
	return $host;
}

/**
 * @param string $host Host with optional port.
 * @return bool
 */
function slimword_port_443_accepts_tls( $host ) {
	$name = $host;
	if ( '[' === substr( $name, 0, 1 ) ) {
		$end = strpos( $name, ']' );
		$name = false === $end ? $name : substr( $name, 1, $end - 1 );
	} else {
		$name = preg_replace( '/:\d+$/', '', $name );
	}
	if ( ! is_string( $name ) || '' === $name ) {
		return false;
	}

	$context = stream_context_create(
		array(
			'ssl' => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
				'SNI_enabled'      => true,
				'peer_name'        => $name,
			),
		)
	);

	$errno  = 0;
	$errstr = '';
	$socket = @stream_socket_client(
		'ssl://' . $name . ':443',
		$errno,
		$errstr,
		0.4,
		STREAM_CLIENT_CONNECT,
		$context
	);

	if ( ! is_resource( $socket ) ) {
		return false;
	}

	fclose( $socket );
	return true;
}

/**
 * @return bool
 */
function slimword_https_is_available() {
	static $available = null;
	if ( null !== $available ) {
		return $available;
	}

	if ( slimword_request_is_https() ) {
		$available = true;
		slimword_store_https_cache( true );
		return true;
	}

	$host = slimword_request_host();
	if ( '' === $host ) {
		$available = false;
		return false;
	}

	$cache = __DIR__ . '/../private/https-available.txt';
	if ( is_readable( $cache ) ) {
		$parts = explode( ' ', trim( (string) file_get_contents( $cache ) ), 2 );
		$age   = isset( $parts[1] ) ? time() - (int) $parts[1] : PHP_INT_MAX;
		if ( $age >= 0 && $age < 600 && ( '0' === $parts[0] || '1' === $parts[0] ) ) {
			$available = '1' === $parts[0];
			return $available;
		}
	}

	$available = slimword_port_443_accepts_tls( $host );
	slimword_store_https_cache( $available );
	return $available;
}

/**
 * @param bool $available Whether TLS answered.
 */
function slimword_store_https_cache( $available ) {
	$dir = __DIR__ . '/../private';
	if ( ! is_dir( $dir ) ) {
		return;
	}
	file_put_contents( $dir . '/https-available.txt', ( $available ? '1' : '0' ) . ' ' . time(), LOCK_EX );
}

if ( slimword_https_is_available() ) {
	if ( ! defined( 'FORCE_SSL_ADMIN' ) ) {
		define( 'FORCE_SSL_ADMIN', true );
	}

	if ( ! slimword_request_is_https() ) {
		$host = slimword_request_host();
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		if ( '' !== $host && strlen( $uri ) > 0 && '/' === $uri[0] ) {
			header( 'Location: https://' . $host . $uri, true, 301 );
			exit;
		}
	}

	$_SERVER['HTTPS'] = 'on';
}
