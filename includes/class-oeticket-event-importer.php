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
	protected static $plugin_slug = 'oeticket-event-importer';
	public $errors = array();
	public $errors_images = array();
	public $success = false;
	public $imported_total = 0;
	static $api_user = '209c0d95-8f9a-4b6e-a96a-457aea9f0e41';
	static $api_key = "9EEXgSSc1Rpq/M8uW8VpgCu8EFNCwLtgKwXcu0UKkHzxtB3AaRQMoBFrxFvHEcJhxk+RgGRlhj5Ax1vOPqUb1w==";
	static $api_url = "https://api.import.io/store/connector/";
	static $event_extractor_guid = "7840fd2d-dbd0-442a-8b5f-a57bc181d1a6";
	static $instances_extractor_guid = "a7b28577-e0eb-4267-b9e6-08ef476d5c02";
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

		add_action( 'tribe_events_venue_updated', array( $this, 'tribe_events_venue_updated'), 10, 2 );
		add_action( 'tribe_events_venue_created', array( $this, 'tribe_events_venue_created'), 10, 2 );
		add_action( 'tribe_events_update_meta', array( $this, 'tribe_events_update_meta'), 10, 2 );

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
	public function build_extractor_url( $connector = null ) {
		switch ( $connector ) {
			case 'venue':
				$connector_guid = apply_filters( 'oeticket_import_get_venue_extractor_guid', self::$venue_extractor_guid );
				break;

			case 'instances':
				$connector_guid = apply_filters( 'oeticket_import_get_instances_extractor_guid', self::$instances_extractor_guid );
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
	public function json_retrieve( $api_url, $oeticket_url ) {
		$args = array( 'body' => json_encode( array( 'input' => array( 'webpage/url' => $oeticket_url ))));
		$response = wp_remote_post( $api_url, $args );

		if ( is_wp_error( $response ) ) {
		   $this->errors[] = $response->get_error_message(). " ($oeticket_url)";
		} else {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
		}
		return $response;
	}


	/**
	 * retrieve a oeticket event object
	 * example: http://www.oeticket.com/de/tickets/cirque-du-soleil-kooza-wien-under-the-grand-chapiteau-332281/event.html
	 *
	 * @param string $event_url the url of the event to retrieve
	 * @return array the json data
	 */
	public function get_oeticket_event( $event_url ) {
		$event = $this->json_retrieve( $this->build_extractor_url(), $event_url );

		if ( ! empty( $event->results ) ) {
			$this->event_object = $event->results[0];
			$this->event_object->url = $event_url;
			$this->event_object->instances = array();

			for ($i=1; $i <= $this->event_object->paged; $i++) {
				$event_instances = $this->json_retrieve( $this->build_extractor_url( 'instances' ), add_query_arg( array( 'spage' => $i ), $event_url ) );
				if ( ! empty( $event_instances->results ) ) {
					foreach ( $event_instances->results as $event_instance ) {
						if ( ! empty( $event_instance->ticket_link ) ) {
							$event_instance->ticket_link = apply_filters( 'oeticket_ticket_link', 'http://www.oeticket.com'. $event_instance->ticket_link, $event_instance->ticket_link );
							$this->event_object->instances[] = $event_instance;
						}
					}
				}
			}
		}
		return $this->event_object;
	}

	/**
	 * retrieve a oeticket venue
	 * example: http://www.oeticket.com/de/spielstaetten/cirque-du-soleil-8926/venue.html
	 *
	 * @param string $venue_url the url of the venue to retrieve
	 * @return array the json data
	 */
	public function get_oeticket_venue( $venue_url ) {
		$response = $this->json_retrieve( $this->build_extractor_url( 'venue' ), $venue_url );

		if ( ! is_wp_error( $response ) && ! empty( $response->results ) ) {
			$response = $this->event_object->venue = $response->results[0];
			$this->event_object->venue->url = esc_url( $venue_url );
		}
		return $response;
	}


	/**
	 * returns an array of oeticket.com URLs given a text blob of them
	 *
	 * @since    1.0.0
	 * @param string $raw_event_ids the raw oeticket.com URLs
	 * @return array the parsed oeticket URLs
	 */
	public function parse_events_from_textarea( $raw_event_urls ) {
		$event_urls = (array) explode( "\n", tribe_multi_line_remove_empty_lines( $raw_event_urls ));
		$event_urls = array_map( 'esc_url_raw', $event_urls );
		$allowed_hosts = apply_filters( 'oeticket_allowed_hosts', array( 'www.oeticket.com' ) );

		foreach ( $event_urls as $key => $event_url ) {
			$event_url_host = parse_url( $event_url, PHP_URL_HOST );
			if ( ! in_array( $event_url_host , $allowed_hosts) ) {
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

	public function get_event_cover( $url ) {
		$get_photo = wp_remote_get( $url );

		if ( ! is_wp_error( $get_photo) ) {
			return apply_filters( 'oeticket_get_event_cover', array( 'url' => $url, 'source' => $get_photo['body'] ) );
		} else {
			if ( is_wp_error( $get_photo ) ) {
				$this->errors_images[] = $get_photo->get_error_message();
			} else {
				$this->errors_images[] = __( 'Could not successfully import the image for unknown reasons.', $this->plugin_slug );
			}
		}
		return false;
	}

	/**
	 * find a locally stored event/venue with the specified oeticket URL
	 *
	 * @since 1.0
	 * @author jkudish
	 * @param string $oeticket_url the event or venue
	 * @param string $object_type the type of object we are looking for
	 * @param string $fallback_object_name the post title used as a fallback if $oeticket_url is empty for some reason
	 * @return int|null the event ID or null on failure
	 */
	function find_local_object_with_oeticket_url( $oeticket_url, $object_type = 'event', $fallback_object_name = null ) {

		remove_action( 'pre_get_posts', array( 'TribeEventsQuery', 'pre_get_posts' ), 50 );

		switch ( $object_type ) {
			case 'event' :
				$meta_key = '_ecp_custom_1';
				$post_type = TribeEvents::POSTTYPE;
			break;
			case 'venue' :
				$meta_key = '_VenueOeticketURL';
				$post_type = TribeEvents::VENUE_POST_TYPE;
			break;
			default :
				return new WP_Error( 'invalid_object_type', __( 'Object type provided is invalid', $this->plugin_slug ), $object_type );
		}

		$query = new WP_Query();
		$query_args = array(
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'nopaging' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query' => array(
				array(
					'key' => $meta_key,
					'value' => $oeticket_url,
				)
			)
		);


 		$query->query( $query_args );

		wp_reset_query();

		// run query again but with post name if meta_query returned nothing
		if ( empty( $query->posts[0] ) && $fallback_object_name ) {
			unset($query_args['meta_query']);
			$query_args['name'] = sanitize_title( $fallback_object_name );
			$query->query( $query_args );
		}

		$post_id = ( !empty( $query->posts[0] ) ) ? $query->posts[0]->ID : false;

		add_action( 'pre_get_posts', array( 'TribeEventsQuery', 'pre_get_posts' ), 50 );

		return apply_filters( 'oeticket_find_local_object_with_oeticket_url', $post_id );
	}

	/**
	 * parse the oeticket venue given an object URL
	 * or use the venue property of the event itself
	 *
	 * @since 1.0
	 * @author jkudish
	 * @param object $facebook_event the Facebook event json object
	 * @return object the venue object
	 */
	function parse_oeticket_venue( $oeticket_event ) {

		if ( ! parse_url( $oeticket_event->venue_link, PHP_URL_HOST ) ) {
			$oeticket_venue_link = 'http://www.oeticket.com'. $oeticket_event->venue_link;
		}

		$raw_venue = $this->get_oeticket_venue( $oeticket_venue_link );
		$venue = new stdClass;
		if ( ! is_wp_error( $raw_venue ) && ! empty( $raw_venue->venue_name ) ) {
			$venue->oeticket_url = $oeticket_venue_link;
			$venue->name = ( !empty( $raw_venue->venue_name ) ) ? trim( $raw_venue->venue_name ) : false;
			$venue->description = ( !empty( $raw_venue->venue_description ) ) ? wp_strip_all_tags( $raw_venue->venue_description ) : false;
			$venue->address = ( !empty( $raw_venue->venue_street ) ) ? trim( $raw_venue->venue_street ) : false;
			$venue->city = ( !empty( $raw_venue->venue_city ) ) ? trim( rtrim( $raw_venue->venue_city, ',' )) : false;
			$venue->country = ( !empty( $raw_venue->venue_country ) ) ? trim( $raw_venue->venue_country ) : false;
			$venue->zip = ( !empty( $raw_venue->venue_zip ) ) ? trim( $raw_venue->venue_zip ) : false;
			$venue->map_link = ( !empty( $raw_venue->venue_map ) ) ? trim( $raw_venue->venue_map ) : false;
		} else {
			$venue->oeticket_url = false;
			$venue->name = ( !empty( $oeticket_event->venue_name ) ) ? trim( $oeticket_event->venue_name ) : false;
			$venue->description = false;
			$venue->address = ( !empty( $oeticket_event->venue_street ) ) ? trim( $oeticket_event->venue_street ) : false;
			$venue->city = ( !empty( $oeticket_event->venue_city ) ) ? trim( rtrim( $raw_venue->venue_city, ',' )) : false;
			$venue->country = ( !empty( $oeticket_event->venue_country ) ) ? trim( $raw_venue->venue_country ) : false;
			$venue->zip = ( !empty( $oeticket_event->venue_zip ) ) ? trim( $oeticket_event->venue_zip ) : false;
		}
		return $venue;
	}

	/**
	 * determine if an event is an all day event or not
	 *
	 * @param string $start_time the start time
	 * @param string $enddate the end time
	 * @param string $oeticket_url the oeticket URL of the event / used in the filter
	 * @return bool
	 */
	public function determine_if_is_all_day( $start_time, $enddate, $oeticket_url = '' ) {
		$start_time = date( 'Hi', strtotime( $start_time ) );
		$enddate = date( 'Hi', strtotime( $enddate ) );
		$all_day_start_time = apply_filters( 'oeticket_all_day_start_time', '1000' );
		$all_day_enddate = apply_filters( 'oeticket_all_day_enddate', '1000' );

		if ( $all_day_start_time == $start_time && $all_day_enddate == $enddate ) {
			$is_all_day = true;
		} else {
			$is_all_day = false;
		}
		return apply_filters( 'oeticket_determine_if_is_all_day', $is_all_day, $start_time, $enddate, $oeticket_url );
	}

	/**
	 * parse an oeticket event to get all the necessary
	 * params to create the local event
	 *
	 * @since 1.0
	 * @author jkudish
	 * @param object $facebook_event the Facebook event json object
	 * @return array the event paramaters
	 */
	public function parse_oeticket_event_args( $single_event, $oeticket_event ) {

		// fetch venue/organizer objects and local ID
		$venue = $this->parse_oeticket_venue( $oeticket_event );
		$local_venue_id = $this->find_local_object_with_oeticket_url( $venue->oeticket_url, 'venue', $venue->name );

		// setup the base array
		$event_params = array(
			'OeticketURL' => $oeticket_event->oeticket_url,
			'post_title' => ( !empty( $oeticket_event->title ) ) ? $oeticket_event->title : '',
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
			'post_content' => wp_strip_all_tags( $oeticket_event->description ),
		);

		// set venue only if we have at least a name
		if ( ! empty( $venue->name ) ) {

			$local_venue_country = tribe_get_country( $local_venue_id );
			$local_venue_city = tribe_get_city( $local_venue_id );
			$local_venue_zip = tribe_get_zip( $local_venue_id );
			$local_venue_address = tribe_get_address( $local_venue_id );
			$local_venue_description = get_post_field( 'post_content', $local_venue_id, 'raw' );

			$event_params['Venue'] = array(
				'Venue' => $venue->name,
				'Address' => ! empty( $local_venue_address ) ? $local_venue_address : $venue->address,
				'City' =>  ! empty( $local_venue_city ) ? $local_venue_city : $venue->city,
				'Country' => ! empty( $local_venue_country ) ? $local_venue_country : $venue->country,
				'Zip' => ! empty( $local_venue_zip ) ? $local_venue_zip : $venue->zip,
				'Description' => ! empty( $local_venue_description ) ? $local_venue_description : $venue->description,
			);

			if ( $venue->oeticket_url ) {
				$event_params['Venue']['OeticketURL'] = $venue->oeticket_url;
			}

			if ( $venue->map_link && $map_link = wp_parse_args( $venue->map_link ) ) {
				$ll = explode( ',', $map_link['ll'] );
				$event_params['Venue']['ShowMap'] = true;
				$event_params['Venue']['ShowMapLink'] = $venue->map_link;
				$event_params['Venue']['Lat'] = @$ll[0];
				$event_params['Venue']['Lng'] = @$ll[1];
			}
		}

		// set venue ID
		if ( !empty( $local_venue_id ) ) {
			$event_params['Venue']['VenueID'] = $local_venue_id;
			$event_params['EventVenueID'] = $local_venue_id;
		}

		// set taxonomy terms
		if ( ! empty( $oeticket_event->category ) && $categories = array_map( 'trim', explode(', ', $oeticket_event->category))) {

			$taxonomy = TribeEvents::TAXONOMY;
			$event_params['tax_input'] = array( $taxonomy => array() );
			foreach ($categories as $category) {
				if ( $term = get_term_by( 'name', $category, $taxonomy ) ) {
					$term_id = $term->term_id;
				} else {
					$inserted_term = wp_insert_term( $category, $taxonomy );
					if ( ! is_wp_error( $inserted_term )) {
						$term_id = $inserted_term['term_id'];
					} else {
						continue;
					}
				}
				$event_params['tax_input'][$taxonomy][] = $term_id;
			}
		}

		$start_date = $single_event->startdate;
		$end_date = ! empty( $single_event->enddate ) ? $single_event->enddate : $start_date;

		// set the dates
		$event_params['EventStartDate'] = TribeDateUtils::dateOnly( $start_date );
		$event_params['EventEndDate'] = TribeDateUtils::dateOnly( $end_date );

		// determine all day / set the time
		if ( $this->determine_if_is_all_day( $start_date, $end_date, $single_event->ticket_link ) ) {
			$event_params['EventAllDay'] = 'yes';
		} else {
			$event_params['EventStartHour'] = TribeDateUtils::hourOnly( $start_date );
			$event_params['EventStartMinute'] = TribeDateUtils::minutesOnly( $start_date );
			$event_params['EventStartMeridian'] = TribeDateUtils::meridianOnly( $start_date );
			$event_params['EventEndHour'] = TribeDateUtils::hourOnly( $end_date );
			$event_params['EventEndMinute'] = TribeDateUtils::minutesOnly( $end_date );
			$event_params['EventEndMeridian'] = TribeDateUtils::meridianOnly( $end_date );
		}

		return apply_filters( 'oeticket_parse_event', $event_params, $single_event, $oeticket_event );
	}

	/**
	 * Create or update an event given an URL from oeticket
	 *
	 * @param string $oeticket_event_url the Facebook ID of the event
	 * @return array|WP_Error
	 * @author jkudish
	 * @since    1.0.0
	 */
	public function create_local_event( $oeticket_event_url ) {

		// Get the oetick event
		$oeticket_event = $this->get_oeticket_event( $oeticket_event_url );

		if ( isset( $oeticket_event->title ) ) {

			if ( ! empty( $oeticket_event->{'cover/_source'} ) ) {
				$event_cover = apply_filters( 'oeticket_event_cover', $this->get_event_cover( "http:". $oeticket_event->{'cover/_source'} ), $oeticket_event );
			}

			$imported = array();

			foreach ( $oeticket_event->instances as $key => $event ) {

				if ( ! $this->find_local_object_with_oeticket_url( $event->ticket_link, 'event' ) ) {
					// filter the origin trail
					add_filter( 'tribe-post-origin', array( $this, 'get_plugin_slug' ) );

					$instance_args = $this->parse_oeticket_event_args( $event, $oeticket_event );

					// create the event
					// https://gist.github.com/leszekr/5011218
					// http://docs.tri.be/Events-Calendar/source-function-tribe_create_event.html#13-53
					$event_id = tribe_create_event( $instance_args );

					// count this as a successful import
					$this->imported_total++;

					if ( ! empty( $event_cover ) ) {

						// setup clean vars to import the photo
						$event_cover['url'] = stripslashes($event_cover['url']);
						$uploads = wp_upload_dir();
						$wp_filetype = wp_check_filetype($event_cover['url'], null );
						$filename = wp_unique_filename( $uploads['path'], basename('oeticket_event_' . $oeticket_event->id), $unique_filename_callback = null ) . '.' . $wp_filetype['ext'];
						$full_path_filename = $uploads['path'] . "/" . $filename;

						if ( substr_count( $wp_filetype['type'], "image" ) ) {

							// push the actual picture data to the local file system
							$file_saved = file_put_contents($uploads['path'] . "/" . $filename, $event_cover['source']);

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
									$this->errors_images[] = sprintf( __( '%s. Event Image Error: Failed to save record into database.', $this->plugin_slug ), $oeticket_event->title);
								}
							} else {
								$this->errors_images[] = sprintf( __( '%s. Event Image Error: The file cannot be saved.', $this->plugin_slug ), $oeticket_event->title);
							}
						} else {
							$this->errors_images[] = sprintf( __( '%s. Event Image Error: "%s" is not a valid image. %s', $this->plugin_slug ), $oeticket_event->title, basename($event_cover['url']), $wp_filetype['type'] );
						}
					}

					// set the event's Oeticket meta
					update_post_meta( $event_id, '_ecp_custom_1', $event->ticket_link );
					update_post_meta( $event_id, '_oeticketURL', $oeticket_event_url );

					// set the event's map status if global setting is enabled
					if( tribe_get_option('fb_enable_GoogleMaps') ) {
						update_post_meta( $event_id, '_EventShowMap', true );
					}

					// get the created venue IDs
					$venue_id = ! empty( $instance_args['EventVenueID'] ) ? $instance_args['EventVenueID'] : tribe_get_venue_id( $event_id );

					// Set the post status to publish for the venue.
					if ( get_post_status( $venue_id ) != 'publish' ) {
						wp_publish_post( $venue_id );
					}

					// set venue oeticket URL
					if ( isset( $args['Venue']['oeticketURL'] ) ) {
						update_post_meta( $venue_id, '_VenueOeticketURL', $args['Venue']['oeticketURL'] );
					}

					// remove filter for the origin trail
					remove_filter( 'tribe-post-origin', array( $this, 'get_plugin_slug' ) );

					$imported[] = apply_filters( 'oeticket_successful_import_data', array( 'event' => $event_id, 'venue' => $venue_id ) );
				} else {
					$this->errors[] = sprintf( __( 'The instance of the event "%s" at %s was already imported from oeticket.com. These instances have been skipped.', $this->plugin_slug ), $oeticket_event->title, TribeDateUtils::dateOnly( $event->startdate ). ' ' .TribeDateUtils::timeOnly( $event->startdate ) );
				}
			}

			return $imported;

		} else {
			do_action('log', 'Facebook event', 'tribe-events-facebook', $oeticket_event);
			return new WP_Error( 'invalid_event', sprintf( __( "Either the event with URL %s does not exist or we couldn't reach the Import.io API", $this->plugin_slug ), make_clickable( $oeticket_event_url ) ) );
		}
	}

	public function tribe_events_update_meta( $post_id, $data ) {
		$data['EventVenueID'] = ! empty( $data['EventVenueID']) ? $data['EventVenueID'] : $data['Venue']['VenueID'];

		if ( ! empty( $data['EventVenueID'] ) && ! get_post_meta( $post_id, '_EventVenueID', true ) ) {
			return update_post_meta( $post_id, '_EventVenueID', $data['EventVenueID'] );
		}
	}

	public function tribe_events_venue_updated( $venue_id, $data ) {
		if ( !empty( $data['Description'] ) ) {
			wp_update_post( array( 'ID' => $venue_id, 'post_content' => $data['Description'] ) );
		}
	}

	public function tribe_events_venue_created( $venue_id, $data  ) {
		tribe_update_venue($venue_id, $data);
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
			$title = __( 'The Events Calendar', self::$plugin_slug );
			echo '<div class="error"><p>' . sprintf( __( 'To begin using The Events Calendar: oeticket.com Event Importer, please install the latest version of %s.', self::$plugin_slug ), '<a href="' . $url . '" class="thickbox" title="' . $title . '">' . $title . '</a>', $title ) . '</p></div>';
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
