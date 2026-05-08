<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugora Floating Social Buttons — license storage + Plugora gateway validation.
 * Mirrors the other Plugora plugins so the back-office (subscriptions,
 * downloads, update checks) treats every plugin the same way.
 */
class Plugora_SB_License {
	const OPT_KEY   = 'plugora_sb_license_key';
	const OPT_STATE = 'plugora_sb_license_state';
	const TRANSIENT = 'plugora_sb_license_recheck';

	public static function is_active() {
		$state = get_option( self::OPT_STATE, [] );
		if ( empty( $state ) || empty( $state['valid'] ) ) return false;
		if ( ! empty( $state['expires_at'] ) && strtotime( $state['expires_at'] ) < time() ) return false;
		return true;
	}

	public static function get_key()   { return (string) get_option( self::OPT_KEY, '' ); }
	public static function get_state() { return (array)  get_option( self::OPT_STATE, [] ); }

	public static function activate( $key ) {
		$key    = trim( (string) $key );
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $key === '' ) {
			update_option( self::OPT_KEY, '' );
			update_option( self::OPT_STATE, [] );
			return [ 'valid' => false, 'error' => 'empty_key' ];
		}

		$response = wp_remote_post( PLUGORA_SB_API, [
			'timeout' => 12,
			'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
			'body'    => wp_json_encode( [
				'key'         => $key,
				'domain'      => $domain,
				'plugin_slug' => 'social-buttons',
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'valid' => false, 'error' => 'network', 'message' => $response->get_error_message() ];
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) return [ 'valid' => false, 'error' => 'bad_response' ];

		if ( empty( $body['valid'] ) ) {
			update_option( self::OPT_KEY, $key );
			update_option( self::OPT_STATE, [
				'valid'      => false,
				'error'      => $body['error'] ?? 'invalid',
				'checked_at' => time(),
			] );
			return $body;
		}

		$lic = $body['license'] ?? [];
		update_option( self::OPT_KEY, $key );
		update_option( self::OPT_STATE, [
			'valid'             => true,
			'edition'           => $body['edition'] ?? 'premium',
			'type'              => $lic['type'] ?? null,
			'expires_at'        => $lic['expires_at'] ?? null,
			'max_sites'         => $lic['max_sites'] ?? 1,
			'activated_domains' => $lic['activated_domains'] ?? [],
			'checked_at'        => time(),
		] );
		set_transient( self::TRANSIENT, 1, DAY_IN_SECONDS );
		return $body;
	}

	public static function deactivate() {
		update_option( self::OPT_KEY, '' );
		update_option( self::OPT_STATE, [] );
		delete_transient( self::TRANSIENT );
	}

	public static function render_panel() {
		$message = '';
		if (
			isset( $_POST['plugora_sb_license_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plugora_sb_license_nonce'] ) ), 'plugora_sb_license' )
		) {
			if ( isset( $_POST['plugora_deactivate'] ) ) {
				self::deactivate();
				$message = '<div class="notice notice-success"><p>' . esc_html__( 'License removed. Premium features disabled.', 'plugora-social-buttons' ) . '</p></div>';
			} else {
				$key    = sanitize_text_field( wp_unslash( $_POST['plugora_sb_license_key'] ?? '' ) );
				$result = self::activate( $key );
				if ( ! empty( $result['valid'] ) ) {
					$message = '<div class="notice notice-success"><p>' . esc_html__( '✓ License active — premium features unlocked.', 'plugora-social-buttons' ) . '</p></div>';
				} else {
					$err     = esc_html( $result['error'] ?? 'invalid' );
					$message = '<div class="notice notice-error"><p>' . esc_html__( 'License check failed:', 'plugora-social-buttons' ) . ' ' . $err . '</p></div>';
				}
			}
		}

		$key     = self::get_key();
		$state   = self::get_state();
		$active  = self::is_active();
		$buy_url = PLUGORA_SB_BUY_URL;
		$allowed = [ 'div' => [ 'class' => true ], 'p' => [], 'strong' => [], 'em' => [], 'a' => [ 'href' => true, 'target' => true, 'rel' => true ] ];
		?>
		<div class="plugora-license-wrap">
			<?php echo wp_kses( $message, $allowed ); ?>

			<div class="plugora-license-grid">
				<div class="plugora-license-card">
					<h2><?php echo $active
						? '<span class="plugora-badge plugora-badge-pro">PRO</span> ' . esc_html__( 'Premium active', 'plugora-social-buttons' )
						: '<span class="plugora-badge plugora-badge-free">FREE</span> ' . esc_html__( 'Free edition', 'plugora-social-buttons' ); ?></h2>
					<p class="description">
						<?php if ( $active ) : ?>
							<?php esc_html_e( 'All premium features are unlocked on this site.', 'plugora-social-buttons' ); ?>
							<?php if ( ! empty( $state['expires_at'] ) ) echo ' ' . esc_html( sprintf( __( 'Renews on %s.', 'plugora-social-buttons' ), $state['expires_at'] ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Enter a license key to unlock scheduling, unlimited custom buttons, advanced visibility rules and priority support.', 'plugora-social-buttons' ); ?>
						<?php endif; ?>
					</p>

					<form method="post">
						<?php wp_nonce_field( 'plugora_sb_license', 'plugora_sb_license_nonce' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="plugora_sb_license_key"><?php esc_html_e( 'License key', 'plugora-social-buttons' ); ?></label></th>
								<td>
									<input name="plugora_sb_license_key" id="plugora_sb_license_key" type="text"
										value="<?php echo esc_attr( $key ); ?>"
										class="regular-text code" placeholder="PLG-XXXX-XXXX-XXXX" autocomplete="off" />
									<p class="description"><?php esc_html_e( "You'll get this by email after purchase.", 'plugora-social-buttons' ); ?></p>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button class="button button-primary" type="submit">
								<?php echo $active ? esc_html__( 'Re-check license', 'plugora-social-buttons' ) : esc_html__( 'Activate license', 'plugora-social-buttons' ); ?>
							</button>
							<?php if ( $active ) : ?>
								<button class="button" type="submit" name="plugora_deactivate" value="1"><?php esc_html_e( 'Deactivate on this site', 'plugora-social-buttons' ); ?></button>
							<?php endif; ?>
							<?php if ( ! $active ) : ?>
								<a class="button button-secondary plugora-buy-btn" href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Purchase a license', 'plugora-social-buttons' ); ?> &rarr;
								</a>
							<?php endif; ?>
						</p>
					</form>
				</div>

				<aside class="plugora-license-card plugora-upsell">
					<h3><?php esc_html_e( 'Premium features', 'plugora-social-buttons' ); ?></h3>
					<ul class="plugora-feature-list">
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Unlimited custom buttons', 'plugora-social-buttons' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Scheduling & campaign windows', 'plugora-social-buttons' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Advanced page-level visibility rules', 'plugora-social-buttons' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Priority support', 'plugora-social-buttons' ); ?></li>
					</ul>
					<?php if ( ! $active ) : ?>
						<a class="button button-primary button-hero" href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'plugora-social-buttons' ); ?></a>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
	}
}
