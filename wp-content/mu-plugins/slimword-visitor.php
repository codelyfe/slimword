<?php
/**
 * Plugin Name: Slim WordPress Visitor
 * Description: Cookie notice and a favicon taken from the site logo.
 */

add_action( 'wp_head', 'slimword_favicon', 2 );
add_action( 'init', 'slimword_cookie_block' );
add_action( 'template_redirect', 'slimword_cookies_page', 0 );

/**
 * Cookie notice block for the editor.
 */
function slimword_cookie_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		'slimword/cookie-notice',
		array(
			'api_version'     => 3,
			'title'           => __( 'Cookie notice' ),
			'category'        => 'widgets',
			'render_callback' => static function () {
				$GLOBALS['slimword_cookie_block'] = true;
				ob_start();
				slimword_cookie_notice();
				unset( $GLOBALS['slimword_cookie_block'] );
				return (string) ob_get_clean();
			},
		)
	);
}

add_action( 'admin_menu', 'slimword_cookie_menu', 99 );
add_action( 'admin_init', 'slimword_cookie_settings' );

/**
 * @return array<string, string>
 */
function slimword_cookie_options() {
	$saved = get_option( 'slimword_cookie', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge(
		array(
			'enabled'     => '1',
			'message'     => __( 'This site uses a few cookies. The cookies page explains each one.' ),
			'button'      => __( 'OK' ),
			'link_url'    => '',
			'link_label'  => __( 'Cookies' ),
			'position'    => 'bottom',
			'background'  => '#1d2327',
			'text'        => '#ffffff',
			'button_bg'   => '#ffffff',
			'button_text' => '#1d2327',
		),
		$saved
	);
}

/**
 * Settings → Cookies.
 */
function slimword_cookie_menu() {
	add_options_page(
		__( 'Cookies' ),
		__( 'Cookies' ),
		'manage_options',
		'slimword-cookies',
		'slimword_cookie_page'
	);
}

/**
 * Register the cookie notice settings.
 */
function slimword_cookie_settings() {
	register_setting(
		'slimword_cookies',
		'slimword_cookie',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'slimword_cookie_sanitize',
			'default'           => array(),
		)
	);
	register_setting(
		'slimword_cookies',
		'slimword_cookie_rows',
		array(
			'type'              => 'string',
			'sanitize_callback' => static function ( $value ) {
				$rows = array();
				if ( is_array( $value ) ) {
					foreach ( $value as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$name = sanitize_text_field( $row[0] ?? '' );
						if ( $name === '' ) {
							continue;
						}
						$rows[] = array( $name, sanitize_text_field( $row[1] ?? '' ), sanitize_text_field( $row[2] ?? '' ) );
					}
				}
				return wp_json_encode( $rows );
			},
		)
	);
}

/**
 * @param mixed $value Submitted settings.
 * @return array<string, string>
 */
function slimword_cookie_sanitize( $value ) {
	$value = is_array( $value ) ? $value : array();
	$color = static function ( $raw, $fallback ) {
		$hex = sanitize_hex_color( (string) $raw );
		return $hex ? $hex : $fallback;
	};
	$position = isset( $value['position'] ) ? (string) $value['position'] : 'bottom';
	if ( ! in_array( $position, array( 'bottom', 'top', 'corner' ), true ) ) {
		$position = 'bottom';
	}
	return array(
		'enabled'     => isset( $value['enabled'] ) ? '1' : '0',
		'message'     => sanitize_textarea_field( (string) ( $value['message'] ?? '' ) ),
		'button'      => sanitize_text_field( (string) ( $value['button'] ?? 'OK' ) ),
		'link_url'    => esc_url_raw( (string) ( $value['link_url'] ?? '' ) ),
		'link_label'  => sanitize_text_field( (string) ( $value['link_label'] ?? '' ) ),
		'position'    => $position,
		'background'  => $color( $value['background'] ?? '', '#1d2327' ),
		'text'        => $color( $value['text'] ?? '', '#ffffff' ),
		'button_bg'   => $color( $value['button_bg'] ?? '', '#ffffff' ),
		'button_text' => $color( $value['button_text'] ?? '', '#1d2327' ),
	);
}

/**
 * Cookie notice settings screen.
 */
function slimword_cookie_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	$opt = slimword_cookie_options();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cookies' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_cookies' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Notice' ); ?></th>
					<td><label><input type="checkbox" name="slimword_cookie[enabled]" value="1" <?php checked( $opt['enabled'], '1' ); ?>> <?php esc_html_e( 'Show the cookie notice' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="slimword-cookie-message"><?php esc_html_e( 'Message' ); ?></label></th>
					<td><textarea class="large-text" rows="4" id="slimword-cookie-message" name="slimword_cookie[message]"><?php echo esc_textarea( $opt['message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="slimword-cookie-button"><?php esc_html_e( 'Button' ); ?></label></th>
					<td><input class="regular-text" id="slimword-cookie-button" name="slimword_cookie[button]" value="<?php echo esc_attr( $opt['button'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="slimword-cookie-link"><?php esc_html_e( 'Link' ); ?></label></th>
					<td>
						<input class="regular-text" id="slimword-cookie-link" name="slimword_cookie[link_label]" value="<?php echo esc_attr( $opt['link_label'] ); ?>" placeholder="<?php esc_attr_e( 'Link text' ); ?>">
						<input class="regular-text" name="slimword_cookie[link_url]" value="<?php echo esc_attr( $opt['link_url'] ); ?>" placeholder="https://">
						<p class="description"><?php esc_html_e( 'Leave the address blank to use the cookies page, which lists every cookie this site sets.' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Placement' ); ?></th>
					<td>
						<label><input type="radio" name="slimword_cookie[position]" value="bottom" <?php checked( $opt['position'], 'bottom' ); ?>> <?php esc_html_e( 'Bar along the bottom' ); ?></label><br>
						<label><input type="radio" name="slimword_cookie[position]" value="top" <?php checked( $opt['position'], 'top' ); ?>> <?php esc_html_e( 'Bar along the top' ); ?></label><br>
						<label><input type="radio" name="slimword_cookie[position]" value="corner" <?php checked( $opt['position'], 'corner' ); ?>> <?php esc_html_e( 'Small box in the corner' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Colors' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Background' ); ?> <input type="color" name="slimword_cookie[background]" value="<?php echo esc_attr( $opt['background'] ); ?>"></label>
						<label><?php esc_html_e( 'Text' ); ?> <input type="color" name="slimword_cookie[text]" value="<?php echo esc_attr( $opt['text'] ); ?>"></label>
						<label><?php esc_html_e( 'Button' ); ?> <input type="color" name="slimword_cookie[button_bg]" value="<?php echo esc_attr( $opt['button_bg'] ); ?>"></label>
						<label><?php esc_html_e( 'Button text' ); ?> <input type="color" name="slimword_cookie[button_text]" value="<?php echo esc_attr( $opt['button_text'] ); ?>"></label>
					</td>
				</tr>
			</table>
			<h2><?php esc_html_e( 'Preview' ); ?></h2>
			<div id="slimword-cookie-preview" style="position:relative;padding:16px;background:#111;color:#fff;max-width:720px"></div>
			<h2><?php esc_html_e( 'Cookie page rows' ); ?></h2>
			<?php
			$table = json_decode( (string) get_option( 'slimword_cookie_rows', '' ), true );
			if ( ! is_array( $table ) ) {
				$table = array();
			}
			for ( $i = 0; $i < 6; $i++ ) {
				$row = isset( $table[ $i ] ) && is_array( $table[ $i ] ) ? $table[ $i ] : array( '', '', '' );
				echo '<p><input name="slimword_cookie_rows[' . $i . '][0]" value="' . esc_attr( (string) ( $row[0] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Cookie' ) . '"> ';
				echo '<input class="regular-text" name="slimword_cookie_rows[' . $i . '][1]" value="' . esc_attr( (string) ( $row[1] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Why' ) . '"> ';
				echo '<input name="slimword_cookie_rows[' . $i . '][2]" value="' . esc_attr( (string) ( $row[2] ?? '' ) ) . '" placeholder="' . esc_attr__( 'How long' ) . '"></p>';
			}
			?>
			<?php submit_button(); ?>
			<script>
			(function(){
				var box=document.getElementById('slimword-cookie-preview');
				function paint(){
					var msg=document.getElementById('slimword-cookie-message');
					var btn=document.getElementById('slimword-cookie-button');
					var bg=document.querySelector('[name="slimword_cookie[background]"]');
					var color=document.querySelector('[name="slimword_cookie[text]"]');
					var bbg=document.querySelector('[name="slimword_cookie[button_bg]"]');
					var btx=document.querySelector('[name="slimword_cookie[button_text]"]');
					if(!box||!msg) return;
					box.style.background=bg?bg.value:'#111';
					box.style.color=color?color.value:'#fff';
					box.innerHTML='<p>'+msg.value.replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];})+'</p><button type="button" style="background:'+(bbg?bbg.value:'#fff')+';color:'+(btx?btx.value:'#111')+'">'+(btn?btn.value:'OK')+'</button> <button type="button">Reject</button>';
				}
				document.querySelector('form').addEventListener('input', paint);
				paint();
			})();
			</script>
		</form>
	</div>
	<?php
}

/**
 * Small notice until the visitor accepts it.
 */
function slimword_cookie_notice() {
	if ( is_admin() && empty( $GLOBALS['slimword_cookie_block'] ) ) {
		return;
	}
	if ( empty( $GLOBALS['slimword_cookie_block'] ) && is_singular() && function_exists( 'has_block' ) && has_block( 'slimword/cookie-notice' ) ) {
		return;
	}
	$opt = slimword_cookie_options();
	if ( '1' !== $opt['enabled'] ) {
		return;
	}
	$link = $opt['link_url'] !== '' ? $opt['link_url'] : slimword_cookies_url();
	$place = 'left:16px;right:16px;bottom:16px;max-width:720px';
	if ( 'top' === $opt['position'] ) {
		$place = 'left:16px;right:16px;top:16px;max-width:720px';
	} elseif ( 'corner' === $opt['position'] ) {
		$place = 'right:16px;bottom:16px;left:auto;max-width:360px';
	}
	$style = 'position:fixed;' . $place . ';z-index:99999;display:flex;gap:12px;align-items:center;justify-content:space-between;margin:0 auto;padding:12px 16px;border-radius:4px;background:' . $opt['background'] . ';color:' . $opt['text'];
	?>
	<div id="slimword-cookie" role="dialog" aria-label="<?php esc_attr_e( 'Cookies' ); ?>" hidden style="<?php echo esc_attr( $style ); ?>">
		<p id="slimword-cookie-text" style="margin:0">
			<?php echo esc_html( $opt['message'] ); ?>
			<?php if ( $link && $opt['link_label'] !== '' ) : ?>
				<a href="<?php echo esc_url( $link ); ?>" style="color:inherit"><?php echo esc_html( $opt['link_label'] ); ?></a>
			<?php endif; ?>
		</p>
		<button type="button" id="slimword-cookie-ok" style="background:<?php echo esc_attr( $opt['button_bg'] ); ?>;color:<?php echo esc_attr( $opt['button_text'] ); ?>;border:0;padding:6px 12px;cursor:pointer"><?php echo esc_html( $opt['button'] ); ?></button>
		<button type="button" id="slimword-cookie-no" style="background:transparent;color:inherit;border:1px solid currentColor;padding:6px 12px;cursor:pointer"><?php esc_html_e( 'Reject' ); ?></button>
	</div>
	<script>
	(function(){
		if (/(?:^|; )slimword_consent=(?:accepted|rejected)(?:;|$)/.test(document.cookie)) return;
		var box = document.getElementById('slimword-cookie');
		var btn = document.getElementById('slimword-cookie-ok');
		var no = document.getElementById('slimword-cookie-no');
		if (!box || !btn) return;
		box.hidden = false;
		function choose(value){document.cookie='slimword_consent='+value+'; Path=/; Max-Age=15552000; SameSite=Lax';box.hidden=true;}
		btn.addEventListener('click', function(){choose('accepted')});
		if (no) no.addEventListener('click', function(){choose('rejected')});
	})();
	</script>
	<?php
}

/**
 * Address of the cookies explanation.
 *
 * @return string
 */
function slimword_cookies_url() {
	return home_url( '/cookies/' );
}

/**
 * Explain the cookies this site sets. A published page at this address is left alone.
 */
function slimword_cookies_page() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( ! preg_match( '#/cookies/?$#', $path ) ) {
		return;
	}
	$existing = get_page_by_path( 'cookies' );
	if ( $existing instanceof WP_Post && 'publish' === $existing->post_status ) {
		return;
	}
	status_header( 200 );
	nocache_headers();
	get_header();
	echo '<main class="slimword-cookies" style="max-width:720px;margin:40px auto;padding:0 16px">';
	echo '<h1>' . esc_html__( 'Cookies' ) . '</h1>';
	echo '<p>' . esc_html__( 'A cookie is a small note your browser keeps for this site. Slim WordPress does not use advertising or analytics cookies. The cookies below are the ones this site can set.' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Cookie' ) . '</th><th>' . esc_html__( 'Why it is set' ) . '</th><th>' . esc_html__( 'How long it lasts' ) . '</th></tr></thead><tbody>';
	$saved = json_decode( (string) get_option( 'slimword_cookie_rows', '' ), true );
	$rows  = array();
	if ( is_array( $saved ) ) {
		foreach ( $saved as $parts ) {
			if ( is_array( $parts ) && count( $parts ) >= 3 && $parts[0] !== '' ) {
				$rows[] = $parts;
			}
		}
	}
	if ( ! $rows ) {
		$rows = array(
		array( 'slimword_consent', __( 'Remembers that you accepted or rejected the cookie notice. It is set only when you click a button.' ), __( '6 months' ) ),
		array( 'wordpress_test_cookie', __( 'Checks that your browser will accept cookies. WordPress sets it on the login screen and removes it after the check.' ), __( 'Until you close the browser' ) ),
		array( 'wordpress_logged_in_*', __( 'Keeps you signed in. It is set only after a successful login.' ), __( '2 days, or 14 days if you choose Remember Me' ) ),
		array( 'wordpress_sec_*', __( 'A second login cookie that makes the first one harder to reuse from another browser.' ), __( 'The same as the login cookie' ) ),
		array( 'comment_author_*, comment_author_email_*, comment_author_url_*', __( 'Fills in your name, email, and site the next time you comment. These are set only when you post a comment.' ), __( '1 year' ) ),
		);
	}
	foreach ( $rows as $row ) {
		echo '<tr><td><code>' . esc_html( $row[0] ) . '</code></td><td>' . esc_html( $row[1] ) . '</td><td>' . esc_html( $row[2] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p>' . esc_html__( 'You can remove these cookies in your browser settings. Removing the login cookie signs you out. Removing the consent cookie shows the notice again.' ) . '</p>';
	echo '</main>';
	get_footer();
	exit;
}

/**
 * Use the logo as the browser icon when the site has no site icon.
 */
function slimword_favicon() {
	if ( get_option( 'site_icon' ) ) {
		return;
	}
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( ! $logo ) {
		return;
	}
	$url = wp_get_attachment_image_url( $logo, 'full' );
	if ( ! $url ) {
		return;
	}
	echo '<link rel="icon" href="' . esc_url( $url ) . '" />' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $url ) . '" />' . "\n";
}

