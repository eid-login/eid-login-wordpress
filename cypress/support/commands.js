// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

const default_testuser = "testuser";
const default_email = "testuser@example.com";
const default_password = "testuser123";

Cypress.Commands.add("login", (username = default_testuser, password = default_password) => {
  cy.clearCookies();

  cy.visit({
    url: "/wp-login.php",
    method: "POST",
    body: {
      log: username,
      pwd: password,
    },
  });
});

Cypress.Commands.add("logout", () => {
  cy.get("#wp-admin-bar-logout > a").click({ force: true });
});

// Execute arbitrary SQL commands. Example usage:
// cy.database("SELECT * FROM table WHERE id BETWEEN ? AND ?", [10, 20]).then(($rs) => {});
Cypress.Commands.add("database", (sql, params) => {
  const options = {
    sql,
    params,
  };

  return cy.task("db:sql", options).then((rs) => {
    return rs;
  });
});

// Create a specific (test) user via WordPress REST API.
// Cookies are currently the only natively supported authentication method in
// WordPress. So we log in as admin and grab the nonce that we injected via PHP:
// <script>var wpApiSettings = {"root":"rest_route","nonce":"123"};</script>
// In WordPress, nonces are unique tokens stored in user's session.
Cypress.Commands.add(
  "createUser",
  (username = default_testuser, email = default_email, password = default_password) => {
    cy.clearCookies();
    cy.login("admin", "admin", "/wp-login.php");
    cy.visit("/wp-admin/options-general.php?page=eidlogin-settings");

    cy.window().then((win) => {
      cy.request({
        method: "POST",
        url: `${win.wpApiSettings.root}wp/v2/users`,
        body: {
          username: username,
          email: email,
          password: password,
        },
        headers: {
          "X-WP-Nonce": win.wpApiSettings.nonce,
        },
      }).then((response) => {
        expect(response.status).to.equal(201);
        cy.log(`Created user ${username}`);
        cy.logout();
      });
    });
  }
);

// Delete a specific (test) user via WordPress REST API.
// Also see createUser command for more documentation.
Cypress.Commands.add("deleteUser", (username = default_testuser) => {
  cy.clearCookies();
  cy.login("admin", "admin", "/wp-login.php");
  cy.visit("/wp-admin/options-general.php?page=eidlogin-settings");

  cy.window().then((win) => {
    // Get the user's id first.
    cy.request({
      method: "GET",
      url: `${win.wpApiSettings.root}wp/v2/users`,
      body: {
        search: username,
      },
      headers: {
        "X-WP-Nonce": win.wpApiSettings.nonce,
      },
    }).then((response) => {
      expect(response.status).to.equal(200);

      if (response.body.length == 0) {
        cy.log(`User ${username} not found. Skipping...`);
        return;
      }

      let userID = response.body[0].id;

      // Now delete the user via its id.
      cy.request({
        method: "DELETE",
        url: `${win.wpApiSettings.root}wp/v2/users/${userID}`,
        body: {
          force: true,
          reassign: 1,
        },
        headers: {
          "X-WP-Nonce": win.wpApiSettings.nonce,
        },
      }).then((response) => {
        expect(response.status).to.equal(200);
        let sql = "DELETE FROM wp_eidlogin_eid_users WHERE uid = ?";
        cy.database(sql, [userID]).then(($rs) => {});
        cy.log(`Deleted user ${username}`);
        cy.logout();
      });
    });
  });
});

// Set the value of `disable_password_login` to true for the given user.
Cypress.Commands.add("disablePasswordLogin", (username = default_testuser) => {
  let sql = "UPDATE wp_usermeta um SET um.meta_value = 'true' ";
  sql += "WHERE um.meta_key = 'eidlogin_disable_password' ";
  sql += "AND um.user_id = (SELECT ID FROM wp_users u WHERE u.user_login = ?)";

  cy.database(sql, [default_testuser]).then(($rs) => {});
});

// Insert a (dummy) eID for the given user.
Cypress.Commands.add("insertEID", (username = default_testuser) => {
  let sql = "INSERT INTO wp_eidlogin_eid_users (uid, eid) ";
  sql += "SELECT ID, 'dummy' FROM wp_users u WHERE u.user_login = ?";

  cy.database(sql, [default_testuser]).then(($rs) => {});
});
