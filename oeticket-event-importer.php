<?php
/**
 * Plugin Name:       oeticket.com Event Importer
 * Plugin URI:        http://jancbeck.com/oeticket-importer/
 * Description:       This plugin allows you to easily import event data from oeticket.com to the Events Calendar by Modern Tribe for WordPress.
 * Version:           1.0.0
 * Author:            Jan Beck
 * Author URI:        http://jancbeck.com/
 * Text Domain:       oeticket-event-importer
 * License:           MIT
 * License URI:       http://jancbeck.mit-license.org
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/jancbeck/oeticket-event-importer/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( is_admin() ) {

	require_once( plugin_dir_path( __FILE__ ) . 'includes/class-oeticket-event-importer.php' );
	add_action( 'plugins_loaded', 'init_oticket_event_importer' );

	function init_oticket_event_importer() {
		add_filter( 'tribe_tec_addons', array( 'oeticket_Event_Importer', 'init_addon' ) );
		if ( class_exists( 'TribeEvents' ) && defined( 'TribeEvents::VERSION' ) && version_compare( TribeEvents::VERSION, oeticket_Event_Importer::REQUIRED_TEC_VERSION, '>=' ) ) {
			oeticket_Event_Importer::get_instance();
		}
		if ( !class_exists( 'TribeEvents' ) ) {
			add_action( 'admin_notices', array( 'oeticket_Event_Importer', 'fail_message' ) );
		}
	}

	/*
	 * Register hooks that are fired when the plugin is activated or deactivated.
	 * When the plugin is deleted, the uninstall.php file is loaded.
	 */
	register_activation_hook( __FILE__, array( 'oeticket_Event_Importer', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'oeticket_Event_Importer', 'deactivate' ) );

}


