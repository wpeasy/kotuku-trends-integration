;((w, d) => {
    const FETCH_TIMEOUT = 600000; // 10 minutes
    let products = []; //placeholder for our retrieved products
    let current_page = 1, page_count;
    let addedProductCount = 0;
    let disabledProductCount = 0;

    const totalProductsPlaceholder = document.getElementById('total-products');
    const retrievedProductsPlaceholder = document.getElementById('retrieved-products');
    const addedProductsPlaceholder = document.getElementById('added-products');
    const disabledProductsPlaceholder = document.getElementById('disabled-products');


    /*
    Generic method for XHR calls
     */
    async function doRestAction(url, method = 'GET', data = null) {
        const headers = {
            'X-WP-Nonce': trends_admin.nonce
        };

        const controller = new AbortController();
        const id = setTimeout(() => controller.abort(), FETCH_TIMEOUT);

        try {
            const response = await fetch(url,
                {
                    headers,
                    signal: controller.signal,
                    method: method
                }
            );

            clearTimeout(id);

            if (!response.ok) {
                updateStatusWindow('error', "HTTP error! status: " + response.status );
                throw new Error("HTTP error! status: ${response.status}");
            }
            updateStatusWindow('success', 'Got Data');
            const data = await response.json();
            return data;

        } catch (error) {
            if (error.name === 'AbortError') {
                updateStatusWindow('error', 'Request timed out');
                console.error('Request timed out');
            } else {
                console.error('Error:', error);
            }
        }
    }

    const updateStatusWindow = (mode, message) => {
        const statusWindow = d.getElementById('status-window');
        statusWindow.dataset.statusMode = mode;
        statusWindow.innerText = message;
    }

    const clear_stats = ()=>{
        totalProductsPlaceholder.innerText = '0';
        retrievedProductsPlaceholder.innerText = '0';
        addedProductsPlaceholder.innerText = '0';
        disabledProductsPlaceholder.innerText = '0';

        current_page = 1;
        products = [];
        addedProductCount = 0;
        disabledProductCount = 0;
    }


    const get_product_stats = () => {
        updateStatusWindow('waiting', 'Getting product Stats');

        doRestAction(trends_admin.rest_url + trends_admin.rest_namespace + '/get_stats')
            .then( result => {

            })
    }

    w.addEventListener('DOMContentLoaded', () => {
        d.getElementById('sync-button').addEventListener('click', e => {
            e.preventDefault();
            clear_stats();
            get_product_stats();
        });
    })

})(window, document)