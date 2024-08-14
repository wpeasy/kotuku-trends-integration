<?php

namespace AlanBlair\TrendsIntegration\App;

class Settings
{
    private static $_instance;
    public static function get_instance()
    {
        if(!self::$_instance){ self::$_instance = new self(); }
        return self::$_instance;
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings_fields']);
    }

    public function register_settings_page() {
        add_options_page(
            'Trends Integration',
            'Trends Integration',
            'manage_options',
            'trends-settings',
            [$this, 'render_settings_page']
        );
    }

    function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Trend API Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('trends-settings-group'); ?>
                <?php do_settings_sections('trends-settings'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    function register_settings_fields() {
        register_setting(
            'trends-settings-group',
            'trends_products_url',
            'esc_url_raw'
        );
        register_setting(
            'trends-settings-group',
            'trends_categories_url',
            'esc_url_raw'
        );
        register_setting(
            'trends-settings-group',
            'trends_api_username',
            'sanitize_text_field'
        );
        register_setting(
            'trends-settings-group',
            'trends_api_password',
            'sanitize_text_field'
        );
        register_setting(
            'trends-settings-group',
            'trends_api_standard_markup_percent',
            'absint'
        );
        register_setting(
            'trends-settings-group',
            'trends_api_include_inactive',
            'absint'
        );

        add_settings_section(
            'trends-settings-section',
            'API Settings',
            [$this, 'trends_render_settings_section'],
            'trends-settings'
        );



        add_settings_field(
            'trends-products-url-field',
            'Products URL',
            [$this, 'trends_render_products_url_field'],
            'trends-settings',
            'trends-settings-section'
        );

        add_settings_field(
            'trends-categories-url-field',
            'Categories URL',
            [$this, 'trends_render_categories_url_field'],
            'trends-settings',
            'trends-settings-section'
        );

        add_settings_field(
            'trends-api-username-field',
            'API User Name',
            [$this, 'trends_render_api_username_field'],
            'trends-settings',
            'trends-settings-section'
        );

        add_settings_field(
            'trends-api-password-field',
            'API Password',
            [$this, 'trends_render_api_password_field'],
            'trends-settings',
            'trends-settings-section'
        );


        add_settings_field(
            'trends-api-standard-markup-percent',
            'Base markup percent',
            [$this, 'trends_api_standard_markup_percent_field'],
            'trends-settings',
            'trends-settings-section'
        );

        add_settings_field(
            'trends-api-include-inactive',
            'Include Inactive',
            [$this, 'trends_api_include_inactive_callback'],
            'trends-settings',
            'trends-settings-section'
        );

    }

    function trends_render_settings_section() {
        echo '<p>Enter your API settings below:</p>';
    }

// Render the Products URL field
    function trends_render_products_url_field() {
        $value = get_option('trends_products_url', 'https://au.api.trendscollection.com/api/v1/products.json');
        echo '<input type="text" name="trends_products_url" value="' . esc_attr($value) . '" class="regular-text">';
    }

// Render the Categories URL field
    function trends_render_categories_url_field() {
        $value = get_option('trends_categories_url', 'https://au.api.trendscollection.com/api/v1/categories.json');
        echo '<input type="text" name="trends_categories_url" value="' . esc_attr($value). '" class="regular-text">';
    }

// Render the API Username field
    function trends_render_api_username_field() {
        $value = get_option('trends_api_username', '');
        echo '<input type="text" name="trends_api_username" value="' . esc_attr($value) . '" class="regular-text">';
    }

// Render the API Password field
    function trends_render_api_password_field() {
        $value = get_option('trends_api_password', '');
        echo '<input type="password" name="trends_api_password" value="' . esc_attr($value) . '" class="regular-text">';
    }


    function trends_api_standard_markup_percent_field() {
        $value = get_option('trends_api_standard_markup_percent_field', '60');
        echo '<input type="number" name="trends_api_standard_markup_percent" value="' . esc_attr($value) . '" class="regular-text">';
    }

    function trends_api_include_inactive_callback() {
        $value = get_option('trends_api_include_inactive');
        $checked = isset($value) ? checked(1, $value, false) : '';
        echo "<input type='checkbox' name='trends_api_include_inactive' value='1' $checked />";
    }
}