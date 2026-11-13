<?php
/**
 * Plugin Name: Slim WordPress Cache
 * Description: Settings → Cache lists the file object cache and clears it.
 */

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Cache' ),
			__( 'Cache' ),
			'manage_options',
			'slimword-cache',
			'slimword_render_cache_page'
		);
	},
	99
);

add_action( 'admin_post_slimword_clear_cache', 'slimword_clear_cache' );

/**
 * Delete every file in the object cache.
 */
function slimword_clear_cache() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_clear_cache' );
	wp_cache_flush();
	if ( function_exists( 'slimword_page_cache_flush' ) ) {
		slimword_page_cache_flush();
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache&cleared=1' ) );
	exit;
}

/**
 * Settings screen for the file cache.
 */
function slimword_render_cache_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	$cache = isset( $GLOBALS['slimword_file_cache'] ) ? $GLOBALS['slimword_file_cache'] : null;
	$rows  = $cache ? $cache->entries() : array();
	$bytes = 0;
	foreach ( $rows as $row ) {
		$bytes += (int) $row['bytes'];
	}
	$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per    = 50;
	$slice  = array_slice( $rows, ( $page - 1 ) * $per, $per );
	$pages  = max( 1, (int) ceil( count( $rows ) / $per ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cache' ); ?></h1>
		<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'The cache was cleared.' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					__( '%1$s items, %2$s, stored in private/cache/object.' ),
					number_format_i18n( count( $rows ) ),
					size_format( $bytes )
				)
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'slimword_clear_cache' ); ?>
			<input type="hidden" name="action" value="slimword_clear_cache">
		<?php submit_button( __( 'Clear cache' ), 'delete', 'submit', false ); ?>
		</form>
		<?php
		$pages_on = ! is_file( ABSPATH . 'private/cache/page-cache.off' );
		$ttl      = is_file( ABSPATH . 'private/cache/page-ttl.txt' ) ? (int) file_get_contents( ABSPATH . 'private/cache/page-ttl.txt' ) : 600;
		$saved    = glob( ABSPATH . 'private/cache/pages/*.url' ) ?: array();
		?>
		<h2><?php esc_html_e( 'Saved pages' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'slimword_page_cache_settings' ); ?>
			<input type="hidden" name="action" value="slimword_page_cache_settings">
			<label><input type="checkbox" name="enabled" value="1" <?php checked( $pages_on ); ?>> <?php esc_html_e( 'Save public pages' ); ?></label>
			<label><?php esc_html_e( 'Minutes' ); ?> <input type="number" min="1" name="minutes" value="<?php echo esc_attr( (string) max( 1, (int) round( $ttl / 60 ) ) ); ?>"></label>
			<p><label><?php esc_html_e( 'Addresses to skip, one per line' ); ?><br><textarea class="large-text" name="skip" rows="4"><?php echo esc_textarea( is_file( ABSPATH . 'private/cache/nocache.txt' ) ? (string) file_get_contents( ABSPATH . 'private/cache/nocache.txt' ) : "/cart\n/checkout\n/my-account" ); ?></textarea></label></p>
			<?php submit_button( __( 'Save' ), 'secondary', 'submit', false ); ?>
		</form>
		<p><?php echo esc_html( sprintf( __( 'Saved pages served: %s' ), number_format_i18n( (int) ( is_file( ABSPATH . 'private/cache/page-hits.txt' ) ? file_get_contents( ABSPATH . 'private/cache/page-hits.txt' ) : 0 ) ) ) ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Address' ); ?></th><th><?php esc_html_e( 'Saved' ); ?></th><th></th></tr></thead><tbody>
		<?php if ( ! $saved ) : ?><tr><td colspan="3"><?php esc_html_e( 'No pages saved.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( array_slice( $saved, 0, 50 ) as $url_file ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) file_get_contents( $url_file ) ); ?></code></td>
				<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) filemtime( $url_file ) ) ); ?></td>
				<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_delete_page&file=' . rawurlencode( basename( $url_file, '.url' ) ) ), 'slimword_delete_page' ) ); ?>"><?php esc_html_e( 'Delete' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[ $row['group'] ] = true;
		}
		?>
		<h2><?php esc_html_e( 'Cache groups' ); ?></h2>
		<ul>
		<?php foreach ( array_keys( $groups ) as $group ) : ?>
			<li><?php echo esc_html( $group ); ?> <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_clear_group&group=' . rawurlencode( $group ) ), 'slimword_clear_group' ) ); ?>"><?php esc_html_e( 'Clear' ); ?></a></li>
		<?php endforeach; ?>
		</ul>
		<h2><?php esc_html_e( 'Database' ); ?></h2>
		<?php
		$db_path = ABSPATH . 'private/database.sqlite';
		$db_size = is_file( $db_path ) ? size_format( (int) filesize( $db_path ) ) : __( 'Missing' );
		$free    = function_exists( 'disk_free_space' ) ? @disk_free_space( ABSPATH ) : false;
		?>
		<p><?php echo esc_html( sprintf( __( 'Database file: %1$s. Free disk space: %2$s.' ), $db_size, false === $free ? __( 'Unknown' ) : size_format( (float) $free ) ) ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_export_db' ), 'slimword_export_db' ) ); ?>"><?php esc_html_e( 'Download database' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_vacuum_db' ), 'slimword_vacuum_db' ) ); ?>"><?php esc_html_e( 'Compact database' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_fix_urls' ), 'slimword_fix_urls' ) ); ?>"><?php esc_html_e( 'Copy this site’s address into the database' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'slimword_import_db' ); ?>
			<input type="hidden" name="action" value="slimword_import_db">
			<input type="file" name="database" accept=".sqlite">
			<?php submit_button( __( 'Replace database' ), 'delete', 'submit', false ); ?>
		</form>
		<table class="widefat striped" style="margin-top:16px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Group' ); ?></th>
					<th><?php esc_html_e( 'Key' ); ?></th>
					<th><?php esc_html_e( 'Size' ); ?></th>
					<th><?php esc_html_e( 'Saved' ); ?></th>
					<th><?php esc_html_e( 'Expires' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $slice ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Nothing is cached yet.' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $slice as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['group'] ); ?></td>
						<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
						<td><?php echo esc_html( size_format( (int) $row['bytes'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $row['saved'] ) ); ?></td>
						<td>
							<?php
							echo ! empty( $row['expires'] )
								? esc_html( wp_date( 'Y-m-d H:i:s', (int) $row['expires'] ) )
								: esc_html__( 'Never' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $pages > 1 ) : ?>
			<p>
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						)
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_slimword_page_cache_settings', 'slimword_page_cache_settings' );
add_action( 'admin_post_slimword_delete_page', 'slimword_delete_page' );
add_action( 'admin_post_slimword_clear_group', 'slimword_clear_group' );
add_action( 'admin_post_slimword_vacuum_db', 'slimword_vacuum_db' );
add_action( 'admin_post_slimword_fix_urls', 'slimword_fix_urls' );
add_action( 'admin_post_slimword_export_db', 'slimword_export_db' );
add_action( 'admin_post_slimword_import_db', 'slimword_import_db' );

/**
 * Save the page-cache switch and lifetime.
 */
function slimword_page_cache_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_page_cache_settings' );
	$dir = ABSPATH . 'private/cache';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$off = $dir . '/page-cache.off';
	if ( empty( $_POST['enabled'] ) ) {
		file_put_contents( $off, '1' );
	} elseif ( is_file( $off ) ) {
		unlink( $off );
	}
	$minutes = isset( $_POST['minutes'] ) ? max( 1, (int) $_POST['minutes'] ) : 10;
	file_put_contents( $dir . '/page-ttl.txt', (string) ( $minutes * 60 ) );
	$skip = isset( $_POST['skip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['skip'] ) ) : '';
	file_put_contents( $dir . '/nocache.txt', $skip );
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}

/**
 * Download the live SQLite file.
 */
function slimword_export_db() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_export_db' );
	$path = ABSPATH . 'private/database.sqlite';
	if ( ! is_file( $path ) ) {
		wp_die( esc_html__( 'The database file was not found.' ), 404 );
	}
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="database.sqlite"' );
	readfile( $path );
	exit;
}

/**
 * Replace the database after keeping a copy.
 */
function slimword_import_db() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_import_db' );
	if ( empty( $_FILES['database']['tmp_name'] ) || ! is_uploaded_file( $_FILES['database']['tmp_name'] ) ) {
		wp_die( esc_html__( 'Choose a SQLite file.' ) );
	}
	$handle = fopen( $_FILES['database']['tmp_name'], 'rb' );
	$header = $handle ? (string) fread( $handle, 16 ) : '';
	if ( $handle ) {
		fclose( $handle );
	}
	if ( strncmp( $header, "SQLite format 3\0", 16 ) !== 0 ) {
		wp_die( esc_html__( 'That file is not a SQLite database.' ) );
	}
	if ( function_exists( 'slimword_backup_database' ) ) {
		slimword_backup_database();
	}
	$dest = ABSPATH . 'private/database.sqlite';
	if ( ! move_uploaded_file( $_FILES['database']['tmp_name'], $dest ) ) {
		wp_die( esc_html__( 'The database could not be replaced.' ) );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}

/**
 * Delete one saved page.
 */
function slimword_delete_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_delete_page' );
	$file = isset( $_GET['file'] ) ? (string) wp_unslash( $_GET['file'] ) : '';
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $file ) ) {
		wp_die( esc_html__( 'That saved page was not found.' ), 404 );
	}
	$dir = ABSPATH . 'private/cache/pages/';
	foreach ( array( '.html', '.url' ) as $suffix ) {
		if ( is_file( $dir . $file . $suffix ) ) {
			unlink( $dir . $file . $suffix );
		}
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}

/**
 * Clear one object-cache group.
 */
function slimword_clear_group() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_clear_group' );
	$group = isset( $_GET['group'] ) ? sanitize_key( wp_unslash( $_GET['group'] ) ) : '';
	if ( isset( $GLOBALS['slimword_file_cache'] ) && $group !== '' ) {
		$GLOBALS['slimword_file_cache']->flush_group( $group );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}

/**
 * Rebuild the SQLite file so it uses less space.
 */
function slimword_vacuum_db() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_vacuum_db' );
	global $wpdb;
	$wpdb->query( 'VACUUM' );
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}

/**
 * Write the address of this request into the site URL fields.
 */
function slimword_fix_urls() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	check_admin_referer( 'slimword_fix_urls' );
	$script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	$base   = (string) preg_replace( '#/wp-admin/.*$#', '', $script );
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	$url    = ( is_ssl() ? 'https://' : 'http://' ) . $host . $base;
	update_option( 'siteurl', $url );
	update_option( 'home', $url );
	wp_safe_redirect( admin_url( 'options-general.php?page=slimword-cache' ) );
	exit;
}
