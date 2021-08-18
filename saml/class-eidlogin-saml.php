<?php
/**
 * This file is licensed under the Affero General Public License version 3 or
 * later, see license.txt.
 *
 * @package    eID-Login
 * @subpackage eID-Login/saml
 * @copyright  ecsec 2021
 */

declare(strict_types = 1);

use Ecsec\Eidlogin\Dep\OneLogin\Saml2\Auth;
use Ecsec\Eidlogin\Dep\OneLogin\Saml2\Settings;
use Ecsec\Eidlogin\Dep\OneLogin\Saml2\Utils;

/**
 * Class that handles the SAML and TR-03130 flow.
 *
 * First there are functions that implement routes, than functions that
 * implement hooks and at the bottom there are private helper functions.
 */
class Eidlogin_Saml {

	const COOKIE_NAME   = 'wp_eidlogin';
	const FLOW_LOGIN    = 'login';
	const FLOW_REGISTER = 'register';
	const KEY_COOKIE    = 'wp_eidlogin_cookie';
	const KEY_FLOW      = 'wp_eidlogin_flow';
	const KEY_USER_ID   = 'wp_eidlogin_user_id';
	const NONCE_NAME    = 'wp_saml';

	const EID_CLIENT_BASEURL = 'http://127.0.0.1:24727/eID-Client?tcTokenURL=';

	/**
	 * The plugin name.
	 *
	 * @var string $plugin_name The plugin name.
	 */
	private $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @var string $version The plugin version.
	 */
	private $version;

	/**
	 * The Twig template.
	 *
	 * @var \Twig\Environment $twig The Twig template object.
	 */
	private $twig;

	/**
	 * Constructor of the Eidlogin_Saml class.
	 *
	 * @param string            $plugin_name The name of the plugin.
	 * @param string            $version The version of the plugin.
	 * @param \Twig\Environment $twig The Twig template.
	 */
	public function __construct( string $plugin_name, string $version, \Twig\Environment $twig ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->twig        = $twig;
	}

	/**
	 * Enqueue our custom CSS file.
	 *
	 * Callback for action hook `login_enqueue_scripts`.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/eidlogin-saml.css', array(), $this->version, 'all' );
	}

	/**
	 * Handle the SAML and TR-03130 routes.
	 *
	 * Callback for action hook `init`.
	 *
	 * Routes are:
	 * /wp-login.php?{saml_login,saml_register,tctoken,resume,saml_metadata,saml_acs}
	 */
	public function saml_routes() {
		// Return if triggered by WP-Cron.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) ) {
			$scriptname = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) );
		}

		// Only handle specific routes (wp-login.php with specific GET parameters).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'wp-login.php' !== $scriptname || empty( $_GET ) ) {
			return;
		}

		// Only handle SAML routes if all requirements are met.
		if ( $this->eidlogin_is_available() === true ) {
			if ( isset( $_GET['saml_acs'] ) ) {
				$this->saml_acs();
			} elseif ( isset( $_GET['tctoken'] ) ) {
				$this->tctoken();
			} elseif ( isset( $_GET['resume'] ) ) {
				$this->resume();
			} elseif ( isset( $_GET['saml_register'] ) ) {
				$this->saml_register();
			} elseif ( isset( $_GET['saml_login'] ) ) {
				$this->saml_login();
			}
		}

		if ( isset( $_GET['saml_metadata'] ) ) {
			$this->saml_metadata();
		}
		// phpcs:enable
	}

	/**
	 * Create and display the SP SAML Metadata as XML.
	 *
	 * The SP Metadata are shown only if the plugin is activated or if the
	 * settings are correct and the caller is an administrator. This is the case
	 * in step 2 (IdP) of the wizard where otherwise the retrieval of the SP
	 * metadata via XHR would not be possible. In all other cases return status
	 * code 404.
	 */
	public function saml_metadata() {
		if ( $this->eidlogin_is_available() === true ||
			( $this->eidlogin_is_available( false ) === true && is_user_logged_in() && current_user_can( 'administrator' ) ) ) {
				$saml_settings = new Settings( $this->saml_settings(), true );
				$metadata      = $saml_settings->getSPMetadata();

				header( 'Content-Type: text/xml' );
				// Output should be unescaped XML.
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo ent2ncr( $metadata );
				// phpcs:enable
				exit();
		}

		http_response_code( 404 );
		exit();
	}

	/**
	 * Handle the registration of an eID from the user's profile or directly
	 * from the wizard.
	 */
	private function saml_register() {
		$this->saml_login( self::FLOW_REGISTER );
	}

	/**
	 * Handle the SAML login from the WordPress login screen and the assignment
	 * of the eID from the user's profile.
	 *
	 * @param string $flow The eID flow (login or register).
	 */
	private function saml_login( string $flow = self::FLOW_LOGIN ) {
		Eidlogin_Helper::write_log( sprintf( "Starting authentication with flow '%s'.", $flow ) );

		$auth = $this->get_saml_auth();

		// Create a random unique ID as requestId for validating the Response in
		// saml_acs() in order to prevent replay attacks.
		$uid = Eidlogin_Helper::random_string();
		Eidlogin_Helper::write_log( $uid, 'Created unique request id: ' );

		// Create a random unique ID and save it in a cookie.
		$cookie_id = Eidlogin_Helper::random_string();
		Eidlogin_Helper::write_log( $cookie_id, 'Created unique cookie id: ' );
		setcookie( self::COOKIE_NAME, $cookie_id, time() + 60 * 5, '/', '', true, true );

		// Data we need to continue after returning.
		$continue = array(
			self::KEY_FLOW    => $flow,
			self::KEY_USER_ID => get_current_user_id(),
			self::KEY_COOKIE  => $cookie_id,
		);

		// Save continue data.
		$cd = new Eidlogin_Continue_Data();
		$cd->save( $uid, wp_json_encode( $continue ), time() );
		Eidlogin_Helper::write_log( 'Successfully saved continue data.' );

		// Create url for redirect.
		$url = null;
		// If we have a TR-03130 flow, we need another redirect step to let the
		// eID-Client fetch the TC token from us, not the eID-Server directly.
		if ( $this->check_for_tr03130() ) {
			$tctoken_url = get_home_url() . '/wp-login.php?tctoken=' . rawurlencode( $uid );
			$url         = self::EID_CLIENT_BASEURL . rawurlencode( $tctoken_url );
			Eidlogin_Helper::write_log( 'Redirecting to ' . $url, 'TR-03130:' );
		} else {
			$url = $auth->login( null, array(), false, false, true, true, null, $uid );
		}

		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Handle the TC Token endpoint.
	 *
	 * It redirects the eID-Client to the eID-Server to fetch the TcToken.
	 */
	public function tctoken() {
		// Using a nonce here doesn't make sense because the call of this
		// function always comes from an external eID client.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tctoken'] ) ) {
			return;
		}

		$req_id_raw = filter_var( wp_unslash( $_GET['tctoken'] ), FILTER_SANITIZE_STRING );
		$req_id     = rawurldecode( $req_id_raw );
		// phpcs:enable

		$auth = $this->get_saml_auth();

		$url = $auth->login( null, array(), false, false, true, true, null, $req_id );
		Eidlogin_Helper::write_log( $auth->getLastRequestXML(), 'AuthnRequestXML: ' );

		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Process an incoming SAML Response.
	 *
	 * @throws Exception If the SAML Response is invalid.
	 */
	private function saml_acs() {
		$saml_settings   = null;
		$auth            = null;
		$response        = null;
		$response_as_xml = null;
		$flow            = self::FLOW_LOGIN;

		try {
			// Get the \OneLogin\Saml2\Response from the request.
			$saml_settings   = $this->saml_settings();
			$auth            = new Auth( $saml_settings );
			$response        = $auth->createResponse();
			$response_as_xml = $response->getXMLDocument();
			$in_response_to  = null;

			// Check the InResponseTo value.
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $response_as_xml->documentElement->hasAttribute( 'InResponseTo' ) ) {
				// See https://research.nccgroup.com/2021/03/29/saml-xml-injection/.
				$in_response_to_raw = $response_as_xml->documentElement->getAttribute( 'InResponseTo' );
				$in_response_to     = filter_var( $in_response_to_raw, FILTER_SANITIZE_STRING );
				Eidlogin_Helper::write_log( $in_response_to, 'InResponseTo attribute: ' );
			} else {
				throw new Exception( 'Missing inResponseTo Attribute in SAML Response.' );
			}
			// phpcs:enable

			// Load the continue data from the database.
			$cdata         = new Eidlogin_Continue_Data();
			$continue_data = $cdata->get_by_uid( $in_response_to );
			if ( null === $continue_data ) {
				throw new Exception( 'eID continue data not found in database (probably deleted via cronjob).' );
			}

			$continue_data_array = get_object_vars( json_decode( $continue_data->value ) );
			$flow                = $continue_data_array[ self::KEY_FLOW ];

			// Validate the algorithms according to the profile.
			if ( $this->check_for_tr03130() ) {
				$response_as_xml_enc = $response->getXMLDocument( true );
				$enc_method_list     = Utils::query( $response_as_xml_enc, '/samlp:Response/saml:EncryptedAssertion/xenc:EncryptedData/xenc:EncryptionMethod' );
				if ( count( $enc_method_list ) !== 1 ) {
					throw new Exception( 'Expected one EncryptionMethod Node as child of EncryptedData but found ' . count( $enc_method_list ) );
				}
				if ( ! $enc_method_list[0]->hasAttribute( 'Algorithm' ) ) {
					throw new Exception( 'Found a EncryptionMethod Node for EncryptedData but missing Algorithm Attribute.' );
				}
				if ( ! in_array( $enc_method_list[0]->getAttribute( 'Algorithm' ), $saml_settings['alg']['encryption']['data'], true ) ) {
					throw new Exception( 'Found a EncryptionMethod Node for Encrypted Data with invalid Algorithm Attribute: ' . $enc_method_list[0]->getAttribute( 'Algorithm' ) );
				}
				$enc_method_list = Utils::query( $response_as_xml_enc, '/samlp:Response/saml:EncryptedAssertion/xenc:EncryptedData/ds:KeyInfo/xenc:EncryptedKey/xenc:EncryptionMethod' );
				if ( count( $enc_method_list ) !== 1 ) {
					throw new Exception( 'Expected one EncryptionMethod Node as child of EncryptedKey but found ' . count( $enc_method_list ) );
				}
				if ( ! $enc_method_list[0]->hasAttribute( 'Algorithm' ) ) {
					throw new Exception( 'Found a EncryptionMethod Node for EncryptedKey but missing Algorithm Attribute.' );
				}
				if ( ! in_array( $enc_method_list[0]->getAttribute( 'Algorithm' ), $saml_settings['alg']['encryption']['key'], true ) ) {
					throw new Exception( 'Found a EncryptionMethod Node for EncryptedKey with invalid Algorithm Attribute: ' . $enc_method_list[0]->getAttribute( 'Algorithm' ) );
				}
			} else {
				$response_as_xml_enc = $response->getXMLDocument();
				$sign_method_list    = Utils::query( $response_as_xml_enc, '/samlp:Response/saml:Assertion/ds:Signature/ds:SignedInfo/ds:SignatureMethod' );
				if ( count( $sign_method_list ) === 1 ) {
					if ( ! $sign_method_list[0]->hasAttribute( 'Algorithm' ) ) {
						throw new Exception( 'Found a SignatureMethodNode but missing Algorithm Attribute' );
					}
					if ( ! in_array( $sign_method_list[0]->getAttribute( 'Algorithm' ), $saml_settings['alg']['signing'], true ) ) {
						throw new Exception( 'Found a SignatureMethodNode with invalid Algorithm Attribute: ' . $sign_method_list[0]->getAttribute( 'Algorithm' ) );
					}
				} elseif ( count( $sign_method_list ) > 1 ) {
					throw new Exception( 'Expected max one SignatureMethod Node but found ' . count( $sign_method_list ) );
				}
			}

			// Check that the continue data is not older than 5 min.
			$time  = $continue_data->time;
			$limit = time() - EIDLOGIN_EXPIRATION_TIME;
			if ( $time < $limit ) {
				throw new Exception( 'eID continue data found for inResponseTo: ' . $in_response_to . ' is expired.' );
			}

			// Delete the continue_data (this is also triggered via Cronjob).
			$cdata->delete_by_uid( $in_response_to );
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'Auth:' );

			$url = wp_login_url() . '?eid_error=login';

			if ( self::FLOW_REGISTER === $flow ) {
				// Authentication started in the user's profile or wizard.
				$msg = rawurlencode( __( 'Error while validating the SAML Response.', 'eidlogin' ) );
				$url = admin_url() . 'profile.php?eid_error=' . $msg . '#eid-header';
			}

			wp_safe_redirect( $url );
			exit();
		}

		// Process the SAML response and gather its data.
		$attributes_as_xml = array();
		$errors            = array();
		$eid               = null;

		try {
			$auth->processCreatedResponse( $response );

			// Get and save errors that might have been occurred.
			$errors = $auth->getErrors();
			if ( count( $errors ) === 0 ) {
				// Extract the eID according to the profile.
				if ( $this->check_for_tr03130() ) {
					// We must verify the external signature. Check if the query parameter exists.
					// phpcs:disable WordPress.Security.NonceVerification.Recommended
					if ( ! array_key_exists( 'SigAlg', $_GET ) ) {
						throw new Exception( 'Missing SigAlg param.' );
					}

					// Check if the given signature algorithm is allowed according to the settings.
					$sig_alg_given = filter_var( wp_unslash( $_GET['SigAlg'] ), FILTER_SANITIZE_STRING );
					if ( ! in_array( $sig_alg_given, $saml_settings['alg']['signing'], true ) ) {
						throw new Exception( 'Invalid SigAlg param ' . $sig_alg_given );
					}

					// Do the actual validation.
					Utils::validateBinarySign( 'SAMLResponse', $_GET, $saml_settings['idp'] );

					$attributes = $response->getAttributes();
					if ( array_key_exists( 'RestrictedID', $attributes ) && count( $attributes['RestrictedID'] ) === 1 ) {
						$eid = $attributes['RestrictedID'][0];
					}
					// phpcs:enable
				} else {
					$eid = $response->getNameId();
				}

				// In both cases get and assign the attributes.
				$attributes_as_xml = $response->getAttributesAsXML();
			}
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( 'Error processing SAML Response for user id ' . get_current_user_id() . ': ' . $e->getMessage() );
			$errors[] = $e->getMessage();
		}

		// Build the response data.
		$response_id = Eidlogin_Helper::random_string();
		Eidlogin_Helper::write_log( $response_id, 'Created unique response id: ' );

		$response_data = array(
			'isAuthenticated'    => $auth->isAuthenticated(),
			'lastErrorException' => $auth->getLastErrorException(),
			'errors'             => $errors,
			'status'             => Utils::getStatus( $response_as_xml ),
			'eid'                => $eid,
			'attributes'         => $attributes_as_xml,
		);

		// Merge the data of the two arrays.
		$continue_data = get_object_vars( json_decode( $continue_data->value ) );
		$response_data = array_merge( $response_data, $continue_data );

		// If we have a TR-03130 flow, we need another redirect step, for this
		// we save the response data to the database.
		if ( $this->check_for_tr03130() ) {
			$response_data = wp_json_encode( $response_data );
			$rdata         = new Eidlogin_Response_Data();
			$rdata->save( $response_id, $response_data, time() );
			Eidlogin_Helper::write_log( 'Successfully saved response data.' );

			$redirect_url = get_home_url() . '/wp-login.php?resume=' . rawurlencode( $response_id );
			Eidlogin_Helper::write_log( 'Redirect to resume URL: ' . $redirect_url, 'TR-03130: ' );
			wp_safe_redirect( $redirect_url );
		} else {
			// Otherwise process the SAML data now.
			$this->process_saml_response_data( $response_id, $response_data );
		}
	}

	/**
	 * This action should resume after a SAML Flow.
	 *
	 * The SAML Response must have been delivered by an TR-03130 eID-Client before.
	 */
	public function resume() {
		// Using a nonce here doesn't make sense because the call of this
		// function always comes from an external eID client.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['resume'] ) ) {
			return;
		}

		$req_id_raw = filter_var( wp_unslash( $_GET['resume'] ), FILTER_SANITIZE_STRING );
		$req_id     = rawurldecode( $req_id_raw );
		Eidlogin_Helper::write_log( $req_id, 'TR-03130: Resumed request id: ' );
		// phpcs:enable

		$this->process_saml_response_data( $req_id );
	}

	/**
	 * Process the data from a SAML Response.
	 *
	 * If only a response_id is given, the data is fetched from the database.
	 *
	 * @param string|null $response_id The unique id of the response.
	 * @param array|null  $response_data The data of the response.
	 * @throws Exception If an error occurs during the processing of the response..
	 */
	public function process_saml_response_data( $response_id = null, $response_data = null ) {
		if ( null === $response_id ) {
			throw new Exception( 'Missing response_id while processing the SAML response.' );
		}

		if ( null === $response_data ) {
			try {
				$rdata         = new Eidlogin_Response_Data();
				$response_data = $rdata->get_by_uid( $response_id );
				if ( null === $response_data ) {
					throw new Exception( 'Could not find response_data for response_id: ' . $response_id );
				}
				$rdata->delete_by_uid( $response_id );
				$response_data = get_object_vars( json_decode( $response_data->value ) );
			} catch ( Exception $e ) {
				Eidlogin_Helper::write_log( $e->getMessage(), 'Auth:' );
			}
		}

		// Get values from the response data.
		$user_id_response   = $response_data[ self::KEY_USER_ID ];
		$cookie_id_response = $response_data[ self::KEY_COOKIE ];
		$flow               = $response_data[ self::KEY_FLOW ];

		// Check if the cookie is present and its ID matches the one in the database.
		if ( ! array_key_exists( self::COOKIE_NAME, $_COOKIE ) ) {
			Eidlogin_Helper::write_log( 'Cookie not found while processing the SAML Response.' );
			wp_safe_redirect( wp_login_url() . '?eid_error' );
			exit();
		}

		$cookie_id_cookie = filter_var( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ), FILTER_SANITIZE_STRING );
		// Delete the cookie by setting its expiration date to the past.
		setcookie( self::COOKIE_NAME, '', time() - 1, '/', '', true, true );

		if ( $cookie_id_cookie !== $cookie_id_response ) {
			$msg = sprintf(
				'Given cookie ID does not match the expected ID from the database: %s vs. %s.',
				$cookie_id_cookie,
				$cookie_id_response
			);

			Eidlogin_Helper::write_log( $msg );
			wp_safe_redirect( wp_login_url() . '?eid_error' );
			exit();
		}

		$errors = $response_data['errors'];
		if ( ! empty( $errors ) ) {
			$url = wp_login_url() . '?eid_error=login';

			// Handle the cancellation of the authentication.
			if ( $this->user_canceled_login( $response_data['status'] ) ) {
				Eidlogin_Helper::write_log( 'User canceled the authentication process.' );

				// Check if user is logged in (don't rely on is_user_logged_in()!).
				if ( 0 !== $user_id_response ) {
					// Authentication started in the user's profile or wizard.
					$msg = rawurlencode( __( 'Creation of eID connection aborted', 'eidlogin' ) );
					$url = admin_url() . 'profile.php?eid_error=' . $msg . '#eid-header';
				} else {
					$url = wp_login_url() . '?eid_error=canceled';
				}
			} else {
				// Actual errors occurred, write them to the log file.
				foreach ( $errors as $error ) {
					Eidlogin_Helper::write_log( $error );
				}

				// Check if user is logged in (don't rely on is_user_logged_in()!).
				if ( 0 !== $user_id_response ) {
					// Authentication started in the user's profile or wizard.
					$msg = rawurlencode( __( 'Creation of eID connection failed! Please ensure the used eID-Card is valid.', 'eidlogin' ) );
					$url = admin_url() . 'profile.php?eid_error=' . $msg . '#eid-header';
				}
			}

			wp_safe_redirect( $url );
			exit();
		}

		// See https://research.nccgroup.com/2021/03/29/saml-xml-injection/.
		$eid = filter_var( $response_data['eid'], FILTER_SANITIZE_STRING );
		if ( empty( $eid ) ) {
			Eidlogin_Helper::write_log( 'The SAML assertion does not contain a eID.' );
			wp_safe_redirect( wp_login_url() . '?eid_error' );
			exit();
		}

		$eidlogin_user = new Eidlogin_User();

		$db_user_id = $eidlogin_user->get_user_id( $eid );

		// eID was NOT found in EIDLOGIN_EID_USERS_TABLE.
		if ( false === $db_user_id ) {
			Eidlogin_Helper::write_log( 'No eID found in the database.' );
			// Case 1: Authentication started in the user's profile or wizard.
			// Check if user is logged in (don't rely on is_user_logged_in()!).
			if ( self::FLOW_REGISTER === $flow ) {
				try {
					$eidlogin_user->save_eid( $eid, $user_id_response );

					// In TR-03130 flow, the attributes are an object.
					if ( $this->check_for_tr03130() ) {
						$response_attributes = get_object_vars( $response_data['attributes'] );
					} else {
						$response_attributes = $response_data['attributes'];
					}

					$eidlogin_user->save_attributes( $response_attributes, $user_id_response );
					Eidlogin_Helper::write_log( 'Successfully saved eID data in the database.' );
				} catch ( Exception $e ) {
					Eidlogin_Helper::write_log( $e->getMessage() );
					$msg = rawurlencode( $e->getMessage() );
					$url = admin_url() . 'profile.php?eid_error=' . $msg . '#eid-header';
					wp_safe_redirect( $url );
					exit();
				}

				// Redirect to the profile.
				wp_safe_redirect( admin_url() . 'profile.php#eid-header' );
				exit();
			}

			// Case 2: User is not logged in. Authentication started on the
			// login page. Set the profile.php as redirect parameter.
			$url = wp_login_url( '/wp-admin/profile.php#eid-header' ) . '&eid_error=nocon';
			wp_safe_redirect( $url );
			exit();
		} else {
			// A matching eID was found in the database. If the user started
			// from the login page, set the authentication cookie and redirect
			// him to the dashboard.
			$db_user_id = intval( $db_user_id );
			Eidlogin_Helper::write_log( sprintf( 'eID found in the database for user with ID %d.', $db_user_id ) );

			if ( self::FLOW_LOGIN === $flow ) {
				Eidlogin_Helper::write_log( 'Set auth cookies and redirect the user to the dashboard.' );
				wp_set_current_user( $db_user_id );
				wp_set_auth_cookie( $db_user_id, false, true );
				wp_safe_redirect( admin_url() );
				exit();
			}

			// Register flow: check if the eID is already assigned to an other
			// user account.
			if ( $user_id_response !== $db_user_id ) {
				$msg = sprintf(
					"Current user with ID '%d' tried to assign eID '%s'
                    which is already assigned to user with ID '%d'.",
					$user_id_response,
					$eid,
					$db_user_id
				);
				Eidlogin_Helper::write_log( $msg );

				$msg = rawurlencode( __( 'The eID is already connected to another account.', 'eidlogin' ) );
				$url = admin_url() . 'profile.php?eid_error=' . $msg . '#eid-header';
				wp_safe_redirect( $url );
				exit();
			}

			// If the user has an eID, is logged in and manually starts the SAML
			// flow again via /wp-login.php?saml_register, just log and redirect.
			Eidlogin_Helper::write_log( 'The current user tries to re-assign his existing eID.', 'Warning: ' );
			wp_safe_redirect( admin_url() . 'profile.php#eid-header' );
			exit();
		}
	}

	/**
	 * Display the eID button on the login page.
	 *
	 * Callback for filter hook `login_form`.
	 */
	public function display_perso_button() {
		if ( $this->eidlogin_is_available() ) {
			// Output is already escaped by Twig.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->twig->render(
				'perso-button.html',
				array(
					'persoBtnTarget' => site_url( 'wp-login.php' ) . '?saml_login',
				)
			);
			// phpcs:enable
		}
	}

	/**
	 * Display an error message on the login page if a matching parameter is
	 * set.
	 *
	 * Callback for filter hook `login_message`.
	 *
	 * @return null|string
	 */
	public function saml_login_message() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['eid_error'] ) ) {
			switch ( $_REQUEST['eid_error'] ) {
				case 'settings':
					$msg = __( 'The SAML plugin is not configured properly. Please contact your administrator.', 'eidlogin' );
					break;
				case 'login':
					$msg = __( 'Login with eID failed! Please ensure the used eID-Card is valid.', 'eidlogin' );
					break;
				case 'nocon':
					$msg = __( 'eID-Login is not yet set up for your account.', 'eidlogin' );
					break;
				case 'canceled':
					$msg = __( 'Log in with eID aborted.', 'eidlogin' );
					break;
				default:
					$msg = __( 'An unknown error occurred.', 'eidlogin' );
					return '<p id="login_error">' . $msg . '</p>';
			}

			return '<p class="message">' . $msg . '</p>';
		}
		// phpcs:enable
	}

	/**
	 * Display the current status of the eID-Login on the user's profile
	 * page.
	 *
	 * Provide a link to connect and disconnect the user account with an eID
	 * service.
	 *
	 * Callback for action hook `show_user_profile`.
	 *
	 * @param WP_User $profileuser The current user.
	 */
	public function saml_profile( WP_User $profileuser ) {
		if ( $this->eidlogin_is_available() === false ) {
			$current_status = __( 'The eID-Login is not activated! Please contact the administrator!', 'eidlogin' );
			// Output is already escaped by Twig.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->twig->render(
				'profile-inactive.html',
				array(
					'status'         => __( 'Status', 'eidlogin' ),
					'current_status' => $current_status,
				)
			);
			// phpcs:enable
			return;
		}

		$eidlogin_user = new Eidlogin_User();

		// Check if parameter `eid` is set and handle the assignment or removal
		// of the eID accordingly.
		if ( array_key_exists( 'eid', $_GET ) ) {
			if ( 'remove' === $_GET['eid'] ) {
				if ( array_key_exists( 'nonce', $_GET ) ) {
					$nonce = filter_var( wp_unslash( $_GET['nonce'] ), FILTER_SANITIZE_STRING );

					if ( wp_verify_nonce( $nonce, self::NONCE_NAME ) === 1 ) {
						Eidlogin_Helper::write_log( sprintf( 'Nonce (%s) is valid.', $nonce ) );
						$eidlogin_user->remove_eid_data( $profileuser->ID );
					} else {
						$msg = sprintf( 'Nonce (%s) is invalid. Possible CSRF attack!', $nonce );
						Eidlogin_Helper::write_log( $msg );
					}
				} else {
					Eidlogin_Helper::write_log( 'Will not remove anything due to missing nonce. Possible CSRF attack!' );
				}
			}
		}

		$current_status   = '';
		$change_url       = admin_url() . 'profile.php';
		$btn_identifier   = '';
		$change_btn_label = '';

		// Check if the current user has a eID assigned to his account.
		if ( $eidlogin_user->is_connected( $profileuser->ID ) ) {
			$current_status   = __( 'Your account is currently connected to your eID. By default you can use Username and Password or eID to login. Activate the following option, to prevent the login by username and password and enhance the security of your account.', 'eidlogin' );
			$btn_identifier   = 'optionRemoveEID';
			$change_btn_label = __( 'Delete connection to eID', 'eidlogin' );
			$change_url      .= '?eid=remove&nonce=' . wp_create_nonce( self::NONCE_NAME ) . '#eid-header';
		} else {
			$current_status   = __( 'Your account is currently not connected to your eID. Create a connection to use your German eID ("Personalausweis") or another eID for the login to WordPress. More information can be found in the <a href="https://eid.services/eidlogin/wordpress/userdocs?lang=en" target="_blank">FAQ</a>.', 'eidlogin' );
			$btn_identifier   = 'optionAddEID';
			$change_btn_label = __( 'Create connection to eID', 'eidlogin' );
			$change_url       = get_home_url() . '/wp-login.php?saml_register';
		}

		// Display a message if there was an error during assignment.
		if ( isset( $_GET['eid_error'] ) && '' !== $_GET['eid_error'] ) {
			$current_status = filter_var( wp_unslash( $_GET['eid_error'] ), FILTER_SANITIZE_STRING );
		}

		// Output is already escaped by Twig.
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->twig->render(
			'profile.html',
			array(
				'status'                       => __( 'Status', 'eidlogin' ),
				'current_status'               => $current_status,
				'btn_identifier'               => $btn_identifier,
				'change_btn_label'             => $change_btn_label,
				'show_disable_password_option' => $eidlogin_user->is_connected( $profileuser->ID ),
				'checked'                      => ( 'true' === get_user_meta( $profileuser->ID, EIDLOGIN_DISABLE_PASSWORD, true ) ) ? 'checked' : '',
				'change_url'                   => $change_url,
				'labels'                       => Eidlogin_I18n::translations(),
			)
		);
		// phpcs:enable
	}

	/**
	 * Update the user settings and set `disable_password_login` to true/false.
	 *
	 * Callback for action hook `personal_options_update`.
	 *
	 * @param int $user_id The ID of the current user.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public function saml_profile_update( int $user_id ) {
		// Verify the nonce and the referrer.
		if ( check_admin_referer( 'update-user_' . $user_id ) !== 1 ) {
			return false;
		}

		// Check that the current user has the capability to edit the $user_id.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$disable_password_login = 'false';
		if ( isset( $_POST[ EIDLOGIN_DISABLE_PASSWORD ] ) && 'true' === $_POST[ EIDLOGIN_DISABLE_PASSWORD ] ) {
			$disable_password_login = 'true';
		}

		// Create / update the user meta for the $user_id.
		return update_user_meta( $user_id, EIDLOGIN_DISABLE_PASSWORD, $disable_password_login );
	}

	/**
	 * Called if the user itself or the administrator updates a user profile.
	 *
	 * If this incorporates a change of the password, the password login option
	 * must be (re-)enabled. This hook is also triggered if the user or an admin
	 * issues the "reset password email".
	 *
	 * Callback for action hook `profile_update`.
	 *
	 * @param int $user_id The ID of the current user.
	 */
	public function eidlogin_profile_update( int $user_id ) : void {
		// Return if user tries to reset his password (/wp-login.php?action=lostpassword).
		// The usage of is_user_logged_in() is safe here, because there is no
		// cross-domain cookie usage involved.
		if ( false === is_user_logged_in() ) {
			return;
		}

		// Return if an admin triggers the reset link for another user (/users.php?action=resetpassword).
		if ( array_key_exists( 'action', $_GET ) && 'resetpassword' === $_GET['action'] ) {
			return;
		}

		// Return if the password didn't change.
		if ( ! isset( $_POST['pass1'] ) || '' === $_POST['pass1'] ) {
			return;
		}

		// If the password changed, verify the nonce and the referrer.
		if ( check_admin_referer( 'update-user_' . $user_id ) !== 1 ) {
			Eidlogin_Helper::write_log( sprintf( 'Cannot update profile, verification failed for user_id "%d".', $user_id ) );
			return;
		}

		// Check that the current user has the capability to edit the $user_id.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			Eidlogin_Helper::write_log( sprintf( 'User with id "%d" is not allowed to edit the current profile.', $user_id ) );
			return;
		}

		$msg = sprintf(
			"Password changed by user or admin for user with ID '%d'.
            (Re-)enable password login.",
			$user_id
		);
		Eidlogin_Helper::write_log( $msg );

		update_user_meta( $user_id, EIDLOGIN_DISABLE_PASSWORD, 'false' );
	}

	/**
	 * Called after the user has changed his password with the
	 * help of the email reset option.
	 *
	 * In this case, the password login option must be (re-)enabled.
	 *
	 * Callback for action hook `after_password_reset`.
	 *
	 * @param WP_User $user The current user.
	 * @param string  $new_pass New password for the user in plaintext.
	 */
	public function eidlogin_password_reset( WP_User $user, string $new_pass ) : void {
		$msg = sprintf(
			"User with ID '%d' reset his password. (Re-)enable password login.",
			$user->ID
		);
		Eidlogin_Helper::write_log( $msg );

		update_user_meta( $user->ID, EIDLOGIN_DISABLE_PASSWORD, 'false' );
	}

	/**
	 * After a new user is registered, assign a meta value that indicates that
	 * the user hasn't logged in yet.
	 *
	 * This is used to display a custom message on the first login only.
	 * Callback for action hook `user_register`.
	 *
	 * @param int $user_id The ID of the current user.
	 */
	public function user_register_defaults( int $user_id ) : void {
		add_user_meta( $user_id, EIDLOGIN_FIRST_TIME_USER, 'true', true );
		add_user_meta( $user_id, EIDLOGIN_DISABLE_PASSWORD, 'false', true );
	}

	/**
	 * Display a message on top of the options page after the first login and
	 * reset the user's meta data afterwards.
	 *
	 * Callback for action hook `personal_options`.
	 */
	public function eidlogin_first_time_user_hint() : void {
		$user_id     = get_current_user_id();
		$is_new_user = get_user_meta( $user_id, EIDLOGIN_FIRST_TIME_USER, true );

		if ( 'true' === $is_new_user && $this->eidlogin_is_available() ) {
			// Output is already escaped by Twig.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->twig->render(
				'notification.html',
				array(
					'notification' => __( 'You can use your eID (for example your German identity card) to login to WordPress. Connect your eID to your account in the settings now.', 'eidlogin' ),
					'link_text'    => __( 'Go to Settings', 'eidlogin' ),
				)
			);
			// phpcs:enable

			// Reset the value of the user.
			update_user_meta( $user_id, EIDLOGIN_FIRST_TIME_USER, 'false' );
		}
	}

	/**
	 * Check if the user is allowed to use username/password for authentication.
	 *
	 * Callback for filter hook `wp_set_auth_cookie`.
	 *
	 * @param WP_User $user The current user.
	 * @return WP_User|WP_Error
	 */
	public function allow_password_login( WP_User $user ) {
		$password_disabled = get_user_meta( $user->ID, EIDLOGIN_DISABLE_PASSWORD, true );

		$eidlogin_user = new Eidlogin_User();

		// Double-check that the user really has an eID connected.
		if ( 'true' === $password_disabled && $eidlogin_user->is_connected( $user->ID ) ) {
			$message = __( 'Login with username and password is disabled. Please use the eID-Login.', 'eidlogin' );
			return new WP_Error( 'login_error', $message );
		}

		// User is allowed to use password.
		return $user;
	}

	/**
	 * Get SAML Auth instance.
	 *
	 * In case of an exception, the specific error is logged and the user is
	 * redirected to the login page showing an appropriate error message.
	 *
	 * @return Ecsec\Eidlogin\Dep\OneLogin\Saml2\Auth
	 * @throws Exception If SAML settings were incorrect.
	 */
	private function get_saml_auth() {
		try {
			$auth = new Auth( $this->saml_settings() );
		} catch ( \Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'SAML: ' );
			$url = wp_login_url() . '?eid_error=settings';
			wp_safe_redirect( $url );
			exit();
		}

		return $auth;
	}

	/**
	 * Get the SAML settings with values from the options.
	 *
	 * @return null|array
	 */
	private function saml_settings() {
		$eidlogin_options = get_option( 'eidlogin_options' );
		if ( empty( $eidlogin_options ) ) {
			return;
		}

		$want_assertions_encrypted = false;
		if ( 'true' === $eidlogin_options['sp_enforce_enc'] ) {
			$want_assertions_encrypted = true;
		}

		// Determine if we should skip XML validation (according to wp-config.php).
		$skip_xml_validation = false;
		if ( defined( 'EIDLOGIN_SKIP_XML_VALIDATION' ) ) {
			$skip_xml_validation = EIDLOGIN_SKIP_XML_VALIDATION;
		}

		$settings = array(
			'strict'      => true,
			'debug'       => false,
			'security'    => array(
				'wantNameId'              => true,
				'wantAssertionsSigned'    => true,
				'wantAssertionsEncrypted' => $want_assertions_encrypted,
				'wantXMLValidation'       => ! $skip_xml_validation,
				'authnRequestsSigned'     => true,
				'signMetadata'            => true,
				'requestedAuthnContext'   => false,
				'signatureAlgorithm'      => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
				'digestAlgorithm'         => 'http://www.w3.org/2001/04/xmlenc#sha256',
				'encryption_algorithm'    => 'http://www.w3.org/2009/xmlenc11#aes256-gcm',
			),
			'sp'          => array(
				'entityId'                 => $eidlogin_options['sp_entity_id'],
				'assertionConsumerService' => array(
					'url'     => EIDLOGIN_ACS_URL,
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
				),
				'x509cert'                 => Utils::formatCert( $eidlogin_options['sp_cert_act'] ),
				'x509certNew'              => Utils::formatCert( $eidlogin_options['sp_cert_new'] ?? '' ),
				'privateKey'               => Utils::formatPrivateKey( $eidlogin_options['sp_key_act'] ),
				'x509certEnc'              => Utils::formatCert( $eidlogin_options['sp_cert_act_enc'] ),
				'x509certNewEnc'           => Utils::formatCert( $eidlogin_options['sp_cert_new_enc'] ?? '' ),
				'privateKeyEnc'            => Utils::formatPrivateKey( $eidlogin_options['sp_key_act_enc'] ),
			),
			'idp'         => array(
				'entityId'            => $eidlogin_options['idp_entity_id'],
				'singleSignOnService' => array(
					'url'     => $eidlogin_options['idp_sso_url'],
					'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
				),
				'x509certMulti'       => array(
					'signing'    => array(
						0 => Utils::formatCert( $eidlogin_options['idp_cert_sign'] ),
					),
					'encryption' => array(
						0 => Utils::formatCert( $eidlogin_options['idp_cert_enc'] ),
					),
				),
			),
			'alg'         => array(
				'signing'    => array(
					// TODO: Remove rsa-sha256 in 2022.
					'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
					'http://www.w3.org/2007/05/xmldsig-more#sha224-rsa-MGF1',
					'http://www.w3.org/2007/05/xmldsig-more#sha256-rsa-MGF1',
					'http://www.w3.org/2007/05/xmldsig-more#sha384-rsa-MGF1',
					'http://www.w3.org/2007/05/xmldsig-more#sha512-rsa-MGF1',
				),
				'encryption' => array(
					'key'  => array(
						'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p',
					),
					'data' => array(
						'http://www.w3.org/2009/xmlenc11#aes128-gcm',
						'http://www.w3.org/2009/xmlenc11#aes192-gcm',
						'http://www.w3.org/2009/xmlenc11#aes256-gcm',
					),
				),
			),
			'authnReqExt' => array(),
		);

		// Adjust settings for TR-03130 usage.
		$idp_ext_tr03130 = $eidlogin_options['idp_ext_tr03130'];

		if ( ! empty( $idp_ext_tr03130 ) ) {
			// Add AuthnRequestExtension. Convert escaped entities back, e.g. &lt; to <.
			$settings['authnReqExt']['tr03130'] = htmlspecialchars_decode( $idp_ext_tr03130 );
			// No signed assertion.
			$settings['security']['wantAssertionsSigned'] = false;
			// Signature of message is checked outside of php-saml.
			$settings['security']['wantMessagesSigned'] = false;
		}

		return $settings;
	}

	/**
	 * Check if the eID-Login is ready to use.
	 *
	 * This requires complete and valid SAML settings, the usage of HTTPS
	 * everywhere and (on demand) the explicit activation by an administrator.
	 *
	 * @param bool $enforce_activation Indicates whether the activation is mandatory.
	 * @return bool
	 */
	private function eidlogin_is_available( $enforce_activation = true ) : bool {
		$saml_settings = $this->saml_settings();
		if ( empty( $saml_settings ) ) {
			// This can be the case if the plugin is not configured yet and a
			// user enters his profile. Would lead to 'Settings file not found'.
			return false;
		}

		try {
			$auth = new Auth( $saml_settings );
		} catch ( \Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'Settings: ' );
			return false;
		}

		// Check if SSO URL from the IDP and the current WP instance use HTTPS.
		$idp_settings = $saml_settings['idp'];
		if ( Eidlogin_Helper::url_is_https( $idp_settings['singleSignOnService']['url'] ) === false ) {
			return false;
		}
		if ( Eidlogin_Helper::wp_is_https() === false ) {
			return false;
		}

		if ( false === $enforce_activation ) {
			return true;
		}

		// Check if the administrator has activated the plugin.
		$eidlogin_options = get_option( 'eidlogin_options' );
		if ( isset( $eidlogin_options['activated'] ) === false || 'true' !== $eidlogin_options['activated'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the user canceled the authentication process.
	 *
	 * For this, the status element of the SAML response has to be evaluated as
	 * the returned error code is just `invalid_response`. The message depends
	 * on the type:
	 *
	 * * Plain SAML: `urn:iso:std:iso-iec:24727:tech:resultminor:al:cancellationByUser`
	 * * TR-03130: `An error was reported from eCardAPI: http://www.bsi.bund.de/ecard/api/1.1/resultminor/sal#cancellationByUser.`
	 *
	 * @param object|array $status The status of the SAML response.
	 *
	 * @return bool true if the user canceled the authentication.
	 */
	private function user_canceled_login( $status ) : bool {
		if ( $this->check_for_tr03130() ) {
			$msg = $status->msg;
		} else {
			$msg = $status['msg'];
		}

		preg_match( '/.*cancel.*/', $msg, $res );
		if ( count( $res ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Test if we have TR-03130 configured.
	 */
	private function check_for_tr03130() : bool {
		$settings = $this->saml_settings();
		if ( array_key_exists( 'tr03130', $settings['authnReqExt'] ) ) {
			return true;
		}

		return false;
	}

}
