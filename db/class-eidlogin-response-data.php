<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later, see license.txt.
 *
 * @package    eID-Login
 * @subpackage eID-Login/db
 * @copyright  ecsec 2021
 */

declare(strict_types = 1);

/**
 * Class representing a row of the eidlogin_eid_responsedata table.
 */
class Eidlogin_Response_Data {

	/**
	 * A reference to the global database handle.
	 *
	 * @var wpdb $wpdb The database handle.
	 */
	private $wpdb;

	/**
	 * The database table name that is used for all queries.
	 *
	 * @var string $table The database table.
	 */
	private $table;

	/**
	 * Constructor of the Eidlogin_Response_Data class.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . EIDLOGIN_EID_RESPONSEDATA_TABLE;
	}

	/**
	 * Insert a row with the given parameters.
	 *
	 * @param string $uid The unique identifier.
	 * @param string $value The values as JSON encoded string.
	 * @param int    $time The given time.
	 */
	public function save( string $uid, string $value, int $time ) {
		// phpcs:disable WordPress.DB
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$this->table} (uid, value, time) VALUES (%s, %s, %d) ",
				$uid,
				$value,
				$time
			)
		);
		// phpcs:enable
	}

	/**
	 * Get a row for a given identifier.
	 *
	 * @param string $uid The unique identifier.
	 */
	public function get_by_uid( string $uid ) {
		// phpcs:disable WordPress.DB
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT value, time FROM {$this->table} WHERE uid = %s",
				$uid
			)
		);
		// phpcs:enable
	}

	/**
	 * Delete a row for a given identifier.
	 *
	 * @param string $uid The unique identifier.
	 */
	public function delete_by_uid( string $uid ) {
		// phpcs:disable WordPress.DB
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE uid = %s",
				$uid
			)
		);
		// phpcs:enable
	}

	/**
	 * Delete data older than a given limit.
	 *
	 * @param int $limit The limit for deletion as timestamp.
	 */
	public function delete_older_than( int $limit ) {
		// phpcs:disable WordPress.DB
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE time < %d",
				$limit
			)
		);
		// phpcs:enable
	}
}
