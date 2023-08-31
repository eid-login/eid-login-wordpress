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
 * Class that manages the settings of the eID-Login plugin in the WP admin area.
 */
class Eidlogin_Admin {

	const SETTINGS_PAGE          = 'eidlogin-settings';
	const SKIDENTIY_METADATA_URL = 'https://service.skidentity.de/fs/saml/metadata';
	const TR03130_PLACEHOLDER    = '<?xml version="1.0" encoding="UTF-8"?>
<eid:AuthnRequestExtension Version="2" xmlns:eid="http://bsi.bund.de/eID/" xmlns:saml2="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
	<eid:RequestedAttributes>
		<saml2:Attribute Name="DocumentType" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="IssuingState" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="DateOfExpiry" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="GivenNames" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="FamilyNames" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="ArtisticName" eid:RequiredAttribute="false" />
		<saml2:Attribute Name="AcademicTitle" eid:RequiredAttribute="false" />
		<saml2:Attribute Name="DateOfBirth" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="PlaceOfBirth" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="Nationality" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="BirthName" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="PlaceOfResidence" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="ResidencePermitI" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="RestrictedID" eid:RequiredAttribute="true" />
		<saml2:Attribute Name="AgeVerification" eid:RequiredAttribute="true">
			<saml2:AttributeValue xsi:type="eid:AgeVerificationRequestType">
				<eid:Age>18</eid:Age>
			</saml2:AttributeValue>
		</saml2:Attribute>
		<saml2:Attribute Name="PlaceVerification" eid:RequiredAttribute="true">
			<saml2:AttributeValue xsi:type="eid:PlaceVerificationRequestType">
				<eid:CommunityID>027605</eid:CommunityID>
			</saml2:AttributeValue>
		</saml2:Attribute>
		<saml2:Attribute Name="TransactionAttestation">
			<saml2:AttributeValue xsi:type="eid:TransactionAttestationRequestType">
				<eid:TransactionAttestationFormat>http://bsi.bund.de/eID/ExampleAttestationFormat</eid:TransactionAttestationFormat>
			</saml2:AttributeValue>
		</saml2:Attribute>
		<saml2:Attribute Name="LevelOfAssurance">
			<saml2:AttributeValue xsi:type="eid:LevelOfAssuranceType">http://bsi.bund.de/eID/LoA/hoch</saml2:AttributeValue>
		</saml2:Attribute>
	</eid:RequestedAttributes>
</eid:AuthnRequestExtension>';

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
	 * The options for the plugin.
	 *
	 * @var mixed $eidlogin_options The options for the plugin.
	 */
	private $eidlogin_options;

	/**
	 * Constructor of the Eidlogin_Admin class.
	 *
	 * @param string            $plugin_name The name of the plugin.
	 * @param string            $version The version of the plugin.
	 * @param \Twig\Environment $twig The Twig template.
	 */
	public function __construct( string $plugin_name, string $version, \Twig\Environment $twig ) {
		$this->plugin_name      = $plugin_name;
		$this->version          = $version;
		$this->twig             = $twig;
		$this->eidlogin_options = get_option( EIDLOGIN_OPTION_NAME );
	}

	/**
	 * Check if there is a newer version of this plugin available and call the
	 * update function if necessary.
	 *
	 * Callback for action hook `plugins_loaded`.
	 */
	public function eidlogin_check_version() : void {
		$db_version = get_option( EIDLOGIN_VERSION_NAME, '0.0.0' );

		if ( EIDLOGIN_PLUGIN_VERSION !== $db_version ) {
			require_once plugin_dir_path( __DIR__ ) . 'includes/class-eidlogin-activator.php';
			Eidlogin_Activator::update( $db_version );
		}
	}

	/**
	 * Ensure that the language of the user is also used within the REST API.
	 *
	 * If the user has a different language than the website language, the
	 * translation is applied correctly through the plugin. But the strings
	 * within the REST API always use the website language, despite the current
	 * user language, see
	 * <https://wordpress.org/support/topic/wordpress-rest-api-returning-incorrect-locale/#post-12453159>.
	 *
	 * Callback for action hook `plugins_loaded`.
	 *
	 * @param string $locale Current website locale.
	 */
	public function eidlogin_force_locale( $locale ) {
		// Don't use get_user_locale() as it results in an infinite loop!
		$current_user_locale = get_user_meta( get_current_user_id(), 'locale', true );
		if ( ! empty( $current_user_locale ) && $locale !== $current_user_locale ) {
			return $current_user_locale;
		}
		// Return the default website language.
		return $locale;
	}

	/**
	 * Enqueue scripts, but only on our custom settings page.
	 *
	 * Callback for action hook `admin_enqueue_scripts`.
	 */
	public function enqueue_scripts() : void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && self::SETTINGS_PAGE === $_GET['page'] ) {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/eidlogin-admin.js', array( 'wp-i18n' ), $this->version, false );
			wp_enqueue_style( 'eidlogin-simplegrid', plugin_dir_url( __FILE__ ) . 'css/eidlogin-simplegrid.css', array(), $this->version );
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/eidlogin-admin.css', array(), $this->version );
			wp_set_script_translations( $this->plugin_name, $this->plugin_name, plugin_dir_path( __DIR__ ) . 'languages' );
			// Provide a nonce for the usage of the WP API within class
			// `Eidlogin_Rest` and the Cypress tests.
			wp_localize_script(
				$this->plugin_name,
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * Extend the list of allowed hosts for redirects.
	 *
	 * This is needed for the usage of
	 * [wp_safe_redirect](https://developer.wordpress.org/reference/functions/wp_safe_redirect/)
	 * instead of wp_redirect. Therefore, the host from the configured
	 * idp_sso_url is extracted.
	 *
	 * Callback for filter hook `allowed_redirect_hosts`.
	 *
	 * @param array $hosts An array of allowed host names.
	 * @return array The extended array of allowed host names.
	 */
	public function eidlogin_allowed_domains( $hosts ) : array {
		// This only works if the plugin is already configured.
		if ( $this->eidlogin_options && array_key_exists( 'idp_sso_url', $this->eidlogin_options ) ) {
			$idp_sso_url = $this->eidlogin_options['idp_sso_url'];
			$url         = wp_parse_url( $idp_sso_url );
			$hosts[]     = $url['host'];
			// Also add localhost for the eID-Client redirect.
			$hosts[] = '127.0.0.1';
		}

		return $hosts;
	}

	/**
	 * Add settings link to the plugin page.
	 *
	 * Callback for filter hook `plugin_action_links_`.
	 *
	 * @param array $links An array of plugin action links.
	 * @return array The extended array of plugin action links.
	 */
	public function eidlogin_settings_link( array $links ) : array {
		$settings_label = __( 'Settings', 'eidlogin' );
		$url            = admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE );
		array_push( $links, '<a href="' . $url . '">' . $settings_label . '</a>' );
		return $links;
	}

	/**
	 * Add settings link to the menu on the left.
	 *
	 * Callback for action hook `admin_menu`.
	 */
	public function eidlogin_menu_item() : void {
		$page_title = __( 'Settings › eID-Login', 'eidlogin' );
		$menu_title = 'eID-Login';
		$capability = 'manage_options';
		$menu_slug  = self::SETTINGS_PAGE;

		add_options_page(
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			array( $this, 'eidlogin_settings_page' )
		);
	}

	/**
	 * Manage settings by displaying a dedicated page.
	 */
	public function eidlogin_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		// Initialize default values.
		$act_cert     = '';
		$act_cert_enc = '';
		$new_cert     = '';
		$new_cert_enc = '';
		$valid_days   = 0;

		$certs = new Eidlogin_Certificates();

		$act_cert_present = $certs->check_act_cert_present();

		if ( $act_cert_present ) {
			$act_cert     = $certs->get_cert( 'sp_cert_act', true );
			$act_cert_enc = $certs->get_cert( 'sp_cert_act_enc', true );

			try {
				$act_dates                = $certs->get_act_dates();
				$now                      = new \DateTimeImmutable();
				$remaining_valid_interval = $act_dates[ Eidlogin_Certificates::DATES_VALID_TO ]->diff( $now );
				$valid_days               = $remaining_valid_interval->days;
			} catch ( Exception $e ) {
				Eidlogin_Helper::write_log( $e->getMessage(), 'Cannot get certificate data.' );
			}
		}

		$new_cert_present = $certs->check_new_cert_present();

		if ( $new_cert_present ) {
			$new_cert     = $certs->get_cert( 'sp_cert_new', true );
			$new_cert_enc = $certs->get_cert( 'sp_cert_new_enc', true );
		}

		$settings_present = $this->settings_present();

		$activated_checked = '';
		if ( isset( $this->eidlogin_options['activated'] ) && 'true' === $this->eidlogin_options['activated'] ) {
			$activated_checked = 'checked';
		}

		$enforce_enc_checked = '';
		if ( $settings_present && 'true' === $this->eidlogin_options['sp_enforce_enc'] ) {
			$enforce_enc_checked = 'checked';
		}

		// Output is already escaped by Twig.
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->twig->render(
			'settings.html',
			array(
				'settings_present'    => $settings_present,
				'act_cert_present'    => $act_cert_present,
				'new_cert_present'    => $new_cert_present,
				'wp_is_https'         => Eidlogin_Helper::wp_is_https(),
				'images_dir'          => plugin_dir_url( __FILE__ ) . 'images/',
				'activated_checked'   => $activated_checked,
				'sp_entity_id'        => home_url(),
				'idp_metadata_url'    => self::SKIDENTIY_METADATA_URL,
				'eidlogin_options'    => $this->eidlogin_options,
				'tr03130_placeholder' => self::TR03130_PLACEHOLDER,
				'acs_url'             => EIDLOGIN_ACS_URL,
				'metadata_url'        => EIDLOGIN_METADATA_URL,
				'enforce_enc_checked' => $enforce_enc_checked,
				'skidentity_url'      => $this->skidentity_url(),

				'valid_days'          => $valid_days,
				'act_cert'            => $act_cert,
				'act_cert_enc'        => $act_cert_enc,
				'new_cert'            => $new_cert,
				'new_cert_enc'        => $new_cert_enc,

				'labels'              => Eidlogin_I18n::translations(),
			)
		);
        // phpcs:enable
	}

	/**
	 * Hook that fires immediately before a user is deleted from the database.
	 * Use it to remove eID related data as well.
	 *
	 * Callback for action hook `delete_user`.
	 *
	 * @param int $id The ID of the user to delete.
	 */
	public function eidlogin_delete_user( int $id ) : void {
		$msg = sprintf(
			"User with ID '%d' is getting removed, also delete corresponding eID data.",
			$id
		);
		Eidlogin_Helper::write_log( $msg );

		$eidlogin_user = new Eidlogin_User();
		$eidlogin_user->remove_eid_data( $id );
	}

	/**
	 * Check whether the user is already registered.
	 *
	 * @return bool true if the SAML settings are present.
	 */
	private function settings_present() : bool {
		if ( false === $this->eidlogin_options || empty( $this->eidlogin_options ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Return the SkIDentity domain according to the language of the user.
	 *
	 * @return string
	 */
	private function skidentity_url() : string {
		$skidentity_url = 'https://skidentity.com';
		if ( strpos( strtolower( get_user_locale( get_current_user_id() ) ), 'de_de' ) !== false ) {
			$skidentity_url = 'https://skidentity.de';
		}
		return $skidentity_url;
	}
}
