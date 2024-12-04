<?php
/**
 * Manage Redirection settings and profile tab option.
 *
 * @package    BuddyPress Member Types Pro
 * @subpackage Core
 * @copyright  Copyright (c) 2018, Brajesh Singh
 * @license    https://www.gnu.org/licenses/gpl.html GNU Public License
 * @author     Brajesh Singh
 * @since      1.0.0
 */

// Do not allow direct access over web.
defined( 'ABSPATH' ) || exit;

/**
 * Redirection manager.
 */
class BP_Auto_Activation_Auto_Login_Redirection_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var BP_Auto_Activation_Auto_Login_Redirection_Manager
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return BP_Auto_Activation_Auto_Login_Redirection_Manager
	 */
	public static function get_instance() {
		if( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup hooks.
	 */
	public function setup() {
		$settings = (array) get_option( 'bp_auto_activate_auto_login_settings', array() );
		if ( ! empty( $settings['enable_login_redirect'] ) && function_exists( 'buddypress' ) ) {
			$this->setup_login_filters();
		}
	}

	private function setup_login_filters() {
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 110, 3 );
		// compatibility with ajax login v1.x.
		add_filter( 'rh_custom_redirect_for_reg', array( $this, 'ajax_reg_activation_redirect' ), 1000, 2 );

		// Subway plugin compatibility.
		add_filter( 'subway_login_redirect', array( $this, 'subway_login_redirect' ), 1000, 2 );

		add_filter( 'ghostpool_login_redirect', array( $this, 'gp_redirect' ), 1001, 2 );
		add_filter( 'rh_custom_redirect_for_login', array( $this, 'gp_redirect' ), 1001, 2 );
		// boombox theme compatibility.
		add_filter( 'snax_login_redirect_url', array( $this, 'gp_redirect' ), 1001, 2 );
	}

	/**
	 * Calculate the url to be redirected on login.
	 *
	 * @param string  $redirect_to_calculated calculated redirect.
	 * @param string  $redirect_url_specified specified redirect.
	 * @param WP_User $user user object.
	 *
	 * @return string
	 */
	public function login_redirect( $redirect_to_calculated, $redirect_url_specified, $user ) {

		if ( ! $user || is_wp_error( $user ) ) {
			return $redirect_to_calculated;
		}

		$redirect = $this->get_redirect_url( $user->ID, 'login' );

		if ( $redirect ) {
			$redirect_to_calculated = $redirect;
		}

		return $redirect_to_calculated;
	}

	/**
	 * Redirect for login via Subway plugin.
	 *
	 * @param string  $redirect_url where to redirect.
	 * @param WP_User $user user object.
	 *
	 * @return string
	 */
	public function subway_login_redirect( $redirect_url, $user = null ) {

		if ( ! $user || is_wp_error( $user ) ) {
			return $redirect_url;
		}

		$redirect = $this->get_redirect_url( $user->ID, 'login' );

		if ( $redirect ) {
			$redirect_url = $redirect;
		}

		return $redirect_url;
	}

	/**
	 * Filter the redirect link for activation when using ajax register plugin.
	 *
	 * @param string $where url.
	 * @param int    $user_id user id.
	 *
	 * @return string
	 */
	public function ajax_reg_activation_redirect( $where, $user_id ) {

		$redirect = $this->get_redirect_url( $user_id, 'activation' );

		if ( $redirect ) {
			$where = $redirect;
		}

		return $where;
	}

	/**
	 * Implement GhostPool's idiotic redirect.
	 *
	 * @param string  $redirect redirect.
	 * @param WP_User $user user.
	 *
	 * @return string
	 */
	public function gp_redirect( $redirect, $user ) {

		$redirect_url = $this->get_redirect_url( $user->ID, 'login' );
		// if we have a redirect setup, let us update.
		if ( $redirect_url ) {
			$redirect = $redirect_url;
		}

		return $redirect;
	}

	/**
	 * Get the parsed redirect url.
	 *
	 * @param int    $user_id user id.
	 * @param string $type type.
	 *
	 * @return mixed|string
	 */
	private function get_redirect_url( $user_id, $type = 'login' ) {

		if ( ! $user_id ) {
			return '';
		}

		if ( 'login' == $type ) {
			return $this->get_login_redirection_url( $user_id );
		}
	}

	/**
	 * Get login redirection url.
	 *
	 * @param $user_id
	 *
	 * @return string
	 */
	private function get_login_redirection_url( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		$settings     = (array) get_option( 'bp_auto_activate_auto_login_settings', array() );
		$redirect_url = empty( $settings['login_redirect_url'] ) ? BPDev_Account_Auto_Activator::get_user_url( $user->ID ) : self::get_parsed_url( $user, $settings['login_redirect_url'] );

		if ( ! empty( $settings['enable_login_redirect_role'] ) ) {
			foreach ( $user->roles as $role ) {
				if ( ! empty( $settings[ 'enable_login_redirect_' . $role ] ) && ! empty( $settings[ 'login_redirect_url_' . $role ] ) ) {
					$redirect_url = self::get_parsed_url( $user, $settings[ 'login_redirect_url_' . $role ] );
					break;
				}
			}
		}

		return $redirect_url;
	}

	/**
	 * @param $user
	 * @param $url
	 *
	 * @return string
	 */
	public static function get_parsed_url( $user, $url ) {

		if ( ! $user ) {
			return '';
		}

		$user_id     = $user->ID;
		$member_type = bp_get_member_type( $user_id, true );
		$user_url    = BPDev_Account_Auto_Activator::get_user_url( $user_id );
		$user_name   = function_exists( 'bp_members_get_user_slug' ) ? bp_members_get_user_slug( $user_id ) : bp_core_get_username( $user_id, $user->user_nicename, $user->user_login );

		$map       = array(
			'[user_id]'          => $user->ID,
			'[user_login]'       => $user->user_login,
			'[user_nicename]'    => $user->user_nicename,
			'[username]'         => $user_name,
			'[user_profile_url]' => $user_url,
			'[user_url]'         => $user_url,
			'[site_url]'         => site_url( '/' ),
			'[network_url]'      => network_home_url( '/' ),
			'[member_type]'      => $member_type ? $member_type : '',
		);
		$tokens    = array_keys( $map );
		$replacers = array_values( $map );

		$url = str_replace( $tokens, $replacers, $url );

		return $url;
	}
}

// Boot.
BP_Auto_Activation_Auto_Login_Redirection_Manager::get_instance();
