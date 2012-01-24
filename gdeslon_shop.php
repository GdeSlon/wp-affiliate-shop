<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.0
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
				foreach(get_posts("post_type=ps_catalog&numberposts=-1") as $obPost)
					wp_delete_post($obPost->ID, TRUE);
			}
			if ($type == 'all' || $type == 'categories')
			{
				foreach(get_terms('ps_category') as $obTerm)
					wp_delete_term($obTerm->term_id, 'ps_category');
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
	return count(get_terms('ps_category'));
}
function calcProducts()
{
	return count(get_posts("post_type=ps_catalog&numberposts=-1"));
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
	update_option('ps_get_enable',1);
	update_option('ps_access_code', md5(rand(1, 10000).rand(1, 1000).time()));
}

function psDeactivate() {
	global $wpdb;
	delete_option('ps_get_enable');
	delete_option('ps_url');
	delete_option('ps_access_code');
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