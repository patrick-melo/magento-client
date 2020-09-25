<?php namespace Magento;
/**
 * This provides stubs for the Magento2 api. Calls to /rest/V1/products/$sku
 * can be called as follows:
 * 
 * $api = new MagentoApi([
 *   'origin'              =>'http://host/', 
 *   'consumer_key'        => '...', 
 *   'consumer_secret'     => '...',
 *   'access_token'        => '...',
 *   'access_token_secret' => '...'
 * ]);
 * $api->getProduct('somesku');
 * 
 * Refer to the Magento API documentation for more information.
 * https://devdocs.magento.com/redoc/2.3/admin-rest-api.html
 * 
 * To troubleshoot "The signature is invalid", consider editing the following
 * file on the container.
 * /var/www/html/vendor/magento/framework/Oauth/Oauth.php
 * 
 * @author patrick-melo
 *
 */
class Client {
    private $origin = null;
    private $consumerKey = null;
    private $consumerSecret = null;
    private $accessToken = null;
    private $accessTokenSecret = null;
    
    function __construct($params) {
        $this->origin = $params['origin'];
        $this->consumerKey = $params['consumer_key'];
        $this->consumerSecret = $params['consumer_secret'];
        $this->accessToken = $params['access_token'];
        $this->accessTokenSecret = $params['access_token_secret'];
    }
    
    function urlEncodeAsZend($value) {
        $encoded = rawurlencode($value);
        $encoded = str_replace('%7E', '~', $encoded);
        return $encoded;
    }
    
    function sign($method, $url, $oauth) {
        $url = $this->urlEncodeAsZend($url);
        
        $oauth = $this->urlEncodeAsZend(http_build_query($oauth, '', '&'));
        $oauth = implode('&', [$method, $url, $oauth]);
        $secret = implode('&', [$this->consumerSecret, $this->accessTokenSecret]);
        
        return base64_encode(hash_hmac('sha1', $oauth, $secret, true));
    }
    
    function sort(&$array) {
        foreach ($array as &$value) {
            if (is_array($value)) $this->sort($value);
        }
        return ksort($array);
    }
    
    function call($method, $path, $data = null) {
        $url = $this->origin .$path;
        
        $oauth = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];
        if ($method == 'GET' && is_array($data)) {
            /* This fixes "The signature is invalid. Verify and try again."
             * See https://stackoverflow.com/questions/57466483/how-do-i-send-multiple-query-parameters-to-the-api
             */
            $this->sort($data);
            
            $oauth = array_merge($oauth, $data);
            $curlopt_url = $url .'?' .http_build_query($data);
        } else {
            $curlopt_url = $url;
        }
        $oauth['oauth_signature'] = $this->sign($method, $url, $oauth);
        
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $curlopt_url,
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . http_build_query($oauth, '', ',')
            ]
        ];
        if ($method == 'POST' || $method == 'PUT') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, $opts);
        $result = curl_exec($curl);
        if ($result === false) {
            throw new MagentoException(curl_error($curl));
        }
        curl_close($curl);
        
        $o = json_decode($result);
        if (isset($o->message)) {
            //var_dump($opts);
            //var_dump($o);
            //$this->json_dump($o);
            $message = $o->message;
            if (isset($o->parameters)) {
                $message .= ' ' .json_encode($o->parameters);
            }
            throw new MagentoException($message);
        }
        return $o;
    }
    
    function get($path, $data=null) {
        return $this->call('GET', $path, $data);
    }
    
    function post($path, $data) {
        return $this->call('POST', $path, $data);
    }
    
    /* idempotent */
    function put($path, $data) {
        return $this->call('PUT', $path, $data);
    }
    
    function delete($path, $data=null) {
        return $this->call('DELETE', $path, $data);
    }
    
    /* Products */
    
    /*
    function getProducts($page_size=1000, $current_page=1) {
        return $this->get('/rest/V1/products', ['searchCriteria[pageSize]'=>$page_size, 'searchCriteria[currentPage]'=>$current_page]);
    }
    */
    
    function getProduct($sku) {
        return $this->get('/rest/all/V1/products/' .$sku);
    }
    
    function setProduct($product) {
        return $this->post('/rest/all/V1/products', ['product'=>$product]);
    }
    
    function getProductMedia($sku) {
        return $this->get('/rest/all/V1/products/' .$sku .'/media');
    }
    
    function setProductMedia($sku, $entry) {
        return $this->post('/rest/all/V1/products/' .$sku .'/media', ['entry'=>$entry]);
    }

    function removeProductMedia($sku, $media_id) {
        return $this->delete('/rest/all/V1/products/' .$sku .'/media/' .$media_id);
    }
    
    /* Orders */
    
    function getOrders() {
        return $this->get('/rest/all/V1/orders', ['searchCriteria'=>'all']);
    }
    
    function getOrder($order_id) {
        return $this->get('/rest/all/V1/orders/' .$order_id);
    }
    
    function setOrder($order) {
        return $this->put('/rest/all/V1/orders/create', ['entity'=>$order]);
    }
    
    /* https://docs.magento.com/m2/ce/user_guide/sales/order-status.html */
    function setOrderStatus($entity_id, $status, $increment_id) {
        $order = [
            'entity_id' => $entity_id,
            'status' => $status,
            'increment_id' => $increment_id
        ];
        return $this->post('/rest/all/V1/orders', ['entity'=>$order]);
    }
    
    /* Shipments */
    
    function getShipment($shipment_id) {
        return $this->get('/rest/all/V1/shipment/' .$shipment_id);
    }
    
    function getShipments() {
        return $this->get('/rest/all/V1/shipments', ['searchCriteria'=>'all']);
    }
    
    function setOrderShipment($entity_id, $shipment) {
        return $this->post('/rest/all/V1/order/' .$entity_id .'/ship', $shipment);
    }
    
    /* Categories */
    
    function getCategory($category_id) {
        return $this->get('/rest/all/V1/categories/' .$category_id);
    }
    
    function setCategory($category) {
        return $this->post('/rest/all/V1/categories/', ['category'=>$category, 'saveOptions'=>true]);
    }
    
    function moveCategory($category_id, $parentId) {
        return $this->put('/rest/all/V1/categories/' .$category_id .'/move', ['parentId'=>$parentId]);
    }
    
    function json_dump($o) { echo(json_encode($o, JSON_PRETTY_PRINT))."\n"; }
    
    function getProducts($filters, $pageSize = 100, $currentPage = 1) {
        $data = [
            'searchCriteria' => [
                'currentPage' => $currentPage,
                'pageSize' => $pageSize,
                'filterGroups'=>[
                    0 => [
                        'filters'=> (array)(object)$filters
                    ]
                ]
            ]
        ];
        return $this->get('/rest/all/V1/products/', $data);
    }
    
    /* There's a bug preventing listing products by store.
     * See https://magento.stackexchange.com/questions/174620/magento-2-rest-api-accessing-products-based-on-store-code-giving-same-products
     */
    //function getStoreProducts($storeCode, $pageSize = 100, $currentPage = 1) {
    //    return $this->get('/rest/'.$storeCode.'/V1/products/', ['searchCriteria'=>['currentPage'=>$currentPage, 'pageSize'=>$pageSize]]);
    //}
}
class MagentoException extends \Exception {}