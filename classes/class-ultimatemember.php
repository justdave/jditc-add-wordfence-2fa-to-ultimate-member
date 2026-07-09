<?php
/**
 * Wordfence 2FA Ultimate Member integration class.
 *
 * @package JDITC\Add_Wordfence_2FA_to_Ultimate_Member
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

namespace JDITC\Add_Wordfence_2FA_to_Ultimate_Member;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wordfence 2FA integration for Ultimate Member.
 */
class UltimateMember {
	/**
	 * Wordfence login security WP_Error codes that UM should surface directly.
	 *
	 * @var string[]
	 */
	private $wordfence_error_codes = array(
		'wfls_twofactor_required',
		'wfls_twofactor_failed',
		'wfls_twofactor_blocked',
		'wfls_captcha_verify',
		'wfls_captcha_expired',
		'wfls_captcha_required',
		'wfls_email_verified',
		'wfls_email_not_verified',
	);

	/**
	 * Register Ultimate Member integration hooks.
	 */
	public function __construct() {
		add_filter( 'um_custom_authenticate_error_codes', array( $this, 'add_wordfence_auth_error_codes' ) );
		add_action( 'um_after_login_fields', array( $this, 'render_wordfence_2fa_fields' ), 20 );
	}

	/**
	 * Allow UM to display Wordfence's own 2FA/auth error messages.
	 *
	 * @param array $codes Existing third-party error codes.
	 * @return array
	 */
	public function add_wordfence_auth_error_codes( $codes ) {
		if ( ! is_array( $codes ) ) {
			$codes = array();
		}

		$codes = array_merge( $codes, $this->wordfence_error_codes );
		$codes = array_values( array_unique( $codes ) );
		return $codes;
	}

	/**
	 * Render Wordfence 2FA fields on UM login forms.
	 */
	public function render_wordfence_2fa_fields() {
		if ( ! $this->is_wordfence_login_security_available() ) {
			return;
		}

		$posted_token_value    = filter_input( INPUT_POST, 'wfls-token', FILTER_UNSAFE_RAW );
		$posted_remember_value = filter_input( INPUT_POST, 'wfls-remember-device', FILTER_UNSAFE_RAW );
		$field_id              = 'wfls-token-' . wp_generate_uuid4();
		$container_id          = 'w2faum-container-' . wp_generate_uuid4();
		$show_immediately      = is_string( $posted_token_value ) && '' !== $posted_token_value;
		$remember_selected     = is_string( $posted_remember_value ) && '' !== $posted_remember_value;
		$disabled_attr         = $show_immediately ? '' : 'disabled';
		$this->enqueue_wordfence_2fa_script();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="um-field" data-key="wfls-token" data-jditc-w2fa="1" data-jditc-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		<?php
		if ( ! $show_immediately ) :
			?>
			style="display:none;"<?php endif; ?>>
			<div class="um-field-label">
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php esc_html_e( 'Wordfence 2FA Code', 'jditc-add-wordfence-2fa-to-ultimate-member' ); ?>
				</label>
			</div>
			<div class="um-field-area">
				<input
					type="text"
					name="wfls-token"
					id="<?php echo esc_attr( $field_id ); ?>"
					class="um-form-field"
					autocomplete="one-time-code"
					inputmode="numeric"
					<?php echo esc_attr( $disabled_attr ); ?>
					placeholder="<?php esc_attr_e( '123456', 'jditc-add-wordfence-2fa-to-ultimate-member' ); ?>"
				>
				<div class="um-field-checkbox" style="margin-top:8px;">
					<label style="display:inline-flex; align-items:center; gap:6px; line-height:1.2;">
						<input type="checkbox" name="wfls-remember-device" value="1" <?php checked( $remember_selected ); ?> <?php echo esc_attr( $disabled_attr ); ?> style="display:inline-block !important; position:static !important; opacity:1 !important; width:auto !important; height:auto !important; clip:auto !important; clip-path:none !important; margin:0;">
						<?php esc_html_e( 'Remember this device for 30 days', 'jditc-add-wordfence-2fa-to-ultimate-member' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue the login form script that toggles Wordfence 2FA UI behavior.
	 */
	private function enqueue_wordfence_2fa_script() {
		$handle               = 'jditc-w2fa-um-login';
		$script_relative_path = 'js/jditc-ultimatemember-login.js';
		$script_absolute_path = trailingslashit( \JDITC_W2FA_UM_PATH ) . $script_relative_path;
		$script_version       = file_exists( $script_absolute_path ) ? (string) filemtime( $script_absolute_path ) : '1.0';

		wp_enqueue_script(
			$handle,
			trailingslashit( \JDITC_W2FA_UM_URL ) . $script_relative_path,
			array(),
			$script_version,
			true
		);
	}

	/**
	 * Detect whether Wordfence Login Security is available.
	 *
	 * @return bool
	 */
	private function is_wordfence_login_security_available() {
		return defined( 'WORDFENCE_LS_VERSION' ) || class_exists( '\\WordfenceLS\\Controller_WordfenceLS' );
	}
}
