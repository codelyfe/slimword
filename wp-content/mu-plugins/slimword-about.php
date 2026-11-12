<?php
/**
 * About page content from the Slim WordPress README on GitHub.
 */

if ( is_readable( dirname( __DIR__ ) . '/slimword-version.php' ) ) {
	require_once dirname( __DIR__ ) . '/slimword-version.php';
}

/**
 * README markdown from GitHub, or the copy in this folder.
 *
 * @return string
 */
function slimword_about_readme() {
	$cached = get_transient( 'slimword_about_readme' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$repo = defined( 'SLIMWORD_GITHUB_REPO' ) ? SLIMWORD_GITHUB_REPO : 'codelyfe/slimword';
	$response = wp_remote_get(
		'https://raw.githubusercontent.com/' . $repo . '/master/README.md',
		array(
			'timeout' => 8,
			'headers' => array( 'User-Agent' => 'Slim-WordPress' ),
		)
	);

	$body = '';
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$body = trim( wp_remote_retrieve_body( $response ) );
	}
	if ( '' === $body && is_readable( ABSPATH . 'README.md' ) ) {
		$body = trim( (string) file_get_contents( ABSPATH . 'README.md' ) );
	}
	if ( '' === $body ) {
		return '';
	}

	set_transient( 'slimword_about_readme', $body, 15 * MINUTE_IN_SECONDS );
	return $body;
}

/**
 * Turn the README into HTML.
 *
 * @param string $markdown Markdown text.
 * @return string
 */
function slimword_about_readme_html( $markdown ) {
	$lines = preg_split( "/\r\n|\n|\r/", $markdown );
	$html  = '';
	$list  = '';
	$para  = array();

	$flush_para = static function () use ( &$para, &$html ) {
		if ( ! $para ) {
			return;
		}
		$html .= '<p>' . slimword_about_inline( implode( ' ', $para ) ) . '</p>';
		$para  = array();
	};
	$flush_list = static function () use ( &$list, &$html ) {
		if ( '' === $list ) {
			return;
		}
		$html .= '<ul>' . $list . '</ul>';
		$list  = '';
	};

	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*$/', $line ) ) {
			$flush_para();
			$flush_list();
			continue;
		}
		if ( preg_match( '/^(#{1,3})\s+(.+)$/', $line, $match ) ) {
			$flush_para();
			$flush_list();
			$level = strlen( $match[1] );
			$html .= '<h' . $level . '>' . slimword_about_inline( $match[2] ) . '</h' . $level . '>';
			continue;
		}
		if ( preg_match( '/^\s*[-*]\s+(.+)$/', $line, $match ) ) {
			$flush_para();
			$list .= '<li>' . slimword_about_inline( $match[1] ) . '</li>';
			continue;
		}
		if ( preg_match( '/^\|.+\|$/', $line ) ) {
			if ( preg_match( '/^\|\s*-+/', $line ) ) {
				continue;
			}
			$flush_para();
			$flush_list();
			$cells = array_map( 'trim', explode( '|', trim( $line, '|' ) ) );
			$html .= '<p>' . slimword_about_inline( implode( ' — ', $cells ) ) . '</p>';
			continue;
		}
		$flush_list();
		$para[] = trim( $line );
	}

	$flush_para();
	$flush_list();
	return $html;
}

/**
 * Escape text and keep links, bold, and code.
 *
 * @param string $text One line of markdown.
 * @return string
 */
function slimword_about_inline( $text ) {
	$parts = preg_split( '/(`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	$html  = '';
	foreach ( $parts as $part ) {
		if ( strlen( $part ) > 1 && '`' === $part[0] && '`' === substr( $part, -1 ) ) {
			$html .= '<code>' . esc_html( substr( $part, 1, -1 ) ) . '</code>';
			continue;
		}
		$part = esc_html( $part );
		$part = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $part );
		$part = preg_replace_callback(
			'/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( $match ) {
				return '<a href="' . esc_url( $match[2] ) . '">' . $match[1] . '</a>';
			},
			$part
		);
		$html .= $part;
	}
	return $html;
}

add_action(
	'admin_notices',
	static function () {
		global $pagenow;
		if ( 'about.php' !== $pagenow ) {
			return;
		}
		$readme = slimword_about_readme();
		echo '<div class="notice" style="padding:12px 16px;border-left-color:#1a1d21;">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Slim WordPress' ) . '</h2>';
		if ( '' === $readme ) {
			echo '<p>' . esc_html__( 'The README could not be loaded.' ) . '</p>';
		} else {
			echo slimword_about_readme_html( $readme ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
);

