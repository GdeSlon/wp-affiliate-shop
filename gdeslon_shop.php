<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.5.5
Author: GdeSlon
*/

/**
 * Определение констант
 */
define('GS_PLUGIN_URL', get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/');
if (!defined('GS_PLUGIN_PATH')) {
	define('GS_PLUGIN_PATH', dirname(__FILE__));
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(!is_plugin_active('woocommerce/woocommerce.php')){
	function my_admin_notice() {
		?>
		<div class="error">
			<p><?php _e('Плагин GdeSlon Affiliate Shop зависит от плагина Woocommerce! Пожалуйста установите плагин woocommerce!', 'error-woocommerce-require'); ?></p>
		</div>
	<?php
	}
	add_action( 'admin_notices', 'my_admin_notice' );
	function deactivate_plugin_conditional() {
		deactivate_plugins('wp-affiliate-shop/gdeslon_shop.php');
		remove_menu_page('wp-affiliate-shop');
	}
	add_action( 'admin_init', 'deactivate_plugin_conditional' );
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
//	add_submenu_page('wp-affiliate-shop', 'Каталог', 'Каталог', 'edit_pages', 'edit.php?post_type=ps_catalog');
//	add_submenu_page('wp-affiliate-shop', 'Категории', 'Категории', 'edit_pages', 'edit-tags.php?taxonomy=ps_category');
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


add_filter('woocommerce_loop_add_to_cart_link', 'change_link', 1, 2);
function change_link(){
	global $post;

	?>
	<a href="<?php echo add_query_arg('do_product_action', 'redirect', get_permalink($post))?>" target="_blank" >
		<img src="<?php echo GS_PLUGIN_URL?>img/buy.png" alt="Купить <?php echo $post->post_title; ?>" height="25px" style="width:124px"/>
	</a>
<?php

}


include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(is_plugin_active('woocommerce/woocommerce.php')){

	add_action( 'pre_get_posts', 'custom_pre_get_posts_query' );

	function custom_pre_get_posts_query( $q ) {


		if ( ! $q->is_main_query() ) return;
		if ( ! $q->is_post_type_archive() ) return;

		if ( ! is_admin() && is_shop() ) {

			$q->set( 'post_type', array('product', 'ps_catalog') );
		}

		remove_action( 'pre_get_posts', 'custom_pre_get_posts_query' );

	}

//	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

	remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );

	remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30 );

	$cart_id = woocommerce_get_page_id('cart');

	if($cart_id){
		wp_delete_post($cart_id);
	}
}

