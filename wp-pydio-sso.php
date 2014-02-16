<?php
/*
Plugin Name: WP Pydio SSO
Plugin URI: http://pyd.io/
Description: Associate Pydio and WordPress users directly using WP as the master user database
Version: 2.0-alpha
Author: Charles du Jeu
Author URI: http://pyd.io/
Text Domain: wp-pydio-sso
Domain Path: /languages

	WP Pydio SSO
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
 * @package		Pydio\WP_Pydio_SSO
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
class WP_Pydio_SSO {

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
	public function __construct() {

		$this->options = get_option( 'pydio_settings' );
		$this->glueCode = $this->options['install_path'] . '/plugins/auth.remote/glueCode.php';
		$this->glueCode_found = @is_file( $this->glueCode );

		// Authentication
		add_action( 'wp_login',			array( $this, 'authenticate' ), 10, 2 );
		add_action( 'wp_logout',		array( $this, 'logout' ), 1 );
		add_action( 'user_register',	array( $this, 'createUser' ), 1, 1 );
		add_action( 'set_user_role',	array( $this, 'updateUserRole' ), 1, 1 );
		add_action( 'delete_user',		array( $this, 'deleteUser' ), 1, 1);

		// Settings
		add_action( 'admin_init',		array( $this, 'admin_init' ) );
		add_action( 'admin_head',		array( $this, 'admin_head' ) );
		add_action( 'admin_menu',		array( $this, 'admin_menu' ) );
		add_action( 'plugins_loaded',	array( $this, 'load_plugin_textdomain' ) );

		// Plugin Activation
		register_activation_hook( __FILE__, array( 'WP_Pydio_SSO', 'activate_plugin' ) );

	} // END __construct()

	public function get_defaults() {

		$defaults = array(
			'install_path'	=> '',
			'secret_key'	=> '',
			'auto_create'	=> true,
		);

		$options = apply_filters( 'wp_pydio_sso_defaults', $defaults );

		return $options;

	} // END get_defaults()

	public function set_glue_globals( $authenticate, $user = null, $bool = null ) {

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

	/**
	 * Add a link to the admin bar to launch Pydio
	 *
	 * @since	2.1
	 * @return	void
	 */
	public function admin_head() {

		if ( ! empty( $this->options['install_path'] ) && current_user_can( apply_filters( 'wp_pydio_bridge_admin_bar_cap', 'activate_plugins' ) ) ) {

			echo "<style type='text/css'>#wp-admin-bar-pydio > a:before{ content:'\\f322' }</style>";

			add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_switch' ), 99 );
		}

	} // END admin_head()

	public function admin_bar_switch() {

		global $wp_admin_bar;
		$pydio_path = explode( $_SERVER['SERVER_NAME'], $this->options['install_path'] );

		$wp_admin_bar->add_node(
			array(
				'id' => 'pydio',
				'title' => __( 'File Manager', 'wp-pydio-bridge' ),
				'href' => 'http://' . $_SERVER['SERVER_NAME'] . $pydio_path[1],
			)
		);

	} // END admin_bar_switch()

	public function admin_menu() {

		add_submenu_page(
			'options-general.php',
			__( 'Pydio SSO Settings', 'wp-pydio-sso' ),
			__( 'Pydio SSO', 'wp-pydio-sso' ),
			apply_filters( 'wp_pydio_sso_settings_cap', 'activate_plugins' ), // @todo
			'wp-pydio-sso',
			array( $this, 'options_page' )
		);

	} // END admin_menu()

	public function options_page() { ?>

		<div class="wrap">
			<h2><?php _e( 'Pydio SSO Settings', 'wp-pydio-sso' ); ?></h2>
			<form action="options.php" method="post">
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
			array( $this, 'plugin_options_validate' )
		);

		add_settings_section(
			'pydio_settings_creds',
			__( 'Credentials', 'wp-pydio-sso' ),
			array( $this, 'section_creds' ),
			'pydio_settings'
		);

		add_settings_field(
			'pydio_text_string',
			__( 'Pydio Path', 'wp-pydio-sso' ),
			array( $this, 'plugin_setting_string' ),
			'pydio_settings',
			'pydio_settings_creds'
		);

		add_settings_field(
			'pydio_secret_string',
			__( 'Secret Key', 'wp-pydio-sso' ),
			array( $this, 'plugin_secret_string' ),
			'pydio_settings',
			'pydio_settings_creds'
		);

		add_settings_section(
			'pydio_settings_options',
			__( 'Options', 'wp-pydio-sso' ),
			array( $this, 'section_options' ),
			'pydio_settings'
		);

		add_settings_field(
			'pydio_autocreate_string',
			__( 'Auto Create', 'wp-pydio-sso' ),
			array( $this, 'plugin_autocreate_string' ),
			'pydio_settings',
			'pydio_settings_options'
		);

		add_settings_section(
			'pydio_settings_repo',
			__( 'Creating Pydio Repositories', 'wp-pydio-sso' ),
			array( $this, 'section_repo' ),
			'pydio_settings'
		);

	} // END admin_init()

	public function guess_pydio_path() {

		//get WP abs path
		$path = dirname( ABSPATH ) . '/pydio';

		return $path;

	} // END guess_pydio_path()

	public function section_creds() {

		echo "<p>" . __( 'Set the data to connect to your Pydio installation', 'wp-pydio-sso' ) . "</p>";

	} // END install_section_text()

	public function section_options() {

		echo "<p>" . __( 'Define how WordPress and Pydio should interact', 'wp-pydio-sso' ) . "</p>";

	} // END options_section_text()

	public function section_repo() {

		$uploads = wp_upload_dir();
		?>
		<p>
			<?php _e( "Now that your Wordpress users can access Pydio, you have to create repositories in Pydio and let them access it. This is not automated at the moment, so you have to log in as 'admin' and create them manually from within Pydio. Set the repositories default rights at least at 'r' (read), so that the users can indeed access the repositories.", 'wp-pydio-sso' ); ?>
		</p>
		<p>
			<?php _e( 'Repository creation will ask you to enter the path to your repository. Here are some wordpress-related paths you may want to explore using Pydio :', 'wp-pydio-sso' ); ?>
			<ul>
				<li><code>.<?php echo get_theme_root(); ?></code> : <?php _e( 'The wordpress themes contents', 'wp-pydio-sso' ); ?><li>
				<li><code>.<?php echo WP_PLUGIN_DIR; ?></code> : <?php _e( 'The wordpress plugins', 'wp-pydio-sso' ); ?><li>
				<li><code>.<?php echo $uploads['basedir']; ?></code> : <?php _e( 'The media library', 'wp-pydio-sso' ); ?><li>
			</ul>
			<?php _e( 'Of course, repositories are not limited to these values, you can browse whatever part of you server', 'wp-pydio-sso' ); ?>
		</p>
		<?php
	} // END repo_section_text()

	public function plugin_setting_string() {

		echo "<input id='plugin_text_string' name='pydio_settings[install_path]' size='70' type='text' value='{$this->options['install_path']}' />";
		echo '<p class="description">' . sprintf( __( 'Installation path. Enter here the full path to your installation on the server, i.e. the root folder containing ajaxplorer index.php file. Do not include slash at the end. May look like %s', 'wp-pydio-sso' ), '<code>' . $this->guess_pydio_path() . '</code>' ) . '</p>';

	} // END plugin_setting_string()

	public function plugin_secret_string() {

		echo "<input id='plugin_secret_string' name='pydio_settings[secret_key]' size='70' type='text' value='{$this->options['secret_key']}' />";
		echo '<p class="description">' . __( "must be the same as the AUTH_DRIVER 'SECRET' option in your Pydio configuration", 'wp-pydio-sso' ) . '</p>';

	} // END plugin_secret_string()

	public function plugin_autocreate_string() {

		$value = $this->options['auto_create'];
		?>
		<fieldset>
			<label title="<?php _e( 'Yes', 'wp-pydio-sso' ); ?>">
				<input type="radio" id="pydio_auto_create_true" value="1" name="pydio_settings[auto_create]" <?php checked( $value, true, false ); ?>/>
				<span><?php _e( 'Yes', 'wp-pydio-sso' ); ?></span>
			</label>
			<br>
			<label title="<?php _e( 'No', 'wp-pydio-sso' ); ?>">
				<input type="radio" id="pydio_auto_create_false" value="0" name="pydio_settings[auto_create]" <?php checked( $value, true, false ); ?>/>
				<span><?php _e( 'No', 'wp-pydio-sso' ); ?></span>
			</label>
			<p class="description"><?php _e( 'Create Pydio users when they login', 'wp-pydio-sso' ); ?></p>
		</fieldset>

		<?php
	} // END plugin_autocreate_string()

	public function plugin_options_validate( $input ) {

		$newinput					= array();
		$newinput['install_path']	= trim( $input['install_path'] );
		$newinput['secret_key']		= trim( $input['secret_key'] );
		$install					= $newinput['install_path'];

		if ( substr( $install, strlen( $install ) - 1 ) == "/" ) {
			$newinput['install_path'] = substr( $install, 0, strlen( $install ) - 1 );
		}
		if ( !is_dir( $newinput['ajxp_install_path'] ) ) {
			//TO FIX : that notice do not work
			add_action('admin_notices', create_function('', 'echo \'<div id="message" class="error fade"><p><strong>' . sprintf( __( 'The directory %s do not exists', 'wp-pydio-sso' ), '<code>' . $newinput['install_path'] . '</code>') . '</strong></p></div>\';'));
			$newinput['install_path'] = "";
		}

		$newinput['auto_create'] = in_array( $input['auto_create'], array( '1', 1, 'true', true ) ) ? true : false;

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
			'wp-pydio-sso',
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

} // END class WP_Pydio_SSO

/**
 * Instantiate the main class
 *
 * @since	1.0.0
 * @access	public
 *
 * @var	object	$wp_pydio_sso holds the instantiated class {@uses WP_Pydio_SSO}
 */
$wp_pydio_sso = new WP_Pydio_SSO();
