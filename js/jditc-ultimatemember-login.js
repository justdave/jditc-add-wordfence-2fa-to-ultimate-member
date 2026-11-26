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
		var allowReusedTurnstileToken = wrapper.dataset.jditcReusedTurnstileToken === '1';
		var ajaxURL = wrapper.getAttribute('data-jditc-ajax-url') || '';
		if (!ajaxURL) {
			return;
		}

		var stepTwoActive = wrapper.style.display !== 'none';
		var precheckInFlight = false;
		var twoFactorTurnstileWidget = null;
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

		function sanitizeNotice(message) {
			var noticeMessage = typeof message === 'string' ? message : '';
			var purifier      = window['DOMPurify'];
			var fallback      = document.createDocumentFragment();

			fallback.textContent = noticeMessage;
			if (!purifier || typeof purifier.sanitize !== 'function' || purifier.isSupported === false) {
				return fallback;
			}

			try {
				var sanitizedContent = purifier.sanitize(
					noticeMessage.replace(/\r\n?|\n/g, '<br>'),
					{
						ALLOWED_TAGS: [ 'a', 'br', 'em', 'strong' ],
						ALLOWED_ATTR: [ 'href' ],
						ALLOW_DATA_ATTR: false,
						ALLOW_ARIA_ATTR: false,
						ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|\/(?!\/)|#|(?:\.\.?\/)|(?:[^\s:/?#]+(?:[/?#]|$)))/i,
						RETURN_DOM_FRAGMENT: true
					}
				);
				return sanitizedContent && sanitizedContent.nodeType === Node.DOCUMENT_FRAGMENT_NODE ? sanitizedContent : fallback;
			} catch (error) {
				return fallback;
			}
		}

		function showPreflightNotice(message, isError) {
			clearPreflightNotice();

			var notice = document.createElement('p');
			notice.className = isError ? 'um-notice err' : 'um-notice warning';
			notice.setAttribute('data-jditc-preflight-notice', '1');
			notice.style.whiteSpace = 'pre-line';
			notice.appendChild(sanitizeNotice(message));

			var firstField = form.querySelector('.um-field');
			if (firstField) {
				firstField.parentNode.insertBefore(notice, firstField);
			} else {
				form.insertBefore(notice, form.firstChild);
			}
		}

		function setTurnstileVisibility(visible) {
			// If various integrations added multiple Turnstile widgets to the same form, keep them visible.
			if (!visible && form.querySelectorAll('.cf-turnstile').length > 1) {
				return;
			}

			var nodes = form.querySelectorAll([
				'.cf-turnstile',
				'.cf-turnstile-br',
				'.cfturnstile-widget-label',
				'.cf-turnstile-failed-text'
			].join(', '));

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

		function getTurnstileInput() {
			// PHP uses the last submitted response when duplicate fields are present.
			var controls = form.elements;
			for (var i = controls.length - 1; i >= 0; i--) {
				var control = controls[i];
				if (control.tagName === 'INPUT' && control.type === 'hidden' && control.name === 'cf-turnstile-response' && !control.matches(':disabled')) {
					return control;
				}
			}

			return null;
		}

		function getTurnstileWidget(input) {
			return input ? input.closest('.cf-turnstile') : null;
		}

		function resetTurnstile(widget) {
			if (!widget || !window.turnstile || typeof window.turnstile.reset !== 'function') {
				return false;
			}

			try {
				window.turnstile.reset(widget);
				return true;
			} catch (error) {
				return false;
			}
		}

		// A successful preflight can consume the token; the final UM POST needs a fresh one.
		function refreshTurnstileForFinalSubmit(previousToken, widget, onDone) {
			if (!previousToken) {
				onDone(true);
				return;
			}

			var input = getTurnstileInput();
			if (!widget || getTurnstileWidget(input) !== widget || !resetTurnstile(widget)) {
				onDone(false);
				return;
			}

			var startedAt = Date.now();
			var noticeShown = false;
			var timer = window.setInterval(function () {
				var refreshedInput = getTurnstileInput();
				if (getTurnstileWidget(refreshedInput) === widget && refreshedInput.value &&
					(refreshedInput.value !== previousToken || allowReusedTurnstileToken)) {
					window.clearInterval(timer);
					onDone(true);
				} else if (Date.now() - startedAt >= 120000) {
					window.clearInterval(timer);
					onDone(false);
				} else if (!noticeShown && Date.now() - startedAt >= 3000) {
					noticeShown = true;
					showPreflightNotice('Security check in progress. Please complete it if prompted.', false);
				}
			}, 100);
		}

		function turnstileReadyForTwoFactor() {
			if (!twoFactorTurnstileWidget) {
				return true;
			}

			var input = getTurnstileInput();
			var widget = getTurnstileWidget(input);
			var available = twoFactorTurnstileWidget.isConnected && (!widget || widget === twoFactorTurnstileWidget) &&
				window.turnstile && typeof window.turnstile.reset === 'function';
			var expired = false;
			if (available && widget && typeof window.turnstile.isExpired === 'function') {
				try {
					expired = window.turnstile.isExpired(widget);
				} catch (error) {
					// The server remains responsible for validating the submitted token.
				}
			}

			if (available && widget && input.value && !expired) {
				return true;
			}

			setTurnstileVisibility(true);
			if (expired) {
				resetTurnstile(widget);
			}

			showPreflightNotice(available ? 'Please complete the security check, then try again.' :
				'We couldn’t load the security check. Please refresh the page and try again.', !available);
			return false;
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

			var turnstileInput = getTurnstileInput();
			setTurnstileVisibility(!turnstileInput || !turnstileInput.value);
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
			twoFactorTurnstileWidget = null;
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

			var turnstileInput = getTurnstileInput();
			var turnstileToken = turnstileInput ? turnstileInput.value : '';
			var turnstileWidget = getTurnstileWidget(turnstileInput);
			if (turnstileToken) {
				payload.append('cf-turnstile-response', turnstileToken);
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
					onDone(null, json, turnstileToken, turnstileWidget);
				}, function (err) {
					onDone(err, null, turnstileToken, turnstileWidget);
				});
		}

		function stopSubmitEvent(event) {
			if (event) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		}

		function runPrecheckAndMaybeSubmit(event) {
			if (stepTwoActive) {
				if (!turnstileReadyForTwoFactor()) {
					stopSubmitEvent(event);
				}
				return;
			}

			if (precheckInFlight) {
				stopSubmitEvent(event);
				return;
			}

			var usernameInput = findUsernameInput();
			var passwordInput = findPasswordInput();
			if (!usernameInput || !passwordInput) {
				return;
			}

			var username = usernameInput.value || '';
			var password = passwordInput.value || '';
			if (!username || !password) {
				return;
			}

			stopSubmitEvent(event);

			clearPreflightNotice();
			precheckInFlight = true;

			doWordfencePrecheck(username, password, function (err, json, turnstileToken, turnstileWidget) {
				var needsTwoFactor = !err && json && (json.two_factor_required ||
					(typeof json.error === 'string' && /CODE REQUIRED/i.test(json.error)));
				if (needsTwoFactor || (!err && json && json.login)) {
					refreshTurnstileForFinalSubmit(turnstileToken, turnstileWidget, function (refreshed) {
						precheckInFlight = false;
						if (!refreshed) {
							showCredentialsStep();
							resetTurnstile(turnstileWidget);
							showPreflightNotice('We couldn’t complete the security check. Please refresh the page and try again.', true);
							return;
						}

						clearPreflightNotice();
						if (needsTwoFactor) {
							twoFactorTurnstileWidget = turnstileToken ? turnstileWidget : null;
							showTokenStep();
						} else {
							form.submit();
						}
					});

					return;
				}

				precheckInFlight = false;
				showCredentialsStep();
				if (turnstileToken) {
					resetTurnstile(turnstileWidget);
				}

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
