<?php
add_action('init', 'registerGdeSlonPostType');
function registerGdeSlonPostType()
{
	if (!get_option('ps_use_posts'))
		return;
	$postTypeConfig = array(
		'public' => true,
		'exclude_from_search' => true,
		'menu_position' => 20,
		'has_archive'	  => true,
		'supports'=> array(
			'title',
			'editor',
			'page-attributes',
			'thumbnail',
			'excerpt'
		),
		'labels'	=> array(
			'name' => 'Каталог',
			'singular_name' => 'Товар',
			'not_found'=> __('Товары не найдены'),
			'not_found_in_trash'=> __('Товары не найдены в корзине'),
			'edit_item' => __('Редактирование ', 'товара'),
			'search_items' => __('Поиск товара'),
			'view_item' => __('Просмотр товара'),
			'new_item' => __('Новый товар'),
			'add_new' => __('Создать'),
			'add_new_item' => __('Новый товар'),
		),
		'show_in_nav_menus'=> false,
	);
	register_post_type('ps_catalog', $postTypeConfig);
	register_taxonomy(
		'ps_category',
		'ps_catalog',
		array(
			'hierarchical' => true,
			'label' => __( 'Категории товаров' ),
			'sort' => true,
			'args' => array( 'orderby' => 'term_order' ),
			'rewrite' => array( 'slug' => 'type' )
		)
	);
}

/**
 * Создание или обновление таксономии.
 * При этом происходит связывание термов и таксономии
 * @param $item
 * @return
 */
function importTerm($item)
{
	if (!get_option('ps_use_posts'))
		return;
	global $wpdb;
	$parentId = 0;
	$dbItem = $wpdb->get_row("SELECT * FROM ps_categories WHERE id = {$item['id']}");
	if ($dbItem->parent_id)
	{
		$obParentDbItem = $wpdb->get_row("SELECT * FROM ps_categories WHERE id = {$dbItem->parent_id}");
		//@todo: Нужно еще обрабатывать ситуацию, когда родительская категория еще не создана. Пока отложу, вероятность небольшая
		$parentId = $obParentDbItem->taxonomy_id;
	}
	//@todo: еще одна возможная неприятная ситуация — если удалят категорию каталога из админки. Пока отложу, вероятность небольшая
	if ($dbItem->taxonomy_id)
		wp_update_term($dbItem->taxonomy_id, 'ps_category', array('name' => $dbItem->title, 'parent' => $parentId));
	else
	{
		$result = wp_insert_term($dbItem->title, 'ps_category', array('parent'=> $parentId));
		$wpdb->query("UPDATE ps_categories SET taxonomy_id = '{$result['term_id']}' WHERE id = {$dbItem->id}");
	}
}

function importPost($obDbItem)
{
	if (!get_option('ps_use_posts'))
		return;
	global $wpdb;
	$categoryItem = $wpdb->get_row("SELECT * FROM ps_categories WHERE id = {$obDbItem->category_id}");
	if ($obDbItem->post_id)
	{
		wp_update_post(array(
				'ID'			=> $obDbItem->post_id,
				'post_title'	=> $obDbItem->title,
				'post_content'	=> $obDbItem->description,
				'post_type'		=> 'ps_catalog',
				'post_status'	=> 'publish',
			));
		foreach(array('url', 'price', 'currency', 'image', 'bestseller') as $var)
		{
			update_post_meta($obDbItem->post_id, $var, $obDbItem->{$var}, get_post_meta($obDbItem->post_id, $var, TRUE));
		}
		wp_set_object_terms($obDbItem->post_id, intval($categoryItem->taxonomy_id), 'ps_category');
	}
	else
	{
		$postId = wp_insert_post(array(
				'post_title'	=> $obDbItem->title,
				'post_content'	=> $obDbItem->description,
				'post_type'		=> 'ps_catalog',
				'post_status'	=> 'publish',
			));
		foreach(array('url', 'price', 'currency', 'image', 'bestseller') as $var)
		{
			add_post_meta($postId, $var, $obDbItem->{$var}, TRUE);
		}
		wp_set_object_terms($postId, intval($categoryItem->taxonomy_id), 'ps_category');
		$wpdb->query("UPDATE ps_products SET post_id = '{$postId}' WHERE id = {$obDbItem->id}");
	}

}
add_filter('the_content', 'showPost', 999999);
//@todo посоветоваться с Сергеем на счет блока хлебных крошек.
//add_filter('the_post', 'showBreadCrumbs', 999999);
function showBreadCrumbs($content)
{
	global $post;
	if (!get_option('ps_use_posts') || $post->post_type != 'ps_catalog' || !is_single())
		return;
	global $wpdb;
	echo '<p>';
	$br = array('<a href="'.get_post_type_archive_link('ps_catalog').'">Каталог</a>');
}
function showPost($content)
{
	global $wpdb;
	global $post;
	if (!get_option('ps_use_posts') || $post->post_type != 'ps_catalog')
		return;
	?>
		<table>
			<tr>
				<td style="vertical-align: top;">
					<img src="<?php echo get_post_meta($post->ID, 'image', TRUE)?>" style="width: 250px;" />
					<p class="products-price"><?php echo get_post_meta($post->ID, 'price', TRUE); ?> <?php echo (get_post_meta($post->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($post->ID, 'currency', TRUE)); ?></p>
					<p><a href="<?php echo bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/go.php?url='.get_post_meta($post->ID, 'url', TRUE); ?>" target="_blank">
						<img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/buy.png" alt="Купить <?php echo $post->post_title; ?>" />
					</a></p>
				</td>
				<td>&nbsp;</td>
				<td style="vertical-align: top;">
					<h3 style="margin-top: 0;"><?php echo $post->post_title; ?></h3>
					<div class="products-description"><p><?php echo html_entity_decode(nl2br($content)); ?></p></div>
				</td>
			</tr>
		</table>
	<?php if (is_single()):?>
	<h3>Похожие товары</h3>
	<table class="products-list">
		<tr>
			<?php
				$product = getItemByPost($post);
				$catIDs = getCategoriesChildren($product->category_id);
				$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND category_id IN (".implode(',', $catIDs).") AND id <> {$product->id} ORDER BY RAND() LIMIT ".get_option('ps_row_limit'));
			?>
			<?php foreach ($products as $item) { $relatedItem = getPostByItem($item);?>
				<td style="text-align: left;">
					<div class="products-image"><a href="<?php echo get_permalink($relatedItem->ID)?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php echo get_post_meta($relatedItem->ID, 'image', TRUE)?>" style="width: 100px; height: 100px;" /></a></div>
					<p class="products-name"><?php echo $relatedItem->post_title; ?></p>
					<p class="products-price"><?php echo get_post_meta($relatedItem->ID, 'price', TRUE); ?> <?php echo (get_post_meta($relatedItem->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($relatedItem->ID, 'currency', TRUE)); ?></p>
					<p class="products-details"><a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/details.png" alt="Подробнее" /></a></p>
				</td>
			<?php } ?>
		</tr>
	</table>

	<?php
		$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND bestseller = 1 ORDER BY RAND() LIMIT ".get_option('ps_row_limit'));
	?>
    <?php if (!empty($products)) { ?>
	<h3>Бестселлеры</h3>
	<table class="products-list">
		<tr>
			<?php foreach ($products as $item) { $relatedItem = getPostByItem($item)?>
				<td style="text-align: left;">
					<div class="products-image"><a href="<?php echo get_permalink($relatedItem->ID)?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php echo get_post_meta($relatedItem->ID, 'image', TRUE)?>" style="width: 100px; height: 100px;" /></a></div>
					<p class="products-name"><?php echo $relatedItem->post_title; ?></p>
					<p class="products-price"><?php echo get_post_meta($relatedItem->ID, 'price', TRUE); ?> <?php echo (get_post_meta($relatedItem->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($relatedItem->ID, 'currency', TRUE)); ?></p>
					<p class="products-details"><a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/details.png" alt="Подробнее" /></a></p>
				</td>
			<?php } ?>
		</tr>
	</table>
    <?php } ?>
	<?php endif; ?>
	<?php
}

function getPostByItem($obItem)
{
	return get_post($obItem->post_id);
}
function getItemByPost($obPost)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM ps_products WHERE post_id = '{$obPost->ID}'");
}