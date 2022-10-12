// Set baseUrl in cypress.json

describe('Test the eID-Login SP metadata', () => {
	it('Check if the status code is 404 if SAML settings are missing', () => {
		// Reset the options from the database temporarily.
		cy.task('db:modify_options', 'clear');
		cy.request({ url: '/wp-login.php?saml_metadata', failOnStatusCode: false }).then((resp) => {
			expect(resp.status).to.eq(404);
		});
	});

	it('Check the correctness of the XML response if the SAML settings are present', () => {
		// Make sure the options are in the database.
		cy.task('db:modify_options', 'add');

		cy.request('/wp-login.php?saml_metadata').then((resp) => {
			expect(resp.status).to.eq(200);
			expect(resp.headers['content-type']).to.eq('text/xml;charset=UTF-8');

			const xml = Cypress.$.parseXML(resp.body);

			const entityDescriptor = xml.getElementsByTagName('md:EntityDescriptor')[0];
			expect(entityDescriptor.getAttribute('entityID')).to.eq('https://wordpress.p396.de');

			const signature = xml.getElementsByTagName('ds:Signature')[0];
			expect(signature).to.be.not.null;
			const signatureValue = signature.querySelectorAll('SignatureValue');
			expect(signatureValue).to.be.not.null;

			const spSSODescriptor = xml.getElementsByTagName('md:SPSSODescriptor')[0];
			expect(spSSODescriptor.getAttribute('WantAssertionsSigned')).to.eq('true');
			expect(spSSODescriptor.getAttribute('AuthnRequestsSigned')).to.eq('true');

			const keyDescriptor = xml.getElementsByTagName('md:KeyDescriptor')[0];
			expect(keyDescriptor.getAttribute('use')).to.eq('signing');
			const certs = keyDescriptor.querySelectorAll('X509Certificate');
			expect(certs.length).to.eq(1);

			const acs = xml.getElementsByTagName('md:AssertionConsumerService')[0];
			expect(acs.getAttribute('Location')).to.eq('https://wordpress.p396.de/wp-login.php?saml_acs');
			expect(acs.getAttribute('Binding')).to.eq('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST');

			const nameIDFormat = xml.getElementsByTagName('md:NameIDFormat')[0];
			expect(nameIDFormat).to.be.not.null;
		});
	});
});
