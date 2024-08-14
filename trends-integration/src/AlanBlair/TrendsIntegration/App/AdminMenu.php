<?php

namespace AlanBlair\TrendsIntegration\App;

class AdminMenu
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'register_admin_scripts']);
        add_action('init' , [$this, 'maybe_render_admin']);

    }

    public function register_admin_scripts()
    {
        $conf = ApplicationController::get_instance()->get_config();
        wp_register_script('trends-admin', $conf['url'] . 'admin/app/assets/index.js', [], false, true);
        wp_register_style('trends-admin', $conf['url'] . 'admin/app/assets/index.css');

        $map = new ResponseStatusMap();
        $arr = $map->get_map();

        wp_localize_script(
            'trends-admin',
            'trends_admin',
            ApplicationController::get_instance()->get_js_variables()
        );
    }

    function add_admin_menu()
    {
        add_menu_page(
            'Trends Integration', // Page title
            'Trends Integration', // Menu title
            'manage_options', // Capability
            'trends-integration', // Menu slug
            [$this, 'admin_page'], // Callback function
            'dashicons-chart-line', // Icon URL
            30 // Position
        );

    }

    function admin_page()
    {
        ?>
        <div class="wrap">
            <h3>Trends API Integration Admin</h3>
            <iframe id="trends-admin-iframe" src="/?trends-admin=true"></iframe>
        </div>
        <style>
            #trends-admin-iframe{
                width: 100%;
            }
        </style>
        <script>
            let iframe;
            document.addEventListener("DOMContentLoaded", () => {
                iframe = document.getElementById("trends-admin-iframe");

                iframe.onload = handleIframeLoad;

                // Define a callback function for the observer
                const callback = (mutationsList, observer) => {
                    for (const mutation of mutationsList) {
                        if (mutation.type === 'childList') {
                            handleIframeLoad();
                        }
                    }
                };

                setTimeout(()=>{
                    // Options for the observer (which mutations to observe)
                    const config = { childList: true, subtree: true };

                    // Create an instance of the observer with the callback function
                    const observer = new MutationObserver(callback);

                    // Start observing the document with the configured parameters
                    observer.observe(iframe.contentWindow.document.body, config);
                }, 500)


            });

            function handleIframeLoad() {
                //console.info('IFRAME SIZED');
                try {
                    // Destructuring to get the scrollHeight directly
                    const { scrollHeight: height } = iframe.contentWindow.document.body;

                    // Template literals for setting the height
                    iframe.style.height = `${height}px`;
                } catch (e) {

                }
            }

        </script>
        <?php
    }

    public function maybe_render_admin(){
        if(isset($_GET['trends-admin'])){
             $ac= ApplicationController::get_instance()
                ->add_js_variable('rest_url', get_rest_url())
                ->add_js_variable('rest_namespace', 'trends/v1')
                ->add_js_variable('nonce', wp_create_nonce('wp_rest'));

            $trends_admin = $ac->get_js_variables();
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Vite App</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/7.2.96/css/materialdesignicons.min.css" integrity="sha512-LX0YV/MWBEn2dwXCYgQHrpa9HJkwB+S+bnBpifSOTO1No27TqNMKYoAn6ff2FBh03THAzAiiCwQ+aPX+/Qt/Ow==" crossorigin="anonymous" referrerpolicy="no-referrer" />

            </head>
            <body>
            <script>
                const trends_admin = <?php echo json_encode($trends_admin); ?>
            </script>
            <div id="trends_app">Loading</div>
            <script type="module" crossorigin src="/wp-content/plugins/trends-integration/admin/app/assets/index.js"></script>
            <link rel="stylesheet" href="/wp-content/plugins/trends-integration/admin/app/assets/index.css">
            </body>
            </html>


            <?php
            die();
        }

    }
}