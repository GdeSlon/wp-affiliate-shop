<?php

define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

$item_id = isset($_REQUEST['item_id']) ? $_REQUEST['item_id'] : '';
$url = get_post_meta($item_id, 'url', TRUE);

if(preg_match('#(http?)://\S+[^\s.,>)\];\'\"!?]#i',$url)){
    header('Content-type: text/html; charset=utf-8');
    echo "Перенаправление";
    sleep(2);
    echo "<html><head><meta http-equiv=\"refresh\" content=\"0;url=$url\"></head></html>";
    exit();
}else{
    header("Location: http://".$_SERVER['HTTP_HOST']);
}
?>