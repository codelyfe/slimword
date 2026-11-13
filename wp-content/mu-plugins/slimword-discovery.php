<?php
/**
 * Plugin Name: Slim WordPress Discovery
 * Description: Universal SEO, answer-engine, and generative-engine tags for any site.
 */

add_action( 'wp_head', 'slimword_discovery_head', 5 );
add_action( 'admin_menu', 'slimword_discovery_menu', 99 );
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

	$title = slimword_discovery_title();
	$desc  = slimword_discovery_description();
	$url   = slimword_discovery_url();
	$image = slimword_discovery_image();
	$type  = is_singular( 'post' ) ? 'article' : 'website';

	echo '<link rel="describedby" type="text/plain" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
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
				'headline'      => slimword_discovery_title(),
				'description'   => $desc,
				'datePublished' => get_post_time( 'c', true, $post ),
				'dateModified'  => get_post_modified_time( 'c', true, $post ),
				'isPartOf'      => array( '@id' => home_url( '/#website' ) ),
				'publisher'     => array( '@id' => home_url( '/#organization' ) ),
			);
			$crumbs = slimword_discovery_breadcrumbs( $post );
			if ( count( $crumbs ) > 1 ) {
				$graph[] = array(
					'@type'           => 'BreadcrumbList',
					'@id'             => get_permalink( $post ) . '#breadcrumb',
					'itemListElement' => $crumbs,
				);
			}
			$faqs = slimword_discovery_faqs( $post );
			if ( $faqs ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => get_permalink( $post ) . '#faq',
					'mainEntity' => $faqs,
				);
			}
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
function slimword_discovery_title() {
	if ( is_singular() ) {
		$custom = get_post_meta( get_queried_object_id(), 'slimword_seo_title', true );
		if ( is_string( $custom ) && $custom !== '' ) {
			return $custom;
		}
	}
	return wp_get_document_title();
}

add_filter( 'document_title_parts', 'slimword_discovery_title_parts' );

/**
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function slimword_discovery_title_parts( $parts ) {
	if ( is_singular() ) {
		$custom = get_post_meta( get_queried_object_id(), 'slimword_seo_title', true );
		if ( is_string( $custom ) && $custom !== '' ) {
			$parts['title'] = $custom;
		}
	}
	return $parts;
}

/**
 * @param WP_Post $post Current page or post.
 * @return array<int, array<string, mixed>>
 */
function slimword_discovery_breadcrumbs( $post ) {
	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => get_bloginfo( 'name' ) !== '' ? get_bloginfo( 'name' ) : __( 'Home' ),
			'item'     => home_url( '/' ),
		),
	);
	if ( is_post_type_hierarchical( $post->post_type ) ) {
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => get_the_title( $ancestor ),
				'item'     => get_permalink( $ancestor ),
			);
		}
	}
	$items[] = array(
		'@type'    => 'ListItem',
		'position' => count( $items ) + 1,
		'name'     => get_the_title( $post ),
		'item'     => get_permalink( $post ),
	);
	return $items;
}

/**
 * Questions from details/summary blocks in the content.
 *
 * @param WP_Post $post Current page or post.
 * @return array<int, array<string, mixed>>
 */
function slimword_discovery_faqs( $post ) {
	$content = (string) $post->post_content;
	if ( $content === '' || stripos( $content, '<summary' ) === false ) {
		return array();
	}
	if ( ! preg_match_all( '#<details\b[^>]*>\s*<summary\b[^>]*>(.*?)</summary>(.*?)</details>#is', $content, $matches, PREG_SET_ORDER ) ) {
		return array();
	}
	$faqs = array();
	foreach ( $matches as $match ) {
		$question = trim( wp_strip_all_tags( $match[1] ) );
		$answer   = trim( wp_strip_all_tags( $match[2] ) );
		if ( $question === '' || $answer === '' ) {
			continue;
		}
		$faqs[] = array(
			'@type'          => 'Question',
			'name'           => $question,
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $answer,
			),
		);
	}
	return $faqs;
}

add_action( 'add_meta_boxes', 'slimword_discovery_meta_box' );
add_action( 'save_post', 'slimword_discovery_save_meta' );

/**
 * Title and description fields on pages and posts.
 */
function slimword_discovery_meta_box() {
	foreach ( array( 'post', 'page' ) as $type ) {
		add_meta_box(
			'slimword-seo',
			__( 'Discovery' ),
			'slimword_discovery_meta_box_html',
			$type,
			'normal',
			'high'
		);
	}
}

/**
 * @param WP_Post $post Current post.
 */
function slimword_discovery_meta_box_html( $post ) {
	wp_nonce_field( 'slimword_seo', 'slimword_seo_nonce' );
	$title = get_post_meta( $post->ID, 'slimword_seo_title', true );
	$desc  = get_post_meta( $post->ID, 'slimword_seo_description', true );
	echo '<p><label for="slimword_seo_title">' . esc_html__( 'SEO title' ) . '</label><br>';
	echo '<input type="text" class="large-text" id="slimword_seo_title" name="slimword_seo_title" value="' . esc_attr( (string) $title ) . '"></p>';
	echo '<p><label for="slimword_seo_description">' . esc_html__( 'SEO description' ) . '</label><br>';
	echo '<textarea class="large-text" rows="3" id="slimword_seo_description" name="slimword_seo_description">' . esc_textarea( (string) $desc ) . '</textarea></p>';
	echo '<p class="description">' . esc_html__( 'Leave these blank to use the page title, excerpt, or the start of the content.' ) . '</p>';
}

/**
 * @param int $post_id Post ID.
 */
function slimword_discovery_save_meta( $post_id ) {
	if ( ! isset( $_POST['slimword_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slimword_seo_nonce'] ) ), 'slimword_seo' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$title = isset( $_POST['slimword_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['slimword_seo_title'] ) ) : '';
	$desc  = isset( $_POST['slimword_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['slimword_seo_description'] ) ) : '';
	update_post_meta( $post_id, 'slimword_seo_title', $title );
	update_post_meta( $post_id, 'slimword_seo_description', $desc );
}

/**
 * @return string
 */
function slimword_discovery_description() {
	$post = get_queried_object();
	$text = '';
	if ( $post instanceof WP_Post ) {
		$custom = get_post_meta( $post->ID, 'slimword_seo_description', true );
		if ( is_string( $custom ) && $custom !== '' ) {
			$text = $custom;
		} else {
			$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		}
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
/**
 * Settings → Discovery.
 */
function slimword_discovery_menu() {
	add_options_page(
		__( 'Discovery' ),
		__( 'Discovery' ),
		'manage_options',
		'slimword-discovery',
		'slimword_discovery_page'
	);
}

/**
 * Show the SEO, answer-engine, and generative-engine output this site already has.
 */
function slimword_discovery_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these options.' ), 403 );
	}
	$name  = get_bloginfo( 'name' );
	$desc  = get_bloginfo( 'description' );
	$logo  = (int) get_theme_mod( 'custom_logo' );
	$email = get_option( 'admin_email' );
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		)
	);
	$rows = array(
		array( __( 'Site name' ), $name !== '' ? $name : __( 'Not set' ) ),
		array( __( 'Tagline, used as the home description' ), $desc !== '' ? $desc : __( 'Not set' ) ),
		array( __( 'Home URL' ), home_url( '/' ) ),
		array( __( 'Language' ), get_bloginfo( 'language' ) ? get_bloginfo( 'language' ) : 'en' ),
		array( __( 'Logo' ), $logo ? __( 'Set' ) : __( 'Not set' ) ),
		array( __( 'Public' ), '1' === (string) get_option( 'blog_public' ) ? __( 'Yes' ) : __( 'No, robots block the whole site' ) ),
		array( __( 'Search and author pages' ), __( 'noindex' ) ),
		array( __( 'User sitemap' ), __( 'Hidden' ) ),
		array( __( 'Sitemap' ), home_url( '/wp-sitemap.xml' ) ),
		array( __( 'llms.txt' ), home_url( '/llms.txt' ) ),
		array( __( 'llms-full.txt' ), home_url( '/llms-full.txt' ) ),
		array( __( 'security.txt' ), home_url( '/.well-known/security.txt' ) ),
		array( __( 'Contact email' ), is_email( $email ) ? $email : __( 'Not set' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Discovery' ); ?></h1>
		<p><?php esc_html_e( 'These are the SEO, answer-engine, and generative-engine values this site is already publishing.' ); ?></p>
		<?php
		$front_id    = (int) get_option( 'page_on_front' );
		$share_title = $name;
		$share_desc  = $desc;
		$share_image = '';
		if ( $front_id ) {
			$custom_title = get_post_meta( $front_id, 'slimword_seo_title', true );
			$custom_desc  = get_post_meta( $front_id, 'slimword_seo_description', true );
			if ( is_string( $custom_title ) && $custom_title !== '' ) {
				$share_title = $custom_title;
			} else {
				$share_title = get_the_title( $front_id );
			}
			if ( is_string( $custom_desc ) && $custom_desc !== '' ) {
				$share_desc = $custom_desc;
			} elseif ( has_excerpt( $front_id ) ) {
				$share_desc = get_the_excerpt( $front_id );
			}
			$thumb = get_the_post_thumbnail_url( $front_id, 'full' );
			if ( $thumb ) {
				$share_image = $thumb;
			}
		}
		if ( $share_image === '' && $logo ) {
			$share_image = (string) wp_get_attachment_image_url( $logo, 'full' );
		}
		?>
		<h2><?php esc_html_e( 'Share preview' ); ?></h2>
		<div style="max-width:480px;border:1px solid #dcdcde;background:#fff">
			<?php if ( $share_image !== '' ) : ?>
				<img src="<?php echo esc_url( $share_image ); ?>" alt="" style="display:block;width:100%;height:220px;object-fit:cover">
			<?php endif; ?>
			<div style="padding:12px 16px">
				<div style="color:#646970;font-size:12px"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
				<strong><?php echo esc_html( $share_title ); ?></strong>
				<p style="margin:6px 0 0"><?php echo esc_html( $share_desc !== '' ? $share_desc : __( 'No description yet.' ) ); ?></p>
			</div>
		</div>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<th style="width:28%"><?php echo esc_html( $row[0] ); ?></th>
						<td><?php echo esc_html( $row[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'SEO' ); ?></h2>
		<p><?php esc_html_e( 'Every public page gets a canonical URL, a meta description, Open Graph tags, Twitter tags, and schema.org JSON. A page or post can set its own SEO title and description. Otherwise the title is the page title, and the description is the excerpt or the start of the content. The home page uses the tagline when it has no custom description. Images without alt text use the page title. Pages and posts also publish a breadcrumb trail, and details/summary questions become FAQ schema.' ); ?></p>

		<h2><?php esc_html_e( 'AEO' ); ?></h2>
		<p><?php esc_html_e( 'llms.txt lists published pages and the latest posts. llms-full.txt adds short excerpts. The home page also links to llms.txt with describedby.' ); ?></p>
		<h3><?php esc_html_e( 'Pages in llms.txt' ); ?></h3>
		<?php slimword_discovery_list( $pages ); ?>
		<h3><?php esc_html_e( 'Posts in llms.txt' ); ?></h3>
		<?php slimword_discovery_list( $posts ); ?>

		<h2><?php esc_html_e( 'GEO' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Organization and WebSite schema, including a site search action, on the home page.' ); ?></li>
			<li><?php esc_html_e( 'Article schema on posts, WebPage schema on pages, a breadcrumb trail, and FAQ schema when the content has details and summary blocks.' ); ?></li>
			<li><?php esc_html_e( 'Training crawlers GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, and CCBot may read only llms.txt and llms-full.txt.' ); ?></li>
			<li><?php esc_html_e( 'Search crawlers PerplexityBot, ChatGPT-User, OAI-SearchBot, and Claude-SearchBot may read the public site.' ); ?></li>
		</ul>
	</div>
	<?php
}

/**
 * @param WP_Post[] $items Published content.
 */
function slimword_discovery_list( $items ) {
	if ( ! $items ) {
		echo '<p>' . esc_html__( 'None published.' ) . '</p>';
		return;
	}
	echo '<ul>';
	foreach ( $items as $item ) {
		echo '<li><a href="' . esc_url( get_permalink( $item ) ) . '">' . esc_html( get_the_title( $item ) ) . '</a></li>';
	}
	echo '</ul>';
}

function slimword_discovery_alt( $attr ) {
	if ( empty( $attr['alt'] ) && is_singular() ) {
		$attr['alt'] = wp_strip_all_tags( get_the_title() );
	}
	return $attr;
}
