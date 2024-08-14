<?php

namespace AlanBlair\TrendsIntegration\App\Products;

use AlanBlair\TrendsIntegration\App\API\API_Controller;
use AlanBlair\TrendsIntegration\App\ResponseDataVO;
use AlanBlair\TrendsIntegration\App\ResponseStatusMap;
use AlanBlair\TrendsIntegration\Categories\CategoriesModel;

class ProductsController
{
    const PRODUCT_PROCESS_QUEUE_TRANSIENT = 'trends_process_products';
    public static function process_categories()
    {
        $api_status = get_transient(API_Controller::TRANSIENT_FETCH_STATUS);
        if($api_status !== API_Controller::STATUS_COMPLETED){
            return  new \WP_Error(ResponseStatusMap::STATUS_CRITICAL, 'API Fetch is not complete');
        }
        $categories = API_Controller::get_instance()->get_categories();
        return CategoriesModel::get_instance()->update_categories($categories);
    }

    public static function reset_queue(){
        /*@todo remove this */
        delete_transient(self::PRODUCT_PROCESS_QUEUE_TRANSIENT);

        ProductsModel::get_instance()->delete_all_rows_from_trends_product_process_queue();
    }

    public static function process_next_product()
    {
        $pm = ProductsModel::get_instance();
        $next_product = $pm->get_row_from_trends_product_process_queue(true);
        if(!$next_product){
            return new ResponseDataVO(
                null,
                ResponseStatusMap::STATUS_DONE,
                'No products in queue'
            );
        }

        if ('Active' !== $next_product->active) {
            /* Disable product if it has already been imported */
            $existing_post_id = $pm->get_post_id_by_supplier_unique_id($next_product->code);
            if(is_wp_error($existing_post_id)){
                $message = "Product inactive, but not imported to Woocommerce";
                $status = ResponseStatusMap::STATUS_DOES_NOT_EXIST;
            }else{
                self::set_product_to_draft($existing_post_id);
                $message = "Product has been set to draft";
                $status = ResponseStatusMap::STATUS_UNPUBLISHED;
            }

            /* Return  response */
            return new ResponseDataVO(
                null,
                $status,
                $message
            );
        }

        $prepared_product = $pm->prepare_product($next_product);

        /* check if the product already exists */
        $post_id = $pm->get_post_id_by_supplier_unique_id($prepared_product['supplier_unique_id']);

        /* Product not yet in the map */
        if(is_wp_error($post_id)) {
            /* Import the product */
            $importer = new ProductImporter();
            $existing_post_id = $importer->import_product($prepared_product);
            $pm->add_product_to_map($prepared_product['supplier_unique_id'], $existing_post_id);
            return $prepared_product;
        }else{
            /* Product is already in the map */
            return new ResponseDataVO(
                null,
                ResponseStatusMap::STATUS_ALREADY_EXISTS,
                $prepared_product['supplier_unique_id'] . ' has already been imported'
            );
        }
    }


    /*
     * Use to set a product status to draft if it no longer exists in the Trends data
     */
    private static function set_product_to_draft($product_id) {
        // Check if the given ID is a valid product
        if ('product' === get_post_type($product_id)) {
            wp_update_post(array(
                'ID' => $product_id,
                'post_status' => 'draft'
            ));
            return true; // successfully set to draft
        }
        return false; // not a product or update failed
    }

    /*
     * Get an array of IDs for Trends products that are published
     */
    private static function get_product_ids_by_supplier_name($supplier_name) {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,  // Retrieve all products with the specified meta value.
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => 'supplier_name',
                    'value' => $supplier_name
                )
            ),
            'fields'         => 'ids',  // Only retrieve the IDs
        );

        $products = get_posts($args);

        return $products;  // This will be an array of product IDs
    }
}