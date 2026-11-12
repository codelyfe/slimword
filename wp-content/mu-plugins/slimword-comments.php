<?php
/**
 * Plugin Name: Slim WordPress Comments
 * Description: Settings → Comments turns comments and pingbacks off for the whole site.
 */

/**
 * Comments stay available until an admin turns them off.
 *
 * @return bool
 */
function slimword_comments_disabled() {
	return '1' === (string) get_option( 'slimword_disable_comments', '0' );
}

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Slim comments' ),
			__( 'Slim comments' ),
			'manage_options',
			'slimword-comments',
			'slimword_render_comments_page'
		);
	},
	99
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'slimword_comments',
			'slimword_disable_comments',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
				'default'           => '0',
			)
		);
	}
);

/**
 * Settings screen for comments.
 */
function slimword_render_comments_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Comments' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'slimword_comments' ); ?>
			<label>
				<input type="hidden" name="slimword_disable_comments" value="0">
				<input type="checkbox" name="slimword_disable_comments" value="1" <?php checked( slimword_comments_disabled() ); ?>>
				<?php esc_html_e( 'Disable comments' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Turn this on to close comments and pingbacks on every post and page, hide them in the admin, and refuse new ones.' ); ?>
			</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_filter( 'comments_open', 'slimword_comments_are_closed', 20, 2 );
add_filter( 'pings_open', 'slimword_comments_are_closed', 20, 2 );
add_filter( 'comments_array', 'slimword_hide_existing_comments', 20 );
add_filter( 'feed_links_show_comments_feed', 'slimword_comments_feed' );

/**
 * @param bool $open Whether comments or pings are open.
 * @return bool
 */
function slimword_comments_are_closed( $open ) {
	if ( slimword_comments_disabled() ) {
		return false;
	}
	return $open;
}

/**
 * @param array $comments Comments for the current post.
 * @return array
 */
function slimword_hide_existing_comments( $comments ) {
	if ( slimword_comments_disabled() ) {
		return array();
	}
	return $comments;
}

/**
 * @param bool $show Whether to advertise the comments feed.
 * @return bool
 */
function slimword_comments_feed( $show ) {
	if ( slimword_comments_disabled() ) {
		return false;
	}
	return $show;
}

add_action(
	'init',
	static function () {
		if ( ! slimword_comments_disabled() ) {
			return;
		}
		foreach ( get_post_types() as $type ) {
			if ( post_type_supports( $type, 'comments' ) ) {
				remove_post_type_support( $type, 'comments' );
				remove_post_type_support( $type, 'trackbacks' );
			}
		}
	},
	100
);

add_action(
	'admin_menu',
	static function () {
		if ( ! slimword_comments_disabled() ) {
			return;
		}
		remove_menu_page( 'edit-comments.php' );
	},
	99
);

add_action(
	'admin_init',
	static function () {
		if ( ! slimword_comments_disabled() ) {
			return;
		}
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow || 'comment.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);

add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( slimword_comments_disabled() ) {
			$bar->remove_node( 'comments' );
		}
	},
	99
);

add_action(
	'wp_dashboard_setup',
	static function () {
		if ( slimword_comments_disabled() ) {
			remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		}
	}
);

add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( ! slimword_comments_disabled() ) {
			return $result;
		}
		$route = $request->get_route();
		if ( is_string( $route ) && false !== strpos( $route, '/wp/v2/comments' ) ) {
			return new WP_Error( 'slimword_comments_disabled', __( 'Comments are disabled.' ), array( 'status' => 403 ) );
		}
		return $result;
	},
	10,
	3
);

add_filter(
	'preprocess_comment',
	static function ( $comment ) {
		if ( slimword_comments_disabled() ) {
			wp_die( esc_html__( 'Comments are disabled.' ), 403 );
		}
		return $comment;
	}
);
