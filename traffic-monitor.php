<?php
/**
 * Plugin Name: Traffic Monitor
 * Plugin URI: https://github.com/akenion/wordpress-traffic-monitor
 * Description: Record and review HTTP requests to your site
 * Version: 0.0.1
 * Requires at last: 5.2
 * Requires PHP: 7.4
 * Author: Alex Kenion
 * Author URI: https://github.com/akenion
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

//Prevent direct access to plugin source
if(!defined('WPINC'))
	exit;

require_once 'TrafficMonitorDatabase.php';

/**
 * A container for hooks and core plugin functionality
 */
class TrafficMonitorPlugin{

	//The name of the capability that allows for viewing traffic monitor data
	public const CAPABILITY_MONITOR_TRAFFIC='monitor_traffic';

	//Option keys
	private const OPTION_TOP_VALUE_LIMIT='traffic_monitor_top_value_limit';
	private const OPTION_RETENTION_PERIOD='traffic_monitor_retention_period';

	//Default option values
	private const DEFAULT_TOP_VALUE_LIMIT=10;
	private const DEFAULT_RETENTION_PERIOD='PT1M';

	/**
	 * Activate Traffic Monitor
	 * - Create database tables for traffic logs
	 * - Add capability to view traffic monitor
	 */
	public static function activate(): void{
		global $wpdb;
		$trafficDb=new TrafficMonitorDatabase($wpdb);
		if(!$trafficDb->createSchema())
			error_log('Failed to initialize traffic monitor schema');
		$admin=get_role('administrator');
		if($admin!==null)
			$admin->add_cap(self::CAPABILITY_MONITOR_TRAFFIC);
	}

	/**
	 * Deactive Traffic Monitor
	 * - Remove capability to view traffic monitor
	 */
	public static function deactivate(): void{
		$admin=get_role('administrator');
		if($admin!==null)
			$admin->remove_cap(self::CAPABILITY_MONITOR_TRAFFIC);
	}

	/**
	 * Uninstall Traffic Monitor
	 * - Delete all data and tables
	 */
	public static function uninstall(): void{
		global $wpdb;
		$trafficDb=new TrafficMonitorDatabase($wpdb);
		if(!$trafficDb->deleteSchema())
			error_log('Failed to remove traffic monitor schema');
	}

	/**
	 * Register the Monitor Traffic page on the tools menu
	 */
	public static function registerToolsPage(): void{
		global $wpdb;
		require_once "TrafficMonitorPage.php";
		$trafficMonitorPage=new TrafficMonitorPage(self::CAPABILITY_MONITOR_TRAFFIC, new TrafficMonitorDatabase($wpdb), get_option(self::OPTION_TOP_VALUE_LIMIT));
		add_submenu_page(
			'tools.php',
			TrafficMonitorPage::TITLE,
			TrafficMonitorPage::TITLE,
			self::CAPABILITY_MONITOR_TRAFFIC,
			'monitor-traffic',
			[$trafficMonitorPage, 'display']
		);
	}

	/**
	 * Register the Traffic Monitor page on the settings menu
	 */
	public static function registerSettingsPage(): void{
		add_options_page(
			'Traffic Monitor',
			'Traffic Monitor',
			'manage_options',
			'traffic_monitor_settings',
			[self::class, 'displaySettings']
		);
	}


	/**
	 * Render the settings page, if appropriate
	 */
	public static function displaySettings(): void{
		if(!current_user_can('manage_options'))
			return;
		if(isset($_GET['settings-updated']))
			echo "<p>Settings Saved</p>";
?>
		<div class="wrap">
			<h1><?= htmlentities(get_admin_page_title()) ?></h1>
			<form action="options.php" method="post">
<?php
				settings_fields('traffic_monitor_settings');
				do_settings_sections('traffic_monitor_settings');
				submit_button('Save Settings');
?>
			</form>
		</div>
<?php
	}

	/**
	 * Initialize the Traffic Monitor plugin on each request
	 */
	public static function initialize(): void{
		if(is_admin()){
			wp_register_style('traffic_monitor_styles', plugins_url('traffic-monitor.css', __FILE__));
			add_action('admin_menu', [self::class, 'registerToolsPage']);
			add_action('admin_menu', [self::class, 'registerSettingsPage']);
		}
		if(!wp_next_scheduled('traffic_monitor_purge'))
			wp_schedule_event(time(), 'every_minute', 'traffic_monitor_purge');
	}

	/**
	 * Register settings for Traffic Monitor
	 */
	private static function registerSettings(): void{
		register_setting(
			'traffic_monitor_settings',
			self::OPTION_TOP_VALUE_LIMIT,
			[
				'type'=>'integer',
				'description'=>'The number of top values to display on the "Monitor Traffic" page',
				'default'=>self::DEFAULT_TOP_VALUE_LIMIT
			]
		);
		add_settings_section(
			'traffic_monitor_interface',
			'Traffic Monitor Interface',
			function(){},
			'traffic_monitor_settings'
		);
		add_settings_field(
			self::OPTION_TOP_VALUE_LIMIT,
			'Top Value Limit',
			function(){
?>
				<input type="number" name="<?= htmlentities(self::OPTION_TOP_VALUE_LIMIT) ?>" value="<?= htmlentities(get_option(self::OPTION_TOP_VALUE_LIMIT)) ?>">
<?php
			},
			'traffic_monitor_settings',
			'traffic_monitor_interface'
		);
		register_setting(
			'traffic_monitor_settings',
			self::OPTION_RETENTION_PERIOD,
			[
				'type'=>'string',
				'description'=>'A duration for which to retain logs as specified in ISO 8601 and compatible with PHP\'s DateInterval',
				'default'=>self::DEFAULT_RETENTION_PERIOD
			]
		);
		add_settings_section(
			'traffic_monitor_records',
			'Traffic Monitor Records',
			function(){},
			'traffic_monitor_settings'
		);
		add_settings_field(
			self::OPTION_RETENTION_PERIOD,
			'Retention Period',
			function(){
?>
				<input type="text" name="<?= htmlentities(self::OPTION_RETENTION_PERIOD) ?>" value="<?= htmlentities(get_option(self::OPTION_RETENTION_PERIOD)) ?>">
<?php
			},
			'traffic_monitor_settings',
			'traffic_monitor_records'
		);
	}

	/**
	 * Admin-specific initialization for Traffic Monitor
	 */
	public static function adminInitialize(): void{
		self::registerSettings();
	}

	/**
	 * Record every request in the database
	 */
	public static function handleRequest(): void{
		global $wpdb;
		$record=TrafficMonitorRecord::fromGlobals();
		$trafficDb=new TrafficMonitorDatabase($wpdb);
		if(!$trafficDb->record($record))
			error_log('Failed to record traffic monitor record');
	}

	/**
	 * Purge records that exceed the retention duration
	 */
	public static function purgeRecords(): void{
		global $wpdb;
		$trafficDb=new TrafficMonitorDatabase($wpdb);
		$date=new DateTime();
		$date->sub(new DateInterval(get_option(self::OPTION_RETENTION_PERIOD, self::DEFAULT_RETENTION_PERIOD)));
		if(!$trafficDb->purgeRecords($date))
			error_log('Failed to purge traffic monitor records');
	}

	/**
	 * Add the necessary cron interval for the record purge
	 */
	public static function addCronInterval(array $schedules): array{
		$schedules['every_minute']=[
			'interval'=>60,
			'display'=>'Every Minute'
		];
		return $schedules;
	}

}

register_activation_hook(__FILE__, ['TrafficMonitorPlugin', 'activate']);
register_deactivation_hook(__FILE__, ['TrafficMonitorPlugin', 'deactivate']);
register_uninstall_hook(__FILE__, ['TrafficMonitorPlugin', 'uninstall']);

add_filter('plugins_loaded', ['TrafficMonitorPlugin', 'handleRequest']);
add_filter('cron_schedules', ['TrafficMonitorPlugin', 'addCronInterval']);
add_action('init', ['TrafficMonitorPlugin', 'initialize']);
add_action('admin_init', ['TrafficMonitorPlugin', 'adminInitialize']);
add_action('traffic_monitor_purge', ['TrafficMonitorPlugin', 'purgeRecords']);
