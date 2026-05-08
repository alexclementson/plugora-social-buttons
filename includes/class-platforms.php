<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central registry of all supported social platforms.
 *
 * Each platform definition includes:
 *   - label        : human-readable name (translatable)
 *   - brand_color  : official brand colour (used by default on the front end)
 *   - icon         : inline SVG markup
 *   - value_type   : 'url' | 'tel' | 'email' | 'whatsapp' | 'sms'
 *   - placeholder  : example value to show in the admin
 *   - href_format  : printf format used to build the rendered href
 *   - new_tab      : whether the link opens in a new tab by default
 *
 * Add new platforms by filtering `plugora_sb_platforms`.
 */
class Plugora_SB_Platforms {

	private static $cache = null;

	public static function all() {
		if ( self::$cache !== null ) return self::$cache;

		$base = [
			'facebook' => [
				'label'       => __( 'Facebook', 'plugora-social-buttons' ),
				'brand_color' => '#1877F2',
				'icon'        => self::svg( 'facebook' ),
				'value_type'  => 'url',
				'placeholder' => 'https://facebook.com/your-page',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'x' => [
				'label'       => __( 'X (Twitter)', 'plugora-social-buttons' ),
				'brand_color' => '#000000',
				'icon'        => self::svg( 'x' ),
				'value_type'  => 'url',
				'placeholder' => 'https://x.com/your-handle',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'instagram' => [
				'label'       => __( 'Instagram', 'plugora-social-buttons' ),
				'brand_color' => '#E4405F',
				'icon'        => self::svg( 'instagram' ),
				'value_type'  => 'url',
				'placeholder' => 'https://instagram.com/your-handle',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'linkedin' => [
				'label'       => __( 'LinkedIn', 'plugora-social-buttons' ),
				'brand_color' => '#0A66C2',
				'icon'        => self::svg( 'linkedin' ),
				'value_type'  => 'url',
				'placeholder' => 'https://linkedin.com/company/your-company',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'youtube' => [
				'label'       => __( 'YouTube', 'plugora-social-buttons' ),
				'brand_color' => '#FF0000',
				'icon'        => self::svg( 'youtube' ),
				'value_type'  => 'url',
				'placeholder' => 'https://youtube.com/@your-channel',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'tiktok' => [
				'label'       => __( 'TikTok', 'plugora-social-buttons' ),
				'brand_color' => '#010101',
				'icon'        => self::svg( 'tiktok' ),
				'value_type'  => 'url',
				'placeholder' => 'https://tiktok.com/@your-handle',
				'href_format' => '%s',
				'new_tab'     => true,
			],
			'whatsapp' => [
				'label'       => __( 'WhatsApp', 'plugora-social-buttons' ),
				'brand_color' => '#25D366',
				'icon'        => self::svg( 'whatsapp' ),
				'value_type'  => 'whatsapp',
				'placeholder' => '+15551234567',
				'href_format' => 'https://wa.me/%s',
				'new_tab'     => true,
			],
			'phone' => [
				'label'       => __( 'Phone', 'plugora-social-buttons' ),
				'brand_color' => '#0EA5E9',
				'icon'        => self::svg( 'phone' ),
				'value_type'  => 'tel',
				'placeholder' => '+15551234567',
				'href_format' => 'tel:%s',
				'new_tab'     => false,
			],
			'email' => [
				'label'       => __( 'Email', 'plugora-social-buttons' ),
				'brand_color' => '#6366F1',
				'icon'        => self::svg( 'email' ),
				'value_type'  => 'email',
				'placeholder' => 'hello@example.com',
				'href_format' => 'mailto:%s',
				'new_tab'     => false,
			],
			'sms' => [
				'label'       => __( 'SMS', 'plugora-social-buttons' ),
				'brand_color' => '#22C55E',
				'icon'        => self::svg( 'sms' ),
				'value_type'  => 'sms',
				'placeholder' => '+15551234567',
				'href_format' => 'sms:%s',
				'new_tab'     => false,
			],
			'custom' => [
				'label'       => __( 'Custom link', 'plugora-social-buttons' ),
				'brand_color' => '#111827',
				'icon'        => self::svg( 'custom' ),
				'value_type'  => 'url',
				'placeholder' => 'https://example.com',
				'href_format' => '%s',
				'new_tab'     => true,
			],
		];

		self::$cache = (array) apply_filters( 'plugora_sb_platforms', $base );
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function exists( $key ) {
		return (bool) self::get( $key );
	}

	/**
	 * Build the actual href for a button given a stored value.
	 * Returns '' if the value is empty or invalid.
	 */
	public static function build_href( $platform_key, $value ) {
		$p = self::get( $platform_key );
		if ( ! $p || $value === '' ) return '';

		switch ( $p['value_type'] ) {
			case 'whatsapp':
				// Strip everything except digits.
				$digits = preg_replace( '/\D+/', '', (string) $value );
				return $digits ? sprintf( $p['href_format'], $digits ) : '';
			case 'tel':
			case 'sms':
				$cleaned = preg_replace( '/[^\d+]/', '', (string) $value );
				return $cleaned ? sprintf( $p['href_format'], $cleaned ) : '';
			case 'email':
				return is_email( $value ) ? sprintf( $p['href_format'], sanitize_email( $value ) ) : '';
			case 'url':
			default:
				$url = esc_url_raw( $value );
				return $url ? sprintf( $p['href_format'], $url ) : '';
		}
	}

	/* ------------------------------------------------------------------
	 * Inline SVG icons — single colour so we can recolour with currentColor.
	 * 24×24 viewport, no external dependencies.
	 * ------------------------------------------------------------------ */
	private static function svg( $key ) {
		$icons = [
			'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.51 1.49-3.9 3.78-3.9 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>',
			'x'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2H21l-6.52 7.45L22 22h-6.27l-4.91-6.42L5.1 22H2.34l6.97-7.96L2 2h6.41l4.43 5.86L18.244 2Zm-1.1 18h1.69L7.93 4H6.13l11.014 16Z"/></svg>',
			'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
			'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5A2.5 2.5 0 1 1 5 8.5a2.5 2.5 0 0 1-.02-5ZM3 9.75h4V21H3V9.75ZM9.5 9.75h3.83v1.54h.05c.53-1 1.84-2.05 3.79-2.05 4.05 0 4.8 2.66 4.8 6.13V21h-4v-4.93c0-1.18-.02-2.7-1.65-2.7-1.65 0-1.9 1.29-1.9 2.61V21h-4V9.75Z"/></svg>',
			'youtube'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23 12s0-3.6-.46-5.32a2.78 2.78 0 0 0-1.96-1.96C18.86 4.25 12 4.25 12 4.25s-6.86 0-8.58.47A2.78 2.78 0 0 0 1.46 6.68C1 8.4 1 12 1 12s0 3.6.46 5.32a2.78 2.78 0 0 0 1.96 1.96c1.72.47 8.58.47 8.58.47s6.86 0 8.58-.47a2.78 2.78 0 0 0 1.96-1.96C23 15.6 23 12 23 12ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>',
			'tiktok'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.5 3a5.5 5.5 0 0 0 5 5v3a8.5 8.5 0 0 1-5-1.62V15a6 6 0 1 1-6-6c.34 0 .67.03 1 .09v3.13a3 3 0 1 0 2 2.83V3h3Z"/></svg>',
			'whatsapp'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.93 11.93 0 0 0 12 0C5.37 0 0 5.37 0 12c0 2.11.55 4.17 1.6 5.99L0 24l6.18-1.62A11.94 11.94 0 0 0 12 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52ZM12 22a9.9 9.9 0 0 1-5.06-1.39l-.36-.21-3.67.96.98-3.58-.24-.37A9.94 9.94 0 1 1 22 12c0 5.52-4.48 10-10 10Zm5.43-7.55c-.3-.15-1.76-.87-2.04-.97-.27-.1-.47-.15-.66.15-.2.3-.76.97-.93 1.16-.17.2-.34.22-.63.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.34.45-.51.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.51-.07-.15-.66-1.6-.9-2.18-.24-.58-.49-.5-.66-.5-.17 0-.37-.02-.57-.02-.2 0-.5.07-.76.37s-1.01.99-1.01 2.42c0 1.43 1.04 2.81 1.18 3 .15.2 2.04 3.12 4.95 4.37 2.91 1.25 2.91.83 3.43.78.52-.05 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/></svg>',
			'phone'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.62 10.79a15.46 15.46 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.05-.24c1.16.39 2.4.6 3.66.6a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.27.21 2.5.6 3.66a1 1 0 0 1-.25 1.05l-2.23 2.08Z"/></svg>',
			'email'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
			'sms'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>',
			'custom'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>',
		];
		return $icons[ $key ] ?? '';
	}
}
