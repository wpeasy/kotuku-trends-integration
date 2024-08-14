<?php

namespace AlanBlair\TrendsIntegration\App\Products;

class ProductImporter
{
    const IMPORT_USER_ID = 1;

    public function import_product($product)
    {
        $product_name = $product['title'];
        $product_desc = $product['description'];
        $product_additional_details = $product['additional_details'];

        $post = array(
            'post_author' => self::IMPORT_USER_ID,
            'post_content' => $product_desc,
            'post_status' => "publish",
            'post_title' => $product_name,
            'post_parent' => '',
            'post_excerpt' => $product_additional_details,
            'post_type' => "product",
        );

        //Create product
        $post_id = wp_insert_post($post, false);

        if ($post_id > 0) {

            # Custom options ( related to other plugin )
            update_post_meta($post_id, '_enable_pep', 'yes');
            update_post_meta($post_id, '_enable_price', 'yes');
            update_post_meta($post_id, '_enable_add_to_cart', 'yes');

            # Handle Categories
            $product_categories = $product['categories'];
            $categories = array();
            foreach ($product_categories as $product_category) {

                $product_cat = $product_category;

                $category = get_term_by('name', $product_cat, 'product_cat');

                if (!empty($category)) {

                    $categories[] = $category->term_id;
                } else{
                    $cid = wp_insert_term($product_cat, 'product_cat');
                    if (!is_wp_error($cid)) {
                        $categories[] = isset($cid['term_id']) ? $cid['term_id'] : 0;
                    }
                }
            }

            wp_set_object_terms($post_id, $categories, 'product_cat');

            # Set Price
            $product_price = $product['price'];
            update_post_meta($post_id, '_regular_price', $product_price);
            update_post_meta($post_id, '_price', $product_price);

            # Save prices by quantity
            update_post_meta($post_id, '_kotuku_quantity_pricing', $product['prices']);
            # Save additional costs
            update_post_meta($post_id, '_kotuku_additional_costs', $product['additional_costs']);

            # Save custom meta ( includes sizing )
            foreach ($product['meta_data'] as $product_meta) {
                update_post_meta($post_id, $product_meta['key'], $product_meta['value']);
            }

            # Save Images

            # Import featured image
            $product_image = $product['featured_image']->link;
            $image = $this->is_valid_image($product_image, true);

            if ($image['status']) {
                $this->generate_featured_image($image['link'], $post_id); // Set featured image
            }

            # Import gallery image
            $existing_gallery = get_post_meta($post_id, '_product_image_gallery', true);
            $gallery_parts = explode(',', $existing_gallery);

            if (empty($existing_gallery) || empty($gallery_parts) || array_sum($gallery_parts) == 0) {
                $product_gallery = $product['gallery'];
                $gallery = [];
                foreach ($product_gallery as $gal_img) {
                    $gallery_img = $gal_img->link;
                    $g_image = $this->is_valid_image($gallery_img, true);
                    if ($g_image['status']) {
                        $attach_id = $this->generate_gallery_image($g_image['link'], $post_id);
                        $gallery[] = $attach_id;

                    }
                }
                $img_gallery = implode(',', $gallery);
                update_post_meta($post_id, '_product_image_gallery', $img_gallery); // Set gallery images
            }


            # Save product options ( checkboxes, colors, select...etc ) => colours
            update_post_meta($post_id, '_kotuku_colours', $product['colours']);
        }

        $this->include_parent_categories_for_product($post_id);

        return $post_id;
    }

    private function is_valid_image($file, $url = false)
    {

        if ($url == true) {
            $file = str_replace(" ", "", $file);
            $file = preg_replace('/^(\/\/)/i', "https://", $file);
        }

        if($url){
            $file_headers = @get_headers($file);
            if($file_headers[0] == 'HTTP/1.0 404 Not Found'){ return false; }
        }

        $size = getimagesize($file);

        $response = array(
            'status' => (strtolower(substr($size['mime'], 0, 5)) == 'image' ? true : false),
            'link' => $file
        );

        return $response;
    }

    private function generate_featured_image($image_url, $post_id)
    {

        $upload_dir = wp_upload_dir();
        $filename = basename($image_url);

        $local_url = '';
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $timeout_seconds = 60;
        // Download file to temp dir
        $temp_file = download_url($image_url, $timeout_seconds);
        if (!is_wp_error($temp_file)) {
            file_put_contents(__DIR__ .'/downloaded.log', $filename . "\r\n", FILE_APPEND);

            $file = array(
                'name'     => $filename,
                'tmp_name' => $temp_file,
                'error'    => 0,
                'size'     => filesize($temp_file),
            );

            $overrides = array(
                'test_form' => false,
                'test_size' => true,
            );

            $results = wp_handle_sideload($file, $overrides);
            if (empty($results['error'])) {
                $path  = $results['file']; // Full path to the file
                $local_url = $results['url'];  // URL to the file in the uploads dir
                $type      = $results['type']; // MIME type of the file

            } else {
                return false;
            }
        }else{
            file_put_contents(__DIR__ .'/download_error.log', $filename . "\r\n", FILE_APPEND);
        }

        $file = isset($path) ? $path : '';
        $guid = $local_url;
        if (empty($file) or empty($file)) {
            return false;
        }


        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid' => $guid,
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
        $res2 = set_post_thumbnail($post_id, $attach_id);
    }

    private function generate_gallery_image($image_url, $post_id)
    {

        $upload_dir = wp_upload_dir();
        $filename = basename($image_url);
        $local_url = "";
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $timeout_seconds = 5;
        // Download file to temp dir
        $temp_file = download_url($image_url, $timeout_seconds);
        if (!is_wp_error($temp_file)) {

            $file = array(
                'name'     => $filename,
                'tmp_name' => $temp_file,
                'error'    => 0,
                'size'     => filesize($temp_file),
            );

            $overrides = array(
                'test_form' => false,
                'test_size' => true,
            );

            $results = wp_handle_sideload($file, $overrides);

            if (empty($results['error'])) {
                $path  = $results['file']; // Full path to the file
                $local_url = $results['url'];  // URL to the file in the uploads dir
                $type      = $results['type']; // MIME type of the file

            } else {
                return false;
            }
        }

        $file = isset($path) ? $path : '';
        $guid = $local_url;
        if (empty($file) or empty($file)) {
            return false;
        }

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid' => $guid,
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        $res1 = wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Function to include parent categories for a single WooCommerce product.
     *
     * @param int $product_id The ID of the product to process.
     */
    function include_parent_categories_for_product($product_id) {
        // Get product categories
        $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        
        // Get parent categories for each product category
        $parent_categories = array();
        foreach ($product_categories as $product_category) {
            $parent_categories = array_merge($parent_categories, get_ancestors($product_category, 'product_cat'));
        }


        // Include parent categories for the product
        $product_categories = array_unique(array_merge($product_categories, $parent_categories));

        // Update product categories
        wp_set_post_terms($product_id, $product_categories, 'product_cat');
    }
}