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
 * Class that provides custom REST endpoints.
 */
class Eidlogin_Rest {

	const REST_API_VERSION = 'v1';

	/**
	 * The plugin name.
	 *
	 * @var string $plugin_name The plugin name.
	 */
	private $plugin_name;

	/**
	 * The plugin name.
	 *
	 * @var array $form_validator The information about how to validate the data.
	 */
	private $form_validator;

	/**
	 * The plugin name.
	 *
	 * @var array $form_sanitizer The information about how to sanitize the data.
	 */
	private $form_sanitizer;

	/**
	 * Constructor of the REST API class.
	 *
	 * @param string $plugin_name The name of the plugin.
	 */
	public function __construct( string $plugin_name ) {
		$this->plugin_name = $plugin_name;

		$this->form_sanitizer = array(
			'activated'       => 'Eidlogin_Helper::convert_checkbox_value',
			'sp_entity_id'    => 'sanitize_text_field',
			'sp_enforce_enc'  => 'Eidlogin_Helper::convert_checkbox_value',
			'idp_entity_id'   => 'sanitize_text_field',
			'idp_sso_url'     => 'sanitize_text_field',
			'idp_cert_sign'   => 'esc_textarea',
			'idp_cert_enc'    => 'esc_textarea',
			'idp_ext_tr03130' => 'esc_textarea',
			'eid_delete'      => 'Eidlogin_Helper::convert_checkbox_value',
		);
	}

	/**
	 * Register the routes for the REST API.
	 *
	 * Callback for action hook `rest_api_init`.
	 */
	public function eidlogin_rest_api() : void {
		// Array must be assigned here (in contrast to the constructor).
		// Otherwise the translations are not present.
		$this->form_validator = $this->get_form_validators();

		register_rest_route(
			$this->plugin_name . '/' . self::REST_API_VERSION,
			'/eidlogin-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_options' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'administrator' );
				},
				'args'                => array(
					'action' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return 'save' === $param || 'reset' === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'data'   => array(
						'validate_callback' => array( $this, 'validate_form_data' ),
						'sanitize_callback' => array( $this, 'sanitize_form_data' ),
					),
				),
			)
		);

		register_rest_route(
			$this->plugin_name . '/' . self::REST_API_VERSION,
			'/eidlogin-idp-metadata/(?P<url>[A-Za-z0-9+/]+={0,2}$)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'idp_metadata' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'administrator' );
				},
				'args'                => array(
					'url' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->plugin_name . '/' . self::REST_API_VERSION,
			'/eidlogin-activate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'administrator' );
				},
			)
		);

		register_rest_route(
			$this->plugin_name . '/' . self::REST_API_VERSION,
			'/eidlogin-preparerollover',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'preparerollover' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'administrator' );
				},
			)
		);

		register_rest_route(
			$this->plugin_name . '/' . self::REST_API_VERSION,
			'/eidlogin-executerollover',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'executerollover' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'administrator' );
				},
			)
		);
	}

	/**
	 * Provide an array of form validators.
	 *
	 * @return array
	 */
	private function get_form_validators() : array {
		return array(
			'activated'       => array(
				'func'              => 'is_bool',
				'default_error_msg' => __( 'The activation checkbox is missing.', 'eidlogin' ),
			),
			'sp_entity_id'    => array(
				'func'              => 'Eidlogin_Helper::non_empty_string',
				'default_error_msg' => __( 'The EntityID of the Service Provider is missing.', 'eidlogin' ),
			),
			'sp_enforce_enc'  => array(
				'func'              => 'is_bool',
				'default_error_msg' => __( 'The enforce encryption parameter is missing.', 'eidlogin' ),
			),
			'idp_entity_id'   => array(
				'func'              => 'Eidlogin_Helper::non_empty_string',
				'default_error_msg' => __( 'The EntityID of the Identity Provider is missing.', 'eidlogin' ),
			),
			'idp_sso_url'     => array(
				'func'              => 'Eidlogin_Helper::url_is_https',
				'default_error_msg' => __( 'Invalid Single Sign-On URL of the Identity Provider.', 'eidlogin' ),
			),
			'idp_cert_sign'   => array(
				'func'              => 'Eidlogin_Helper::validate_sign_certificate',
				'default_error_msg' => __( 'The Signature Certificate of the Identity Provider is missing.', 'eidlogin' ),
			),
			'idp_cert_enc'    => array(
				'func'              => 'Eidlogin_Helper::validate_enc_certificate',
				'default_error_msg' => __( 'The Encryption Certificate of the Identity Provider is missing.', 'eidlogin' ),
			),
			'idp_ext_tr03130' => array(
				'func'              => 'Eidlogin_Helper::empty_or_valid_xml',
				'default_error_msg' => __( 'The AuthnRequestExtension XML element is no valid XML.', 'eidlogin' ),
			),
			'eid_delete'      => array(
				'func'              => 'is_bool',
				'default_error_msg' => __( 'The value of the confirmation dialog is missing.', 'eidlogin' ),
			),
		);
	}

	/**
	 * Validate the values passed to the REST API.
	 *
	 * @param array $values The parameters with the data to validate.
	 */
	public function validate_form_data( $values ) {
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, $this->form_validator ) ) {
				$validator = $this->form_validator[ $key ];
				// Call the specific validation function of the current input.
				$retval = call_user_func_array( $validator['func'], array( $value ) );
				// In case of an error, the function has to return false or a specific
				// error message which is used in WP_Error in this case.
				if ( is_string( $retval ) ) {
					return new WP_Error( 'validation_error', $retval );
				} elseif ( false === $retval ) {
					return new WP_Error( 'validation_error', $validator['default_error_msg'] );
				}
			} else {
				Eidlogin_Helper::write_log( 'Key ' . $key . ' not found in validation array.' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize the values passed to the REST API.
	 *
	 * @param array $values The values to validate.
	 */
	public function sanitize_form_data( $values ) {
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, $this->form_sanitizer ) ) {
				$sanitizer = $this->form_sanitizer[ $key ];
				// Replace the value inside the array with the sanitized value.
				$values[ $key ] = call_user_func_array( $sanitizer, array( $value ) );
			} else {
				Eidlogin_Helper::write_log( 'Key ' . $key . ' not found in sanitize array.' );
				return false;
			}
		}

		return $values;
	}

	/**
	 * Update or reset the options for the user.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function update_options( WP_REST_Request $request ) {
		$json = $request->get_json_params();

		$action = $json['action'];

		if ( 'save' === $action ) {
			// First check if the option already exists.
			$eidlogin_options = get_option( EIDLOGIN_OPTION_NAME );

			$save_data = $json['data'];

			// Only set default values on first insert.
			if ( ! $eidlogin_options ) {
				$this->set_user_defaults();

				$certs = new Eidlogin_Certificates();

				try {
					// Create a new key and cert for signing.
					$cert_data      = $certs->create_key_and_x509_cert();
					$cert_data_sign = array(
						'sp_key_act'  => $cert_data['key'],
						'sp_cert_act' => $cert_data['cert'],
					);

					// Create a new key and cert for encryption.
					$cert_data     = $certs->create_key_and_x509_cert();
					$cert_data_enc = array(
						'sp_key_act_enc'  => $cert_data['key'],
						'sp_cert_act_enc' => $cert_data['cert'],
					);
				} catch ( Exception $e ) {
					Eidlogin_Helper::write_log( $e->getMessage(), 'Cannot create certificates.' );
					$resp_data = array(
						'status' => 'error',
					);
					$response  = rest_ensure_response( $resp_data );
					$response->set_status( 500 );
					return $response;
				}
			} else {
				// Assign the values for keys and certs from the database.
				$cert_data_sign = array(
					'sp_key_act'  => $eidlogin_options['sp_key_act'],
					'sp_cert_act' => $eidlogin_options['sp_cert_act'],
				);
				$cert_data_enc  = array(
					'sp_key_act_enc'  => $eidlogin_options['sp_key_act_enc'],
					'sp_cert_act_enc' => $eidlogin_options['sp_cert_act_enc'],
				);

				if ( 'true' === $save_data['eid_delete'] ) {
					Eidlogin_Helper::write_log( 'Remove all eID connections of all users after confirmation. ' );
					require_once plugin_dir_path( __DIR__ ) . 'includes/class-eidlogin-cleanup.php';
					Eidlogin_Cleanup::remove_plugin_data( false, false );
				}
			}

			$save_data = array_merge( $save_data, $cert_data_sign, $cert_data_enc );

			// update_option returns false on error and if nothing was changed.
			update_option( EIDLOGIN_OPTION_NAME, $save_data );

			// Only schedule the cronjobs after the first successful insert.
			if ( ! $eidlogin_options ) {
				$cron = new Eidlogin_Cron();
				$cron->schedule_cert();
				$cron->schedule_cleanup();
			}

			$resp_data['status']  = 'success';
			$resp_data['message'] = __( 'Settings have been saved.', 'eidlogin' );
		} elseif ( 'reset' === $action ) {
			// Remove options, all eID connections and the metadata of all users.
			require_once plugin_dir_path( __DIR__ ) . 'includes/class-eidlogin-cleanup.php';
			Eidlogin_Cleanup::remove_plugin_data( false, true );
			Eidlogin_Cleanup::deactivate_cronjobs();

			$resp_data['status']  = 'success';
			$resp_data['message'] = __( 'Settings have been reset.', 'eidlogin' );
		}

		$response = rest_ensure_response( $resp_data );
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * Set the default options for all existing users.
	 */
	private function set_user_defaults() : void {
		$users = get_users( array( 'fields' => array( 'ID' ) ) );

		foreach ( $users as $user ) {
			update_user_meta( $user->ID, EIDLOGIN_FIRST_TIME_USER, 'true', true );
			update_user_meta( $user->ID, EIDLOGIN_DISABLE_PASSWORD, 'false', true );
		}
	}

	/**
	 * Fetch the IdP metadata from a given URL.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function idp_metadata( WP_REST_Request $request ) {
		$url_encoded = $request->get_param( 'url' );
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$url = urldecode( base64_decode( $url_encoded ) );
		// phpcs:enable

		try {
			$idp_metadata = Eidlogin_Helper::get_idp_saml_metadata( $url );

			$resp_code                  = 200;
			$resp_data['idp_cert_enc']  = $idp_metadata['idp_cert_enc'];
			$resp_data['idp_cert_sign'] = $idp_metadata['idp_cert_sign'];
			$resp_data['idp_entity_id'] = $idp_metadata['idp_entity_id'];
			$resp_data['idp_sso_url']   = $idp_metadata['idp_sso_url'];
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'Cannot fetch IdP metadata.' );
			$resp_code = 404;
			$resp_data = wp_json_encode( new StdClass() );
		}

		$response = rest_ensure_response( $resp_data );
		$response->set_status( $resp_code );
		return $response;
	}


	/**
	 * Activate the state of the app.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function activate( WP_REST_Request $request ) {
		$eidlogin_options              = get_option( EIDLOGIN_OPTION_NAME );
		$eidlogin_options['activated'] = 'true';
		update_option( EIDLOGIN_OPTION_NAME, $eidlogin_options );

		$resp_code           = 200;
		$resp_data['status'] = 'success';

		$response = rest_ensure_response( $resp_data );
		$response->set_status( $resp_code );
		return $response;
	}


	/**
	 * Prepare the SAML certificate rollover by creating two new keys and certificates
	 * and by saving them as sp_{key,cert}_new option.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function preparerollover( WP_REST_Request $request ) {
		try {
			$certs = new Eidlogin_Certificates();
			$certs->do_prepare();

			$resp_data['cert_new']     = $certs->get_cert( 'sp_cert_new', true );
			$resp_data['cert_new_enc'] = $certs->get_cert( 'sp_cert_new_enc', true );

			$resp_code            = 200;
			$resp_data['status']  = 'success';
			$resp_data['message'] = __( 'Certificate Rollover has been prepared.', 'eidlogin' );
		} catch ( Exception $e ) {
			Eidlogin_Helper::write_log( $e->getMessage(), 'Cannot prepare rollover.' );
			$resp_code = 500;
			$resp_data = array(
				'status' => 'error',
			);
		}

		$response = rest_ensure_response( $resp_data );
		$response->set_status( $resp_code );
		return $response;
	}

	/**
	 * Prepare the SAML certificate rollover by creating two new keys and certificates
	 * and by saving them as sp_{key,cert}_new option.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function executerollover( WP_REST_Request $request ) {
		$certs = new Eidlogin_Certificates();
		$certs->do_rollover();

		$resp_data['cert_act']     = $certs->get_cert( 'sp_cert_act', true );
		$resp_data['cert_act_enc'] = $certs->get_cert( 'sp_cert_act_enc', true );

		$resp_data['status']  = 'success';
		$resp_data['message'] = __( 'Certificate Rollover has been executed.', 'eidlogin' );

		$response = rest_ensure_response( $resp_data );
		$response->set_status( 200 );
		return $response;
	}
}
