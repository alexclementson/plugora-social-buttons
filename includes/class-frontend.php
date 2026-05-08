<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Front-end renderer + asset enqueue.
 */
class Plugora_SB_Frontend {

	private static $should_render = null;

	public static function should_render() {
		if ( self::$should_render !== null ) return self::$should_render;
		$opts = Plugora_SB_Settings::get();
		self::$should_render = Plugora_SB_Visibility::should_render( $opts );
		return self::$should_render;
	}

	public static function enqueue() {
		if ( ! self::should_render() ) return;
		wp_enqueue_style(
			'plugora-sb',
			PLUGORA_SB_URL . 'assets/frontend.css',
			[],
			PLUGORA_SB_VERSION
		);
		wp_enqueue_script(
			'plugora-sb',
			PLUGORA_SB_URL . 'assets/frontend.js',
			[],
			PLUGORA_SB_VERSION,
			true
		);
	}

	public static function render() {
		if ( ! self::should_render() ) return;
		$opts = Plugora_SB_Settings::get();

		$buttons = [];
		foreach ( $opts['buttons'] as $b ) {
			if ( empty( $b['enabled'] ) ) continue;
			$href = Plugora_SB_Platforms::build_href( $b['platform'], $b['value'] );
			if ( ! $href ) continue;
			$p = Plugora_SB_Platforms::get( $b['platform'] );
			$buttons[] = [
				'platform' => $b['platform'],
				'href'     => $href,
				'label'    => $b['label'] !== '' ? $b['label'] : $p['label'],
				'color'    => $b['color'] !== '' ? $b['color'] : $p['brand_color'],
				'icon'     => $p['icon'],
				'new_tab'  => ! empty( $p['new_tab'] ),
			];
		}
		if ( ! $buttons ) return;

		$layout       = $opts['layout'];
		$position     = $opts['position'];
		$trigger      = $opts['trigger'];
		$icon_size    = (int) $opts['icon_size'];
		$spacing      = (int) $opts['spacing'];
		$border_pct   = (int) $opts['border_radius'];
		$shadow_lvl   = (int) $opts['shadow'];
		$z_index      = (int) $opts['z_index'];

		$shadow_map = [
			0 => 'none',
			1 => '0 2px 6px rgba(0,0,0,0.10)',
			2 => '0 6px 18px rgba(0,0,0,0.18)',
			3 => '0 10px 30px rgba(0,0,0,0.25)',
			4 => '0 18px 50px rgba(0,0,0,0.35)',
		];

		$style = sprintf(
			'--psb-size:%dpx;--psb-gap:%dpx;--psb-radius:%d%%;--psb-shadow:%s;--psb-z:%d;',
			$icon_size + 18, // outer button size
			$spacing,
			$border_pct,
			$shadow_map[ $shadow_lvl ] ?? $shadow_map[2],
			$z_index
		);

		$device_classes = [];
		if ( empty( $opts['show_on_desktop'] ) ) $device_classes[] = 'psb-hide-desktop';
		if ( empty( $opts['show_on_tablet'] ) )  $device_classes[] = 'psb-hide-tablet';
		if ( empty( $opts['show_on_mobile'] ) )  $device_classes[] = 'psb-hide-mobile';

		$wrap_classes = array_merge( [
			'psb',
			'psb--pos-' . sanitize_html_class( $position ),
			'psb--layout-' . sanitize_html_class( $layout ),
			'psb--trigger-' . sanitize_html_class( $trigger ),
			! empty( $opts['show_labels'] ) ? 'psb--labels' : 'psb--no-labels',
		], $device_classes );

		$is_collapsible = in_array( $layout, [ 'expandable', 'popup' ], true );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrap_classes ) ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			role="complementary"
			aria-label="<?php esc_attr_e( 'Social media links', 'plugora-social-buttons' ); ?>">

			<?php if ( $is_collapsible ) : ?>
				<button type="button" class="psb__toggle" aria-expanded="false" aria-controls="psb-list"
					aria-label="<?php esc_attr_e( 'Toggle social menu', 'plugora-social-buttons' ); ?>">
					<span class="psb__toggle-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
					</span>
					<span class="psb__toggle-close" aria-hidden="true">×</span>
				</button>
			<?php endif; ?>

			<ul id="psb-list" class="psb__list">
				<?php foreach ( $buttons as $b ) :
					$rel    = $b['new_tab'] ? 'noopener noreferrer' : '';
					$target = $b['new_tab'] ? '_blank' : '';
					?>
					<li class="psb__item">
						<a class="psb__btn" href="<?php echo esc_url( $b['href'] ); ?>"
							<?php if ( $target ) echo 'target="' . esc_attr( $target ) . '"'; ?>
							<?php if ( $rel )    echo 'rel="'    . esc_attr( $rel )    . '"'; ?>
							style="--psb-color: <?php echo esc_attr( $b['color'] ); ?>;"
							aria-label="<?php echo esc_attr( $b['label'] ); ?>"
							data-platform="<?php echo esc_attr( $b['platform'] ); ?>">
							<span class="psb__icon" aria-hidden="true"><?php echo wp_kses( $b['icon'], Plugora_SB_Settings::svg_kses() ); ?></span>
							<span class="psb__label"><?php echo esc_html( $b['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
