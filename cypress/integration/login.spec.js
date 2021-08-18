// Set baseUrl in cypress.json

describe("Test the WordPress login page", () => {
  before(() => {
    // Reset the options from the database temporarily.
    cy.task("db:modify_options", "clear");
    cy.deleteUser();
    cy.createUser();
  });

  beforeEach(() => {
    cy.visit("/wp-login.php");
  });

  it("Try to login with empty form triggering an error", () => {
    cy.get("#loginform").submit();
    cy.get("#login_error").should("exist");
  });

  it("Redirect unauthenticated users to login page", function () {
    cy.visit("/wp-admin/profile.php");
    cy.url().should("include", "/wp-login.php");
  });

  it("Checks the existence of the eID button", () => {
    // Without valid SAML settings, the eID button should not be visible.
    cy.get("[data-cy=perso]").should("not.exist");
    // Make sure the options are in the database.
    cy.task("db:modify_options", "add");
    cy.visit("/wp-login.php");

    cy.get("#perso-btn").should("have.attr", "href").and("contains", "/wp-login.php?saml_login");
  });

  it("Check the existance of the notification", () => {
    cy.login();
    cy.get("#first-time-options-message").should("exist");
    cy.logout();
    cy.login();
    cy.get("#first-time-options-message").should("not.exist");
    cy.url().should("include", "/wp-admin");
    cy.logout();
  });

  it("Try to login in with username and password if disable_password_login is true", () => {
    cy.disablePasswordLogin();
    // With no eID present, the login with password should still work.
    cy.login();
    cy.get("#login_error").should("not.exist");
    cy.url().should("include", "/wp-admin");
    cy.logout();

    // With a eID present, the login should now fail.
    cy.insertEID();
    cy.login();
    cy.get("#login_error").should("exist");
  });

});
