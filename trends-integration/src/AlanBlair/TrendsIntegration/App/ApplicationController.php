<?php

namespace AlanBlair\TrendsIntegration\App;

use AlanBlair\TrendsIntegration\App\API\API_Controller;

class ApplicationController
{
    private $_config;
    const ADMIN_NONCE = 'trends-admin-action';

    /* Add any variables to output on the DOM */
    private $_js_variables = [];

    private static $_instance;
    public static function get_instance()
    {
        if(!self::$_instance){ self::$_instance = new self(); }
        return self::$_instance;
    }

    public function __construct()
    {
        register_activation_hook(TRENDS_PLUGIN_FILE, [$this, 'on_activate']);
        $this->add_js_variable('REST_CONSTANTS', ResponseStatusMap::get_map());
        Settings::get_instance();
        AdminMenu::get_instance();
        REST_Endpoints::get_instance();
    }

    public function on_activate()
    {
        API_Controller::get_instance()->reset();
        $this->create_trends_product_map_table();
        $this->create_trends_product_process_queue_table();
    }

    /*
     * JS Variables are for outputting to teh DOM for JS usage
     */
    public function add_js_variable($name, $value){
        $this->_js_variables[$name] = $value;
        return $this;
    }
    public function get_js_variables(){
        return $this->_js_variables;
    }


    /**
     * @param mixed $config
     * @return ApplicationController
     */
    public function set_config($config)
    {
        $this->_config = $config;
        return $this;
    }

    /**
     * @return mixed
     */
    public function get_config()
    {
        return $this->_config;
    }

    public function run()
    {

    }


    /*******************************
     * TABLE CREATION
     */

    function create_trends_product_map_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'trends_product_map';

        $sql = "CREATE TABLE $table_name (
        post_id int NOT NULL,
        supplier_unique_id int NOT NULL UNIQUE PRIMARY KEY
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    function create_trends_product_process_queue_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'trends_product_process_queue';

        $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product TEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


}