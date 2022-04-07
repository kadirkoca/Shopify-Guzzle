<?php

namespace App\Engine\Shopify;

use App\Engine\Shopify\ShopifyClientBase;
use App\Engine\Shopify\Shopifymodels\ShopifyResponseFilter;
use App\Models\EngineConfig;

class ShopifyClient extends ShopifyClientBase {
	function __construct($timeout = 30) {
        $config = EngineConfig::all()->first();
        if(!isset($config))return;
        $apikey = $config->shopify_api_key;
        $apipassword = $config->shopify_api_password;
        $storename = $config->shopify_store_name;
		parent::__construct($apikey, $apipassword, $storename, $timeout);
	}

	function getAbandonedCarts($limit = NULL, $since_id = NULL, $created_at_min = NULL, $created_at_max = NULL,
        $updated_at_min = NULL, $updated_at_max = NULL, $status = NULL) {

		$query = array();
		$query['limit'] = $limit;
		$query['since_id'] = $since_id;
		$query['created_at_min'] = $created_at_min;
		$query['created_at_max'] = $created_at_max;
		$query['updated_at_min'] = $updated_at_min;
		$query['updated_at_max'] = $updated_at_max;
		$query['status'] = $status;

		return new ShopifyResponseFilter($this->processRestRequest('GET', '/checkouts.json', $query), 'abandoned_cart');
	}


	function getCheckoutsCount($limit = NULL, $since_id = NULL, $created_at_min = NULL, $created_at_max = NULL,
        $updated_at_min = NULL, $updated_at_max = NULL, $status = NULL) {

		$query = array();
		$query['limit'] = $limit;
		$query['since_id'] = $since_id;
		$query['created_at_min'] = $created_at_min;
		$query['created_at_max'] = $created_at_max;
		$query['updated_at_min'] = $updated_at_min;
		$query['updated_at_max'] = $updated_at_max;
		$query['status'] = $status;

		return $this->processRestRequest('GET', "/checkouts/count.json", $query);
	}

	function getProductsByIdList($ids) {
        $fields = 'id,handle';
		$query = array();
		$query['ids'] = implode(",",$ids);
		$query['limit'] = 250;
		$query['fields'] = $fields;
		return $this->processRestRequest('GET', "/products.json", $query);
	}

	function getOrders($limit = NULL, $since_id = NULL, $fields = [NULL], $created_at_min = NULL, $created_at_max = NULL,
        $updated_at_min = NULL, $updated_at_max = NULL, $processed_at_min = NULL, $processed_at_max = NULL, $status = NULL,
        $fulfillment_status = NULL, $financial_status = NULL, $ids = [NULL]) {
        
		$query = array();
		$query['limit'] = $limit;
		$query['since_id'] = $since_id;
		$query['created_at_min'] = $created_at_min;
		$query['created_at_max'] = $created_at_max;
		$query['updated_at_min'] = $updated_at_min;
		$query['updated_at_max'] = $updated_at_max;
		$query['processed_at_min'] = $processed_at_min;
		$query['processed_at_max'] = $processed_at_max;
		$query['status'] = $status;
		$query['financial_status'] = $financial_status;
		$query['fulfillment_status'] = $fulfillment_status;
		$query['ids'] = implode(",",$ids);
		$query['fields'] = implode(",",$fields);
		return new ShopifyResponseFilter($this->processRestRequest('GET', "/orders.json", $query), 'orders');
	}
}
