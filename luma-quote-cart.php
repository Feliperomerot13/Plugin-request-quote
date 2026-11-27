<?php
/**
 * Plugin Name: Luma Quote Cart
 * Plugin URI:  https://example.com
 * Description: A lightweight Request a Quote plugin for WooCommerce. Adds "add to quote", mini basket, and email inquiry features. MVP Phase 1.
 * Version:     1.2.0
 * Author:      Luma Studio
 * Author URI:  https://example.com
 * Text Domain: luma-quote-cart
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.5
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define Constants.
define('LUMA_QUOTE_VERSION', '1.2.0');
define('LUMA_QUOTE_PATH', plugin_dir_path(__FILE__));
define('LUMA_QUOTE_URL', plugin_dir_url(__FILE__));
define('LUMA_QUOTE_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class.
 */
class Luma_Quote_Cart
{

	/**
	 * Instance property.
	 *
	 * @var Luma_Quote_Cart
	 */
	private static $instance = null;

	public $settings;
	public $cart;
	public $ajax;
	public $frontend;
	public $mailer;

	/**
	 * Get Class Instance.
	 *
	 * @return Luma_Quote_Cart
	 */
	public static function get_instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		// Checks if WooCommerce is active.
		if (!$this->is_woocommerce_active()) {
			add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
			return;
		}

		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active()
	{
		return class_exists('WooCommerce');
	}

	/**
	 * Show notice if WooCommerce is missing.
	 */
	public function woocommerce_missing_notice()
	{
		?>
		<div class="error">
			<p><?php esc_html_e('Luma Quote Cart requires WooCommerce to be installed and active.', 'luma-quote-cart'); ?></p>
		</div>
		<?php
	}

	/**
	 * Include required core files.
	 */
	private function includes()
	{
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-quote-pdf.php';
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-settings.php';
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-cart.php';
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-ajax.php';
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-frontend.php';
		require_once LUMA_QUOTE_PATH . 'includes/class-lqc-mailer.php';
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks()
	{
		// Localization.
		add_action('plugins_loaded', array($this, 'load_textdomain'));

		// Force session for guests to preserve quote cart.
		add_action('wp_loaded', array($this, 'force_guest_session'));

		// Initialize classes.
		// We can initialize them here or make them singletons/static as needed.
		// For simplicity in MVP, we instantiate them if they need init hooks.

		$this->settings = new Luma_Quote_Cart_Settings();
		$this->cart = new Luma_Quote_Cart_Cart(); // Session handler
		$this->ajax = new Luma_Quote_Cart_Ajax();
		$this->frontend = new Luma_Quote_Cart_Frontend();
		$this->mailer = new Luma_Quote_Cart_Mailer();
	}

	/**
	 * Load text domain.
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('luma-quote-cart', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Force WooCommerce session for guests.
	 */
	public function force_guest_session()
	{
		if (function_exists('WC') && isset(WC()->session) && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
	}
}

// Initialize the plugin.
function luma_quote_cart_init()
{
	return Luma_Quote_Cart::get_instance();
}
add_action('plugins_loaded', 'luma_quote_cart_init');
