<?php
/**
 * Plugora Floating Social Buttons — clean uninstall.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$plugora_sb_options = [
	'plugora_sb_settings',
	'plugora_sb_version',
	'plugora_sb_license_key',
	'plugora_sb_license_state',
];
foreach ( $plugora_sb_options as $plugora_sb_opt ) {
	delete_option( $plugora_sb_opt );
}

delete_transient( 'plugora_sb_license_recheck' );
