<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later, see license.txt.
 *
 * @package    eID-Login
 * @subpackage eID-Login/includes
 * @copyright  ecsec 2021
 */

declare(strict_types = 1);

/**
 * Class that implements the `register_uninstall_hook`.
 *
 * It removes or truncates the tables that are created in class
 * Eidlogin_Activator and deletes all options created by this plugin. It is also
 * responsible for unscheduling the hooks for the cronjobs.
 */
class Eidlogin_Cleanup {

	/**
	 * Remove some or all data of the plugin.
	 *
	 * @param bool $dropdb Indicates whether to drop our custom tables.
	 * @param bool $delete_options Indicates whether to remove the options.
	 */
	public static function remove_plugin_data( $dropdb = false, $delete_options = false ) {
		global $wpdb;

		$prefix             = $wpdb->get_blog_prefix();
		$users_table        = $prefix . EIDLOGIN_EID_USERS_TABLE;
		$attributes_table   = $prefix . EIDLOGIN_EID_ATTRIBUTES_TABLE;
		$continuedata_table = $prefix . EIDLOGIN_EID_CONTINUEDATA_TABLE;
		$responsedata_table = $prefix . EIDLOGIN_EID_RESPONSEDATA_TABLE;

        // phpcs:disable WordPress.DB
		if ( true === $dropdb ) {
			$wpdb->query( "DROP TABLE $users_table" );
			$wpdb->query( "DROP TABLE $attributes_table" );
			$wpdb->query( "DROP TABLE $continuedata_table" );
			$wpdb->query( "DROP TABLE $responsedata_table" );
		} else {
			$wpdb->query( "DELETE FROM $users_table" );
			$wpdb->query( "DELETE FROM $attributes_table" );
			$wpdb->query( "DELETE FROM $continuedata_table" );
			$wpdb->query( "DELETE FROM $responsedata_table" );
		}

		// Make sure, all user meta data is removed. This is important so that
		// the password login is (re-) enabled if the eID connection is removed.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->usermeta WHERE meta_key = %s OR meta_key = %s",
				EIDLOGIN_FIRST_TIME_USER,
				EIDLOGIN_DISABLE_PASSWORD
			)
		);
        // phpcs:enable

		if ( true === $delete_options ) {
			delete_option( EIDLOGIN_OPTION_NAME );
			delete_option( EIDLOGIN_VERSION_NAME );
		}
	}

	/**
	 * Unschedule the created hook for the cronjobs.
	 */
	public static function deactivate_cronjobs() {
		$timestamp = wp_next_scheduled( EIDLOGIN_CERT_CRON_HOOK );
		wp_unschedule_event( $timestamp, EIDLOGIN_CERT_CRON_HOOK );

		$timestamp = wp_next_scheduled( EIDLOGIN_CLEANUP_CRON_HOOK );
		wp_unschedule_event( $timestamp, EIDLOGIN_CLEANUP_CRON_HOOK );
	}
}
