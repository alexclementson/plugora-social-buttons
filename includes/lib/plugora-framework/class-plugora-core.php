<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugora Core — shared top-level admin menu + plugin registry.
 *
 * Loaded by every Plugora plugin. The first plugin to load wins (guarded by
 * class_exists), so we get a single "Plugora" admin bucket no matter how many
 * Plugora plugins are installed.
 *
 * Each plugin should call:
 *   Plugora_Core::register_plugin( [
 *     'slug'        => 'plugora-cf7-mailchimp',
 *     'label'       => 'CF7 Mailchimp',
 *     'description' => 'Send Contact Form 7 submissions to Mailchimp.',
 *     'callback'    => [ Plugora_CF7MC_Settings::class, 'render_page' ],
 *     'capability'  => 'manage_options',
 *   ] );
 *
 * It will:
 *   - Add a top-level "Plugora" menu (only once, no matter how many plugins register)
 *   - Add a submenu entry for the plugin under Plugora
 *   - Show every registered plugin on the Plugora landing page
 */
if ( ! class_exists( 'Plugora_Core' ) ) :

class Plugora_Core {
	const MENU_SLUG = 'plugora';

	/** @var array<string, array> */
	private static $plugins = [];

	/**
	 * Register a Plugora plugin so it appears under the shared menu.
	 *
	 * @param array $args {
	 *   @type string   $slug        Unique submenu slug (e.g. 'plugora-cf7-mailchimp').
	 *   @type string   $label       Human label shown in the submenu and landing page.
	 *   @type string   $description Optional short description for the landing page.
	 *   @type callable $callback    Render callback for the submenu page.
	 *   @type string   $capability  Required capability (default: manage_options).
	 *   @type int      $position    Optional submenu position.
	 * }
	 */
	public static function register_plugin( array $args ) {
		$slug = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
		if ( ! $slug || empty( $args['callback'] ) ) return;

		self::$plugins[ $slug ] = wp_parse_args( $args, [
			'slug'        => $slug,
			'label'       => $slug,
			'description' => '',
			'capability'  => 'manage_options',
			'position'    => null,
		] );

		// Hook the menu registration once.
		if ( ! has_action( 'admin_menu', [ __CLASS__, 'register_menu' ] ) ) {
			add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 9 );
		}
	}

	public static function get_plugins() {
		return self::$plugins;
	}

	public static function register_menu() {
		global $admin_page_hooks;

		// Top-level Plugora bucket (only register once).
		if ( empty( $admin_page_hooks[ self::MENU_SLUG ] ) ) {
			add_menu_page(
				'Plugora',
				'Plugora',
				'manage_options',
				self::MENU_SLUG,
				[ __CLASS__, 'render_landing' ],
				'dashicons-screenoptions',
				58
			);
			// Rename the auto-generated first submenu to "Plugora" (overview).
			add_submenu_page(
				self::MENU_SLUG,
				'Plugora',
				'Plugora',
				'manage_options',
				self::MENU_SLUG,
				[ __CLASS__, 'render_landing' ]
			);
		}

		// Sort by label for consistent display.
		$plugins = self::$plugins;
		uasort( $plugins, function ( $a, $b ) {
			return strcasecmp( (string) $a['label'], (string) $b['label'] );
		} );

		foreach ( $plugins as $p ) {
			add_submenu_page(
				self::MENU_SLUG,
				$p['label'],
				$p['label'],
				$p['capability'],
				$p['slug'],
				$p['callback'],
				$p['position']
			);
		}
	}

	public static function render_landing() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$plugins = self::$plugins;
		uasort( $plugins, function ( $a, $b ) {
			return strcasecmp( (string) $a['label'], (string) $b['label'] );
		} );
		?>
		<div class="wrap">
			<h1>Plugora</h1>
			<p>Manage all your Plugora plugins from one place.</p>

			<?php if ( empty( $plugins ) ) : ?>
				<p><em>No Plugora plugins are registered yet.</em></p>
			<?php else : ?>
				<div class="plugora-card-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:16px;">
					<?php foreach ( $plugins as $p ) :
						$url = admin_url( 'admin.php?page=' . $p['slug'] );
						?>
						<div class="plugora-card" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;display:flex;flex-direction:column;gap:8px;">
							<h2 style="margin:0;font-size:16px;"><?php echo esc_html( $p['label'] ); ?></h2>
							<?php if ( ! empty( $p['description'] ) ) : ?>
								<p style="margin:0;color:#646970;flex:1;"><?php echo esc_html( $p['description'] ); ?></p>
							<?php endif; ?>
							<p style="margin:0;">
								<a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Open settings</a>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		if ( class_exists( 'Plugora_Ecosystem' ) ) {
			Plugora_Ecosystem::render( [
				'current_slug' => '',
				'context'      => 'landing',
				'limit'        => 6,
				'show_review'  => false,
			] );
		}
	}
}

endif;
