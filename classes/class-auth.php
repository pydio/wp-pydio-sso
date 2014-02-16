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

	} // END __construct()

} // END class WP_Pydio_SSO_Auth
