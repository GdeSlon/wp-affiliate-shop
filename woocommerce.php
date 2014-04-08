<?php
/**
 * Собрал весь функционал для woocommerce в одном singleton-классе
 * User: keriat
 * Date: 4/3/14
 */

class GdeSlon_Woocommerce
{
	static $_entity;
	protected $_isWoocommerceInstalled = FALSE;

	/**
	 * Закрытый конструктор класса. Реализация паттерна SingleTon
	 * Мы проверяем, установлен ли плагин woocommerce, выставляем соответствующий флаг и вешаем на событие init
	 * инициализацию хуков для woocommerce
	 */
	protected function __construct()
	{
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ($this->_isWoocommerceInstalled = is_plugin_active('woocommerce/woocommerce.php'))
		{
			add_action('init', array($this, '_initHooks'));
		}
	}

	/**
	 * Срабатывает на событие Init.
	 * метод приватный, публичный доступ оставлен для корректной работы user_function_call
	 */
	public function _initHooks()
	{
		remove_all_filters('woocommerce_cart_link'); //удаляем ссылку на корзину
		remove_all_filters('woo_nav_after'); //удаляем сам блок корзины

		remove_all_filters( 'woocommerce_simple_add_to_cart');
		remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30 );

		/**
		 * удаляем станицу корзины из базы
		 */
		$cart_id = woocommerce_get_page_id('cart');
		if($cart_id)
		{
			wp_delete_post($cart_id);
		}

		/**
		 * Меняем работу ссылки добавления в корзину. Теперь она переадресовывает на партнёрку.
		 */
		add_action('woocommerce_simple_add_to_cart', array($this, 'hook_change_link'), 1, 2);
		add_filter('woocommerce_loop_add_to_cart_link', array($this, 'hook_change_link'), 1, 2);

		/**
		 * Подгружаем изображение из мета-поля поста
		 */
		add_filter('woocommerce_single_product_image_html', array($this, 'hook_woocommerce_single_product_image_html'), 1, 2);
		add_action('woocommerce_placeholder_img', array($this, 'hook_woocommerce_placeholder_img'), 11, 1);
	}

	public function hook_woocommerce_single_product_image_html($imgHtml, $postId)
	{
		if (stripos($imgHtml, 'placeholder') && $imgPath = get_post_meta($postId, 'image', TRUE))
		{
			$imgHtml = '<img src="'.$imgPath.'"/>';
		}
		return $imgHtml;
	}

	public function hook_woocommerce_placeholder_img($imgHtml)
	{
		global $product;
		if (stripos($imgHtml, 'placeholder') && $imgPath = get_post_meta($product->id, 'image', TRUE))
		{
			$imgHtml = '<img src="'.$imgPath.'"/>';
		}
		return $imgHtml;
	}


	public function hook_change_link()
	{
		global $post, $product;
		echo '<a href="'.add_query_arg('do_product_action', 'redirect', get_permalink($post)).
				'" class="button add_to_cart_button product_type_simple" target="_blank" >'.
				esc_html( $product->add_to_cart_text()).'</a>';
	}

	static function isWoocommerceActive()
	{
		return self::init()->_isWoocommerceInstalled;
	}

	/**
	 * @static
	 * @return GdeSlon_Woocommerce
	 */
	static function init()
	{
		if (!is_object(self::$_entity))
		{
			self::$_entity = new self();
		}
		return self::$_entity;
	}
}