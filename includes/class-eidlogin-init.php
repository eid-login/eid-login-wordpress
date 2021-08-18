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
 * Main class of the eID-Login plugin.
 *
 * It is responsible for loading all dependencies, the templating engine and the
 * locale and for setting various hooks.
 */
class Eidlogin_Init {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @access   protected
	 * @var      Eidlogin_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The plugin name.
	 *
	 * @access   protected
	 * @var string $plugin_name The plugin name.
	 */
	protected $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @access   protected
	 * @var string $version The plugin version.
	 */
	protected $version;

	/**
	 * The Twig template.
	 *
	 * @access   protected
	 * @var \Twig\Environment $twig The Twig template object.
	 */
	protected $twig;

	/**
	 * Constructor of the Eidlogin_Init class.
	 *
	 * Set the plugin name and the plugin version that can be used throughout
	 * the plugin. Load the dependencies, define the locale, and set the hooks
	 * for the admin area and the public-facing side of the site.
	 */
	public function __construct() {
		$this->version     = EIDLOGIN_PLUGIN_VERSION;
		$this->plugin_name = 'eidlogin';

		$this->load_dependencies();
		$this->load_twig();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load all dependencies and initialize der Loader class.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'db/class-eidlogin-continue-data.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'db/class-eidlogin-response-data.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'db/class-eidlogin-user.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-eidlogin-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-eidlogin-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-eidlogin-helper.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-eidlogin-certificates.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-eidlogin-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-eidlogin-cron.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-eidlogin-rest.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'saml/class-eidlogin-saml.php';

		$this->loader = new Eidlogin_Loader();
	}

	/**
	 * Load and initialize the Twig template engine.
	 */
	private function load_twig() {
		$loader     = new \Twig\Loader\FilesystemLoader( plugin_dir_path( dirname( __FILE__ ) ) . 'tmpl' );
		$this->twig = new \Twig\Environment( $loader );
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Eidlogin_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 */
	private function set_locale() {
		$plugin_i18n = new Eidlogin_I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Eidlogin_Admin( $this->plugin_name, $this->version, $this->twig );

		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'eidlogin_check_version' );

		$this->loader->add_action( 'locale', $plugin_admin, 'eidlogin_force_locale' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// In order to display the link only for this plugin, the first
		// parameter has to have the following format:
		// 'plugin_action_links_plugin_name/plugin_name.php'.
		$action_link = sprintf( 'plugin_action_links_%s/%s.php', $this->plugin_name, $this->plugin_name );
		$this->loader->add_filter( $action_link, $plugin_admin, 'eidlogin_settings_link' );
		$this->loader->add_filter( 'allowed_redirect_hosts', $plugin_admin, 'eidlogin_allowed_domains', 10, 1 );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'eidlogin_menu_item' );

		$this->loader->add_action( 'delete_user', $plugin_admin, 'eidlogin_delete_user', 10, 1 );

		// Setup CRON.
		$plugin_cron = new Eidlogin_Cron( $this->twig );
		$this->loader->add_filter( 'cron_schedules', $plugin_cron, 'eidlogin_cron_interval' );
		$this->loader->add_action( EIDLOGIN_CERT_CRON_HOOK, $plugin_cron, 'eidlogin_cert_cron_run' );
		$this->loader->add_action( EIDLOGIN_CLEANUP_CRON_HOOK, $plugin_cron, 'eidlogin_cleanup_cron_run' );

		// Setup REST interface.
		$plugin_rest = new Eidlogin_Rest( $this->plugin_name );
		$this->loader->add_action( 'rest_api_init', $plugin_rest, 'eidlogin_rest_api', 10 );
	}

	/**
	 * Register all of the hooks related to the public-facing SAML functionality
	 * of the plugin.
	 */
	private function define_public_hooks() {
		$plugin_saml = new Eidlogin_Saml( $this->plugin_name, $this->version, $this->twig );

		$this->loader->add_action( 'login_enqueue_scripts', $plugin_saml, 'enqueue_styles' );
		$this->loader->add_action( 'init', $plugin_saml, 'saml_routes', 1 );

		$this->loader->add_action( 'show_user_profile', $plugin_saml, 'saml_profile', 10, 1 );
		$this->loader->add_action( 'personal_options_update', $plugin_saml, 'saml_profile_update' );
		$this->loader->add_action( 'profile_update', $plugin_saml, 'eidlogin_profile_update' );
		$this->loader->add_action( 'after_password_reset', $plugin_saml, 'eidlogin_password_reset', 10, 2 );

		$this->loader->add_filter( 'login_form', $plugin_saml, 'display_perso_button' );
		$this->loader->add_filter( 'login_message', $plugin_saml, 'saml_login_message' );

		$this->loader->add_action( 'user_register', $plugin_saml, 'user_register_defaults' );
		$this->loader->add_action( 'personal_options', $plugin_saml, 'eidlogin_first_time_user_hint' );

		$this->loader->add_filter( 'wp_authenticate_user', $plugin_saml, 'allow_password_login' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}
}
