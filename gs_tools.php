<?php
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

	/**
	 * Проверка mime-типа файла. Добавлена для того, чтобы избежать проблем со скачанной html-страницей.
	 * Если возвращает TRUE — значит проблема имеет место.
	 * @static
	 * @param string $file Путь к загруженному файлу.
	 * @return bool
	 */
	static public function checkMimeType($file)
	{
		if (class_exists('finfo'))
		{
			$obFinfo = new finfo();
			//Предотвращение ошибки в ранних версиях php до 5.2
			if (!defined('FILEINFO_MIME_TYPE'))
			{
				define('FILEINFO_MIME_TYPE', 16);
			}
			$mimeType = $obFinfo->file($file, FILEINFO_MIME_TYPE);
		}
		else
		{
			//Если в системе нет ни mimt_content_type ни finfo расширения, то мы никак не можем проверить файл. Пропускаем проверку.
			return FALSE;
		}
		return !(stripos($mimeType, 'zip') !== FALSE);
	}

	static public function getFileFromUrl()
	{
		$url = GS_Config::init()->get('ps_url');
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
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
		if ($titleVendor = GS_Config::init()->get('import_vendor'))
		{
			preg_match('|\<vendor\>(.+)\</vendor\>|', $content, $matches);
			$vendor = @$matches[1];
			$vendor = str_replace('<![CDATA[', '', $vendor);
			$vendor = str_replace(']]>', '', $vendor);
			if (strtolower($vendor) !== strtolower($titleVendor))
				$stateVendor = FALSE;
		}
		if ($titleFilter = GS_Config::init()->get('import_title'))
		{
			preg_match('|\<name\>(.+)\</name\>|', $content, $matches);
			$title = @$matches[1];
			$title = str_replace('<![CDATA[', '', $title);
			$title = str_replace(']]>', '', $title);
			if (stripos($title, $titleFilter) === FALSE)
				$stateTitle = FALSE;
		}
		if ($priceFilter = GS_Config::init()->get('import_price'))
		{
			preg_match('|\<price\>(.+)\</price\>|', $content, $matches);
			$price = @$matches[1];
			if (floatval($priceFilter) > floatval($price))
				$statePrice = FALSE;
		}
		return ($stateTitle && $statePrice && $stateVendor);
	}

	/**
	 * Получение серверного пути к директории загрузки файлов
	 * @return mixed
	 */
	static function get_upload_path()
	{
		$dirData = wp_upload_dir();
		return $dirData['path'];
	}

	/**
	 * Проверка на возможность записи в директорию аплоада
	 * @return bool
	 */
	static function is_upload_directory_writeable()
	{
		return is_writable(self::get_upload_path());
	}
}


/**
 * Создание или обновление таксономии.
 * При этом происходит связывание термов и таксономии
 * @param $item
 * @return
 */
function importTerm(array $category)
{
	global $wpdb;
	$parentId = 0;
	if ($category['parent_id'])
	{
		$parentDbItem = get_category_by_outer_id($category['parent_id']);
		if ($parentDbItem)
			$parentId = $parentDbItem->term_id;
	}
	if (($dbItem = get_category_by_outer_id($category['id']))/* || ($dbItem = $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE name = '{$category['title']}'"))*/)
	{
		$termId = $dbItem->term_id;
		wp_update_term($dbItem->term_id, 'ps_category', array(
			'name'			=> $category['title'],
			'parent'		=> $parentId,
			'slug'			=> transliteration($category['title'])
		));
	}
	else
	{
		$result = wp_insert_term($category['title'], 'ps_category', array(
			'parent'	=> $parentId,
			'slug'		=> transliteration($category['title'])
		));
		if (is_array($result))
			$termId = $result['term_id'];
		elseif (is_object($result) && get_class($result) == 'WP_Error')
		{
			if (!empty($result->error_data['term_exists']))
				$termId = $result->error_data['term_exists'];
		}
	}
	if ($termId)
		$wpdb->query("UPDATE {$wpdb->terms} SET term_group = {$category['id']} WHERE term_id = $termId");
}

/**
 * Импортирование продукта из xml
 * @param array $item
 * @param null $params
 * @return mixed
 */
function importPost(array $item, $params = NULL)
{
	global $wpdb;
	$obItem = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_mime_type = {$item['id']}");
	$postId = null;
	if ($obItem->ID)
	{
		if ($obItem->post_status == 'trash')
			return;
		if (!$obItem->post_modified || $obItem->post_date != $obItem->post_modified)
		{
			$item['title'] = $obItem->post_title;
			$item['description'] = $obItem->post_content;
		}
		$params = array(
			'ID'				=> $obItem->ID,
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'ps_catalog',
			//'post_status'		=> 'publish',
			'post_mime_type'	=> $item['id'],
			'post_name'			=> transliteration($item['title'])
		);
		if (get_post_meta($obItem->ID, 'edited_by_user', TRUE))
		{
			unset($params['post_title']);
			unset($params['post_content']);
			unset($params['post_name']);
		}
		wp_update_post($params);
		foreach(array('url', 'price', 'currency', 'bestseller') as $var)
		{
			update_post_meta($obItem->ID, $var, $item[$var], get_post_meta($obItem->ID, $var, TRUE));
		}
		$postId = $obItem->ID;

	}
	else
	{
		$postId = wp_insert_post(array(
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'ps_catalog',
			'post_status'		=> 'publish',
			'post_mime_type'	=> $item['id'],
			'comment_status'	=> 'closed',
			'post_name'			=> transliteration($item['title'])
		));
		foreach(array('url', 'price', 'currency', 'bestseller') as $var)
		{
			add_post_meta($postId, $var, $item[$var], TRUE);
		}
		add_post_meta($postId, '_wp_page_template', 'sidebar-page.php', TRUE);
	}
	/**
	 * Подгрузка изображения
	 */
	if (!empty($item['image']))
	{
		download_image($item['image'], $postId);
	}
	wp_set_object_terms($postId, array(intval(get_category_by_outer_id($item['category_id'])->term_id)), 'ps_category');
	foreach($params as $name => $value)
	{
		update_post_meta($postId, $name, $value);
	}
}

/**
 * Подгрузка изображения
 * @param $url
 * @param $postId
 * @todo добавить обходной вариант на тот случай, если загрузка не удалась — просто запоминать урл на картинку
 * @todo Вынести наконец всё в красивый класс и закончить рефакторинг — запланировано на 7.06.2012
 */
function download_image($url, $postId)
{
	if (!GS_Config::init()->get('ps_download_images'))
	{
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
		return;
	}
	if (!GdeSlonImport::checkCurl())
	{
		$opts = array(
			'http'=>array(
				'method'=>"GET",
				'header'=>"Accept-language: en\r\n"
			)
		);
		$context = stream_context_create($opts);
		$fileContents = file_get_contents($url, false, $context);
	}
	else
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$fileContents = curl_exec($ch);
		curl_close($ch);
	}

	$localFilepath = dirname(__FILE__).'/downloads/'.basename($url);
	$f = fopen($localFilepath, 'w');
	fwrite($f, $fileContents);
	fclose($f);
	/**
	 * Удаление не пользовательских аттачментов
	 */
	foreach(get_children(array(
			'post_parent' => $postId,
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'numberposts' => -1,
		)) as $attachment)
	{
		if (get_post_meta($attachment->ID, 'is_image_from_gdeslon', TRUE))
		{
			wp_delete_attachment($attachment->ID, TRUE);
		}
	}
	$state = insert_attachment($localFilepath,$postId, true);
	if (is_wp_error($state))
	{
		echo 'При попытке загрузить файл '.$url.' возникла ошибка: '.$state->get_error_message().
				". Файл был присоединён старым способом\n\r";
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
	}
	else
	{
		add_post_meta($state, 'is_image_from_gdeslon', TRUE);
	}
	@unlink($localFilepath);
}

/**
 * Вставка информации о изображении в базу
 * @param $image
 * @param $post_id
 * @param bool $setthumb
 * @return mixed
 */
function insert_attachment($image, $post_id, $setthumb = FALSE)
{
	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
	require_once(ABSPATH . "wp-admin" . '/includes/media.php');

	$array = array( //array to mimic $_FILES
		'name' => basename($image), //isolates and outputs the file name from its absolute path
		'type' => 'image/jpeg', //yes, thats sloppy, see my text further down on this topic
		'tmp_name' => $image, //this field passes the actual path to the image
		'error' => 0, //normally, this is used to store an error, should the upload fail. but since this isnt actually an instance of $_FILES we can default it to zero here
		'size' => filesize($image) //returns image filesize in bytes
	);
	$imageId = media_handle_sideload($array, $post_id);
	if ($setthumb)
		update_post_meta($post_id,'_thumbnail_id',$imageId);
	return $imageId;
}

function get_category_by_outer_id($outerId)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE   = {$outerId}");
}