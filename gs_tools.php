<?php
function makeLink($page = 1) {
	$res = get_permalink(get_option('ps_page'));
	$delimiter=(strpos($res,'?')===false)?'?':'&';
	$params = array('page='.$page);
	if (!empty($_GET['cat'])) $params[] = 'cat='.$_GET['cat'];
	if (!empty($_GET['ps_search'])) $params[] = 'ps_search='.$_GET['ps_search'];
	return $res.$delimiter.implode('&',$params);
}

//Рекурсивное получение родительских категорий
function ps_get_taxonomy_parents($term_id, array $terms = array())
{
	$obTerm = get_term($term_id, 'ps_category');
	if ($obTerm->parent)
	{
		$terms = ps_get_taxonomy_parents($obTerm->parent, $terms);
	}
	$terms[] = $obTerm;
	return $terms;
}

function fixUrl($url) {
	return preg_replace('/(:?\?.+?)\?/', '$1&', $url);
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