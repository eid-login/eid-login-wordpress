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

use Ecsec\Eidlogin\Dep\OneLogin\Saml2\IdPMetadataParser;

/**
 * Class with helper functions.
 */
class Eidlogin_Helper {

	/**
	 * Write to error_log if debugging is enabled.
	 *
	 * @param mixed  $var The variable statement to print.
	 * @param string $prefix An optional prefix for the output.
	 */
	public static function write_log( $var, string $prefix = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			$out = 'Eidlogin ' . $prefix . ' ';

			if ( is_array( $var ) || is_object( $var ) ) {
				if ( $var instanceof DOMDocument ) {
					// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$var->preserveWhiteSpace = false;
					$var->formatOutput       = true;
					// phpcs:enable
					$out .= $var->saveXML();
				} else {
					// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$out .= "\n" . print_r( $var, true );
					// phpcs:enable
				}
			} else {
				$out .= $var;
			}

			// Remove duplicate spaces.
			$out = preg_replace( '/\s+/', ' ', $out );
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $out );
            // phpcs:enable
		}
	}

	/**
	 * Test if this WordPress instance uses TLS.
	 *
	 * Note that is_ssl() alone won't work for websites behind some load balancers.
	 *
	 * @return bool
	 */
	public static function wp_is_https() : bool {
		// Make sure to respect the forwarded headers.
		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
			$_SERVER['HTTPS'] = 'on';
		}
		return is_ssl();
	}

	/**
	 * Test if the given URL is valid and the scheme/protocol is HTTPS.
	 *
	 * @param string $url The URL to check.
	 * @return bool
	 */
	public static function url_is_https( string $url ) : bool {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return false;
		}

		// $url is valid, test if scheme/protocol is HTTPS.
		$valid_url = wp_parse_url( $url );
		if ( 'https' === $valid_url['scheme'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Test if the input value is non-empty and is of type string.
	 *
	 * @param string $input The input to check.
	 * @return bool
	 */
	public static function non_empty_string( string $input ) : bool {
		return ! empty( $input );
	}

	/**
	 * Test if the input value (if present) is valid XML.
	 *
	 * @param string $input The input to check.
	 * @return DOMDocument|bool
	 */
	public static function empty_or_valid_xml( string $input ) {
		if ( empty( trim( $input ) ) ) {
			return true;
		}
		$dom = new \DOMDocument();
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		return @$dom->loadXML( $input );
		// phpcs:enable
	}

	/**
	 * Test if the signature certificate meets the requirements. It must be
	 * present and valid.
	 *
	 * @param string $cert The certificate to check.
	 * @return bool|string
	 */
	public static function validate_sign_certificate( string $cert ) {
		if ( empty( $cert ) ) {
			return false;
		}

		return self::validate_certificate( $cert, __( 'Signature Certificate', 'eidlogin' ) );
	}

	/**
	 * Test if the encryption certificate meets the requirements. It doesn't
	 * have to be present, but if it is, it must be valid.
	 *
	 * @param string $cert The certificate to check.
	 * @return bool|string
	 */
	public static function validate_enc_certificate( string $cert ) {
		if ( empty( $cert ) ) {
			return true;
		}

		return self::validate_certificate( $cert, __( 'Encryption Certificate', 'eidlogin' ) );
	}

	/**
	 * Test if the given certificate is valid.
	 *
	 * @param string $cert The certificate to check.
	 * @param string $cert_type The type of the certificate (signature or encryption).
	 * @return bool|string
	 */
	private static function validate_certificate( string $cert, string $cert_type ) {
		try {
			$certs = new Eidlogin_Certificates();
			$certs->check_cert_pubkey_length( $cert, $cert_type );
		} catch ( Exception $e ) {
			return $e->getMessage();
		}

		return true;
	}

	/**
	 * Create a random string with an optional prefix.
	 *
	 * @param int    $length The length of the random string that should be returned in bytes, excluding the prefix.
	 * @param string $prefix An optional prefix (default: eidlogin_).
	 * @return string
	 */
	public static function random_string( int $length = 24, string $prefix = 'eidlogin_' ) : string {
		$rand = $prefix . bin2hex( random_bytes( $length ) );
		// Limit characters because of the database restriction to varchar(64).
		return substr( $rand, 0, 64 );
	}

	/**
	 * Convert a boolean value to the corresponding string value. This is the
	 * usual way WordPress saves bool values in the database.
	 *
	 * @param bool $checked The bool value from the checkbox.
	 * @return string
	 */
	public static function convert_checkbox_value( bool $checked ) : string {
		return ( true === $checked ) ? 'true' : 'false';
	}

	/**
	 * Grab the IDP metadata from the given URL.
	 *
	 * @param string $url The IDP metadata URL.
	 * @return array The IDP metadata.
	 */
	public static function get_idp_saml_metadata( $url ) {
		self::write_log( 'Grabing IDP metadata from ' . $url );

		$idp_metadata       = array();
		$idp_metadata_raw   = IdPMetadataParser::parseRemoteXML( $url );
		$idp_metadata_first = $idp_metadata_raw['idp'];

		if ( array_key_exists( 'x509cert', $idp_metadata_first ) ) {
			$idp_metadata['idp_cert_sign'] = $idp_metadata_first['x509cert'];
			$idp_metadata['idp_cert_enc']  = $idp_metadata_first['x509cert'];
		} else {
			$idp_metadata['idp_cert_sign'] = $idp_metadata_first['x509certMulti']['signing'][0];
			$idp_metadata['idp_cert_enc']  = $idp_metadata_first['x509certMulti']['encryption'][0];
		}

		$idp_metadata['idp_entity_id'] = $idp_metadata_first['entityId'];
		$idp_metadata['idp_sso_url']   = $idp_metadata_first['singleSignOnService']['url'];

		return $idp_metadata;
	}

	/**
	 * Send a mail to a recipient with a given subject and message.
	 *
	 * @param string $to The recipient where the mail is sent to.
	 * @param string $subject The subject of the mail.
	 * @param string $message The message / content of the mail.
	 * @return bool
	 */
	public static function send_mail( string $to, string $subject, string $message ) : bool {
		$msg = sprintf( "Sent to '%s' with subject '$subject' ", $to, $subject );
		self::write_log( $msg, 'Mail:' );
		return wp_mail( $to, $subject, $message );
	}
}
