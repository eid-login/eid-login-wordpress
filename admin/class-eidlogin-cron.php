<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later, see license.txt.
 *
 * @package    eID-Login
 * @subpackage eID-Login/admin
 * @copyright  ecsec 2021
 */

declare(strict_types = 1);

/**
 * Class that implements the handling of the Cronjobs.
 */
class Eidlogin_Cron {

	public const KEYROLLOVER_PREPARE_FAILED = 1;
	public const KEYROLLOVER_EXECUTE_FAILED = 2;

	/**
	 * The (optional) Twig template.
	 *
	 * @var \Twig\Environment $twig The Twig template object.
	 */
	private $twig;

	/**
	 * Constructor of the Cron class.
	 *
	 * @param \Twig\Environment $twig The Twig template.
	 */
	public function __construct( \Twig\Environment $twig = null ) {
		$this->twig = $twig;
	}

	/**
	 * Filter function that defines a custom interval for cron.
	 *
	 * This is mainly for developing and debugging as there are default
	 * intervals provided by WordPress like hourly and daily.
	 *
	 * Callback for filter hook `cron_schedules`.
	 *
	 * @param array $schedules An array with the current scheduled tasks.
	 */
	public function eidlogin_cron_interval( $schedules ) : array {
		$schedules['eidlogin_interval'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 300 Seconds' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the cronjob for the certificate expiration.
	 */
	public function schedule_cert() : void {
		if ( ! wp_next_scheduled( EIDLOGIN_CERT_CRON_HOOK ) ) {
			$timestamp = time() + 61;

			$rs = wp_schedule_event( $timestamp, 'daily', EIDLOGIN_CERT_CRON_HOOK );
			if ( true === $rs ) {
				Eidlogin_Helper::write_log( 'Successfully scheduled the certificate hook.' );
			} else {
				Eidlogin_Helper::write_log( 'Failed to schedule the certificate hook.' );
			}
		}
	}

	/**
	 * Schedule the cronjob for the cleanup tasks.
	 */
	public function schedule_cleanup() : void {
		if ( ! wp_next_scheduled( EIDLOGIN_CLEANUP_CRON_HOOK ) ) {
			$timestamp = time() + 61;

			$rs = wp_schedule_event( $timestamp, 'daily', EIDLOGIN_CLEANUP_CRON_HOOK );
			if ( true === $rs ) {
				Eidlogin_Helper::write_log( 'Successfully scheduled the cleanup hook.' );
			} else {
				Eidlogin_Helper::write_log( 'Failed to schedule the cleanup hook.' );
			}
		}
	}

	/**
	 * Function that actually runs the certificate job every cron interval.
	 *
	 * Callback for custom action hook `EIDLOGIN_CERT_CRON_HOOK`.
	 */
	public function eidlogin_cert_cron_run() : void {
		// For testing use https://wordpress.p396.de/wp-content/plugins/eidlogin/crontest.php.
		try {
			$certs = new Eidlogin_Certificates();

			$now                      = new DateTimeImmutable();
			$act_dates                = $certs->get_act_dates();
			$remaining_valid_interval = $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ]->diff( $now );
			Eidlogin_Helper::write_log( 'Certificate remains valid for ' . $remaining_valid_interval->days . ' days.', 'Cron:' );

			$prep_span = 56; // 2 month
			$exe_span  = 28; // 1 month

			// Are we in key rollover execute span?
			if ( $remaining_valid_interval->days <= $exe_span ) {
				Eidlogin_Helper::write_log( 'Certificate Job is in key rollover execute span ...', 'Cron:' );
				try {
					$certs->do_rollover();
					$this->inform_on_rollover();
					Eidlogin_Helper::write_log( 'Certificate Job rollover executed. Done!', 'Cron:' );
				} catch ( Exception $e ) {
					Eidlogin_Helper::write_log( 'Certificate Job: failed to make rollover to new cert: ' . $e->getMessage(), 'Cron:' );
					$this->inform_on_error( self::KEYROLLOVER_EXECUTE_FAILED, $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ], $e->getMessage() );
					Eidlogin_Helper::write_log( 'Certificate Job: informed admins and removed job. Done.', 'Cron:' );
				}

				return;
			}

			// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
			// Activate for debugging this path.
			// $prep_span = $remaining_valid_interval->days;
			// phpcs:enable

			// Are we in key rollover prepare span?
			if ( $remaining_valid_interval->days <= $prep_span ) {
				Eidlogin_Helper::write_log( 'Certificate Job is in key rollover prepare span ...', 'Cron:' );

				if ( $certs->check_new_cert_present() ) {
					Eidlogin_Helper::write_log( 'Certificate Job: new cert already present. Done!', 'Cron:' );
					return;
				}

				try {
					$certs->do_prepare();

					Eidlogin_Helper::write_log( 'Certificate Job: new cert created ...', 'Cron:' );
					$valid_to    = $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ];
					$activate_on = $valid_to->modify( '-' . $exe_span . ' days' );
					$this->inform_on_new_cert( $valid_to, $activate_on );
					Eidlogin_Helper::write_log( 'Certificate Job: admins informed. Done!', 'Cron:' );
				} catch ( Exception $e ) {
					Eidlogin_Helper::write_log( 'Certificate Job: failed to create a new cert: ' . $e->getMessage(), 'Cron:' );
					$this->inform_on_error( self::KEYROLLOVER_EXECUTE_FAILED, $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ], $e->getMessage() );
					Eidlogin_Helper::write_log( 'Certificate Job: informed admins and removed job. Done.', 'Cron:' );
				}

				return;
			}

			// Nothing to do.
			Eidlogin_Helper::write_log( 'Certificate Job is NOT in key rollover prepare or execute span ... Nothing to do. Done!', 'Cron:' );
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( 'Certificate Job failed: ' . $e->getMessage(), 'Cron:' );
			$this->inform_on_error( self::KEYROLLOVER_EXECUTE_FAILED, $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ], $e->getMessage() );
			Eidlogin_Helper::write_log( 'Certificate Job: informed admins and removed job. Done.', 'Cron:' );

			return;
		}
	}

	/**
	 * Inform admins about a certificate rollover via mail.
	 */
	private function inform_on_rollover() : void {
		$subject = 'WordPress eID Login Certificate Rollover executed';
		$admins  = $this->get_admin_users();

		foreach ( $admins as $admin ) {
			$email = trim( $admin->user_email );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$body = $this->twig->render(
					'mails/cert-rollover.html',
					array(
						'display_name' => $admin->display_name,
					)
				);

				$rs = Eidlogin_Helper::send_mail( $email, $subject, $body );
				if ( false === $rs ) {
					Eidlogin_Helper::write_log( 'Failed to sent mail. Here is the body of the mail:', 'Mail:' );
					Eidlogin_Helper::write_log( $body, 'Mail:' );
				}
			}
		}
	}

	/**
	 * Inform admins about a new certificate via mail.
	 *
	 * @param DateTimeImmutable $valid_to Date until the actual certificate is valid.
	 * @param DateTimeImmutable $activate_on Date when the new certificate will be activated.
	 */
	private function inform_on_new_cert( DateTimeImmutable $valid_to, DateTimeImmutable $activate_on ) : void {
		$subject = 'WordPress eID Login Certificate Rollover prepared';
		$admins  = $this->get_admin_users();

		foreach ( $admins as $admin ) {
			$email = trim( $admin->user_email );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$body = $this->twig->render(
					'mails/cert-new.html',
					array(
						'display_name' => $admin->display_name,
						'metadata_url' => EIDLOGIN_METADATA_URL,
						'valid_to'     => $valid_to->format( 'Y-m-d' ),
						'activate_on'  => $activate_on->format( 'Y-m-d' ),
					)
				);

				$rs = Eidlogin_Helper::send_mail( $email, $subject, $body );
				if ( false === $rs ) {
					Eidlogin_Helper::write_log( 'Failed to sent mail. Here is the body of the mail:', 'Mail:' );
					Eidlogin_Helper::write_log( $body, 'Mail:' );
				}
			}
		}
	}

	/**
	 * Inform admins about an error via mail and remove the job.
	 *
	 * @param int               $error_type Type of error.
	 * @param DateTimeImmutable $valid_to ValidTo date of the actual certificate.
	 * @param string            $msg The message of the Exception.
	 */
	private function inform_on_error( int $error_type, DateTimeImmutable $valid_to, string $msg = '' ) : void {
		$subject = 'WordPress eID Login Certificate Rollover error';
		$admins  = $this->get_admin_users();

		foreach ( $admins as $admin ) {
			$email = trim( $admin->user_email );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {

				if ( self::KEYROLLOVER_PREPARE_FAILED === $error_type ) {
					$error = 'Failed to create new certificates.';
				} elseif ( self::KEYROLLOVER_EXECUTE_FAILED === $error_type ) {
					$error = 'Failed to activate new certificates.';
				}

				if ( ! empty( trim( $msg ) ) ) {
					$msg = 'Exception Message: ' . $msg;
				}

				$body = $this->twig->render(
					'mails/cert-error.html',
					array(
						'display_name' => $admin->display_name,
						'error'        => $error,
						'msg'          => $msg,
						'valid_to'     => $valid_to->format( 'Y-m-d' ),
					)
				);

				$rs = Eidlogin_Helper::send_mail( $email, $subject, $body );
				if ( false === $rs ) {
					Eidlogin_Helper::write_log( 'Failed to sent mail. Here is the body of the mail:', 'Mail:' );
					Eidlogin_Helper::write_log( $body, 'Mail:' );
				}
			}
		}

		// Remove cronjob / hook.
		$timestamp = wp_next_scheduled( EIDLOGIN_CERT_CRON_HOOK );
		wp_unschedule_event( $timestamp, EIDLOGIN_CERT_CRON_HOOK );
		Eidlogin_Helper::write_log( 'Hook eidlogin_cert_cron_hook removed.', 'Cron:' );
	}

	/**
	 * Get all admins from the database.
	 *
	 * @return array Array of WP_User objects with the role 'administrator'.
	 */
	private function get_admin_users() : array {
		$args = array(
			'role'   => 'administrator',
			'fields' => array( 'display_name', 'user_email' ),
		);
		return get_users( $args );
	}

	/**
	 * Function that actually runs the cleanup job every cron interval.
	 *
	 * It deletes all data in the tables `wp_eidlogin_eid_continuedata` and
	 * `wp_eidlogin_eid_responsedata` older than 5 minutes.
	 *
	 * Callback for custom action hook `EIDLOGIN_CLEANUP_CRON_HOOK`.
	 */
	public function eidlogin_cleanup_cron_run() : void {
		$limit = time() - EIDLOGIN_EXPIRATION_TIME;

		$cd = new Eidlogin_Continue_Data();
		$cd->delete_older_than( $limit );

		$rdata = new Eidlogin_Response_Data();
		$rdata->delete_older_than( $limit );

		Eidlogin_Helper::write_log( 'Deleted all data older than 5 minutes.', 'Cron:' );
	}
}
