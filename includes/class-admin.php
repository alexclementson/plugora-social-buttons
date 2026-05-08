<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin asset loader for Plugora Floating Social Buttons.
 */
class Plugora_SB_Admin {

	public static function enqueue( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check on the current admin page slug.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== Plugora_SB_Settings::PAGE ) return;

		wp_enqueue_style(
			'plugora-sb-admin',
			PLUGORA_SB_URL . 'assets/admin.css',
			[],
			PLUGORA_SB_VERSION
		);
		wp_enqueue_script(
			'plugora-sb-admin',
			PLUGORA_SB_URL . 'assets/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			PLUGORA_SB_VERSION,
			true
		);

		// Pass platform data + defaults to the admin JS for the live preview / row template.
		$platforms = [];
		foreach ( Plugora_SB_Platforms::all() as $key => $p ) {
			$platforms[ $key ] = [
				'label'       => $p['label'],
				'brand_color' => $p['brand_color'],
				'icon'        => $p['icon'],
				'placeholder' => $p['placeholder'],
			];
		}

		wp_localize_script( 'plugora-sb-admin', 'PlugoraSB', [
			'platforms' => $platforms,
			'defaults'  => Plugora_SB_Settings::defaults(),
			'i18n'      => [
				'remove_confirm' => __( 'Remove this button?', 'plugora-social-buttons' ),
				'reset_confirm'  => __( 'Reset every setting to its default value?', 'plugora-social-buttons' ),
			],
		] );
	}
}
