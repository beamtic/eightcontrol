<?php

/**
 *
 *  Class to communicate with the Shopware APIs
 *
 * @Author Jacob (jacobseated@gmail.com)
 */

namespace eightcontrol\shopware;

use Exception;
use eightcontrol\http_client\http_client;
use eightcontrol\php_helpers\php_helpers;
use eightcontrol\file_handler\file_handler;
use eightcontrol\shopware\types\client_credentials;
use eightcontrol\shopware\types\password_auth;
use eightcontrol\shopware\types\site_details;

class admin_api implements admin_interface
{

    private http_client $http;
    private file_handler $fh;
    private php_helpers $helpers;
    private site_details $d;

    /**
     * 
     * @var password_auth|client_credentials
     */
    private $auth;

    /**
     * @throws Exception
     */
    public function __construct($http, $fh, $php_helpers, site_details $site_details, $auth_type)
    {
        $this->d = $site_details;
        $this->http = $http;
        $this->fh = $fh;
        $this->helpers = $php_helpers;
        $this->auth = $auth_type;

        // Attempt to obtain ore refresh auth token if needed
        $this->auth->token();

        if (!file_exists($this->d->base_path() . 'tmp/shopware/')) {
            $this->fh->create_directory('tmp/shopware/', $this->d->base_path());
        }
    }

    /**
     * Update specific fields of a product. The fields to be updated must be provided as a multidimensional array.
     * @param string $product_id 
     * @param array $fields_to_update_arr 
     * @return bool 
     */
    public function update_product(string $product_id, array $fields_to_update_arr)
    {
        return (empty($this->http->patch(
            $this->d->scheme() . '://' . $this->d->host() . $this->d->product_endpoint() . '/' . $product_id,
            $this->d->default_headers() + ['Authorization' => 'Bearer ' . $this->auth->token()->access_token()],
            json_encode($fields_to_update_arr)
        )->body_entity())) ? true : false;
    }

    /**
     * Fetch a single product based on its unique ID
     * @param string $id
     * @return mixed
     */
    public function product_id(string $id)
    {
        return json_decode($this->http->get(
            $this->d->scheme() . '://' . $this->d->host() . $this->d->product_endpoint() . '/' . $id,
            null,
            $this->d->default_headers() + ['Authorization' => 'Bearer ' . $this->auth->token()->access_token()]
        )->body_entity());
    }

    /**
     * Fetches all products from the API, or a combination of limit and page. Warning: Fetching all, without limit or page, can use a lot of system resources depending on total number of products in the store
     * @param int|null $limit
     * @param int|null $page
     * @return mixed
     * @throws Exception
     */
    public function products_fetch_all(int $limit = null, int $page = null)
    {
        $query_string = '';
        if ($limit !== null) {
            $query_string = '?limit=' . $limit;
            if ($page !== null) {
                $query_string .= '&page=' . $page;
            }
        }
        $api_response = json_decode($this->http->get(
            $this->d->scheme() . '://' . $this->d->host() . $this->d->product_endpoint() . $query_string,
            null,
            $this->d->default_headers() + ['Authorization' => 'Bearer ' . $this->auth->token()->access_token()]
        )->body_entity());

        if (isset($api_response->errors[0])) {
            // 9 = Invalid access token
            if ($api_response->errors[0]->code == 9) {
                throw new Exception('Invalid access token. Try to renew an old token or obtain a new one.');
            } else {
                throw new Exception('Unknown error. Please analyze the HTTP response to find out what went wrong.');
            }
        }
        return $api_response;
    }


    /**
     * Fetch products in batches (pages) of $limit, and optionally store as temp files for later work
     * @param int $limit
     * @param bool $storeAsFiles
     * @return array|int|mixed
     * @throws Exception
     */
    public function products_fetch_in_batches(int $limit = 10, bool $store_as_files = false)
    {
        $api_response = null;
        $data_arr = [];
        $i = 1;

        // If the user wants to return one big array
        if (false === $store_as_files) {
            while (true) {
                usleep(50 * 1000); // Conserve CPU
                $api_response = $this->products_fetch_all($limit, $i);

                // If total is not available or invalid, assume we reached the end
                if (!isset($api_response->total) || $api_response->total === 0 || is_numeric($api_response->total) === false) {
                    return $data_arr;
                }

                // If no data is available, assume we reached the end
                if (!isset($api_response->data) || is_array($api_response->data) === false) {
                    return $data_arr;
                }

                // Append the entities to the $dataArr variable
                array_merge($data_arr, $api_response->data);
                ++$i;
            }
        }

        // If the user choose to save the batches as files, check if the required temp directories exist
        // If not, attempt to create them
        if (!file_exists($this->d->base_path() . 'tmp/shopware/api/products')) {
            // If the required tmp directories does not exist, we attempt to create them
            $this->fh->create_directory($this->d->base_path() . 'tmp/shopware/api/products', $this->d->base_path());
        }

        // Attempt to save the batches in individual files
        $total_entities = 0; // Total entities successfully stored as files
        $p = 1; // Note. The total number of pages is unknown
        while (true) {
            usleep(50 * 1000); // Sleep for 50 milliseconds to conserve our precious finite CPU horsepower
            $api_response = $this->products_fetch_all($limit, $p);

            // If total is not available or invalid, assume we reached the end
            if (!isset($api_response->total) || $api_response->total === 0 || is_numeric($api_response->total) === false) {
                return $total_entities;
            }

            // If no data is available, assume we reached the end
            if (!isset($api_response->data) || is_array($api_response->data) === false) {
                return $total_entities;
            }

            // If the tmp directories exist, attempt to save the file. Note. One file per page!
            $file_path = $this->d->base_path() . 'tmp/shopware/api/products/page' . $p . '.json';

            $this->fh->write_file($file_path, json_encode($api_response->data));

            // If the script is called from CLI, show status while working
            if ($this->helpers->is_called_from_cli()) {
                echo "    Batch saved to: " . $file_path . "\n";
            }

            // If total is defined (expected) we attempt to count total entities saved
            $total_entities = $total_entities + $api_response->total;
            ++$p;
        }
    }
    
}
