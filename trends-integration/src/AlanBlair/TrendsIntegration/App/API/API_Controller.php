<?php
/*
 * This class is to interact with the Trends API
 * Interactions are saved as WordPress Transients
 * @see https://www.trends.nz/api
 */
namespace AlanBlair\TrendsIntegration\App\API;

use AlanBlair\TrendsIntegration\App\Products\ProductsModel;
use AlanBlair\TrendsIntegration\App\ResponseDataVO;
use AlanBlair\TrendsIntegration\App\ResponseStatusMap;

class API_Controller
{
    const STATUS_IDLE = 'idle';
    const STATUS_FETCHING = 'fetching';
    const STATUS_ERROR = 'error';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const TRANSIENT_FETCH_STATUS = 'trends_fetch_status';
    const TRANSIENT_LAST_ERROR = 'trends_fetch_last_error';
    const TRANSIENT_PAGE_CURRENT = 'trends_fetch_page_current';
    const TRANSIENT_PAGE_COUNT = 'trends_fetch_page_count';
    const TRANSIENT_PAGE_SIZE = 'trends_fetch_page_size';
    const TRANSIENT_TOTAL_ITEMS = 'trends_fetch_total_items';
    const TRANSIENT_FETCHED_PRODUCTS = 'trends_fetched_products';
    const TRANSIENT_FETCHED_PRODUCTS_COUNT = 'trends_fetched_products_count';
    const TRANSIENT_REMOVED_PRODUCTS = 'trends_removed_products';
    const TRANSIENT_ADDED_PRODUCTS = 'trends_fetch_added_products';

    private static $_instance = null;

    public static function get_instance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    public function __construct()
    {

    }

    /*
     * Reset all transients
     */
    public function reset()
    {
        set_transient(self::TRANSIENT_FETCH_STATUS, self::STATUS_IDLE);
        delete_transient(self::TRANSIENT_LAST_ERROR);
        set_transient(self::TRANSIENT_PAGE_CURRENT, 1);
        set_transient(self::TRANSIENT_PAGE_COUNT, 0);
        set_transient(self::TRANSIENT_PAGE_SIZE, 100);
        set_transient(self::TRANSIENT_TOTAL_ITEMS, 0);

        set_transient(self::TRANSIENT_FETCHED_PRODUCTS, []);
        set_transient(self::TRANSIENT_REMOVED_PRODUCTS, []);
        set_transient(self::TRANSIENT_ADDED_PRODUCTS, []);
        set_transient(self::TRANSIENT_FETCHED_PRODUCTS_COUNT, 0);
    }

    /**
     * @return array
     */
    public function cancel(): array
    {
        $this->reset();
        $this->_set_status(self::STATUS_CANCELLED);
        return $this->get_product_stats();
    }

    /*
     * Restarts the fetch process
     * On next fetch call we should get the next page;
     */
    public function  restart()
    {
        $this->reset();
        $this->_set_status(self::STATUS_FETCHING);
        return $this->get_product_stats();
    }

    /**
     * Get all products from the Trends Endpoint and return the Product Stats or a WP_Error.
     * @return array|bool|\WP_Error
     */
    public function fetch_all_pages(): \WP_Error|bool|array
    {
        $this->reset();
        $this->_set_status(self::STATUS_FETCHING);
        while (self::STATUS_FETCHING === get_transient(self::TRANSIENT_FETCH_STATUS)) {
            $result = $this->fetch_next_products_page();
            if (is_wp_error($result)) {
                return $result;
                break;
            }
        }
        return $this->get_product_stats();
    }

    /**
     * Fetch the next page from Trends endpoint
     * Increment the Current Page number if the is not the last page.
     *
     */
    public function fetch_next_products_page()
    {
        if(get_transient(self::TRANSIENT_FETCH_STATUS) !== self::STATUS_FETCHING){
            return false;
        }

        $api = TrendsAPI::get_instance();
        $url = get_option('trends_products_url');
        $user = get_option('trends_api_username');
        $pass = get_option('trends_api_password');
        $include_inactive = get_option('trends_api_include_inactive');


        $page = get_transient(self::TRANSIENT_PAGE_CURRENT);
        $full_url = $url . '?page_no=' . $page;
        if(!empty($include_inactive)){
            $full_url.= '&inc_inactive=1';
        }


        $result = $api->request($full_url, 'GET', $user, $pass);

        if (is_wp_error($result)) {
            $this->_set_error($result->get_error_messages());
            return $result;
        } else {
            /*@todo - make this the standard */
            $added = ProductsModel::get_instance()->add_product_array_to_process_queue($result['data']);
            if(is_wp_error($added)){
                return $added;
            }

            $this->_append_products($result['data']);
            set_transient(self::TRANSIENT_PAGE_COUNT, $result['page_count']);
            set_transient(self::TRANSIENT_TOTAL_ITEMS, $result['total_items']);
            /*
            If in DEBUG Mode get only the first page
            Otherwise get all pages.
            */
            if ($result['page_count'] == $page || TRENDS_DEBUG) {
                $this->_set_status(self::STATUS_COMPLETED);
                return new ResponseDataVO(
                    $this->get_product_stats(),
                    ResponseStatusMap::STATUS_DONE,
                    'done'
                );
            } else {
                $this->_increment_current_page();
            }
        }
        return $this->get_product_stats();
    }

    private function _set_status($status)
    {
        set_transient(self::TRANSIENT_FETCH_STATUS, $status);
    }

    private function _set_error($message)
    {
        $this->_set_status(self::STATUS_ERROR);
        set_transient(self::TRANSIENT_LAST_ERROR, $message);
    }

    private function _set_current_page($page)
    {
        set_transient(self::TRANSIENT_PAGE_CURRENT, $page);
    }

    private function _increment_current_page()
    {
        $page = get_transient(self::TRANSIENT_PAGE_CURRENT);
        $page++;
        $this->_set_current_page($page);
    }


    private function _append_products(array $array)
    {
        $products = get_transient(self::TRANSIENT_FETCHED_PRODUCTS);
        $new_array = array_merge($products, $array);
        set_transient(self::TRANSIENT_FETCHED_PRODUCTS, $new_array);
        set_transient(self::TRANSIENT_FETCHED_PRODUCTS_COUNT, count($new_array));
    }

    /**
     * @return array
     */
    public function get_product_stats()
    {
        return [
            'status' => get_transient(self::TRANSIENT_FETCH_STATUS),
            'last_error' => get_transient(self::TRANSIENT_LAST_ERROR),
            'current_page' => get_transient(self::TRANSIENT_PAGE_CURRENT),
            'page_count' => get_transient(self::TRANSIENT_PAGE_COUNT),
            'fetched_product_count' => get_transient(self::TRANSIENT_FETCHED_PRODUCTS_COUNT),
            'total_product_count' => get_transient(self::TRANSIENT_TOTAL_ITEMS),
            'added_products' => get_transient(self::TRANSIENT_ADDED_PRODUCTS),
            'removed_products' => get_transient(self::TRANSIENT_REMOVED_PRODUCTS)
        ];
    }

    /**
     * @return array
     */
    public function get_products()
    {
        return get_transient(self::TRANSIENT_FETCHED_PRODUCTS);
    }

    /**************
     * CATEGORIES
     */
    public function get_categories(){
        $url = get_option('trends_categories_url');
        $user = get_option('trends_api_username');
        $pass = get_option('trends_api_password');
        $result = TrendsAPI::get_instance()->request($url, 'GET', $user, $pass);
        return $result['data'];
    }

}