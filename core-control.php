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

		// Detect non-supported WordPress version and return early
		if ( version_compare( $wp_version, '3.2', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wp_notice' ) );
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

	
	public function get_modules() {
		$modules = get_option('core_control-active_modules', array());
		foreach ( (array) $modules as $module ) {
			if ( 0 !== validate_file($module) )
				continue;
			if ( ! file_exists(WP_PLUGIN_DIR . '/' . $this->folder . '/modules/' . $module) )
				continue;
			include_once WP_PLUGIN_DIR . '/' . $this->folder . '/modules/' . $module;
			$class = basename($module, '.php');
			$this->modules[ $class ] = new $class;
		}
	}

	public function get_active_modules() {

	}



	function admin_menu() {
		add_submenu_page('tools.php', __('Core Control', 'core-control'), __('Core Control', 'core-control'), 'manage_options', 'core-control', array(&$this, 'main_page'));
	}

	function is_module_active($module) {
		return in_array( $module, get_option('core_control-active_modules', array()) );
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
	
		if ( version_compare(PHP_VERSION, '5.2.0', '<') )
			printf(__('<p><strong>Core Control:</strong> WARNING!! Your server is currently running PHP %s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, <strong>over 80%% of WordPress installs are using PHP 5.2+</strong>, WordPress will require PHP 5.2+ some day soon, Prepare while your have time time.</p>', 'core-control'), PHP_VERSION);
		
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

	//HELPERS
	function find_files( $folder, $args = array() ) {
	
		$folder = untrailingslashit($folder);
	
		$defaults = array( 'pattern' => '', 'levels' => 100, 'relative' => false );
		$r = wp_parse_args($args, $defaults);

		extract($r, EXTR_SKIP);
		
		//Now for recursive calls, clear relative, we'll handle it, and decrease the levels.
		unset($r['relative']);
		--$r['levels'];
	
		if ( ! $levels )
			return array();
		
		if ( ! is_readable($folder) )
			return false;

		if ( true === $relative )
			$relative = $folder;
	
		$files = array();
		if ( $dir = @opendir( $folder ) ) {
			while ( ( $file = readdir($dir) ) !== false ) {
				if ( in_array($file, array('.', '..') ) )
					continue;
				if ( is_dir( $folder . '/' . $file ) ) {
					$files2 = $this->find_files( $folder . '/' . $file, $r );
					if( $files2 )
						$files = array_merge($files, $files2 );
					else if ( empty($pattern) || preg_match('|^' . str_replace('\*', '\w+', preg_quote($pattern)) . '$|i', $file) )
						$files[] = $folder . '/' . $file . '/';
				} else {
					if ( empty($pattern) || preg_match('|^' . str_replace('\*', '\w+', preg_quote($pattern)) . '$|i', $file) )
						$files[] = $folder . '/' . $file;
				}
			}
		}
		@closedir( $dir );
	
		if ( ! empty($relative) ) {
			$relative = trailingslashit($relative);
			foreach ( $files as $key => $file )
				$files[$key] = preg_replace('!^' . preg_quote($relative) . '!', '', $file);
		}
	
		return $files;
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
		wp_die( sprintf( __( 'Sorry, but your version of WordPress does not meet Core Control\'s required version of %1$s3.2%2$s to run properly. The plugin not been activated. %3$sClick here to return to the Dashboard%4$s.', 'core-control' ), '<strong>', '</strong>', '<a href="' . $url . '">"', '</a>' ) );
	}
}
register_activation_hook( __FILE__, 'core_control_activation_hook' );
