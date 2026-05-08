<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Decides whether the floating bar should render on the current request.
 * Pure logic — no output. Filters allow third-party overrides.
 */
class Plugora_SB_Visibility {

	/**
	 * @param array $opts Sanitised options array.
	 * @return bool
	 */
	public static function should_render( array $opts ) {
		$decision = self::evaluate( $opts );
		return (bool) apply_filters( 'plugora_sb_should_render', $decision, $opts );
	}

	private static function evaluate( array $opts ) {
		// Never render in the admin or feeds.
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) return false;

		// Need at least one enabled button with a usable href.
		if ( ! self::has_visible_buttons( $opts['buttons'] ?? [] ) ) return false;

		// Device-level hide rules (server-side guess; CSS handles the precise breakpoints too).
		if ( wp_is_mobile() ) {
			if ( empty( $opts['show_on_mobile'] ) && empty( $opts['show_on_tablet'] ) ) return false;
		} else {
			if ( empty( $opts['show_on_desktop'] ) ) return false;
		}

		// Type-based hide.
		if ( ! empty( $opts['hide_on_posts'] )    && is_singular( 'post' ) )            return false;
		if ( ! empty( $opts['hide_on_pages'] )    && is_page() )                        return false;
		if ( ! empty( $opts['hide_on_archives'] ) && ( is_archive() || is_home() || is_search() ) ) return false;
		if ( ! empty( $opts['hide_on_woo'] )      && function_exists( 'is_woocommerce' ) && is_woocommerce() ) return false;

		// Page-level allow/deny lists.
		$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $opts['visibility_ids'] ?? '' ) ) ) );
		if ( $ids ) {
			$current = is_singular() ? (int) get_queried_object_id() : 0;
			if ( $opts['visibility_mode'] === 'show_only' ) {
				if ( ! $current || ! in_array( $current, $ids, true ) ) return false;
			} elseif ( $opts['visibility_mode'] === 'hide_on' ) {
				if ( $current && in_array( $current, $ids, true ) ) return false;
			}
		}

		// Scheduling (premium).
		if ( ! empty( $opts['schedule_enabled'] ) && plugora_sb_is_premium() ) {
			$now = time();
			if ( ! empty( $opts['schedule_start'] ) && strtotime( $opts['schedule_start'] . ' UTC' ) > $now ) return false;
			if ( ! empty( $opts['schedule_end'] )   && strtotime( $opts['schedule_end']   . ' UTC' ) < $now ) return false;
		}

		return true;
	}

	private static function has_visible_buttons( $buttons ) {
		if ( ! is_array( $buttons ) ) return false;
		foreach ( $buttons as $b ) {
			if ( empty( $b['enabled'] ) ) continue;
			$href = Plugora_SB_Platforms::build_href( $b['platform'] ?? '', $b['value'] ?? '' );
			if ( $href ) return true;
		}
		return false;
	}
}
