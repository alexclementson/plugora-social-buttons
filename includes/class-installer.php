<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Activation / upgrade routines for Plugora Floating Social Buttons.
 * No custom DB tables — everything lives in a single options row.
 */
class Plugora_SB_Installer {
	public static function activate() {
		// Seed defaults so the front end has something sensible to render.
		if ( get_option( Plugora_SB_Settings::OPT_KEY ) === false ) {
			add_option( Plugora_SB_Settings::OPT_KEY, Plugora_SB_Settings::defaults() );
		}
		add_option( 'plugora_sb_version', PLUGORA_SB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'plugora_sb_version' ) !== PLUGORA_SB_VERSION ) {
			update_option( 'plugora_sb_version', PLUGORA_SB_VERSION );
		}
	}

	public static function deactivate() {
		// Keep settings on deactivate; uninstall.php handles destructive cleanup.
	}
}
