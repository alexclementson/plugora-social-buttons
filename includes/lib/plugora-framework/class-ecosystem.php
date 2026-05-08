<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugora Ecosystem — reusable cross-plugin upsell + discovery section.
 *
 * Drop into any Plugora plugin admin page with:
 *
 *   Plugora_Ecosystem::render( [
 *     'current_slug' => 'plugora-cf7-mailchimp', // hide self
 *     'context'      => 'settings',              // 'settings' | 'dashboard' | 'landing'
 *   ] );
 *
 * It will:
 *   - Pull a remote catalog from https://plugora.dev/api/ecosystem.json
 *     (cached 12h via transient), with a built-in local fallback so it never
 *     looks broken offline.
 *   - Hide any plugin the user already has installed (via slug → main file map
 *     or by detecting the Plugora_* class at runtime).
 *   - Boost contextually relevant cards based on other plugins active on the
 *     site (e.g. promote CF7 Mailchimp Connector when CF7 is installed,
 *     promote upcoming Woo tools when WooCommerce is installed).
 *   - Render a premium-looking grid (cards w/ icon, description, badges,
 *     primary "Install" / "Learn more" buttons) plus a review prompt and
 *     "Request a plugin" callout.
 *   - Let users dismiss the section per-user via user meta.
 *
 * Loaded by every Plugora plugin. Idempotent (class_exists guard).
 */
if ( ! class_exists( 'Plugora_Ecosystem' ) ) :

class Plugora_Ecosystem {
	const REMOTE_URL    = 'https://plugora.dev/api/ecosystem.json';
	const CACHE_KEY     = 'plugora_ecosystem_catalog';
	const CACHE_TTL     = 12 * HOUR_IN_SECONDS;
	const DISMISS_META  = 'plugora_ecosystem_dismissed';
	const REVIEW_META   = 'plugora_ecosystem_review_dismissed';
	const ASSETS_HOOK   = 'plugora_ecosystem_assets_printed';

	/**
	 * Public entry point — render the ecosystem section.
	 *
	 * @param array $args {
	 *   @type string $current_slug Slug of the host plugin (filtered out of cards).
	 *   @type string $context      'settings' | 'dashboard' | 'landing'.
	 *   @type int    $limit        Max cards to show (default 4).
	 *   @type bool   $show_review  Whether to render the review prompt (default true).
	 *   @type bool   $show_request Whether to render the "request a plugin" CTA (default true).
	 * }
	 */
	public static function render( array $args = [] ) {
		$args = wp_parse_args( $args, [
			'current_slug' => '',
			'context'      => 'settings',
			'limit'        => 4,
			'show_review'  => true,
			'show_request' => true,
		] );

		// Per-user dismiss.
		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) === '1' ) {
			return;
		}

		$catalog = self::get_catalog();
		$cards   = self::filter_and_rank( $catalog, $args['current_slug'] );
		$cards   = array_slice( $cards, 0, max( 1, (int) $args['limit'] ) );

		self::print_assets_once();
		?>
		<section class="plugora-eco" data-context="<?php echo esc_attr( $args['context'] ); ?>">
			<div class="plugora-eco__bar" aria-hidden="true"></div>

			<header class="plugora-eco__header">
				<div class="plugora-eco__heading">
					<span class="plugora-eco__chip">
						<span class="plugora-eco__chip-dot" aria-hidden="true"></span>
						Plugora ecosystem
					</span>
					<h2 class="plugora-eco__title">More from the Plugora catalog</h2>
					<p class="plugora-eco__sub">A small, curated set of plugins that pair nicely with what you already have installed.</p>
				</div>
				<div class="plugora-eco__header-actions">
					<a class="plugora-eco__link" href="https://plugora.dev/plugins" target="_blank" rel="noreferrer">Browse all<span aria-hidden="true"> →</span></a>
					<button type="button" class="plugora-eco__icon-btn" data-plugora-eco-dismiss aria-label="Hide Plugora ecosystem section" title="Hide section">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
			</header>

			<?php if ( empty( $cards ) ) : ?>
				<div class="plugora-eco__empty">
					<p><strong>You're all caught up.</strong> Every Plugora plugin we ship today is already installed.</p>
					<a class="plugora-eco__link" href="https://plugora.dev/coming-soon" target="_blank" rel="noreferrer">See what's coming next <span aria-hidden="true">→</span></a>
				</div>
			<?php else : ?>
				<div class="plugora-eco__grid">
					<?php foreach ( $cards as $card ) : self::render_card( $card ); endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $args['show_review'] || $args['show_request'] ) : ?>
				<div class="plugora-eco__footer">
					<?php if ( $args['show_review'] ) self::render_review_prompt( $args['current_slug'] ); ?>
					<?php if ( $args['show_request'] ) self::render_request_prompt(); ?>
				</div>
			<?php endif; ?>

			<nav class="plugora-eco__links" aria-label="Plugora resources">
				<a href="https://plugora.dev/docs" target="_blank" rel="noreferrer">
					<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
					Docs
				</a>
				<a href="https://plugora.dev/changelog" target="_blank" rel="noreferrer">
					<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
					Changelog
				</a>
				<a href="https://plugora.dev/support" target="_blank" rel="noreferrer">
					<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
					Support
				</a>
				<a href="https://plugora.dev" target="_blank" rel="noreferrer">
					<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20"/><path d="M12 2a15 15 0 0 0 0 20"/></svg>
					plugora.dev
				</a>
			</nav>
		</section>
		<?php
	}

	// ---------------------------------------------------------------------
	// Cards
	// ---------------------------------------------------------------------

	private static function render_card( array $c ) {
		$badge = ! empty( $c['badge'] ) ? $c['badge'] : '';
		$tone  = $c['badge_tone'] ?? 'neutral';
		?>
		<article class="plugora-eco-card plugora-eco-card--tone-<?php echo esc_attr( $tone ); ?>">
			<div class="plugora-eco-card__media" aria-hidden="true">
				<div class="plugora-eco-card__icon">
					<?php echo wp_kses_post( $c['icon'] ?? self::default_icon() ); ?>
				</div>
				<?php if ( $badge ) : ?>
					<span class="plugora-eco-card__badge plugora-eco-card__badge--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $badge ); ?></span>
				<?php endif; ?>
			</div>

			<div class="plugora-eco-card__body">
				<h3 class="plugora-eco-card__title"><?php echo esc_html( $c['name'] ); ?></h3>
				<p class="plugora-eco-card__desc"><?php echo esc_html( $c['description'] ); ?></p>

				<?php if ( ! empty( $c['rating'] ) ) :
					$rating = max( 0, min( 5, (float) $c['rating'] ) );
					$full   = (int) floor( $rating );
					?>
					<div class="plugora-eco-card__rating" title="<?php echo esc_attr( number_format_i18n( $rating, 1 ) . ' out of 5' ); ?>">
						<span class="plugora-eco-card__stars" aria-hidden="true"><?php echo esc_html( str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full ) ); ?></span>
						<span class="plugora-eco-card__rating-text">
							<?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?>
							<?php if ( ! empty( $c['rating_count'] ) ) : ?>
								<span class="plugora-eco-card__rating-count">· <?php echo esc_html( number_format_i18n( (int) $c['rating_count'] ) ); ?> reviews</span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="plugora-eco-card__actions">
				<a class="plugora-eco-btn plugora-eco-btn--primary" href="<?php echo esc_url( $c['install_url'] ?? $c['url'] ); ?>" target="_blank" rel="noreferrer">
					<?php echo esc_html( $c['cta'] ?? 'Get plugin' ); ?>
					<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
				</a>
				<?php if ( ! empty( $c['learn_url'] ) ) : ?>
					<a class="plugora-eco-btn plugora-eco-btn--ghost" href="<?php echo esc_url( $c['learn_url'] ); ?>" target="_blank" rel="noreferrer">Learn more</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	// ---------------------------------------------------------------------
	// Catalog (remote + fallback)
	// ---------------------------------------------------------------------

	private static function get_catalog() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$catalog = self::fetch_remote_catalog();
		if ( empty( $catalog ) ) {
			$catalog = self::fallback_catalog();
		}

		set_transient( self::CACHE_KEY, $catalog, self::CACHE_TTL );
		return $catalog;
	}

	private static function fetch_remote_catalog() {
		$res = wp_remote_get( self::REMOTE_URL, [
			'timeout' => 4,
			'headers' => [ 'Accept' => 'application/json' ],
		] );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return [];
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) || empty( $body['plugins'] ) ) {
			return [];
		}
		// Trust only known fields.
		$out = [];
		foreach ( (array) $body['plugins'] as $p ) {
			if ( empty( $p['slug'] ) || empty( $p['name'] ) ) continue;
			$out[] = [
				'slug'         => sanitize_key( $p['slug'] ),
				'name'         => sanitize_text_field( $p['name'] ),
				'description'  => sanitize_text_field( $p['description'] ?? '' ),
				'badge'        => isset( $p['badge'] )       ? sanitize_text_field( $p['badge'] )       : '',
				'badge_tone'   => isset( $p['badge_tone'] )  ? sanitize_key( $p['badge_tone'] )         : 'neutral',
				'cta'          => isset( $p['cta'] )         ? sanitize_text_field( $p['cta'] )         : 'Get plugin',
				'install_url'  => isset( $p['install_url'] ) ? esc_url_raw( $p['install_url'] )         : '',
				'learn_url'    => isset( $p['learn_url'] )   ? esc_url_raw( $p['learn_url'] )           : '',
				'url'          => isset( $p['url'] )         ? esc_url_raw( $p['url'] )                 : '',
				'rating'       => isset( $p['rating'] )      ? (float) $p['rating']                     : 0,
				'rating_count' => isset( $p['rating_count'] )? (int) $p['rating_count']                 : 0,
				'requires'     => isset( $p['requires'] )    ? (array) $p['requires']                   : [],
				'priority'     => isset( $p['priority'] )    ? (int) $p['priority']                     : 0,
				'icon'         => isset( $p['icon'] )        ? wp_kses_post( $p['icon'] )               : '',
				'class_marker' => isset( $p['class_marker'] )? sanitize_text_field( $p['class_marker'] ): '',
			];
		}
		return $out;
	}

	/**
	 * Local fallback catalog — guarantees the section always renders something
	 * useful even when plugora.dev is unreachable. Mirrors the pricing/landing
	 * data so the WordPress admin and the marketing site stay in sync.
	 */
	private static function fallback_catalog() {
		return [
			[
				'slug'         => 'plugora-folders-pages',
				'name'         => 'Lightning Folders for Pages',
				'description'  => 'Drag-and-drop folders for the WordPress Pages screen. Free + premium in one plugin.',
				'badge'        => 'Popular',
				'badge_tone'   => 'pink',
				'cta'          => 'Get Folders',
				'install_url'  => 'https://plugora.dev/buy/folders-pages',
				'learn_url'    => 'https://plugora.dev/plugins/folders-pages',
				'rating'       => 4.9,
				'rating_count' => 128,
				'class_marker' => 'Plugora_Folders_Settings',
				'priority'     => 80,
			],
			[
				'slug'         => 'plugora-cf7-mailchimp',
				'name'         => 'CF7 → Mailchimp Connector',
				'description'  => 'Sync Contact Form 7 submissions straight into Mailchimp audiences with per-form mapping.',
				'badge'        => 'New',
				'badge_tone'   => 'primary',
				'cta'          => 'Get CF7 Mailchimp',
				'install_url'  => 'https://plugora.dev/buy/cf7-mailchimp',
				'learn_url'    => 'https://plugora.dev/plugins/cf7-mailchimp',
				'rating'       => 5.0,
				'rating_count' => 14,
				'requires'     => [ 'wpcf7' ],
				'class_marker' => 'Plugora_CF7MC_Settings',
				'priority'     => 70,
			],
			[
				'slug'         => 'plugora-social-share',
				'name'         => 'Plugora Social Share Buttons',
				'description'  => 'Lightweight, privacy-friendly share buttons that match your site, not theirs.',
				'badge'        => 'Coming soon',
				'badge_tone'   => 'neutral',
				'cta'          => 'Get notified',
				'install_url'  => 'https://plugora.dev/coming-soon/social-share',
				'learn_url'    => 'https://plugora.dev/coming-soon/social-share',
				'priority'     => 40,
			],
			[
				'slug'         => 'plugora-woo-receipts',
				'name'         => 'Plugora Receipts for WooCommerce',
				'description'  => 'Beautiful, branded order receipts and invoices delivered with every WooCommerce sale.',
				'badge'        => 'Coming soon',
				'badge_tone'   => 'neutral',
				'cta'          => 'Get notified',
				'install_url'  => 'https://plugora.dev/coming-soon/woo-receipts',
				'learn_url'    => 'https://plugora.dev/coming-soon/woo-receipts',
				'requires'     => [ 'woocommerce' ],
				'priority'     => 30,
			],
		];
	}

	// ---------------------------------------------------------------------
	// Filtering / ranking
	// ---------------------------------------------------------------------

	private static function filter_and_rank( array $catalog, string $current_slug ) {
		$installed_slugs = self::installed_plugora_slugs();
		$context_active  = self::active_context_plugins();

		$out = [];
		foreach ( $catalog as $card ) {
			// Skip the host plugin and any other Plugora plugin already present.
			if ( $card['slug'] === $current_slug ) continue;
			if ( in_array( $card['slug'], $installed_slugs, true ) ) continue;
			if ( ! empty( $card['class_marker'] ) && class_exists( $card['class_marker'] ) ) continue;

			// Only show "requires X" cards when the prerequisite plugin is active
			// (e.g. don't push CF7 Mailchimp to sites without CF7).
			if ( ! empty( $card['requires'] ) ) {
				$requires_met = false;
				foreach ( (array) $card['requires'] as $needle ) {
					if ( in_array( $needle, $context_active, true ) ) { $requires_met = true; break; }
				}
				if ( ! $requires_met ) continue;
			}

			// Smart boost: if the prerequisite is active, push the card up.
			$score = (int) ( $card['priority'] ?? 0 );
			if ( ! empty( $card['requires'] ) ) $score += 50;
			$card['_score'] = $score;
			$out[] = $card;
		}

		usort( $out, function ( $a, $b ) {
			return ( $b['_score'] ?? 0 ) <=> ( $a['_score'] ?? 0 );
		} );

		return $out;
	}

	private static function installed_plugora_slugs() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slugs = [];
		foreach ( get_plugins() as $file => $data ) {
			$dir = strtok( $file, '/' );
			if ( strpos( $dir, 'plugora-' ) === 0 ) {
				$slugs[] = sanitize_key( $dir );
			}
		}
		return $slugs;
	}

	/**
	 * Detect helpful context plugins so we can show or hide cards intelligently.
	 * Returns short tokens (e.g. 'wpcf7', 'woocommerce').
	 */
	private static function active_context_plugins() {
		$tokens = [];
		if ( class_exists( 'WPCF7' ) || defined( 'WPCF7_VERSION' ) )       $tokens[] = 'wpcf7';
		if ( class_exists( 'WooCommerce' ) )                                $tokens[] = 'woocommerce';
		if ( defined( 'ELEMENTOR_VERSION' ) )                               $tokens[] = 'elementor';
		if ( class_exists( 'GFForms' ) )                                    $tokens[] = 'gravityforms';
		if ( defined( 'WPSEO_VERSION' ) )                                   $tokens[] = 'yoast';
		return $tokens;
	}

	// ---------------------------------------------------------------------
	// Review + Request prompts
	// ---------------------------------------------------------------------

	private static function render_review_prompt( string $current_slug ) {
		if ( get_user_meta( get_current_user_id(), self::REVIEW_META . '_' . $current_slug, true ) === '1' ) {
			return;
		}
		?>
		<div class="plugora-eco-callout plugora-eco-callout--review">
			<div class="plugora-eco-callout__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
			</div>
			<div class="plugora-eco-callout__body">
				<strong>Enjoying this plugin?</strong>
				<p>A 5-star review on WordPress.org takes 30 seconds and means the world to a small team.</p>
			</div>
			<div class="plugora-eco-callout__actions">
				<a class="plugora-eco-btn plugora-eco-btn--primary" href="https://wordpress.org/support/plugin/<?php echo esc_attr( $current_slug ?: 'plugora' ); ?>/reviews/#new-post" target="_blank" rel="noreferrer">Leave a review</a>
				<button type="button" class="plugora-eco-btn plugora-eco-btn--link" data-plugora-eco-review-dismiss data-slug="<?php echo esc_attr( $current_slug ); ?>">Maybe later</button>
			</div>
		</div>
		<?php
	}

	private static function render_request_prompt() {
		?>
		<div class="plugora-eco-callout plugora-eco-callout--request">
			<div class="plugora-eco-callout__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-12V5l-8-3-8 3v5c0 8 8 12 8 12z"/><path d="M9 12l2 2 4-4"/></svg>
			</div>
			<div class="plugora-eco-callout__body">
				<strong>Need a plugin we don't have?</strong>
				<p>Tell us what you're missing — we ship requests as standalone Plugora plugins.</p>
			</div>
			<div class="plugora-eco-callout__actions">
				<a class="plugora-eco-btn plugora-eco-btn--ghost" href="https://plugora.dev/request" target="_blank" rel="noreferrer">Request a plugin</a>
			</div>
		</div>
		<?php
	}

	// ---------------------------------------------------------------------
	// AJAX handlers (dismiss)
	// ---------------------------------------------------------------------

	public static function ajax_dismiss() {
		check_ajax_referer( 'plugora_ecosystem', 'nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'auth' ], 401 );
		update_user_meta( get_current_user_id(), self::DISMISS_META, '1' );
		wp_send_json_success();
	}

	public static function ajax_dismiss_review() {
		check_ajax_referer( 'plugora_ecosystem', 'nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'auth' ], 401 );
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : 'plugora';
		update_user_meta( get_current_user_id(), self::REVIEW_META . '_' . $slug, '1' );
		wp_send_json_success();
	}

	public static function register_hooks() {
		if ( did_action( 'plugora_ecosystem_hooks_registered' ) ) return;
		add_action( 'wp_ajax_plugora_ecosystem_dismiss',        [ __CLASS__, 'ajax_dismiss' ] );
		add_action( 'wp_ajax_plugora_ecosystem_dismiss_review', [ __CLASS__, 'ajax_dismiss_review' ] );
		do_action( 'plugora_ecosystem_hooks_registered' );
	}

	// ---------------------------------------------------------------------
	// Inline assets (printed once per request, no extra HTTP)
	// ---------------------------------------------------------------------

	private static function default_icon() {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7v10l9 5 9-5V7z"/><path d="M3 7l9 5 9-5"/><path d="M12 22V12"/></svg>';
	}

	private static function print_assets_once() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook name is a class constant prefixed with "plugora_".
		if ( did_action( self::ASSETS_HOOK ) ) return;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook name is a class constant prefixed with "plugora_".
		do_action( self::ASSETS_HOOK );
		$nonce = wp_create_nonce( 'plugora_ecosystem' );
		?>
		<style id="plugora-eco-css">
			/* =========================================================
			   Plugora Ecosystem — Design System v2
			   Surface-aware: works on light WP admin AND dark Plugora
			   surfaces. Honors `data-theme="dark"` or
			   `prefers-color-scheme: dark`. All scoped under .plugora-eco
			   to avoid leaking into the host page.
			   ========================================================= */
			.plugora-eco{
				/* brand */
				--peco-brand:#7c5cff;
				--peco-brand-strong:#6b4def;
				--peco-brand-soft:rgba(124,92,255,.12);
				--peco-pink:#ec4899;
				--peco-amber:#f59e0b;
				--peco-success:#10b981;

				/* light surface (default) */
				--peco-surface:#ffffff;
				--peco-surface-2:#fafafa;
				--peco-card:#ffffff;
				--peco-card-hover:#fbfaff;
				--peco-fg:#0f172a;
				--peco-fg-muted:#5b6472;
				--peco-fg-subtle:#8a93a2;
				--peco-border:rgba(15,23,42,.10);
				--peco-border-strong:rgba(15,23,42,.18);
				--peco-shadow:0 1px 0 rgba(15,23,42,.03), 0 12px 32px -20px rgba(15,23,42,.20);
				--peco-shadow-card:0 1px 2px rgba(15,23,42,.04), 0 6px 18px -10px rgba(15,23,42,.12);

				position:relative;
				margin:32px 20px 8px 2px;
				padding:28px 28px 22px;
				background:var(--peco-surface);
				color:var(--peco-fg);
				border:1px solid var(--peco-border);
				border-radius:16px;
				box-shadow:var(--peco-shadow);
				font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;
				overflow:hidden;
			}
			/* Dark surface adaptation */
			@media (prefers-color-scheme: dark){
				.plugora-eco:not([data-theme="light"]){
					--peco-surface:#0f1117;
					--peco-surface-2:#13161f;
					--peco-card:#171a23;
					--peco-card-hover:#1c2030;
					--peco-fg:#f1f3f8;
					--peco-fg-muted:#a3aab8;
					--peco-fg-subtle:#737a8b;
					--peco-border:rgba(255,255,255,.08);
					--peco-border-strong:rgba(255,255,255,.16);
					--peco-shadow:0 1px 0 rgba(0,0,0,.4), 0 24px 48px -28px rgba(0,0,0,.7);
					--peco-shadow-card:0 1px 0 rgba(255,255,255,.03), 0 8px 24px -14px rgba(0,0,0,.6);
				}
			}
			.plugora-eco[data-theme="dark"]{
				--peco-surface:#0f1117;--peco-surface-2:#13161f;--peco-card:#171a23;--peco-card-hover:#1c2030;
				--peco-fg:#f1f3f8;--peco-fg-muted:#a3aab8;--peco-fg-subtle:#737a8b;
				--peco-border:rgba(255,255,255,.08);--peco-border-strong:rgba(255,255,255,.16);
				--peco-shadow:0 1px 0 rgba(0,0,0,.4), 0 24px 48px -28px rgba(0,0,0,.7);
				--peco-shadow-card:0 1px 0 rgba(255,255,255,.03), 0 8px 24px -14px rgba(0,0,0,.6);
			}

			.plugora-eco *{box-sizing:border-box;}

			/* Brand stripe along the top */
			.plugora-eco__bar{
				position:absolute;top:0;left:0;right:0;height:3px;
				background:linear-gradient(90deg,var(--peco-brand) 0%,var(--peco-pink) 60%,var(--peco-amber) 100%);
				opacity:.9;
			}

			/* Header */
			.plugora-eco__header{
				display:flex;justify-content:space-between;align-items:flex-start;
				gap:24px;flex-wrap:wrap;margin-bottom:22px;
			}
			.plugora-eco__heading{min-width:0;}
			.plugora-eco__chip{
				display:inline-flex;align-items:center;gap:7px;
				padding:4px 10px;border-radius:999px;
				background:var(--peco-brand-soft);color:var(--peco-brand);
				font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
			}
			.plugora-eco__chip-dot{
				width:6px;height:6px;border-radius:999px;
				background:var(--peco-brand);box-shadow:0 0 0 3px rgba(124,92,255,.18);
			}
			.plugora-eco__title{
				margin:10px 0 4px;font-size:22px;line-height:1.25;font-weight:600;
				color:var(--peco-fg);letter-spacing:-.01em;
			}
			.plugora-eco__sub{margin:0;color:var(--peco-fg-muted);max-width:62ch;font-size:13.5px;}

			.plugora-eco__header-actions{display:flex;align-items:center;gap:6px;flex-shrink:0;}
			.plugora-eco__link{
				display:inline-flex;align-items:center;gap:4px;
				color:var(--peco-brand);text-decoration:none;font-weight:500;font-size:13px;
				padding:6px 10px;border-radius:8px;transition:background .15s ease,color .15s ease;
			}
			.plugora-eco__link:hover{background:var(--peco-brand-soft);color:var(--peco-brand-strong);}
			.plugora-eco__icon-btn{
				display:inline-grid;place-items:center;width:30px;height:30px;
				background:transparent;border:1px solid transparent;border-radius:8px;
				color:var(--peco-fg-subtle);cursor:pointer;transition:all .15s ease;
			}
			.plugora-eco__icon-btn:hover{background:var(--peco-surface-2);color:var(--peco-fg);border-color:var(--peco-border);}

			/* Grid + cards */
			.plugora-eco__grid{
				display:grid;gap:14px;
				grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
			}
			.plugora-eco-card{
				display:flex;flex-direction:column;
				background:var(--peco-card);
				border:1px solid var(--peco-border);
				border-radius:14px;
				overflow:hidden;
				transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
				box-shadow:var(--peco-shadow-card);
			}
			.plugora-eco-card:hover{
				transform:translateY(-2px);
				background:var(--peco-card-hover);
				border-color:var(--peco-border-strong);
				box-shadow:0 4px 12px -6px rgba(124,92,255,.20), 0 18px 36px -20px rgba(124,92,255,.30);
			}

			.plugora-eco-card__media{
				position:relative;
				padding:18px 18px 0;
				display:flex;justify-content:space-between;align-items:flex-start;
			}
			.plugora-eco-card__icon{
				width:44px;height:44px;display:grid;place-items:center;border-radius:12px;
				background:linear-gradient(135deg,var(--peco-brand) 0%,var(--peco-pink) 100%);
				color:#fff;
				box-shadow:0 6px 14px -6px rgba(124,92,255,.55), inset 0 1px 0 rgba(255,255,255,.25);
			}
			.plugora-eco-card__icon svg{width:22px;height:22px;}

			.plugora-eco-card__badge{
				display:inline-flex;align-items:center;
				font-size:10px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;
				padding:4px 9px;border-radius:999px;
				background:var(--peco-surface-2);color:var(--peco-fg-muted);
				border:1px solid var(--peco-border);
			}
			.plugora-eco-card__badge--primary{background:rgba(124,92,255,.14);color:var(--peco-brand);border-color:rgba(124,92,255,.25);}
			.plugora-eco-card__badge--pink{background:rgba(236,72,153,.14);color:var(--peco-pink);border-color:rgba(236,72,153,.25);}
			.plugora-eco-card__badge--success{background:rgba(16,185,129,.14);color:var(--peco-success);border-color:rgba(16,185,129,.25);}
			.plugora-eco-card__badge--amber{background:rgba(245,158,11,.14);color:var(--peco-amber);border-color:rgba(245,158,11,.25);}

			.plugora-eco-card__body{padding:14px 18px 6px;flex:1;}
			.plugora-eco-card__title{
				margin:0 0 6px;font-size:15px;font-weight:600;line-height:1.3;
				color:var(--peco-fg);letter-spacing:-.005em;
			}
			.plugora-eco-card__desc{
				margin:0;font-size:13px;line-height:1.5;color:var(--peco-fg-muted);
				display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;
			}

			.plugora-eco-card__rating{
				margin-top:10px;display:flex;align-items:center;gap:6px;
				font-size:12px;color:var(--peco-fg-subtle);
			}
			.plugora-eco-card__stars{color:var(--peco-amber);letter-spacing:1px;font-size:13px;}
			.plugora-eco-card__rating-text{color:var(--peco-fg-muted);font-weight:500;}
			.plugora-eco-card__rating-count{color:var(--peco-fg-subtle);font-weight:400;margin-left:2px;}

			.plugora-eco-card__actions{
				margin-top:14px;padding:0 18px 18px;
				display:flex;align-items:center;gap:8px;flex-wrap:wrap;
			}

			/* Buttons (own namespace, never inherits WP .button) */
			.plugora-eco-btn{
				display:inline-flex;align-items:center;justify-content:center;gap:6px;
				font:600 13px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
				padding:9px 14px;border-radius:9px;border:1px solid transparent;
				cursor:pointer;text-decoration:none;
				transition:all .15s ease;white-space:nowrap;
			}
			.plugora-eco-btn--primary{
				background:linear-gradient(180deg,var(--peco-brand) 0%,var(--peco-brand-strong) 100%);
				color:#fff !important;
				box-shadow:0 1px 0 rgba(255,255,255,.18) inset, 0 6px 14px -8px rgba(124,92,255,.6);
			}
			.plugora-eco-btn--primary:hover{transform:translateY(-1px);box-shadow:0 1px 0 rgba(255,255,255,.18) inset, 0 10px 20px -8px rgba(124,92,255,.7);}
			.plugora-eco-btn--ghost{
				background:transparent;color:var(--peco-fg) !important;
				border-color:var(--peco-border-strong);
			}
			.plugora-eco-btn--ghost:hover{background:var(--peco-surface-2);border-color:var(--peco-fg-subtle);}
			.plugora-eco-btn--link{
				background:transparent;border:0;padding:9px 8px;
				color:var(--peco-fg-muted) !important;font-weight:500;
			}
			.plugora-eco-btn--link:hover{color:var(--peco-fg) !important;text-decoration:underline;}

			/* Footer callouts */
			.plugora-eco__footer{
				margin-top:22px;display:grid;gap:12px;
				grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
			}
			.plugora-eco-callout{
				display:flex;align-items:center;gap:14px;
				padding:16px 18px;border-radius:12px;
				background:var(--peco-surface-2);
				border:1px solid var(--peco-border);
			}
			.plugora-eco-callout--review{
				background:linear-gradient(135deg,rgba(124,92,255,.10),rgba(236,72,153,.08));
				border-color:rgba(124,92,255,.24);
			}
			.plugora-eco-callout__icon{
				flex-shrink:0;width:36px;height:36px;display:grid;place-items:center;border-radius:10px;
				background:var(--peco-card);color:var(--peco-brand);
				border:1px solid var(--peco-border);
			}
			.plugora-eco-callout--review .plugora-eco-callout__icon{color:var(--peco-amber);}
			.plugora-eco-callout__body{flex:1;min-width:0;}
			.plugora-eco-callout__body strong{color:var(--peco-fg);font-size:13.5px;font-weight:600;display:block;}
			.plugora-eco-callout__body p{margin:2px 0 0;color:var(--peco-fg-muted);font-size:12.5px;}
			.plugora-eco-callout__actions{display:flex;align-items:center;gap:6px;flex-shrink:0;}

			/* Resource links */
			.plugora-eco__links{
				margin-top:18px;padding-top:16px;
				border-top:1px solid var(--peco-border);
				display:flex;align-items:center;gap:18px;flex-wrap:wrap;
				font-size:12.5px;
			}
			.plugora-eco__links a{
				display:inline-flex;align-items:center;gap:6px;
				color:var(--peco-fg-muted);text-decoration:none;font-weight:500;
				transition:color .15s ease;
			}
			.plugora-eco__links a:hover{color:var(--peco-brand);}
			.plugora-eco__links svg{opacity:.7;}

			/* Empty state */
			.plugora-eco__empty{
				padding:24px;text-align:center;border-radius:12px;
				background:var(--peco-surface-2);border:1px dashed var(--peco-border-strong);
				color:var(--peco-fg-muted);
			}
			.plugora-eco__empty p{margin:0 0 8px;}
			.plugora-eco__empty strong{color:var(--peco-fg);}

			/* Mobile polish */
			@media (max-width: 640px){
				.plugora-eco{padding:22px 18px 18px;border-radius:14px;}
				.plugora-eco__title{font-size:19px;}
				.plugora-eco__header-actions{width:100%;justify-content:flex-start;}
				.plugora-eco-callout{flex-wrap:wrap;}
				.plugora-eco-callout__actions{width:100%;}
			}
		</style>
		<script id="plugora-eco-js">
		(function(){
			var nonce=<?php echo wp_json_encode( $nonce ); ?>;
			var ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			function post(action,extra){
				var body='action='+encodeURIComponent(action)+'&nonce='+encodeURIComponent(nonce);
				if(extra){for(var k in extra){body+='&'+encodeURIComponent(k)+'='+encodeURIComponent(extra[k]);}}
				return fetch(ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body});
			}
			document.addEventListener('click',function(e){
				var d=e.target.closest('[data-plugora-eco-dismiss]');
				if(d){e.preventDefault();var sec=d.closest('.plugora-eco');if(sec){sec.style.opacity='0';sec.style.transition='opacity .2s';setTimeout(function(){sec.remove();},200);}post('plugora_ecosystem_dismiss');return;}
				var r=e.target.closest('[data-plugora-eco-review-dismiss]');
				if(r){e.preventDefault();var c=r.closest('.plugora-eco-callout');if(c) c.remove();post('plugora_ecosystem_dismiss_review',{slug:r.getAttribute('data-slug')||''});}
			});
		})();
		</script>
		<?php
	}
}

Plugora_Ecosystem::register_hooks();

endif;
