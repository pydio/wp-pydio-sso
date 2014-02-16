<?php
/**
 * @author		Charles du Jeu <charles@ajaxplorer.info>
 * @copyright	Copyright (c) 2014, Charles du Jeu
 * @license		https://www.gnu.org/licenses/lgpl-2.1.html LGPLv2.1
 * @package		Pydio\WP_Pydio_SSO\Auth
 */

//avoid direct calls to this file
if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * @since	0.1.0
 */
class WP_Pydio_SSO_Auth {

	/**
	 * Holds a copy of the object for easy reference.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	private
	 * @var		object	$instance
	 */
	private static $instance;

	/**
	 * Getter method for retrieving the object instance.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	public
	 *
	 * @return	object	WPCollab_HelloEmoji_Admin::$instance
	 */
	public static function get_instance() {

		return self::$instance;

	} // END get_instance()

	/**
	 * Constructor. Hooks all interactions to initialize the class.
	 *
	 * @since	0.1.0
	 * @access	public
	 *
	 * @return	void
	 */
	public function __construct() {

		self::$instance = $this;

		$this->options = get_option( 'pydio_settings' );

		$this->glueCode = $this->options['install_path'] . '/plugins/auth.remote/glueCode.php';
		$this->glueCode_found = @is_file( $this->glueCode );

	} // END __construct()

	public function set_glue_globals( $type, $user = null, $bool = null ) {

		if ( ! $this->glueCode_found ) {
			return;
		}

		global $AJXP_GLUE_GLOBALS;

		$AJXP_GLUE_GLOBALS = array();

		switch ( $type ) {
			case 'authenticate':
				$AJXP_GLUE_GLOBALS['secret']			= $this->options['secret_key'];
				$AJXP_GLUE_GLOBALS['autoCreate']		= $this->options['auto_create'];
				$AJXP_GLUE_GLOBALS['plugInAction']		= 'login';
				$AJXP_GLUE_GLOBALS['login']				= array(
					'name'		=> $user,
					'password'	=> $user->user_pass,
					'roles'		=> $user->roles
				);
				break;
			case 'logout':
				$AJXP_GLUE_GLOBALS['secret'] 			= $this->options['secret_key'];
				$AJXP_GLUE_GLOBALS['plugInAction']		= 'logout';
				break;
			case 'create_user':
				$AJXP_GLUE_GLOBALS['user']				= array();
				$AJXP_GLUE_GLOBALS['user']['name']		= $user->user_login;
				$AJXP_GLUE_GLOBALS['user']['password']	= $user->user_pass;
				$AJXP_GLUE_GLOBALS['user']['right']		= ( is_super_admin( $user->ID ) ? 'admin' : '' ); // @todo
				$AJXP_GLUE_GLOBALS['user']['roles']		= $user->roles;
				$AJXP_GLUE_GLOBALS['plugInAction']		= ( $bool ? 'addUser' : 'updateUser' );
				break;
			case 'delete_user':
				$AJXP_GLUE_GLOBALS['secret']			= $this->options['secret_key'];
				$AJXP_GLUE_GLOBALS['userName']			= $user->user_login;
				$AJXP_GLUE_GLOBALS['plugInAction']		= "delUser";
				break;
		}

		include( $this->glueCode );

	} // END set_glue_globals()

	public function authenticate( $username ) {

		$this->set_glue_globals( 'authenticate', get_user_by( 'login', $username ) );

	} // END authenticate()

	public function logout() {

		$this->set_glue_globals( 'logout' );

	} // END logout()

	public function update_user_role( $user_id ) {

		$this->create_user( $user_id, false );

	} // END update_user_role()

	public function create_user( $user_id, $is_new = true ) {

		$this->set_glue_globals( 'create_user', get_userdata( $user_id ), $is_new );

	} // END create_user()

	public function delete_user( $user_id ) {

		$this->set_glue_globals( 'delete_user', get_userdata( $user_id ) );

	} // END delete_user()

} // END class WP_Pydio_SSO_Auth
