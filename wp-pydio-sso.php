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

/** Register autoloader */
spl_autoload_register( 'WP_Pydio_SSO::autoload' );

define( 'AJXP_EXEC', true );

/**
 * Main class to run the plugin
 *
 * @since	1.0.0
 */
class WP_Pydio_SSO {

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
	 * Current version of the plugin.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	public
	 * @var		string	$version
	 */
	public static $version = '2.0-alpha';

	public	$options,
			$glueCode,
			$glueCode_found;

	/**
	 * Holds a copy of the main plugin filepath.
	 *
	 * @since	0.1.0
	 * @access	private
	 * @var		string	$file
	 */
	private static $file = __FILE__;

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

		self::$instance = $this;

		$this->options = get_option( 'pydio_settings' );

		new WP_Pydio_SSO_Auth;
		add_action( 'plugins_loaded',	array( $this, 'load_plugin_textdomain' ) );

		if ( is_admin() ) {

			new WP_Pydio_SSO_Admin();

		}

		if ( is_admin_bar_showing() ) {

			add_action( 'admin_head',	array( $this, 'admin_head' ) );

		}

		// Plugin Activation
		register_activation_hook( __FILE__, array( 'WP_Pydio_SSO', 'activate_plugin' ) );

	} // END __construct()

	/**
	 * Autoloader to load classes as needed.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	public
	 *
	 * @param	string	$classname	The name of the class
	 * @return	null	Return early if the class name does not start with the correct prefix
	 */
	public static function autoload( $classname ) {

		if ( stripos( $classname, 'WP_Pydio_SSO_' ) !== false ) {

			$class_name = trim( $classname, 'WP_Pydio_SSO_' );
			$file_path = __DIR__ . '/classes/class-' . strtolower( $class_name ) . '.php';

			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}

		} else {

			return;

		}

	} // END autoload()

	public function get_defaults() {

		$defaults = array(
			'install_path'	=> '',
			'secret_key'	=> '',
			'auto_create'	=> true,
		);

		$options = apply_filters( 'wp_pydio_sso_defaults', $defaults );

		return $options;

	} // END get_defaults()

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

	/**
	 * Getter method for retrieving the object instance.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	public
	 *
	 * @return	object	WPCollab_HelloEmoji::$instance
	 */
	public static function get_instance() {

		return self::$instance;

	} // END get_instance()

	/**
	 * Getter method for retrieving the main plugin filepath.
	 *
	 * @since	0.1.0
	 * @static
	 * @access	public
	 *
	 * @return	string	self::$file
	 */
	public static function get_file() {

		return self::$file;

	} // END get_file()

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
