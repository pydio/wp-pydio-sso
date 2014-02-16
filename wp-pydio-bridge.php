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

// avoid direct calls to this file
defined( 'ABSPATH' ) or die();

define( 'AJXP_EXEC', true );

class WP_Pydio_Bridge 
{
	
	public $options;
	public $glueCode;
	public $glueCode_found;
	
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

	public function __construct() 
	{
		$this->options = get_option( 'pydio_settings' );
		$this->glueCode = $this->options['install_path'] . '/plugins/auth.remote/glueCode.php';
		$this->glueCode_found = @is_file( $this->glueCode );
		
		// Authentication
		add_action( 'wp_login', array( &$this, 'authenticate' ), 10, 2 );
		add_action( 'wp_logout', array( &$this, 'logout' ), 1 );
		add_action( 'user_register', array( &$this, 'create_user' ), 1, 1 );
		add_action( 'set_user_role', array( &$this, 'update_user_role' ), 1, 1 );
		add_action( 'delete_user', array( &$this, 'delete_user' ), 1, 1 );
		
		// Settings
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_head', array( &$this, 'admin_head' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'plugins_loaded', array( &$this, 'load_plugin_textdomain' ) );
		
		// Plugin Activation
		register_activation_hook( __FILE__, array( 'WP_Pydio_Bridge', 'activate_plugin' ) );
	}
	
	private function set_glue_globals( $authenticate, $user = null, $bool = null ) 
	{
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
	}

	public function authenticate( $username ) 
	{
		$this->set_glue_globals( 'authenticate', get_user_by( 'login', $username ) );
	}
	
	public function logout() 
	{
		$this->set_glue_globals( 'logout' );
	}

	public function update_user_role( $user_id ) 
	{
		$this->create_user( $user_id, false );
	}

	public function create_user( $user_id, $is_new = true ) 
	{
		$this->set_glue_globals( 'create_user', get_userdata( $user_id ), $is_new );
	}

	public function delete_user( $user_id ) 
	{
		$this->set_glue_globals( 'delete_user', get_userdata( $user_id ) );
	}
	
	public function get_defaults() 
	{
		$defaults = array(
			'install_path'	=> '',
			'secret_key'	=> '2iNmstt6J*BqvNa',
			'auto_create'	=> true,
		);
		
		$options = apply_filters( 'wp_pydio_bridge_defaults', $defaults );
		
		return $options;
	}
	
	/**
	 * Add a link to the admin bar to launch Pydio
	 * 
	 * @since	2.1
	 * @return	void
	 */

	public function admin_head() 
	{
		if ( ! empty( $this->options['install_path'] ) && current_user_can( apply_filters( 'wp_pydio_bridge_admin_bar_cap', 'activate_plugins' ) ) ) {
			
			echo "<style type='text/css'>#wp-admin-bar-pydio > a:before{ content:'\\f322' }</style>";
			
			add_action( 'wp_before_admin_bar_render', function() {
				global $wp_admin_bar;
				$pydio_path = explode( $_SERVER['SERVER_NAME'], $this->options['install_path'] );
				$wp_admin_bar->add_node( array(
					'id' => 'pydio',
					'title' => __( 'File Manager', 'wp-pydio-bridge' ),
					'href' => "http://" . $_SERVER['SERVER_NAME'] . $pydio_path[ 1 ],
				) );
			}, 99 );
		}
	}
	
	public function admin_menu() 
	{
		add_submenu_page(
			'options-general.php',
			__( 'Pydio Bridge Settings', 'wp-pydio-bridge' ),
			__( 'Pydio Bridge', 'wp-pydio-bridge' ),
			apply_filters( 'wp_pydio_bridge_settings_cap', 'activate_plugins' ), // @todo
			'wp-pydio-bridge',
			array( &$this, 'options_page' )
		);
	}

	public function options_page() 
	{
		ob_start();
			settings_fields( 'pydio_settings' );
			do_settings_sections( 'pydio_settings' );
			submit_button();
		$form_fields = ob_get_clean();
		
		echo '' .
		"<div class='wrap'>" .
			"<h2>" . __( 'Pydio Bridge Settings', 'wp-pydio-bridge' ) . "</h2>" .
			"<form action='options.php' method='post'>" .
				$form_fields .
			"</form>" .
		"</div>";
	}

	public function admin_init() 
	{
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
		
	}

	public function guess_pydio_path() 
	{
		return dirname( ABSPATH ) . '/pydio';
	}

	public function section_creds() 
	{
		echo "<p>" . __( 'Set the data to connect to your Pydio installation', 'wp-pydio-bridge' ) . "</p>";
	}
	
	public function plugin_setting_string() 
	{
		echo "<input id='plugin_text_string' name='pydio_settings[install_path]' size='70' type='text' value='{$this->options['install_path']}' />";
		echo '<p class="description">' . sprintf( __( 'Installation path. Enter here the full path to your installation on the server, i.e. the root folder containing ajaxplorer index.php file. Do not include slash at the end. May look like %s', 'wp-pydio-bridge' ), '<code>' . $this->guess_pydio_path() . '</code>') . '</p>';
	}
	
	public function plugin_secret_string() 
	{
    	echo "<input id='plugin_secret_string' name='pydio_settings[secret_key]' size='70' type='text' value='{$this->options['secret_key']}' />";
		echo '<p class="description">' . __( "must be the same as the AUTH_DRIVER 'SECRET' option in your Pydio configuration", 'wp-pydio-bridge' ) . '</p>';
	}
	
	public function section_options() 
	{
		echo "<p>" . __( 'Define how WordPress and Pydio should interact', 'wp-pydio-bridge' ) . "</p>";
	}
	
	public function plugin_autocreate_string() 
	{
		$value = $this->options['auto_create'];
		echo '' .
		"<fieldset>" .
			"<label title='" . __( 'Yes', 'wp-pydio-bridge' ) . "'>" .
				"<input type='radio' id='pydio_auto_create_true' value='1' name='pydio_settings[auto_create]' " . checked( $value, true, false ) . "/>" .
				"<span>" . __( 'Yes', 'wp-pydio-bridge' ) . "</span>" .
			"</label>" .
			"<br />" .
			"<label title='" . __( 'No', 'wp-pydio-bridge' ) . "'>" .
				"<input type='radio' id='pydio_auto_create_false' value='0' name='pydio_settings[auto_create]' " . checked( $value, false, false ) . "/>" .
				"<span>" . __( 'No', 'wp-pydio-bridge' ) . "</span>" .
			"</label>" .
			"<br />" .
			"<p class='description'>" . __( 'Create Pydio users when they login', 'wp-pydio-bridge' ) . "</p>" .
		"</fieldset>";
	}
	
	public function section_repo() 
	{
		$uploads = wp_upload_dir();
		$plugins = untrailingslashit( trailingslashit( str_replace( plugin_basename( __DIR__ ), '', plugin_dir_path( __FILE__ ) ) ) );
		echo '' .
		"<p>" . __( "Now that your Wordpress users can access Pydio, you have to create repositories in Pydio and let them access it. This is not automated at the moment, so you have to log in as 'admin' and create them manually from within Pydio. Set the repositories default rights at least at 'r' (read), so that the users can indeed access the repositories.", 'wp-pydio-bridge' ) . "</p>" .
		"<p>" . __( 'Repository creation will ask you to enter the path to your repository. Here are some wordpress-related paths you may want to explore using Pydio:', 'wp-pydio-bridge' ) . "</p>" .
		"<ul>" .
			"<li><code>" . get_theme_root() . "</code> : " . __( 'The wordpress themes', 'wp-pydio-bridge' ) . "</li>" .
			"<li><code>{$plugins}</code> : " . __( 'The wordpress plugins', 'wp-pydio-bridge' ) . "</li>" .
			"<li><code>{$uploads['basedir']}</code> : " . __( 'The media library', 'wp-pydio-bridge' ) . "</li>" .
		"</ul>" .
		"<p>" . __( 'Of course, repositories are not limited to these values, you can browse whatever part of you server', 'wp-pydio-bridge' ) . "</p>";
	}

	public function plugin_options_validate( $input ) 
	{
		$newinput = array();
		$newinput['install_path']	= trim( $input['install_path'] );
		$newinput['secret_key']		= trim( $input['secret_key'] );
		$install					= $newinput['install_path'];
		
		if ( substr( $install, strlen( $install ) - 1 ) == "/" ) {
			$newinput['install_path'] = substr( $install, 0, strlen( $install ) - 1 );
		}
		if ( ! is_dir( $newinput['install_path'] ) ) {
			//TO FIX : that notice do not work
			add_action( 'admin_notices', create_function( '', 'echo \'<div id="message" class="error fade"><p><strong>' . sprintf( __( 'The directory %s does not exist', 'wp-pydio-bridge' ), '<code>' . $newinput['install_path'] . '</code>' ) . '</strong></p></div>\';' ) );
			$newinput['install_path'] = "";
		}

		$newinput['auto_create'] = in_array( $input['auto_create'], array( '1', 1, 'true', true ) ) ? true : false;

		return $newinput;
	}
	
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

	public function load_plugin_textdomain()
	{
		load_plugin_textdomain(
			'wp-pydio-bridge',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}
	
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

	public function activate_plugin( $network_wide ) 
	{
		$defaults = self::get_defaults();
		
		if ( is_multisite() && ( true == $network_wide ) ) {
			
			global $wpdb;
			$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

			if ( $blogs ) {
				foreach ( $blogs as $blog ) {
					switch_to_blog( $blog['blog_id'] );
					add_option( 'pydio_settings', $defaults );
				}
				restore_current_blog();
			}
		} else {
			add_option( 'pydio_settings', $defaults );
		}
	}

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
