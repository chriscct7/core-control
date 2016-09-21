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

		// Load files
		$this->require_files();

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

		// Add Core Control menu item
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Save enabled modules
		add_action( 'admin_init', array( $this, 'save_module_selections' ) );

		// Add Module Activation Settings Page
		add_action( 'core_control-default', array( $this, 'module_selection_page' ) );

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
	 * Loads all files into scope.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return 	void
	 */
	public function require_files() {
		if ( is_admin() ) {
			require CORE_CONTROL_PLUGIN_DIR . 'modules/core_control_cron.php';
			require CORE_CONTROL_PLUGIN_DIR . 'modules/core_control_filesystem.php';
			require CORE_CONTROL_PLUGIN_DIR . 'modules/core_control_http.php';
			require CORE_CONTROL_PLUGIN_DIR . 'modules/core_control_http_log.php';
			require CORE_CONTROL_PLUGIN_DIR . 'modules/core_control_updates.php';	
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
	 * This page contains settings to turn on each available module as well those modules's settings.
	 *
	 * @since 1.3.0
	 * @access public
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page( 'tools.php', esc_html( __( 'Core Control', 'core-control' ) ), esc_html( __( 'Core Control', 'core-control' ) ), 'manage_options', 'core-control', array( $this, 'main_page' ) );
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
		if ( ! empty( $saved_modules ) && is_array( $saved_modules ) ) {
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

	/**
	 * Is valid module.
	 *
	 * Takes in the filename of a module and returns a boolean of if it's a module.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @param  string Filename of module.
	 * @return bool   Is module valid.
	 */
	public function is_valid_module( $module ) {
		if ( ! empty ( $this->get_modules() ) ) {
			return in_array( $module, $this->get_modules() );
		} else {
			return false;
		}
	}
	
	/**
	 * Outputs Core Control settings pages.
	 *
	 * Outputs the settings page for the selected module.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function main_page() {
		echo '<div class="wrap">';
			screen_icon( 'tools' );
			echo '<h2>' . esc_html( __( 'Core Control', 'core-control' ) ) . '</h2>';
		
			$current_module = ! empty( $_GET['module'] ) ? $_GET['module'] : '';
			if ( ! $current_module || ! $this->is_module_active( $current_module ) ) {
				$current_module = 'default';
			}
			
			echo '<ul class="subsubsub">';
				$current = $current_module === 'default' ? ' class="current"' : '';
				$modules = $this->get_active_modules();
				$sep     = empty( $modules ) ? '' : ' | ';
				echo "<li><a href='" . admin_url( 'tools.php?page=core-control' ) . "'" . $current . ">" . esc_html( __( 'Main Page', 'core-control' ) ) . "</a>$sep</li>";

				foreach ( $modules as $module_filename => $module ) {
					if ( empty( $module['id'] ) ) {
						continue;
					}
					
					$url   = admin_url( 'tools.php?page=core-control&module=' . $module['id'] );
					$title = ! empty( $module['title'] ) ? esc_html( $module['title'] ) : esc_html( __('Module title unavailable', 'core-control' ) );
					$sep   = $module_filename === end( $modules ) ? '' : ' | ';
					$current = $current_module === $module['id'] ? ' class="current"' : '';
					echo "<li><a href='$url'$current>$title</a>$sep</li>";
				}
			echo '</ul>';
			echo '<br class="clear" />';
			do_action( 'core_control-' . $current_module );
		echo '</div>';
	}

	/**
	 * Outputs module selection page.
	 *
	 * Outputs the settings page for picking modules to use.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function module_selection_page() {
		$modules = $this->get_modules();
		?>
		<p><?php echo esc_html( __( 'Welcome to Core Control, Please select the subsection from the above menu which you would like to modify', 'core-control' ) ); ?></p>
		<p><?php echo esc_html( __( 'You may Enable/Disable which modules are loaded by checking them in the following list:', 'core-control' ) ); ?></p>
		<form method="post" action="admin-post.php?action=core_control-modules">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col" class="check-column"><input type="checkbox" name="check-all" /></th>
				<th scope="col"><?php echo esc_html( __( 'Module Name', 'core-control' ) ); ?></th>
				<th scope="col"><?php echo esc_html( __( 'Description', 'core-control' ) ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
				foreach ( $modules as $module_file => $module ) {
					$active = $this->is_module_active( $module );
					$style = $active ? ' style="background-color: #e7f7d3"' : '';
					?>
					<tr<?php echo $style ?>>
						<th scope="row" class="check-column"><input type="checkbox" name="checked[]" value="<?php echo esc_attr( $module_file ) ?>" <?php checked( $active ); ?> /></th>
						<td>
							<?php
							if ( ! empty( $module['title'] ) ) {
								echo esc_html( $module['title'] );
							} else {
								echo esc_html( __( 'Module title not available', 'core-control' ) );
							}
							?>
						</td>
						<td>
							<?php
							if ( ! empty( $module['description'] ) ) {
								echo esc_html( $module['description'] );
							} else {
								echo esc_html( __( 'Module description not available', 'core-control' ) );
							}
							?>
						</td>
					</tr>
					<?php
				}
			?>
			</tbody>
			</table>
			<p>
			<?php wp_nonce_field( 'core-control-settings-nonce', 'core-control-settings-nonce' ); ?>
			<?php submit_button( esc_html( __( 'Save Module Choices', 'core-control' ) ), 'primary', 'core-control-module-settings-submit', false ); ?>
			</p>
			</form>
		<?php
	}

	/**
	 * Save module selections.
	 *
	 * Saves active module selections.
	 *
	 * @access public
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function save_module_selections() {

		// Check if user pressed the save button and nonce is valid
		if ( ! isset( $_POST['core-control-module-settings-submit'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['core-control-settings-nonce'], 'core-control-settings-nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checked = isset( $_POST['checked'] ) ? stripslashes_deep( (array) $_POST['checked'] ) : array();

		foreach ( $checked as $index => $module ) {
			if ( ! $this->is_valid_module( $module ) ) {
				unset( $checked[ $index ] );
			}
		}

		update_option( 'core_control-active_modules', $checked);
		wp_redirect( admin_url( 'tools.php?page=core-control' ) );
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
$GLOBALS['core-control'] = new Core_Control();

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
