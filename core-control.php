<?php
/*
 * Plugin Name: Core Control
 * Version: 1.3.0
 * Plugin URI: https://dd32.id.au/wordpress-plugins/core-control/
 * Description: Core Control is a set of plugin modules which can be used to control certain aspects of the WordPress control.
 * Author: Dion Hulse
 * Author URI: https://dd32.id.au/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Core_Control {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since 1.3.0
	 * @access public
	 * @var string $version Plugin version.
	 */
	public $version = '1.3.0';

	/**
	 * Plugin file.
	 *
	 * @since 1.3.0
	 * @access public
	 * @var string $file PHP File constant for main file.
	 */
	public $file = __FILE__;
	
	/**
	 * Primary class constructor.
	 *
	 * @since 1.3.0
	 * @access public
	 *
	 * @global $wp_version The version of WordPress installed.
	 */
	public function __construct() {
		global $wp_version;

		// Define constants
		$this->define_globals();

		// Load translations
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Detect non-supported WordPress versions and return early
		if ( version_compare( $wp_version, '3.2', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wp_notice' ) );
			return;
		}

		// Detect non-supported PHP versions and return early
		if ( version_compare( PHP_VERSION, '5.2.4', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_notice' ) );
			return;
		}

		// Add menu item
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		register_activation_hook(__FILE__, array( $this, 'activate'));

		//Add actions/filters
		add_action('admin_post_core_control-modules', array( $this, 'handle_posts'));

		//Add page
		add_action('core_control-default', array( $this, 'default_page'));

	}

	/**
	 * Define Core Control constants.
	 *
	 * This function defines all of the Core Control PHP constants.
	 *
	 * @since 1.3.0
	 * @access private
	 *
	 * @return void
	 */
	private function define_globals() {

		if ( ! defined( 'CORE_CONTROL_VERSION' ) ) {
			define( 'CORE_CONTROL_VERSION', $this->version );
		}

		if ( ! defined( 'CORE_CONTROL_PLUGIN_FILE' ) ) {
			define( 'CORE_CONTROL_PLUGIN_FILE', $this->file );
		}

		if ( ! defined( 'CORE_CONTROL_PLUGIN_DIR' ) ) {
			define( 'CORE_CONTROL_PLUGIN_DIR', plugin_dir_path( $this->file )  );
		}

		if ( ! defined( 'CORE_CONTROL_PLUGIN_URL' ) ) {
			define( 'CORE_CONTROL_PLUGIN_URL', plugin_dir_url( $this->file )  );
		}
	}

	/**
	 * Loads the plugin textdomain for translation.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {

		// Traditional WordPress plugin locale filter.
		$cc_locale  = apply_filters( 'plugin_locale',  get_locale(), 'core-control' );
		$cc_mofile  = sprintf( '%1$s-%2$s.mo', 'core-control', $cc_locale ); 
	
		// Look for wp-content/languages/core-control/core-control-{lang}_{country}.mo
		$cc_mofile1 = WP_LANG_DIR . '/core-control/' . $cc_mofile;

		// Look in wp-content/languages/plugins/core-control/core-control-{lang}_{country}.mo
		$cc_mofile2 = WP_LANG_DIR . '/plugins/core-control/' . $cc_mofile;

		// Look in wp-content/languages/plugins/core-control-{lang}_{country}.mo
		$cc_mofile3 = WP_LANG_DIR . '/plugins/' . $cc_mofile;

		// Look in wp-content/plugins/core-control/languages/core-control-{lang}_{country}.mo
		$cc_mofile4 = dirname( plugin_basename( CORE_CONTROL_PLUGIN_FILE ) ) . '/languages/';
		$cc_mofile4 = apply_filters( 'core_control_languages_directory', $cc_mofile4 );

		if ( file_exists( $cc_mofile1 ) ) {
			load_textdomain( 'core-control', $cc_mofile1 );
		} elseif ( file_exists( $cc_mofile2 ) ) {
			load_textdomain( 'core-control', $cc_mofile2 );
		} elseif ( file_exists( $cc_mofile3 ) ) {
			load_textdomain( 'core-control', $cc_mofile3 );
		} else {
			load_plugin_textdomain( 'core-control', false, $cc_mofile4 );
		}
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * Adds the main page for Core Control to the menu as a submenu of Tools.php.
	 * This page contains settings to turn on each available module.
	 *
	 * @since 1.3.0
	 * @access public
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page( 'tools.php', __( 'Core Control', 'core-control' ), __( 'Core Control', 'core-control' ), 'manage_options', 'core-control', array( $this, 'settings_page' ) );
	}

	/**
	 * Array of installed modules.
	 *
	 * Returns an array keyed by the module filename (for backwards compat reasons) of all installed modules.
	 * The array contains the id, title (translated) and description (translated) of the module.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return array Array of installed modules.
	 */
	public function get_modules() {
		$modules = array();

		// Cron Module
		$modules['core_control_cron.php'] = array(
			'id' 		  => 'cron',
			'title' 	  => __( 'Cron Module', 'core-control' ),
			'description' => __( 'This allows you to manually run WordPress Cron Jobs and to diagnose Cron issues.' , 'core-control' ),
		);

		// Filesystem Module
		$modules['core_control_filesystem.php'] = array(
			'id' 		  => 'filesystem',
			'title' 	  => __( 'Filesystem Module', 'core-control' ),
			'description' => __( 'This allows you to Enable/Disable the different Filesystem access methods which WordPress supports for upgrades.' , 'core-control' ),
		);

		// HTTP Module
		$modules['core_control_http.php'] = array(
			'id' 		  => 'http',
			'title' 	  => __( 'HTTP Module', 'core-control' ),
			'description' => __( 'This allows you to Enable/Disable the different HTTP Access methods which WordPress 3.2+ supports.' , 'core-control' ),
		);

		// HTTP Log Module
		$modules['core_control_http_log.php'] = array(
			'id' 		  => 'httplog',
			'title' 	  => __( 'HTTP Log Module', 'core-control' ),
			'description' => __( 'This allows you to Log external connections which WordPress makes.' , 'core-control' ),
		);

		// Updates Module
		$modules['core_control_updates.php'] = array(
			'id' 		  => 'updates',
			'title' 	  => __( 'Updates Module', 'core-control' ),
			'description' => __( 'This allows you to Disable Plugin/Theme/Core update checking, or to force a check to take place.' , 'core-control' ),
		);

		$modules = apply_filters( 'core_control_get_modules', $modules );
		return $modules;
	}

	/**
	 * Array of active modules.
	 *
	 * Returns an array keyed by the module filename (for backwards compat reasons) of all active modules.
	 * The array contains the id, title (translated) and description (translated) of the module.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return array Array of active modules.
	 */
	public function get_active_modules() {
		$saved_modules = get_option( 'core_control-active_modules', array() );
		$modules = $this->get_modules();

		$active_modules = array();
		if ( ! empty( $saved_modules ) && is_array( $saved_modules ) && in_array( ) ) {
			foreach( $saved_modules as $module_filename ) {
				if ( in_array( $module_filename, $modules ) ) {
					$active_modules[ $module_filename ] = $modules[ $module_filename ];
				}
			}
		}
		return $active_modules;
	}

	/**
	 * Is module active.
	 *
	 * Takes in the filename of a module and returns a boolean of if it's active.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @param  string Filename of module.
	 * @return bool   Is module active.
	 */
	public function is_module_active( $module ) {
		if ( ! empty ( $this->get_active_modules() ) ) {
			return in_array( $module, $this->get_active_modules() );
		} else {
			return false;
		}
	}
	
	function handle_posts() {
		$checked = isset($_POST['checked']) ? stripslashes_deep( (array)$_POST['checked'] ) : array();

		foreach ( $checked as $index => $module ) {
			if ( 0 !== validate_file($module) ||
				! file_exists(WP_PLUGIN_DIR . '/' . $this->folder . '/modules/' . $module) )
					unset($checked[$index]);
		}

		update_option('core_control-active_modules', $checked);
		wp_redirect( admin_url('tools.php?page=core-control') );
	}
	
	function main_page() {
		echo '<div class="wrap">';
		screen_icon('tools');
		echo '<h2>Core Control</h2>';
		
		$module = !empty($_GET['module']) ? $_GET['module'] : 'default';
		
		$menus = array( array('default', 'Main Page') );
		foreach ( $this->modules as $a_module ) {
			if ( ! $a_module->has_page() )
				continue;
			$menus[] = $a_module->menu();
		}
		echo '<ul class="subsubsub">';
		foreach ( $menus as $menu ) {
			$url = 'tools.php?page=core-control';
			if ( 'default' != $menu[0] )
				$url .= '&module=' . $menu[0];
			$title = $menu[1];
			$sep = $menu == end($menus) ? '' : ' | ';
			$current = $module == $menu[0] ? ' class="current"' : '';
			echo "<li><a href='$url'$current>$title</a>$sep</li>";
		}
		echo '</ul>';
		echo '<br class="clear" />';

		do_action('core_control-' . $module);

		echo '</div>';
	}

	function default_page() {
		$files = $this->find_files( WP_PLUGIN_DIR . '/' . $this->folder . '/modules/', array('pattern' => '*.php', 'levels' => 1, 'relative' => true) );
?>
<p>Welcome to Core Control, Please select the subsection from the above menu which you would like to modify</p>
<p>You may Enable/Disable which modules are loaded by checking them in the following list:
<form method="post" action="admin-post.php?action=core_control-modules">
<table class="widefat">
	<thead>
	<tr>
		<th scope="col" class="check-column"><input type="checkbox" name="check-all" /></th>
		<th scope="col">Module Name</th>
		<th scope="col">Description</th>
	</tr>
	</thead>
	<tbody>
	<?php
		foreach ( $files as $module ) {
			$details = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->folder . '/modules/' . $module, true, false);
			$active = $this->is_module_active($module);
			$style = $active ? ' style="background-color: #e7f7d3"' : '';
	?>
	<tr<?php echo $style ?>>
		<th scope="row" class="check-column"><input type="checkbox" name="checked[]" value="<?php echo esc_attr($module) ?>" <?php checked($active); ?> /></th>
		<td><?php echo $details['Title'] . ' ' . $details['Version'] ?></td>
		<td><?php echo $details['Description'] ?></td>
	</tr>
	<?php
		} //end foreach;
	?>
	</tbody>
</table>
<input type="submit" class="button-secondary" value="Save Module Choices" />
</p>
</form>
<?php
	}

	/**
	 * Output a nag notice if the user has an out of date WordPress version.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return 	void
	 */
	public function wp_notice() {
		$url = admin_url( 'plugins.php' );
		// Check for MS dashboard
		if( is_network_admin() ) {
			$url = network_admin_url( 'plugins.php' );
		}
		?>
		<div class="error">
			<p><?php echo esc_html( sprintf( __( 'Sorry, but your version of WordPress does not meet Core Control\'s required version of 3.2 to run properly. The plugin not been activated. %1$sClick here to return to the Dashboard%2$s.', 'core-control' ), '<a href="' . $url . '">"', '</a>' ) ); ?></p>
		</div>
		<?php

	}

	/**
	 * Output a nag notice if the user has an out of date PHP version.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return 	void
	 */
	public function php_notice() {
		$url = admin_url( 'plugins.php' );
		// Check for MS dashboard
		if ( is_network_admin() ) {
			$url = network_admin_url( 'plugins.php' );
		}
		?>
		<div class="error">
			<p><?php echo esc_html( sprintf( __( 'Sorry, but your version of PHP does not meet Core Control\'s required version of 5.2.4 to run properly. The plugin not been activated. %1$sClick here to return to the Dashboard%2$s.', 'core-control' ), '<a href="' . $url . '">"', '</a>' ) ); ?></p>
		</div>
		<?php

	}
}

/**
 * Fired when the plugin is activated.
 *
 * @access public
 * @since 1.3.0
 *
 * @global int $wp_version      The version of WordPress for this install.
 * 
 * @param boolean $network_wide True if WP MS admin uses "Network Activate" action, false otherwise.
 * @return void
 */
function core_control_activation_hook( $network_wide ) {

	global $wp_version;
	
	$url = admin_url( 'plugins.php' );
	// Check for MS dashboard
	if ( is_network_admin() ) {
		$url = network_admin_url( 'plugins.php' );
	}
	
	if ( version_compare( $wp_version, '3.2', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html( sprintf( __( 'Sorry, but your version of WordPress does not meet Core Control\'s required version of %1$s3.2%2$s to run properly. The plugin not been activated. %3$sClick here to return to the Dashboard%4$s.', 'core-control' ), '<strong>', '</strong>', '<a href="' . $url . '">"', '</a>' ) ) );
	}

	if ( version_compare( PHP_VERSION, '5.2.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html( sprintf( __( 'Sorry, but your version of PHP does not meet Core Control\'s required version of %1$s5.2.4%2$s to run properly. The plugin not been activated. %3$sClick here to return to the Dashboard%4$s.', 'core-control' ), '<strong>', '</strong>', '<a href="' . $url . '">"', '</a>' ) ) );
	}
}
register_activation_hook( __FILE__, 'core_control_activation_hook' );
