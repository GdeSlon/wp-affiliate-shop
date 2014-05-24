<?php
//проверка на существование опции в настройках постоянных ссылок
function gs_perma_check(){
	$opt=get_option('woocommerce_permalinks');
	if (empty($opt["category_base"])){
	?>
	<script type="text/javascript">
		$j = jQuery;
		$j().ready(function(){
			$j('.wrap > h2').parent().prev().after('<div class="update-nag">Чтобы избежать ошибок <a href="<?=admin_url()?>options-permalink.php">измените</a> значение опции "Постоянная ссылка рубрик" на непустое.</div>');
		});
	</script>
	<?php
	}
}
add_action('admin_head','gs_perma_check');
//Рекурсивное получение родительских категорий
function ps_get_taxonomy_parents($term_id, array $terms = array())
{
	$obTerm = get_term($term_id, 'product_cat');
	if (!empty($obTerm->parent))
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
		return stripos($mimeType, 'zip') === FALSE && $mimeType != 'application/octet-stream';
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
		$file = curl_redirect_exec($ch);
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

	/**
	 * @static
	 * Проверка разрешения на доступ к файлам cron.php и get-direct.php
	 */
	static function check_access()
	{
		if (PHP_SAPI === 'cli')
		{
			return;
		}
		$accessCode = GS_Config::init()->get('ps_access_code');
		$getEnable = (int)GS_Config::init()->get('ps_get_enable');

		$getCode = empty($_GET['code']) ? NULL : $_GET['code'];
		$postCode = empty($_POST['code']) ? NULL : $_POST['code'];

		if (!$postCode)
		{
			if (!$getCode)
			{
				die('Не найден код');
			}
			elseif (!$getEnable)
			{
				die('Возможность обновления базы GET-запросом выключена');
			}
			elseif ($getCode != $accessCode)
			{
				die('Проверьте правильность кода');
			}
		}
		elseif ($postCode != $accessCode)
		{
			die('Проверьте правильность кода');
		}
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
	if (!empty($category['parent_id'])) {
		$parentDbItem = get_category_by_outer_id($category['parent_id']);
		if ($parentDbItem)
			$parentId = $parentDbItem->term_id;
	}

	// Если категория существует то обновляем её
	if (($dbItem = get_category_by_outer_id($category['id']))) {
		$termId = $dbItem->term_id;
		$args = array('parent' => $parentId);
		// Старые мета
		$original_name = get_post_meta($termId, 'original_name', $single = true);
		$original_slug = get_post_meta($termId, 'original_slug', $single = true);

		// Если оригинальное имя не было изменено
		// а новое отличается то можем переписать его
		if($dbItem->name == $original_name && $dbItem->name != $category['title']) {
			$args['name'] = $category['title'];
			update_post_meta($termId, 'original_name', $category['title']);
		}

		// Если оригинальный слаг не был изменен
		// а новоый отличается то можем переписать его
		if($dbItem->slug == $original_slug && $dbItem->slug != transliteration($category['title'])) {
			$args['slug'] = transliteration($category['title']);
			update_post_meta($termId, 'original_slug', transliteration($category['title']));
		}

		wp_update_term($dbItem->term_id, 'product_cat', $args);
	}
	// Если категории существует то создаём её
	else {
		$result = wp_insert_term($category['title'], 'product_cat', array(
			'parent'	=> $parentId,
			'slug'		=> transliteration($category['title'])
		));

		if (is_array($result)) {
			$termId = $result['term_id'];
		}
		elseif (is_object($result) && get_class($result) == 'WP_Error') {
			if (!empty($result->error_data['term_exists']))
				$termId = $result->error_data['term_exists'];
		}

		// Сохраняем слаги на слудующий раз
		update_post_meta($termId, 'original_name', $category['title']);
		update_post_meta($termId, 'original_slug', transliteration($category['title']));
	}

	if ($termId) {
		$wpdb->query("UPDATE {$wpdb->terms} SET term_group = {$category['id']} WHERE term_id = $termId");
	}
//	var_dump($parentId, $termId); die;
}

/**
 * Импортирование продукта из xml
 * @param array $item
 * @param null $params
 * @return mixed
 */
function importPost(array $item, $params = NULL)
{
	/*
	 * If connected woocommerce plugin - add posts to post type of woocommerce
	 */
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if(!is_plugin_active('woocommerce/woocommerce.php')){
		echo "Для корректной работы этого плагина, необходимо установить Woocommerce плагин.";
		die;
	}
	$product_params = $params;
	global $wpdb;
	$rd_args = array(
		'post_type' => 'product',
		'meta_key' => 'offer_id',
		'meta_value' => $item['id']
	);
	$q = new WP_Query( $rd_args );
	$obItem=@$q->posts[0];
	//$obItem = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID = {$item['id']}");
	$postId = null;
	if (!empty($obItem->ID))
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
			'post_type'			=> 'product',
			//'post_status'		=> 'publish',
			'post_name'			=> transliteration($item['title'])
		);
		if (get_post_meta($obItem->ID, 'edited_by_user', TRUE))
		{
			unset($params['post_title']);
			unset($params['post_content']);
			unset($params['post_name']);
		}
		wp_update_post($params);

		//set gdeslon offer_id
		update_post_meta($obItem->ID, 'offer_id', $item['id']);
			
		//set gdeslon url
		if($item['url'])
			update_post_meta($obItem->ID, 'url', $item['url'], get_post_meta($obItem->ID, 'url', TRUE));

		//set gdeslon price
		if($item['price']){
			update_post_meta($obItem->ID, '_regular_price', $item['price'], get_post_meta($obItem->ID, '_regular_price', TRUE));
			update_post_meta($obItem->ID, '_price', $item['price'], get_post_meta($obItem->ID, '_price', TRUE));
			update_post_meta($obItem->ID, '_visibility', 'visible', get_post_meta($obItem->ID, '_visibility', TRUE));
			update_post_meta($obItem->ID, '_stock_status', 'instock', get_post_meta($obItem->ID, '_stock_status', TRUE));
		}


		$postId = $obItem->ID;

	}
	else
	{
		$postId = wp_insert_post(array(
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'product',
			'post_status'		=> 'publish',
			'post_mime_type'	=> $item['id'],
			'comment_status'	=> 'closed',
			'post_name'			=> transliteration($item['title'])
		));

		//set gdeslon offer_id
			add_post_meta($postId, 'offer_id', $item['id']);
		
		//set gdeslon url
		if($item['url'])
			add_post_meta($postId, 'url', $item['url'], TRUE);

		//set gdeslon price
		if($item['price']){
			add_post_meta($postId, '_regular_price', $item['price'], TRUE);
			add_post_meta($postId, '_price', $item['price'], TRUE);
			add_post_meta($postId, '_visibility', 'visible', TRUE);
			add_post_meta($postId, '_stock_status', 'instock', TRUE);
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
	wp_set_object_terms($postId, array(intval(get_category_by_outer_id($item['category_id'])->term_id)), 'product_cat');

	//add product params
	$attributes = array();
	unset($product_params['params_list']);
	unset($product_params['vendor']);
	foreach($product_params as $name => $value)
	{
		$attributes[htmlspecialchars(stripslashes($name))] = array(
				//Make sure the 'name' is same as you have the attribute
				'name'         => htmlspecialchars(stripslashes($name)),
				'value'        => $value,
				'position'     => 1,
				'is_visible'   => 1,
				'is_variation' => 1,
				'is_taxonomy'  => 0
			);
	}

	set_product_attributes($postId, $attributes);
}

function set_product_attributes($post_id, $attributes)
{

	//Add as post meta
	add_post_meta($post_id, '_product_attributes', serialize($attributes));

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
		$fileContents = curl_redirect_exec($ch);
		curl_close($ch);
	}
	if (!$fileContents)
	{
		echo 'При попытке загрузить файл '.$url.' возникла ошибка: '.
			"Содержимое файла не получено. Файл был присоединён старым способом\n\r";
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
		return;
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
	if (is_file($localFilepath))
		unlink($localFilepath);
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
		//'size' => filesize($image) //returns image filesize in bytes
	);
	$imageId = media_handle_sideload($array, $post_id);
	if ($setthumb)
		update_post_meta($post_id,'_thumbnail_id',$imageId);
	return $imageId;
}

function get_category_by_outer_id($outerId)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE  term_group = {$outerId}");
}

/*
	curl_exec which takes in account redirects

	Source http://stackoverflow.com/a/3890902/1194327
*/
function curl_redirect_exec($ch, $curlopt_header = false) {
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$data = curl_exec($ch);

	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($http_code == 301 || $http_code == 302) {
		list($header) = explode("\r\n\r\n", $data, 2);

		$matches = array();
		preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
		$url = trim(str_replace($matches[1], "", $matches[0]));

		$url_parsed = parse_url($url);
		if (isset($url_parsed)) {
			curl_setopt($ch, CURLOPT_URL, $url);
			return curl_redirect_exec($ch, $curlopt_header);
		}
	}

	if ($curlopt_header) {
		return $data;
	} else {
		list(, $body) = explode("\r\n\r\n", $data, 2);
		return $body;
	}
}
