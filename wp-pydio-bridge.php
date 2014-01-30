<?php
/*
Plugin Name: WP Pydio Bridge
Plugin URI: http://pyd.io/
Description: Associate Pydio and WordPress users directly using WP as the master user database
Version: 2.0-alpha
Author: Charles du Jeu
Author URI: http://pyd.io/
Text Domain: wp-pydio-bridge
Domain Path: /languages

	WP Pydio Bridge
	Copyright (C) 2014  Charles du Jeu (http://pyd.io/)

	This library is free software; you can redistribute it and/or
	modify it under the terms of the GNU Lesser General Public
	License as published by the Free Software Foundation; either
	version 2.1 of the License, or (at your option) any later version.

	This library is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public
	License along with this library; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/
/**
 * @author		Charles du Jeu <charles@ajaxplorer.info>
 * @copyright	Copyright (c) 2014, Charles du Jeu
 * @license		https://www.gnu.org/licenses/lgpl-2.1.html LGPLv2.1
 * @package		Pydio\WP_Pydio_Bridge
 * @version		2.0
 */

//avoid direct calls to this file
if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

define( 'AJXP_EXEC', true );

/**
 * Main class to run the plugin
 * 
 * @since	1.0.0
 */
class WP_Pydio_Bridge {
	
	public $options;
	public $glueCode;
	public $glueCodeFound;
	
	/**
	 * Constructor. Hooks all interactions to initialize the class.
	 * 
	 * @since	1.0.0
	 * @access	public
	 * 
	 * @see	get_option()
	 * @see	add_action()
	 * @see	register_activation_hook()
	 * 
	 * @return	void
	 */
	public function __construct() {

		$this->options = get_option( 'pydio_settings' );
		$this->glueCode = $this->options['install_path'] . '/plugins/auth.remote/glueCode.php';
		$this->glueCodeFound = @is_file( $this->glueCode );
		$this->autoCreate = $this->options['auto_create'];

		add_action( 'wp_login',			array( &$this, 'authenticate' ),	10, 2 );
		add_action( 'wp_logout',		array( &$this, 'logout' ),			1 );
		add_action( 'user_register',	array( &$this, 'createUser' ),		1, 1 );
		add_action( 'set_user_role',	array( &$this, 'updateUserRole' ),	1, 1 );
		add_action( 'delete_user',		array( &$this, 'deleteUser' ),		1, 1);
		add_action( 'admin_init',		array( &$this, 'admin_init' ) );
		add_action( 'admin_menu',		array( &$this, 'admin_menu' ) );
		add_action( 'plugins_loaded',	array( &$this, 'load_plugin_textdomain' ) );
		
		register_activation_hook( __FILE__, array( 'WP_Pydio_Bridge', 'activate_plugin' ) );

	} // END __construct()
	
	public function get_defaults() {
		
		$defaults = array(
			'install_path'	=> '/somepath/folder/', // @todo clear this!!
			'secret_key'	=> 'sdafsdfasdfdd',
			'auto_create'	=> true,
		);
		
		$options = apply_filters( 'wp_pydio_bridge_defaults', $defaults );
		
		return $options;
	}

	public function authenticate( $username ) {
		
		if( !$this->glueCodeFound ) {
			return;
		}
		
		$userdata = get_user_by( 'login', $username );

		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		//$plugInAction, $login, $result, $secret, $autoCreate;
		$AJXP_GLUE_GLOBALS['secret'] = $this->options['secret_key'];
		$AJXP_GLUE_GLOBALS['autoCreate'] = $this->options['auto_create'];
		$AJXP_GLUE_GLOBALS['plugInAction'] = 'login';
		$AJXP_GLUE_GLOBALS['login'] = array(
			'name' => $username,
			'password' => $userdata->user_pass,
			'roles' => $userdata->roles
		);
		
		include( $this->glueCode );
		
	} // END authenticate()
	
	 public function logout() {

		global $plugInAction;

		if ( !$this->glueCodeFound ) {
			return;
		}

		global $AJXP_GLUE_GLOBALS;

		$AJXP_GLUE_GLOBALS					= array();
		$AJXP_GLUE_GLOBALS['secret']		= $this->options['secret_key'];
		$AJXP_GLUE_GLOBALS['plugInAction']	= 'logout';
		
		include( $this->glueCode );
		
	} // END logout()

	public function updateUserRole($userId) {

		$this->createUser( $userId, false );
		
	} // END updateUserRole()

	public function createUser( $userId, $isNew = true ) {

		if ( !$this->glueCodeFound ) {
			return;
		}

		$userData = get_userdata( $userId );
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		//global $plugInAction, $result, $secret, $user;
		$AJXP_GLUE_GLOBALS['secret']			= $this->options['secret_key'];
		$AJXP_GLUE_GLOBALS['user']				= array();
		$AJXP_GLUE_GLOBALS['user']['name']		= $userData->user_login;
		$AJXP_GLUE_GLOBALS['user']['password']	= $userData->user_pass;
		$AJXP_GLUE_GLOBALS['user']['right']		= ( is_super_admin( $userId ) ? 'admin' : '' ); // @todo
		$AJXP_GLUE_GLOBALS['user']['roles']		= $userData->roles;
		$AJXP_GLUE_GLOBALS['plugInAction']		= ( $isNew ? 'addUser' : 'updateUser' );

		include( $this->glueCode );
		
	} // END createUser()

	public function deleteUser( $userId ) {

		if ( !$this->glueCodeFound ) {
			return;
		}

		$userData = get_userdata( $userId );
		global $AJXP_GLUE_GLOBALS;
		$AJXP_GLUE_GLOBALS = array();
		//global $plugInAction, $result, $secret, $userName;
		$AJXP_GLUE_GLOBALS['secret']		= $this->options['secret_key'];
		$AJXP_GLUE_GLOBALS['userName']		= $userData->user_login;
		$AJXP_GLUE_GLOBALS['plugInAction']	= "delUser";
		
		include( $this->glueCode );
		
	} // END deleteUser()

	public function admin_menu() {

		add_submenu_page(
			'options-general.php',
			__( 'Pydio Bridge Settings', 'wp-pydio-bridge' ),
			__( 'Pydio Bridge', 'wp-pydio-bridge' ),
			apply_filters( 'wp_pydio_bridge_settings_cap', 'activate_plugins' ), // @todo
			'wp-pydio-bridge',
			array( &$this, 'options_page' )
		);
		
	} // END admin_menu()

	public function options_page() { ?>

		<div class="wrap">
			<h2><?php _e( 'Pydio Bridge Settings', 'wp-pydio-bridge' ); ?></h2>	
			<form action="" method="post">
			<?php
				settings_fields( 'pydio_settings_nonce' );
				do_settings_sections( 'pydio_settings' );
				submit_button();
			?>
			</form>
		</div>

		<?php
	} // END options_page()

	public function admin_init() {

		register_setting(
			'pydio_settings',
			'pydio_settings',
			array( &$this, 'plugin_options_validate' )
		);
		
		add_settings_section(
			'pydio_settings_creds',
			__( 'Credentials', 'wp-pydio-bridge' ),
			array( &$this, 'section_creds' ),
			'pydio_settings'
		);
		
		add_settings_field(
			'pydio_text_string',
			__( 'Pydio Path', 'wp-pydio-bridge' ),
			array( &$this, 'plugin_setting_string' ),
			'pydio_settings',
			'pydio_settings_creds'
		);
		
		add_settings_field(
			'pydio_secret_string',
			__( 'Secret Key', 'wp-pydio-bridge' ),
			array( &$this, 'plugin_secret_string' ),
			'pydio_settings',
			'pydio_settings_creds'
		);
		
		add_settings_section(
			'pydio_settings_options',
			__( 'Options', 'wp-pydio-bridge' ),
			array( &$this, 'section_options' ),
			'pydio_settings'
		);
		
		add_settings_field(
			'pydio_autocreate_string',
			__( 'Auto Create', 'wp-pydio-bridge' ),
			array( &$this, 'plugin_autocreate_string' ),
			'pydio_settings',
			'pydio_settings_options'
		);
		
		add_settings_section(
			'pydio_settings_repo',
			__( 'Creating Pydio Repositories', 'wp-pydio-bridge' ),
			array( &$this, 'section_repo' ),
			'pydio_settings'
		);
		
	} // END admin_init()

	public function guess_pydio_path() {

		//get WP abs path
		$wp_abspath = ABSPATH;
		$url = dirname( $wp_abspath );

		return $url . '/pydio';
		
	} // END guess_pydio_path()

	public function section_creds() { ?>
		
		<p>
			<?php _e( 'Set the data to connect to your Pydio installation', 'wp-pydio-bridge' ); ?>
		</p>
	
	<?php
	} // END install_section_text()
	
	public function section_options() { ?>
		
		<p>
			<?php _e( 'Define how WordPress and Pydio should interact', 'wp-pydio-bridge' ); ?>
		</p>
	
	<?php
	} // END options_section_text()

	public function section_repo() {
		
		$installPath = str_replace( "\\", "/", dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
		?>
		<p>
			<?php _e( "Now that your Wordpress users can access Pydio, you have to create repositories in Pydio and let them access it. This is not automated at the moment, so you have to log in as 'admin' and create them manually from within Pydio. Set the repositories default rights at least at 'r' (read), so that the users can indeed access the repositories.", 'wp-pydio-bridge' ); ?>
		</p>
		<p>
			<?php _e( 'Repository creation will ask you to enter the path to your repository. Here are some wordpress-related paths you may want to explore using Pydio :', 'wp-pydio-bridge' ); ?>
			<ul>
				<li><code>.<?php echo $installPath; ?>/wp-content/themes</code> : <?php _e( 'The wordpress themes contents', 'wp-pydio-bridge' ); ?><li>
				<li><code>.<?php echo $installPath; ?>/wp-content/plugins</code> : <?php _e( 'The wordpress plugins', 'wp-pydio-bridge' ); ?><li>
				<li><code>.<?php echo $installPath . '/' . get_option( 'upload_path' ); ?></code> : <?php _e( 'The media library', 'wp-pydio-bridge' ); ?><li>
			</ul>
			<?php _e( 'Of course, repositories are not limited to these values, you can browse whatever part of you server', 'wp-pydio-bridge' ); ?>
		</p>
		<?php
	} // END repo_section_text()

	public function plugin_setting_string() {
		
		echo '<input id="plugin_text_string" name="wp_ajxp_options[ajxp_install_path]" size="70" type="text" value="' . $this->options['install_path'] . '" />';
		echo '<p class="description">' . sprintf( __( 'Installation path. Enter here the full path to your installation on the server, i.e. the root folder containing ajaxplorer index.php file. Do not include slash at the end. May look like %s', 'wp-pydio-bridge' ), '<code>' . $this->guess_pydio_path() . '</code>') . '</p>';
		
	} // END plugin_setting_string()

	public function plugin_secret_string() {
		
		echo '<input id="plugin_secret_string" name="wp_ajxp_options[ajxp_secret_key]" size="70" type="text" value="' . $this->options['secret_key'] . '" />';
		echo '<p class="description">' . __( "must be the same as the AUTH_DRIVER 'SECRET' option in your Pydio configuration", 'wp-pydio-bridge' ) . '</p>';
		
	} // END plugin_secret_string()

	public function plugin_autocreate_string() {
		
		$value = $this->options['auto_create'];
		$true = ( true == $value );
		$false = ( true != $value );
		?>
		<fieldset>
			<label title="<?php _e( 'Yes', 'wp-pydio-bridge' ); ?>">
				<input type="radio" id="pydio_auto_create_true" value="true" name="pydio_settings[auto_create]" <?php checked( $true ); ?>>
				<span><?php _e( 'Yes', 'wp-pydio-bridge' ); ?></span>
			</label>
			<br>
			<label title="<?php _e( 'No', 'wp-pydio-bridge' ); ?>">
				<input type="radio" id="pydio_auto_create_false" value="false" name="pydio_settings[auto_create]" <?php checked( $false ); ?>>
				<span><?php _e( 'No', 'wp-pydio-bridge' ); ?></span>
			</label>
			<p class="description"><?php _e( 'Create Pydio users when they login', 'wp-pydio-bridge' ); ?></p>
		</fieldset>
		
		<?php	
	} // END plugin_autocreate_string()

	public function plugin_options_validate( $input ) {
		
		$newinput = array();
		$newinput['install_path']	= trim( $input['install_path'] );
		$newinput['secret_key']		= trim( $input['secret_key'] );
		$install					= $newinput['install_path'];
		
		if ( substr( $install, strlen( $install ) - 1 ) == "/" ) {
			$newinput['install_path'] = substr( $install, 0, strlen( $install ) - 1 );
		}
		if ( !is_dir( $newinput['ajxp_install_path'] ) ) {
			//TO FIX : that notice do not work
			add_action('admin_notices', create_function('', 'echo \'<div id="message" class="error fade"><p><strong>' . sprintf( __( 'The directory %s do not exists', 'wp-pydio-bridge' ), '<code>' . $newinput['install_path'] . '</code>') . '</strong></p></div>\';'));
			$newinput['install_path'] = "";
		}

		$newinput['auto_create'] = $input['auto_create'];

		return $newinput;
		
	} // END plugin_options_validate()
	
	/**
	 * Load the plugin's textdomain hooked to 'plugins_loaded'.
	 * 
	 * @since	2.0.0
	 * @access	public
	 * 
	 * @see		load_plugin_textdomain()
	 * @see		plugin_basename()
	 * @action	plugins_loaded
	 * 
	 * @return	void
	 */
	public function load_plugin_textdomain() {
		
		load_plugin_textdomain(
			'wp-pydio-bridge',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
		
	} // END load_plugin_textdomain()
	
	/**
	 * Fired when plugin is activated
	 * 
	 * @since	2.0.0
	 * @access	public
	 * 
	 * @action	register_activation_hook
	 * 
	 * @param	bool	$network_wide TRUE if WPMU 'super admin' uses Network Activate option
	 * @return	void
	 */
	public function activate_plugin( $network_wide ) {
		
		$defaults = self::get_defaults();
		
		if ( is_multisite() && ( true == $network_wide ) ) {
			
			global $wpdb;
			$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

			if ( $blogs ) {
				foreach( $blogs as $blog ) {
					switch_to_blog( $blog['blog_id'] );
					add_option( 'pydio_settings', $defaults );
				}
				restore_current_blog();
			}
			
		} else {

			add_option( 'pydio_settings', $defaults );
			
		}
	} // END activate_plugin()

} // END class WP_Pydio_Bridge

/**
 * Instantiate the main class
 * 
 * @since	1.0.0
 * @access	public
 * 
 * @var	object	$wp_pydio_bridge holds the instantiated class {@uses WP_Pydio_Bridge}
 */
$wp_pydio_bridge = new WP_Pydio_Bridge();
