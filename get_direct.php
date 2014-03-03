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

GdeSlonImport::check_access();

$path = GS_PLUGIN_PATH.'/downloads';
try{
	file_put_contents ($path.'/test.txt', 'Hello File');
	@unlink($path.'/test.txt');
}catch (ErrorException $e ){
	die("Не хватает прав на запись в каталог $path . Выставьте нужные права и попробуйте еще раз.");
}

restore_error_handler();


/* Распаковка архива */
WP_Filesystem();
if ($status = unzip_file($path.'/archive.zip', $path) !== TRUE)
{
	die('Ошибка при распаковке архива. Данные об ошибке
	PclZip — Code: '.$status->get_error_code().'; Message: '.$status->get_error_message($status->get_error_code()));
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
	global $wpdb;
	$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s", $info_nocdata->shop->offers->offer[$i]->name[0] ));
	if (!$post)
	{
		$i++;
		continue;
	}

	$post_id = get_post($post)->ID;
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
$info->asXML(GS_PLUGIN_PATH.'/direct.xml');
copy(GS_PLUGIN_PATH.'/direct.xml', GS_PLUGIN_PATH.'/downloads/direct.xml');

$plugins_url = plugins_url();
$cur_path = plugin_basename(__FILE__);
$plugin_name = str_replace('get_direct.php','',$cur_path);
echo $plugins_url.'/'.$plugin_name.'direct.xml';

