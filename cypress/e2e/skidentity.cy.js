import 'cypress-wait-until';

/**
 * Test the eID assignment and the login with SkIDentity.
 * Please note: holds state between tests and needs user interaction.
 * Can only be run in chromium based browsers!
 */
const username = 'testuser';
const password = 'testuser123';
const email = 'testuser@example.com';
const waitForSkidInMs = 10000;

describe('Test all SAML related settings and workflows', () => {
	before(() => {
		if (Cypress.browser.displayName !== 'Electron' || Cypress.browser.isHeadless) {
			throw new Error(
				'Tests need interaction with the skidentity system and works only in non headless Electron'
			);
		}

		cy.log('********************************************************************');
		cy.log('ATTENTION: The SkIDentity test needs user interaction!');
		cy.log('If you have not created a CloudId in the current browser profile yet, ');
		cy.log('please do it now and restart the test as this is mandatory.');
		cy.log('IMPORTANT: Make sure that the eID you use is not assigned by another user!');
		cy.log('********************************************************************');
		cy.log('Please follow the instructions in the log messages!');
		cy.log('********************************************************************');

		cy.task('db:modify_options', 'clear');
		cy.deleteUser(username);
		cy.createUser(username, email, password);
	});

	it('Display the correct content if the plugin settings are invalid', () => {
		cy.login();
		cy.visit('/wp-admin/profile.php');
		cy.get('#eidlogin-invalid-settings').should('exist');
		cy.get('.eidlogin-settings-button-eid').should('not.exist');
		cy.logout();
	});

	it('Test the assignment of a eID to the account', () => {
		// Delete all existing eIDs and attributes.
		cy.database("DELETE FROM wp_eidlogin_eid_users").then(($rs) => {});
		cy.database("DELETE FROM wp_eidlogin_eid_attributes").then(($rs) => {});

		cy.task('db:modify_options', 'add');
		cy.login();
		cy.visit('/wp-admin/profile.php');

		// The user has no eID assigned to his account yet.
		cy.get('#optionAddEID').should('exist');
		cy.get('#optionRemoveEID').should('not.exist');
		cy.get('#eidlogin-invalid-settings').should('not.exist');
		cy.get('#eidlogin_disable_password').should('not.exist');

		cy.log('Waiting for SkIDentity...');
		cy.log('***********************************');
		cy.log(
			'ATTENTION: If you dont have a CloudId in the cypress used browser profile, please create it now and restart the test ...'
		);
		cy.log('OTHERWISE ... , please ABORT at the IdP ...');
		cy.log('***********************************');

		// Test 1: check if the cancelation is handled correctly.
		cy.get('#optionAddEID').click();

		// Wait until the browser returns to the profile page.
		cy.waitUntil(
			() =>
				cy.get('#optionAddEID').then(($button) => {
					$button.is(':visible');
				}),
			{
				timeout: waitForSkidInMs,
				interval: 1000,
			}
		);

		// Due to the cancelation, the user STILL has nothing assigned.
		cy.location('pathname').should('eq', '/wp-admin/profile.php');
		cy.get('#optionAddEID').should('exist');
		cy.get('#optionRemoveEID').should('not.exist');
		cy.get('#eidlogin-invalid-settings').should('not.exist');
		cy.get('#eidlogin_disable_password').should('not.exist');

		cy.log('Waiting for SkIDentity...');
		cy.log('*********************************');
		cy.log('ATTENTION: Please ENTER CORRECT PIN at the IdP ...');
		cy.log('*********************************');

		// Test 2: check if the assignment is handled correctly.
		cy.get('#optionAddEID').click();

		// Wait until the browser returns to the profile page.
		cy.waitUntil(
			() =>
				cy.get('#optionRemoveEID').then(($button) => {
					$button.is(':visible');
				}),
			{
				timeout: waitForSkidInMs,
				interval: 1000,
			}
		);

		// After a successful assignment, other elements should be visible / invisible.
		cy.location('pathname').should('eq', '/wp-admin/profile.php');
		cy.get('#optionRemoveEID').should('exist');
		cy.get('#eidlogin_disable_password').should('exist');
		cy.get('#optionAddEID').should('not.exist');

		cy.logout();
	});

	it('Test the cancelation of a login attempt', () => {
		// It is expected that the previous function ran so that the plugin data is
		// present in the database. Otherwise the eID button is missing.
		cy.log('Waiting for SkIDentity...');
		cy.log('***********************************');
		cy.log('ATTENTION: Please ABORT at the IdP ...');
		cy.log('***********************************');

		cy.visit('/wp-login.php');
		cy.get('#perso-btn').click();

		// Wait until the browser returns to the login page.
		cy.waitUntil(
			() =>
				cy.get('#login').then(($div) => {
					$div.is(':visible');
				}),
			{
				timeout: waitForSkidInMs,
				interval: 1000,
			}
		);

		cy.location('pathname').should('eq', '/wp-login.php');
		cy.location('search').should('eq', '?eid_error=canceled');
		cy.get('p.message').should('exist');
	});

	it('Test a successful login with eID success', () => {
		// It is expected that the previous function ran so that the plugin data is
		// present in the database. Otherwise the eID button is missing.
		cy.log('Waiting for SkIDentity...');
		cy.log('*********************************');
		cy.log('ATTENTION: Please ENTER CORRECT PIN at the IdP ...');
		cy.log('*********************************');

		cy.visit('/wp-login.php');
		cy.get('#perso-btn').click();

		cy.waitUntil(
			() =>
				cy.url().then(($url) => {
					$url === 'https://wordpress.p396.de/wp-admin/';
				}),
			{
				timeout: waitForSkidInMs,
				interval: 1000,
			}
		);

		// Finally, the user should be redirected to the WP homepage.
		cy.location('pathname').should('eq', '/wp-admin/');
		cy.logout();
	});

	it('Test the removal if the eID', () => {
		cy.login();
		cy.visit('/wp-admin/profile.php');

		cy.get('#optionRemoveEID').click();

		cy.get('.eidlogin-settings-button-eid').should('be.visible');
		cy.get('#eidlogin_disable_password').should('not.exist');

		cy.logout();
	});

	it('Test a login attempt without assigned eID', () => {
		cy.deleteUser(username);
		cy.createUser(username, email, password);

		cy.log('Waiting for SkIDentity...');
		cy.log('*********************************');
		cy.log('ATTENTION: Please ENTER CORRECT PIN at the IdP ...');
		cy.log('*********************************');

		cy.visit('/wp-login.php');
		cy.get('#perso-btn').click();

		// Wait until the browser returns to the login page.
		cy.waitUntil(
			() =>
				cy.url().then(($url) => {
					$url === 'https://wordpress.p396.de/wp-admin/';
				}),
			{
				timeout: waitForSkidInMs,
				interval: 1000,
			}
		);

		cy.location('pathname').should('eq', '/wp-login.php');
		cy.location('search').should(
			'eq',
			'?redirect_to=%2Fwp-admin%2Fprofile.php%23eid-header&eid_error=nocon'
		);
		cy.get('p.message').should('exist');
	});
});
