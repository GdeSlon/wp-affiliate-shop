<?php
add_action('init', 'registerGdeSlonPostType');
function registerGdeSlonPostType()
{
	$postTypeConfig = array(
		'public' => true,
		'exclude_from_search' => true,
		'show_in_menu'	=> false,
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
			'update_count_callback' => '_update_post_term_count',
			'args' => array( 'orderby' => 'term_order' ),
			'rewrite' => array( 'slug' => 'type' )
		)
	);

	function filter_where($where = '')
	{
		if ((!is_tax('ps_category') && !is_search()) || is_single())
		{
			return $where;
		}
		return str_replace("post_type IN ('post',", "post_type IN ('post','ps_catalog',", $where);
	}
	add_filter('posts_where', 'filter_where', 9999);
}

/**
 * Создание или обновление таксономии.
 * При этом происходит связывание термов и таксономии
 * @param $item
 * @return
 */
function importTerm(array $category)
{
	global $wpdb;
	$parentId = 0;
	if ($category['parent_id'])
	{
		$parentDbItem = get_category_by_outer_id($category['parent_id']);
		if ($parentDbItem)
			$parentId = intval($parentDbItem->term_id);
	}
	if (($dbItem = get_category_by_outer_id($category['id'])) || ($dbItem = $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE name = '{$category['title']}'")))
	{
		$termId = $dbItem->term_id;
		//@todo: Нужно еще обрабатывать ситуацию, когда родительская категория еще не создана. Пока отложу, вероятность небольшая
		wp_update_term($dbItem->term_id, 'ps_category', array(
				'name'			=> $category['title'],
				'parent'		=> $parentId,
			));
	}
	else
	{
		list($termId) = wp_insert_term($category['title'], 'ps_category', array(
				'parent'		=> $parentId,
			));
	}
	$wpdb->query("UPDATE {$wpdb->terms} SET term_group = {$category['id']} WHERE term_id = $termId");
}

function importPost(array $item)
{
	global $wpdb;
	$obItem = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_mime_type = {$item['id']}");
	$postId = null;
	if ($obItem->ID)
	{
		if ($obItem->post_status == 'trash')
			return;
		if (!$obItem->post_modified || $obItem->post_date != $obItem->post_modified)
		{
			$item['title'] = $obItem->post_title;
			$item['description'] = $obItem->post_content;
		}
		wp_update_post(array(
				'ID'				=> $obItem->ID,
				'post_title'		=> $item['title'],
				'post_content'		=> $item['description'],
				'post_type'			=> 'ps_catalog',
				'post_status'		=> 'publish',
				'post_mime_type'	=> $item['id']
			));
		foreach(array('url', 'price', 'currency', 'image', 'bestseller') as $var)
		{
			update_post_meta($obItem->ID, $var, $item[$var], get_post_meta($obItem->ID, $var, TRUE));
		}
		$postId = $obItem->ID;
	}
	else
	{
		$postId = wp_insert_post(array(
				'post_title'		=> $item['title'],
				'post_content'		=> $item['description'],
				'post_type'			=> 'ps_catalog',
				'post_status'		=> 'publish',
				'post_mime_type'	=> $item['id'],
				'comment_status'	=> 'closed'
			));
		foreach(array('url', 'price', 'currency', 'image', 'bestseller') as $var)
		{
			add_post_meta($postId, $var, $item[$var], TRUE);
		}
		add_post_meta($postId, '_wp_page_template', 'sidebar-page.php', TRUE);
	}
	wp_set_object_terms($postId, array(intval(get_category_by_outer_id($item['category_id'])->term_id)), 'ps_category');
}

function get_category_by_outer_id($outerId)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE term_group = {$outerId}");
}

//@todo В чистом виде работает некорректно из-за специфики темы. Надо думать, что можно сделать.
//add_filter('single_template', 'filter_single_template');
function filter_single_template( $template )
{
	global $wp_query;

	if (get_post_type($wp_query->post) != 'ps_catalog')
		return $template;

	// No template? Nothing we can do.
	$template_file = get_post_meta($wp_query->post->ID, '_wp_page_template', true);
	if ( ! $template_file )
		return $template;

	// If there's a tpl in a (child theme or theme with no child)
	if ( file_exists( get_stylesheet_directory() .'/'. $template_file ) )
		return get_stylesheet_directory() .'/'. $template_file;
	// If there's a tpl in the parent of the current child theme
	/*	else if ( file_exists( TEMPLATEPATH . DIRECTORY_SEPARATOR . $template_file ) )
			return TEMPLATEPATH . DIRECTORY_SEPARATOR . $template_file;*/
	return $template;
}


add_filter('the_content', 'showPost', 999999);
add_filter('loop_start', 'showBreadCrumbs', 999999);
function showBreadCrumbs($content)
{
	global $post;
	if (($post->post_type != 'ps_catalog' || !is_single()) && !is_tax('ps_category') && !is_post_type_archive('ps_catalog'))
		return;

	$delimiter = '&raquo;'; // разделить между ссылками
	$home = 'Home'; // текст ссылка "Главная"
	$before = '<span class="current">';
	$after = '</span>';

	if ( !is_home() && !is_front_page() || is_paged() ) {

		echo '<div id="crumbs">';

		global $post;
		$homeLink = get_bloginfo('url');
		echo '<a href="' . $homeLink . '">' . $home . '</a> ' . $delimiter . ' ';
		if (!is_post_type_archive('ps_catalog'))
			echo '<a href="'.get_post_type_archive_link('ps_catalog') . '">' . get_post_type_object('ps_catalog')->labels->name . '</a> ';
		else
			echo $before.get_post_type_object('ps_catalog')->labels->name .$after;

		if (is_tax('ps_category'))
		{
			global $wp_query;
			$cat = $wp_query->get_queried_object();
			$taxonomies = ps_get_taxonomy_parents($cat->parent);
			foreach($taxonomies as $obTerm)
			{
				if (get_class($obTerm) !== 'WP_Error')
					echo ' '.$delimiter . ' '.' <a href="' .get_category_link($obTerm).'" title="' . esc_attr( sprintf( __( "Посмотреть все товары в категории %s" ), $obTerm->name ) ) . '">'.$obTerm->name.'</a>';
			}
			echo ' '.$delimiter . ' '.$before.' '.$cat->name.' '.$after;

		} elseif ( is_single() && !is_attachment() ) {
			$cat = get_the_terms($post->ID, 'ps_category');
			foreach($cat as $obCat)
			{
				$cat = $obCat;
				break;
			}
			$taxonomies = ps_get_taxonomy_parents($cat->parent);
			$taxonomies[] = $cat;
			foreach($taxonomies as $obTerm)
			{
				if (get_class($obTerm) !== 'WP_Error')
					echo ' '.$delimiter.' <a href="' .get_category_link($obTerm).'" title="' . esc_attr( sprintf( __( "Посмотреть все товары в категории %s" ), $obTerm->name ) ) . '">'.$obTerm->name.'</a>';
			}
			echo ' '.$delimiter.' ';
			echo $before . get_the_title() . $after;
		}
		echo '</div>';

	}
}
function showPost($content)
{
	global $wpdb;
	global $post;
	if ($post->post_type != 'ps_catalog')
		return;
	?>
<table class="product-table">
	<tr>
		<td style="vertical-align: top;" class="product-image">
			<?php if (!is_single()):?>
			<a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>" style="display: block;">
			<?php endif?>
				<img src="<?php echo get_post_meta($post->ID, 'image', TRUE)?>" title="Купить <?php echo $post->post_title; ?>" alt="Купить <?php echo $post->post_title; ?>" style="width: 250px;" />
			<?php if (!is_single()):?>
			</a>
			<?php endif?>
			<p class="products-price"><?php echo get_post_meta($post->ID, 'price', TRUE); ?> <?php echo (get_post_meta($post->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($post->ID, 'currency', TRUE)); ?></p>
		</td>
		<td>&nbsp;</td>
		<td style="vertical-align: top;">
			<div class="products-description">
				<p><?php echo html_entity_decode(nl2br($content)); ?></p>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<a href="<?php echo bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/go.php?url='.get_post_meta($post->ID, 'url', TRUE); ?>" target="_blank" >
				<img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/buy.png" alt="Купить <?php echo $post->post_title; ?>" height="25px"/>
			</a>
		</td>
		<td>
		</td>
		<td>
			
			<?php if (!is_single()):?>
			<a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>" style="display: block;">
				<img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/details.png" alt="Подробнее" />
			</a>
			<?php endif?>
		</td>
	</tr>
</table>
<?php if (is_single()):?>
<h3>Похожие товары</h3>
<table class="products-list">
	<tr>
		<?php
  		$termsList = wp_get_post_terms($post->ID, 'ps_category');
		$args = array(
			'numberposts'	=> get_option('ps_row_limit'),
			'orderby'		=> 'rand',
			'post_type'		=> 'ps_catalog',
			'post__not_in'		=> array($post->ID)
		);
		$terms = array();
		foreach($termsList as $obTerm)
		{
			$terms[$obTerm->term_id] = $obTerm->term_id;
		}
		if (count($terms))
		{
			$args['tax_query'] = array(
				array(
					'taxonomy'	=> 'ps_category',
					'field'		=> 'id',
					'terms'		=> $terms,
					'operator'	=> 'IN'
				)
			);
		}
		?>
		<?php foreach (get_posts($args) as $relatedItem):?>
		<td style="text-align: left;">
			<div class="products-image"><a href="<?php echo get_permalink($relatedItem->ID)?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php echo get_post_meta($relatedItem->ID, 'image', TRUE)?>" style="width: 100px; " /></a></div>
			<p class="products-name"><?php echo $relatedItem->post_title; ?></p>
			<p class="products-price"><?php echo get_post_meta($relatedItem->ID, 'price', TRUE); ?> <?php echo (get_post_meta($relatedItem->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($relatedItem->ID, 'currency', TRUE)); ?></p>
			<p class="products-details"><a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo basename(dirname(__FILE__)); ?>/img/details.png" alt="Подробнее" /></a></p>
		</td>
		<?php endforeach?>
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