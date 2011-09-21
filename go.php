<?php
$url = isset($_REQUEST['url']) ? $_REQUEST['url'] : '';
if($url!=''&&!preg_match('#(http?)://#i',$url)){
    $url="http://".$url;
}
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