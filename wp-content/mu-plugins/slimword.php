<?php
/**
 * Plugin Name: Slim WordPress
 * Description: Drops unused WordPress extras. Plugins and themes keep the normal APIs.
 */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		remove_filter( 'the_content', 'capital_P_dangit', 11 );
		remove_filter( 'the_title', 'capital_P_dangit', 11 );
		remove_filter( 'comment_text', 'capital_P_dangit', 31 );

		add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
		} );

		if ( ! is_admin() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}
);

add_action(
	'wp_default_scripts',
	static function ( $scripts ) {
		if ( empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$scripts->registered['jquery']->deps = array_values(
			array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) )
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		wp_deregister_script( 'wp-embed' );
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	},
	100
);

add_filter(
	'wp_resource_hints',
	static function ( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}
		return array_values(
			array_filter(
				$urls,
				static function ( $url ) {
					$url = is_array( $url ) ? ( $url['href'] ?? '' ) : (string) $url;
					return false === strpos( $url, 'w.org' );
				}
			)
		);
	},
	10,
	2
);

add_filter(
	'pre_option_default_ping_status',
	static function () {
		return 'closed';
	}
);
add_filter(
	'pre_option_default_pingback_flag',
	static function () {
		return '';
	}
);

add_action(
	'pre_ping',
	static function ( &$links ) {
		$home = home_url();
		foreach ( $links as $key => $link ) {
			if ( 0 === strpos( (string) $link, $home ) ) {
				unset( $links[ $key ] );
			}
		}
	}
);

foreach ( array( 'home', 'siteurl' ) as $slimword_url_option ) {
	add_filter(
		'option_' . $slimword_url_option,
		static function ( $url ) {
			if ( is_ssl() && is_string( $url ) && 0 === strpos( $url, 'http://' ) ) {
				return 'https://' . substr( $url, 7 );
			}
			return $url;
		}
	);
}

add_action(
	'init',
	static function () {
		remove_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps', 1 );
		wp_clear_scheduled_hook( 'wp_site_health_scheduled_check' );
	}
);

add_filter(
	'user_has_cap',
	static function ( $allcaps ) {
		$allcaps['view_site_health_checks'] = false;
		return $allcaps;
	},
	100
);

add_action(
	'admin_menu',
	static function () {
		remove_submenu_page( 'tools.php', 'site-health.php' );
	},
	999
);

add_action(
	'admin_init',
	static function () {
		global $pagenow;
		if ( in_array( $pagenow, array( 'site-health.php', 'site-health-info.php' ), true ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	},
	0
);

add_action(
	'wp_dashboard_setup',
	static function () {
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	}
);

add_action(
	'admin_init',
	static function () {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		add_action( 'welcome_panel', 'slimword_welcome_panel' );
	}
);

add_action(
	'admin_head-index.php',
	static function () {
		echo '<style>
			.slimword-welcome .welcome-panel-column{display:block}
			.slimword-welcome .welcome-panel-column-content{max-width:none}
			.slimword-welcome .welcome-panel-column-content p,
			.slimword-welcome .welcome-panel-column-content code{overflow-wrap:anywhere}
		</style>';
	}
);

/**
 * Dashboard welcome for this SQLite build.
 */
function slimword_welcome_panel() {
	$version = explode( '-', wp_get_wp_version() )[0];
	$https   = is_ssl()
		? __( 'HTTPS is on for this request.' )
		: __( 'HTTPS stays off until this server answers on port 443.' );
	$db      = defined( 'DB_PATH' ) ? DB_PATH : '';
	?>
	<div class="welcome-panel-content slimword-welcome">
		<div class="welcome-panel-header-wrap">
			<div class="welcome-panel-header">
				<div class="welcome-panel-header-image" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 280" preserveAspectRatio="none">
						<rect width="1200" height="280" fill="#1a1d21"/>
						<circle cx="1080" cy="40" r="160" fill="#2a2f36"/>
					</svg>
				</div>
				<h2><?php esc_html_e( 'Slim WordPress' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: WordPress version number. */
						esc_html__( 'WordPress %s on one SQLite file. Any plugin or theme still installs the normal way.' ),
						esc_html( $version )
					);
					?>
				</p>
			</div>
		</div>
		<div class="welcome-panel-column-container">
			<div class="welcome-panel-column">
				<div class="welcome-panel-column-content">
					<h3><?php esc_html_e( 'Database' ); ?></h3>
					<p><?php esc_html_e( 'No MySQL server. Posts, users, and settings live in the SQLite file for this folder.' ); ?></p>
					<?php if ( $db ) : ?>
						<p><code><?php echo esc_html( $db ); ?></code></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="welcome-panel-column">
				<div class="welcome-panel-column-content">
					<h3><?php esc_html_e( 'Plugins and themes' ); ?></h3>
					<p><?php esc_html_e( 'The plugin and theme directories are unchanged. Install, update, and switch them from the admin screens.' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php' ) ); ?>"><?php esc_html_e( 'Add a plugin' ); ?></a>
					<br>
					<a href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>"><?php esc_html_e( 'Choose a theme' ); ?></a>
				</div>
			</div>
			<div class="welcome-panel-column">
				<div class="welcome-panel-column-content">
					<h3><?php esc_html_e( 'HTTPS' ); ?></h3>
					<p><?php echo esc_html( $https ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Site address' ); ?></a>
				</div>
			</div>
		</div>
	</div>
	<?php
}

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Salts' ),
			__( 'Salts' ),
			'manage_options',
			'slimword-salts',
			'slimword_render_salts_page'
		);
	}
);

add_filter(
	'login_message',
	static function ( $message ) {
		if ( empty( $_COOKIE['slimword_salts_rotated'] ) ) {
			return $message;
		}
		setcookie( 'slimword_salts_rotated', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		return '<p class="message">' . esc_html__( 'Salts were replaced. Log in again.' ) . '</p>' . $message;
	}
);

/**
 * Settings screen that replaces the eight keys in wp-config.php.
 */
function slimword_render_salts_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}

	$notice = '';
	$error  = '';

	if ( isset( $_POST['slimword_rotate_salts'] ) ) {
		check_admin_referer( 'slimword_rotate_salts' );
		$result = slimword_rotate_salts();
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			setcookie( 'slimword_salts_rotated', '1', time() + 2 * MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( wp_login_url() );
			exit;
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Salts' ); ?></h1>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Replace the eight secret keys in wp-config.php. Everyone will have to log in again.' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'slimword_rotate_salts' ); ?>
			<?php submit_button( __( 'Get new salts' ), 'primary', 'slimword_rotate_salts' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Fetch salts from WordPress.org and write them into wp-config.php.
 *
 * @return true|WP_Error
 */
function slimword_rotate_salts() {
	$names = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	$response = wp_remote_get(
		'https://api.wordpress.org/secret-key/1.1/salt/',
		array( 'timeout' => 15 )
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'slimword_salts_http', __( 'WordPress.org did not return new salts.' ) );
	}

	$body  = wp_remote_retrieve_body( $response );
	$found = array();
	foreach ( $names as $name ) {
		$pattern = "/define\(\s*'{$name}'\s*,\s*'((?:\\\\'|[^'])*)'\s*\)\s*;/";
		if ( ! preg_match( $pattern, $body, $matches ) ) {
			return new WP_Error( 'slimword_salts_parse', __( 'The salt response could not be read.' ) );
		}
		$found[ $name ] = stripcslashes( $matches[1] );
	}

	$path = ABSPATH . 'wp-config.php';
	if ( ! is_readable( $path ) || ! is_writable( $path ) ) {
		return new WP_Error( 'slimword_salts_file', __( 'wp-config.php is not writable.' ) );
	}

	$config = file_get_contents( $path );
	if ( ! is_string( $config ) ) {
		return new WP_Error( 'slimword_salts_file', __( 'wp-config.php could not be read.' ) );
	}

	foreach ( $found as $name => $value ) {
		$pattern     = "/define\(\s*['\"]{$name}['\"]\s*,\s*(['\"])(?:\\\\.|(?!\\1).)*\\1\s*\)\s*;/";
		$replacement = "define( '{$name}', " . var_export( $value, true ) . ' );';
		$updated     = preg_replace( $pattern, $replacement, $config, 1, $count );
		if ( ! is_string( $updated ) || 1 !== $count ) {
			return new WP_Error( 'slimword_salts_missing', sprintf( __( '%s is not in wp-config.php.' ), $name ) );
		}
		$config = $updated;
	}

	if ( false === file_put_contents( $path, $config, LOCK_EX ) ) {
		return new WP_Error( 'slimword_salts_file', __( 'wp-config.php could not be saved.' ) );
	}

	return true;
}
