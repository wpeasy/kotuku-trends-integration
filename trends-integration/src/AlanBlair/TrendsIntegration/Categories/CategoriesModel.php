<?php

namespace AlanBlair\TrendsIntegration\Categories;

use AlanBlair\TrendsIntegration\App\API\TrendsAPI;

class CategoriesModel
{
    private static $_instance;
    public static function get_instance()
    {
        if(!self::$_instance){ self::$_instance = new self(); }
        return self::$_instance;
    }

    public function __construct()
    {
        TrendsAPI::get_instance();
    }

    /*
    * Gets categories from API
    * Parses data and updates Woo in one call
    */
    public function update_categories($categories)
    {
        $prepared = $this->_prepare_categories($categories);
        return $this->_run_category_importer($prepared);
    }

    private function _prepare_categories($categories)
    {
        $prepared_data = [];
        foreach ($categories as $category) {

            $single_category = array();
            $single_category['name'] = $category->name;
            $single_category['unique_id'] = $category->number;
            $single_category['children'] = array();

            if (isset($category->sub_categories)) {
                foreach ($category->sub_categories as $sub_category) {
                    $single_category['children'][] = array(
                        'name' => $sub_category->name,
                        'unique_id' => $sub_category->number,
                    );
                }
            }

            $prepared_data[] = $single_category;

        }
        return $prepared_data;
    }

    private function _run_category_importer($cats)
    {
        foreach ($cats as $cat) {

            // Handle parent ( Main categories )
            $cid = wp_insert_term(
                $cat['name'], // the term
                'product_cat'
            );

            if (!is_wp_error($cid)) {
                $cat_id = isset($cid['term_id']) ? $cid['term_id'] : 0;
                update_term_meta($cat_id, 'supplier_unique_id', $cat['unique_id']);
                update_term_meta($cat_id, 'supplier_name', TrendsAPI::supplier_name);
            } else {
                continue;
            }

            // Handle Children
            foreach ($cat['children'] as $cat_child) {
                if (!term_exists($cat['name'], 'product_cat', $cat_id)) {
                    $ccid = wp_insert_term(
                        $cat_child['name'], // the term
                        'product_cat',
                        array(
                            'parent' => $cat_id
                        )
                    );

                    if (!is_wp_error($cid)) {
                        $child_cat_id = isset($ccid['term_id']) ? $ccid['term_id'] : 0;
                        update_term_meta($child_cat_id, 'supplier_unique_id', $cat_child['unique_id']);
                        update_term_meta($cat_id, 'supplier_name', TrendsAPI::supplier_name);
                    }
                }
            }
        }

        return 'Updated Categories';
    }
}