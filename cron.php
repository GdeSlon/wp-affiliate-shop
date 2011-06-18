<?php
ignore_user_abort(true);
set_time_limit(36000);
define('DOING_CRON', true);

require_once(dirname(__FILE__) . '/../../../wp-load.php');

$accessCode = get_option('ps_access_code');
if (empty($_GET['code'])) {
	if (!empty($_SERVER['REQUEST_URI'])) exit;
} else {
	if ($accessCode != $_GET['code']) exit;
}

$base = dirname(__FILE__);
$path = $base.'/downloads';

$url = get_option('ps_url');


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$file = curl_exec($ch);
curl_close($ch);

$f = fopen($path.'/archive.zip', 'w');
fwrite($f, $file);
fclose($f);

/* Распаковка архива */
$filename = escapeshellarg($path.'/archive.zip');
$destination_folder = escapeshellarg($path);

shell_exec("unzip -ou $filename -d $destination_folder");

$xmlfile = '';
$dh = opendir($path);
while ($file = readdir($dh)) {
	if (strpos($file, '.xml') !== false) {
		$xmlfile = $file;
		break;
	}
}
closedir($dh);

global $bufer;
$bufer = '';

function loadFilePart($f, $delimiter) {
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

$f = fopen($path.'/'.$xmlfile, 'r');

/* Обработка категорий */
$content = loadFilePart($f, '</categories>');
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
			
			$cat = array(
				'id' => $id,
				'title' => $title
			);
			if (preg_match('/parent_id="(\d+)"/', $category, $matches)) {
				$parentId = $matches[1];
				$cat['parent_id'] = $parentId;
			}
			$cats[] = $cat;
		} else break;
	}
	foreach ($cats as $item) {
		$item['title'] = mysql_real_escape_string($item['title']);
		$res = $wpdb->get_row("SELECT * FROM ps_categories WHERE id = {$item['id']}");
		if (!empty($res)) {
			$wpdb->query("UPDATE ps_categories SET title = '{$item['title']}' WHERE id = {$item['id']}");
		} else {
			if (!empty($item['parent_id'])) {
				$wpdb->query("INSERT INTO ps_categories SET id = {$item['id']}, parent_id = {$item['parent_id']}, title = '{$item['title']}'");
			} else {
				$wpdb->query("INSERT INTO ps_categories SET id = {$item['id']}, title = '{$item['title']}'");
			}
		}
	}
}

/* Обработка товаров */
$wpdb->query("UPDATE ps_products SET marked = 0 WHERE status <> 2");
while (true) {
	$content = loadFilePart($f, '</offer>');
	$psp = mb_strpos($content, '<offer ', 0, 'utf-8');
	$pep = mb_strpos($content, '</offer>', 0, 'utf-8');
	if ($psp !== false && $pep !== false) {
		$product = mb_substr($content, $psp + mb_strlen('<offer ', 'utf-8'), $pep - $psp - mb_strlen('<offer ', 'utf-8'), 'utf-8');
		
		$matches = array();
		
		preg_match('/ id="(\d+)"/', $product, $matches);
		$id = $matches[1];
		
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
		
		$ps = mb_strpos($product, '<description>', 0, 'utf-8');
		$pe = mb_strpos($product, '</description>', 0, 'utf-8');
		$descr = mb_substr($product, $ps + mb_strlen('<description>', 'utf-8'), $pe - $ps - mb_strlen('<description>', 'utf-8'), 'utf-8');
		
		preg_match('|\<category_id\>(.+)\</category_id\>|', $product, $matches);
		$categoryId = @$matches[1];
		
		$title = mysql_real_escape_string($title);
		$descr = mysql_real_escape_string($descr);
		$res = $wpdb->get_row("SELECT * FROM ps_products WHERE id = {$id}");
		if (!empty($res)) {
			if ($res->status != 2) {
				if (!empty($res->manual)) {
					$title = $res->title;
					$descr = $res->description;
				}
				$wpdb->query("UPDATE ps_products SET
					title = '{$title}',
					description = '{$descr}',
					url = '{$url}',
					price = '{$price}',
					currency = '{$currency}',
					image = '{$image}',
					category_id = '{$categoryId}',
					marked = 1, status = 1
					WHERE id = {$id}");
			}
		} else {
			$wpdb->query("INSERT INTO ps_products SET
				id = {$id},
				title = '{$title}',
				description = '{$descr}',
				url = '{$url}',
				price = '{$price}',
				currency = '{$currency}',
				image = '{$image}',
				category_id = '{$categoryId}',
				marked = 1, status = 1");
		}
		
		unset($content);
	} else { break; }
}
$wpdb->query("UPDATE ps_products SET status = 0 WHERE marked = 0");

fclose($f);

wp_mail(get_option('admin_email'), 'Обновление товаров', 'Обновление товаров завершено!');

echo 'Done!';
exit;
?>