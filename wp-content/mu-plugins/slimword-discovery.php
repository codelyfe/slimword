<?php
/**
 * Plugin Name: Slim WordPress Discovery
 * Description: Universal SEO, answer-engine, and generative-engine tags for any site.
 */

add_action( 'wp_head', 'slimword_discovery_head', 5 );
add_filter( 'robots_txt', 'slimword_discovery_robots', 99, 2 );
add_filter( 'wp_robots', 'slimword_discovery_wp_robots' );
add_filter( 'wp_sitemaps_add_provider', 'slimword_discovery_hide_users', 10, 2 );
add_action( 'template_redirect', 'slimword_discovery_files', 0 );
add_filter( 'wp_get_attachment_image_attributes', 'slimword_discovery_alt', 20, 1 );

/**
 * Share tags, description, and schema.
 */
function slimword_discovery_head() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$title = wp_get_document_title();
	$desc  = slimword_discovery_description();
	$url   = slimword_discovery_url();
	$image = slimword_discovery_image();
	$type  = is_singular( 'post' ) ? 'article' : 'website';

	echo '<link rel="describedby" type="text/plain" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";
	if ( $desc !== '' ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}
	echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	if ( $desc !== '' ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}
	if ( $image !== '' ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	}
	echo '<meta name="twitter:card" content="' . esc_attr( $image !== '' ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	if ( $desc !== '' ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}
	if ( $image !== '' ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
	}

	$graph = array(
		array(
			'@type'       => 'Organization',
			'@id'         => home_url( '/#organization' ),
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
		),
	);
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo ) {
			$graph[0]['logo'] = $logo;
		}
	}
	$email = get_option( 'admin_email' );
	if ( is_email( $email ) ) {
		$graph[0]['email'] = $email;
	}
	if ( is_front_page() ) {
		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => home_url( '/#organization' ) ),
			'inLanguage'      => get_bloginfo( 'language' ) ? get_bloginfo( 'language' ) : 'en',
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$graph[] = array(
				'@type'         => is_singular( 'post' ) ? 'Article' : 'WebPage',
				'@id'           => get_permalink( $post ) . '#content',
				'url'           => get_permalink( $post ),
				'name'          => get_the_title( $post ),
				'headline'      => get_the_title( $post ),
				'description'   => $desc,
				'datePublished' => get_post_time( 'c', true, $post ),
				'dateModified'  => get_post_modified_time( 'c', true, $post ),
				'isPartOf'      => array( '@id' => home_url( '/#website' ) ),
				'publisher'     => array( '@id' => home_url( '/#organization' ) ),
			);
		}
	}

	echo '<script type="application/ld+json">' . wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . '</script>' . "\n";
}

/**
 * @return string
 */
function slimword_discovery_description() {
	$post = get_queried_object();
	$text = '';
	if ( $post instanceof WP_Post ) {
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
	} elseif ( is_front_page() ) {
		$text = get_bloginfo( 'description' );
	}
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
	$text = preg_replace( '/\s+/', ' ', $text );
	if ( ! is_string( $text ) ) {
		return '';
	}
	if ( strlen( $text ) > 160 ) {
		$text = substr( $text, 0, 157 ) . '...';
	}
	return trim( $text );
}

/**
 * @return string
 */
function slimword_discovery_url() {
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	$link = get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) );
	return is_string( $link ) ? $link : home_url( '/' );
}

/**
 * @return string
 */
function slimword_discovery_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$image = wp_get_attachment_image_url( (int) get_post_thumbnail_id(), 'full' );
		if ( $image ) {
			return $image;
		}
	}
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		$image = wp_get_attachment_image_url( $logo, 'full' );
		if ( $image ) {
			return $image;
		}
	}
	return '';
}

/**
 * @param string $output Existing robots text.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function slimword_discovery_robots( $output, $public ) {
	unset( $output );
	if ( ! $public ) {
		return "User-agent: *\nDisallow: /\n";
	}

	$llms = home_url( '/llms.txt' );
	$full = home_url( '/llms-full.txt' );
	$lines = array(
		'User-agent: *',
		'Allow: /',
		'Disallow: /wp-admin/',
		'Allow: /wp-admin/admin-ajax.php',
		'Disallow: /wp-login.php',
		'Disallow: /xmlrpc.php',
		'Disallow: /?s=',
		'Disallow: /search/',
		'Disallow: /feed/',
		'Disallow: /*/feed/',
	);
	foreach ( array( 'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot' ) as $agent ) {
		$lines[] = 'User-agent: ' . $agent;
		$lines[] = 'Allow: ' . $llms;
		$lines[] = 'Allow: ' . $full;
		$lines[] = 'Disallow: /';
	}
	foreach ( array( 'PerplexityBot', 'ChatGPT-User', 'OAI-SearchBot', 'Claude-SearchBot' ) as $agent ) {
		$lines[] = 'User-agent: ' . $agent;
		$lines[] = 'Allow: ' . $llms;
		$lines[] = 'Allow: ' . $full;
		$lines[] = 'Allow: /';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Disallow: /wp-login.php';
	}
	$lines[] = 'Sitemap: ' . home_url( '/wp-sitemap.xml' );
	return implode( "\n", $lines ) . "\n";
}

/**
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function slimword_discovery_wp_robots( $robots ) {
	if ( is_search() || is_author() ) {
		$robots['noindex'] = true;
	}
	return $robots;
}

/**
 * @param mixed  $provider Sitemap provider.
 * @param string $name     Provider name.
 * @return mixed
 */
function slimword_discovery_hide_users( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}

/**
 * Serve llms.txt, llms-full.txt, and security.txt.
 */
function slimword_discovery_files() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( preg_match( '#/(?:\.well-known/)?security\.txt/?$#', $path ) ) {
		slimword_discovery_plain( slimword_discovery_security_txt() );
	}
	if ( preg_match( '#/llms-full\.txt/?$#', $path ) ) {
		slimword_discovery_plain( slimword_discovery_llms( true ) );
	}
	if ( preg_match( '#/llms\.txt/?$#', $path ) ) {
		slimword_discovery_plain( slimword_discovery_llms( false ) );
	}
}

/**
 * @param string $body Plain text.
 */
function slimword_discovery_plain( $body ) {
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $body;
	exit;
}

/**
 * @param bool $full Include post excerpts.
 * @return string
 */
function slimword_discovery_llms( $full ) {
	$name = get_bloginfo( 'name' );
	$desc = get_bloginfo( 'description' );
	$lines = array(
		'# ' . $name,
		'>',
		'> ' . $desc,
		'',
		'## About',
		'- Site: ' . home_url( '/' ),
		'- Sitemap: ' . home_url( '/wp-sitemap.xml' ),
		'',
		'## Pages',
	);
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	foreach ( $pages as $page ) {
		$lines[] = '- ' . get_the_title( $page ) . ': ' . get_permalink( $page );
	}
	$lines[] = '';
	$lines[] = '## Posts';
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $full ? 100 : 20,
		)
	);
	foreach ( $posts as $post ) {
		$line = '- ' . get_the_title( $post ) . ': ' . get_permalink( $post );
		if ( $full ) {
			$excerpt = wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 40 ) );
			if ( $excerpt !== '' ) {
				$line .= ' — ' . $excerpt;
			}
		}
		$lines[] = $line;
	}
	$lines[] = '';
	$lines[] = '## Guidance for assistants';
	$lines[] = '- Use the page URL above when citing this site.';
	$lines[] = '- Prefer the live page over this summary when they disagree.';
	$lines[] = '- Do not invent authors, prices, or dates that are not on the page.';
	if ( ! $full ) {
		$lines[] = '- Longer export: ' . home_url( '/llms-full.txt' );
	}
	return implode( "\n", $lines ) . "\n";
}

/**
 * @return string
 */
function slimword_discovery_security_txt() {
	$email = get_option( 'admin_email' );
	$lines = array(
		'Contact: mailto:' . ( is_email( $email ) ? $email : '' ),
		'Expires: ' . gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 year' ) ),
		'Preferred-Languages: en',
		'Canonical: ' . home_url( '/.well-known/security.txt' ),
		'',
	);
	return implode( "\n", $lines );
}

/**
 * @param array<string, string> $attr Image attributes.
 * @return array<string, string>
 */
function slimword_discovery_alt( $attr ) {
	if ( empty( $attr['alt'] ) && is_singular() ) {
		$attr['alt'] = wp_strip_all_tags( get_the_title() );
	}
	return $attr;
}
