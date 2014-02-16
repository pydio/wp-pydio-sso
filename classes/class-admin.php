<?php
/**
 * @author		Charles du Jeu <charles@ajaxplorer.info>
 * @copyright	Copyright (c) 2014, Charles du Jeu
 * @license		https://www.gnu.org/licenses/lgpl-2.1.html LGPLv2.1
 * @package		Pydio\WP_Pydio_SSO\Admin
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
class WP_Pydio_SSO_Admin {

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

		add_action( 'admin_init',	array( $this, 'admin_init' ) );
		add_action( 'admin_menu',	array( $this, 'admin_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename ( WP_Pydio_SSO::get_file() ), array( $this, 'add_action_links' ) );

	} // END __construct()

	public function admin_menu() {

		add_submenu_page(
			'options-general.php',
			__( 'Pydio Settings', 'wp-pydio-sso' ),
			__( 'Pydio', 'wp-pydio-sso' ),
			apply_filters( 'wp_pydio_sso_settings_cap', 'activate_plugins' ), // @todo
			'wp-pydio',
			array( $this, 'options_page' )
		);

	} // END admin_menu()

	/**
	 * @todo generate tabs from array
	 * 
	 * @return void
	 */
	public function options_page() { ?>

		<div class="wrap">
			<h2><?php _e( 'Pydio Settings', 'wp-pydio-sso' ); ?></h2>

			<h3 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-active" href="#"><?php _e( 'Single sign-on', 'wp-pydio-sso' ); ?></a>
			</h3><!-- .nav-tab-wrapper -->

			<form action="options.php" method="post">
			<?php
				settings_fields( 'pydio_settings' );
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

	} // END plugin_autocreate_string()

	public function plugin_options_validate( $input ) {

		$newinput					= array();
		$newinput['install_path']	= trim( $input['install_path'] );
		$newinput['secret_key']		= trim( $input['secret_key'] );
		$install					= $newinput['install_path'];

		if ( substr( $install, strlen( $install ) - 1 ) == "/" ) {
			$newinput['install_path'] = substr( $install, 0, strlen( $install ) - 1 );
		}
		if ( ! is_dir( $newinput['install_path'] ) ) {
			//TO FIX : that notice do not work
			add_action( 'admin_notices', create_function( '', 'echo \'<div id="message" class="error fade"><p><strong>' . sprintf( __( 'The directory %s does not exist', 'wp-pydio-sso' ), '<code>' . $newinput['install_path'] . '</code>' ) . '</strong></p></div>\';' ) );
			$newinput['install_path'] = "";
		}

		$newinput['auto_create'] = in_array( $input['auto_create'], array( '1', 1, 'true', true ) ) ? true : false;

		return $newinput;

	} // END plugin_options_validate()

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    0.1.0
	 * @access   public
	 *
	 * @see      admin_url()
	 *
	 * @param    array $links Array of links
	 * @return   array Array of links
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . add_query_arg( 'page', 'wp-pydio', admin_url( 'options-general.php' ) ) . '">' . __( 'Settings' ) . '</a>'
			),
			$links
		);

	} // END add_action_links()

} // END class WP_Pydio_SSO_Admin
