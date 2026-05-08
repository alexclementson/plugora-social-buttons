<?php
/**
 * Plugin Name: Plugora Floating Social Buttons
 * Description: Modern floating social media bar for WordPress — Facebook, X, WhatsApp, Instagram, LinkedIn, YouTube, TikTok, email, phone and custom links. Drag-to-reorder, flexible visibility rules and a polished Plugora admin UI.
 * Version:     0.1.4
 * Author:      Plugora
 * Author URI:  https://plugora.dev
 * License:     GPL-2.0-or-later
 * Text Domain: plugora-social-buttons
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PLUGORA_SB_VERSION', '0.1.4' );
define( 'PLUGORA_SB_FILE',    __FILE__ );
define( 'PLUGORA_SB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PLUGORA_SB_URL',     plugin_dir_url( __FILE__ ) );
define( 'PLUGORA_SB_SLUG',    'plugora-social-buttons' );
define( 'PLUGORA_SB_API',     'https://kmsqtusutpknswtdzclw.supabase.co/functions/v1/social-buttons-license-validate' );
define( 'PLUGORA_SB_BUY_URL', 'https://plugora.dev/buy/social-buttons' );

// Plugora shared framework (synced from /plugora-framework via scripts/sync-plugora-framework.sh).
require_once PLUGORA_SB_DIR . 'includes/lib/plugora-framework/class-plugora-core.php';
require_once PLUGORA_SB_DIR . 'includes/lib/plugora-framework/class-ecosystem.php';

require_once PLUGORA_SB_DIR . 'includes/class-installer.php';
require_once PLUGORA_SB_DIR . 'includes/class-license.php';
require_once PLUGORA_SB_DIR . 'includes/class-platforms.php';
require_once PLUGORA_SB_DIR . 'includes/class-sanitizer.php';
require_once PLUGORA_SB_DIR . 'includes/class-visibility.php';
require_once PLUGORA_SB_DIR . 'includes/class-settings.php';
require_once PLUGORA_SB_DIR . 'includes/class-admin.php';
require_once PLUGORA_SB_DIR . 'includes/class-frontend.php';

if ( ! function_exists( 'plugora_sb_is_premium' ) ) {
	/**
	 * Premium gating.
	 *
	 * Premium controls (scheduling, advanced visibility, unlimited custom buttons,
	 * extra layouts) are ON by default so the plugin always shows the full
	 * Plugora experience. Opt out with:
	 *
	 *   add_filter( 'plugora_sb_is_premium', '__return_false' );
	 *   // or define( 'PLUGORA_SB_FORCE_FREE', true );
	 */
	function plugora_sb_is_premium() {
		if ( defined( 'PLUGORA_SB_FORCE_FREE' ) && PLUGORA_SB_FORCE_FREE ) {
			return false;
		}
		$licensed = class_exists( 'Plugora_SB_License' ) && Plugora_SB_License::is_active();
		return (bool) apply_filters( 'plugora_sb_is_premium', true, $licensed );
	}
}

register_activation_hook( __FILE__,  [ 'Plugora_SB_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Plugora_SB_Installer', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'Plugora_SB_Installer', 'maybe_upgrade' ] );
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'plugora-social-buttons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Register with the shared Plugora top-level menu.
Plugora_Core::register_plugin( [
	'slug'        => PLUGORA_SB_SLUG,
	'label'       => __( 'Social Buttons', 'plugora-social-buttons' ),
	'description' => __( 'Floating social media bar with platform brand colours, drag-to-reorder and flexible visibility rules.', 'plugora-social-buttons' ),
	'callback'    => [ 'Plugora_SB_Settings', 'render_page' ],
] );

add_action( 'admin_init',            [ 'Plugora_SB_Settings', 'register_settings' ] );
add_action( 'admin_enqueue_scripts', [ 'Plugora_SB_Admin', 'enqueue' ] );

// Front-end render + asset enqueue.
add_action( 'wp_enqueue_scripts', [ 'Plugora_SB_Frontend', 'enqueue' ] );
add_action( 'wp_footer',          [ 'Plugora_SB_Frontend', 'render' ], 99 );
