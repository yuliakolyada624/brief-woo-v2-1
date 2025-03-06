<?php

/**
 * BriefWoo
 * 
 * @since             1.0.0
 * @package           BriwfWoo
 *
 * Plugin Name:       Brief Woo
 * Description:       Plugin for ordering briefs online.
 * Version:           1.0.0
 * Author:            BriefWoo
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       brief-woo
 * Domain Path:       /languages
 */

use BriefWOO\Hooks;
use BriefWOO\ShortCodes;

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

error_log("PHP is running this script", true);

define('BRIEFWOO_ENV', 'production'); // development, production
define('BRIEFWOO_DOMAIN', 'brief-woo');
define('BRIEFWOO_DEVELOPMENT_DOMAIN', 'localhost:5173');
define('BRIEFWOO_API_BASE', 'http://64.226.73.245:8000/api');
define('BRIEFWOO_API_TOKEN', '4ZKR53DGrybx4e58rROyutGjmbx1KOAoIQimqeqzVsycc96Svdk7LVsxGtCymBz5');
define('BRIEFWOO_VERSION', '1.0.0');
define('BRIEFWOO_PREFIX', 'brief-woo');
define('BRIEFWOO_DIR', plugin_dir_path(__FILE__));
define('BRIEFWOO_FILE', plugin_basename(__FILE__));
define('BRIEFWOO_URL', plugin_dir_url(__FILE__));
define('BRIEFWOO_PRODUCT_ID', 608);

require_once BRIEFWOO_DIR . 'vendor/autoload.php';

if (!defined('WP_UNINSTALL_PLUGIN')) {
  ShortCodes::register();
  Hooks::register();
}