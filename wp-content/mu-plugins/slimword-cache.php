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
			<?php submit_button( __( 'Save' ), 'secondary', 'submit', false ); ?>
		</form>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Address' ); ?></th><th><?php esc_html_e( 'Saved' ); ?></th></tr></thead><tbody>
		<?php if ( ! $saved ) : ?><tr><td colspan="2"><?php esc_html_e( 'No pages saved.' ); ?></td></tr><?php endif; ?>
		<?php foreach ( array_slice( $saved, 0, 50 ) as $url_file ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) file_get_contents( $url_file ) ); ?></code></td>
				<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) filemtime( $url_file ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<h2><?php esc_html_e( 'Database' ); ?></h2>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_export_db' ), 'slimword_export_db' ) ); ?>"><?php esc_html_e( 'Download database' ); ?></a></p>
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
