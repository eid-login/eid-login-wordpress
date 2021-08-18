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
 * Class used for internationalization.
 */
class Eidlogin_I18n {

	/**
	 * Load the plugin text domain for the translations. Usage:
	 * __('translate_me', 'eidlogin')
	 * The text domain must not be passed as a variable!
	 * A (new) pot file can be created with `wp i18n make-pot . languages/eidlogin.pot`.
	 * The *.po files can be edited with Poedit.
	 * https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'eidlogin',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Return an array of all translations needed for the Twig templates.
	 *
	 * The usage of Twig filters/functions would work, but Twig/HTML templates
	 * are not recognized by Poedit, which means that translated strings in the
	 * templates are not imported to Poedit while scanning the source code.
	 * There are tools, but none worked (really!).
	 */
	public static function translations() {
		return array(
			/* Translations for settings.html */
			'overview'                  => __( 'Overview', 'eidlogin' ),
			'select_idp'                => __( 'Select IdP', 'eidlogin' ),
			'configure_idp'             => __( 'Configure IdP', 'eidlogin' ),
			'connect_eid'               => __( 'Connect eID', 'eidlogin' ),
			'help'                      => __( 'Help', 'eidlogin' ),
			'close'                     => __( 'Close', 'eidlogin' ),
			'back'                      => __( 'Back', 'eidlogin' ),
			'next'                      => __( 'Next', 'eidlogin' ),

			/* Help panel */
			'help_topic1'               => __( 'What is the eID-Login plugin?', 'eidlogin' ),
			'help_text1a'               => __( 'The eID-Login plugin enables users to login to WordPress using an <b>eID-Card</b>, i.e. the <a href="https://www.personalausweisportal.de/Webs/PA/EN/citizens/electronic-identification/electronic-identification-node.html" target="_blank">eID function</a> of the German Identity Card. For the connection of an eID to their WordPress account and the eID based login, users need an <b>eID-Client</b>, this means an application like AusweisApp2 or the Open e-Card App.', 'eidlogin' ),
			'help_text1b'               => __( 'By using the German eID card (or another eID) and the associated PIN code the eID-Login provides a secure alternative to the login using username and password.', 'eidlogin' ),
			'help_text1c'               => __( '<b>Important!</b> When using the eID-Login plugin in it`s default configuration, no personal data from the eID card will be read. Only the <a href="https://www.personalausweisportal.de/SharedDocs/faqs/Webs/PA/DE/Haeufige-Fragen/9_pseudonymfunktikon/pseudonymfunktion-liste.html" target="_blank">pseudonym function</a> of the eID is being used. If any other data is being read, the user will be informed about it.', 'eidlogin' ),
			'help_topic2'               => __( 'Setup with SkIDentity as Identity Provider', 'eidlogin' ),
			'help_text2'                => __( 'The easiest way to setup the eID-Login plugin is by using the <a href="https://skidentity.com/" target="_blank">SkIDentity Service</a> which has been preconfigured for ease of use. For the default setup, which means only using the pseudonym function, no costs incur. The only requirement is the <a href="https://sp.skidentity.de/" target="_blank">registration</a> at SkIDentity.', 'eidlogin' ),
			'help_topic3'               => __( 'Setup with another Identity Provider', 'eidlogin' ),
			'help_text3'                => __( 'For the eID-Login any Identity Provider using the SAML protocol for communication with WordPress can be used. Beyond that any service providing an eID-Server according to <a href="https://www.bsi.bund.de/DE/Themen/Unternehmen-und-Organisationen/Standards-und-Zertifizierung/Technische-Richtlinien/TR-nach-Thema-sortiert/tr03130/TR-03130_node.html" target="_blank">BSI TR-03130</a> (also based on SAML) can be used.', 'eidlogin' ),
			'help_topic4'               => __( 'Technical background information', 'eidlogin' ),
			'help_text4'                => __( 'The eID-Login plugin uses the SAML protocol to let users login into WordPress via an external service (Identity Provider) using an eID.', 'eidlogin' ),
			'help_text5'                => __( 'The SAML protocol defines two major and collaborating entities:', 'eidlogin' ),
			'help_ul_text1'             => __( '<b>Service Provider (SP)</b>: An entity which provides any kind of service over the web. In the scope of the eID-Login plugin this is your WordPress instance, which contains a SAML Service Provider component.', 'eidlogin' ),
			'help_ul_text2'             => __( '<b>Identity Provider (IdP)</b>: An entity which authenticates the User and returns a corresponding assertion to the Service Provider. In the scope of the eID-Login plugin this can be any standard compliant SAML Identity Provider which supports eID-based authentication, for example the <a href="https://skidentity.com/" target="_blank">SkIDentity Service</a>.', 'eidlogin' ),
			'help_text6'                => __( 'The eID-Login procedure comprises the following steps:', 'eidlogin' ),
			'help_ol_text1'             => __( 'The User initiates the login procedure at the Service Provider.', 'eidlogin' ),
			'help_ol_text2'             => __( 'The Service Provider creates a SAML authentication request (<AuthnRequest>) and sends it together with the User via redirect to the Identity Provider.', 'eidlogin' ),
			'help_ol_text3'             => __( 'The Identity Provider authenticates the User using her eID via an eID-Client.', 'eidlogin' ),
			'help_ol_text4'             => __( 'The Identity Provider returns the result of the authentication procedure to the Service Provider (<Response>).', 'eidlogin' ),
			'help_ol_text5'             => __( 'The Service Provider validates the provided response and logs in the User in case of success.', 'eidlogin' ),

			/* Panel 1 overview */
			'overview_intro'            => __( 'The eID-Login plugin offers an alternative way of login for the registered users of your WordPress instance, using their electronic identity (<b>eID</b>). For example the German eID can then be used for a secure login.', 'eidlogin' ),
			'overview_hint'             => __( 'Setup of the eID-Login plugin consists of three steps:', 'eidlogin' ),
			'overview_li1_topic'        => __( 'Select Identity Provider', 'eidlogin' ),
			'overview_li1_text'         => __( 'For the usage of the eID-Login plugin a service which makes the eID accessible is needed. This service is called <b>Identity Provider</b> or in short <b>IdP</b>. You can choose to use the preconfigured <a target="_blank" href="https://skidentity.com">SkIDentity</a> service or select another service.', 'eidlogin' ),
			'overview_li2_topic'        => __( 'Configuration at the Identity Provider', 'eidlogin' ),
			'overview_li2_text'         => __( 'At the Identity Provider your WordPress instance, which serves as <b>Service Provider</b>, must be registered. The process of registration depends on the respective Identity Provider. The information needed for registration is provided in step 2.', 'eidlogin' ),
			'overview_li3_text'         => __( 'In order to use a German eID ("Personalausweis") or another eID for the login at WordPress, the eID must be connected to an user account.', 'eidlogin' ),
			'overview_hint_help'        => __( 'Please click on the (?) icon for help regarding the setup or more information.', 'eidlogin' ),
			'continue_skid'             => __( 'Continue with SkIDentity', 'eidlogin' ),
			'continue_other'            => __( 'Continue with another IdP', 'eidlogin' ),

			/* Panel 2 Select IdP */
			'p2_topic'                  => __( 'Select Identity Provider', 'eidlogin' ),
			'p2_hint1'                  => __( 'Select an Identity Provider. It must support the SAML protocol.', 'eidlogin' ),
			'p2_hint2'                  => __( 'Insert the Identity Providers Metadata URL in the respective form field and assign an Entity ID, which must be used for the configuration of the Identity Provider in the next step.', 'eidlogin' ),
			'p2_idp_metadata_url'       => __( 'Identity Provider Metadata URL', 'eidlogin' ),
			'p2_idp_metadata_url_hint'  => __( 'When inserting the Metadata URL, the values in the advanced settings will be updated. Alternatively the advanced settings can be inserted by hand.', 'eidlogin' ),
			'p2_sp_entity_id'           => __( 'Service Provider EntityID', 'eidlogin' ),
			'p2_sp_entity_id_hint'      => __( 'Usually the domain of your WordPress instance is used.', 'eidlogin' ),
			'p2_idp_enforce_enc'        => __( 'Enforce encryption of SAML assertions (Check only if the selected Identity Provider supports this feature!)', 'eidlogin' ),
			'p2_edit_advanced_settings' => __( 'Edit Advanced Settings', 'eidlogin' ),

			/* Panel 2 Advanced Settings */
			'advanced_settings'         => __( 'Advanced Settings', 'eidlogin' ),
			'idp_sso_hint'              => __( 'URL of the Identity Provider to which the SAML authentication request will be sent.', 'eidlogin' ),
			'idp_cert_sign'             => __( 'Signature Certificate of the Identity Provider', 'eidlogin' ),
			'idp_cert_sign_hint'        => __( 'Certificate to validate the signature of the authentication response.', 'eidlogin' ),
			'idp_cert_enc'              => __( 'Encryption Certificate of the Identity Provider', 'eidlogin' ),
			'idp_cert_enc_hint'         => __( 'Certificate to encrypt the authentication request. Omitting the element means that the SAML requests are not encrypted.', 'eidlogin' ),
			'idp_ext_tr03130'           => __( 'AuthnRequestExtension XML element', 'eidlogin' ),
			'idp_ext_tr03130_hint'      => __( 'For a connection according to <a href="https://www.bsi.bund.de/SharedDocs/Downloads/DE/BSI/Publikationen/TechnischeRichtlinien/TR03130/TR-03130_TR-eID-Server_Part1.pdf?__blob=publicationFile&v=1" target="_blank">BSI TR-03130</a>, the corresponding <AuthnRequestExtension> XML element must be inserted here.', 'eidlogin' ),

			/* Panel 3 Configure IdP */
			'p3_hint'                   => __( 'Now go to the selected Identity Provider and use the following data to register the Service Provider there:', 'eidlogin' ),
			'p3_skid_hint'              => __( 'You have selected SkIDentity. Click the button to the right to go to SkIDentity.', 'eidlogin' ),
			'p3_open_skid'              => __( 'Open SkIDentity', 'eidlogin' ),
			'p3_sp_metadata_hint'       => __( 'The metadata as provided by the Service Provider at the URL', 'eidlogin' ),

			/* Panel 4 Connect eID */
			'connect_eid_hint1'         => __( 'To use the eID-Login, the eID must be connected to the user account. For this you need an eID-Card, like the German eID, a suitable cardreader and an active eID-Client (for example <a href="https://www.openecard.org/en/download/pc/" target="_blank">Open eCard-App</a> or <a href="https://www.ausweisapp.bund.de/ausweisapp2/" target="_blank">AusweisApp2</a>). After establishing the connection the eID-Login can be used with the button on the login page.', 'eidlogin' ),
			'connect_eid_hint2'         => __( 'You can connect your account with an eID now. This step is optional and can be done and reverted any time later in your personal settings under the security section.', 'eidlogin' ),
			'connect_eid_hint3'         => __( '<b>Please note</b>: After connecting the eID or finishing the wizard, this page will show a form for direct access to the eID-Login settings. To use the wizard again, reset the settings of the eID-Login plugin.', 'eidlogin' ),
			'connect_eid_button'        => __( 'Create connection to eID', 'eidlogin' ),
			'connect_finish_wizard'     => __( 'Finish wizard', 'eidlogin' ),

			/* Manual settings / form */
			'manual_activated_hint'     => __( 'If the eID-Login is activated, the eID-Login button is shown and users can edit eID connections.', 'eidlogin' ),
			'manual_activated'          => __( 'eID-Login is activated', 'eidlogin' ),
			'manual_required_hint'      => __( 'Please Note: Required values in the following form are labeled with an *.', 'eidlogin' ),

			'manual_sp_settings'        => __( 'Service Provider Settings', 'eidlogin' ),
			'manual_entity_id_hint'     => __( 'EntityID of the Service Provider as configured at the Identity Provider.', 'eidlogin' ),
			'manual_acs_hint'           => __( 'Assertion Consumer URL is determined by the domain of your Service Provider and cannot be changed. Use this value to configure the Service Provider at the Identity Provider.', 'eidlogin' ),
			'manual_metadata_hint'      => __( 'SAML Metadata URL is determined by the domain of your Service Provider and cannot be changed. Use this value to configure the Service Provider at the Identity Provider.', 'eidlogin' ),
			'manual_metadata'           => __( 'SAML Metadata URL', 'eidlogin' ),
			'manual_enforce_enc'        => __( 'Encrypted assertions', 'eidlogin' ),
			'manual_enforce_enc_hint'   => __( 'Enforce encryption of SAML assertions (Check only if the selected Identity Provider supports this feature!)', 'eidlogin' ),

			'manual_idp_settings'       => __( 'Identity Provider Settings', 'eidlogin' ),
			'manual_idp_entity_id'      => __( 'EntityID of the Identity Provider', 'eidlogin' ),
			'manual_idp_sso_hint'       => __( 'URL of the Identity Provider to which the SAML authentication request will be sent.', 'eidlogin' ),

			'save'                      => __( 'Save Changes', 'eidlogin' ),
			'reset'                     => __( 'Reset Settings', 'eidlogin' ),

			/* Certificate rollover */
			'cert_rollover'             => __( 'SAML Certificate Rollover', 'eidlogin' ),
			/* Translators: %s: the remaining days */
			'cert_rollover_status'      => __( 'The active certificates expire in %s days. For a regular certificate rollover no action is required. The rollover will be done automatically. But you always have the option to do a manual rollover, if needed.', 'eidlogin' ),

			'cert_current'              => __( 'Current certificates', 'eidlogin' ),
			'cert_rollover_cert_active' => __( 'The currently active certificate ends with:', 'eidlogin' ),
			'cert_rollover_cert_new'    => __( 'The newly prepared certificate, which is not yet active, ends with:', 'eidlogin' ),
			'cert_rollover_sig'         => __( 'Signature', 'eidlogin' ),
			'cert_rollover_enc'         => __( 'Encryption', 'eidlogin' ),
			'cert_rollover_no_cert'     => __( 'No new certificate prepared yet.', 'eidlogin' ),

			'cert_manual'               => __( 'Manual Certificate Rollover', 'eidlogin' ),
			'cert_preparation'          => __( 'Certificate Rollover Preparation', 'eidlogin' ),
			'cert_prep_hint1'           => __( 'In a first step new certificates will be created, which will be in the state <i>prepared</i> but not activated.', 'eidlogin' ),
			'cert_prep_hint2'           => __( 'After some time the Identity Provider should have noticed the presence of the new certificates or you must explicitly inform the Identity Provider about them. After this has happened the rollover can be executed.', 'eidlogin' ),
			'cert_rollover_prepare'     => __( 'Prepare Certificate Rollover', 'eidlogin' ),

			'cert_activation'           => __( 'Activation of prepared certificates', 'eidlogin' ),
			'cert_rollover_hint1'       => __( 'The activation of the new certificates will happen automatically after some time, but can also be done by clicking the button below.', 'eidlogin' ),
			'cert_rollover_caution'     => __( 'CAUTION: Only do this step manually, if you have made sure that the prepared certificates have been successfully configured at the Identity Provider or there are other important reasons to change the certificates immediately.', 'eidlogin' ),
			'cert_rollover_execute'     => __( 'Activate prepared certificates', 'eidlogin' ),
			'cert_rollover_hint2'       => __( 'The button is only active if the rollover has been prepared already!', 'eidlogin' ),

			/* Translations for profile.html */
			'change_status'             => __( 'Change status', 'eidlogin' ),
			'disable_password'          => __( 'Disable password', 'eidlogin' ),
			'disable_hint'              => __( 'Disable password based login. This will be unset if you use the password recovery.', 'eidlogin' ),
			'confirm_msg'               => __( 'After the deletion you will not be able to access your account via eID-Login. Are you sure?', 'eidlogin' ),
		);
	}

}
