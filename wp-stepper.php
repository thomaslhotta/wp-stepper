<?php
/**
 * WordPress IOT controller plugin
 *
 * Creates a ajax-action.php endpoint that returns metrics from a WordPress installation as a value
 * between 0 and 359. Useful for controlling a physical indicator device
 *
 * @since             1.0.0
 * @package           WP_Stepper
 *
 * @wordpress-plugin
 * Plugin Name:       Stepper
 * Description:       Stepper motor controller plugin
 * Version:           1.0.0
 * Author:            Thomas Lhotta
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WP_Stepper {

	/**
	 * @var WP_Stepper
	 */
	protected static $instance;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Returns the plugin instance
	 *
	 * @return WP_Stepper
	 */
	public static function get_instance() {
		self::$instance = new self();
		self::$instance->register_hooks();

		return self::$instance;
	}

	protected function __construct() {}

	/**
	 * Registers actions
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_stepper', array( $this, 'process_ajax' ) );
		add_action( 'wpmu_options', array( $this, 'show_settings' ) );
		add_action( 'update_wpmu_options', array( $this, 'save_settings' ) );
	}

	/**
	 * Processes the plugin ajax request
	 */
	public function process_ajax() {
		if ( ! $this->check_ip() ) {
			return;
		}

		$settings = $this->get_settings();

		if ( filter_input( INPUT_GET, 'key' ) !== $settings['key'] ) {
			return;
		}

		$count = $this->get_user_count();

		die(
			sprintf(
				'{"count":%d}',
				$this->convert_to_degrees( $count )
			)
		);
	}

	public function get_user_count() {
		$now = new DateTime( 'now' );

		$query = new WP_User_Query(
			array(
				'blog_id'     => 0,
				'date_query'  => array(
					'after' => $now->format( DateTime::RFC822 ),
				),
				'count_total' => true,
				'number'      => 1,
			)
		);

		return $query->get_total();
	}

	/**
	 * Returns the plugin settings
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( empty( $this->settings ) ) {
				$settings = get_site_option(
					'stepper',
					'{}'
				);

				$settings = json_decode( $settings, true );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				$this->settings = wp_parse_args(
					$settings,
					array(
						'key'     => null,
						'ip'      => null,
						'query'   => 'users',
						'blog_id' => 1,
						'form_id' => 0,
						'max'     => 100,
					)
				);
		}

		return $this->settings;
	}

	/**
	 * Checks the request IP if it is configured
	 *
	 * @return bool
	 */
	public function check_ip() {
		$settings = $this->get_settings();

		if ( empty( $settings['ip'] ) ) {
			return true;
		}

		// First check proxy forwarded IP
		$ip = filter_input( INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP );
		if ( empty( $ip ) ) {
			// Also check proxy IP
			$ip = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		}

		// No IP found
		if ( empty( $ip ) ) {
			return false;
		}

		return $ip === $settings['ip'];
	}

	/**
	 * Converts the given number to a range between 0 and 359 degrees.
	 *
	 * @param $number
	 *
	 * @return float
	 */
	public function convert_to_degrees( $number ) {
		$settings = $this->get_settings();

		$steps = 359 / intval( $settings['max'] );

		$degrees = floor( $number * $steps );

		if ( 359 < $degrees ) {
			$degrees = 359;
		}

		return round( $degrees );
	}

	/**
	 * Renders the settings field
	 */
	public function show_settings() {
		$settings = $this->get_settings();

		?>
			<h2>Stepper</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="site_name">Settings</label></th>
					<td>
						<textarea class="widefat" name="stepper_settings"><?php echo json_encode( $settings ); ?></textarea>
					</td>
				</tr>
			</table>

		<?php
	}

	/**
	 * Saves the plugin settings
	 */
	public function save_settings() {
		if ( empty( $_POST['stepper_settings'] ) ) {
			return;
		}

		$settings = json_decode( wp_unslash( $_POST['stepper_settings'] ), true );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		update_site_option(
			'stepper',
			json_encode( $settings )
		);
	}
}

if ( is_main_site() ) {
	WP_Stepper::get_instance();
}

