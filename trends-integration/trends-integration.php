<?php
/**
* @wordpress-plugin
* Plugin Name:       Trends Integration
* Plugin URI:        kotukuworkwear.com.au
* Description:       Plugin to handle the integrations between Kotuku and Trends APIs
* Version:           1.0.0
* Author:            Alan Blair
* Author URI:        https://www.wpeasy.au
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       trends-integration
* Domain Path:       /languages
*/
// If this file is called directly, abort.
if (!defined('WPINC')) { die; }

define('TRENDS_PLUGIN_FILE', __FILE__);
define('TRENDS_DEBUG', false);
define('TRENDS_LOGGING', true);

require_once __DIR__ .'/vendor/autoload.php';



$app = \AlanBlair\TrendsIntegration\App\ApplicationController::get_instance();
$app->set_config(require __DIR__ .'/config.php')
    ->run();


/* Temp to update all product categories */
if(isset($_GET['parse-parents'])){
    add_action('init', function(){
        $pi = new \AlanBlair\TrendsIntegration\App\Products\ProductImporter();

        // Get all products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
        );
        $products = get_posts($args);

        foreach ($products as $product) {
            $product_id = $product->ID;
            $pi->include_parent_categories_for_product($product_id);
        }
    });

}