<?php
/**
* @author Shikhar kumar (shikhar.kr@gmail.com)
*/

use Tygh\Registry;
use Tygh\Mailer;
use Tygh\Http;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_shopify_log($msg)
{
    file_put_contents(LOG_FILE,date('Y-m-d H:i:s')." $msg "."\n",FILE_APPEND);
    
}

// returns array(true, msg)
function fn_shopify_export_product($pid, $pdtls = array())
{

    if(empty($pid)){
        fn_shopify_log('No product id') ;        
        return array(false, 'No product id') ;
    }

    $d = array();
    
    $e['basic_auth'] = array (API_LOGIN, API_KEY) ;       // cscart
    $e['headers'][] = 'Content-Type: application/json' ;

    $se['headers'][] = 'Content-Type: application/json' ;  // shopify    
    
    if(empty($pdtls)){
        
        $_pdtls = Http::get(fn_url('','C').'/api/products/'.$pid, json_encode($d), $e) ;    
        $pdtls = json_decode($_pdtls, true) ;
        
        if ($pdtls['status'] == 404) {
            fn_shopify_log('No product found with id '.$pid) ;        
            return array(false, 'No product found with id '.$pid) ;
        }
    }
    
    $o = fn_get_product_options($pid) ;
    
    if($o){   // variant 
        
        return _shopify_export_product_variant($pid,$pdtls,$o) ;
        
    } else {  // single prd  

        $exists = db_get_row('SELECT * FROM ?:shopify_products WHERE product_id = ?i', $pid) ;
        
        // post
        $p = array();
        $p['product']['title'] = $pdtls['product'] ; 
        $p['product']['body_html'] = $pdtls['full_description'] ;
        //$p['product']['vendor'] = $pdtls[''] ;
        $p['product']['product_type'] = fn_get_category_name($pdtls['main_category']) ;
        $p['product']['published'] = $pdtls['status'] == 'A' ? true : false ; 
        $p['product']['images'][] = array('src'=>$pdtls['main_pair']['detailed']['image_path']) ; 
        
        $v = array(  
          'title' => $pdtls['product'],  
          'barcode' => $pdtls['product_code'],
          'price' => $pdtls['price'],
          'sku' => $pdtls['product_code'],
          'inventory_management' => "shopify",      
          'inventory_quantity' => $pdtls['amount'],
          'option1' => "Default Title" 
        ); 

        fn_shopify_log(' Syncing prd ' . $pid) ;
    
        if(empty($exists)){
    
            $p['product']['variants'][] = $v ;
            $_r = Http::post('https://'.SHOPIFY_API_KEY.':'.SHOPIFY_API_PASS.'@'.SHOPIFY_DOMAIN.'/admin/products.json', json_encode($p), $se);
            // update table
            $r = json_decode($_r,true);
            
            $d = array(
                'product_id' => $pid,
                's_product_id' => $r['product']['id'],
                's_variant_id' => $r['product']['variants'][0]['id'],
                'timestamp'=>time()
            );            
            
            db_query("INSERT INTO ?:shopify_products ?e", $d);
            
        
        } else {
            
            $v['id'] = $exists['s_variant_id'] ;
            $p['product']['variants'][] = $v ;
            $_r = Http::put('https://'.SHOPIFY_API_KEY.':'.SHOPIFY_API_PASS.'@'.SHOPIFY_DOMAIN.'/admin/products/'.$exists['s_product_id'].'.json', json_encode($p), $se);
        
            $r = json_decode($_r,true);
        }   

        
        if(empty($r['product']['id'])){
            fn_shopify_log(' Issue adding prd ' . var_export($p,true)) ;
            fn_shopify_log(' Issue adding prd - return ' . var_export($r,true)) ;      
            return array(false, 'Error') ;  
        } else {
            return array(true, 'Created '.$r['product']['id']) ;    
        }
        
        
    }    
   
}

function _shopify_export_product_variant($pid, $pdtls, $o) {

    $d = array();
    
    $e['basic_auth'] = array (API_LOGIN, API_KEY) ;       // cscart
    $e['headers'][] = 'Content-Type: application/json' ;

    $se['headers'][] = 'Content-Type: application/json' ;  // shopify
    
    $option_exists = db_get_array('SELECT * FROM ?:shopify_options WHERE product_id = ?i', $pid) ;

    if($option_exists){  // update

        // validation here, incase prd was updated - todo
        
        $_spid = db_get_field('SELECT s_product_id FROM ?:shopify_products WHERE product_id = ?i',$pid);
        $_ov = Http::get(fn_url('','C').'/api/combinations/?product_id='.$pid, '', $e) ;    
        $ov = json_decode($_ov, true) ;

        // put
        $p = array();
        $p['product']['title'] = $pdtls['product'] ; 
        $p['product']['body_html'] = $pdtls['full_description'] ;
        //$p['product']['vendor'] = $pdtls[''] ;
        $p['product']['product_type'] = fn_get_category_name($pdtls['main_category']) ;
        $p['product']['published'] = $pdtls['status'] == 'A' ? true : false ; 
        $p['product']['images'][] = array('src'=>$pdtls['main_pair']['detailed']['image_path']) ; 

        foreach($ov as $k=>$v) {
            $_op = key($v['combination']) ;
            $_sop = db_get_field('SELECT s_option FROM ?:shopify_options WHERE option_id = ?i AND product_id = ?i',$_op,$pid);
            $_svid = db_get_field('SELECT s_variant_id FROM ?:shopify_products WHERE '.$_sop.' = ?s AND product_id = ?i',$o[$_op]['variants'][$v['combination'][$_op]]['variant_name'],$pid) ;
            $p['product']['variants'][] = array(
              'id'=>$_svid,  
              'title' => $pdtls['product'],  
              'barcode' => $v['product_code'],
              'price' => round($pdtls['price'],2),
              'sku' => $v['product_code'],
              'inventory_management' => "shopify",      
              'inventory_quantity' => $v['amount'],
              //$_sop => $o[$_op]['variants'][$v['combination'][$_op]]['variant_name'], 
            ); 
        }

        fn_shopify_log(' Syncing prd ' . $pid) ;

        $_r = Http::put('https://'.SHOPIFY_API_KEY.':'.SHOPIFY_API_PASS.'@'.SHOPIFY_DOMAIN.'/admin/products/'.$_spid.'.json', json_encode($p), $se);
        $r = json_decode($_r, true) ;
        
        
        

    } else {  // insert

        if(count($o) > 3){
            fn_shopify_log('More than 3 options not supported by shopify pid '.$pid) ;        
            return array(false, 'More than 3 options not supported by shopify pid '.$pid) ;           
        }

        // track without inventory - return false
        if($pdtls['options_type'] == 's'){
            fn_shopify_log('Track without inventory not supported pid '.$pid) ;        
            return array(false, 'Track without inventory not supported pid '.$pid) ;
        }

        
        if(count($o) > 1){  // to do
            fn_shopify_log('More than 1 options not supported currently '.$pid) ;        
            return array(false, 'More than 1 options not supported currently pid '.$pid) ;           
        }
                
        // insert options
        $c = '1' ;
        foreach($o as $k=>$v){
            $i = array(
                'product_id'=>$pid,
                'option_id'=>$v['option_id'],
                'name'=>$v['option_name'],     
                's_option'=> 'option'.$c,
            );
            db_query("INSERT INTO ?:shopify_options ?e", $i); 
            $c += 1 ;
        }

        $option_exists = db_get_array('SELECT * FROM ?:shopify_options WHERE product_id = ?i', $pid) ;

        $_ov = Http::get(fn_url('','C').'/api/combinations/?product_id='.$pid, '', $e) ;    
        $ov = json_decode($_ov, true) ;

        // post
        $p = array();
        $p['product']['title'] = $pdtls['product'] ; 
        $p['product']['body_html'] = $pdtls['full_description'] ;
        //$p['product']['vendor'] = $pdtls[''] ;
        $p['product']['product_type'] = fn_get_category_name($pdtls['main_category']) ;
        $p['product']['published'] = $pdtls['status'] == 'A' ? true : false ; 
        $p['product']['images'][] = array('src'=>$pdtls['main_pair']['detailed']['image_path']) ; 

        foreach($ov as $k=>$v) {
            $_op = key($v['combination']) ;
            $_sop = db_get_field('SELECT s_option FROM ?:shopify_options WHERE option_id = ?i AND product_id = ?i',$_op,$pid);
            $p['product']['variants'][] = array(  
              'title' => $pdtls['product'],  
              'barcode' => $v['product_code'],
              'price' => round($pdtls['price'],2),
              'sku' => $v['product_code'],
              'inventory_management' => "shopify",      
              'inventory_quantity' => $v['amount'],
              $_sop => $o[$_op]['variants'][$v['combination'][$_op]]['variant_name'], 
            ); 
        }

        fn_shopify_log(' Syncing prd ' . $pid) ;

        $_r = Http::post('https://'.SHOPIFY_API_KEY.':'.SHOPIFY_API_PASS.'@'.SHOPIFY_DOMAIN.'/admin/products.json', json_encode($p), $se);
        $r = json_decode($_r, true) ;

        // update table
        foreach($r['product']['variants'] as $k=>$v){
            
            $d = array(
                'product_id' => $pid,
                's_product_id' => $v['product_id'],
                's_variant_id' => $v['id'],
                'option1'=>($v['option1']?$v['option1']:''),
                'option2'=>($v['option2']?$v['option1']:''),
                'option3'=>($v['option3']?$v['option1']:''),
                'timestamp'=>time()
            );            
            
            db_query("INSERT INTO ?:shopify_products ?e", $d);
        }

    } // end else 

    if ($r['product']['id']) {
        return array(true, 'Created '.$r['product']['id']) ;
    } else {
        fn_shopify_log(' Issue adding prd ' . var_export($p,true)) ;
        fn_shopify_log(' Issue adding prd - return ' . var_export($r,true)) ;  
        return array(false, 'Error') ;
    }
  
}

// HOOK : update stock at shopify
function fn_shopify_change_order_status($status_to, $status_from, $order_info, $force_notification, $order_statuses, $place_order)
{

    foreach($order_info['products'] as $k=>$v){
        
        fn_shopify_log('Order status hook oid '.$order_info['order_id']) ;       

        fn_shopify_export_product($v['product_id']) ;

    }    

}

function fn_shopify_update_product_post($product_data, $product_id, $lang_code, $create)
{
    fn_shopify_log('Product update post hook pid '.$product_id) ;       

    fn_shopify_export_product($product_id) ;

}