<?php
/**
 *
 *  Interface for the Shopware admin-api
 *
 *  @Author Jacob (jacobseated@gmail.com)
 */


namespace eightcontrol\shopware;

use eightcontrol\file_handler\file_handler;
use eightcontrol\http_client\http_client;
use eightcontrol\php_helpers\php_helpers;
use eightcontrol\shopware\types\api_token;
use eightcontrol\shopware\types\client_credentials;
use eightcontrol\shopware\types\password_auth;
use eightcontrol\shopware\types\site_details;

interface admin_interface {
    public function __construct(http_client $http, file_handler $fh, php_helpers $php_helpers, site_details $site_details, $auth_type);
    public function product_id(string $id);
    public function update_product(string $product_id, array $fields_to_update_arr);
    public function products_fetch_all(int $limit = null, int $page = null);
    public function products_fetch_in_batches(int $limit = 10, bool $store_as_files = false);
}