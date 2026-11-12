<?php
/**
 * Plugin Name: Slim WordPress Tasks
 * Description: Runs due tasks at the end of a normal visit. No wp-cron.php loopback.
 */

/**
 * Visit tasks are on until an admin turns them off.
 *
 * @return bool
 */
function slimword_visit_tasks_enabled() {
	if ( ! function_exists( 'get_option' ) ) {
		return true;
	}
	return '0' !== (string) get_option( 'slimword_visit_tasks', '1' );
}

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', slimword_visit_tasks_enabled() );
}

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Tasks' ),
			__( 'Tasks' ),
			'manage_options',
			'slimword-tasks',
			'slimword_render_tasks_page'
		);
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'slimword_tasks',
			'slimword_visit_tasks',
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
 * Settings screen for visit tasks.
 */
function slimword_render_tasks_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tasks' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_tasks' ); ?>
			<label>
				<input type="hidden" name="slimword_visit_tasks" value="0">
				<input type="checkbox" name="slimword_visit_tasks" value="1" <?php checked( slimword_visit_tasks_enabled() ); ?>>
				<?php esc_html_e( 'Run scheduled tasks at the end of a visit' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'This is on by default. Turn it off to use WordPress cron again, including wp-cron.php.' ); ?>
			</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_action( 'shutdown', 'slimword_run_due_tasks', 0 );

/**
 * Run scheduled hooks that are already due.
 */
function slimword_run_due_tasks() {
	if ( ! slimword_visit_tasks_enabled() ) {
		return;
	}
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$gmt_time = microtime( true );
	$lock     = get_transient( 'doing_cron' );
	if ( $lock && ( (float) $lock + WP_CRON_LOCK_TIMEOUT ) > $gmt_time ) {
		return;
	}

	$crons = wp_get_ready_cron_jobs();
	if ( empty( $crons ) ) {
		return;
	}

	$doing = sprintf( '%.22F', $gmt_time );
	set_transient( 'doing_cron', $doing );

	foreach ( $crons as $timestamp => $cronhooks ) {
		if ( $timestamp > $gmt_time ) {
			break;
		}
		foreach ( $cronhooks as $hook => $keys ) {
			foreach ( $keys as $event ) {
				if ( ! empty( $event['schedule'] ) ) {
					wp_reschedule_event( $timestamp, $event['schedule'], $hook, $event['args'], true );
				}
				wp_unschedule_event( $timestamp, $hook, $event['args'], true );
				do_action_ref_array( $hook, $event['args'] );
				if ( (string) get_transient( 'doing_cron' ) !== $doing ) {
					return;
				}
			}
		}
	}

	if ( (string) get_transient( 'doing_cron' ) === $doing ) {
		delete_transient( 'doing_cron' );
	}
}
