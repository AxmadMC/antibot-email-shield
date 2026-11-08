<?php
/**
 * Plugin Name:       Antibot Email Shield
 * Plugin URI:        https://docs.ahmedmc.com/email-shield/
 * Description:       Shows your email to people, not bots. Email addresses never appear in your pages; visitors click a button, pass a Cloudflare Turnstile check, and the email is revealed for a limited time.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ahmed MC
 * Author URI:        https://ahmedmc.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       antibot-email-shield
 *
 * @package AntibotEmailShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ANTIBOT_EMAIL_SHIELD_VERSION', '1.0.0' );
define( 'ANTIBOT_EMAIL_SHIELD_OPTION', 'antibot_email_shield_settings' );
define( 'ANTIBOT_EMAIL_SHIELD_URL', plugin_dir_url( __FILE__ ) );
define( 'ANTIBOT_EMAIL_SHIELD_ACTION', 'antibot_email_reveal' ); // Turnstile action name, checked server-side.
define( 'ANTIBOT_EMAIL_SHIELD_MAX_TOKEN', 2048 );            // Turnstile tokens are at most 2048 characters.

// Settings.

/**
 * Default settings.
 *
 * @return array
 */
function antibot_email_shield_defaults() {
	return array(
		'emails'       => '',
		'site_key'     => '',
		'secret_key'   => '',
		'hide_after'   => 25,
		'button_label' => 'Show email address',
		'rate_limit'   => 10,
	);
}

/**
 * Saved settings merged with defaults.
 *
 * @return array
 */
function antibot_email_shield_settings() {
	$saved = get_option( ANTIBOT_EMAIL_SHIELD_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), antibot_email_shield_defaults() );
}

/**
 * Parse one settings line. Accepts "email" or "id: email".
 * With a plain email, the id is the part before the @ (legal@site.com -> "legal").
 *
 * @param string $line Raw line.
 * @return array|null [ id, email ] or null when invalid.
 */
function antibot_email_shield_parse_line( $line ) {
	$line = trim( (string) $line );
	if ( '' === $line ) {
		return null;
	}
	if ( false === strpos( $line, ':' ) ) {
		if ( ! is_email( $line ) ) {
			return null;
		}
		$email = sanitize_email( $line );
		$id    = sanitize_key( (string) strstr( $email, '@', true ) );
		return $id ? array( $id, $email ) : null;
	}
	list( $id, $email ) = array_map( 'trim', explode( ':', $line, 2 ) );

	$id    = sanitize_key( $id );
	$email = sanitize_email( $email );
	return ( $id && is_email( $email ) ) ? array( $id, $email ) : null;
}

/**
 * Saved emails as id => email.
 *
 * @return array<string,string>
 */
function antibot_email_shield_map() {
	$map   = array();
	$lines = preg_split( '/\r\n|\r|\n/', (string) antibot_email_shield_settings()['emails'] );
	foreach ( (array) $lines as $line ) {
		$parsed = antibot_email_shield_parse_line( $line );
		if ( $parsed ) {
			$map[ $parsed[0] ] = $parsed[1];
		}
	}
	return $map;
}

/**
 * Turnstile is enabled only when both keys are saved.
 *
 * @return bool
 */
function antibot_email_shield_turnstile_enabled() {
	$s = antibot_email_shield_settings();
	return '' !== $s['site_key'] && '' !== $s['secret_key'];
}

/**
 * One-time migration from the plugin's earlier names (Unbotted Email, AMC Email Shield).
 * Old settings are copied, never deleted, so switching back is always possible.
 */
function antibot_email_shield_maybe_migrate() {
	if ( false !== get_option( ANTIBOT_EMAIL_SHIELD_OPTION, false ) ) {
		return;
	}
	foreach ( array( 'unbotted_email_settings', 'amc_email_shield_settings' ) as $old_name ) {
		$old = get_option( $old_name, false );
		if ( is_array( $old ) ) {
			add_option( ANTIBOT_EMAIL_SHIELD_OPTION, array_intersect_key( $old, antibot_email_shield_defaults() ) );
			return;
		}
	}
}
register_activation_hook( __FILE__, 'antibot_email_shield_maybe_migrate' );
add_action( 'admin_init', 'antibot_email_shield_maybe_migrate' );

// Admin: Settings → Antibot Email Shield.

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Antibot Email Shield', 'antibot-email-shield' ),
			__( 'Antibot Email Shield', 'antibot-email-shield' ),
			'manage_options',
			'antibot-email-shield',
			'antibot_email_shield_render_settings_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'antibot_email_shield_group',
			ANTIBOT_EMAIL_SHIELD_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'antibot_email_shield_sanitize_settings',
				'show_in_rest'      => false,
				'default'           => antibot_email_shield_defaults(),
			)
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=antibot-email-shield' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'antibot-email-shield' ) . '</a>' );
		return $links;
	}
);

/**
 * Sanitize settings on save. The Settings API already checks the nonce and the
 * manage_options capability before calling this.
 *
 * @param mixed $input Submitted values.
 * @return array
 */
function antibot_email_shield_sanitize_settings( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$current = antibot_email_shield_settings();
	$d       = antibot_email_shield_defaults();
	$out     = array();

	$clean    = array();
	$rejected = array();
	$lines    = preg_split( '/\r\n|\r|\n/', (string) ( $input['emails'] ?? '' ) );
	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$parsed = antibot_email_shield_parse_line( $line );
		if ( $parsed ) {
			$clean[] = $parsed[0] . ': ' . $parsed[1];
		} else {
			$rejected[] = $line;
		}
	}
	$out['emails'] = implode( "\n", array_slice( $clean, 0, 50 ) );

	$out['site_key']     = substr( sanitize_text_field( (string) ( $input['site_key'] ?? '' ) ), 0, 200 );
	$out['button_label'] = substr( sanitize_text_field( (string) ( $input['button_label'] ?? '' ) ), 0, 100 );
	$out['hide_after']   = max( 0, min( 600, absint( $input['hide_after'] ?? $d['hide_after'] ) ) );
	$out['rate_limit']   = max( 1, min( 100, absint( $input['rate_limit'] ?? $d['rate_limit'] ) ) );
	if ( '' === $out['button_label'] ) {
		$out['button_label'] = $d['button_label'];
	}

	// Secret key: blank keeps the saved key (it is never printed back into the form).
	$new_secret        = substr( sanitize_text_field( (string) ( $input['secret_key'] ?? '' ) ), 0, 200 );
	$out['secret_key'] = '' !== $new_secret ? $new_secret : (string) $current['secret_key'];
	if ( ! empty( $input['clear_secret'] ) ) {
		$out['secret_key'] = '';
	}

	if ( function_exists( 'add_settings_error' ) ) {
		if ( $rejected ) {
			add_settings_error(
				ANTIBOT_EMAIL_SHIELD_OPTION,
				'antibot_email_shield_bad_lines',
				sprintf(
					/* translators: %s: the lines that could not be saved */
					__( 'These lines were not saved because they are not a valid email (use "you@yourdomain.com" or "id: you@yourdomain.com"): %s', 'antibot-email-shield' ),
					implode( ' | ', array_map( 'sanitize_text_field', $rejected ) )
				),
				'error'
			);
		} elseif ( empty( $clean ) ) {
			add_settings_error(
				ANTIBOT_EMAIL_SHIELD_OPTION,
				'antibot_email_shield_none',
				__( 'No emails are saved yet. Type an email such as you@yourdomain.com in the Protected emails box.', 'antibot-email-shield' ),
				'warning'
			);
		}
	}
	return $out;
}

/**
 * Render the settings page.
 */
function antibot_email_shield_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s          = antibot_email_shield_settings();
	$has_secret = '' !== $s['secret_key'];
	$map        = antibot_email_shield_map();
	$name       = ANTIBOT_EMAIL_SHIELD_OPTION;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Antibot Email Shield', 'antibot-email-shield' ); ?></h1>

		<?php if ( ! antibot_email_shield_turnstile_enabled() ) : ?>
			<div class="notice notice-warning"><p>
				<?php esc_html_e( 'Cloudflare Turnstile is not set up yet. Emails are still kept out of your pages, but anyone (including a bot) can request them from the reveal endpoint. Add your Turnstile keys for real bot protection.', 'antibot-email-shield' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'antibot_email_shield_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="abes-emails"><?php esc_html_e( 'Protected emails', 'antibot-email-shield' ); ?></label></th>
					<td>
						<textarea id="abes-emails" name="<?php echo esc_attr( $name ); ?>[emails]" rows="4" class="large-text code" placeholder="<?php esc_attr_e( 'Example (type your own): legal@yourdomain.com', 'antibot-email-shield' ); ?>"><?php echo esc_textarea( $s['emails'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'One email per line. Its id is the part before the @ (or set your own with "id: email"). Use the id in the shortcode, for example:', 'antibot-email-shield' ); ?>
							<code>[email_shield id="legal"]</code>
						</p>
						<p class="description" style="margin-top:8px;">
							<?php if ( $map ) : ?>
								<strong><?php esc_html_e( 'Saved:', 'antibot-email-shield' ); ?></strong>
								<?php
								$parts = array();
								foreach ( $map as $id => $email ) {
									$parts[] = '<code>' . esc_html( $id ) . '</code> &rarr; ' . esc_html( $email );
								}
								echo wp_kses( implode( ', ', $parts ), array( 'code' => array() ) );
								?>
							<?php else : ?>
								<strong style="color:#b32d2e;"><?php esc_html_e( 'No emails saved yet.', 'antibot-email-shield' ); ?></strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abes-site"><?php esc_html_e( 'Turnstile site key', 'antibot-email-shield' ); ?></label></th>
					<td><input id="abes-site" type="text" class="regular-text code" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[site_key]" value="<?php echo esc_attr( $s['site_key'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="abes-secret"><?php esc_html_e( 'Turnstile secret key', 'antibot-email-shield' ); ?></label></th>
					<td>
						<input id="abes-secret" type="password" class="regular-text code" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[secret_key]" value="" placeholder="<?php echo $has_secret ? esc_attr__( 'Saved — leave blank to keep', 'antibot-email-shield' ) : ''; ?>">
						<?php if ( $has_secret ) : ?>
							<label style="margin-left:8px;"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[clear_secret]" value="1"> <?php esc_html_e( 'Remove saved key', 'antibot-email-shield' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Create a free widget in the Cloudflare dashboard (Turnstile → Add widget) and paste both keys. The secret key stays on your server and is never sent to visitors.', 'antibot-email-shield' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abes-hide"><?php esc_html_e( 'Hide again after (seconds)', 'antibot-email-shield' ); ?></label></th>
					<td><input id="abes-hide" type="number" min="0" max="600" class="small-text" name="<?php echo esc_attr( $name ); ?>[hide_after]" value="<?php echo esc_attr( (string) $s['hide_after'] ); ?>"> <span class="description"><?php esc_html_e( '0 = stay visible', 'antibot-email-shield' ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><label for="abes-label"><?php esc_html_e( 'Button label', 'antibot-email-shield' ); ?></label></th>
					<td><input id="abes-label" type="text" class="regular-text" maxlength="100" name="<?php echo esc_attr( $name ); ?>[button_label]" value="<?php echo esc_attr( $s['button_label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="abes-rate"><?php esc_html_e( 'Rate limit', 'antibot-email-shield' ); ?></label></th>
					<td><input id="abes-rate" type="number" min="1" max="100" class="small-text" name="<?php echo esc_attr( $name ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $s['rate_limit'] ); ?>"> <span class="description"><?php esc_html_e( 'Max reveal requests per visitor every 10 minutes', 'antibot-email-shield' ); ?></span></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Shortcode: [email_shield id="legal" label="Email me"].
// [antibot_email_shield] is always available; the short and legacy names
// ([email_shield], [unbotted], [protected_email]) are added only when no other
// plugin already uses them, so this plugin never overrides someone else's shortcode.

/**
 * Shortcode tags handled by this plugin.
 *
 * @return string[]
 */
function antibot_email_shield_tags() {
	return array( 'antibot_email_shield', 'email_shield', 'unbotted', 'protected_email' );
}

add_action(
	'init',
	function () {
		foreach ( antibot_email_shield_tags() as $tag ) {
			if ( 'antibot_email_shield' === $tag || ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, 'antibot_email_shield_shortcode' );
			}
		}
	}
);

/**
 * Whether content contains one of this plugin's shortcodes.
 *
 * @param string $content Content.
 * @return bool
 */
function antibot_email_shield_has_shortcode( $content ) {
	global $shortcode_tags;
	foreach ( antibot_email_shield_tags() as $tag ) {
		if ( isset( $shortcode_tags[ $tag ] ) && 'antibot_email_shield_shortcode' === $shortcode_tags[ $tag ] && has_shortcode( $content, $tag ) ) {
			return true;
		}
	}
	return false;
}

// Elementor's HTML widget doesn't run shortcodes on its own; run ours there.
add_filter(
	'elementor/widget/render_content',
	function ( $content, $widget ) {
		if (
			is_string( $content ) && is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'html' === $widget->get_name()
			&& antibot_email_shield_has_shortcode( $content )
		) {
			$content = do_shortcode( $content );
		}
		return $content;
	},
	10,
	2
);

/**
 * Shortcode output. The email address is never included in the page.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function antibot_email_shield_shortcode( $atts ) {
	$s    = antibot_email_shield_settings();
	$atts = shortcode_atts(
		array(
			'id'    => '',
			'label' => $s['button_label'],
		),
		$atts,
		'email_shield'
	);

	$id  = sanitize_key( $atts['id'] );
	$map = antibot_email_shield_map();
	if ( '' === $id && 1 === count( $map ) ) {
		$id = (string) array_key_first( $map );
	}
	if ( ! isset( $map[ $id ] ) ) {
		return current_user_can( 'manage_options' )
			? '<em>' . esc_html__( 'Antibot Email Shield: unknown email id. Check Settings → Antibot Email Shield.', 'antibot-email-shield' ) . '</em>'
			: '';
	}

	antibot_email_shield_enqueue_assets();

	ob_start();
	?>
	<span class="abes" data-id="<?php echo esc_attr( $id ); ?>">
		<button type="button" class="abes-btn">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg>
			<span><?php echo esc_html( $atts['label'] ); ?></span>
		</button>
		<span class="abes-check" hidden></span>
		<span class="abes-result" hidden></span>
		<span class="abes-msg" role="status" aria-live="polite"></span>
	</span>
	<?php
	return (string) ob_get_clean();
}

/**
 * Load front-end assets only on pages that use the shortcode.
 */
function antibot_email_shield_enqueue_assets( $with_turnstile = true ) {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	$s    = antibot_email_shield_settings();

	wp_enqueue_style( 'antibot-email-shield', ANTIBOT_EMAIL_SHIELD_URL . 'assets/antibot-email-shield.css', array(), ANTIBOT_EMAIL_SHIELD_VERSION );

	$deps = array();
	if ( $with_turnstile && antibot_email_shield_turnstile_enabled() ) {
		// Cloudflare requires loading api.js from its own domain (it may not be self-hosted).
		wp_enqueue_script( 'antibot-email-shield-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, array( 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		$deps[] = 'antibot-email-shield-turnstile';
	}
	wp_enqueue_script( 'antibot-email-shield', ANTIBOT_EMAIL_SHIELD_URL . 'assets/antibot-email-shield.js', $deps, ANTIBOT_EMAIL_SHIELD_VERSION, array( 'in_footer' => true ) );
	wp_localize_script(
		'antibot-email-shield',
		'ANTIBOT_EMAIL_SHIELD',
		array(
			'endpoint'  => esc_url_raw( rest_url( 'antibot-email-shield/v1/reveal' ) ),
			'siteKey'   => antibot_email_shield_turnstile_enabled() ? $s['site_key'] : '',
			'action'    => ANTIBOT_EMAIL_SHIELD_ACTION,
			'hideAfter' => (int) $s['hide_after'],
			'i18n'      => array(
				'verifying' => __( 'Verifying you are human…', 'antibot-email-shield' ),
				'failed'    => __( 'Verification failed. Please try again.', 'antibot-email-shield' ),
				'tooMany'   => __( 'Too many requests. Please wait a few minutes.', 'antibot-email-shield' ),
				/* translators: %d: seconds until the email is hidden again */
				'hidesIn'   => __( 'Hides in %ds', 'antibot-email-shield' ),
			),
		)
	);
}

/*
 * Elementor's Element Caching can serve a saved copy of a widget without running the
 * shortcode again, so the assets above are never added and the button does nothing.
 * When Elementor is active, load the small CSS and JS on every front-end page instead.
 * The Cloudflare Turnstile script is then loaded by the JS only when a button is on the page.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( defined( 'ELEMENTOR_VERSION' ) && ! is_admin() && antibot_email_shield_map() ) {
			antibot_email_shield_enqueue_assets( false );
		}
	},
	20
);

// REST endpoint: POST /wp-json/antibot-email-shield/v1/reveal.

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'antibot-email-shield/v1',
			'/reveal',
			array(
				'methods'             => 'POST',
				'callback'            => 'antibot_email_shield_rest_reveal',
				// Public by design: anonymous visitors must be able to reveal the email.
				// Protection comes from Turnstile verification + per-IP rate limiting below.
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'type'              => 'string',
						'required'          => true,
						'maxLength'         => 64,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_key',
					),
					'token' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'maxLength'         => ANTIBOT_EMAIL_SHIELD_MAX_TOKEN,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
);

/**
 * Visitor IP, used for rate limiting and sent to Cloudflare as remoteip.
 * Only REMOTE_ADDR is trusted by default; forwarded headers can be spoofed.
 *
 * @return string
 */
function antibot_email_shield_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	/**
	 * Trust Cloudflare's CF-Connecting-IP header. Enable ONLY when all traffic
	 * reaches your server through Cloudflare's proxy, otherwise it can be faked.
	 */
	if ( apply_filters( 'antibot_email_shield_trust_cf_header', false ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
	}
	$ip = filter_var( $ip, FILTER_VALIDATE_IP );
	return $ip ? $ip : '0.0.0.0';
}

/**
 * Per-IP rate limit (10-minute window). Returns true when the limit is exceeded.
 *
 * @return bool
 */
function antibot_email_shield_rate_limited() {
	$limit = (int) antibot_email_shield_settings()['rate_limit'];
	$key   = 'antibot_email_shield_rl_' . md5( antibot_email_shield_client_ip() . wp_salt( 'nonce' ) );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return true;
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * Normalise a host for comparison (lowercase, no leading www.).
 *
 * @param string $host Host name.
 * @return string
 */
function antibot_email_shield_norm_host( $host ) {
	$host = strtolower( (string) $host );
	return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
}

/**
 * Verify a Turnstile token with Cloudflare. Fails closed: any error means "not verified".
 *
 * @param string $token Token from the widget.
 * @return bool
 */
function antibot_email_shield_verify_turnstile( $token ) {
	$token = (string) $token;
	if ( '' === $token || strlen( $token ) > ANTIBOT_EMAIL_SHIELD_MAX_TOKEN ) {
		return false;
	}
	$response = wp_safe_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout'     => 8,
			'redirection' => 0,
			'body'        => array(
				'secret'   => antibot_email_shield_settings()['secret_key'],
				'response' => $token,
				'remoteip' => antibot_email_shield_client_ip(),
			),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || true !== ( $data['success'] ?? false ) ) {
		return false;
	}
	// The token must have been issued for this widget action...
	if ( isset( $data['action'] ) && ANTIBOT_EMAIL_SHIELD_ACTION !== $data['action'] ) {
		return false;
	}
	// ...and on this site (stops tokens solved on another website being reused here).
	$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! empty( $data['hostname'] ) && '' !== $site_host && antibot_email_shield_norm_host( $data['hostname'] ) !== antibot_email_shield_norm_host( $site_host ) ) {
		return false;
	}
	return true;
}

/**
 * Handle a reveal request.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function antibot_email_shield_rest_reveal( WP_REST_Request $request ) {
	$headers = array(
		'Cache-Control'          => 'no-store, max-age=0',
		'X-Robots-Tag'           => 'noindex, nofollow',
		'X-Content-Type-Options' => 'nosniff',
	);

	if ( antibot_email_shield_rate_limited() ) {
		return new WP_REST_Response( array( 'error' => 'rate_limited' ), 429, $headers );
	}

	// Verify the visitor first, so ids can't be probed without passing the check.
	if ( antibot_email_shield_turnstile_enabled() && ! antibot_email_shield_verify_turnstile( (string) $request->get_param( 'token' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'verification_failed' ), 403, $headers );
	}

	$map = antibot_email_shield_map();
	$id  = (string) $request->get_param( 'id' );
	if ( ! isset( $map[ $id ] ) ) {
		return new WP_REST_Response( array( 'error' => 'not_found' ), 404, $headers );
	}

	return new WP_REST_Response( array( 'email' => $map[ $id ] ), 200, $headers );
}
