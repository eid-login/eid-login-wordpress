<?php
/**
 * Plugin Name: eID-Login
 * Plugin URI: https://eid.services/eidlogin/wordpress
 * Description: The eID-Login plugin allows to use the German eID-card and similar electronic identity documents for <strong>secure and privacy-friendly login</strong> to WordPress. For this purpose, a so-called eID-Client, such as the AusweisApp2 or the Open eCard App and eID-Service are required. In the default configuration a suitable eID-Service is provided without any additional costs.
 * Version: 1.0.0
 * Requires at least: 5.7
 * Requires PHP: 7.3
 * Author: ecsec GmbH
 * Author URI: https://www.ecsec.de
 * Text Domain: eidlogin
 * License: AGPL
 *
 * @package eID-Login
 * @copyright ecsec 2021
 */

declare(strict_types = 1);

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// EIDLOGIN_PLUGIN_VERSION is the current version of the plugin. It has to be
// in sync with the version number in the header.
define( 'EIDLOGIN_PLUGIN_VERSION', '1.0.0' );
// EIDLOGIN_OPTION_NAME is the name of the options in table 'wp_options'.
define( 'EIDLOGIN_OPTION_NAME', 'eidlogin_options' );
// EIDLOGIN_VERSION_NAME is the name of the version in table 'wp_options'.
define( 'EIDLOGIN_VERSION_NAME', 'eidlogin_version' );

// EIDLOGIN_EID_USERS_TABLE is the db table where the eIDs of the users are stored.
define( 'EIDLOGIN_EID_USERS_TABLE', 'eidlogin_eid_users' );
// EIDLOGIN_EID_ATTRIBUTES_TABLE is the db table where the attributes of the
// SAML response are stored.
define( 'EIDLOGIN_EID_ATTRIBUTES_TABLE', 'eidlogin_eid_attributes' );
// EIDLOGIN_EID_CONTINUEDATA_TABLE is the db table where the continue data for
// the SAML flow is stored.
define( 'EIDLOGIN_EID_CONTINUEDATA_TABLE', 'eidlogin_eid_continuedata' );
// EIDLOGIN_EID_RESPONSEDATA_TABLE is the db table where the response data for
// the TR03130 flow is stored.
define( 'EIDLOGIN_EID_RESPONSEDATA_TABLE', 'eidlogin_eid_responsedata' );
// EIDLOGIN_CERT_CRON_HOOK is the string used for the certificate hook.
define( 'EIDLOGIN_CERT_CRON_HOOK', 'eidlogin_cert_cron_hook' );
// EIDLOGIN_CLEANUP_CRON_HOOK is the string used for the cleanup hook.
define( 'EIDLOGIN_CLEANUP_CRON_HOOK', 'eidlogin_cleanup_cron_hook' );

// The time that a login session stays valid.
define( 'EIDLOGIN_EXPIRATION_TIME', 300 );

// The URL of the ACS is static and independent from the IDP.
define( 'EIDLOGIN_ACS_URL', site_url() . '/wp-login.php?saml_acs' );
// The URL of the SP metadata for the registration at the IDP.
define( 'EIDLOGIN_METADATA_URL', site_url() . '/wp-login.php?saml_metadata' );
// EIDLOGIN_FIRST_TIME_USER indicates whether to show a notification to the user on login.
define( 'EIDLOGIN_FIRST_TIME_USER', 'eidlogin_first_time_user' );
// EIDLOGIN_DISABLE_PASSWORD indicates whether login with password is allowed.
define( 'EIDLOGIN_DISABLE_PASSWORD', 'eidlogin_disable_password' );

/**
 * Function that is triggered when activating the plugin.
 */
function activate_eidlogin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-eidlogin-activator.php';
	Eidlogin_Activator::activate();
}

/**
 * Function that is triggered when deactivating the plugin.
 */
function deactivate_eidlogin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-eidlogin-cleanup.php';
	Eidlogin_Cleanup::deactivate_cronjobs();
}

/**
 * Function that is triggered when uninstalling the plugin.
 */
function uninstall_eidlogin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-eidlogin-cleanup.php';
	// Remove all data, delete database tables and all options.
	Eidlogin_Cleanup::remove_plugin_data( true, true );
}

register_activation_hook( __FILE__, 'activate_eidlogin' );
register_deactivation_hook( __FILE__, 'deactivate_eidlogin' );
register_uninstall_hook( __FILE__, 'uninstall_eidlogin' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-eidlogin-init.php';

/**
 * Function that initializes and actually runs the plugin.
 */
function run_eidlogin() {
	$plugin = new Eidlogin_Init();
	$plugin->run();
}
run_eidlogin();
