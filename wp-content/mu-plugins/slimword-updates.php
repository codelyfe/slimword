<?php
/**
 * Plugin Name: Slim WordPress Updates
 * Description: WordPress.org updates core. GitHub updates the Slim layer without rolling core backwards.
 */

require_once dirname( __DIR__ ) . '/slimword-version.php';

add_action(
	'init',
	static function () {
		if ( wp_next_scheduled( 'slimword_github_update_check' ) ) {
			wp_clear_scheduled_hook( 'slimword_github_update_check' );
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$checked = (int) get_site_transient( 'slimword_github_checked' );
		if ( $checked && ( time() - $checked ) < 15 * MINUTE_IN_SECONDS ) {
			return;
		}
		slimword_check_github_updates( true );
	},
	20
);

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'update_core' ) ) {
			return;
		}
		$release = get_site_transient( 'slimword_github_release' );
		if ( ! is_array( $release ) || empty( $release['version'] ) ) {
			return;
		}
		if ( version_compare( $release['version'], SLIMWORD_VERSION, '<=' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: installed Slim version, 2: GitHub version. */
				__( 'Slim WordPress %1$s can update to %2$s from GitHub.' ),
				SLIMWORD_VERSION,
				$release['version']
			)
		);
		echo ' <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_apply_release' ), 'slimword_apply_release' ) ) . '">' . esc_html__( 'Update Slim WordPress' ) . '</a>';
		echo '</p></div>';
	}
);

add_action( 'core_upgrade_preamble', 'slimword_updates_screen' );
add_action( 'admin_post_slimword_apply_release', 'slimword_apply_release' );

/**
 * Slim release row on Dashboard → Updates. WordPress core keeps its own button.
 */
function slimword_updates_screen() {
	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}
	$release = slimword_github_release();
	echo '<h2>' . esc_html__( 'Slim WordPress' ) . '</h2>';
	if ( ! is_array( $release ) || empty( $release['version'] ) ) {
		echo '<p>' . esc_html__( 'GitHub did not return a Slim release.' ) . '</p>';
		return;
	}
	if ( version_compare( $release['version'], SLIMWORD_VERSION, '<=' ) ) {
		echo '<p>' . esc_html( sprintf( __( 'You have Slim WordPress %s.' ), SLIMWORD_VERSION ) ) . '</p>';
		return;
	}
	echo '<p>' . esc_html( sprintf( __( 'Slim WordPress %1$s is installed. %2$s is available.' ), SLIMWORD_VERSION, $release['version'] ) ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'slimword_apply_release' );
	echo '<input type="hidden" name="action" value="slimword_apply_release" />';
	submit_button( sprintf( __( 'Update Slim WordPress to %s' ), $release['version'] ), 'primary', 'submit', false );
	echo '</form>';
}

/**
 * Latest GitHub release that contains a zip WordPress can install.
 *
 * The zip must contain a top-level wordpress directory.
 *
 * @return array{version:string,package:string}|null
 */
function slimword_github_release() {
	$cached = get_site_transient( 'slimword_github_release' );
	if ( is_array( $cached ) && isset( $cached['version'], $cached['package'] ) ) {
		return $cached;
	}
	if ( 'none' === $cached ) {
		return null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . SLIMWORD_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 20,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Slim-WordPress',
			),
		)
	);

	$found = null;
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$tag  = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'vV' ) : '';
		$zip  = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				$url = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
				if ( '' !== $url && substr( $url, -4 ) === '.zip' ) {
					$zip = $url;
					break;
				}
			}
		}
		if ( '' !== $tag && '' !== $zip ) {
			$found = array(
				'version' => $tag,
				'package' => $zip,
			);
		}
	}

	set_site_transient( 'slimword_github_release', $found ? $found : 'none', $found ? 12 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	return $found;
}

/**
 * Ask GitHub for a newer Slim release and store it where WordPress shows core updates.
 *
 * @param bool $force Skip the cached release.
 */
function slimword_check_github_updates( $force = false ) {
	if ( $force ) {
		delete_site_transient( 'slimword_github_release' );
	}
	slimword_github_release();
	set_site_transient( 'slimword_github_checked', time(), HOUR_IN_SECONDS );
}

/**
 * Install the newest WordPress core, then the newest Slim release.
 */
function slimword_apply_release() {
	if ( ! current_user_can( 'update_core' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to update this site.' ), 403 );
	}
	check_admin_referer( 'slimword_apply_release' );
	$result = slimword_run_requested_update();
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}
	wp_safe_redirect( admin_url( 'update-core.php?slimword-updated=1' ) );
	exit;
}

/**
 * Download and apply both updates. Core is never copied over a newer install.
 */
function slimword_run_requested_update() {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();

	$core = slimword_download_zip( 'https://wordpress.org/latest.zip' );
	if ( ! is_wp_error( $core ) ) {
		slimword_copy_core_if_newer( $core );
		if ( is_dir( $core . '/wp-content/themes' ) ) {
			slimword_copy_tree( $core . '/wp-content/themes', ABSPATH . 'wp-content/themes' );
		}
		slimword_delete_tree( dirname( $core ) );
	}

	delete_site_transient( 'slimword_github_release' );
	$release = slimword_github_release();
	if ( ! $release ) {
		return new WP_Error( 'slimword_release', __( 'GitHub did not return a Slim release.' ) );
	}
	$slim = slimword_download_zip( $release['package'] );
	if ( is_wp_error( $slim ) ) {
		return $slim;
	}
	slimword_copy_core_if_newer( $slim );
	$slim_paths = array(
		'wp-content/mu-plugins',
		'wp-content/plugins/sqlite-database-integration',
		'wp-content/db.php',
		'wp-content/slimword-https.php',
		'wp-content/slimword-version.php',
		'readme.html',
		'README.md',
	);
	foreach ( $slim_paths as $path ) {
		$from = $slim . '/' . $path;
		$to   = ABSPATH . $path;
		if ( is_dir( $from ) ) {
			slimword_copy_tree( $from, $to );
		} elseif ( is_file( $from ) ) {
			wp_mkdir_p( dirname( $to ) );
			copy( $from, $to );
		}
	}
	slimword_delete_tree( dirname( $slim ) );

	$version_file = ABSPATH . 'wp-content/slimword-version.php';
	$installed    = '';
	if ( is_readable( $version_file ) && preg_match( "/SLIMWORD_VERSION'\\s*,\\s*'([^']+)'/", (string) file_get_contents( $version_file ), $match ) ) {
		$installed = $match[1];
	}
	if ( $installed !== $release['version'] ) {
		return new WP_Error( 'slimword_version', __( 'The Slim version file was not updated.' ) );
	}
	delete_site_transient( 'slimword_github_release' );
	return true;
}

/**
 * @param string $url Zip URL.
 * @return string|WP_Error Path to the extracted wordpress directory.
 */
function slimword_download_zip( $url ) {
	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$work   = WP_CONTENT_DIR . '/upgrade/slimword-' . wp_generate_password( 8, false );
	$result = unzip_file( $tmp, $work );
	unlink( $tmp );
	if ( is_wp_error( $result ) ) {
		slimword_delete_tree( $work );
		return $result;
	}
	$root = $work . '/wordpress';
	if ( ! is_dir( $root ) ) {
		slimword_delete_tree( $work );
		return new WP_Error( 'slimword_zip', __( 'The update package was not a WordPress folder.' ) );
	}
	return $root;
}

/**
 * @param string $root Extracted wordpress directory.
 */
function slimword_copy_core_if_newer( $root ) {
	$package_wp   = '0';
	$version_file = $root . '/wp-includes/version.php';
	if ( is_readable( $version_file ) && preg_match( "/\\\$wp_version\\s*=\\s*'([^']+)'/", (string) file_get_contents( $version_file ), $match ) ) {
		$package_wp = $match[1];
	}
	if ( version_compare( $package_wp, wp_get_wp_version(), '<=' ) ) {
		return;
	}
	slimword_copy_tree( $root . '/wp-admin', ABSPATH . 'wp-admin' );
	slimword_copy_tree( $root . '/wp-includes', ABSPATH . 'wp-includes' );
	foreach ( glob( $root . '/*' ) ?: array() as $item ) {
		if ( ! is_file( $item ) || 'wp-config.php' === basename( $item ) || 'wp-config-sample.php' === basename( $item ) ) {
			continue;
		}
		copy( $item, ABSPATH . basename( $item ) );
	}
}

/**
 * @param string $from Source directory.
 * @param string $to   Destination directory.
 */
function slimword_copy_tree( $from, $to ) {
	wp_mkdir_p( $to );
	$items = scandir( $from );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$src = $from . '/' . $item;
		$dst = $to . '/' . $item;
		if ( is_dir( $src ) ) {
			slimword_copy_tree( $src, $dst );
		} else {
			copy( $src, $dst );
		}
	}
}

/**
 * @param string $path Directory to remove.
 */
function slimword_delete_tree( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$items = scandir( $path );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$target = $path . '/' . $item;
		if ( is_dir( $target ) ) {
			slimword_delete_tree( $target );
		} else {
			unlink( $target );
		}
	}
	rmdir( $path );
}
