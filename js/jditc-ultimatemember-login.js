(function () {
	'use strict';

	function findInputByKeys(form, tokenInput, keys, fallbackType) {
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

	function attachToWrapper(wrapper) {
		if (wrapper.dataset.jditcW2faInitialized === '1') {
			return;
		}
		wrapper.dataset.jditcW2faInitialized = '1';

		var form = wrapper.closest('form');
		if (!form) {
			return;
		}

		var tokenInput = wrapper.querySelector('input[name="wfls-token"]');
		var rememberInput = wrapper.querySelector('input[name="wfls-remember-device"]');
		var ajaxURL = wrapper.getAttribute('data-jditc-ajax-url') || '';
		if (!ajaxURL) {
			return;
		}

		var stepTwoActive = wrapper.style.display !== 'none';
		var allowNativeSubmit = false;
		var precheckInFlight = false;
		var canonicalTokenIdClaimed = false;
		var originalTokenId = tokenInput ? tokenInput.id : '';
		var tokenLabel = wrapper.querySelector('label[for="' + originalTokenId + '"]');

		function findUsernameInput() {
			return findInputByKeys(form, tokenInput, ['username', 'user_login', 'user_email'], 'text') ||
				findInputByKeys(form, tokenInput, ['username', 'user_login', 'user_email'], 'email');
		}

		function findPasswordInput() {
			return findInputByKeys(form, tokenInput, ['user_password', 'password', 'pwd'], 'password');
		}

		function setFieldVisibilityByKey(keys, visible) {
			for (var k = 0; k < keys.length; k++) {
				var key = keys[k];

				var byClass = form.querySelectorAll('.um-field-' + key);
				for (var i = 0; i < byClass.length; i++) {
					byClass[i].style.display = visible ? '' : 'none';
				}

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

		function setTurnstileVisibility(visible) {
			var selectors = [
				'.cf-turnstile',
				'.cf-turnstile-br',
				'.cfturnstile-widget-label',
				'.cf-turnstile-failed-text'
			];

			for (var s = 0; s < selectors.length; s++) {
				var nodes = form.querySelectorAll(selectors[s]);
				for (var i = 0; i < nodes.length; i++) {
					var node = nodes[i];
					if (visible) {
						if (Object.prototype.hasOwnProperty.call(node, 'jditcOriginalDisplay')) {
							node.style.display = node.jditcOriginalDisplay;
							delete node.jditcOriginalDisplay;
						}
					} else {
						if (!Object.prototype.hasOwnProperty.call(node, 'jditcOriginalDisplay')) {
							node.jditcOriginalDisplay = node.style.display;
						}
						node.style.display = 'none';
					}
				}
			}
		}

		function setCanonicalTokenMarker(active) {
			if (!tokenInput) {
				return;
			}

			if (active) {
				var existingTokenMarker = document.getElementById('wfls-token');
				if (existingTokenMarker && existingTokenMarker !== tokenInput) {
					return;
				}

				tokenInput.id = 'wfls-token';
				if (tokenLabel) {
					tokenLabel.setAttribute('for', 'wfls-token');
				}
				canonicalTokenIdClaimed = true;
				return;
			}

			if (canonicalTokenIdClaimed) {
				tokenInput.id = originalTokenId;
				if (tokenLabel) {
					tokenLabel.setAttribute('for', originalTokenId);
				}
				canonicalTokenIdClaimed = false;
			}
		}

		function clearPreflightNotice() {
			var notice = form.querySelector('[data-jditc-preflight-notice="1"]');
			if (notice) {
				notice.remove();
			}
		}

		function showPreflightNotice(message, isError) {
			clearPreflightNotice();

			var notice = document.createElement('p');
			notice.className = isError ? 'um-notice err' : 'um-notice';
			notice.setAttribute('data-jditc-preflight-notice', '1');
			notice.innerHTML = message;

			var firstField = form.querySelector('.um-field');
			if (firstField) {
				firstField.parentNode.insertBefore(notice, firstField);
			} else {
				form.insertBefore(notice, form.firstChild);
			}
		}

		function resetTurnstile() {
			var widgets = form.querySelectorAll('.cf-turnstile');
			if (!widgets.length || typeof window.turnstile === 'undefined' || typeof window.turnstile.reset !== 'function') {
				return;
			}

			for (var i = 0; i < widgets.length; i++) {
				try {
					window.turnstile.reset(widgets[i]);
				} catch (error) {
					// The widget may not have finished rendering yet.
				}
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
			setCanonicalTokenMarker(true);
			setTurnstileVisibility(false);
			clearPreflightNotice();

			setFieldVisibilityByKey(['username', 'user_login', 'user_email', 'user_password'], false);
			setKeepSignedInVisibility(false);
			setPrimaryActionsVisibility(false);

			if (tokenInput && !tokenInput.value) {
				tokenInput.focus();
			}
		}

		function showCredentialsStep() {
			stepTwoActive = false;
			wrapper.style.display = 'none';
			setCanonicalTokenMarker(false);
			setTurnstileVisibility(true);
			setFieldVisibilityByKey(['username', 'user_login', 'user_email', 'user_password'], true);
			setKeepSignedInVisibility(true);
			setPrimaryActionsVisibility(true);
			if (tokenInput) {
				tokenInput.value = '';
			}
			if (rememberInput) {
				rememberInput.checked = false;
			}
		}

		function doWordfencePrecheck(username, password, onDone) {
			var payload = new URLSearchParams();
			payload.append('action', 'wordfence_ls_authenticate');
			payload.append('username', username);
			payload.append('password', password);

			var turnstileInput = form.querySelector('input[name="cf-turnstile-response"]');
			if (turnstileInput && turnstileInput.value) {
				payload.append('cf-turnstile-response', turnstileInput.value);
			}

			fetch(ajaxURL, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: payload.toString(),
				credentials: 'same-origin'
			})
				.then(function (resp) {
					if (!resp.ok) {
						throw new Error('Wordfence precheck failed');
					}
					return resp.json();
				})
				.then(function (json) {
					onDone(null, json);
				})
				.catch(function (err) {
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

			clearPreflightNotice();
			precheckInFlight = true;

			doWordfencePrecheck(username, password, function (err, json) {
				precheckInFlight = false;

				if (!err && json && json.two_factor_required) {
					showTokenStep();
					return;
				}

				if (!err && json && typeof json.error === 'string' && /CODE REQUIRED/i.test(json.error)) {
					showTokenStep();
					return;
				}

				if (!err && json && json.login) {
					allowNativeSubmit = true;
					form.submit();
					return;
				}

				showCredentialsStep();
				resetTurnstile();
				showPreflightNotice(
					err ? 'An error was encountered while trying to authenticate. Please try again.' :
						(json && (json.error || json.message) ? (json.error || json.message) : 'An error was encountered while trying to authenticate. Please try again.'),
					true
				);
			});
		}

		var existingRequiredError = form.querySelector('.um-error-code-wfls_twofactor_required');
		var existingInvalidCodeError = form.querySelector('.um-error-code-wfls_twofactor_failed');
		if (existingRequiredError || existingInvalidCodeError || (tokenInput && tokenInput.value)) {
			showTokenStep();
		}

		form.addEventListener(
			'submit',
			function (event) {
				runPrecheckAndMaybeSubmit(event);
			},
			true
		);

		form.addEventListener(
			'click',
			function (event) {
				var submitControl = event.target.closest('button[type="submit"], input[type="submit"]');
				if (!submitControl || !form.contains(submitControl)) {
					return;
				}
				runPrecheckAndMaybeSubmit(event);
			},
			true
		);

		form.addEventListener(
			'keydown',
			function (event) {
				if (event.key !== 'Enter') {
					return;
				}
				runPrecheckAndMaybeSubmit(event);
			},
			true
		);
	}

	function base64UrlToUint8Array(value) {
		var padding = '='.repeat((4 - (value.length % 4)) % 4);
		var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
		var binary = window.atob(base64);
		var bytes = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) {
			bytes[i] = binary.charCodeAt(i);
		}
		return bytes;
	}

	function arrayBufferToBase64Url(buffer) {
		var bytes = new Uint8Array(buffer);
		var binary = '';
		for (var i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	}

	function passkeyOptionsFromJSON(options) {
		var parsed = JSON.parse(JSON.stringify(options));
		parsed.challenge = base64UrlToUint8Array(parsed.challenge);
		if (parsed.allowCredentials) {
			for (var i = 0; i < parsed.allowCredentials.length; i++) {
				parsed.allowCredentials[i].id = base64UrlToUint8Array(parsed.allowCredentials[i].id);
			}
		}
		return parsed;
	}

	function credentialToJSON(credential) {
		return {
			id: credential.id,
			type: credential.type,
			rawId: arrayBufferToBase64Url(credential.rawId),
			response: {
				clientDataJSON: arrayBufferToBase64Url(credential.response.clientDataJSON),
				authenticatorData: arrayBufferToBase64Url(credential.response.authenticatorData),
				signature: arrayBufferToBase64Url(credential.response.signature),
				userHandle: credential.response.userHandle ? arrayBufferToBase64Url(credential.response.userHandle) : null
			}
		};
	}

	function attachPasskeyControl(wrapper) {
		if (wrapper.dataset.jditcPasskeyInitialized === '1') {
			return;
		}
		wrapper.dataset.jditcPasskeyInitialized = '1';

		var form = wrapper.closest('form');
		var button = wrapper.querySelector('[data-jditc-passkey-button]');
		var errorNode = wrapper.querySelector('[data-jditc-passkey-error]');
		var handoffInput = form ? form.querySelector('input[name="jditc-w2fa-passkey-handoff"]') : null;
		var ajaxURL = wrapper.getAttribute('data-jditc-ajax-url') || '';
		if (!form || !button || !handoffInput || !ajaxURL) {
			return;
		}

		function showError(message) {
			errorNode.textContent = message;
			errorNode.style.display = '';
		}

		button.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopImmediatePropagation();
			if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.get) {
				showError('Passkeys are not available in this browser.');
				return;
			}

			button.disabled = true;
			errorNode.style.display = 'none';
			var beginPayload = new URLSearchParams();
			beginPayload.append('action', 'wordfence_ls_begin_passkey_login');

			fetch(ajaxURL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: beginPayload.toString(),
				credentials: 'same-origin'
			})
				.then(function (response) {
					return response.json().then(function (json) {
						return json.data && json.success ? json.data : json;
					});
				})
				.then(function (beginResponse) {
					if (beginResponse.error) {
						throw new Error(beginResponse.error);
					}
					if (!beginResponse.options || !beginResponse.token) {
						throw new Error('Passkey login could not be started.');
					}
					return navigator.credentials.get({ publicKey: passkeyOptionsFromJSON(beginResponse.options) })
						.then(function (credential) {
							var finishPayload = new URLSearchParams();
							finishPayload.append('action', 'jditc_w2fa_finish_passkey_login');
							finishPayload.append('token', beginResponse.token);
							finishPayload.append('credential', JSON.stringify(credentialToJSON(credential)));
							finishPayload.append('rememberme', form.querySelector('input[name="rememberme"]:checked') ? '1' : '0');
							return fetch(ajaxURL, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
								body: finishPayload.toString(),
								credentials: 'same-origin'
							});
						});
				})
				.then(function (response) {
					return response.json().then(function (json) {
						return json.data && json.success ? json.data : json;
					});
				})
				.then(function (finishResponse) {
					if (finishResponse.error || !finishResponse.handoff) {
						throw new Error(finishResponse.error || finishResponse.message || 'Passkey login failed.');
					}
					handoffInput.value = finishResponse.handoff;
					form.submit();
				})
				.catch(function (error) {
					button.disabled = false;
					if (error.name !== 'NotAllowedError') {
						showError(error.message || 'Passkey login failed.');
					}
				});
		});
	}

	var wrappers = document.querySelectorAll('[data-jditc-w2fa="1"]');
	for (var i = 0; i < wrappers.length; i++) {
		attachToWrapper(wrappers[i]);
	}

	var passkeyWrappers = document.querySelectorAll('[data-jditc-passkey="1"]');
	for (var j = 0; j < passkeyWrappers.length; j++) {
		attachPasskeyControl(passkeyWrappers[j]);
	}
})();
