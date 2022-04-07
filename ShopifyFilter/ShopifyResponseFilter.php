<?php

namespace App\Engine\Shopify\Shopifymodels;

use App\Engine\Shopify\ShopifyClient;
use App\Models\AbandonedCart;
use App\Models\Order;
use App\Models\LastCallMark;

class ShopifyResponseFilter {
    private $limit = 250;

	function __construct($dataset, $request_type){
        if($dataset !== 'null' && $request_type === 'abandoned_cart'){
            if(array_key_exists('checkouts', $dataset) !== false && !empty($dataset['checkouts'])){
                $this->processAbandonedList($dataset['checkouts']);
            }
        }else if($dataset !== 'null' && $request_type === 'orders'){
            if(array_key_exists('orders', $dataset) !== false && !empty($dataset['orders'])){
                $this->processOrders($dataset['orders']);
            }
        }
    }

    function processAbandonedList($dataset){
        $countofdataset = count($dataset);
        if($countofdataset<1)return;
        $lcm = LastCallMark::firstOrNew();

        if($countofdataset>=$this->limit){
            $lastid = $dataset[$countofdataset-1]['id'];
            $lcm->abandoned_last = $lastid;
            $lcm->save();
        }else{
            $lcm->abandoned_last = 'none';
            $lcm->save();
        }
        foreach($dataset as $data){
            $checkout_id = $data['id'];
            if(!isset($data['customer'])) continue;
            $customer_name = $data['customer']['first_name'].' '.$data['customer']['last_name'];
            $customer_email = $data['email'];
            
            $customer_country = null;
            $customer_city = null;
            $customer_zip = null;
            $customer_phone = null;

            if(isset($data['customer']['default_address'])){
                $customer_country = $data['customer']['default_address']['country'];
                $customer_city = $data['customer']['default_address']['city'];
                $customer_zip = $data['customer']['default_address']['zip'];
                $customer_phone = $data['customer']['default_address']['phone'];
            }else if(isset($data['shipping_address'])){
                $customer_country = $data['shipping_address']['country'];
                $customer_city = $data['shipping_address']['city'];
                $customer_zip = $data['shipping_address']['zip'];
                $customer_phone = $data['shipping_address']['phone'];
            }

            $customer_country = $customer_country === null ? 'unknown' : $customer_country;
            $customer_city = $customer_city === null ? 'unknown' : $customer_city;
            $customer_zip = $customer_zip === null ? 'unknown' : $customer_zip;
            $customer_phone = $customer_phone === null ? 'unknown' : $customer_phone;
            
            if(stripos($customer_email, 'anonymous') !== false) continue;
            $checkout_date = $data['created_at'];
            $line_products = $data['line_items'];
            $product_list = array();
            foreach($line_products as $line_product){
                array_push($product_list, $line_product['product_id']);
                $allproducts_bag[] = $line_product['product_id'];
            }
            $abandoned_checkout_url = $data['abandoned_checkout_url'];

            if(isset($checkout_id) && isset($customer_email) && !empty($product_list)){
                $AbandonedCart = array();
                $AbandonedCart['checkout_id'] = $checkout_id;
                $AbandonedCart['name'] = $customer_name;
                $AbandonedCart['email'] = $customer_email;
                $AbandonedCart['country'] = $customer_country;
                $AbandonedCart['city'] = $customer_city;
                $AbandonedCart['zip'] = $customer_zip;
                $AbandonedCart['phone'] = $customer_phone;
                $AbandonedCart['date'] = $checkout_date;
                $AbandonedCart['products'] = $product_list;
                $AbandonedCart['cart_url'] = $abandoned_checkout_url;
                $dataset_for_save[] = $AbandonedCart;
            }
        }
        $allproducts_bag = array_unique($allproducts_bag);
        $getproductsbyidlist = new ShopifyClient();
        $allproducts_bag = $getproductsbyidlist->getProductsByIdList($allproducts_bag);
        if(gettype($allproducts_bag) !== 'array')return;
        $this->saveAbandonedRecords($dataset_for_save, $allproducts_bag['products']);
    }

    function saveAbandonedRecords($dataset, $productlist){
        foreach($dataset as $data){
            foreach($data['products'] as $product_id){
                $key = array_search($product_id, array_column($productlist, 'id'));
                if($key !== false){
                    $product = $productlist[$key];
                    $productdata['product_id'] = $product['id'];
                    $productdata['product_handle'] = $product['handle'];
                    $products[] = $productdata;
                }
            }
            $data['products'] = $products;
            if (!AbandonedCart::where('checkout_id', $data['checkout_id'])->exists()) {
                AbandonedCart::firstOrCreate($data);
            }
        }
    }

    /*
       processOrders başarılı siparişlerin içinden daha önce abandoned olanları müşterilerin emailleriyle çıkarır.
    */

    function processOrders($dataset){
        $countofdataset = count($dataset);
        if($countofdataset<1)return;
        $lcm = LastCallMark::firstOrNew();

        if($countofdataset>=$this->limit){
            $lastid = $dataset[$countofdataset-1]['id'];
            $lcm->order_last = $lastid;
            $lcm->save();
        }else{
            $lcm->order_last = 'none';
            $lcm->save();
        }

        $abandonedlist = AbandonedCart::all()->pluck('email')->toArray();
        $abandonedlist = array_unique($abandonedlist);

        $deletelist = array();
        foreach($dataset as $data){
            $order_status = 'positive';

            if(in_array($data['email'], $abandonedlist)){
                array_push($deletelist, $data['email']);
                $order_status = 'positified';
            }

            $order = Order::where('order_id', $data['id'])->first();
            if($order !== null){
                if($order->state !== 'positified') $order->state = $order_status;
            }else{
                $order = new Order();
                $order->order_id = $data['id'];
                $order->state = $order_status;
            }

            $customer_country = null;
            $customer_city = null;
            $customer_zip = null;
            $customer_phone = null;

            if(isset($data['customer']['default_address'])){
                $customer_country = $data['customer']['default_address']['country'];
                $customer_city = $data['customer']['default_address']['city'];
                $customer_zip = $data['customer']['default_address']['zip'];
                $customer_phone = $data['customer']['default_address']['phone'];
            }else if(isset($data['shipping_address'])){
                $customer_country = $data['shipping_address']['country'];
                $customer_city = $data['shipping_address']['city'];
                $customer_zip = $data['shipping_address']['zip'];
                $customer_phone = $data['shipping_address']['phone'];
            }

            $customer_country = $customer_country === null ? 'unknown' : $customer_country;
            $customer_city = $customer_city === null ? 'unknown' : $customer_city;
            $customer_zip = $customer_zip === null ? 'unknown' : $customer_zip;
            $customer_phone = $customer_phone === null ? 'unknown' : $customer_phone;
                        
            $order->email = $data['email'];
            $order->browser_ip = $data['browser_ip'];
            $order->current_total_price = $data['current_total_price'];
            $order->financial_status = $data['financial_status'];
            $order->country = $customer_country;
            $order->city = $customer_city;
            $order->zip = $customer_zip;
            $order->phone = $customer_phone;
            $order->created_at = $data['created_at'];
            $order->save();
        }

        if(!empty($deletelist) && count($deletelist)>0){
            $deletelist = array_unique($deletelist);
            try {
                AbandonedCart::whereIn('email', $deletelist)->delete();
            }catch(\Exception $e) {}
        }
    }
}
