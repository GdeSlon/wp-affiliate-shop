<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.3.2
Author: GdeSlon
*/
require('gs_tools.php');
require('widget.php');
require('posts.php');

add_action("wp_head", "psStyles", 100);

// *****************************************************************************************************

/**
 * @include admin-options.php
 * @return void
 */
function psOptionsPage()
{
	global $wpdb;
	$isUpdated = FALSE;
	$isDeleted = FALSE;
	$isError = FALSE;
	if (isset($_POST['action'])&&($_POST['action'] == 'update')){
		update_option('ps_get_enable', isset($_POST['ps_get_enable']) ? '1' : '0');
		update_option('ps_url', $_POST['ps_url']);
		update_option('ps_page', $_POST['ps_page']);
		update_option('ps_limit', $_POST['ps_limit']);
		update_option('ps_row_limit', $_POST['ps_row_limit']);
		update_option('widget_depth', $_POST['widget_depth']);
		update_option('import_price', $_POST['import_price']);
		update_option('import_title', $_POST['import_title']);
		update_option('import_vendor', $_POST['import_vendor']);
		$isUpdated = TRUE;
	}
	if (isset($_POST['action'])&&($_POST['action'] == 'delete'))
	{
		ignore_user_abort(true);
		set_time_limit(36000);
		$type = @$_POST['type'];
		$agree = @$_POST['agree'];
		if (in_array($type, array('all', 'products', 'categories')) && $agree)
		{
			if ($type == 'all' || $type == 'products')
			{
				deleteProducts();
			}
			if ($type == 'all' || $type == 'categories')
			{
				deleteCategories();
			}
			$isDeleted = TRUE;
		}
		else
			$isError = TRUE;
	}
	$url = get_option('ps_url');
	$get_enable = (int)get_option('ps_get_enable');
	$ps_page = get_option('ps_page');
	$dirname = basename(dirname(__FILE__));
	require_once('templates/admin-options.php');
}

function calcCategories()
{
	global $wpdb;
	return $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->term_taxonomy}` WHERE `taxonomy` = 'ps_category'");
}
function calcProducts()
{
	global $wpdb;
	return $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE `post_type` = 'ps_catalog'");
}
function deleteCategories()
{
	global $wpdb;
	return $wpdb->get_var("DELETE a,b,c FROM {$wpdb->term_taxonomy} a LEFT JOIN {$wpdb->term_relationships} b ON (a.term_taxonomy_id = b.term_taxonomy_id) LEFT JOIN {$wpdb->terms} c ON (a.term_id = c.term_id) WHERE a.taxonomy = 'ps_category';");
}

function deleteProducts()
{
	global $wpdb;
	return $wpdb->query("DELETE a,b,c FROM {$wpdb->posts} a LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id) LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id) WHERE a.post_type = 'ps_catalog'");
}

add_action("admin_menu", "psAdminPage", 8);

function psAdminPage() {
	add_menu_page('GdeSlon Shop', 'GdeSlon Shop', 'edit_pages', 'wp-affiliate-shop', 'psOptionsPage');
	add_submenu_page(__FILE__, 'Настройки', 'Настройки', 'edit_pages', 'wp-affiliate-shop', 'psOptionsPage');
	add_submenu_page('wp-affiliate-shop', 'Каталог', 'Каталог', 'edit_pages', 'edit.php?post_type=ps_catalog');
	add_submenu_page('wp-affiliate-shop', 'Категории', 'Категории', 'edit_pages', 'edit-tags.php?taxonomy=ps_category');
//	add_submenu_page('');

}

function psActivate() {
	global $wpdb;
	update_option('ps_url', '');
	update_option('widget_depth', '0');
	update_option('ps_get_enable',1);
	update_option('ps_access_code', md5(rand(1, 10000).rand(1, 1000).time()));
	update_option('import_price', '');
	update_option('import_title', '');
	update_option('import_vendor', '');

}

function psDeactivate() {
	global $wpdb;
	delete_option('widget_depth');
	delete_option('ps_get_enable');
	delete_option('ps_url');
	delete_option('ps_access_code');
	delete_option('import_price');
	delete_option('import_title');
	delete_option('import_vendor');
}

function psStyles()
{
	echo '<style type="text/css">';
	echo '.products-list { width: 100%; border-spacing: 10px;} ';
	echo '.products-list TD { border: 1px solid #aaa; padding: 4px; vertical-align: top; width: '.round(100 / get_option('ps_row_limit')).'%; } ';
	echo '.products-price { font-weight: bold; } ';
	echo '.products-description { font-size: 0.9em; text-align: left; color: #777; }';
	echo '.product-table .product-image img { max-width: none; }';
	echo '.product-table tr, .product-table td{ border:0; }';
	echo '</style>';
}

register_activation_hook(__FILE__, 'psActivate');
register_deactivation_hook(__FILE__, 'psDeactivate');
?>
