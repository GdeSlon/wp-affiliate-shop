<?php
header('Content-type: text/html; charset=utf-8');
define('PARSING_IS_RUNNING', TRUE);
ignore_user_abort(true);
set_time_limit(36000);
define('DOING_CRON', true);

/**
 * Определение констант
 */
define('GS_PLUGIN_PATH', dirname(__FILE__));

// Переписывает include_path на корень
set_include_path(GS_PLUGIN_PATH . '/../../../');

require_once(GS_PLUGIN_PATH . '/../../../wp-load.php');
require_once(GS_PLUGIN_PATH . '/../../../wp-admin/includes/class-pclzip.php');

require_once(GS_PLUGIN_PATH.'/config.php');
require_once(GS_PLUGIN_PATH.'/options-controller.php');
require_once(GS_PLUGIN_PATH.'/gs_tools.php');
require_once(GS_PLUGIN_PATH.'/widget.php');
require_once(GS_PLUGIN_PATH.'/posts.php');

$accessCode = GS_Config::init()->get('ps_access_code');
$getEnable = (int)GS_Config::init()->get('ps_get_enable');

if (empty($_GET['code'])) {
	if (!empty($_SERVER['REQUEST_URI'])) exit;
} else {
	if(!$getEnable) die('Возможность обновления базы GET-запросом выключена');
	if ($accessCode != $_GET['code']) exit;
}

$path = GS_PLUGIN_PATH.'/downloads';


set_error_handler(
	create_function(
		'$severity, $message, $file, $line',
		'throw new ErrorException($message, $severity, $severity, $file, $line);'
	)
);

try{
	file_put_contents ($path.'/test.txt', 'Hello File');
	@unlink($path.'/test.txt');
}catch (ErrorException $e ){
	die("Не хватает прав на запись в каталог $path . Выставьте нужные права и попробуйте еще раз.");
}

restore_error_handler();
if (!GdeSlonImport::checkCurl() && !GdeSlonImport::checkFileGetContentsCurl())
{
	die("Не найдено расширение php cUrl, а получение удаленного файла запрещено в настройках php.ini");
}
@unlink($path.'/archive.zip');
$f = fopen($path.'/archive.zip', 'w');
fwrite($f, GdeSlonImport::getFileFromUrl());
fclose($f);
if (GdeSlonImport::checkMimeType($path.'/archive.zip'))
{
	die("По указанному пути не найден ZIP-файл. Проверьте правильность введённой ссылки");
}
/* Удаление старых xml-файлов */
$dh = opendir($path);
while ($file = readdir($dh)) {
	if (strpos($file, '.xml') !== false) {
		@unlink($path.'/'.$file);
		break;
	}
}
closedir($dh);

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

global $bufer;
$bufer = '';

function loadFilePart($f, $delimiter, $path = NULL) {
	if (!empty($path) && stripos(file_get_contents($path), $delimiter) === FALSE)
	{
		return '';
	}
	global $bufer;
	$res = '';
	while ($row = fgets($f)) {
		if (($p = mb_strpos($row, $delimiter, 0, 'utf-8')) !== false) {
			$res .= mb_substr($row, 0, $p + mb_strlen($delimiter, 'utf-8'), 'utf-8');
			$newBufer = mb_substr($row, $p + mb_strlen($delimiter, 'utf-8'), mb_strlen($row, 'utf-8'), 'utf-8');
			break;
		} else {
			$res .= $row;
		}
	}
	$res = $bufer.$res;
	$bufer = @$newBufer;
	return $res;
}

$xmlFileFullPath = $path.'/'.$xmlfile;
$f = fopen($xmlFileFullPath, 'r');

/* Обработка категорий */
$content = loadFilePart($f, '</categories>', $xmlFileFullPath);
$ps = mb_strpos($content, '<categories>', 0, 'utf-8');
$pe = mb_strpos($content, '</categories>', 0, 'utf-8');
if ($ps && $pe) {
	$contentCats = mb_substr($content, $ps + mb_strlen('<categories>', 'utf-8'), $pe - $ps - mb_strlen('<categories>', 'utf-8'), 'utf-8');
	$content = mb_substr($content, $pe + mb_strlen('</categories>', 'utf-8'), mb_strlen($content, 'utf-8'), 'utf-8');
	$cats = array();
	while (true) {
		$ps = mb_strpos($contentCats, '<category', 0, 'utf-8');
		$pe = mb_strpos($contentCats, '</category>', 0, 'utf-8');
		if ($ps !== false && $pe !== false) {
			$category = mb_substr($contentCats, $ps + mb_strlen('<category', 'utf-8'), $pe - $ps - mb_strlen('<category', 'utf-8'), 'utf-8');
			$contentCats = mb_substr($contentCats, $pe + mb_strlen('</category>', 'utf-8'), mb_strlen($contentCats, 'utf-8'), 'utf-8');

			$matches = array();
			preg_match('/ id="(\d+)"/', $category, $matches);
			$id = $matches[1];

			$ps = mb_strpos($category, '>', 0, 'utf-8');
			$title = mb_substr($category, $ps + 1, mb_strlen($category, 'utf-8'), 'utf-8');
			$title = str_replace('<![CDATA[', '', $title);
			$title = str_replace(']]>', '', $title);

			$cat = array(
				'id' => $id,
				'title' => $title
			);
			if (preg_match('/parentId="(\d+)"/', $category, $matches)) {
				$parentId = $matches[1];
				$cat['parent_id'] = $parentId;
			}
			$cats[] = $cat;
		} else break;
	}
	foreach ($cats as $item)
	{
		$item['title'] = mysql_real_escape_string($item['title']);
		importTerm($item);
	}
}

/* Обработка товаров */
while (true) {
	$content = loadFilePart($f, '</offer>');
	$psp = mb_strpos($content, '<offer ', 0, 'utf-8');
	$pep = mb_strpos($content, '</offer>', 0, 'utf-8');

	if ($psp !== false && $pep !== false)
	{
		$product = mb_substr($content, $psp + mb_strlen('<offer ', 'utf-8'), $pep - $psp - mb_strlen('<offer ', 'utf-8'), 'utf-8');

		$matches = array();

		if (!GdeSlonImport::filterImport($product))
			continue;

		preg_match('/ id="(\d+)"/', $product, $matches);
		$id = @$matches[1];

		preg_match('|\<url\>(.+)\</url\>|', $product, $matches);
		$url = @$matches[1];

		preg_match('|\<price\>(.+)\</price\>|', $product, $matches);
		$price = @$matches[1];

		preg_match('|\<currencyId\>(.+)\</currencyId\>|', $product, $matches);
		$currency = @$matches[1];

		preg_match('|\<picture\>(.+)\</picture\>|', $product, $matches);
		$image = @$matches[1];

		preg_match('|\<name\>(.+)\</name\>|', $product, $matches);
		$title = @$matches[1];
		$title = str_replace('<![CDATA[', '', $title);
		$title = str_replace(']]>', '', $title);

		if (!$title && preg_match('|\<model\>(.+)\</model\>|', $product, $matches))
		{
			$title = @$matches[1];
			$title = str_replace('<![CDATA[', '', $title);
			$title = str_replace(']]>', '', $title);
		}


		$ps = mb_strpos($product, '<description>', 0, 'utf-8');
		$pe = mb_strpos($product, '</description>', 0, 'utf-8');
		$descr = mb_substr($product, $ps + mb_strlen('<description>', 'utf-8'), $pe - $ps - mb_strlen('<description>', 'utf-8'), 'utf-8');
		$descr = str_replace('<![CDATA[', '', $descr);
		$descr = str_replace(']]>', '', $descr);

		preg_match('|\<categoryId\>(.+)\</categoryId\>|', $product, $matches);
		$categoryId = @$matches[1];

		//обновление поста
		importPost(array(
			'id'			=> $id,
			'title'			=> $title,
			'description'	=> nl2br($descr),
			'url'			=> $url,
			'price'			=> $price,
			'currency'		=> $currency,
			'image'			=> $image,
			'category_id'	=> $categoryId,
		), GdeSlonImport::parseParams($product));
		unset($content);
	} else { break; }
}

fclose($f);
@unlink($path.'/'.$xmlfile);

flushCache($cats);

wp_mail(get_option('admin_email'), 'Обновление товаров', 'Обновление товаров завершено!');
echo "Done!\n";
exit;
