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
		add_filter( 'um_account_page_default_tabs_hook', array( $this, 'add_security_account_tab' ), 999 );
		add_filter( 'um_account_content_hook_security', array( $this, 'render_security_account_tab' ), 10, 2 );
		add_filter( 'um_submit_form_data', array( $this, 'normalize_passkey_login_data' ), 1, 3 );
		add_action( 'um_submit_form_errors_hook_login', array( $this, 'consume_passkey_handoff' ), 1 );
		add_action( 'um_after_login_fields', array( $this, 'render_wordfence_2fa_fields' ), 20 );
		add_action( 'um_after_login_fields', array( $this, 'render_passkey_login_control' ), 1002 );
		add_action( 'wp_ajax_nopriv_jditc_w2fa_finish_passkey_login', array( $this, 'finish_passkey_login' ) );
		add_action( 'wp_ajax_jditc_w2fa_finish_passkey_login', array( $this, 'finish_passkey_login' ) );
	}

	/**
	 * Add the security tab when the current user can use at least one feature.
	 *
	 * @param array $tabs Existing account tabs.
	 * @return array
	 */
	public function add_security_account_tab( $tabs ) {
		if ( ! is_array( $tabs ) || ! $this->current_user_has_security_capability() ) {
			return $tabs;
		}
		$tabs[350]['security'] = array(
			'icon'         => 'um-faicon-shield',
			'title'        => __( 'Security', 'jditc-add-wordfence-2fa-to-ultimate-member' ),
			'submit_title' => __( 'Update Security', 'jditc-add-wordfence-2fa-to-ultimate-member' ),
			'show_button'  => false,
			'custom'       => true,
		);

		return $tabs;
	}

	/**
	 * Render eligible Wordfence security panels in the UM account tab.
	 *
	 * @param string $output Existing tab output.
	 * @return string
	 */
	public function render_security_account_tab( $output ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return $output;
		}

		if ( $this->current_user_can_use_2fa( $user ) && shortcode_exists( 'wordfence_2fa_management' ) ) {
			$output .= do_shortcode( '[wordfence_2fa_management stacked="true"]' );
		}

		if ( $this->current_user_can_use_passkeys( $user ) && shortcode_exists( 'wordfence_passkey_management' ) ) {
			$output .= do_shortcode( '[wordfence_passkey_management stacked="true"]' );
		}

		return $output;
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
	 * Render the passwordless passkey control when Wordfence supports it.
	 */
	public function render_passkey_login_control() {
		if ( ! $this->passkey_api_available() ) {
			return;
		}

		?>
		<div class="um-field um-field-passkey" data-jditc-passkey="1" data-jditc-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<button type="button" class="um-button um-alt" data-jditc-passkey-button>
				<?php esc_html_e( 'Use a passkey instead', 'jditc-add-wordfence-2fa-to-ultimate-member' ); ?>
			</button>
			<p class="um-notice err" data-jditc-passkey-error style="display:none;"></p>
			<input type="hidden" name="jditc-w2fa-passkey-handoff" value="">
		</div>
		<?php
	}

	/**
	 * Finish a Wordfence passkey assertion without establishing a WordPress session.
	 */
	public function finish_passkey_login() {
		if ( ! $this->passkey_api_available() ) {
			wp_send_json_error( array( 'message' => __( 'Passkey login is unavailable.', 'jditc-add-wordfence-2fa-to-ultimate-member' ) ), 404 );
		}

		// Wordfence's unauthenticated passkey endpoint validates its own WebAuthn token.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$token      = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$credential = isset( $_POST['credential'] ) ? wp_unslash( $_POST['credential'] ) : null;
		$remember   = ! empty( $_POST['rememberme'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( is_string( $credential ) ) {
			$credential = json_decode( $credential, true );
		}

		$controller = '\\WordfenceLS\\Controller_Passkey';
		$user       = $controller::shared()->finish_and_authenticate_login( $token, $credential, $remember, false );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => $user->get_error_message() ), 400 );
		}

		$handoff = wp_generate_uuid4();
		set_transient(
			$this->passkey_handoff_key( $handoff ),
			array(
				'user_id'  => (int) $user->ID,
				'remember' => $remember,
			),
			2 * MINUTE_IN_SECONDS
		);

		wp_send_json_success( array( 'handoff' => $handoff ) );
	}

	/**
	 * Mark passkey submissions so UM's password validator can be bypassed safely.
	 *
	 * @param array  $data Submitted form data.
	 * @param string $mode Form mode.
	 * @return array
	 */
	public function normalize_passkey_login_data( $data, $mode ) {
		if ( 'login' === $mode && isset( $data['jditc-w2fa-passkey-handoff'] ) ) {
			$data['jditc-w2fa-passkey-handoff'] = sanitize_text_field( $data['jditc-w2fa-passkey-handoff'] );
		}

		return $data;
	}

	/**
	 * Consume the verified passkey handoff before UM validates a password.
	 *
	 * @param array $submitted_data Submitted form data.
	 */
	public function consume_passkey_handoff( $submitted_data ) {
		$handoff = isset( $submitted_data['jditc-w2fa-passkey-handoff'] ) ? $submitted_data['jditc-w2fa-passkey-handoff'] : '';
		if ( ! $this->passkey_api_available() || ! is_string( $handoff ) || '' === $handoff ) {
			return;
		}

		$key  = $this->passkey_handoff_key( $handoff );
		$data = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
			return;
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		UM()->login()->auth_id = (int) $user->ID;
		remove_action( 'um_submit_form_errors_hook_login', 'um_submit_form_errors_hook_login' );
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

	/**
	 * Determine whether the Wordfence passkey API is available.
	 *
	 * @return bool
	 */
	private function passkey_api_available() {
		$controller = '\\WordfenceLS\\Controller_Passkey';
		return $this->is_wordfence_login_security_available()
			&& class_exists( $controller )
			&& method_exists( $controller, 'shared' )
			&& method_exists( $controller::shared(), 'begin_login' )
			&& method_exists( $controller::shared(), 'finish_and_authenticate_login' );
	}

	/**
	 * Get the transient key for a passkey handoff.
	 *
	 * @param string $handoff Handoff token.
	 * @return string
	 */
	private function passkey_handoff_key( $handoff ) {
		return 'jditc_w2fa_' . hash( 'sha256', $handoff );
	}

	/**
	 * Determine whether the current user can use Wordfence 2FA.
	 *
	 * @param \WP_User $user WordPress user.
	 * @return bool
	 */
	private function current_user_can_use_2fa( $user ) {
		return $this->wordfence_user_method_returns_true( 'can_activate_2fa', $user );
	}

	/**
	 * Determine whether the current user can use Wordfence passkeys.
	 *
	 * @param \\WP_User $user WordPress user.
	 * @return bool
	 */
	private function current_user_can_use_passkeys( $user ) {
		return $this->wordfence_user_method_returns_true( 'can_manage_passkey', $user );
	}

	/**
	 * Check the capabilities Wordfence grants to Optional and Required roles.
	 *
	 * @return bool
	 */
	private function current_user_has_security_capability() {
		$user = wp_get_current_user();
		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		return $user->exists() && ( user_can( $user, 'wf2fa_activate_2fa_self' ) || user_can( $user, 'wfls_manage_passkey_self' ) );
	}

	/**
	 * Call a Wordfence user capability method when that API exists.
	 *
	 * @param string    $method Wordfence method name.
	 * @param \\WP_User $user WordPress user.
	 * @return bool
	 */
	private function wordfence_user_method_returns_true( $method, $user ) {
		$controller = '\\WordfenceLS\\Controller_Users';
		if ( ! $this->is_wordfence_login_security_available() || ! class_exists( $controller ) || ! method_exists( $controller, 'shared' ) ) {
			return false;
		}

		$instance = $controller::shared();
		return method_exists( $instance, $method ) && true === $instance->{$method}( $user );
	}
}
