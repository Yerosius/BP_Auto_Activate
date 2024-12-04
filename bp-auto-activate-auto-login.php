<?php

/**
 * Plugin Name: BuddyPress Auto Activate  Autologin Redirect To Profile On Signup
 * Plugin URI: https://buddydev.com/plugins/bp-auto-activate-auto-login/
 * Description: BuddyPress Auto Activate  Autologin Redirect To Profile On Signup, will automatically activate the user account when they signup for a username or for username/blog both. After activating the new user's account, It will automatically make them logged in and then, the new user will be redirected to his/her profile
 * Author: Brajesh Singh
 * Author URI: https://buddydev.com/
 * Version: 1.5.7
 * Network: true
 * License: GPL
 */
class BPDev_Account_Auto_Activator {

	/**
	 * Singleton instance
	 *
	 * @var BPDev_Account_Auto_Activator
	 */
	private static $instance = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Get singleton instance
	 *
	 * @return BPDev_Account_Auto_Activator
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup hooks
	 */
	public function setup() {
		// add a filter to disable the activation from outside.
		// Note that it is called on plugins_loaded, so callbacks mucst be attached before that.

		if ( apply_filters( 'bpaaal_disable_auto_activation', false ) ) {
			return;
		}

		// sending activation email is disabled.
		if ( is_multisite() || ! self::is_activation_email_enabled() ) {
			// stop notifications.
			add_filter( 'wpmu_welcome_notification', '__return_false', 110 ); // 5 args,no need to send the clear text password when blog is activated
			add_filter( 'wpmu_welcome_user_notification', '__return_false', 110 ); // 5 args,no need to send the clear text password when blog is activated

			// Stop BuddyPress activation email( Email with key etc).
			add_filter( 'bp_core_signup_send_activation_key', '__return_false', 110 ); // 5 args,no need to send the clear text password when blog is activated

			// Remove buddypress notifications.
			add_action( 'bp_loaded', array( $this, 'remove_bp_filters' ), 100 );
		}

		// Multisite Signup with Blog activation.
		add_filter( 'wpmu_signup_blog_notification', array( $this, 'activate_on_blog_signup' ), 10, 7 );

		// Multisite User Signup - without blog activation.
		add_filter( 'wpmu_signup_user_notification', array( $this, 'activate_user_for_wpms' ), 10, 4 );

		// Non multisite user activation.
		add_action( 'bp_core_signup_user', array( $this, 'active_user_for_wps' ), 11, 5 );

		add_action( 'plugins_loaded', array( $this, 'load' ) );
		add_action( 'plugins_loaded', array( $this, 'load_admin' ), 9996 );

		add_action( 'bp_loaded', array( $this, 'load_text_domain' ) );

		add_action( 'gform_user_registered', array( $this, 'on_gform_registration' ), 20, 4 );
	}

	/**
	 * Load.
	 */
	public function load() {
		require plugin_dir_path( __FILE__ ) . 'bp-aaal-redirection-manager.php';
	}

	public function disable_notifications_for_multisite() {

	}

	/**
	 * Remove various filters added by BuddyPress
	 */
	public function remove_bp_filters() {

		if ( has_filter( 'wpmu_signup_user_notification', 'bp_core_activation_signup_user_notification' ) ) {
			// Remove bp user notification for activating account.
			remove_filter( 'wpmu_signup_user_notification', 'bp_core_activation_signup_user_notification', 1);
		}

		if ( has_filter( 'wpmu_signup_blog_notification', 'bp_core_activation_signup_blog_notification' ) ) {
			// remove bp blog notification.
			remove_filter( 'wpmu_signup_blog_notification', 'bp_core_activation_signup_blog_notification', 1 );
		}
	}

	/**
	 * Load translations
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'bp-auto-activate-auto-login', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * On gravity form user registration
	 *
	 * @param int    $user_id  User id.
	 * @param array  $feed     Feed object.
	 * @param array  $entry    Entry details.
	 * @param string $password User Password.
	 */
	public function on_gform_registration( $user_id, $feed, $entry, $password ) {

		$is_pending_activation_enabled = GF_User_Registration::is_pending_activation_enabled( $feed );

		if ( $is_pending_activation_enabled ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		self::login_redirect( $user->user_login, $password );
	}

	/**
	 * Makes a user logged in and redirect to his/her profile
	 *
	 * @param string $user_login user login name.
	 * @param string $password user password.
	 */
	public static function login_redirect( $user_login, $password ) {
		$user = get_user_by( 'login', $user_login );

		if ( ! $user ) {
			return;
		}

		if ( self::is_auto_login_disabled() ) {
			$redirect_url = self::get_redirect_url( $user );
			// if the signup was success full.redirect to the membership page.
			if ( $redirect_url ) {
				bp_core_redirect( $redirect_url );
			}
		}

		// add compat with force login plugin.
		if (  self::use_email_login( $user_login ) ) {
			$creds = array( 'user_login' => $user->user_email, 'user_password' => $password );
		} else {
			$creds = array( 'user_login' => $user->user_login, 'user_password' => $password );
		}

		// make the user logged in.
		$user = wp_signon( $creds );

		if ( ! is_wp_error( $user ) ) {
			$redirect_url = self::get_redirect_url( $user );
			// if the signup was success full.redirect to the membership page.
			if ( $redirect_url ) {
				bp_core_redirect( $redirect_url );
			}
		}
	}

	/**
	 * Should we use email for login.
	 *
	 * @param string $user_login user login.
	 *
	 * @return bool
	 */
	private static function use_email_login( $user_login ) {
		$use = class_exists( 'Force_Email_Auth' ) || function_exists(  'force_login_with_email_get_default' );

		return apply_filters( 'bp_autoactivate_use_email_login', $use, $user_login );
	}

	/**
	 * Update xprofile fields from the signup meta data
	 *
	 * @param int   $user_id numeric user id.
	 * @param array $signup signup data.
	 */
	public static function update_profile_fields( $user_id, $signup ) {

		/* Set any profile data */
		if ( function_exists( 'xprofile_set_field_data' ) ) {

			if ( ! empty( $signup['meta']['profile_field_ids'] ) ) {

				$profile_field_ids = explode( ',', $signup['meta']['profile_field_ids'] );

				foreach ( $profile_field_ids as $field_id ) {

					$current_field = $signup['meta'][ "field_{$field_id}" ];

					if ( ! empty( $current_field ) ) {
						xprofile_set_field_data( $field_id, $user_id, $current_field );
					}
				}
			}
		}

	}

	/**
	 * Activates User account on Multisite based on the given key
	 *
	 * @param string $key the signup key.
	 * @param string $user_or_domain User or domain.
	 */
	public static function ms_activate_account( $key, $user_or_domain ) {

		$is_email_enabled = self::is_activation_email_enabled();
		// if doing ajax, return.
		if ( defined( 'DOING_AJAX' ) ) {
			return $is_email_enabled ? $user_or_domain : false;
		}

		// mimic bp activation.
		$bp = buddypress();
		// do not fire password change email on acount activation.
		add_filter( 'send_password_change_email', '__return_false' );
		$signup = apply_filters( 'bp_core_activate_account', wpmu_activate_signup( $key ) );

		/* If there was errors, add a message and redirect */
		if ( $signup && ! empty( $signup->errors ) ) {
			if ( ! $is_email_enabled ) {
				bp_core_add_message( __( 'There was an error activating your account, please try again.', 'bp-auto-activate-auto-login' ), 'error' );
				bp_core_redirect( bp_get_activation_page() );
			} else {
				return $user_or_domain;
			}
			// send the activation mail in this case.
		}

		$user_id = $signup['user_id'];
		// should we pass password as a param instead of the dependency here?
		$pass = $_POST['signup_password'];

		$ud = get_userdata( $user_id );

		$data = array(
			'user_login'   => $ud->user_login,
			'user_email'   => $ud->user_email,
			'user_pass'    => $pass,
			'ID'           => $user_id,
			'display_name' => bp_core_get_user_displayname( $user_id ),
		);

		// update password.
		if ( is_multisite() ) {
			wp_update_user( $data );
		}

		self::update_profile_fields( $user_id, $signup );

		do_action( 'bp_core_activated_user', $user_id, $key, $signup );
		// let bp handle the new user registerd activity
		// do_action( 'bp_core_account_activated', &$signup, $_GET['key'] );

		bp_core_add_message( __( 'Your account is now active!' ) );

		$bp->activation_complete = true;

		// how do we proceed here(allow emails?
		self::login_redirect( $ud->user_login, $pass );

	}

	/**
	 * Activates User account on multisite
	 *
	 * @param $user
	 * @param $user_email
	 * @param $key
	 * @param $meta
	 *
	 * @return bool
	 */
	public function activate_user_for_wpms( $user, $user_email, $key, $meta ) {

		if ( class_exists( 'GF_User_Registration' ) && isset( $_POST['gform_submit'] ) ) {
			return;
		}

		return self::ms_activate_account( $key, $user );
	}

	/**
	 * Activates the User account when a User signs up for a blog
	 *
	 * @param string $domain blog domain
	 * @param string $path
	 * @param string $title
	 * @param $user
	 * @param string $user_email
	 * @param string $key
	 * @param array $meta
	 *
	 * @return bool
	 */
	public function activate_on_blog_signup( $domain, $path, $title, $user, $user_email, $key, $meta ) {
		return self::ms_activate_account( $key, $domain );
	}

	/**
	 * Activate user account for standard wp install.
	 *
	 * @param int    $user_id numeric user id.
	 * @param string $user_login user login.
	 * @param string $user_password user password.
	 * @param string $user_email user email.
	 * @param array  $usermeta user meta.
	 *
	 * @return bool|int
	 */
	public function active_user_for_wps( $user_id, $user_login, $user_password, $user_email, $usermeta ) {
		global $bp;

		$user = null;

		// In case of ajax, It is most probably our ajax registration plugin, Let that handle it.
		if ( defined( 'DOING_AJAX' ) ) {
			return $user_id;
		}

		if ( is_multisite() ) {
			return $user_id; // do not proceed for mu. We already handle it somewhere else.
		}

		// Disallow send password change email(WordPress sends it when User is updated with teh password)
		add_filter( 'send_password_change_email', '__return_false' );

		$signups = BP_Signup::get( array( 'user_login' => $user_login ) );

		$signups = $signups['signups'];

		if ( ! $signups ) {
			return false;
		}

		// if we are here, just popout the array.
		$signup = array_pop( $signups );

		$key     = $signup->activation_key;
		$user_id = bp_core_activate_signup( $key );

		if ( ! $user_id ) {
			return false;
		}

		bp_core_add_message( __( 'Your account is now active!', 'bp-auto-activate-auto-login' ) );

		$bp->activation_complete = true;

		if ( function_exists( 'xprofile_sync_wp_profile' ) ) {
			xprofile_sync_wp_profile();
		}
		// $ud = get_userdata($signup['user_id']);
		self::login_redirect( $user_login, $user_password );

		// will never reach here anyway.
		return $user_id;
	}

	/**
	 * Load plugin admin section
	 */
	public function load_admin() {

		if ( ! function_exists( 'buddypress' ) || ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		$path = plugin_dir_path( __FILE__ );

		require_once $path . 'admin/pt-settings/pt-settings-loader.php';
		require_once $path . 'admin/class-bp-auto-activate-auto-login-admin-settings-helper.php';

		BP_Auto_Activate_Auto_Login_Admin_Settings_Helper::boot();
	}

	/**
	 * Get parsed url
	 *
	 * @param WP_User $user User obejct.
	 * @param string  $url Url to be parsed.
	 *
	 * @return string
	 */
	private static function get_parsed_url( $user, $url ) {
		return BP_Auto_Activation_Auto_Login_Redirection_Manager::get_parsed_url($user, $url);
	}

	/**
	 * Get redirect url based on admin settings
	 *
	 * @param WP_User $user Activated user object.
	 *
	 * @return string
	 */
	private static function get_redirect_url( $user ) {
		$settings     = (array) get_option( 'bp_auto_activate_auto_login_settings', array() );
		$redirect_url = empty( $settings['redirect_url'] ) ? self::get_user_url( $user->ID ) : self::get_parsed_url( $user, $settings['redirect_url'] );

		if ( ! empty( $settings['enable_role_redirect'] ) ) {
			foreach ( $user->roles as $role ) {
				if ( ! empty( $settings[ 'enable_redirect_' . $role ] ) && ! empty( $settings[ 'redirect_url_' . $role ] ) ) {
					$redirect_url = self::get_parsed_url( $user, $settings[ 'redirect_url_' . $role ] );
					break;
				}
			}
		}

		return apply_filters( 'bpdev_autoactivate_redirect_url', $redirect_url, $user->ID );
	}

	/**
	 * Is activation email enabled.
	 *
	 * @return bool
	 */
	private static function is_activation_email_enabled() {
		$settings = (array) get_option( 'bp_auto_activate_auto_login_settings', array() );

		return isset( $settings['enable_activation_email'] ) ? (bool) $settings['enable_activation_email'] : false;
	}

	/**
	 * Check weather auto login is disabled or not
	 *
	 * @return bool
	 */
	private static function is_auto_login_disabled() {
		$settings = (array) get_option( 'bp_auto_activate_auto_login_settings', array() );

		return isset( $settings['disable_auto_login'] ) ? (bool) $settings['disable_auto_login'] : false;
	}

	/**
	 * Retrieves user url
	 *
	 * @param int $user_id User id.
	 *
	 * @return string
	 */
	public static function get_user_url( $user_id ) {

		if ( function_exists( 'bp_members_get_user_url' ) ) {
			return bp_members_get_user_url( $user_id );
		}

		return bp_core_get_user_domain( $user_id );
	}
}

BPDev_Account_Auto_Activator::get_instance();
