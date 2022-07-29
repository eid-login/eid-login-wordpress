/**
 * Registration wizard and admin settings for the eID-Login.
 *
 * @package eID-Login
 * @output  wp-content/plugins/eidlogin/admin/js/eidlogin-admin.js
 */

// an improved debounce function from http://modernjavascript.blogspot.com/2013/08/building-better-debounce.html
var debounce = function (func, wait) {
	var timeout, args, context, timestamp;
	return function () {
		context = this;
		args = [].slice.call(arguments, 0);
		timestamp = Date.now();
		var later = function () {
			var last = Date.now() - timestamp;
			if (last < wait) {
				timeout = setTimeout(later, wait - last);
			} else {
				timeout = null;
				func.apply(context, args);
			}
		};
		if (!timeout) {
			timeout = setTimeout(later, wait);
		}
	};
};

document.addEventListener('DOMContentLoaded', function (e) {
	// The text domain must not be passed as a variable!
	const { __, _x, _n, _nx } = wp.i18n;

	if (window.location.protocol !== 'https:') {
		document.getElementById('eidlogin-settings-notls').classList.remove('hidden');
		// Return, because the elements are not present in html.
		return;
	}

	// Grab the DOM elements.
	const dataSrc = document.getElementById('eidlogin-settings-datasrc');
	const wizard = document.getElementById('eidlogin-settings-wizard');
	const manual = document.getElementById('eidlogin-settings-manual');
	const buttonHelp = document.getElementById('eidlogin-settings-button-help');
	const buttonSelectSkid = document.getElementById('eidlogin-settings-button-select-skid');
	const inputMetaIdp = document.getElementById('eidlogin-settings-form-wizard-idp_metadata_url');
	const buttonToggleIdp = document.getElementById('eidlogin-settings-button-toggleidp');
	const skidCell1 = document.getElementById('eidlogin-settings-skid-cell-1');
	const skidCell2 = document.getElementById('eidlogin-settings-skid-cell-2');
	const buttonToggleSp = document.getElementById('eidlogin-settings-button-togglesp');
	const buttonWizardSave = document.getElementById('eidlogin-settings-button-next-3');
	const stepWizardSave = document.getElementById('eidlogin-settings-wizard-step-3');
	const stepWizardActivate = document.getElementById('eidlogin-settings-wizard-step-4');
	const buttonWizardActivate = document.getElementById('eidlogin-settings-button-next-4');
	const buttonWizardFinish = document.getElementById('eidlogin-settings-button-finish');
	const certActDiv = document.getElementById('eidlogin-settings-manual-div-cert-act');
	const certActEncDiv = document.getElementById('eidlogin-settings-manual-div-cert-act-enc');
	const certNewDiv = document.getElementById('eidlogin-settings-manual-div-cert-new');
	const certNewEncDiv = document.getElementById('eidlogin-settings-manual-div-cert-new-enc');
	const buttonRolloverPrep = document.getElementById('eidlogin-settings-button-rollover-prepare');
	const buttonRolloverExec = document.getElementById('eidlogin-settings-button-rollover-execute');
	const spanRolloverExec = document.getElementById('eidlogin-settings-span-rollover-execute');

	// Global const and vars
	const skidMetadataUrl = 'https://service.skidentity.de/fs/saml/metadata';
	const skidManagementUrl = 'https://sp.skidentity.de/';
	const txtShowIdp = __('Advanced Settings', 'eidlogin');
	const txtHideIdp = __('Hide Advanced Settings', 'eidlogin');
	const txtShowSp = __('Show Service Provider Metadata', 'eidlogin');
	const txtHideSp = __('Hide Service Provider Metadata', 'eidlogin');

	// Notification panels for the various messages.
	const msgPanel = document.getElementById('eidlogin-settings-msg-panel');
	const msgPanelPrep = document.getElementById('eidlogin-settings-msg-panel-prep');
	const msgPanelExec = document.getElementById('eidlogin-settings-msg-panel-exec');
	const msgDuration = 1000;

	// wpApiSettings is injected to Javascript via wp_localize_script.
	const apiUrl = wpApiSettings.root + 'eidlogin/v1/eidlogin-settings';

	/**
	 * Select skid and save instantly.
	 */
	function selectSkid(e) {
		document.getElementById(
			'eidlogin-settings-form-wizard-idp_metadata_url'
		).value = skidMetadataUrl;
		updateIdpSettings(e);
	}
	buttonSelectSkid.addEventListener('click', selectSkid);

	/**
	 * Switch the active wizard panel and reconfigure step links.
	 */
	function switchWizardPanel(panel) {
		panel = parseInt(panel);
		buttonToggleIdp.innerText = txtShowIdp;
		buttonToggleSp.innerText = txtShowSp;

		let steps = wizard.getElementsByClassName('step');
		[].forEach.call(steps, function (step) {
			step.classList.remove('active');
			// The help button should be always enabled.
			if (step.dataset.help !== 'help') {
				step.classList.add('disabled');
			}
			step.removeEventListener('click', switchPanelEventListener);
			step.removeEventListener('click', saveSettings);
		});

		let panels = wizard.getElementsByClassName('panel');
		[].forEach.call(panels, function (panel) {
			panel.classList.remove('active');
			panel.classList.add('hidden');
		});

		document.getElementById('eidlogin-settings-wizard-panel-' + panel).classList.remove('hidden');
		for (var i = 1; i <= parseInt(panel) + 1; i++) {
			// enable panel switching via step links
			if (i <= 4) {
				// enable form save via step link 3 coming from the start
				if (panel <= 2 && i == 3) {
					document
						.getElementById('eidlogin-settings-wizard-step-' + i)
						.addEventListener('click', saveSettings);
				} else {
					document
						.getElementById('eidlogin-settings-wizard-step-' + i)
						.addEventListener('click', switchPanelEventListener);
				}
				document.getElementById('eidlogin-settings-wizard-step-' + i).classList.remove('disabled');
			}
		}
		document.getElementById('eidlogin-settings-wizard-step-' + panel).classList.add('active');
	}

	/**
	 * Toggle the wizard help div.
	 */
	function toggleHelp() {
		const panelHelp = document.getElementById('eidlogin-settings-wizard-panel-help');
		if (panelHelp.classList.contains('hidden')) {
			panelHelp.classList.remove('hidden');
			buttonHelp.classList.add('active');
		} else {
			panelHelp.classList.add('hidden');
			buttonHelp.classList.remove('active');
		}
	}
	document
		.querySelectorAll('[data-help="help"]')
		.forEach((el) => el.addEventListener('click', toggleHelp));

	/**
	 * Switch the active wizard panel by buttons.
	 */
	function switchPanelEventListener(e) {
		e.preventDefault();
		// Don`t switch if we use skid, save or activate, is handled in saveSettings and activate.
		if (
			e.target === buttonSelectSkid ||
			e.target === buttonWizardSave ||
			e.target === buttonWizardActivate ||
			e.target === stepWizardActivate
		) {
			return;
		}
		switchWizardPanel(e.target.dataset.panel);
	}
	document
		.querySelectorAll('button[data-panel]')
		.forEach((el) => el.addEventListener('click', switchPanelEventListener));

	/**
	 * Fetch and replace idp metadata values when url is changed.
	 */
	function updateIdpSettings(e) {
		const sp_enforce_enc = document.getElementById('eidlogin-settings-form-wizard-sp_enforce_enc');
		const idp_cert_enc = document.getElementById('eidlogin-settings-form-wizard-idp_cert_enc');
		const idp_cert_sign = document.getElementById('eidlogin-settings-form-wizard-idp_cert_sign');
		const idp_entity_id = document.getElementById('eidlogin-settings-form-wizard-idp_entity_id');
		const idp_sso_url = document.getElementById('eidlogin-settings-form-wizard-idp_sso_url');
		const idp_ext_tr03130 = document.getElementById(
			'eidlogin-settings-form-wizard-idp_ext_tr03130'
		);

		if (inputMetaIdp.value === '') {
			sp_enforce_enc.value = '';
			idp_cert_enc.value = '';
			idp_cert_sign.value = '';
			idp_entity_id.value = '';
			idp_sso_url.value = '';
			idp_ext_tr03130.value = '';
			return;
		}

		// Disable button while fetching the metadata.
		buttonWizardSave.disabled = true;
		stepWizardSave.classList.add('disabled');

		var xhr = new XMLHttpRequest();
		// Don't overwrite the "outer" e variable!
		xhr.addEventListener('load', (e2) => {
			sp_enforce_enc.value = '';
			idp_cert_enc.value = '';
			idp_cert_sign.value = '';
			idp_entity_id.value = '';
			idp_sso_url.value = '';
			idp_ext_tr03130.value = '';

			if (e2.target.status == 200) {
				const idpMetadata = JSON.parse(e2.target.responseText);
				idp_cert_enc.value = idpMetadata.idp_cert_enc;
				idp_cert_sign.value = idpMetadata.idp_cert_sign;
				idp_entity_id.value = idpMetadata.idp_entity_id;
				idp_sso_url.value = idpMetadata.idp_sso_url;

				if (e.target == buttonSelectSkid) {
					saveSettings(e);
				}
			}

			buttonWizardSave.disabled = false;
			stepWizardSave.classList.remove('disabled');
		});

		xhr.addEventListener('error', (e) => {
			alert(__('Identity Provider settings could not be fetched'));
		});

		var idpMetaURL = inputMetaIdp.value;
		idpMetaURL = encodeURIComponent(idpMetaURL);
		idpMetaURL = btoa(idpMetaURL);
		// wpApiSettings is injected to Javascript via wp_localize_script.
		const idpMetadataApiUrl =
			wpApiSettings.root + 'eidlogin/v1/eidlogin-idp-metadata/' + idpMetaURL;

		xhr.open('GET', idpMetadataApiUrl, true);
		xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
		xhr.send();
	}
	inputMetaIdp.addEventListener('input', debounce(updateIdpSettings, 200));

	/**
	 * Toggle idp settings in configure panel.
	 */
	function toggleIdp(e) {
		e.preventDefault();
		const panelIdpSettings = document.getElementById('eidlogin-settings-wizard-panel-idp_settings');
		if (panelIdpSettings.classList.contains('hidden')) {
			panelIdpSettings.classList.remove('hidden');
			buttonToggleIdp.innerText = txtHideIdp;
		} else {
			panelIdpSettings.classList.add('hidden');
			buttonToggleIdp.innerText = txtShowIdp;
		}
	}
	buttonToggleIdp.addEventListener('click', toggleIdp);

	/**
	 * Save the settings with a post of the form to the REST API.
	 */
	function saveSettings(e) {
		// Prevent regular form submit.
		e.preventDefault();

		// Maybe we need to switch panel.
		const switchPanel = e.target.dataset.panel == '3';
		const errorMsg = 'Settings could not be saved';

		var xhr = new XMLHttpRequest();
		xhr.addEventListener('load', (e) => {
			let resp = JSON.parse(e.target.responseText);

			if (e.target.status == 200 && resp.status == 'success') {
				msgPanel.classList.remove('hidden');
				msgPanel.innerText = resp.message;
				setTimeout(function () {
					msgPanel.classList.add('hidden');
				}, msgDuration);

				// Maybe we need to switch panel.
				if (switchPanel) {
					// Display the sp_entity_id.
					let value = document.getElementById('eidlogin-settings-form-wizard-sp_entity_id').value;
					document.getElementById(
						'eidlogin-settings-wizard-display-sp_entity_id'
					).innerText = value;

					// Hide the skid button and it`s text, if we don't have skid as configured idp.
					if (inputMetaIdp.value === skidMetadataUrl) {
						skidCell1.classList.remove('hidden');
						skidCell2.classList.remove('hidden');
					} else {
						skidCell1.classList.add('hidden');
						skidCell2.classList.add('hidden');
					}

					switchWizardPanel(3);
				}
			} else {
				alert(resp.data.params.data);
			}
		});

		xhr.addEventListener('error', (e) => {
			alert(errorMsg);
		});

		xhr.open('POST', apiUrl, true);

		xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
		xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);

		// Decide from which form the values should be used (wizard vs. manual).
		let form;
		if (wizard.classList.contains('hidden')) {
			form = 'eidlogin-settings-form-manual-';
		} else {
			form = 'eidlogin-settings-form-wizard-';
		}

		let activated = false;
		if (
			document.getElementById(form + 'activated') &&
			document.getElementById(form + 'activated').checked
		) {
			activated = true;
		}

		let sp_enforce_enc = false;
		if (
			document.getElementById(form + 'sp_enforce_enc') &&
			document.getElementById(form + 'sp_enforce_enc').checked
		) {
			sp_enforce_enc = true;
		}

		let eid_delete = false;
		if (document.getElementById('eidlogin-settings-form-manual-eid_delete').value === 'true') {
			eid_delete = true;
		}

		let data = JSON.stringify({
			action: 'save',
			data: {
				activated: activated,
				sp_entity_id: document.getElementById(form + 'sp_entity_id').value,
				sp_enforce_enc: sp_enforce_enc,
				idp_entity_id: document.getElementById(form + 'idp_entity_id').value,
				idp_sso_url: document.getElementById(form + 'idp_sso_url').value,
				idp_cert_sign: document.getElementById(form + 'idp_cert_sign').value,
				idp_cert_enc: document.getElementById(form + 'idp_cert_enc').value,
				idp_ext_tr03130: document.getElementById(form + 'idp_ext_tr03130').value,
				eid_delete: eid_delete,
			},
		});

		xhr.send(data);
	}
	buttonWizardSave.addEventListener('click', saveSettings);

	/**
	 * Open skid in a new tab/win.
	 */
	function openSkid(event) {
		event.preventDefault();
		window.open(skidManagementUrl,'_blank');
	}
	document.getElementById('eidlogin-settings-button-skid').addEventListener('click', openSkid);

	// Activate the eID-Login after security question.
	function activate(e) {
		let msg = __(
			'Please confirm that the Service Provider has been registered at the Identity Provider. Pressing the "Next" button will activate the eID-Login.',
			'eidlogin'
		);

		if (confirm(msg)) {
			var xhr = new XMLHttpRequest();
			xhr.addEventListener('load', (e) => {
				let resp = JSON.parse(e.target.responseText);
				if (e.target.status == 200 && resp.status == 'success') {
					switchWizardPanel(4);
				} else {
					alert(resp.message);
				}
			});

			xhr.addEventListener('error', (e) => {
				alert('Settings could not be activated');
			});

			// wpApiSettings is injected to Javascript via wp_localize_script.
			const activateApiUrl = wpApiSettings.root + 'eidlogin/v1/eidlogin-activate';
			xhr.open('GET', activateApiUrl, true);
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
			xhr.send();
		} else {
			switchWizardPanel(3);
		}
	}
	buttonWizardActivate.addEventListener('click', activate);
	stepWizardActivate.addEventListener('click', activate);

	// Finish the wizard with a page reload.
	function finish(e) {
		window.location.reload();
	}
	buttonWizardFinish.addEventListener('click', finish);

	/**
	 * Toggle sp metadata in register panel.
	 */
	function toggleSp(e) {
		e.preventDefault();
		const spPanel = document.getElementById('eidlogin-settings-wizard-panel-register-sp');

		if (spPanel.classList.contains('hidden')) {
			const errMsg = __('Service Provider metadata could not be fetched', 'eidlogin');
			const url = '/wp-login.php?saml_metadata';

			var xhr = new XMLHttpRequest();
			xhr.addEventListener('load', (e) => {
				if (e.target.status == 200) {
					var spMetadata = e.target.responseText;
					var spMetadataPre = document.getElementById(
						'eidlogin-settings-wizard-panel-register-sp-metadata'
					);
					spMetadataPre.innerText = '';
					spMetadataPre.appendChild(document.createTextNode(spMetadata));
				} else {
					showError(errMsg);
				}
			});

			xhr.addEventListener('error', (e) => {
				showError(errMsg);
			});

			xhr.open('GET', url, true);
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
			xhr.send();

			buttonToggleSp.innerText = txtHideSp;
			spPanel.classList.remove('hidden');
		} else {
			buttonToggleSp.innerText = txtShowSp;
			spPanel.classList.add('hidden');
		}
	}
	buttonToggleSp.addEventListener('click', toggleSp);

	// Handler for the "create eID" button.
	function createEid() {
		// window.location.host doesn't work here if WP is installed in a sub-directory.
		// Use the base URL of the REST API instead (https://example.com/wordpress/index.php/wp-json/).
		let wproot = wpApiSettings.root;
		let pos = wproot.indexOf('index');
		wproot = wproot.substring(0, pos);
		wproot += 'wp-login.php?saml_register';
		window.location.href = wproot;
	}
	document
		.getElementById('eidlogin-settings-button-eid-create')
		.addEventListener('click', createEid);

	/*
	 * Save the settings after checking about the deletion of existing eIDs.
	 */
	function confirmSave(e) {
		e.preventDefault();

		let msgConfirm = __(
			'Changing the Identity Provider Settings will very likely make existing eID connections not work anymore, as they are bound to a specific Identity Provider! You maybe should make a backup of the settings before saving! Are you sure you want to save now?',
			'eidlogin'
		);

		if (confirm(msgConfirm)) {
			let msgDelete = __('Should all existing eID connections be deleted?', 'eidlogin');

			const inputEidDelete = document.getElementById('eidlogin-settings-form-manual-eid_delete');
			inputEidDelete.value = 'false';

			if (confirm(msgDelete)) {
				inputEidDelete.value = 'true';
			}

			saveSettings(e);
		}
	}
	document
		.getElementById('eidlogin-settings-button-manual-save')
		.addEventListener('click', confirmSave);

	/**
	 * Reset the settings with a post of the form to SettingsController.
	 */
	function resetSettings(e) {
		e.preventDefault();

		let msg = __(
			'Reset of settings will also delete eID connections of all accounts. After this no account will be able to use the eID-Login anymore and all users must create a new eID connection! Are you sure?',
			'eidlogin'
		);

		if (confirm(msg)) {
			var xhr = new XMLHttpRequest();
			xhr.addEventListener('load', (e) => {
				let resp = JSON.parse(e.target.responseText);
				if (e.target.status == 200 && resp.status == 'success') {
					msgPanel.classList.remove('hidden');
					msgPanel.innerText = resp.message;
					setTimeout(function () {
						msgPanel.classList.add('hidden');
						window.location.reload();
					}, msgDuration);
				} else {
					alert(resp.message);
				}
			});

			xhr.addEventListener('error', (e) => {
				alert('Settings could not be reset');
			});

			xhr.open('POST', apiUrl, true);

			xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);

			let data = JSON.stringify({
				action: 'reset',
			});

			xhr.send(data);
		}
	}
	document
		.getElementById('eidlogin-settings-button-reset')
		.addEventListener('click', resetSettings);

	/**
	 * Prepare a SAML Certificate Rollover.
	 */
	function prepRollover(e) {
		e.preventDefault();

		let msg = __(
			'This will create new certificates which will be propagated in the Service Provider SAML Metadata. Are you sure?',
			'eidlogin'
		);

		if (certNewPresent === 'true') {
			msg = __(
				'This will replace the already prepared certificates and replace them with a new ones which will be propagated in the Service Provider SAML Metadata. Are you sure?',
				'eidlogin'
			);
		}

		if (confirm(msg)) {
			const errorMsg = 'Certificate Rollover could not be prepared';
			var xhr = new XMLHttpRequest();
			xhr.addEventListener('load', (e) => {
				let resp = JSON.parse(e.target.responseText);
				if (e.target.status == 200 && resp.status == 'success') {
					certNewDiv.innerText = '... ' + resp.cert_new;
					certNewEncDiv.innerText = '... ' + resp.cert_new_enc;
					buttonRolloverExec.disabled = false;
					spanRolloverExec.classList.add('hidden');

					msgPanelPrep.classList.remove('hidden');
					msgPanelPrep.innerText = resp.message;
					setTimeout(function () {
						msgPanelPrep.classList.add('hidden');
					}, msgDuration);
				} else {
					alert(errorMsg);
				}
			});

			xhr.addEventListener('error', (e) => {
				alert(errorMsg);
			});

			// wpApiSettings is injected to Javascript via wp_localize_script.
			const prepareRolloverApiUrl = wpApiSettings.root + 'eidlogin/v1/eidlogin-preparerollover';

			xhr.open('GET', prepareRolloverApiUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
			xhr.send();
		}
	}
	buttonRolloverPrep.addEventListener('click', prepRollover);

	/**
	 * Execute a SAML Certificate Rollover.
	 */
	function execRollover(e) {
		e.preventDefault();

		let msg = __(
			'This will remove the currently used certificates from the Service Provider SAML Metadata and activate the prepared certificates. Are you sure?',
			'eidlogin'
		);

		if (confirm(msg)) {
			const errorMsg = 'Certificate Rollover could not be executed';
			var xhr = new XMLHttpRequest();
			xhr.addEventListener('load', (e) => {
				let resp = JSON.parse(e.target.responseText);
				if (e.target.status == 200 && resp.status == 'success') {
					certActDiv.innerText = '... ' + resp.cert_act;
					certActEncDiv.innerText = '... ' + resp.cert_act_enc;
					certNewDiv.innerText = __('No new certificate prepared yet.', 'eidlogin');
					certNewEncDiv.innerText = __('No new certificate prepared yet.', 'eidlogin');

					buttonRolloverExec.disabled = true;
					spanRolloverExec.classList.remove('hidden');

					msgPanelExec.classList.remove('hidden');
					msgPanelExec.innerText = resp.message;
					setTimeout(function () {
						msgPanelExec.classList.add('hidden');
					}, msgDuration);
				} else {
					alert(errorMsg);
				}
			});

			xhr.addEventListener('error', (e) => {
				alert(errorMsg);
			});

			// wpApiSettings is injected to Javascript via wp_localize_script.
			const executeRolloverApiUrl = wpApiSettings.root + 'eidlogin/v1/eidlogin-executerollover';

			xhr.open('GET', executeRolloverApiUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
			xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
			xhr.send();
		}
	}
	buttonRolloverExec.addEventListener('click', execRollover);

	// Decide which div to show depending on state of settings.
	const settingsPresent = dataSrc.dataset.present;
	const certActPresent = dataSrc.dataset.act_cert;
	const certNewPresent = dataSrc.dataset.new_cert;

	if (settingsPresent !== 'true') {
		switchWizardPanel(1);
		manual.classList.add('hidden');
		wizard.classList.remove('hidden');
		// Prefill SP EntityID in wizard.
		document.getElementById('eidlogin-settings-form-wizard-sp_entity_id').value =
			window.location.protocol + '//' + window.location.host;
	} else {
		wizard.classList.add('hidden');
		manual.classList.remove('hidden');
		// Decide about rollover div.
		if (certActPresent === 'true') {
			document.getElementById('eidlogin-settings-manual-div-rollover').classList.remove('hidden');
		}
		// Decide about showing new cert and key rollover execute button state.
		if (certNewPresent === 'true') {
			buttonRolloverExec.disabled = false;
			spanRolloverExec.classList.add('hidden');
		}
	}
});
