<?php
/**
 * Plugin Name: Slim WordPress Protection
 * Description: One switch for the usual scans and login guesses.
 */

add_action( 'send_headers', 'slimword_response_headers' );

/**
 * Tell the browser not to use the camera, microphone, or location.
 * On HTTPS, keep later visits on HTTPS.
 */
function slimword_response_headers() {
	if ( headers_sent() ) {
		return;
	}
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Protection' ),
			__( 'Protection' ),
			'manage_options',
			'slimword-protect',
			'slimword_render_protect_page'
		);
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'slimword_protect',
			'slimword_protect',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
				'default'           => '1',
			)
		);
	}
);

/**
 * Protection is on unless an admin turns it off.
 *
 * @return bool
 */
function slimword_protect_enabled() {
	return '0' !== get_option( 'slimword_protect', '1' );
}

add_action(
	'init',
	static function () {
		if ( ! slimword_protect_enabled() ) {
			return;
		}

		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		foreach ( array( 'wp-config.php', '/.env', '../', '..\\' ) as $needle ) {
			if ( false !== stripos( $uri, $needle ) ) {
				status_header( 403 );
				exit;
			}
		}
	},
	1
);

add_filter(
	'request',
	static function ( $query_vars ) {
		if ( slimword_protect_enabled() && isset( $query_vars['author'] ) && ! is_user_logged_in() ) {
			unset( $query_vars['author'] );
			$query_vars['error'] = '404';
		}
		return $query_vars;
	}
);

add_filter(
	'wp_is_application_passwords_available',
	static function ( $available ) {
		return slimword_protect_enabled() ? false : $available;
	}
);

add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( ! slimword_protect_enabled() || is_user_logged_in() ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			return new WP_Error( 'slimword_users', __( 'Sorry, you are not allowed to do that.' ), array( 'status' => 401 ) );
		}
		return $result;
	},
	10,
	3
);

add_action( 'login_form', 'slimword_login_fields' );

add_filter(
	'authenticate',
	static function ( $user, $username ) {
		if ( ! slimword_protect_enabled() || '' === (string) $username ) {
			return $user;
		}
		if ( ! empty( $_POST['company_url'] ) ) {
			return new WP_Error( 'slimword_honeypot', __( 'The login details are not correct.' ) );
		}
		$token  = isset( $_POST['slimword_check'] ) ? (string) wp_unslash( $_POST['slimword_check'] ) : '';
		$answer = isset( $_POST['slimword_answer'] ) ? trim( (string) wp_unslash( $_POST['slimword_answer'] ) ) : '';
		$expect = ( '' !== $token && preg_match( '/^[A-Za-z0-9]+$/', $token ) ) ? get_transient( 'slimword_captcha_' . $token ) : false;
		if ( '' !== $token ) {
			delete_transient( 'slimword_captcha_' . $token );
		}
		if ( false === $expect || (string) $expect !== $answer ) {
			return new WP_Error( 'slimword_captcha', __( 'The check answer is not correct. Refresh the page for a new question.' ) );
		}
		return $user;
	},
	4,
	2
);

/**
 * Honeypot and a new math question on each login page load.
 */
function slimword_login_fields() {
	if ( ! slimword_protect_enabled() ) {
		return;
	}
	$left  = wp_rand( 2, 9 );
	$right = wp_rand( 2, 9 );
	$token = wp_generate_password( 16, false );
	set_transient( 'slimword_captcha_' . $token, (string) ( $left + $right ), 10 * MINUTE_IN_SECONDS );
	echo '<p style="position:absolute;left:-9999px" aria-hidden="true"><label>Company<input type="text" name="company_url" tabindex="-1" autocomplete="off" value=""></label></p>';
	echo '<p><label for="slimword_answer">' . esc_html( sprintf( __( 'What is %1$d + %2$d?' ), $left, $right ) ) . '</label><br>';
	echo '<input type="number" name="slimword_answer" id="slimword_answer" class="input" required autocomplete="off">';
	echo '<input type="hidden" name="slimword_check" value="' . esc_attr( $token ) . '"></p>';
}

add_filter(
	'authenticate',
	static function ( $user, $username ) {
		if ( ! slimword_protect_enabled() || '' === (string) $username ) {
			return $user;
		}
		$key = 'slimword_login_' . md5( slimword_client_address() );
		if ( (int) get_transient( $key ) >= 8 ) {
			return new WP_Error( 'slimword_locked', __( 'Too many attempts. Try again in a few minutes.' ) );
		}
		return $user;
	},
	5,
	2
);

add_action(
	'wp_login_failed',
	static function ( $username, $error = null ) {
		if ( ! slimword_protect_enabled() ) {
			return;
		}
		if ( $error instanceof WP_Error && 'slimword_captcha' === $error->get_error_code() ) {
			return;
		}
		$key   = 'slimword_login_' . md5( slimword_client_address() );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
	},
	10,
	2
);

add_filter(
	'login_errors',
	static function ( $error ) {
		if ( ! slimword_protect_enabled() ) {
			return $error;
		}
		if ( false !== strpos( $error, 'Too many attempts' ) || false !== strpos( $error, 'check answer' ) ) {
			return $error;
		}
		return __( 'The login details are not correct.' );
	}
);

/**
 * Settings screen for the one protection switch.
 */
function slimword_render_protect_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Protection' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_protect' ); ?>
			<label>
				<input type="hidden" name="slimword_protect" value="0">
				<input type="checkbox" name="slimword_protect" value="1" <?php checked( slimword_protect_enabled() ); ?>>
				<?php esc_html_e( 'Block common scans and login guesses' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Hides user names, stops repeated login tries, asks a new math question on each login, ignores bots that fill a hidden field, turns off the file editor and application passwords, and refuses requests for the config file. It does not scan every visitor.' ); ?>
			</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Address used only to count failed logins.
 *
 * @return string
 */
function slimword_client_address() {
	$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return preg_match( '/^[0-9a-f:.]+$/i', $address ) ? $address : 'unknown';
}
