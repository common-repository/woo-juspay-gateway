<?php
/**
 * WooCommerce Juspay Plugin.
 *
 * @class       WC_Juspay
**/


final class WC_Juspay {

	/**
	 * @var plugin name 
	 */
	public $name = 'WooCommerce Gateway Juspay';


	/**
	 * @var plugin version 
	 */
	public $version = '1.0.5';


	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	protected static $_instance = null;


	/**
	 * @var plugin settings 
	 */
	protected static $settings = null;


	/**
	 * @var available api environments
	 */
	public static $environments = array(
		'axis' => array(
			'name' => 'Axis',
			'endpoint' => 'https://axisbank.juspay.in'
		),
		'staging' => array(
			'name' => 'Staging',
			'endpoint' => 'https://sandbox.juspay.in'
		),
		'production' => array(
			'name' => 'Production',
			'endpoint' => 'https://api.juspay.in'
		)
	);


	public static $log = false;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance()
	{
		if (is_null (self::$_instance )) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}


	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {}


	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {}


	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	private function __construct() {	
		$this->define_constants();
		$this->init_settings();
		$this->includes();
		$this->register_hooks();
	}


	/**
	 * Define constants
	 */
    private function define_constants() {
		define( 'WC_JUSPAY_DIR'				, plugin_dir_path( WC_JUSPAY_PLUGIN_FILE ) );
		define( 'WC_JUSPAY_URL'				, plugin_dir_url( WC_JUSPAY_PLUGIN_FILE ) );
		define( 'WC_JUSPAY_BASENAME'		, plugin_basename( WC_JUSPAY_PLUGIN_FILE ) );
		define( 'WC_JUSPAY_VERSION'			, $this->version );
		define( 'WC_JUSPAY_NAME'			, $this->name );
	}


	private function init_settings() {
		if ( is_null( self::$settings ) ) {
			self::$settings = get_option( 'woocommerce_juspay_settings', array() );
		}
	}

	/**
	 * Include plugin dependency files
	 */
    private function includes() {
		require( WC_JUSPAY_DIR . '/vendor/autoload.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-juspay-utils.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay-request.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay-response.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay-webhook-handler.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay-return-handler.php' );
		require( WC_JUSPAY_DIR . '/includes/class-wc-gateway-juspay-manual-handler.php' );

		$this->init_juspay_sdk();

		// load manual order handler
		new WC_Gateway_Juspay_Manual_Handler();
	}


	/**
	 * Register hooks
	 */
    private function register_hooks() {
		add_action('woocommerce_payment_gateways', array($this, 'add_gateway'), 0);
		add_filter('plugin_action_links_'. WC_JUSPAY_BASENAME, array($this, 'plugin_action_links'));
		// add_action('admin_init', array($this, 'debug_order'), 0);
	}


	/**
	 * Intialize juspay php sdk
	 */
	public function init_juspay_sdk() {
		$environment = self::get_option( 'environment' ) && isset( WC_Juspay::$environments[ self::get_option( 'environment' ) ] ) ? self::get_option( 'environment' ) : 'staging';

		if ( self::get_option( $environment . '_api_key' ) ) {
			Juspay\JuspayEnvironment::init()->withApiKey( self::get_option( $environment . '_api_key' ) );
		}

		Juspay\JuspayEnvironment::init()->withBaseUrl( WC_Juspay::$environments[$environment]['endpoint'] );
	}


	/**
	 * Add the gateways to WooCommerce.
	 */
    public function add_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Juspay';
        return $methods;
    }


	/**
	 * Adds plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$links['settings'] = '<a href="'. admin_url( 'admin.php?page=wc-settings&tab=checkout&section=juspay' ) .'">' . __('Settings', 'juspay') . '</a>';
		return $links;
	}


	/**
	 * Get option from plugin settings
	 */
	public static function get_option( $name, $default = null) {
		if ( ! empty( self::$settings ) ) {
			if ( array_key_exists( $name, self::$settings ) ) {
				return self::$settings[$name];
			}
		}

		return $default;
	}


	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( 'yes' === self::get_option( 'debug', 'yes' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}

			self::$log->log( $level, $message, array( 'source' => 'juspay' ) );
		}
	}


	// Debugging
    public function debug_order() {
		$order = wc_get_order('14352');
		echo WC()->countries->countries[ $order->get_billing_country() ];
		WC_Juspay_Utils::d($order);
	}
}
