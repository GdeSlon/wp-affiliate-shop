<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.4.8
Author: GdeSlon
*/
require_once('config.php');
register_activation_hook(__FILE__, array(GS_Config::init(), 'activate'));
register_deactivation_hook(__FILE__, array(GS_Config::init(), 'deactivate'));
require_once('options-controller.php');
require_once('gs_tools.php');
require_once('widget.php');
require_once('posts.php');

/**
 * Вывод стилей
 */
add_action("wp_head", "psStyles", 100);

/**
 * Определение констант
 */
define('GS_PLUGIN_URL', get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/');

//*****************************************************************************************************

add_action("admin_menu", "psAdminPage", 8);

function psAdminPage()
{
	add_menu_page('GdeSlon Shop', 'GdeSlon Shop', 'edit_pages', 'wp-affiliate-shop', array(GS_Options_Controller::init(), 'render'));
	add_submenu_page(__FILE__, 'Настройки', 'Настройки', 'edit_pages', 'wp-affiliate-shop', array(GS_Options_Controller::init(), 'render'));
	add_submenu_page('wp-affiliate-shop', 'Каталог', 'Каталог', 'edit_pages', 'edit.php?post_type=ps_catalog');
	add_submenu_page('wp-affiliate-shop', 'Категории', 'Категории', 'edit_pages', 'edit-tags.php?taxonomy=ps_category');
}

function psStyles()
{
	echo '<style type="text/css">';
	echo '.products-list { width: 100%; border-spacing: 10px;} ';
	echo '.products-list TD { border: 1px solid #aaa; padding: 4px; vertical-align: top; width: '.round(100 / GS_Config::init()->get('ps_row_limit')).'%; } ';
	echo '.products-price { font-weight: bold; } ';
	echo '.products-description { font-size: 0.9em; text-align: left; color: #777; }';
	echo '.product-table .product-image img { max-width: none; }';
	echo '.product-table tr, .product-table td{ border:0; }';
	echo '</style>';
}
