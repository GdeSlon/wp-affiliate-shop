<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.5.4
Author: GdeSlon
*/

/**
 * Определение констант
 */
define('GS_PLUGIN_URL', get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/');
if (!defined('GS_PLUGIN_PATH')) {
	define('GS_PLUGIN_PATH', dirname(__FILE__));
}


require_once(GS_PLUGIN_PATH.'/config.php');
register_activation_hook(__FILE__, array(GS_Config::init(), 'activate'));
register_deactivation_hook(__FILE__, array(GS_Config::init(), 'deactivate'));
require_once(GS_PLUGIN_PATH.'/options-controller.php');
require_once(GS_PLUGIN_PATH.'/gs_tools.php');
require_once(GS_PLUGIN_PATH.'/widget.php');
require_once(GS_PLUGIN_PATH.'/posts.php');

/**
 * Вывод стилей
 */
add_action("wp_head", "psStyles", 100);

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
	echo '.products-list TD { border: 1px solid #aaa; padding: 4px; vertical-align: top; width: '.round(100 / max(1, GS_Config::init()->get('ps_row_limit'))).'%; } ';
	echo '.products-price { font-weight: bold; } ';
	echo '.products-description { font-size: 0.9em; text-align: left; color: #777; }';
	echo '.product-table .product-image img { max-width: none; }';
	echo '.product-table tr, .product-table td{ border:0; }';
	echo '</style>';
}

/**
 * Скачивание yandex-direct
 */
add_action( 'wp_ajax_get_direct', 'get_direct' );
add_action( 'wp_ajax_nopriv_get_direct', 'get_direct' );
function get_direct()
{
	require_once(GS_PLUGIN_PATH.'/get_direct.php');
	die;
}

/**
 * Выкачка данных из выгрузки
 */
add_action( 'wp_ajax_parse_url', 'ajax_parse_url' );
add_action( 'wp_ajax_nopriv_parse_url', 'ajax_parse_url' );
function ajax_parse_url()
{
	require_once(GS_PLUGIN_PATH.'/cron.php');
	die;
}