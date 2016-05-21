<?php
/**
 * @author Shikhar kumar (shikhar.kr@gmail.com)
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

//$log_file = Registry::get('config.dir.var') .'/shopify.log';

if ($mode == 'index') {

    if(empty(API_LOGIN) || empty(API_KEY) || empty(SHOPIFY_DOMAIN) || empty(SHOPIFY_API_KEY) || empty(SHOPIFY_API_PASS) ){
        exit('Config values missing in shopify/config.php');    
    }

}

if ($mode == 'products') {
    //DebugBreak();
    
    $a = db_get_fields('SELECT product_id FROM ?:products WHERE status = "A" ORDER BY product_id');
    $c = 1 ;
    $t = 0 ;

    foreach ($a as $k => $v) {
        
        set_time_limit(0);
        try{
            list($r,$m) = fn_shopify_export_product($v) ;    
        }catch (Exception $e) {
            echo '<br>Caught exception: ',  $e->getMessage(), "\n";
            fn_shopify_log($e->getMessage());
        }
        

        if ($r) {
            $t += 1 ;
            echo "<br>$t pid ".$v ;
        } else {
            echo "<br>Issue updating ".$v ;
            echo "<br>". $m ;
        }


        if(!empty($_REQUEST['count']) && $c > $_REQUEST['count']) {
            break ;
        }
        
        $c += 1 ; 
    }

    echo "<br> Total updated ".$t ;
    
    exit('<br>complete, check var/shopigy.log for more details') ;
}

if ($mode == 'test') {
    //print_r(fn_get_product_data(33479,$_SESSION['auth'])) ;
    
    echo json_encode(array(0=>"red",1=>"Green",2=>"Blue")) ;
    exit ;
}

if ($mode == 'list') {

    $d = array();
    $d['status'] = 'A' ;
    //$d['pname'] = 'Magic The Gathering' ;
    $d['items_per_page'] = 500 ;
    $d['page'] = 3 ;
    
    $e['basic_auth'] = array (API_LOGIN, API_KEY) ;       // cscart
    $e['headers'][] = 'Content-Type: application/json' ;

    $se['headers'][] = 'Content-Type: application/json' ;  // shopify
    
    $pdtls = Tygh\Http::get(fn_url('','C').'/api/products', $d, $e) ;

    echo $pdtls ;

    //$_pdtls = json_decode($pdtls, true) ;    
    
    exit ;
}