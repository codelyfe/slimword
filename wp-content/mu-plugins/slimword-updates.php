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
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'slimword_apply_release' );
		echo '<input type="hidden" name="action" value="slimword_apply_release" />';
		submit_button( __( 'Update WordPress only' ), 'secondary', 'scope_core', false );
		echo '</form>';
	} else {
		echo '<p>' . esc_html( sprintf( __( 'Slim WordPress %1$s is installed. %2$s is available.' ), SLIMWORD_VERSION, $release['version'] ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'slimword_apply_release' );
		echo '<input type="hidden" name="action" value="slimword_apply_release" />';
		submit_button( __( 'Update Slim only' ), 'secondary', 'scope_slim', false );
		echo ' ';
		submit_button( __( 'Update WordPress only' ), 'secondary', 'scope_core', false );
		echo ' ';
		submit_button( __( 'Update both' ), 'primary', 'scope_both', false );
		echo '</form>';
	}
	$log = get_option( 'slimword_update_log', array() );
	if ( is_array( $log ) && $log ) {
		echo '<h3>' . esc_html__( 'Last update' ) . '</h3><ul>';
		foreach ( array_slice( $log, 0, 8 ) as $row ) {
			$when = isset( $row['time'] ) ? wp_date( 'Y-m-d H:i', (int) $row['time'] ) : '';
			echo '<li>' . esc_html( $when . ' — ' . ( $row['line'] ?? '' ) ) . '</li>';
		}
		echo '</ul>';
	}
	$backups = glob( ABSPATH . 'private/backups/database-*.sqlite' ) ?: array();
	rsort( $backups );
	if ( $backups ) {
		echo '<h3>' . esc_html__( 'Database copies' ) . '</h3><ul>';
		foreach ( array_slice( $backups, 0, 5 ) as $file ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=slimword_download_backup&file=' . rawurlencode( basename( $file ) ) ), 'slimword_download_backup' );
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( basename( $file ) ) . '</a></li>';
		}
		echo '</ul>';
	}
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
	$scope = 'both';
	if ( isset( $_POST['scope_slim'] ) ) {
		$scope = 'slim';
	} elseif ( isset( $_POST['scope_core'] ) ) {
		$scope = 'core';
	}
	$result = slimword_run_requested_update( $scope );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}
	wp_safe_redirect( admin_url( 'update-core.php?slimword-updated=1' ) );
	exit;
}

/**
 * Download and apply both updates. Core is never copied over a newer install.
 */
function slimword_run_requested_update( $scope = 'both' ) {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$before = SLIMWORD_VERSION;
	$backup = slimword_backup_database();
	slimword_update_note( 'Started ' . $scope . ' update from ' . $before . ( $backup ? '. Database copy ' . basename( $backup ) : '' ) );

	if ( 'slim' !== $scope ) {
		$core = slimword_download_zip( 'https://wordpress.org/latest.zip' );
		if ( is_wp_error( $core ) ) {
			slimword_update_note( 'WordPress download failed: ' . $core->get_error_message() );
			return $core;
		}
		$copied = slimword_copy_core_if_newer( $core );
		slimword_update_note( $copied ? 'Copied a newer WordPress.' : 'Installed WordPress was already current.' );
		if ( is_dir( $core . '/wp-content/themes' ) ) {
			slimword_copy_tree( $core . '/wp-content/themes', ABSPATH . 'wp-content/themes' );
		}
		slimword_delete_tree( dirname( $core ) );
	}

	if ( 'core' === $scope ) {
		slimword_update_note( 'Finished WordPress-only update.' );
		return true;
	}

	delete_site_transient( 'slimword_github_release' );
	$release = slimword_github_release();
	if ( ! $release ) {
		slimword_update_note( 'GitHub did not return a Slim release.' );
		return new WP_Error( 'slimword_release', __( 'GitHub did not return a Slim release.' ) );
	}
	slimword_update_note( 'Downloading Slim ' . $release['version'] . '.' );
	$slim = slimword_download_zip( $release['package'] );
	if ( is_wp_error( $slim ) ) {
		slimword_update_note( 'Slim download failed: ' . $slim->get_error_message() );
		return $slim;
	}
	$package = '0';
	$version_in_zip = $slim . '/wp-content/slimword-version.php';
	if ( is_readable( $version_in_zip ) && preg_match( "/SLIMWORD_VERSION'\\s*,\\s*'([^']+)'/", (string) file_get_contents( $version_in_zip ), $match ) ) {
		$package = $match[1];
	}
	slimword_copy_core_if_newer( $slim );
	$slim_paths = array(
		'wp-content/plugins/sqlite-database-integration',
		'wp-content/db.php',
		'wp-content/slimword-https.php',
		'wp-content/slimword-version.php',
		'readme.html',
		'README.md',
	);
	if ( version_compare( $package, $before, '>' ) ) {
		array_unshift( $slim_paths, 'wp-content/mu-plugins' );
	} else {
		slimword_update_note( 'Left mu-plugins in place because the package is not newer than ' . $before . '.' );
	}
	foreach ( $slim_paths as $path ) {
		$from = $slim . '/' . $path;
		$to   = ABSPATH . $path;
		if ( is_dir( $from ) ) {
			slimword_copy_tree( $from, $to );
		} elseif ( is_file( $from ) ) {
			wp_mkdir_p( dirname( $to ) );
			if ( ! copy( $from, $to ) ) {
				slimword_delete_tree( dirname( $slim ) );
				return new WP_Error( 'slimword_copy', sprintf( __( 'Could not copy %s.' ), $path ) );
			}
		}
	}
	slimword_delete_tree( dirname( $slim ) );

	$installed = '';
	$version_file = ABSPATH . 'wp-content/slimword-version.php';
	if ( is_readable( $version_file ) && preg_match( "/SLIMWORD_VERSION'\\s*,\\s*'([^']+)'/", (string) file_get_contents( $version_file ), $done ) ) {
		$installed = $done[1];
	}
	if ( $installed !== $release['version'] ) {
		slimword_update_note( 'The version file stayed at ' . $installed . '.' );
		return new WP_Error( 'slimword_version', __( 'The Slim version file was not updated.' ) );
	}
	slimword_update_note( 'Finished at Slim ' . $installed . '.' );
	delete_site_transient( 'slimword_github_release' );
	return true;
}

/**
 * @param string $line What happened.
 */
function slimword_update_note( $line ) {
	$log = get_option( 'slimword_update_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift( $log, array( 'time' => time(), 'line' => $line ) );
	update_option( 'slimword_update_log', array_slice( $log, 0, 12 ), false );
}

/**
 * @return string Path of the copy, or empty.
 */
function slimword_backup_database() {
	$src = ABSPATH . 'private/database.sqlite';
	if ( ! is_file( $src ) ) {
		return '';
	}
	$dir = ABSPATH . 'private/backups';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$dest = $dir . '/database-' . gmdate( 'Ymd-His' ) . '.sqlite';
	if ( ! copy( $src, $dest ) ) {
		return '';
	}
	$files = glob( $dir . '/database-*.sqlite' ) ?: array();
	rsort( $files );
	foreach ( array_slice( $files, 5 ) as $old ) {
		unlink( $old );
	}
	return $dest;
}

add_action( 'admin_post_slimword_download_backup', 'slimword_download_backup' );

/**
 * Download one database copy.
 */
function slimword_download_backup() {
	if ( ! current_user_can( 'update_core' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to update this site.' ), 403 );
	}
	check_admin_referer( 'slimword_download_backup' );
	$name = isset( $_GET['file'] ) ? basename( (string) wp_unslash( $_GET['file'] ) ) : '';
	$path = ABSPATH . 'private/backups/' . $name;
	if ( ! preg_match( '/^database-\d{8}-\d{6}\.sqlite$/', $name ) || ! is_file( $path ) ) {
		wp_die( esc_html__( 'That database copy was not found.' ), 404 );
	}
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	readfile( $path );
	exit;
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
		return false;
	}
	slimword_copy_tree( $root . '/wp-admin', ABSPATH . 'wp-admin' );
	slimword_copy_tree( $root . '/wp-includes', ABSPATH . 'wp-includes' );
	foreach ( glob( $root . '/*' ) ?: array() as $item ) {
		if ( ! is_file( $item ) || 'wp-config.php' === basename( $item ) || 'wp-config-sample.php' === basename( $item ) ) {
			continue;
		}
		copy( $item, ABSPATH . basename( $item ) );
	}
	return true;
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
