<?php
class GdeSlonWidget extends WP_Widget
{
	protected $_wpdb;
	public function __construct()
	{
		global $wpdb;
		$this->_wpdb = $wpdb;
		parent::WP_Widget('gdeslon_widget', 'GdeSlon Widget', array('description' => 'Вывод блока с категориями'));
	}

	/**
	 * @see WP_Widget::widget
	 * @param $args
	 * @param $instance
	 * @return void
	 */
	public function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$depth = empty($instance['depth']) ? 0 : intval($instance['depth']);
		echo $before_widget;
		//содержание функции psCategories
		echo '<ul><li>', $before_title, ($title ? $title : 'Разделы каталога'), $after_title;
		wp_list_categories(array(
			'taxonomy'   => 'ps_category',
			'title_li'   => '',
			'hide_empty' => 0,
			'depth'      => $depth,
		));
		echo '</li></ul>';
		echo $after_widget;
	}

	/**
	 * @param $new_instance
	 * @see WP_Widget::update
	 * @param $old_instance
	 * @return array
	 */
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['depth'] = intval($new_instance['depth']);
		return $instance;
	}

	/**
	 * @see WP_Widget::form
	 * @param $instance
	 * @return void
	 */
	public function form($instance) {
		if ($instance) {
			$title = esc_attr($instance['title']);
			$depth = $instance['depth'];
		}
		else {
			$title = __('New title', 'text_domain');
			$depth = 0;
		}
		?>
	<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('Вложенность:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>" type="text" value="<?php echo $depth; ?>" />
	</p>
	<?php
 	}
}
add_action('widgets_init', 'registerWindget');
function registerWindget()
{
	register_widget("GdeSlonWidget");
}