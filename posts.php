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
			'excerpt',
			'custom-fields'
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
	flush_rewrite_rules(false);

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
 * Помечаем товар, как отредактированный вручную
 */
add_action('edit_post', 'markAsEdited');
function markAsEdited($post)
{
	if (defined('PARSING_IS_RUNNING'))
		return;
	$post = is_object($post) ? $post : get_post($post);
	update_post_meta($post->ID,'edited_by_user', 1, get_post_meta($post->ID, 'edited_by_user', TRUE));
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
			$parentId = $parentDbItem->term_id;
	}
	if (($dbItem = get_category_by_outer_id($category['id']))/* || ($dbItem = $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE name = '{$category['title']}'"))*/)
	{
		$termId = $dbItem->term_id;
		wp_update_term($dbItem->term_id, 'ps_category', array(
			'name'			=> $category['title'],
			'parent'		=> $parentId,
			'slug'			=> transliteration($category['title'])
		));
	}
	else
	{
		$result = wp_insert_term($category['title'], 'ps_category', array(
			'parent'	=> $parentId,
			'slug'		=> transliteration($category['title'])
		));
		if (is_array($result))
			$termId = $result['term_id'];
		elseif (is_object($result) && get_class($result) == 'WP_Error')
		{
			if (!empty($result->error_data['term_exists']))
				$termId = $result->error_data['term_exists'];
		}
	}
	if ($termId)
		$wpdb->query("UPDATE {$wpdb->terms} SET term_group = {$category['id']} WHERE term_id = $termId");
}

/**
 * Сброс кэша после вставки новых категорий
 * @return void
 */
function flushCache($categories)
{
	delete_option("ps_category_children");
}

function addParamsToPost($postId, $params)
{

}

function transliteration($str)
{
	$r_trans = Array(
		"А","Б","В","Г","Д","Е","Ё","Ж","З","И","Й","К","Л","М",
		"Н","О","П","Р","С","Т","У","Ф","Х","Ц","Ч","Ш","Щ","Э",
		"Ю","Я","Ъ","Ы","Ь",
		"а","б","в","г","д","е","ё","ж","з","и","й","к","л","м",
		"н","о","п","р","с","т","у","ф","х","ц","ч","ш","щ","э",
		"ю","я","ъ","ы","ь"," ",",","-","(",")",".","?","!",":","\"","'","=","\\","/");

	$e_trans = Array(
		"a","b","v","g","d","e","e","j","z","i","i","k","l","m",
		"n","o","p","r","s","t","u","f","h","cz","ch","sh","sch",
		"e","yu","ya","","i","",
		"a","b","v","g","d","e","e","j","z","i","i","k","l","m",
		"n","o","p","r","s","t","u","f","h","c","ch","sh","sch",
		"e","yu","ya","","i","","-","-","-","-","-","-","","","-","","","","","");

	$str = strtolower( str_replace($r_trans, $e_trans, $str) );
	$str = preg_replace('~([\-]+)~','-',$str);

	$str = preg_replace('~([^a-z0-9\-])~','',$str);
	return $str;
}


function importPost(array $item, $params = NULL)
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
		$params = array(
			'ID'				=> $obItem->ID,
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'ps_catalog',
			//'post_status'		=> 'publish',
			'post_mime_type'	=> $item['id'],
			'post_name'			=> transliteration($item['title'])
		);
		if (get_post_meta($obItem->ID, 'edited_by_user', TRUE))
		{
			unset($params['post_title']);
			unset($params['post_content']);
			unset($params['post_name']);
		}
		wp_update_post($params);
		foreach(array('url', 'price', 'currency', 'bestseller') as $var)
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
			'comment_status'	=> 'closed',
			'post_name'			=> transliteration($item['title'])
		));
		foreach(array('url', 'price', 'currency', 'bestseller') as $var)
		{
			add_post_meta($postId, $var, $item[$var], TRUE);
		}
		add_post_meta($postId, '_wp_page_template', 'sidebar-page.php', TRUE);
	}
	/**
	 * Подгрузка изображения
	 */
	if (!empty($item['image']))
	{
		download_image($item['image'], $postId);
	}
	wp_set_object_terms($postId, array(intval(get_category_by_outer_id($item['category_id'])->term_id)), 'ps_category');
	foreach($params as $name => $value)
	{
		update_post_meta($postId, $name, $value);
	}
}

/**
 * Подгрузка изображения
 * @param $url
 * @param $postId
 * @todo добавить обходной вариант на тот случай, если загрузка не удалась — просто запоминать урл на картинку
 * @todo Вынести наконец всё в красивый класс и закончить рефакторинг — запланировано на 7.06.2012
 */
function download_image($url, $postId)
{
	if (!GdeSlonImport::checkCurl())
	{
		$opts = array(
			'http'=>array(
				'method'=>"GET",
				'header'=>"Accept-language: en\r\n"
			)
		);
		$context = stream_context_create($opts);
		$fileContents = file_get_contents($url, false, $context);
	}
	else
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$fileContents = curl_exec($ch);
		curl_close($ch);
	}

	$localFilepath = dirname(__FILE__).'/downloads/'.basename($url);
	$f = fopen($localFilepath, 'w');
	fwrite($f, $fileContents);
	fclose($f);
	insert_attachment($localFilepath,$postId, true);
	@unlink($localFilepath);
}
function insert_attachment($image, $post_id, $setthumb = FALSE)
{
	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
	require_once(ABSPATH . "wp-admin" . '/includes/media.php');

	$array = array( //array to mimic $_FILES
		'name' => basename($image), //isolates and outputs the file name from its absolute path
		'type' => 'image/jpeg', //yes, thats sloppy, see my text further down on this topic
		'tmp_name' => $image, //this field passes the actual path to the image
		'error' => 0, //normally, this is used to store an error, should the upload fail. but since this isnt actually an instance of $_FILES we can default it to zero here
		'size' => filesize($image) //returns image filesize in bytes
	);
	$imageId = media_handle_sideload($array, $post_id);
	if ($setthumb)
		update_post_meta($post_id,'_thumbnail_id',$imageId);
	return $imageId;
}

function get_category_by_outer_id($outerId)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE term_group = {$outerId}");
}

//@todo В чистом виде работает некорректно из-за специфики темы. Надо думать, что можно сделать.
add_filter('single_template', 'filter_single_template');
add_filter('body_class', 'filter_single_body_class',9999);
function filter_single_body_class($class)
{
	$classKeys = array_flip($class);
	unset($classKeys['singular']);
	return array_flip($classKeys);
}
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
			if (is_array($cat))
			{
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
		return $content;
	?>
<table class="product-table">
	<tr>
		<td style="vertical-align: top;" class="product-image">
			<?php if (!is_single()):?>
			<a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>" style="display: block;">
			<?php endif?>
			<?php get_image_from_catalog_item($relatedItem)?>
			<?php if (!is_single()):?>
			</a>
			<?php endif?>
			<p class="products-price"><?php echo get_post_meta($post->ID, 'price', TRUE); ?> <?php echo (get_post_meta($post->ID, 'currency', TRUE) == 'RUR' ? 'руб.' : get_post_meta($post->ID, 'currency', TRUE)); ?></p>
		</td>
		<td>&nbsp;</td>
		<td style="vertical-align: top;">
			<div class="products-description">
				<p><?php echo html_entity_decode(nl2br($content)); ?></p>
				<table>
					<?php if (get_post_meta($post->ID, 'vendor', TRUE)):?>
					<tr>
						<th>Производитель</th>
						<td><?php echo get_post_meta($post->ID, 'vendor', TRUE)?></td>
					</tr>
					<?php endif?>
					<?php if (get_post_meta($post->ID, 'params_list', TRUE)):?>
					<?php foreach(explode(',',get_post_meta($post->ID, 'params_list', TRUE)) as $paramKey):?>
						<tr>
							<th><?php echo $paramKey?></th>
							<td><?php echo get_post_meta($post->ID, $paramKey, TRUE)?></td>
						</tr>
						<?php endforeach?>
					<?php endif?>
				</table>
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
			<div class="products-image"><a href="<?php echo get_permalink($relatedItem->ID)?>" title="<?php echo $relatedItem->post_title; ?>"><?php get_image_from_catalog_item($relatedItem->ID,100)?></a></div>
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
				<div class="products-image"><a href="<?php echo get_permalink($relatedItem->ID)?>" title="<?php echo $relatedItem->post_title; ?>"><?php echo get_image_from_catalog_item($relatedItem, 100)?></a></div>
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

/**
 * Вывод изображения
 * @param $post
 * @param int $width
 */
function get_image_from_catalog_item($post, $width = 250)
{
	$post = is_object($post) ? $post : get_post($post);
	$url = has_post_thumbnail($post->ID)
			? wp_get_attachment_url(get_post_thumbnail_id($post->ID)) : get_post_meta($post->ID, 'image', TRUE);
	echo '<img src="'.$url.'" title="Купить '.$post->post_title.'" alt="Купить '.$post->post_title.'" style="width: '.$width .'px;" />';

}