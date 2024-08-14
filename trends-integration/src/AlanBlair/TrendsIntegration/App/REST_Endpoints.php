<?php

namespace AlanBlair\TrendsIntegration\App;

use AlanBlair\TrendsIntegration\App\API\API_Controller;
use AlanBlair\TrendsIntegration\App\Products\ProductsController;

class REST_Endpoints
{
    private static $_instance;

    public static function get_instance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_rest_endpoint']);
    }

    function register_rest_endpoint()
    {
        register_rest_route(
            'trends/v1',
            'get_stats',
            array(
                'methods' => 'GET',
                'callback' => [$this, 'api_get_stats'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            )
        );

        register_rest_route(
            'trends/v1',
            'restart_fetch_products',
            array(
                'methods' => 'GET',
                'callback' => [$this, 'api_restart_fetch_products'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            )
        );

        register_rest_route(
            'trends/v1',
            'fetch_next_page',
            array(
                'methods' => 'GET',
                'callback' => [$this, 'api_fetch_next_products_page'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            )
        );

        register_rest_route(
            'trends/v1',
            'process_categories',
            array(
                'methods' => 'GET',
                'callback' => [$this, 'process_categories'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            )
        );

        register_rest_route(
            'trends/v1',
            'process_next_product',
            array(
                'methods' => 'GET',
                'callback' => [$this, 'process_next_product'],

                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }

            )
        );
    }

    function api_get_stats(\WP_REST_Request $request)
    {
        return rest_ensure_response(
            new ResponseDataVO(
                API_Controller::get_instance()->get_product_stats()
            )

        );
    }

    function api_restart_fetch_products()
    {
        ProductsController::reset_queue(); //Empty the queue for processing
        return rest_ensure_response(
            new ResponseDataVO(
                API_Controller::get_instance()->restart()
            )

        );
    }

    function api_fetch_next_products_page()
    {
        $result = API_Controller::get_instance()->fetch_next_products_page();

        /*Error */
        if (is_wp_error($result)) {
            return new \WP_REST_Response( $result, 400 );
        }
        /* ResponseDataVO */
        else if($result instanceof ResponseDataVO){
            return $result;
        }
        /* Other */
        else {
            return rest_ensure_response(
                new ResponseDataVO(
                    $result,
                    ResponseStatusMap::STATUS_OK
                )
            );
        }

    }

    function process_categories()
    {
        $result = ProductsController::process_categories();
        /*Error */
        if (is_wp_error($result)) {
            return new \WP_REST_Response( $result, 400 );
        }
        else if($result instanceof ResponseDataVO){
            return $result;
        }
        else {
            return rest_ensure_response(
                new ResponseDataVO(
                    $result,
                    ResponseStatusMap::STATUS_OK
                )
            );
        }

    }

    function process_next_product()
    {
        $result = ProductsController::process_next_product();
        /*Error */
        if (is_wp_error($result)) {
            return new \WP_REST_Response( $result, 400 );
        }
        else if($result instanceof ResponseDataVO){
            return $result;
        } else {
            return rest_ensure_response(new ResponseDataVO(
                    $result,
                    ResponseStatusMap::STATUS_OK
                )
            );
        }

    }
}