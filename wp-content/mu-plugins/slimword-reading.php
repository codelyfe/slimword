<?php
/**
 * Plugin Name: Slim WordPress Reading
 * Description: The same accessibility panel as SellingIt. Choices stay in the browser.
 */

add_action( 'wp_enqueue_scripts', 'slimword_reading_script', 40 );

/**
 * Load the accessibility panel on public pages.
 */
function slimword_reading_script() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || ! slimword_reading_enabled() ) {
		return;
	}
	$path = __DIR__ . '/slimword-reading/a11y-widget.min.js';
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'slimword-reading',
		content_url( 'mu-plugins/slimword-reading/a11y-widget.min.js' ),
		array(),
		(string) filemtime( $path ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

add_action(
	'wp_head',
	static function () {
		if ( ! slimword_reading_enabled() ) {
			return;
		}
		if ( is_admin() ) {
			return;
		}
		$side = 'right' === get_option( 'slimword_reading_corner', 'left' ) ? 'right:18px!important;left:auto!important' : 'left:18px!important;right:auto!important';
		echo '<style>#a11y-widget-root{position:fixed!important;z-index:1000000!important;' . $side . ';bottom:18px!important;width:auto!important;height:auto!important;pointer-events:none}#a11y-widget-root *{pointer-events:auto}</style>';
	},
	99
);

add_action( 'wp_footer', 'slimword_reading_fit', 99999 );

/**
 * Make the panel's choices apply to WordPress themes, and keep the panel and cookie notice usable.
 */
function slimword_reading_fit() {
	if ( is_admin() || ! slimword_reading_enabled() ) {
		return;
	}
	$css = '
html[data-a11y-font-scale]{font-size:var(--a11y-font-scale)!important}
html[data-a11y-font-scale] body{font-size:1rem!important}
html[data-a11y-dark] .wp-site-blocks,
html[data-a11y-dark] #page,
html[data-a11y-dark] .site,
html[data-a11y-dark] header,
html[data-a11y-dark] footer{background-color:#0f1218!important;color:#e9eef5!important}
html[data-a11y-hc] .wp-site-blocks,
html[data-a11y-hc] #page,
html[data-a11y-hc] .site,
html[data-a11y-hc] header,
html[data-a11y-hc] footer{background:#000!important;color:#fff!important}
html[data-a11y-grayscale] #slimword-cookie,
html[data-a11y-grayscale] #wpadminbar,
html[data-a11y-grayscale] #a11y-widget-root,
html[data-a11y-grayscale] #a11y-hover-read-btn{filter:none!important}
html[data-a11y-hide-images] #a11y-widget-root img,
html[data-a11y-hide-images] #wpadminbar img{display:inline-block!important}
html[data-a11y-pause-anim] #a11y-widget-root,
html[data-a11y-pause-anim] #a11y-widget-root *,
html[data-a11y-pause-anim] #slimword-cookie{animation:auto!important}
body:has(#slimword-cookie:not([hidden])) #a11y-widget-root{bottom:96px!important}
body.admin-bar #a11y-widget-root{z-index:99999!important}
';
	$tools = get_option( 'slimword_a11y_tools', array() );
	$keep  = is_array( $tools ) ? $tools : array();
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var s=document.createElement("style");s.id="slimword-reading-fit";s.textContent=' . wp_json_encode( $css ) . ';document.head.appendChild(s);var keep=' . wp_json_encode( $keep ) . ';if(!keep.length)return;var n=0;var t=setInterval(function(){var root=document.getElementById("a11y-widget-root");var shadow=root&&root.shadowRoot;n++;if(!shadow){if(n>20)clearInterval(t);return;}clearInterval(t);shadow.querySelectorAll("input[id]").forEach(function(input){if(keep.indexOf(input.id)===-1){var row=input.closest(".row")||input.parentElement;if(row)row.style.display="none";}});},200);});</script>';
}
