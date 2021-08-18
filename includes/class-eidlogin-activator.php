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
 * Class that implements the `register_activation_hook`.
 */
class Eidlogin_Activator {

	/**
	 * The activate function is called if the plugin is enabled by the
	 * administrator.
	 */
	public static function activate() {
		self::create_db_tables();

		// Schedule / activate the cronjobs only if certificates are present.
		// This is not the case on first activation.
		$certs = new Eidlogin_Certificates();
		if ( $certs->check_act_cert_present() ) {
			$cron = new Eidlogin_Cron();
			$cron->schedule_cert();
			$cron->schedule_cleanup();
		}
	}

	/**
	 * Create the necessary database tables on (first) activation.
	 *
	 * See https://codex.wordpress.org/Creating_Tables_with_Plugins for the
	 * usage of the dbDelta function (e.g. each field has to be on its own
	 * line).
	 */
	private static function create_db_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$users_table     = $wpdb->prefix . EIDLOGIN_EID_USERS_TABLE;

		$sql = "CREATE TABLE $users_table (
                id bigint NOT NULL AUTO_INCREMENT,
                eid varchar(64) NOT NULL,
                uid bigint unsigned NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY eidlogin_eid_index (eid),
                UNIQUE KEY eidlogin_uid_index (uid)
            ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$attributes_table = $wpdb->prefix . EIDLOGIN_EID_ATTRIBUTES_TABLE;

		$sql = "CREATE TABLE $attributes_table (
                id bigint NOT NULL AUTO_INCREMENT,
                uid bigint unsigned NOT NULL,
                name varchar(255) NOT NULL,
                value longtext NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY eidlogin_attr_name_index (uid, name)
            ) $charset_collate;";

		dbDelta( $sql );

		$continuedata_table = $wpdb->prefix . EIDLOGIN_EID_CONTINUEDATA_TABLE;

		$sql = "CREATE TABLE $continuedata_table (
                id bigint NOT NULL AUTO_INCREMENT,
                uid varchar(64) NOT NULL,
                value longtext NOT NULL,
                time int NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

		dbDelta( $sql );

		$responsedata_table = $wpdb->prefix . EIDLOGIN_EID_RESPONSEDATA_TABLE;

		$sql = "CREATE TABLE $responsedata_table (
                id bigint NOT NULL AUTO_INCREMENT,
                uid varchar(64) NOT NULL,
                value longtext NOT NULL,
                time int NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Update the version number in the database if there is a new plugin
	 * version available.
	 *
	 * @param string $db_version The current version of the plugin in the database.
	 */
	public static function update( string $db_version ) {
		$log_msg = sprintf(
			'Update database version from %s to new version %s',
			$db_version,
			EIDLOGIN_PLUGIN_VERSION
		);
		Eidlogin_Helper::write_log( $log_msg );

		update_option( EIDLOGIN_VERSION_NAME, EIDLOGIN_PLUGIN_VERSION );
	}
}
