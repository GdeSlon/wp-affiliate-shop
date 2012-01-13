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

?>