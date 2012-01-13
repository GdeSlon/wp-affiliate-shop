<?php

function getCategoriesTreeList($parentId, $level, &$res) {
	global $wpdb;
	if (!empty($parentId))
		$where = 'parent_id = '.$parentId;
	else
		$where = 'parent_id IS NULL';
	$prefix = '';
	for ($i = 0; $i < $level; $i++) $prefix .= '--';
	$cats = $wpdb->get_results("SELECT * FROM ps_categories WHERE $where");
	foreach ($cats as $item) {
		$res[$item->id] = $prefix.$item->title;
		getCategoriesTreeList($item->id, $level + 1, $res);
	}
	return $res;
}

function getCategoriesChildren($id) {
	global $wpdb;
	$res = array($id);
	$cats = $wpdb->get_results("SELECT id FROM ps_categories WHERE parent_id = {$id}");
	if (!empty($res)) {
		foreach ($cats as $item) {
			$res = array_merge($res, getCategoriesChildren($item->id));
		}
	}
	return $res;
}

function makeLink($page = 1) {
	$res = get_permalink(get_option('ps_page'));
	$delimiter=(strpos($res,'?')===false)?'?':'&';
	$params = array('page='.$page);
	if (!empty($_GET['cat'])) $params[] = 'cat='.$_GET['cat'];
	if (!empty($_GET['ps_search'])) $params[] = 'ps_search='.$_GET['ps_search'];
	return $res.$delimiter.implode('&',$params);
}


/**
 * Will be used if future to replace cron.php
 */
class GdeSlonImport
{
	static public function checkCurl()
	{
		return function_exists('curl_init');
	}

	static public function checkFileGetContentsCurl()
	{
		return ini_get('allow_url_fopen');
	}

	static public function getFileFromUrl()
	{
		$url = get_option('ps_url');
		if (!self::checkCurl())
		{
			$opts = array(
				'http'=>array(
					'method'=>"GET",
					'header'=>"Accept-language: en\r\n"
				)
			);
			$context = stream_context_create($opts);
			return file_get_contents($url, false, $context);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$file = curl_exec($ch);
		curl_close($ch);
		return $file;
	}

}



?>