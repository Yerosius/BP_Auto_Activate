<?php
/**
 * Admin settings helper class for plugin
 *
 * @package bp-auto-activate-auto-login
 */

// No direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Press_Themes\PT_Settings\Page;

/**
 * Class BP_Auto_Activate_Auto_Login_Admin_Settings_Helper
 */
class BP_Auto_Activate_Auto_Login_Admin_Settings_Helper {

	/**
	 * Page object
	 *
	 * @var Page
	 */
	private $page;

	/**
	 * Page slug
	 *
	 * @var string
	 */
	private $page_slug = '';

	/**
	 * Boot class
	 */
	public static function boot() {
		$self = new self();
		$self->setup();
	}

	/**
	 * Setup
	 */
	private function setup() {
		$this->page_slug = 'bp-auto-activate-auto-login-settings';

		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_setting_page' ) );
	}

	/**
	 * Initialize the admin settings panel and fields
	 */
	public function init() {
		global $pagenow;

		$page_slug = isset( $_GET['page'] ) ? trim( $_GET['page'] ) : '';

		if ( 'options.php' === $pagenow || $this->page_slug === $page_slug ) {
			$this->register_settings();
		}
	}

	/**
	 * Initialize settings
	 */
	private function register_settings() {
		$page = new Page( 'bp_auto_activate_auto_login_settings', __( 'BP Auto Activate Auto Login Settings', 'bp-auto-activate-auto-login' ) );

		$panel   = $page->add_panel( 'activation', __( 'Activation Redirection', 'bp-auto-activate-auto-login' ) );
		$section = $panel->add_section( 'activation-sec-general', __( 'General settings', 'bp-auto-activate-auto-login' ) );
		$section->add_fields(
			array(
				array(
					'name'    => 'redirect_url',
					'label'   => __( 'Redirect to', 'bp-auto-activate-auto-login' ),
					'type'    => 'text',
					'default' => '[user_url]',
					'desc'    => __( 'Default redirection url. Supported tags [user_url],[site_url],[network_url]', 'bp-auto-activate-auto-login' ),
				),
				array(
					'name'    => 'enable_role_redirect',
					'label'   => __( 'Enable role based redirect?', 'bp-auto-activate-auto-login' ),
					'type'    => 'radio',
					'default' => 0,
					'options' => array(
						1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
						0 => __( 'No', 'bp-auto-activate-auto-login' ),
					),
					'desc'    => __( 'If enabled, you can configure role specific redirect below', 'bp-auto-activate-auto-login' ),
				),
			)
		);

		$roles = wp_roles()->roles;
		foreach ( $roles as $role => $label ) {
			// No redirection for bbPress based roles.
			if ( strpos( $role, 'bbp_' ) === 0 ) {
				continue;
			}

			$section = $panel->add_section( 'activation-sec-role_' . $role, $label['name'], sprintf( __( ' Redirection settings for users with %s role.' ), $label['name'] ) );

			$section->add_fields( array(
				array(
					'name'    => 'enable_redirect_' . $role,
					'label'   => __( 'Enable?', 'bp-auto-activate-auto-login' ),
					'type'    => 'radio',
					'default' => 0,
					'options' => array(
						1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
						0 => __( 'No', 'bp-auto-activate-auto-login' ),
					),
					'desc'    => __( "Only enabled role's redirect is used.", 'bp-auto-activate-auto-login' ),
				),
				array(
					'name'    => 'redirect_url_' . $role,
					'label'   => __( 'Redirect to', 'bp-auto-activate-auto-login' ),
					'type'    => 'text',
					'default' => '[user_url]',
					'desc'    => __( 'Supported tags [user_url],[site_url],[network_url]', 'bp-auto-activate-auto-login' ),
				)

			) );
		}

		// Login rediraction
		$panel   = $page->add_panel( 'login', __( 'Login Redirection', 'bp-auto-activate-auto-login' ) );
		$section = $panel->add_section( 'login-sec-general', __( 'General settings', 'bp-auto-activate-auto-login' ) );
		$section->add_fields(
			array(
				array(
					'name'    => 'enable_login_redirect',
					'label'   => __( 'Enable Login redirection?', 'bp-auto-activate-auto-login' ),
					'type'    => 'radio',
					'default' => 0,
					'options' => array(
						1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
						0 => __( 'No', 'bp-auto-activate-auto-login' ),
					),
					'desc'    => __( 'Enable/Disable login redirection functionality.', 'bp-auto-activate-auto-login' ),
				),
				array(
					'name'    => 'login_redirect_url',
					'label'   => __( 'Redirect to', 'bp-auto-activate-auto-login' ),
					'type'    => 'text',
					'default' => '[user_url]',
					'desc'    => __( 'Default redirect url. Supported tags [user_url],[site_url],[network_url]', 'bp-auto-activate-auto-login' ),
				),
				array(
					'name'    => 'enable_login_redirect_role',
					'label'   => __( 'Enable Role based redirection?', 'bp-auto-activate-auto-login' ),
					'type'    => 'radio',
					'default' => 0,
					'options' => array(
						1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
						0 => __( 'No', 'bp-auto-activate-auto-login' ),
					),
					'desc'    => __( 'Only works if login redirection is enabled too. If enabled, role based redirection will be applied.', 'bp-auto-activate-auto-login' ),
				),
			)
		);

		$roles = wp_roles()->roles;
		foreach ( $roles as $role => $label ) {
			// No redirection for bbPress based roles.
			if ( strpos( $role, 'bbp_' ) === 0 ) {
				continue;
			}

			$section = $panel->add_section( 'login-sec-role_' . $role, $label['name'], sprintf( __( ' Redirection settings for users with %s role.' ), $label['name'] ) );

			$section->add_fields( array(
				array(
					'name'    => 'enable_login_redirect_' . $role,
					'label'   => __( 'Enable?', 'bp-auto-activate-auto-login' ),
					'type'    => 'radio',
					'default' => 0,
					'options' => array(
						1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
						0 => __( 'No', 'bp-auto-activate-auto-login' ),
					),
					'desc'    => __( "Only enabled role's redirection is used. Otherwise, default url specified above will be used.", 'bp-auto-activate-auto-login' ),
				),
				array(
					'name'    => 'login_redirect_url_' . $role,
					'label'   => __( 'Redirect to', 'bp-auto-activate-auto-login' ),
					'type'    => 'text',
					'default' => '[user_url]',
					'desc'    => __( 'Supported tags [user_url],[site_url],[network_url]', 'bp-auto-activate-auto-login' ),
				)
			) );
		}

		// Login rediraction
		if ( ! is_multisite() ) {
			$panel   = $page->add_panel( 'panel-misc', __( 'Miscellaneous', 'bp-auto-activate-auto-login' ) );
			$section = $panel->add_section( 'misc-sec-general', __( 'Email Notification Settings', 'bp-auto-activate-auto-login' ) );
			$section->add_fields(
				array(
					array(
						'name'    => 'enable_activation_email',
						'label'   => __( 'Enable Activation email?', 'bp-auto-activate-auto-login' ),
						'type'    => 'radio',
						'default' => 0,
						'options' => array(
							1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
							0 => __( 'No', 'bp-auto-activate-auto-login' ),
						),
						'desc'    => __( 'By default the activation email is disabled. If you enable it, Please make sure to Edit BuddyPress Activation Email template to reflect the changes.', 'bp-auto-activate-auto-login' ),
					),
					array(
						'name'    => 'disable_auto_login',
						'label'   => __( 'Disable Auto Login?', 'bp-auto-activate-auto-login' ),
						'type'    => 'radio',
						'default' => 0,
						'options' => array(
							1 => __( 'Yes', 'bp-auto-activate-auto-login' ),
							0 => __( 'No', 'bp-auto-activate-auto-login' ),
						),
						'desc'    => __( 'If you disable it, User will not be auto login.', 'bp-auto-activate-auto-login' ),
					),
				)
			);
		}

		$this->page = $page;

		do_action( 'bp_auto_activate_auto_login_admin_settings', $page );

		// allow enabling options.
		$page->init();
	}

	/**
	 * Render page
	 */
	public function render() {
		$this->page->render();
	}

	/**
	 * Add menu
	 */
	public function add_setting_page() {
		add_options_page(
			__( 'BP Auto Activate Auto Login Settings', 'bp-auto-activate-auto-login' ),
			__( 'BP Auto Activate Auto Login Settings', 'bp-auto-activate-auto-login' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render' )
		);
	}
}