<?php
/**
 * Wordfence 2FA Ultimate Member integration class.
 *
 * @package JDITC\Wordfence_2FA_for_Ultimate_Member
 */

namespace JDITC\Wordfence_2FA_for_Ultimate_Member;

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
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" class="um-field" data-key="wfls-token" 
		<?php
		if ( ! $show_immediately ) :
			?>
			style="display:none;"<?php endif; ?>>
			<div class="um-field-label">
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php esc_html_e( 'Wordfence 2FA Code', 'wordfence-2fa-for-ultimate-member' ); ?>
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
					placeholder="<?php esc_attr_e( '123456', 'wordfence-2fa-for-ultimate-member' ); ?>"
				>
				<div class="um-field-checkbox" style="margin-top:8px;">
					<label style="display:inline-flex; align-items:center; gap:6px; line-height:1.2;">
						<input type="checkbox" name="wfls-remember-device" value="1" <?php checked( $remember_selected ); ?> <?php echo esc_attr( $disabled_attr ); ?> style="display:inline-block !important; position:static !important; opacity:1 !important; width:auto !important; height:auto !important; clip:auto !important; clip-path:none !important; margin:0;">
						<?php esc_html_e( 'Remember this device for 30 days', 'wordfence-2fa-for-ultimate-member' ); ?>
					</label>
				</div>
			</div>
		</div>
		<script>
		(function() {
			var wrapper = document.getElementById(<?php echo wp_json_encode( $container_id ); ?>);
			if (!wrapper) {
				return;
			}

			var form = wrapper.closest('form');
			if (!form) {
				return;
			}

			var tokenInput = wrapper.querySelector('input[name="wfls-token"]');
			var rememberInput = wrapper.querySelector('input[name="wfls-remember-device"]');
			var ajaxURL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			var stepTwoActive = wrapper.style.display !== 'none';
			var allowNativeSubmit = false;
			var precheckInFlight = false;

			function findInputByKeys(keys, fallbackType) {
				var inputs = form.querySelectorAll('input');
				for (var i = 0; i < inputs.length; i++) {
					var input = inputs[i];
					if (!input.name) {
						continue;
					}
					if (input === tokenInput) {
						continue;
					}

					var inputName = input.name;
					var inputKey = input.getAttribute('data-key') || '';
					for (var k = 0; k < keys.length; k++) {
						var key = keys[k];
						if (inputKey === key || inputName === key || inputName.indexOf(key + '-') === 0) {
							return input;
						}
					}
				}

				if (fallbackType) {
					for (var j = 0; j < inputs.length; j++) {
						var fallbackInput = inputs[j];
						if (fallbackInput === tokenInput) {
							continue;
						}
						if (fallbackInput.type === fallbackType && fallbackInput.name) {
							return fallbackInput;
						}
					}
				}

				return null;
			}

			function findUsernameInput() {
				return findInputByKeys(['username', 'user_login', 'user_email'], 'text') ||
					findInputByKeys(['username', 'user_login', 'user_email'], 'email');
			}

			function findPasswordInput() {
				return findInputByKeys(['user_password', 'password', 'pwd'], 'password');
			}

			function setFieldVisibilityByKey(keys, visible) {
				for (var k = 0; k < keys.length; k++) {
					var key = keys[k];

					// Common UM wrapper class: .um-field-<key>
					var byClass = form.querySelectorAll('.um-field-' + key);
					for (var i = 0; i < byClass.length; i++) {
						byClass[i].style.display = visible ? '' : 'none';
					}

					// Fallback: locate any control with data-key=<key> and hide its nearest field wrapper.
					var byDataKey = form.querySelectorAll('[data-key="' + key + '"]');
					for (var j = 0; j < byDataKey.length; j++) {
						var field = byDataKey[j].closest('.um-field');
						if (field) {
							field.style.display = visible ? '' : 'none';
						}
					}
				}
			}

			function setPrimaryActionsVisibility(visible) {
				var selectors = [
					'.um-button.um-alt',
					'a.um-button.um-alt',
					'button.um-button.um-alt',
					'input.um-button.um-alt'
				];

				for (var s = 0; s < selectors.length; s++) {
					var nodes = form.querySelectorAll(selectors[s]);
					for (var n = 0; n < nodes.length; n++) {
						nodes[n].style.display = visible ? '' : 'none';
					}
				}
			}

			function setKeepSignedInVisibility(visible) {
				var rememberSelectors = [
					'.um-field-rememberme',
					'input[name="rememberme"]',
					'input[name^="rememberme-"]',
					'input[data-key="rememberme"]'
				];

				for (var s = 0; s < rememberSelectors.length; s++) {
					var nodes = form.querySelectorAll(rememberSelectors[s]);
					for (var i = 0; i < nodes.length; i++) {
						var node = nodes[i];
						var field = node.classList && node.classList.contains('um-field') ? node : node.closest('.um-field');
						if (field) {
							field.style.display = visible ? '' : 'none';
						} else {
							node.style.display = visible ? '' : 'none';
						}
					}
				}
			}

			function hideCodeRequiredNotice() {
				var notices = form.querySelectorAll('.um-error-code-wfls_twofactor_required');
				for (var i = 0; i < notices.length; i++) {
					notices[i].style.display = 'none';
				}
			}

			function enableTokenControls() {
				if (tokenInput) {
					tokenInput.disabled = false;
				}
				if (rememberInput) {
					rememberInput.disabled = false;
				}
			}

			function showTokenStep() {
				stepTwoActive = true;
				wrapper.style.display = '';
				enableTokenControls();
				hideCodeRequiredNotice();

				setFieldVisibilityByKey(['username', 'user_login', 'user_email', 'user_password'], false);
				setKeepSignedInVisibility(false);
				setPrimaryActionsVisibility(false);

				if (tokenInput && !tokenInput.value) {
					tokenInput.focus();
				}
			}

			function doWordfencePrecheck(username, password, onDone) {
				var payload = new URLSearchParams();
				payload.append('action', 'wordfence_ls_authenticate');
				payload.append('username', username);
				payload.append('password', password);

				fetch(ajaxURL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: payload.toString(),
					credentials: 'same-origin'
				})
				.then(function(resp) {
					if (!resp.ok) {
						throw new Error('Wordfence precheck failed');
					}
					return resp.json();
				})
				.then(function(json) {
					onDone(null, json);
				})
				.catch(function(err) {
					onDone(err);
				});
			}

			function runPrecheckAndMaybeSubmit(event) {
				if (allowNativeSubmit) {
					allowNativeSubmit = false;
					return;
				}

				if (stepTwoActive) {
					allowNativeSubmit = true;
					return;
				}

				if (precheckInFlight) {
					if (event) {
						event.preventDefault();
						event.stopImmediatePropagation();
					}
					return;
				}

				var usernameInput = findUsernameInput();
				var passwordInput = findPasswordInput();
				if (!usernameInput || !passwordInput) {
					allowNativeSubmit = true;
					return;
				}

				var username = usernameInput.value || '';
				var password = passwordInput.value || '';
				if (!username || !password) {
					allowNativeSubmit = true;
					return;
				}

				if (event) {
					event.preventDefault();
					event.stopImmediatePropagation();
				}

				precheckInFlight = true;

				doWordfencePrecheck(username, password, function(err, json) {
					precheckInFlight = false;

					if (!err && json && json.two_factor_required) {
						showTokenStep();
						return;
					}

					if (!err && json && typeof json.error === 'string' && /CODE REQUIRED/i.test(json.error)) {
						showTokenStep();
						return;
					}

					allowNativeSubmit = true;
					form.submit();
				});
			}

			var existingRequiredError = form.querySelector('.um-error-code-wfls_twofactor_required');
			var existingInvalidCodeError = form.querySelector('.um-error-code-wfls_twofactor_failed');
			if (existingRequiredError || existingInvalidCodeError || (tokenInput && tokenInput.value)) {
				showTokenStep();
			}

			form.addEventListener('submit', function(event) {
				runPrecheckAndMaybeSubmit(event);
			}, true);

			form.addEventListener('click', function(event) {
				var submitControl = event.target.closest('button[type="submit"], input[type="submit"]');
				if (!submitControl || !form.contains(submitControl)) {
					return;
				}
				runPrecheckAndMaybeSubmit(event);
			}, true);

			form.addEventListener('keydown', function(event) {
				if (event.key !== 'Enter') {
					return;
				}
				runPrecheckAndMaybeSubmit(event);
			}, true);

		})();
		</script>
		<?php
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
