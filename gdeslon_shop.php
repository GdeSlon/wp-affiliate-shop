<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.0
Author: GdeSlon
*/
require('gs_tools.php');
require('gs_pages.php');
require('widget.php');
require('posts.php');

add_filter("the_content", "psCatalog");
add_filter("wp_title", "psTitle");
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
	if (isset($_POST['action'])&&($_POST['action'] == 'update'))
	{
		update_option('ps_get_enable', isset($_POST['ps_get_enable']) ? '1' : '0');
		update_option('ps_use_posts', isset($_POST['ps_use_posts']) ? '1' : '0');
		update_option('ps_url', $_POST['ps_url']);
		update_option('ps_page', $_POST['ps_page']);
		update_option('ps_limit', $_POST['ps_limit']);
		update_option('ps_row_limit', $_POST['ps_row_limit']);
		$isUpdated = TRUE;
	}
	if (isset($_POST['action'])&&($_POST['action'] == 'delete'))
	{
		$type = @$_POST['type'];
		$agree = @$_POST['agree'];
		if (in_array($type, array('all', 'products', 'categories')) && $agree)
		{
			if ($type == 'all' || $type == 'products')
				$wpdb->query('DELETE FROM ps_products');
			if ($type == 'all' || $type == 'categories')
				$wpdb->query('DELETE FROM ps_categories');
			$isDeleted = TRUE;
		}
		else
			$isError = TRUE;
	}
	$url = get_option('ps_url');
	$get_enable = (int)get_option('ps_get_enable');
	$use_posts = (int)get_option('ps_use_posts');
	$ps_page = get_option('ps_page');
	$dirname = basename(dirname(__FILE__));
	require_once('templates/admin-options.php');
}

function calcCategories()
{
	global $wpdb;
	return $wpdb->get_var("SELECT COUNT(*) FROM ps_categories");
}
function calcProducts()
{
	global $wpdb;
	return $wpdb->get_var("SELECT COUNT(*) FROM ps_products");
}

/**
 * @include admin-catalog.php
 * @return void
 */
function psCatalogPage() {
	global $wpdb;
	$isUpdated = FALSE;
	if( isset($_POST['action'])&&($_POST['action'] == 'update')) {
		$isUpdated = TRUE;
	}
	$ps_search = @$_GET['ps_search'];
	$ps_cat = @$_GET['ps_cat'];
	if (!empty($ps_search) || !empty($ps_cat))
	{
		$where = array('1=1');
		if (!empty($ps_search))
		{
			$ps_search - mysql_real_escape_string($ps_search);
			$where[] = "UPPER(title) LIKE UPPER('%$ps_search%')";
		}
		if (!empty($ps_cat))
		{
			$allCats = getCategoriesChildren($ps_cat);
			$where[] = "category_id IN (".implode(',', $allCats).")";
		}

		if (!empty($_GET['del']))
		{
			$wpdb->query("UPDATE ps_products SET status = 2 WHERE id = {$_GET['del']}");
		}
		if (@$_POST['action'] == 'update')
		{
			if (!empty($_POST['product_id']))
			{
				$id = $_POST['product_id'];
				$title = mysql_real_escape_string($_POST['ps_title']);
				$description = mysql_real_escape_string($_POST['ps_description']);
				$wpdb->query("UPDATE ps_products SET title = '$title', description = '$description', manual = 1 WHERE id = $id");
			}
			$products = $wpdb->get_results("SELECT id FROM ps_products WHERE ".implode(' AND ', $where)." ");
			$IDs = array();
			foreach ($products as $item) $IDs[] = $item->id;
			$wpdb->query("UPDATE ps_products SET bestseller = 0 WHERE id IN (".implode(',', $IDs).")");
			if (!empty($_POST['bs']) && is_array($_POST['bs'])) {
				$wpdb->query("UPDATE ps_products SET bestseller = 1 WHERE id IN (".implode(',', $_POST['bs']).")");
			}
		}

		$statuses = array(
			0 => 'Неакт.',
			1 => 'Актив.',
			2 => 'Удален'
		);
		$products = $wpdb->get_results("SELECT * FROM ps_products WHERE ".implode(' AND ', $where)." ORDER BY title ASC");
	}
	require_once('templates/admin-catalog.php');
}

add_action("admin_menu", "psAdminPage");

function psAdminPage() {
	add_menu_page('GdeSlon Shop', 'GdeSlon Shop', 'edit_pages', __FILE__, 'psOptionsPage');
	add_submenu_page(__FILE__, 'Настройки', 'Настройки', 'edit_pages', __FILE__, 'psOptionsPage');
	if (!get_option('ps_use_posts'))
		add_submenu_page(__FILE__, 'Каталог', 'Каталог', 'edit_pages', 'psCatalogPage', 'psCatalogPage');
}

function psActivate() {
	global $wpdb;
	update_option('ps_url', '');
	update_option('ps_get_enable',1);
	update_option('ps_use_posts',1);
	update_option('ps_access_code', md5(rand(1, 10000).rand(1, 1000).time()));
	$wpdb->query("
		CREATE TABLE `ps_categories` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`parent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
			`taxonomy_id` INT(11) UNSIGNED NULL DEFAULT NULL,
			`title` VARCHAR(200) NOT NULL,
			PRIMARY KEY (`id`),
			INDEX `FKps_categories_parent_id` (`parent_id`),
			INDEX `FKps_categories_taxonomy_id` (`taxonomy_id`),
			CONSTRAINT `FKps_categories_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `ps_categories` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;
    ");
	$wpdb->query("
		CREATE TABLE `ps_products` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`category_id` INT(11) UNSIGNED NOT NULL,
			`post_id` INT(11) UNSIGNED NULL,
			`title` TEXT NOT NULL,
			`description` TEXT NULL,
			`url` VARCHAR(255) NOT NULL,
			`price` VARCHAR(50) NOT NULL,
			`currency` VARCHAR(10) NOT NULL,
			`image` VARCHAR(255) NULL DEFAULT NULL,
			`bestseller` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`status` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
			`marked` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`manual` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			INDEX `FKps_products_category_id` (`category_id`),
			INDEX `FKps_products_post_id` (`post_id`),
			INDEX `ps_products_status` (`status`),
			INDEX `ps_products_marked` (`marked`),
			INDEX `ps_products_title` (`title`(100)),
			CONSTRAINT `FKps_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `ps_categories` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;
    ");
}

function psDeactivate() {
	global $wpdb;
	delete_option('ps_get_enable');
	delete_option('ps_use_posts');
	delete_option('ps_url');
	delete_option('ps_access_code');
	$wpdb->query("DROP TABLE `ps_products`;");
	$wpdb->query("DROP TABLE `ps_categories`;");
}

register_activation_hook(__FILE__, 'psActivate');
register_deactivation_hook(__FILE__, 'psDeactivate');
?>