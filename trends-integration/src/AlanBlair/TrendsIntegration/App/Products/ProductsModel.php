<?php

namespace AlanBlair\TrendsIntegration\App\Products;

use AlanBlair\TrendsIntegration\App\API\TrendsAPI;
use AlanBlair\TrendsIntegration\App\ResponseStatusMap;

/*
 * @see https://www.trends.nz/api
 * Note: Products API is not working as documented
 * 1. If you specify a page size the pagination does not work
 *
 */

class ProductsModel
{
    private $_markup;

    const supplier_name = 'trends';

    private static $_instance;
    public static function get_instance()
    {
        if(!self::$_instance){ self::$_instance = new self(); }
        return self::$_instance;
    }

    public function __construct()
    {
        TrendsAPI::get_instance();
        $this->_markup = get_option('trends_api_standard_markup_percent');
    }

    /*
     * Just gets the products fom the API
     * Products will be parsed and added to Woo individually
     * As it will take too long to process in a single call
     *
     * NOTE: Trends API fails with any page size other than 250
     */
    public function get_products(int $page = 1)
    {
        $url = get_option('trends_products_url');
        $user = get_option('trends_api_username');
        $pass = get_option('trends_api_password');
        $full_url = $url . '?page_no=' . $page;

        return TrendsAPI::get_instance()->request($full_url, 'GET', $user, $pass);

    }

    public function prepare_product($product)
    {

        $single_product = array();

        # Product main data
        $single_product['is_variable'] = false;
        $single_product['title'] = isset($product->name) ? $product->name : '';
        $single_product['description'] = isset($product->description) ? $product->description : '';
        $single_product['additional_details'] = '';

        # Product Additional details
        if (isset($product->colours)) {

            $single_product['additional_details'] .= '<strong>Colours: </strong>' . $product->colours;

            if (!empty($product->secondary_colours)) {
                $single_product['additional_details'] .= '<br>' . $product->secondary_colours;
            }
        }

        if (isset($product->dimensions) && is_array($product->dimensions)) {
            $filtered_dimensions = array_filter($product->dimensions);

            $single_product['additional_details'] .= '<br></br><strong>Dimensions: </strong>';
            $single_product['additional_details'] .= '<ul class="kotuku-list">';
            foreach ($filtered_dimensions as $dimension) {
                $single_product['additional_details'] .= '<li>' . $dimension . '</li>';
            }
            $single_product['additional_details'] .= '</ul>';
        }

        if (isset($product->branding_options) && is_array($product->branding_options)) {

            $single_product['additional_details'] .= '<br></br><strong>Branding Options: </strong>';
            $single_product['additional_details'] .= '<ul class="kotuku-list">';
            foreach ($product->branding_options as $branding_options) {
                $single_product['additional_details'] .= '<li><strong>' . $branding_options->print_type . ': </strong>' . $branding_options->print_description . '</li>';
            }
            $single_product['additional_details'] .= '</ul>';
        }

        if (isset($product->carton)) {

            $product_packaging = !empty($product->packaging) ? $product->packaging : '';
            $single_product['additional_details'] .= '<br></br><strong>Packaging: </strong>' . $product_packaging;

            $single_product['additional_details'] .= '<ul class="kotuku-list">';

            $carton_dimensions = array();
            if (!empty($product->carton->length)) {
                $carton_dimensions[] = $product->carton->length . ' cm';
            }
            if (!empty($product->carton->width)) {
                $carton_dimensions[] = $product->carton->width . ' cm';
            }
            if (!empty($product->carton->height)) {
                $carton_dimensions[] = $product->carton->height . ' cm';
            }
            if (count($carton_dimensions) > 0) {
                $single_product['additional_details'] .= '<li><strong>Carton Dimensions: </strong>' . implode(' x ', $carton_dimensions) . '</li>';
            }

            if (!empty($product->carton->quantity)) {
                $single_product['additional_details'] .= '<li><strong>Carton Quantity: </strong>' . $product->carton->quantity . ' pieces</li>';
            }

            if (!empty($product->carton->weight)) {
                $single_product['additional_details'] .= '<li><strong>Carton Weight: </strong>' . $product->carton->weight . 'kg</li>';
            }

            $single_product['additional_details'] .= '</ul>';
        }

        if (!empty($product->materials) && !is_array($product->materials) && !is_object($product->materials)) {
            $single_product['additional_details'] .= '<br></br><strong>Materials: </strong>' . $product->materials;
        }

        if (!empty($product->specifications) && !is_array($product->specifications) && !is_object($product->specifications)) {
            $single_product['additional_details'] .= '<br></br><strong>Specifications: </strong>' . $product->specifications;
        }

        # Product Images
        $single_product['gallery'] = array();
        if (count($product->images) > 0) {
            $i = 0;
            foreach ($product->images as $image) {
                $i++;
                if ($i === 1) {
                    $single_product['featured_image'] = $image;
                    continue;
                }

                $single_product['gallery'][] = $image;
            }
        }

        # Product categories
        $single_product['categories'] = array();
        if (count($product->categories) > 0) {
            foreach ($product->categories as $category) {
                $single_product['categories'][] = $category->name;
            }
        }

        # Product pricing rules
        $price_after = isset($product->pricing->prices[0]->price) ? ($this->_do_price_markup($product->pricing->prices[0]->price)) : 0;
        $single_product['price'] = round($price_after, 2);
        $single_product['prices'] = array();
        if (count($product->pricing->prices) > 0) {
            $min_start = 0;
            foreach ($product->pricing->prices as $rule) {

                $prices_after = ($rule->price + $this->_markup / 100);
                $single_product['prices'][] = array(
                    'min' => $min_start,  // This is calculated manually based on previous max quantity
                    'max' => $rule->quantity,
                    'price' => round($prices_after, 2),
                );
                $min_start = $rule->quantity - 1;
            }
        }

        $single_product['additional_costs'] = array();
        if (count($product->pricing->additional_costs) > 0) {
            foreach ($product->pricing->additional_costs as $add_cost) {

                $unit_price_after = !empty($add_cost->unit_price) ? $this->_do_price_markup($add_cost->unit_price) : 0;
                $setup_price_after = !empty($add_cost->setup_price) ? $this->_do_price_markup($add_cost->setup_price) : 0;

                $single_product['additional_costs'][] = array(
                    'label' => $add_cost->description,  // This is calculated manually based on previous max quantity
                    'unit_price' => round($unit_price_after, 2),
                    'setup_price' => round($setup_price_after, 2),
                );
            }
        }

        # Handle Variables ( because of Woo limitations we can't use variable product type with these products, so we'll turn the variables into options )
        // Colours
        $colours = array();
        if (!empty($product->colours)) {

            $product_colours = $product->colours;
            $is_colours_mix = false;

            preg_match('/Mix and Match - /', $product_colours, $matchesMix);
            if (!empty($matchesMix[0])) {
                $is_colours_mix = true;
                $product_colours = str_replace($matchesMix[0], '', $product_colours);
            } else {
                preg_match('/Mix and Match: /', $product_colours, $matchesMix2);
                if (!empty($matchesMix2[0])) {
                    $is_colours_mix = true;
                    $product_colours = str_replace($matchesMix2[0], '', $product_colours);
                }
            }

            $product_colours_array = explode('.', $product_colours);
            array_pop($product_colours_array);

            foreach ($product_colours_array as $colour_part) {
                $colours_op = array();
                $colour_label = '';

                preg_match('/.+?(\w+: ?)/', $colour_part, $matches);
                if (!empty($matches[0])) {
                    $colour_label = $matches[0];
                    $colour_part = str_replace($matches[0], '', $colour_part);
                }

                $colours_array = explode(',', $colour_part);

                if (empty($colour_label)) {
                    $colours_op['label'] = 'Colour';
                } else {
                    $colours_op['label'] = str_replace(':', '', $colour_label);
                }

                $colours_op['options'] = $colours_array;

                if ($is_colours_mix) {

                    $primary_clabel = $colours_op['label'] . ' (primary)';
                    $secondary_clabel = $colours_op['label'] . ' (secondary)';

                    $colours_op['label'] = trim($primary_clabel);
                    $colours[] = $colours_op;

                    $colours_op['label'] = trim($secondary_clabel);
                    $colours[] = $colours_op;
                } else {

                    $colours[] = $colours_op;
                }
            }
        }

        // Secondary Colours
        $scolours = array();
        if (!empty($product->secondary_colours)) {

            $sproduct_colours = $product->secondary_colours;
            $is_colours_mix = false;

            preg_match('/Mix and Match - /', $sproduct_colours, $smatchesMix);
            if (!empty($smatchesMix[0])) {
                $is_colours_mix = true;
                $sproduct_colours = str_replace($smatchesMix[0], '', $sproduct_colours);
            } else {
                preg_match('/Mix and Match: /', $sproduct_colours, $smatchesMix2);
                if (!empty($smatchesMix2[0])) {
                    $is_colours_mix = true;
                    $sproduct_colours = str_replace($smatchesMix2[0], '', $sproduct_colours);
                }
            }

            $sproduct_colours_array = explode('.', $sproduct_colours);
            array_pop($sproduct_colours_array);

            foreach ($sproduct_colours_array as $scolour_part) {
                $scolours_op = array();
                $scolour_label = '';

                preg_match('/.+?(\w+: ?)/', $scolour_part, $smatches);
                if (!empty($smatches[0])) {
                    $scolour_label = $smatches[0];
                    $scolour_part = str_replace($smatches[0], '', $scolour_part);
                }

                $scolours_array = explode(',', $scolour_part);

                if (empty($scolour_label)) {
                    $scolours_op['label'] = 'Colour';
                } else {
                    $scolours_op['label'] = str_replace(':', '', $scolour_label);
                }

                $scolours_op['options'] = $scolours_array;

                if ($is_colours_mix) {

                    $sprimary_clabel = $scolours_op['label'] . ' (primary)';
                    $ssecondary_clabel = $scolours_op['label'] . ' (secondary)';

                    $scolours_op['label'] = trim($sprimary_clabel);
                    $scolours[] = $scolours_op;

                    $scolours_op['label'] = trim($ssecondary_clabel);
                    $scolours[] = $scolours_op;
                } else {

                    $scolours[] = $scolours_op;
                }
            }
        }


        // Merge primary and secondary colors array into one.
        $single_product['colours'] = array_merge($colours, $scolours);
        // possible variables are: colours, sizing

        // Meta data
        $single_product['supplier_unique_id'] = $product->code;
        $single_product['supplier_name'] = self::supplier_name;

        // Wireframe PDFs
        if (!empty($product->product_wire)) {
            $single_product['kotuku_product_wire'] = $product->product_wire;
        }

        $single_product['meta_data'] = array();

        $single_product['meta_data'][] = array(
            'key' => 'supplier_name',
            'value' => self::supplier_name,
        );

        $single_product['meta_data'][] = array(
            'key' => 'supplier_unique_id',
            'value' => $product->code,
        );
        $single_product['meta_data'][] = array(
            'key' => 'supplier_last_updated',
            'value' => $product->last_updated,
        );
        if (!empty($product->product_wire)) {
            $single_product['meta_data'][] = array(
                'key' => 'kotuku_product_wire',
                'value' => $product->product_wire,
            );
        }
        if (!empty($product->sizing)) {
            $sizes = array();

            foreach ($product->sizing as $sizing) {
                $sizes[] = explode(',', str_replace('cm', '(cm)', $sizing->sizing_line));
            }
            $single_product['meta_data'][] = array(
                'key' => 'kotuku_product_sizing',
                'value' => $sizes,
            );
        }
        return $single_product;
    }

    private function _do_price_markup($price)
    {
        return number_format($price + (($this->_markup / 100) * $price));
    }


    /*#################################
     DB FUNCTIONS
    #################################*/

    function add_product_to_map($supplier_unique_id, $post_id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'trends_product_map';

        $result = $wpdb->replace(
            $table_name,
            array(
                'post_id' => $post_id,
                'supplier_unique_id' => $supplier_unique_id
            ),
            array('%d', '%d')  // data format
        );

        if (!$result) {
            return new \WP_Error( ResponseStatusMap::STATUS_WARNING, 'Error adding item to database.');
        }
        return true;
    }

    function get_post_id_by_supplier_unique_id($supplier_unique_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'trends_product_map';

        // Fetch post_id based on supplier_unique_id
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $table_name WHERE supplier_unique_id = %d",
            $supplier_unique_id
        ));

        if (null === $post_id) {
            return new \WP_Error(ResponseStatusMap::STATUS_WARNING, 'No post ID found for the given supplier_unique_id.');
        }

        return $post_id;
    }


    function add_product_to_process_queue($product){
        global $wpdb;

        $table_name = $wpdb->prefix . 'trends_product_process_queue';

        // Use the REPLACE method
        $result = $wpdb->replace(
            $table_name,
            array('product' => $product),
            array('%s')  // data format for TEXT
        );

        if (!$result) {
            return new \WP_Error(ResponseStatusMap::STATUS_WARNING, 'Error replacing item in the database.');
        }

        return true;
    }

    function add_product_array_to_process_queue($products){
        foreach ($products as $product){
            $result = $this->add_product_to_process_queue(json_encode($product));
            if(is_wp_error($result)){
                return $result;
                break;
            }
        }
    }

    function get_row_from_trends_product_process_queue($remove_after_get = false) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'trends_product_process_queue';

        $query = "SELECT * FROM $table_name LIMIT 1";


        $row = $wpdb->get_row($query);

        if (!$row) {
            return new \WP_Error(ResponseStatusMap::STATUS_WARNING, 'Row not found in the database.');
        }
        if($remove_after_get){
            $this->delete_row_from_trends_product_process_queue($row->id);
        }

        return json_decode($row->product);
    }

    function delete_row_from_trends_product_process_queue($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'trends_product_process_queue';

        // Delete the row based on ID
        $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

        if (!$result) {
            return new \WP_Error(ResponseStatusMap::STATUS_WARNING, 'Error deleting row from the database or row does not exist.');
        }

        return true;
    }

    function delete_all_rows_from_trends_product_process_queue() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'trends_product_process_queue';

        // Delete all rows from the table
        $result = $wpdb->query("DELETE FROM $table_name");

        if ($result === false) {
            return new \WP_Error(ResponseStatusMap::STATUS_WARNING, 'Error deleting rows from the database.');
        }

        return true;
    }


}