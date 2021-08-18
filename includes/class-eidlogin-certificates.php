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

use Ecsec\Eidlogin\Dep\OneLogin\Saml2\Utils;

/**
 * Class that implements the handling of X.509 certificates.
 */
class Eidlogin_Certificates {

	// Use a time span of two years. If this value is changed, you must also
	// change the spans in Eidlogin_Cron!
	public const VALID_SPAN             = 730;
	public const DATES_VALID_FROM       = 'validFrom';
	public const DATES_VALID_TO         = 'validTo';
	public const KEY_LENGTH_LIMIT_LOWER = 2048;

	/**
	 * Checks for actual keys and certs in config.
	 *
	 * @return bool True if an actual key and cert has been found.
	 */
	public function check_act_cert_present() : bool {
		$options = get_option( EIDLOGIN_OPTION_NAME );
		if ( ! isset( $options['sp_key_act'], $options['sp_cert_act'], $options['sp_key_act_enc'], $options['sp_cert_act_enc'] ) ) {
			return false;
		}

		$key_str      = $options['sp_key_act'] ?? '';
		$cert_str     = $options['sp_cert_act'] ?? '';
		$key_str_enc  = $options['sp_key_act_enc'] ?? '';
		$cert_str_enc = $options['sp_cert_act_enc'] ?? '';
		if ( ! empty( $key_str ) && ! empty( $cert_str ) && ! empty( $key_str_enc ) && ! empty( $cert_str_enc ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks for new key and cert in config.
	 *
	 * @return bool True if an actual key and cert has been found.
	 */
	public function check_new_cert_present() : bool {
		$options = get_option( EIDLOGIN_OPTION_NAME );
		if ( ! isset( $options['sp_key_new'], $options['sp_cert_new'], $options['sp_key_new_enc'], $options['sp_cert_new_enc'] ) ) {
			return false;
		}

		$key_str      = $options['sp_key_new'] ?? '';
		$cert_str     = $options['sp_cert_new'] ?? '';
		$key_str_enc  = $options['sp_key_new_enc'] ?? '';
		$cert_str_enc = $options['sp_cert_new_enc'] ?? '';
		if ( ! empty( $key_str ) && ! empty( $cert_str ) && ! empty( $key_str_enc ) && ! empty( $cert_str_enc ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The DateTimes from and until the actual signature certificate is valid to.
	 *
	 * @throws Exception If the actual signature certificate could not be found, read or parsed.
	 * @return array The DateTimes form and until the actual signature certificate is valid to as assoc array.
	 */
	public function get_act_dates(): array {
		$options = get_option( EIDLOGIN_OPTION_NAME );

		$cert_act = $options['sp_cert_act'];
		if ( empty( $cert_act ) ) {
			throw new Exception( 'No actual cert found in the database.' );
		}

		$cert = openssl_x509_read( Utils::formatCert( $cert_act ) );
		if ( false === $cert ) {
			throw new Exception( 'OpenSSL error: failed to read certificate.' );
		}

		$cert_details = openssl_x509_parse( $cert );
		if ( false === $cert_details ) {
			throw new Exception( 'OpenSSL error: failed to parse certificate.' );
		}

		$ret_val                           = array();
		$ret_val[ self::DATES_VALID_FROM ] = \DateTimeImmutable::createFromFormat( 'ymdGisT', $cert_details[ self::DATES_VALID_FROM ] );
		$ret_val[ self::DATES_VALID_TO ]   = \DateTimeImmutable::createFromFormat( 'ymdGisT', $cert_details[ self::DATES_VALID_TO ] );

		return $ret_val;
	}

	/**
	 * Return the actual cert as string.
	 * An empty string is returned if no value has been found.
	 *
	 * @param string $cert_option The option of the specific certificate.
	 * @param bool   $end_only Give back only the last 20 chars of the cert if true.
	 *
	 * @return string The actual cert as string
	 */
	public function get_cert( $cert_option, $end_only = false ) : string {
		$options  = get_option( EIDLOGIN_OPTION_NAME );
		$cert_str = $options[ $cert_option ];
		if ( $end_only ) {
			$cert_str = $this->crop_cert( $cert_str );
		}

		return $cert_str;
	}

	/**
	 * Return the cropped version of a certificate.
	 *
	 * @param string $cert The given certificate.
	 * @return string The cropped version of the certificate.
	 */
	public function crop_cert( string $cert ) : string {
		return substr( $cert, strlen( $cert ) - 66, 40 );
	}

	/**
	 * Create a RSA key and generate a X.509 certificate.
	 *
	 * @param int $days The days the certificate should be valid.
	 * @throws Exception If something goes wrong with the OpenSSL extension.
	 * @return array An array containing the private key and the certificate.
	 */
	public function create_key_and_x509_cert( int $days = self::VALID_SPAN ) : array {
		if ( ! extension_loaded( 'openssl' ) ) {
			throw new Exception( 'OpenSSL error: openssl extension not available.' );
		}

		// Use our own config from the root directory.
		$openssl_configargs = array( 'config' => plugin_dir_path( __DIR__ ) . 'openssl.conf' );

		// Use the hostname as common name.
		$home_url = wp_parse_url( get_home_url() );
		$dn       = array(
			'commonName' => $home_url['host'] . ' WordPress eID-Login Plugin',
		);

		// Create the private key.
		$key = openssl_pkey_new( $openssl_configargs );
		if ( false === $key ) {
			throw new Exception( 'OpenSSL error: failed to create private key.' );
		}

		// Export the private key.
		if ( false === openssl_pkey_export( $key, $key_pem ) ) {
			throw new Exception( 'OpenSSL error: failed to export private key.' );
		}

		// Create a CSR.
		$csr = openssl_csr_new( $dn, $key, $openssl_configargs );
		if ( false === $csr ) {
			throw new Exception( 'OpenSSL error: failed to create CSR.' );
		}

		// Use current time as serial number.
		$serial = time();

		// Generate the certificate.
		$cert = openssl_csr_sign( $csr, null, $key, $days, $openssl_configargs, $serial );
		if ( false === $cert ) {
			throw new Exception( 'OpenSSL error: failed to create certificate.' );
		}

		// Export the certificate.
		if ( false === openssl_x509_export( $cert, $cert_pem ) ) {
			throw new Exception( 'OpenSSL error: failed to export certificate.' );
		}

		return array(
			'key'  => $key_pem,
			'cert' => $cert_pem,
		);
	}

	/**
	 * Prepare the certificate rollover by creating and saving a private key and
	 * a certificate for signing and encryption.
	 *
	 * @throws Exception If something goes wrong while creating the key/cert.
	 */
	public function do_prepare(): void {
		// Get the current options first.
		$options = get_option( EIDLOGIN_OPTION_NAME );

		try {
			// Create a new key and cert for signing.
			$cert_data = $this->create_key_and_x509_cert();

			$options['sp_key_new']  = $cert_data['key'];
			$options['sp_cert_new'] = $cert_data['cert'];
			$resp_data['cert_new']  = $this->crop_cert( $cert_data['cert'] );

			// Create a new key and cert for encryption.
			$cert_data = $this->create_key_and_x509_cert();

			$options['sp_key_new_enc']  = $cert_data['key'];
			$options['sp_cert_new_enc'] = $cert_data['cert'];
			$resp_data['cert_new_enc']  = $this->crop_cert( $cert_data['cert'] );

			// Save the updated options.
			update_option( EIDLOGIN_OPTION_NAME, $options );
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'Cannot prepare rollover.' );
		}
	}

	/**
	 * Execute the certificate rollover.
	 */
	public function do_rollover(): void {
		// Get the current options first.
		$options = get_option( EIDLOGIN_OPTION_NAME );

		// Backup the actual keys and certs.
		$options['sp_key_old']      = $options['sp_key_act'];
		$options['sp_cert_old']     = $options['sp_cert_act'];
		$options['sp_key_old_enc']  = $options['sp_key_act_enc'];
		$options['sp_cert_old_enc'] = $options['sp_cert_act_enc'];

		// Set the new keys and certs to the actual ones.
		$options['sp_key_act']      = $options['sp_key_new'];
		$options['sp_cert_act']     = $options['sp_cert_new'];
		$options['sp_key_act_enc']  = $options['sp_key_new_enc'];
		$options['sp_cert_act_enc'] = $options['sp_cert_new_enc'];

		// Reset the (previously) new keys and certs.
		$options['sp_key_new']      = '';
		$options['sp_cert_new']     = '';
		$options['sp_key_new_enc']  = '';
		$options['sp_cert_new_enc'] = '';

		// Save the updated options.
		update_option( EIDLOGIN_OPTION_NAME, $options );
	}

	/**
	 * Check if the public key of a given certificate has a longer key than the
	 * limit.
	 *
	 * @param string $cert The certificate to check.
	 * @param string $cert_type The type of the certificate (signature or encryption).
	 *
	 * @return true If the key length is longer than the limit.
	 * @throws Exception If the input can not be handled as certificate.
	 */
	public function check_cert_pubkey_length( string $cert, string $cert_type ) : bool {
		/* Translators: %s: the type of the certificate (signature or encryption) */
		$user_msg = sprintf( __( 'The %s of the Identity Provider could not be read.', 'eidlogin' ), $cert_type );

		$pubkey = openssl_pkey_get_public( Utils::formatCert( $cert ) );
		if ( ! $pubkey ) {
			Eidlogin_Helper::write_log( 'Could not read public key of x509 cert string.' );
			throw new Exception( $user_msg );
		}

		$pubkey_details = openssl_pkey_get_details( $pubkey );
		if ( ! $pubkey_details ) {
			Eidlogin_Helper::write_log( 'Could not read public key details.' );
			throw new Exception( $user_msg );
		}

		if ( $pubkey_details['bits'] < self::KEY_LENGTH_LIMIT_LOWER ) {
			$admin_msg = sprintf(
				'Key size of public key is too small (%s vs. %d)',
				$pubkey_details['bits'],
				self::KEY_LENGTH_LIMIT_LOWER
			);
			Eidlogin_Helper::write_log( $admin_msg );

			/* Translators: %1$s The type of the certificate (signature or encryption) and %2$s the minimal valid key length */
			$user_msg = sprintf( __( 'The %1$s of the Identity Provider has an insufficient public key length. The minimal valid key length is %2$s.', 'eidlogin' ), $cert_type, self::KEY_LENGTH_LIMIT_LOWER );
			throw new Exception( $user_msg );
		}

		return true;
	}

}
