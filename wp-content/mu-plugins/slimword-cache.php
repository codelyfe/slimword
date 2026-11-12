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
