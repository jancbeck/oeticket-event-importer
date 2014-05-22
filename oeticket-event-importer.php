<?php
/**
 * Plugin Name:       oeticket.com Event Importer
 * Plugin URI:        http://jancbeck.com/oeticket-importer/
 * Description:       This plugin allows you to easily import event data from oeticket.com to the Events Calendar by Modern Tribe for WordPress.
 * Version:           1.0.0
 * Author:            Jan Beck
 * Author URI:        http://jancbeck.com/
 * Text Domain:       oeticket-event-importer
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/jancbeck/oeticket-event-importer/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( is_admin() ) {

/*
 * @TODO:
 *
 * - replace `class-oeticket-event-importer.php` with the name of the plugin's class file
 *
 */
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-oeticket-event-importer.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 * @TODO:
 *
 * - replace oeticket.com_Event_Importer with the name of the class defined in
 *   `class-oeticket-event-importer.php`
 */
register_activation_hook( __FILE__, array( 'oeticket_Event_Importer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'oeticket_Event_Importer', 'deactivate' ) );

/*
 * @TODO:
 *
 * - replace oeticket.com_Event_Importer with the name of the class defined in
 *   `class-oeticket-event-importer.php`
 */
add_action( 'plugins_loaded', array( 'oeticket_Event_Importer', 'get_instance' ) );

}


