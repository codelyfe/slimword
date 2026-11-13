<?php
/**
 * Plugin Name: Slim WordPress Extras
 * Description: Heartbeat, maintenance, error log, reading switch, and the accessibility script refresh.
 */

function slimword_reading_enabled() {
	return '0' !== (string) get_option( 'slimword_reading_on', '1' );
}

add_filter(
	'heartbeat_settings',
	static function ( $settings ) {
		$seconds = (int) get_option( 'slimword_heartbeat', 60 );
		if ( $seconds >= 15 ) {
			$settings['interval'] = $seconds;
		}
		return $settings;
	}
);

add_action(
	'template_redirect',
	static function () {
		if ( '1' !== (string) get_option( 'slimword_maintenance', '0' ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		status_header( 503 );
		header( 'Retry-After: 3600' );
		wp_die( esc_html__( 'This site is briefly closed. Please come back soon.' ), esc_html__( 'Closed' ), array( 'response' => 503 ) );
	}
);

add_action(
	'shutdown',
	static function () {
		$error = error_get_last();
		if ( ! is_array( $error ) || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			return;
		}
		slimword_error_note( $error['message'] . ' ' . $error['file'] . ':' . $error['line'] );
	}
);

add_action(
	'action_scheduler_failed_execution',
	static function ( $action_id, $error ) {
		$message = $error instanceof Exception ? $error->getMessage() : 'failed task';
		slimword_error_note( 'Task ' . $action_id . ': ' . $message );
	},
	10,
	2
);

/**
 * @param string $line Log line.
 */
function slimword_error_note( $line ) {
	$log   = get_option( 'slimword_error_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 'time' => time(), 'line' => substr( $line, 0, 300 ) );
	update_option( 'slimword_error_log', array_slice( $log, -30 ), false );
}

add_action(
	'wp_body_open',
	static function () {
		echo '<a class="skip-link screen-reader-text" href="#content" style="position:absolute;left:-999px">' . esc_html__( 'Skip to content' ) . '</a>';
	},
	1
);

add_action(
	'wp_head',
	static function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$prefs = get_user_meta( get_current_user_id(), 'a11y_widget_prefs_v4', true );
		if ( ! is_string( $prefs ) || $prefs === '' ) {
			return;
		}
		echo '<script>try{localStorage.setItem("a11y_widget_prefs_v4",' . wp_json_encode( $prefs ) . ')}catch(e){}</script>';
	},
	1
);

add_action(
	'wp_ajax_slimword_save_a11y',
	static function () {
		check_ajax_referer( 'slimword_a11y' );
		$prefs = isset( $_POST['prefs'] ) ? (string) wp_unslash( $_POST['prefs'] ) : '';
		if ( strlen( $prefs ) > 4000 ) {
			wp_die( '0' );
		}
		update_user_meta( get_current_user_id(), 'a11y_widget_prefs_v4', $prefs );
		wp_die( '1' );
	}
);

add_action(
	'wp_footer',
	static function () {
		if ( ! is_user_logged_in() || ! slimword_reading_enabled() ) {
			return;
		}
		$nonce = wp_create_nonce( 'slimword_a11y' );
		echo '<script>document.addEventListener("click",function(){setTimeout(function(){try{var v=localStorage.getItem("a11y_widget_prefs_v4")||"";if(v&&v!==window.slimwordA11ySent){window.slimwordA11ySent=v;var b=new FormData();b.append("action","slimword_save_a11y");b.append("_ajax_nonce",' . wp_json_encode( $nonce ) . ');b.append("prefs",v);fetch(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',{method:"POST",body:b,credentials:"same-origin"})}}catch(e){}},400)},true);</script>';
	},
	99998
);

add_action(
	'admin_menu',
	static function () {
		add_options_page( __( 'Slim log' ), __( 'Slim log' ), 'manage_options', 'slimword-log', 'slimword_render_log_page' );
	},
	99
);

add_action(
	'admin_init',
	static function () {
		register_setting( 'slimword_reading', 'slimword_reading_on', array( 'type' => 'string', 'sanitize_callback' => static function ( $value ) { return '1' === (string) $value ? '1' : '0'; } ) );
		register_setting( 'slimword_reading', 'slimword_reading_corner', array( 'type' => 'string', 'sanitize_callback' => static function ( $value ) { return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'left'; } ) );
		register_setting(
			'slimword_reading',
			'slimword_a11y_tools',
			array(
				'type'              => 'array',
				'sanitize_callback' => static function ( $value ) {
					$allowed = array( 'a11y-scale', 'a11y-dark', 'a11y-hc', 'a11y-spacing', 'a11y-dys', 'a11y-ul', 'a11y-focus', 'a11y-hideimg', 'a11y-mutevid', 'a11y-gray', 'a11y-anim', 'a11y-mask' );
					$value   = is_array( $value ) ? $value : array();
					return array_values( array_intersect( $allowed, $value ) );
				},
			)
		);
		register_setting( 'slimword_site', 'slimword_heartbeat', array( 'type' => 'integer', 'sanitize_callback' => 'absint' ) );
		register_setting( 'slimword_site', 'slimword_maintenance', array( 'type' => 'string', 'sanitize_callback' => static function ( $value ) { return '1' === (string) $value ? '1' : '0'; } ) );
	}
);

add_action(
	'admin_post_slimword_refresh_reading',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
		}
		check_admin_referer( 'slimword_refresh_reading' );
		$response = wp_remote_get( 'https://raw.githubusercontent.com/codelyfe/A11y-Widget-Place-Anywhere/main/a11y-widget.min.js', array( 'timeout' => 20 ) );
		$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) < 1000 || false === strpos( $body, 'a11y' ) ) {
			wp_die( esc_html__( 'The accessibility script could not be downloaded.' ) );
		}
		file_put_contents( __DIR__ . '/slimword-reading/a11y-widget.min.js', $body );
		wp_safe_redirect( admin_url( 'options-general.php?page=slimword-log&reading=1' ) );
		exit;
	}
);

/**
 * Settings for the panel, heartbeat, maintenance, and the recent log.
 */
function slimword_render_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	$errors = get_option( 'slimword_error_log', array() );
	$ajax   = get_option( 'slimword_ajax_log', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Slim log' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_reading' ); ?>
			<label><input type="hidden" name="slimword_reading_on" value="0"><input type="checkbox" name="slimword_reading_on" value="1" <?php checked( slimword_reading_enabled() ); ?>> <?php esc_html_e( 'Accessibility panel' ); ?></label>
			<p><label><?php esc_html_e( 'Corner' ); ?>
				<select name="slimword_reading_corner">
					<option value="left" <?php selected( get_option( 'slimword_reading_corner', 'left' ), 'left' ); ?>><?php esc_html_e( 'Left' ); ?></option>
					<option value="right" <?php selected( get_option( 'slimword_reading_corner', 'left' ), 'right' ); ?>><?php esc_html_e( 'Right' ); ?></option>
				</select>
			</label></p>
			<?php
			$tools = get_option( 'slimword_a11y_tools', array() );
			$tools = is_array( $tools ) && $tools ? $tools : array( 'a11y-scale', 'a11y-dark', 'a11y-hc', 'a11y-spacing', 'a11y-dys', 'a11y-ul', 'a11y-focus', 'a11y-hideimg', 'a11y-mutevid', 'a11y-gray', 'a11y-anim', 'a11y-mask' );
			$labels = array(
				'a11y-scale' => __( 'Text size' ),
				'a11y-dark' => __( 'Dark mode' ),
				'a11y-hc' => __( 'High contrast' ),
				'a11y-spacing' => __( 'Text spacing' ),
				'a11y-dys' => __( 'Dyslexia-friendly' ),
				'a11y-ul' => __( 'Underline links' ),
				'a11y-focus' => __( 'Focus' ),
				'a11y-hideimg' => __( 'Hide images' ),
				'a11y-mutevid' => __( 'Mute videos' ),
				'a11y-gray' => __( 'Grayscale' ),
				'a11y-anim' => __( 'Pause animations' ),
				'a11y-mask' => __( 'Reading mask' ),
			);
			foreach ( $labels as $id => $label ) {
				echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="slimword_a11y_tools[]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $tools, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			?>
			<?php submit_button( __( 'Save panel' ) ); ?>
		</form>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_site' ); ?>
			<p><label><?php esc_html_e( 'Admin heartbeat seconds' ); ?> <input type="number" min="15" name="slimword_heartbeat" value="<?php echo esc_attr( (string) get_option( 'slimword_heartbeat', 60 ) ); ?>"></label></p>
			<label><input type="hidden" name="slimword_maintenance" value="0"><input type="checkbox" name="slimword_maintenance" value="1" <?php checked( '1' === (string) get_option( 'slimword_maintenance', '0' ) ); ?>> <?php esc_html_e( 'Maintenance page for visitors' ); ?></label>
			<?php submit_button( __( 'Save site' ) ); ?>
		</form>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slimword_refresh_reading' ), 'slimword_refresh_reading' ) ); ?>"><?php esc_html_e( 'Download the accessibility script' ); ?></a></p>
		<h2><?php esc_html_e( 'PHP errors' ); ?></h2>
		<ul>
		<?php foreach ( array_reverse( is_array( $errors ) ? $errors : array() ) as $row ) : ?>
			<li><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) . ' — ' . $row['line'] ); ?></li>
		<?php endforeach; ?>
		</ul>
		<h2><?php esc_html_e( 'Ajax' ); ?></h2>
		<ul>
		<?php foreach ( array_reverse( is_array( $ajax ) ? $ajax : array() ) as $row ) : ?>
			<li><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) . ' — ' . $row['action'] . ' (' . $row['path'] . ')' ); ?></li>
		<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
