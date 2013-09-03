<?php
header('Content-type: text/html; charset=utf-8');
define('PARSING_IS_RUNNING', TRUE);
ignore_user_abort(true);
set_time_limit(36000);
define('DOING_CRON', true);

/**
 * Определение констант
 */
if (!defined('GS_PLUGIN_PATH')) {
	define('GS_PLUGIN_PATH', dirname(__FILE__));
}

// Переписывает include_path на корень
set_include_path(GS_PLUGIN_PATH . '/../../../');

require_once(GS_PLUGIN_PATH . '/../../../wp-load.php');
require_once(GS_PLUGIN_PATH . '/../../../wp-admin/includes/class-pclzip.php');

require_once(GS_PLUGIN_PATH.'/config.php');
require_once(GS_PLUGIN_PATH.'/options-controller.php');
require_once(GS_PLUGIN_PATH.'/gs_tools.php');
require_once(GS_PLUGIN_PATH.'/widget.php');
require_once(GS_PLUGIN_PATH.'/posts.php');

function get_post_by_title($page_title, $output = OBJECT) {
    global $wpdb;
        $post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s", $page_title ));
        if ( $post )
            return get_post($post, $output);

    return null;
}

$accessCode = GS_Config::init()->get('ps_access_code');
$getEnable = (int)GS_Config::init()->get('ps_get_enable');

if (empty($_GET['code'])) {
	if (!empty($_SERVER['REQUEST_URI'])) exit("Неверный код безопасности.");
} else {
	if(!$getEnable) die('Возможность обновления базы GET-запросом выключена');
	if ($accessCode != $_GET['code']) exit;
}

$path = GS_PLUGIN_PATH.'/downloads';
try{
	file_put_contents ($path.'/test.txt', 'Hello File');
	@unlink($path.'/test.txt');
}catch (ErrorException $e ){
	die("Не хватает прав на запись в каталог $path . Выставьте нужные права и попробуйте еще раз.");
}

restore_error_handler();


/* Распаковка архива */
$zip = new PclZip($path.'/archive.zip');
if ($status = $zip->extract(PCLZIP_OPT_PATH, $path) === 0)
{
	die('Ошибка при распаковке архива. Данные об ошибке PclZip — Code: '.$zip->error_code.'; Message: '.$zip->error_string);
}

$xmlfile = '';
$dh = opendir($path);
while ($file = readdir($dh)) {
	if (strpos($file, '.xml') !== false) {
		$xmlfile = $file;
		break;
	}
}
closedir($dh);

if (empty($xmlfile)) {
	echo 'Не удалось получить выгрузку.';
	exit;
}

$xmlFileFullPath = $path.'/'.$xmlfile;
//$f = fopen($xmlFileFullPath, 'r');


// load the document
$info_nocdata = simplexml_load_file($xmlFileFullPath, 'SimpleXMLElement', LIBXML_NOCDATA);
$info = simplexml_load_file($xmlFileFullPath);

// update
$info->shop->name = get_bloginfo();
$info->shop->company = get_bloginfo();
$info->shop->url = get_site_url();

$i = 0;

foreach($info->shop->offers->offer as $key => $value)
{
	$post = get_post_by_title($info_nocdata->shop->offers->offer[$i]->name[0]);
	$post_id = $post->ID;

	$value->url = get_permalink($post_id);

	if(GS_Config::init()->get('ps_download_images')):
		$thumbnail_id = get_post_thumbnail_id($post_id);
		$image_full = wp_get_attachment_image_src($thumbnail_id, 'full');
		$image_thumbnail = wp_get_attachment_image_src($thumbnail_id);
		$value->thumbnail = $image_thumbnail[0];
		$value->picture = $image_full[0];
	endif;

	unset($value->original_picture);
	unset($value->attributes()->gs_category_id);
	$i++;
}

// save the updated document
$info->asXML('direct.xml');
copy('direct.xml', 'downloads/direct.xml');

$file = ("direct.xml");
header ("Content-Type: application/octet-stream");
header ("Accept-Ranges: bytes");
header ("Content-Length: ".filesize($file));
header ("Content-Disposition: attachment; filename=".$file);
readfile("downloads/direct.xml");

unlink('direct.xml');