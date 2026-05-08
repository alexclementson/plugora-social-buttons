<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralised sanitisation + validation for the settings option.
 * Keeps Plugora_SB_Settings::sanitize() readable and re-usable.
 */
class Plugora_SB_Sanitizer {

	const POSITIONS = [ 'bottom-right', 'bottom-left', 'middle-right', 'middle-left' ];
	const LAYOUTS   = [ 'vertical', 'horizontal', 'expandable', 'popup' ];
	const TRIGGERS  = [ 'hover', 'click' ];

	public static function sanitize( $input, array $defaults ) {
		if ( ! is_array( $input ) ) return $defaults;

		$out = $defaults;

		// Buttons.
		$out['buttons'] = self::sanitize_buttons( $input['buttons'] ?? [] );

		// Display.
		$out['position']      = in_array( $input['position'] ?? '', self::POSITIONS, true ) ? $input['position'] : $defaults['position'];
		$out['layout']        = in_array( $input['layout']   ?? '', self::LAYOUTS,   true ) ? $input['layout']   : $defaults['layout'];
		$out['trigger']       = in_array( $input['trigger']  ?? '', self::TRIGGERS,  true ) ? $input['trigger']  : $defaults['trigger'];

		$out['icon_size']     = self::clamp_int( $input['icon_size']     ?? null, 16, 80,  $defaults['icon_size'] );
		$out['spacing']       = self::clamp_int( $input['spacing']       ?? null, 0,  40,  $defaults['spacing'] );
		$out['border_radius'] = self::clamp_int( $input['border_radius'] ?? null, 0,  50,  $defaults['border_radius'] );
		$out['shadow']        = self::clamp_int( $input['shadow']        ?? null, 0,  4,   $defaults['shadow'] );
		$out['z_index']       = self::clamp_int( $input['z_index']       ?? null, 0,  2147483647, $defaults['z_index'] );

		$out['show_labels']     = self::tribool( $input['show_labels']     ?? 0 );
		$out['show_on_desktop'] = self::tribool( $input['show_on_desktop'] ?? 0 );
		$out['show_on_tablet']  = self::tribool( $input['show_on_tablet']  ?? 0 );
		$out['show_on_mobile']  = self::tribool( $input['show_on_mobile']  ?? 0 );

		// Visibility.
		$out['visibility_mode'] = in_array( $input['visibility_mode'] ?? '', [ 'all', 'show_only', 'hide_on' ], true )
			? $input['visibility_mode']
			: $defaults['visibility_mode'];

		$out['visibility_ids']  = self::sanitize_id_list( $input['visibility_ids'] ?? '' );

		$out['hide_on_posts']    = self::tribool( $input['hide_on_posts']    ?? 0 );
		$out['hide_on_pages']    = self::tribool( $input['hide_on_pages']    ?? 0 );
		$out['hide_on_archives'] = self::tribool( $input['hide_on_archives'] ?? 0 );
		$out['hide_on_woo']      = self::tribool( $input['hide_on_woo']      ?? 0 );

		// Scheduling (premium).
		$out['schedule_enabled'] = self::tribool( $input['schedule_enabled'] ?? 0 );
		$out['schedule_start']   = self::sanitize_datetime( $input['schedule_start'] ?? '' );
		$out['schedule_end']     = self::sanitize_datetime( $input['schedule_end']   ?? '' );

		return $out;
	}

	private static function sanitize_buttons( $raw ) {
		if ( ! is_array( $raw ) ) return [];
		$clean = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) continue;
			$key = sanitize_key( $row['platform'] ?? '' );
			if ( ! $key || ! Plugora_SB_Platforms::exists( $key ) ) continue;

			$clean[] = [
				'platform' => $key,
				'value'    => self::sanitize_value_for( $key, (string) ( $row['value'] ?? '' ) ),
				'label'    => sanitize_text_field( $row['label'] ?? '' ),
				'color'    => self::sanitize_hex( $row['color'] ?? '' ),
				'enabled'  => empty( $row['enabled'] ) ? 0 : 1,
			];
		}
		return $clean;
	}

	private static function sanitize_value_for( $platform_key, $value ) {
		$p = Plugora_SB_Platforms::get( $platform_key );
		if ( ! $p ) return '';
		$value = trim( wp_unslash( $value ) );
		switch ( $p['value_type'] ) {
			case 'email':
				return sanitize_email( $value );
			case 'tel':
			case 'sms':
			case 'whatsapp':
				return preg_replace( '/[^\d+\-\s()]/', '', $value );
			case 'url':
			default:
				return esc_url_raw( $value );
		}
	}

	private static function sanitize_hex( $hex ) {
		$hex = trim( (string) $hex );
		if ( $hex === '' ) return '';
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex, $m ) ) {
			return '#' . ltrim( strtolower( $m[1] ), '#' );
		}
		return '';
	}

	private static function sanitize_id_list( $raw ) {
		if ( is_array( $raw ) ) $raw = implode( ',', $raw );
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw ) ) );
		return implode( ',', array_unique( $ids ) );
	}

	private static function sanitize_datetime( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) return '';
		// Accepts datetime-local: YYYY-MM-DDTHH:MM
		$ts = strtotime( $raw );
		return $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '';
	}

	private static function clamp_int( $value, $min, $max, $fallback ) {
		if ( $value === null || $value === '' ) return $fallback;
		$n = (int) $value;
		if ( $n < $min ) return $min;
		if ( $n > $max ) return $max;
		return $n;
	}

	private static function tribool( $v ) { return empty( $v ) ? 0 : 1; }
}
