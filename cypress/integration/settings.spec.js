// Set baseUrl in cypress.json

const parser = require('fast-xml-parser');

const prefix = '#eidlogin-settings-';
const skidMetaUrl = 'https://service.skidentity.de/fs/saml/metadata';

describe('Test admin related eID settings', () => {
	before(() => {
		cy.task('db:modify_options', 'clear');
	});

	beforeEach(() => {
		cy.task('db:modify_options', 'clear');
		cy.visit('/wp-login.php');
		cy.get('[data-cy=perso]').should('not.exist');
		cy.login('admin', 'admin');
	});

	it('Use wizard back and forth', () => {
		// Fetch SkIDentity metadata for later comparison.
		cy.request(skidMetaUrl).then((response) => {
			var idpMetadata = parser.parse(response.body, { ignoreAttributes: false });

			var nsFirst = '';
			Object.keys(idpMetadata).forEach((key) => {
				nsFirst = key.match('.*(?=:)')[0];
			});

			var entDesc = idpMetadata[nsFirst + ':EntityDescriptor'];

			var nsSecond = '';
			Object.keys(entDesc).forEach((key) => {
				var match = key.match('.*(?=:)');
				if (match != null) {
					nsSecond = match[0];
					return;
				}
			});

			cy.wrap(entDesc['@_entityID']).as('idp_entity_id');
			var ssoDesc = entDesc[nsSecond + ':IDPSSODescriptor'];
			cy.wrap(ssoDesc[nsSecond + ':SingleSignOnService'][0]['@_Location']).as('idp_sso_url');
			var keyDesc = ssoDesc[nsSecond + ':KeyDescriptor'];

			var nsThird = '';
			Object.keys(keyDesc).forEach((key) => {
				if (keyDesc[key]['@_use'] == 'signing') {
					Object.keys(keyDesc[key]).forEach((key2) => {
						var match = key2.match('.*(?=:)');
						if (match != null) {
							nsThird = match[0];
							return;
						}
					});
				}
			});

			Object.keys(keyDesc).forEach((key) => {
				if (keyDesc[key]['@_use'] == 'signing') {
					cy.wrap(
						keyDesc[key][nsThird + ':KeyInfo'][nsThird + ':X509Data'][nsThird + ':X509Certificate']
					).as('idp_cert_sign');
				}
				if (keyDesc[key]['@_use'] == 'encryption') {
					cy.wrap(
						keyDesc[key][nsThird + ':KeyInfo'][nsThird + ':X509Data'][nsThird + ':X509Certificate']
					).as('idp_cert_enc');
				}
			});
		});

		cy.visit('/wp-admin/options-general.php?page=eidlogin-settings');

		// Overview panel at start
		cy.get(`${prefix}wizard`).should('be.visible');
		cy.get(`${prefix}wizard-panel-1`).should('be.visible');

		// Show / hide info panel
		cy.get(`${prefix}wizard-panel-help`).should('not.be.visible');
		cy.get(`${prefix}button-help`).should('not.have.class', 'active');
		// Use force:true to prevent error "the center of this element is hidden from view".
		cy.get(`${prefix}button-help`).click({ force: true });
		cy.get(`${prefix}wizard-panel-help`).should('be.visible');
		cy.get(`${prefix}button-help`).should('have.class', 'active');
		cy.get(`${prefix}button-help`).click({ force: true });
		cy.get(`${prefix}wizard-panel-help`).should('not.be.visible');
		cy.get(`${prefix}button-help`).click({ force: true });
		cy.get(`${prefix}wizard-panel-help`).should('be.visible');
		cy.get(`${prefix}button-close-help`).click({ force: true });
		cy.get(`${prefix}wizard-panel-help`).should('not.be.visible');

		// Check wizard navigation steps
		cy.get(`${prefix}wizard-step-3`).should('have.class', 'disabled');
		cy.get(`${prefix}wizard-step-4`).should('have.class', 'disabled');
		cy.get(`${prefix}wizard-step-2`).click();
		cy.get(`${prefix}wizard-step-3`).should('not.have.class', 'disabled');
		cy.get(`${prefix}wizard-step-4`).should('have.class', 'disabled');
		cy.get(`${prefix}wizard-panel-2`).should('be.visible');
		cy.get(`${prefix}wizard-step-1`).click();
		cy.get(`${prefix}wizard-panel-1`).should('be.visible');

		// Configure IDP with the metadata URL of SkIDentity and compare to the fetched values.
		cy.get(`${prefix}button-next-2`).click();
		cy.get(`${prefix}wizard-panel-2`).should('be.visible');
		cy.get(`${prefix}form-wizard-sp_entity_id`).should('have.value', Cypress.config().baseUrl);
		cy.get(`${prefix}form-wizard-sp_enforce_enc`).should('not.be.checked');
		cy.get(`${prefix}form-wizard-idp_metadata_url`).type(skidMetaUrl);
		cy.get(`${prefix}button-toggleidp`).click();
		cy.get(`${prefix}wizard-panel-idp_settings`).should('be.visible');

		cy.get('@idp_entity_id').then((idp_entity_id) => {
			cy.get(`${prefix}form-wizard-idp_entity_id`).should('have.value', idp_entity_id);
		});
		cy.get('@idp_sso_url').then((idp_sso_url) => {
			cy.get(`${prefix}form-wizard-idp_sso_url`).should('have.value', idp_sso_url);
		});
		cy.get('@idp_cert_sign').then((idp_cert_sign) => {
			cy.get(`${prefix}form-wizard-idp_cert_sign`).should('have.value', idp_cert_sign);
		});
		cy.get('@idp_cert_enc').then((idp_cert_enc) => {
			cy.get(`${prefix}form-wizard-idp_cert_enc`).should('have.value', idp_cert_enc);
		});

		cy.get(`${prefix}form-wizard-idp_ext_tr03130`).should('be.empty');
		cy.get(`${prefix}button-toggleidp`).click();
		cy.get(`${prefix}wizard-panel-idp_settings`).should('not.be.visible');
		// Fetched values should be saved as they are valid and result in correct SP entityId.
		cy.get(`${prefix}button-next-3`).click();
		cy.get(`${prefix}wizard-panel-3`).should('be.visible');
		cy.get(`${prefix}wizard-display-sp_entity_id`).contains(Cypress.config().baseUrl);

		// Test back buttons
		cy.get(`${prefix}button-back-2`).click();
		cy.get(`${prefix}wizard-panel-2`).should('be.visible');
		cy.get(`${prefix}button-back-1`).click();
		cy.get(`${prefix}wizard-panel-1`).should('be.visible');

		// Use SkIDentity button, should also result in valid and saved values.
		cy.get(`${prefix}button-select-skid`).click();
		cy.get(`${prefix}wizard-panel-3`).should('be.visible');
		// Go back and check fetched values.
		cy.get(`${prefix}button-back-2`).click();
		cy.get(`${prefix}wizard-panel-2`).should('be.visible');

		cy.get('@idp_entity_id').then((idp_entity_id) => {
			cy.get(`${prefix}form-wizard-idp_entity_id`).should('have.value', idp_entity_id);
		});
		cy.get('@idp_sso_url').then((idp_sso_url) => {
			cy.get(`${prefix}form-wizard-idp_sso_url`).should('have.value', idp_sso_url);
		});
		cy.get('@idp_cert_sign').then((idp_cert_sign) => {
			cy.get(`${prefix}form-wizard-idp_cert_sign`).should('have.value', idp_cert_sign);
		});
		cy.get('@idp_cert_enc').then((idp_cert_enc) => {
			cy.get(`${prefix}form-wizard-idp_cert_enc`).should('have.value', idp_cert_enc);
		});
		cy.get(`${prefix}form-wizard-idp_ext_tr03130`).should('be.empty');

		// Proceed to last step with first aborting then confirming the security question.
		cy.get(`${prefix}button-next-3`).click();
		cy.get(`${prefix}wizard-panel-3`).should('be.visible');

		let count = 0;

		// Intercept the window confirm event.
		cy.on('window:confirm', ($str) => {
			count += 1;

			switch (count) {
				case 1:
					// First click on next: simulate cancel button.
					return false;
				case 2:
					// Second click on next: simulate ok button.
					return true;
			}
		});

		cy.get(`${prefix}button-next-4`).click();
		cy.get(`${prefix}wizard-panel-3`).should('be.visible');

		cy.get(`${prefix}button-next-4`).click();
		cy.get(`${prefix}wizard-panel-4`).should('be.visible');

		// Finish configuration, should show manual mode.
		cy.get(`${prefix}button-finish`).click();
		cy.get(`${prefix}manual`).should('be.visible');

		// Check for eID-Login button.
		cy.logout();
		cy.get('[data-cy=perso]').should('be.visible');
	});

	it('Test eidlogin (de-) activation', () => {
		cy.task('db:modify_options', 'add');
		cy.visit('/wp-admin/options-general.php?page=eidlogin-settings');

		// Deactivate eidlogin.
		cy.get(`${prefix}form-manual-activated`).should('be.checked');
		cy.get(`${prefix}form-manual-activated`).uncheck();
		cy.get(`${prefix}button-manual-save`).click();

		// Check if eID button is hidden.
		cy.logout();
		cy.get('[data-cy=perso]').should('not.exist');

		cy.login('admin', 'admin');
		cy.visit('/wp-admin/options-general.php?page=eidlogin-settings');

		// Activate eidlogin.
		cy.get(`${prefix}form-manual-activated`).should('not.be.checked');
		cy.get(`${prefix}form-manual-activated`).check();
		cy.get(`${prefix}form-manual-activated`).should('be.checked');
		cy.get(`${prefix}button-manual-save`).click();

		// Check if eID button is visible.
		cy.logout();
		cy.get('[data-cy=perso]').should('be.visible');
	});

	it('Test manual config with form value validation', () => {
		cy.task('db:modify_options', 'add');
		cy.visit('/wp-admin/options-general.php?page=eidlogin-settings');

		// Check if the asterisk of the SP entityID label is present.
		cy.get(`${prefix}manual-sp > .form-table > tbody > :nth-child(1) > th > label`).contains('*');
		// Check if the asterisk of the IDP entityID label is present.
		cy.get(`${prefix}manual-idp > .form-table > tbody > :nth-child(1) > th > label`).contains('*');
		// Check if the asterisk of the IDP SSO URL label is present.
		cy.get(`${prefix}manual-idp > .form-table > tbody > :nth-child(2) > th > label`).contains('*');
		// Check if the asterisk of the IDP Cert label is present.
		cy.get(`${prefix}manual-idp > .form-table > tbody > :nth-child(3) > th > label`).contains('*');

		// Remove the form values and evaluate the different errors later.
		cy.get(`${prefix}form-manual-sp_entity_id`).clear();
		cy.get(`${prefix}form-manual-idp_entity_id`).clear();
		cy.get(`${prefix}form-manual-idp_sso_url`).clear();
		cy.get(`${prefix}form-manual-idp_cert_sign`).clear();

		// Don't try to evaluate alerts with Cypress!! Randomly the error "null is
		// not a spy or a call to a spy!" occurs. None of the solutions worked,
		// including those from the official docs. Use cy.Intercept instead!

		cy.intercept('/index.php?rest_route=/eidlogin/v1/eidlogin-settings').as('settings');

		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal('Die EntityID des Service Providers fehlt.');
			});

		// Filling sp_entity_id correctly and check next error message.
		cy.get(`${prefix}form-manual-sp_entity_id`).type('https://wordpress.p396.de');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal('Die EntityID des Identity Providers fehlt.');
			});

		// Filling idp_entity_id correctly and check next error message.
		cy.get(`${prefix}form-manual-idp_entity_id`).type(
			'https://service.skidentity.de/fs/saml/metadata'
		);
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Ungültige Identity Provider Single Sign-On URL.'
				);
			});

		// Filling idp_sso_url with invalid URL and check next error message.
		cy.get(`${prefix}form-manual-idp_sso_url`).type('foobar');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Ungültige Identity Provider Single Sign-On URL.'
				);
			});

		// Filling idp_sso_url with non TLS URL and check next error message.
		cy.get(`${prefix}form-manual-idp_sso_url`).clear();
		cy.get(`${prefix}form-manual-idp_sso_url`).type('http://foobar.com');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Ungültige Identity Provider Single Sign-On URL.'
				);
			});

		// Filling idp_sso_url correctly and check next error message.
		cy.get(`${prefix}form-manual-idp_sso_url`).clear();
		cy.get(`${prefix}form-manual-idp_sso_url`).type('https://foobar.com');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das Signatur-Zertifikat des Identity Providers fehlt.'
				);
			});

		// Filling idp_cert_sign incorrectly and check next error message.
		cy.get(`${prefix}form-manual-idp_cert_sign`).clear();
		cy.get(`${prefix}form-manual-idp_cert_sign`).type('foobar');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das Signaturzertifikat des Identity Providers konnte nicht gelesen werden.'
				);
			});

		// Filling idp_cert_sign with 1024 bit cert and check next error message.
		cy.get(`${prefix}form-manual-idp_cert_sign`).clear();
		cy.get(
			`${prefix}form-manual-idp_cert_sign`
		).type(
			'MIIBKTCB1KADAgECAgRglScoMA0GCSqGSIb3DQEBCwUAMBwxGjAYBgNVBAMMEXRlc3QtY2VydCByc2EgNTEyMB4XDTIxMDUwNzExNDAyNFoXDTIyMDUwNzExNDAyNFowHDEaMBgGA1UEAwwRdGVzdC1jZXJ0IHJzYSA1MTIwXDANBgkqhkiG9w0BAQEFAANLADBIAkEA0LP4k6cbOL1xSs432wj9YB/TB3BkO7j7fxelkqJZNPTtWrMlj1L+3qpPAuGdhXkj689o38Rbk9yOpqq4FlN11QIDAQABMA0GCSqGSIb3DQEBCwUAA0EAo1xf6bJSmcBB9Q2URr7DM22GPeykJGwmAltR3nBeXvauzbS4syF+/cjVzEO+t8wCo+Ws7tfvcLCocUp+cOVZNQ==',
			{ delay: 1 }
		);
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das Signaturzertifikat des Identity Providers beinhaltet einen öffentlichen Schlüssel unzureichender Länge. Die minimal gültige Schlüssellänge ist 2048.'
				);
			});

		// Filling idp_cert_sign correctly and check next error message.
		cy.get(`${prefix}form-manual-idp_cert_sign`).clear();
		cy.get(
			`${prefix}form-manual-idp_cert_sign`
		).type(
			'MIIFlzCCA3+gAwIBAgIIUxbcS/Bb6QcwDQYJKoZIhvcNAQELBQAwYzELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMITWljaGVsYXUxEzARBgNVBAoTCmVjc2VjIEdtYkgxGzAZBgNVBAMTElNrSURlbnRpdHkgU0FNTCBGUzAeFw0yMDAxMTMxMDAwMDBaFw0yMjAxMTMxMDAwMDBaMGMxCzAJBgNVBAYTAkRFMQ8wDQYDVQQIEwZCYXllcm4xETAPBgNVBAcTCE1pY2hlbGF1MRMwEQYDVQQKEwplY3NlYyBHbWJIMRswGQYDVQQDExJTa0lEZW50aXR5IFNBTUwgRlMwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQCgSraq4/BaSD+8tPKKsez/Uk6FZ2c4cxSzjvcZptVPo7IH2cdLRKnlVfVgLPoeV+MOL/viu1y6IPp6aEJ09vl/7V0P5oEZ9BJ41K6DVsBb/puiFOC/Ma6Q53DbHbZQJJdGPmX1RH297e420iYs19zH7Y98X+ZTVOlOIxc26/yubc6XiMPvGzIv5BsHYzfyLFdapV/PTj21BDUmhas/H83zJP1IGdurJOt8/u7T1Mg2haLlU+Vp1xdeSaZgk+iesRyIB3Y774s6jqavxkit9PHk+Qq166sW2NOQLtb/BR/1aVK5rvvQqrZ0cLnk2jCFyDht4kZ7O6T5C0seQXDOGKHacv6neqfLu+4lWOTpZk/ANrbd8d2oG98k8lc5j2agVC7PjM0lTRoEMedTfG7J4q4mgSKhlL+YrRhIb/nYUSScn0EiAr32YSb5caboT3+eiqXnzAqVbH/wtwXIpbTkgQEwlk6A/TkDhv9+ssDv75k4PUKWmFjUKrC/TUQmC5k8TXvO40NX2cGOVimTavN1fSe1Pj1ytmQXRrbfrKiNwz+EbhAJHTdkEHh40XwjJh2jvwSSctvs3vpVIAtX4FPtHTOraBCZyyH0X/1vtKRruY2VzO8kAeU2Zb4NWE2STmFSXbIG9Pyci9eqdtd5nr3GaPj4g8BabcmMweOJRWwqm8F3fwIDAQABo08wTTAdBgNVHQ4EFgQUPSTV0I2z0mB0eJ/2JPvLPb4UVxswHwYDVR0jBBgwFoAUPSTV0I2z0mB0eJ/2JPvLPb4UVxswCwYDVR0PBAQDAgSQMA0GCSqGSIb3DQEBCwUAA4ICAQCbquW0L2qylIajQ0IelyVQhhAQPc2Eu8ZYequg2OGWHD/LnMyQxEX7eCiIEXTy92+B1Yw9BWVPQo2LvIgzwNAOFaepbdZJCa9CfuI5BEJUlX4QlGZWMfoFIhT08//Z1op+ru4FeQEZwH6fVJqotTnxkpmjbAOMrC5UVpADqBoIoRdS0IaWjW2mN6Gt9G0priQxmgV3FC8n4dhYUgyndOG9ImYkgxtRwHGnk0SC/N6b3PMZxAccxDKBfY0vxAsg3Hktshc5LF2OW08o9Uji/w6OHvSL4uYVGkPOot6u1wncKsz8bQyt7Sj+Tx3nNdqjNciZsd11i9YlIlI0DmLCb4cq61P1AAAZY4d9ah0NdfWLNBUdeER4qnOahdwJXQXdMGkc4FNF4gx7gczGG4vrMKHgn8v2jxEuAhNHVbBGSi0JwO/eK/p8nFW8y/3SgXIWhL+efS4DWYcYhVKU7izAgj0fnnF/flUkaJjTH+rSgzQK/QISYplzSGPa0+bri/kxvxx1Q1VwPI1hpFAS/o9pFuANlNeBD6x26HZYJPK7Leg9/sQ+IAgkS8KR+GInyaZ285A1QNmBy7MmVU304WM6fiZ9+Osbi7n7aK6+BFbKFnhnVRTp4C7Vp3xCXut6z62q0BuxfiHvrYgA5X2HxPRuTjb+beHkiLq7VOb9AW8cPI4wHw==',
			{ delay: 1 }
		);
		// The encryption certificate can be empty, but if present, it has to be valid.
		cy.get(`${prefix}form-manual-idp_cert_enc`).clear();
		cy.get(`${prefix}form-manual-idp_cert_enc`).type('foobar');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das Verschlüsselungszertifikat des Identity Providers konnte nicht gelesen werden.'
				);
			});

		// Filling idp_cert_enc with 1024 bit cert and check next error message.
		cy.get(`${prefix}form-manual-idp_cert_enc`).clear();
		cy.get(
			`${prefix}form-manual-idp_cert_enc`
		).type(
			'MIIBKTCB1KADAgECAgRglScoMA0GCSqGSIb3DQEBCwUAMBwxGjAYBgNVBAMMEXRlc3QtY2VydCByc2EgNTEyMB4XDTIxMDUwNzExNDAyNFoXDTIyMDUwNzExNDAyNFowHDEaMBgGA1UEAwwRdGVzdC1jZXJ0IHJzYSA1MTIwXDANBgkqhkiG9w0BAQEFAANLADBIAkEA0LP4k6cbOL1xSs432wj9YB/TB3BkO7j7fxelkqJZNPTtWrMlj1L+3qpPAuGdhXkj689o38Rbk9yOpqq4FlN11QIDAQABMA0GCSqGSIb3DQEBCwUAA0EAo1xf6bJSmcBB9Q2URr7DM22GPeykJGwmAltR3nBeXvauzbS4syF+/cjVzEO+t8wCo+Ws7tfvcLCocUp+cOVZNQ==',
			{ delay: 1 }
		);
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das Verschlüsselungszertifikat des Identity Providers beinhaltet einen öffentlichen Schlüssel unzureichender Länge. Die minimal gültige Schlüssellänge ist 2048.'
				);
			});

		// Filling idp_cert_enc correctly and check next error message.
		cy.get(`${prefix}form-manual-idp_cert_enc`).clear();
		cy.get(
			`${prefix}form-manual-idp_cert_enc`
		).type(
			'MIIFlzCCA3+gAwIBAgIIUxbcS/Bb6QcwDQYJKoZIhvcNAQELBQAwYzELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMITWljaGVsYXUxEzARBgNVBAoTCmVjc2VjIEdtYkgxGzAZBgNVBAMTElNrSURlbnRpdHkgU0FNTCBGUzAeFw0yMDAxMTMxMDAwMDBaFw0yMjAxMTMxMDAwMDBaMGMxCzAJBgNVBAYTAkRFMQ8wDQYDVQQIEwZCYXllcm4xETAPBgNVBAcTCE1pY2hlbGF1MRMwEQYDVQQKEwplY3NlYyBHbWJIMRswGQYDVQQDExJTa0lEZW50aXR5IFNBTUwgRlMwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQCgSraq4/BaSD+8tPKKsez/Uk6FZ2c4cxSzjvcZptVPo7IH2cdLRKnlVfVgLPoeV+MOL/viu1y6IPp6aEJ09vl/7V0P5oEZ9BJ41K6DVsBb/puiFOC/Ma6Q53DbHbZQJJdGPmX1RH297e420iYs19zH7Y98X+ZTVOlOIxc26/yubc6XiMPvGzIv5BsHYzfyLFdapV/PTj21BDUmhas/H83zJP1IGdurJOt8/u7T1Mg2haLlU+Vp1xdeSaZgk+iesRyIB3Y774s6jqavxkit9PHk+Qq166sW2NOQLtb/BR/1aVK5rvvQqrZ0cLnk2jCFyDht4kZ7O6T5C0seQXDOGKHacv6neqfLu+4lWOTpZk/ANrbd8d2oG98k8lc5j2agVC7PjM0lTRoEMedTfG7J4q4mgSKhlL+YrRhIb/nYUSScn0EiAr32YSb5caboT3+eiqXnzAqVbH/wtwXIpbTkgQEwlk6A/TkDhv9+ssDv75k4PUKWmFjUKrC/TUQmC5k8TXvO40NX2cGOVimTavN1fSe1Pj1ytmQXRrbfrKiNwz+EbhAJHTdkEHh40XwjJh2jvwSSctvs3vpVIAtX4FPtHTOraBCZyyH0X/1vtKRruY2VzO8kAeU2Zb4NWE2STmFSXbIG9Pyci9eqdtd5nr3GaPj4g8BabcmMweOJRWwqm8F3fwIDAQABo08wTTAdBgNVHQ4EFgQUPSTV0I2z0mB0eJ/2JPvLPb4UVxswHwYDVR0jBBgwFoAUPSTV0I2z0mB0eJ/2JPvLPb4UVxswCwYDVR0PBAQDAgSQMA0GCSqGSIb3DQEBCwUAA4ICAQCbquW0L2qylIajQ0IelyVQhhAQPc2Eu8ZYequg2OGWHD/LnMyQxEX7eCiIEXTy92+B1Yw9BWVPQo2LvIgzwNAOFaepbdZJCa9CfuI5BEJUlX4QlGZWMfoFIhT08//Z1op+ru4FeQEZwH6fVJqotTnxkpmjbAOMrC5UVpADqBoIoRdS0IaWjW2mN6Gt9G0priQxmgV3FC8n4dhYUgyndOG9ImYkgxtRwHGnk0SC/N6b3PMZxAccxDKBfY0vxAsg3Hktshc5LF2OW08o9Uji/w6OHvSL4uYVGkPOot6u1wncKsz8bQyt7Sj+Tx3nNdqjNciZsd11i9YlIlI0DmLCb4cq61P1AAAZY4d9ah0NdfWLNBUdeER4qnOahdwJXQXdMGkc4FNF4gx7gczGG4vrMKHgn8v2jxEuAhNHVbBGSi0JwO/eK/p8nFW8y/3SgXIWhL+efS4DWYcYhVKU7izAgj0fnnF/flUkaJjTH+rSgzQK/QISYplzSGPa0+bri/kxvxx1Q1VwPI1hpFAS/o9pFuANlNeBD6x26HZYJPK7Leg9/sQ+IAgkS8KR+GInyaZ285A1QNmBy7MmVU304WM6fiZ9+Osbi7n7aK6+BFbKFnhnVRTp4C7Vp3xCXut6z62q0BuxfiHvrYgA5X2HxPRuTjb+beHkiLq7VOb9AW8cPI4wHw==',
			{ delay: 1 }
		);
		// The AuthnRequestExtension XML Element can be empty, but if present, it has to be valid XML.
		cy.get(`${prefix}form-manual-idp_ext_tr03130`).clear();
		cy.get(`${prefix}form-manual-idp_ext_tr03130`).type('foobar');
		cy.get(`${prefix}button-manual-save`).click();
		cy.wait('@settings')
			.its('response.body')
			.then((settings) => {
				expect(settings.data.params.data).to.equal(
					'Das AuthnRequestExtension XML-Element ist kein valides XML.'
				);
			});

		// Filling idp_ext_tr03130 correctly, the data should be saved successfully.
		cy.get(`${prefix}form-manual-idp_ext_tr03130`).clear();
		cy.get(`${prefix}form-manual-idp_ext_tr03130`).type('<foo>bar</foo>');
		cy.get(`${prefix}button-manual-save`).click();

		// Logout and check for eID-Login button.
		cy.logout();
		cy.get('[data-cy=perso]').should('be.visible');
	});

	it('Test the reset of the settings', () => {
		cy.task('db:modify_options', 'add');
		cy.visit('/wp-admin/options-general.php?page=eidlogin-settings');
		cy.get(`${prefix}manual`).should('be.visible');

		let count = 0;

		// Intercept the window confirm event.
		cy.on('window:confirm', ($str) => {
			count += 1;

			switch (count) {
				case 1:
					// First click on reset: simulate cancel button.
					return false;
				case 2:
					// Second click on reset: simulate ok button.
					return true;
			}
		});

		cy.get(`${prefix}button-reset`).click();
		cy.get(`${prefix}manual`).should('be.visible');

		cy.get(`${prefix}button-reset`).click();
		cy.get(`${prefix}wizard`).should('be.visible');

		cy.logout();
		// cy.get('#perso-btn-div').should('not.exist');
		cy.get('[data-cy=perso]').should('not.exist');
	});
});
