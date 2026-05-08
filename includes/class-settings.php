<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Settings option + admin page (Buttons / Display / Visibility / License tabs).
 *
 * Stored option (`plugora_sb_settings`) is shaped by Plugora_SB_Sanitizer.
 */
class Plugora_SB_Settings {
	const OPT_KEY = 'plugora_sb_settings';
	const PAGE    = 'plugora-social-buttons';

	public static function defaults() {
		return [
			'buttons' => [
				[ 'platform' => 'whatsapp',  'value' => '', 'label' => '', 'color' => '', 'enabled' => 1 ],
				[ 'platform' => 'facebook',  'value' => '', 'label' => '', 'color' => '', 'enabled' => 1 ],
				[ 'platform' => 'instagram', 'value' => '', 'label' => '', 'color' => '', 'enabled' => 1 ],
				[ 'platform' => 'email',     'value' => '', 'label' => '', 'color' => '', 'enabled' => 0 ],
			],
			'position'        => 'bottom-right',
			'layout'          => 'vertical',
			'trigger'         => 'hover',
			'icon_size'       => 22,
			'spacing'         => 10,
			'border_radius'   => 50,
			'shadow'          => 2,
			'z_index'         => 9990,
			'show_labels'     => 0,
			'show_on_desktop' => 1,
			'show_on_tablet'  => 1,
			'show_on_mobile'  => 1,
			'visibility_mode' => 'all',
			'visibility_ids'  => '',
			'hide_on_posts'   => 0,
			'hide_on_pages'   => 0,
			'hide_on_archives' => 0,
			'hide_on_woo'     => 0,
			'schedule_enabled' => 0,
			'schedule_start'   => '',
			'schedule_end'     => '',
		];
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPT_KEY, [] ), self::defaults() );
		if ( $key === null ) return $opts;
		return $opts[ $key ] ?? null;
	}

	public static function register_settings() {
		register_setting( 'plugora_sb_settings_group', self::OPT_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			'default'           => self::defaults(),
		] );
	}

	public static function sanitize( $input ) {
		return Plugora_SB_Sanitizer::sanitize( $input, self::defaults() );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Forbidden', 'plugora-social-buttons' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switcher.
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'buttons';
		$base_url = admin_url( 'admin.php?page=' . self::PAGE );
		$tabs     = [
			'buttons'    => __( 'Buttons', 'plugora-social-buttons' ),
			'display'    => __( 'Display', 'plugora-social-buttons' ),
			'visibility' => __( 'Visibility', 'plugora-social-buttons' ),
			'license'    => __( 'License', 'plugora-social-buttons' ),
		];
		?>
		<div class="wrap plugora-sb-wrap">
			<h1>
				<?php esc_html_e( 'Floating Social Buttons', 'plugora-social-buttons' ); ?>
				<?php echo plugora_sb_is_premium()
					? '<span class="plugora-badge plugora-badge-pro" style="vertical-align:middle;margin-left:8px">PRO</span>'
					: '<span class="plugora-badge plugora-badge-free" style="vertical-align:middle;margin-left:8px">FREE</span>'; ?>
			</h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) :
					$url = $slug === 'buttons' ? $base_url : add_query_arg( 'tab', $slug, $base_url );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php
			if ( $tab === 'license' ) {
				Plugora_SB_License::render_panel();
			} else {
				self::render_settings_form( $tab );
			}
			?>
		</div>
		<?php
		if ( class_exists( 'Plugora_Ecosystem' ) ) {
			Plugora_Ecosystem::render( [
				'current_slug' => 'plugora-social-buttons',
				'context'      => 'settings',
			] );
		}
	}

	private static function render_settings_form( $tab ) {
		$opts = self::get();
		?>
		<form method="post" action="options.php" class="plugora-sb-form" id="plugora-sb-form">
			<?php settings_fields( 'plugora_sb_settings_group' ); ?>

			<div class="plugora-sb-grid">
				<div class="plugora-sb-grid__main">
					<?php
					if ( $tab === 'buttons' )    self::render_buttons_tab( $opts );
					if ( $tab === 'display' )    self::render_display_tab( $opts );
					if ( $tab === 'visibility' ) self::render_visibility_tab( $opts );
					?>
				</div>
				<aside class="plugora-sb-grid__preview">
					<div class="plugora-card plugora-sb-preview-card">
						<h2 class="plugora-card-title"><?php esc_html_e( 'Live preview', 'plugora-social-buttons' ); ?></h2>
						<p class="plugora-card-sub"><?php esc_html_e( 'Updates as you change settings.', 'plugora-social-buttons' ); ?></p>
						<div class="plugora-sb-preview-stage" id="plugora-sb-preview"></div>
					</div>
				</aside>
			</div>

			<p class="submit">
				<?php submit_button( __( 'Save changes', 'plugora-social-buttons' ), 'primary', 'submit', false ); ?>
				<button type="button" class="button" id="plugora-sb-reset"><?php esc_html_e( 'Reset to defaults', 'plugora-social-buttons' ); ?></button>
			</p>
		</form>
		<?php
	}

	private static function render_buttons_tab( $opts ) {
		$platforms = Plugora_SB_Platforms::all();
		?>
		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Buttons', 'plugora-social-buttons' ); ?></h2>
			<p class="plugora-card-sub"><?php esc_html_e( 'Drag to reorder. Toggle to enable. Add as many as you need.', 'plugora-social-buttons' ); ?></p>

			<div class="plugora-sb-buttons" id="plugora-sb-buttons">
				<?php foreach ( $opts['buttons'] as $i => $b ) self::render_button_row( $i, $b ); ?>
			</div>

			<div class="plugora-sb-add-row">
				<label for="plugora-sb-add-platform" class="screen-reader-text"><?php esc_html_e( 'Choose a platform', 'plugora-social-buttons' ); ?></label>
				<select id="plugora-sb-add-platform">
					<?php foreach ( $platforms as $key => $p ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $p['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-secondary" id="plugora-sb-add-button">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add button', 'plugora-social-buttons' ); ?>
				</button>
			</div>
		</div>

		<template id="plugora-sb-row-template">
			<?php self::render_button_row( '__INDEX__', [ 'platform' => '__PLATFORM__', 'value' => '', 'label' => '', 'color' => '', 'enabled' => 1 ], true ); ?>
		</template>
		<?php
	}

	private static function render_button_row( $i, $b, $is_template = false ) {
		$platform = $b['platform'];
		$p        = Plugora_SB_Platforms::get( $platform );
		if ( ! $p && ! $is_template ) return;
		$base    = self::OPT_KEY . '[buttons][' . esc_attr( $i ) . ']';
		?>
		<div class="plugora-sb-row" data-platform="<?php echo esc_attr( $platform ); ?>">
			<span class="plugora-sb-row__handle dashicons dashicons-menu" aria-hidden="true"></span>
			<span class="plugora-sb-row__icon" style="<?php echo $p ? 'color:' . esc_attr( $p['brand_color'] ) : ''; ?>">
				<?php if ( $p ) echo wp_kses( $p['icon'], self::svg_kses() ); ?>
			</span>
			<div class="plugora-sb-row__fields">
				<strong class="plugora-sb-row__label"><?php echo $p ? esc_html( $p['label'] ) : esc_html__( 'Custom', 'plugora-social-buttons' ); ?></strong>
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[platform]" value="<?php echo esc_attr( $platform ); ?>" />
				<input type="text"
					name="<?php echo esc_attr( $base ); ?>[value]"
					value="<?php echo esc_attr( $b['value'] ); ?>"
					placeholder="<?php echo $p ? esc_attr( $p['placeholder'] ) : ''; ?>"
					class="plugora-sb-row__value regular-text" />
				<input type="text"
					name="<?php echo esc_attr( $base ); ?>[label]"
					value="<?php echo esc_attr( $b['label'] ); ?>"
					placeholder="<?php echo $p ? esc_attr( $p['label'] ) : ''; ?>"
					class="plugora-sb-row__custom-label" />
				<input type="text"
					name="<?php echo esc_attr( $base ); ?>[color]"
					value="<?php echo esc_attr( $b['color'] ); ?>"
					placeholder="<?php echo $p ? esc_attr( $p['brand_color'] ) : '#000000'; ?>"
					class="plugora-sb-row__color" maxlength="7" />
			</div>
			<label class="plugora-switch plugora-sb-row__toggle">
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[enabled]" value="0" />
				<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $b['enabled'] ) ); ?> />
				<span class="plugora-switch-slider"></span>
			</label>
			<button type="button" class="button-link plugora-sb-row__remove" aria-label="<?php esc_attr_e( 'Remove button', 'plugora-social-buttons' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<?php
	}

	private static function render_display_tab( $opts ) {
		?>
		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Position & layout', 'plugora-social-buttons' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Position', 'plugora-social-buttons' ); ?></th>
					<td>
						<div class="plugora-sb-position-grid">
							<?php
							$positions = [
								'middle-left'   => __( 'Middle left', 'plugora-social-buttons' ),
								'middle-right'  => __( 'Middle right', 'plugora-social-buttons' ),
								'bottom-left'   => __( 'Bottom left', 'plugora-social-buttons' ),
								'bottom-right'  => __( 'Bottom right', 'plugora-social-buttons' ),
							];
							foreach ( $positions as $val => $label ) : ?>
								<label class="plugora-sb-radio-tile">
									<input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[position]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $opts['position'], $val ); ?> />
									<span class="plugora-sb-radio-tile__dot"></span>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="plugora_sb_layout"><?php esc_html_e( 'Layout', 'plugora-social-buttons' ); ?></label></th>
					<td>
						<select id="plugora_sb_layout" name="<?php echo esc_attr( self::OPT_KEY ); ?>[layout]">
							<option value="vertical"   <?php selected( $opts['layout'], 'vertical' ); ?>><?php esc_html_e( 'Vertical stack', 'plugora-social-buttons' ); ?></option>
							<option value="horizontal" <?php selected( $opts['layout'], 'horizontal' ); ?>><?php esc_html_e( 'Horizontal row', 'plugora-social-buttons' ); ?></option>
							<option value="expandable" <?php selected( $opts['layout'], 'expandable' ); ?>><?php esc_html_e( 'Expandable floating button', 'plugora-social-buttons' ); ?></option>
							<option value="popup"      <?php selected( $opts['layout'], 'popup' ); ?>><?php esc_html_e( 'Pop-up panel', 'plugora-social-buttons' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Open expandable on', 'plugora-social-buttons' ); ?></th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[trigger]" value="hover" <?php checked( $opts['trigger'], 'hover' ); ?>/> <?php esc_html_e( 'Hover', 'plugora-social-buttons' ); ?></label>
						&nbsp;&nbsp;
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[trigger]" value="click" <?php checked( $opts['trigger'], 'click' ); ?>/> <?php esc_html_e( 'Click', 'plugora-social-buttons' ); ?></label>
					</td>
				</tr>
			</table>
		</div>

		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Design', 'plugora-social-buttons' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				self::number_row( 'icon_size',     __( 'Icon size (px)',     'plugora-social-buttons' ), $opts['icon_size'],     16, 80 );
				self::number_row( 'spacing',       __( 'Button spacing (px)', 'plugora-social-buttons' ), $opts['spacing'],       0,  40 );
				self::number_row( 'border_radius', __( 'Border radius (%)',  'plugora-social-buttons' ), $opts['border_radius'], 0,  50 );
				self::number_row( 'shadow',        __( 'Shadow intensity (0-4)', 'plugora-social-buttons' ), $opts['shadow'],   0,  4 );
				self::number_row( 'z_index',       __( 'Z-index',            'plugora-social-buttons' ), $opts['z_index'],     0,  2147483647 );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show labels on hover', 'plugora-social-buttons' ); ?></th>
					<td><?php self::toggle( 'show_labels', $opts['show_labels'] ); ?></td>
				</tr>
			</table>
		</div>

		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Devices', 'plugora-social-buttons' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Show on desktop', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'show_on_desktop', $opts['show_on_desktop'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Show on tablet',  'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'show_on_tablet',  $opts['show_on_tablet'] );  ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Show on mobile',  'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'show_on_mobile',  $opts['show_on_mobile'] );  ?></td></tr>
			</table>
		</div>
		<?php
	}

	private static function render_visibility_tab( $opts ) {
		?>
		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Where to show', 'plugora-social-buttons' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Visibility mode', 'plugora-social-buttons' ); ?></th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[visibility_mode]" value="all"       <?php checked( $opts['visibility_mode'], 'all' ); ?>/> <?php esc_html_e( 'Show on all pages', 'plugora-social-buttons' ); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[visibility_mode]" value="hide_on"   <?php checked( $opts['visibility_mode'], 'hide_on' ); ?>/> <?php esc_html_e( 'Hide on selected pages', 'plugora-social-buttons' ); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_KEY ); ?>[visibility_mode]" value="show_only" <?php checked( $opts['visibility_mode'], 'show_only' ); ?>/> <?php esc_html_e( 'Show only on selected pages', 'plugora-social-buttons' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="plugora_sb_visibility_ids"><?php esc_html_e( 'Page / post IDs', 'plugora-social-buttons' ); ?></label></th>
					<td>
						<input type="text" id="plugora_sb_visibility_ids" class="regular-text" name="<?php echo esc_attr( self::OPT_KEY ); ?>[visibility_ids]" value="<?php echo esc_attr( $opts['visibility_ids'] ); ?>" placeholder="12, 48, 102" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of post or page IDs.', 'plugora-social-buttons' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="plugora-card">
			<h2 class="plugora-card-title"><?php esc_html_e( 'Content type rules', 'plugora-social-buttons' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Hide on posts', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'hide_on_posts', $opts['hide_on_posts'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Hide on pages', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'hide_on_pages', $opts['hide_on_pages'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Hide on archives, home & search', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'hide_on_archives', $opts['hide_on_archives'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Hide on WooCommerce pages', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'hide_on_woo', $opts['hide_on_woo'] ); ?></td></tr>
			</table>
		</div>

		<div class="plugora-card">
			<h2 class="plugora-card-title">
				<?php esc_html_e( 'Scheduling', 'plugora-social-buttons' ); ?>
				<?php if ( ! plugora_sb_is_premium() ) echo ' <span class="plugora-badge plugora-badge-pro" style="margin-left:6px">PRO</span>'; ?>
			</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Enable schedule', 'plugora-social-buttons' ); ?></th><td><?php self::toggle( 'schedule_enabled', $opts['schedule_enabled'] ); ?></td></tr>
				<tr>
					<th scope="row"><label for="plugora_sb_schedule_start"><?php esc_html_e( 'Start (UTC)', 'plugora-social-buttons' ); ?></label></th>
					<td><input type="datetime-local" id="plugora_sb_schedule_start" name="<?php echo esc_attr( self::OPT_KEY ); ?>[schedule_start]" value="<?php echo esc_attr( $opts['schedule_start'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="plugora_sb_schedule_end"><?php esc_html_e( 'End (UTC)', 'plugora-social-buttons' ); ?></label></th>
					<td><input type="datetime-local" id="plugora_sb_schedule_end" name="<?php echo esc_attr( self::OPT_KEY ); ?>[schedule_end]" value="<?php echo esc_attr( $opts['schedule_end'] ); ?>" /></td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function toggle( $key, $value ) {
		?>
		<label class="plugora-switch">
			<input type="hidden" name="<?php echo esc_attr( self::OPT_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="0" />
			<input type="checkbox" name="<?php echo esc_attr( self::OPT_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $value ) ); ?> />
			<span class="plugora-switch-slider"></span>
		</label>
		<?php
	}

	private static function number_row( $key, $label, $value, $min, $max ) {
		$id = 'plugora_sb_' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="number" id="<?php echo esc_attr( $id ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="<?php echo esc_attr( self::OPT_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="small-text" /></td>
		</tr>
		<?php
	}

	public static function svg_kses() {
		return [
			'svg'    => [ 'xmlns' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'class' => true, 'width' => true, 'height' => true ],
			'path'   => [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ],
			'circle' => [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ],
			'rect'   => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true, 'stroke' => true ],
			'g'      => [ 'fill' => true, 'stroke' => true ],
			'line'   => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ],
			'polyline' => [ 'points' => true ],
		];
	}
}
