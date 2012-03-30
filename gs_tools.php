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

	static public function parseParams($content)
	{
		preg_match_all('|\<param name="(.+)"\>(.+)\</param\>|', $content, $matches);
		$params = array();
		foreach($matches[1] as $key => $title)
		{
			$params[$title] = str_replace(']]>', '', str_replace('<![CDATA[', '', $matches[2][$key]));
		}
		$params['params_list'] = implode(',',$matches[1]);

		preg_match('|\<vendor\>(.+)\</vendor\>|', $content, $matches);
		$params['vendor'] = '';
		if (!empty($matches[1]))
			$params['vendor'] = str_replace(']]>', '', str_replace('<![CDATA[', '', @$matches[1]));
		return $params;
	}

	static public function filterImport($content)
	{
		$stateVendor = $stateTitle = $statePrice = TRUE;
		if ($titleVendor = get_option('import_vendor'))
		{
			preg_match('|\<vendor\>(.+)\</vendor\>|', $content, $matches);
			$vendor = @$matches[1];
			$vendor = str_replace('<![CDATA[', '', $vendor);
			$vendor = str_replace(']]>', '', $vendor);
			if (strtolower($vendor) !== strtolower($titleVendor))
				$stateVendor = FALSE;
		}
		if ($titleFilter = get_option('import_title'))
		{
			preg_match('|\<name\>(.+)\</name\>|', $content, $matches);
			$title = @$matches[1];
			$title = str_replace('<![CDATA[', '', $title);
			$title = str_replace(']]>', '', $title);
			if (stripos($title, $titleFilter) === FALSE)
				$stateTitle = FALSE;
		}
		if ($priceFilter = get_option('import_price'))
		{
			preg_match('|\<price\>(.+)\</price\>|', $content, $matches);
			$price = @$matches[1];
			if (floatval($priceFilter) > floatval($price))
				$statePrice = FALSE;
		}
		return ($stateTitle && $statePrice && $stateVendor);
	}
}