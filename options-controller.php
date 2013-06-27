<?php
/**
 * Страница опций выделена в отдельный контейнер, чтобы разгрузить файл плагина.
 * Применён паттерн SingleTon
 */
class GS_Options_Controller
{
	/**
	 * Класс wpdb
	 * @var wpdb
	 */
	protected $_wpdb;

	/**
	 * Закрытый конструктор
	 */
	protected function __construct()
	{
		global $wpdb;
		$this->_wpdb = $wpdb;
	}

	/**
	 * Функция для сохранения опций плагина
	 * @include templates/admin-options.php
	 * @return void
	 */
	public  function render()
	{
		$isUpdated = FALSE;
		$isDeleted = FALSE;
		$isError = FALSE;
		if (isset($_POST['action']))
		{
			switch($_POST['action'])
			{
				case 'update':
					foreach(array('ps_get_enable', 'ps_download_images','ps_url', 'ps_page','ps_row_limit','ps_limit','widget_depth','import_price','import_title','import_vendor') as $option)
					{
						GS_Config::init()->set($option, in_array($option, array('ps_download_images','ps_get_enable')) ?
									(isset($_POST[$option]) ? '1' : '0') :
									@$_POST[$option]
						);
					}
					$isUpdated = TRUE;
					break;
				case 'delete':
					ignore_user_abort(true);
					set_time_limit(36000);
					$type = @$_POST['type'];
					$agree = @$_POST['agree'];
					if (in_array($type, array('all', 'products', 'categories')) && $agree)
					{
						if ($type == 'all' || $type == 'products')
						{
							self::deleteProducts();
						}
						if ($type == 'all' || $type == 'categories')
						{
							self::deleteCategories();
						}
						$isDeleted = TRUE;
					}
					else
						$isError = TRUE;
					break;
			}
		}
		$url = GS_Config::init()->get('ps_url');
		$get_enable = (int)GS_Config::init()->get('ps_get_enable');
		$ps_page = GS_Config::init()->get('ps_page');
		$dirname = basename(dirname(__FILE__));
		$categoriesNumber = $this->calcCategories();
		$productsNumber = $this->calcProducts();
		require_once(dirname(__FILE__).'/templates/admin-options.php');
	}

	/**
	 * Количество категорий в базе
	 * @return integer
	 */
	public function calcCategories()
	{
		return $this->_wpdb->get_var("SELECT COUNT(*) FROM `{$this->_wpdb->term_taxonomy}` WHERE `taxonomy` = 'ps_category'");
	}

	/**
	 * Количество продуктов в базе
	 * @return integer
	 */
	public function calcProducts()
	{
		return $this->_wpdb->get_var("SELECT COUNT(*) FROM `{$this->_wpdb->posts}` WHERE `post_type` = 'ps_catalog'");
	}

	public function deleteCategories()
	{
		return $this->_wpdb->get_var("DELETE a,b,c FROM {$this->_wpdb->term_taxonomy} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.term_taxonomy_id = b.term_taxonomy_id) LEFT JOIN {$this->_wpdb->terms} c ON (a.term_id = c.term_id) WHERE a.taxonomy = 'ps_category';");
	}

	public function deleteProducts()
	{
		return $this->_wpdb->query("DELETE a,b,c FROM {$this->_wpdb->posts} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.ID = b.object_id) LEFT JOIN {$this->_wpdb->postmeta} c ON (a.ID = c.post_id) WHERE a.post_type = 'ps_catalog'");
	}

	/**
	 * Закрываем конструктор и прикручиваем паттерн Singleton.
	 * @static
	 * @return GS_Options_Controller
	 */
	public static function init()
	{
		static $self;
		if (!is_object($self))
		{
			$self = new self();
		}
		return $self;
	}
}
