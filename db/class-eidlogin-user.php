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
 * Class representing a row of the eidlogin_eid_continuedata table.
 */
class Eidlogin_User {

	/**
	 * A reference to the global database handle.
	 *
	 * @var wpdb $wpdb The database handle.
	 */
	private $wpdb;

	/**
	 * The database table name that is used for the user's table.
	 *
	 * @var string $table The database table.
	 */
	private $eid_users_table;

	/**
	 * The database table name that is used for the user's attribute table.
	 *
	 * @var string $table The database table.
	 */
	private $eid_attr_table;

	/**
	 * Constructor of the Eidlogin_User class.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb            = $wpdb;
		$this->eid_users_table = $wpdb->prefix . EIDLOGIN_EID_USERS_TABLE;
		$this->eid_attr_table  = $wpdb->prefix . EIDLOGIN_EID_ATTRIBUTES_TABLE;
	}

	/**
	 * Check if a user with a given eID exists in the database.
	 *
	 * @param string $name_id The given name_id.
	 * @return int|bool Returns the user ID if present, false otherwise.
	 */
	public function get_user_id( string $name_id ) {
		$users_table = $this->wpdb->users;

		$sql  = "SELECT u.ID FROM $users_table u ";
		$sql .= "JOIN $this->eid_users_table e ON (u.ID = e.uid) WHERE e.eid = %s";

		$db_user_id = intval( $this->wpdb->get_var( $this->wpdb->prepare( $sql, $name_id ) ) );

		if ( 0 === $this->wpdb->num_rows ) {
			return false;
		}

		return $db_user_id;
	}

	/**
	 * Check if the given user has a eID assigned to his account.
	 *
	 * @param int $uid The given user id.
	 * @return bool true if the user has a eID assigned.
	 */
	public function is_connected( int $uid ) : bool {
		$sql = "SELECT COUNT(*) as count FROM $this->eid_users_table e WHERE e.uid = %d";

		$connections = $this->wpdb->get_row(
			$this->wpdb->prepare( $sql, $uid )
		);

		return ( 1 === intval( $connections->count ) ) ? true : false;
	}

	/**
	 * Assign the eid to the uid of the user.
	 *
	 * @param string $eid The eID of the user to assign.
	 * @param int    $uid The uID of the user to assign.
	 * @throws Exception If database operation cannot be applied.
	 */
	public function save_eid( string $eid, int $uid ) : void {
		// Using $wpdb->insert() for custom tables is appropriate.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rs = $this->wpdb->insert(
			$this->eid_users_table,
			array(
				'eid' => $eid,
				'uid' => $uid,
			)
		);
		// phpcs:enable

		if ( false === $rs ) {
			Eidlogin_Helper::write_log( $this->wpdb->last_error, 'DB error: ' );
			throw new Exception( __( 'Cannot save eID for the current user.', 'eidlogin' ) );
		}
	}

	/**
	 * Save the attributes from the SAML response in the database.
	 *
	 * @param array $attributes The SAML attributes to be saved.
	 * @param int   $uid The uID of the user.
	 * @throws Exception If database operation cannot be applied.
	 */
	public function save_attributes( array $attributes, int $uid ) {
		foreach ( $attributes as $name => $values ) {
			if ( count( $values ) === 0 ) {
				continue;
			}
			$value_count = 0;
			foreach ( $values as $value ) {
				$current_name = $name;
				if ( $value_count > 0 ) {
					$current_name .= '_' . strval( $value_count );
				}

				// Using $wpdb->insert() for custom tables is appropriate.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$rs = $this->wpdb->insert(
					$this->eid_attr_table,
					array(
						'uid'   => $uid,
						'name'  => $current_name,
						'value' => $value,
					)
				);
				// phpcs:enable

				if ( false === $rs ) {
					Eidlogin_Helper::write_log( $this->wpdb->last_error, 'DB error: ' );
					throw new Exception( 'Cannot insert attribute ' . $current_name );
				}
				$value_count++;
			}
		}
	}

	/**
	 * Remove all eID related data for a specific user.
	 *
	 * @param int $id The ID of the user.
	 */
	public function remove_eid_data( int $id ) {
		// phpcs:disable WordPress.DB
		$sql  = "DELETE FROM $this->eid_users_table ";
		$sql .= 'WHERE uid = %s';
		$this->wpdb->query( $this->wpdb->prepare( $sql, $id ) );

		// We MUST (re-) enable the password login if we remove the eID!
		update_user_meta( $id, EIDLOGIN_DISABLE_PASSWORD, 'false' );
		Eidlogin_Helper::write_log( 'Password login re-enabled.' );

		$sql  = "DELETE FROM $this->eid_attr_table ";
		$sql .= 'WHERE uid = %s';
		$this->wpdb->query( $this->wpdb->prepare( $sql, $id ) );
		// phpcs:enable
	}

}
