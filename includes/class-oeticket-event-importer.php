<?php
/**
 * oeticket.com Event Importer
 *
 * @package   oeticket.com_Event_Importer
 * @author    Jan Beck <mail@jancbeck.com>
 * @license   GPL-2.0+
 * @link      http://jancbeck.com/
 * @copyright 2014 Jan Beck
 */

/**
 * Plugin class.

 * @package oeticket.com_Event_Importer
 */
class oeticket_Event_Importer {

	protected static $instance;
	const VERSION = '1.0.0';
	const REQUIRED_TEC_VERSION = '3.5';
	protected $plugin_slug = 'oeticket-event-importer';
	public $errors = array();
	public $errors_images = array();
	public $success = false;
	public $imported_total = 0;
	static $api_user = '209c0d95-8f9a-4b6e-a96a-457aea9f0e41';
	static $api_key = "9EEXgSSc1Rpq/M8uW8VpgCu8EFNCwLtgKwXcu0UKkHzxtB3AaRQMoBFrxFvHEcJhxk+RgGRlhj5Ax1vOPqUb1w==";
	static $api_url = "https://api.import.io/store/connector/";
	static $event_extractor_guid = "7840fd2d-dbd0-442a-8b5f-a57bc181d1a6";
	static $reccuring_extractor_guid = "a7b28577-e0eb-4267-b9e6-08ef476d5c02";
	static $venue_extractor_guid = "4f4fd502-3005-45e0-8d2a-cc7ff42d7a4a";

	/**
	 * Object representing a Facebook entity.
	 *
	 * @var stdClass
	 */
	protected $event_object;

	/**
	 * Date format used during import of events.
	 *
	 * @var string
	 */
	protected $date_format = '';

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		$this->date_format = apply_filters( 'tribe_fb_date_format', get_option( 'date_format' ) );

	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), $this->VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), $this->VERSION );
		}

	}

	/**
	 * Add settings action link to the import page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Start importing!', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a import page for this plugin to the Events Calendar menu.
		 *
		 */
		$this->plugin_screen_hook_suffix = add_submenu_page(
			'/edit.php?post_type=' . TribeEvents::POSTTYPE,
			__( 'oeticket.com Event Importer', $this->plugin_slug ),
			__( 'oeticket.com Import', $this->plugin_slug ),
			'edit_posts',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the import page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$this->process_import_page();
		include_once( plugin_dir_path( __DIR__ ) .'views/admin.php' );
	}

	/**
	 * build out a graph url using query args and the FB access token
	 *
	 * @since    1.0.0
	 * @param string $connector name of the connector to be used for Import.io
	 * @return string the full URL
	 */
	function build_extractor_url( $connector = null ) {
		switch ( $connector ) {
			case 'venue':
				$connector_guid = apply_filters( 'oeticket_import_get_reccuring_extractor_guid', self::$venue_extractor_guid );
				break;

			case 'reccuring':
				$connector_guid = apply_filters( 'oeticket_import_get_reccuring_extractor_guid', self::$reccuring_extractor_guid );
				break;

			default:
				$connector_guid = apply_filters( 'oeticket_import_get_event_extractor_guid', self::$event_extractor_guid );
				break;
		}

		$api_user = apply_filters( 'oeticket_api_user', self::$api_user, $connector );
		$api_key  = apply_filters( 'oeticket_api_code', self::$api_key, $connector );
		$api_url  = apply_filters( 'oeticket_api_url', self::$api_url, $connector  );

		$url = trailingslashit( $api_url ) . $connector_guid . "/_query";
		$url = add_query_arg( array( '_user' => urlencode( $api_user ), '_apikey' => urlencode( $api_key ) ), $url );
		do_action( 'log', 'url with access token', 'oeticket-event-importer', $url);
		return $url;
	}

	/**
	 * retrive the body of a page using the HTTP API and json decode the result
	 *
	 * @since    1.0.0
	 * @param string $url the URL to retrieve
	 * @param string $event_url the event url to query
	 * @return string the json string
	 */
	function json_retrieve( $url, $event_url ) {
		$args = array( 'body' => json_encode( array( 'input' => array( 'webpage/url' => $event_url ))));
		$response = wp_remote_post( $url, $args );
		$response = json_decode( wp_remote_retrieve_body( $response ) );
		return $response;
	}


	/**
	 * retrieve a oeticket object
	 * example: http://www.oeticket.com/de/tickets/cirque-du-soleil-kooza-wien-under-the-grand-chapiteau-332281/event.html
	 *
	 * @param string $event_url the url of the event to retrieve
	 * @return array the json data
	 */
	function get_oeticket_event( $event_url ) {
		$event = $this->json_retrieve( $this->build_extractor_url(), $event_url );
		$this->event_object = $event->results[0];
		$this->event_object->url = $event_url;
		return $this->event_object;
	}


	/**
	 * returns an array of oeticket.com URLs given a text blob of them
	 *
	 * @since    1.0.0
	 * @param string $raw_event_ids the raw oeticket.com URLs
	 * @return array the parsed oeticket URLs
	 */
	function parse_events_from_textarea( $raw_event_urls ) {
		$event_urls = (array) explode( "\n", tribe_multi_line_remove_empty_lines( $raw_event_urls ));
		$event_urls = array_map( 'esc_url_raw', $event_urls );

		foreach ( $event_urls as $key => $event_url ) {
			$event_url_host = parse_url( $event_url, PHP_URL_HOST );
			if ( 'www.oeticket.com' != $event_url_host ) {
				unset( $event_urls[ $key ] );
			}
		}
		$event_urls = array_filter( $event_urls );

		if ( empty( $event_urls ) ) {
			$this->errors[] = __( 'The oeticket URLs provided must be valid and one per line.', $this->plugin_slug );
			return array();
		}
		return $event_urls;
	}

	/**
	 * process import when submitted
	 *
	 * @since    1.0.0
	 * @author jkudish
	 * @return void
	 */
	public function process_import_page() {
		if ( ! empty( $_POST['oeticket-confirm-import'] ) ) {
			// check nonce
			check_admin_referer( 'oeticket-event-import', 'oeticket-confirm-import' );

			$events_to_import = array();
			$this->no_events_imported = true;

			// individual events from textarea
			if ( !empty( $_POST['oeticket-import-events-by-id'] ) ) {
				$events_to_import = array_merge( $events_to_import, $this->parse_events_from_textarea( $_POST['oeticket-import-events-by-id'] ) );
			}
			// loop through events and import them
			if ( !empty( $events_to_import ) && empty( $this->errors ) ) {
				foreach ( $events_to_import as $oeticket_event_url ) {
					$local_event = $this->create_local_event( $oeticket_event_url );
					do_action('log', 'local event', 'oeticket-event-importer', $local_event);
					if ( is_wp_error( $local_event ) ) {
						$this->errors[] = $local_event->get_error_message();
					} else {
						$this->no_events_imported = false;
					}
				}
			} else {
				$this->errors[] = __( 'No valid events were provided for import. The import failed as a result.', $this->plugin_slug );
			}

			// mark it as successful
			if ( empty( $this->errors ) ) {
				$this->success = true;
			}
		}
	}

	/**
	 * Create or update an event given an URL from oeticket
	 *
	 * @param int $oeticket_event_url the Facebook ID of the event
	 * @return array|WP_Error
	 * @author jkudish
	 * @since    1.0.0
	 */
	function create_local_event( $oeticket_event_url ) {

		// Get the oetick event
		$oeticket_event = $this->get_oeticket_event( $oeticket_event_url );


		if ( isset( $oeticket_event->title ) ) {

			// parse the event
			$args = $this->parse_oeticket_event( $oeticket_event );
			var_dump($args);

			if ( !$this->find_local_object_with_oeticket_url( $args['oeticketURL'], 'event' ) ) {
				// filter the origin trail
				add_filter( 'tribe-post-origin', array( $this, 'origin_filter' ) );

				// create the event
				$event_id = tribe_create_event( $args );

				// count this as a successful import
				$this->imported_total++;

				if ( ! empty( $event_picture ) ) {

					// setup clean vars to import the photo
					$event_picture['url'] = stripslashes($event_picture['url']);
					$uploads = wp_upload_dir();
					$wp_filetype = wp_check_filetype($event_picture['url'], null );
					$filename = wp_unique_filename( $uploads['path'], basename('oeticket_event_' . $oeticket_event->id), $unique_filename_callback = null ) . '.' . $wp_filetype['ext'];
					$full_path_filename = $uploads['path'] . "/" . $filename;

					if ( substr_count( $wp_filetype['type'], "image" ) ) {

						// push the actual picture data to the local file system
						$file_saved = file_put_contents($uploads['path'] . "/" . $filename, $event_picture['source']);

						if ( $file_saved ) {

							// setup attachment params
							$attachment = array(
								 'post_mime_type' => $wp_filetype['type'],
								 'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
								 'post_content' => '',
								 'post_status' => 'inherit',
								 'guid' => $uploads['url'] . "/" . $filename
							);

							// attach photo to post obj (event)
							$attach_id = wp_insert_attachment( $attachment, $full_path_filename, $event_id );

							if ( $attach_id ) {
								// set the thumbnail (featured image)
								set_post_thumbnail($event_id, $attach_id);

								// attach metadata for attachment
								require_once(ABSPATH . "wp-admin" . '/includes/image.php');
								$attach_data = wp_generate_attachment_metadata( $attach_id, $full_path_filename );
								wp_update_attachment_metadata( $attach_id,  $attach_data );

							} else {
								$this->errors_images[] = sprintf( __( '%s. Event Image Error: Failed to save record into database.', $this->plugin_slug ), $oeticket_event->name);
							}
						} else {
							$this->errors_images[] = sprintf( __( '%s. Event Image Error: The file cannot be saved.', $this->plugin_slug ), $oeticket_event->name);
						}
					} else {
						$this->errors_images[] = sprintf( __( '%s. Event Image Error: "%s" is not a valid image. %s', $this->plugin_slug ), $oeticket_event->name, basename($event_picture['url']), $wp_filetype['type'] );
					}
				}

				// set the event's Facebook ID meta
				update_post_meta( $event_id, '_ecp_custom_1', $args['FacebookID'] );

				// set the event's map status if global setting is enabled
				if( tribe_get_option('fb_enable_GoogleMaps') ) {
					update_post_meta( $event_id, '_EventShowMap', true );
				}

				// get the created venue IDs
				$venue_id = tribe_get_venue_id( $event_id );

				// Set the post status to publish for the venue.
				if ( get_post_status( $venue_id ) != 'publish' ) {
					wp_publish_post( $venue_id );
				}

				// set venue Facebook ID
				if ( isset( $args['Venue']['FacebookID'] ) ) {
					update_post_meta( $venue_id, '_VenueFacebookID', $args['Venue']['FacebookID'] );
				}

				// remove filter for the origin trail
				remove_filter( 'tribe-post-origin', array( $this, 'origin_filter' ) );

				return array( 'event' => $event_id, 'venue' => $venue_id );
			} else {
				return new WP_Error( 'event_already_exists', sprintf( __( 'The event "%s" was already imported from oeticket.com.', $this->plugin_slug ), $oeticket_event->name, $oeticket_event ) );
			}
		} else {
			do_action('log', 'Facebook event', 'tribe-events-facebook', $oeticket_event);
			return new WP_Error( 'invalid_event', sprintf( __( "Either the event with ID %s does not exist or we couldn't reach the Import.io API", $this->plugin_slug ), $oeticket_event_url ) );
		}
	}

	/**
	 * origin/trail filter
	 *
	 * @since    1.0.0
	 * @author jkudish
	 * @return string facebook importer identifier
	 */
	function origin_filter() {
		return self::$plugin_slug;
	}

	/**
	 * display a failure message when TEC is not installed
	 *
	 * @since    1.0.0
	 * @author jkudish
	 * @return void
	 */
	static function fail_message() {
		if ( current_user_can( 'activate_plugins' ) ) {
			$url = add_query_arg( array( 'tab' => 'plugin-information', 'plugin' => 'the-events-calendar', 'TB_iframe' => 'true' ), admin_url( 'plugin-install.php' ) );
			$title = __( 'The Events Calendar', $this->plugin_slug );
			echo '<div class="error"><p>' . sprintf( __( 'To begin using The Events Calendar: oeticket.com Event Importer, please install the latest version of %s.', $this->plugin_slug ), '<a href="' . $url . '" class="thickbox" title="' . $title . '">' . $title . '</a>', $title ) . '</p></div>';
		}
	}

	/**
	 * Add FB Importer to the list of add-ons to check required version.
	 *
	 * @param array $plugins the existing plugins
	 *
	 * @return mixed
	 * @author jkudish
	 * @since    1.0.0
	 */
	static function init_addon( $plugins ) {
		$plugins['OeticketImporter'] = array( 'plugin_name' => 'The Events Calendar: oeticket.com Event Importer', 'required_version' => oeticket_Event_Importer::REQUIRED_TEC_VERSION, 'current_version' => oeticket_Event_Importer::VERSION, 'plugin_dir_file' => basename( dirname( __FILE__ ) ) . '/oeticket-event-importer.php' );
		return $plugins;
	}

}
