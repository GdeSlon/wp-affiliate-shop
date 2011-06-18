<?php

function showCategoryLevel($tops, $parentId = null) {
	global $wpdb;
	if (empty($parentId))
		$cats = $wpdb->get_results("SELECT * FROM ps_categories WHERE parent_id IS NULL ORDER BY title ASC");
	else
		$cats = $wpdb->get_results("SELECT * FROM ps_categories WHERE parent_id = {$parentId} ORDER BY title ASC");

	echo '<ul>';
	foreach ($cats as $item) {
		if (in_array($item->id, $tops)) {
			echo '<li>'.$item->title.'</li>';
			showCategoryLevel($tops, $item->id);
		} else {
			echo '<li><a href="'.get_permalink(get_option('ps_page')).'?cat='.$item->id.'">'.$item->title.'</a></li>';
		}
	}
	echo '</ul>';
}

function psCategories() {
	global $wpdb;
    
	$tops = array();
	if (!empty($_GET['pid'])) {
		$catId = $wpdb->get_var("SELECT category_id FROM ps_products WHERE id = {$_GET['pid']}");
		$_GET['cat'] = $catId;
	}
	if (!empty($_GET['cat'])) {
		$catId = (int)$_GET['cat'];
		$tops = array($catId);
		$parentId = $wpdb->get_var("SELECT parent_id FROM ps_categories WHERE id = {$catId}");
		while (!empty($parentId)) {
			$tops[] = $parentId;
			$parentId = $wpdb->get_var("SELECT parent_id FROM ps_categories WHERE id = {$parentId}");
		}
	}
	
	$top = $wpdb->get_results("SELECT * FROM ps_categories WHERE parent_id IS NULL ORDER BY title ASC");
	echo '<ul>';
	echo '<li><h2>Разделы каталога</h2>';
	showCategoryLevel($tops);
	echo '</li></ul>';
}

function psMainPage() {
	global $wpdb;
	$page = 1;
	$limit = get_option('ps_limit');
	if (!empty($_GET['page'])) $page = (int)$_GET['page'];
	if ($page < 1) $page = 1;
	$offset = ($page - 1) * $limit;
	?>
		<?php
			$search = '';
			if (!empty($_GET['ps_search'])) {
				$s = mysql_real_escape_string($_GET['ps_search']);
				$search = " AND UPPER(title) LIKE UPPER('%$s%') ";
			}
			if (!empty($_GET['cat'])) {
				$catId = (int)$_GET['cat'];
				$catIDs = getCategoriesChildren($catId);
				$count = $wpdb->get_var("SELECT COUNT(*) FROM ps_products WHERE status = 1 AND category_id IN (".implode(',', $catIDs).") $search");
				$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND category_id IN (".implode(',', $catIDs).") $search ORDER BY title LIMIT $offset,$limit");
			} else {
				if (!empty($_GET['ps_search'])) {
					$count = $wpdb->get_var("SELECT COUNT(*) FROM ps_products WHERE status = 1 $search");
					$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 $search ORDER BY title LIMIT $offset,$limit");
				} else {
					$count = $wpdb->get_var("SELECT COUNT(*) FROM ps_products WHERE status = 1 AND bestseller = 1 $search");
					$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND bestseller = 1 $search ORDER BY title LIMIT $offset,$limit");
				}
			}
		?>
		<?php if (empty($products)) { ?>
			<p>Товаров не найдено</p>
		<?php } ?>
		<table class="products-list">
			<tr>
				<?php $cnt = 0; ?>
				<?php foreach ($products as $item) { ?>
					<td style="text-align: left;">
						<div class="products-image"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php echo $item->image; ?>" style="width: 100px; height: 100px;" /></a></div>
						<p class="products-name"><?php echo $item->title; ?></p>
						<p class="products-price"><?php echo $item->price; ?> <?php echo ($item->currency == 'RUR' ? 'руб.' : $item->currency); ?></p>
						<p class="products-details"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/img/details.png" alt="Подробнее" /></a></p>
					</td>
					<?php
						$cnt++;
						if ($cnt >= get_option('ps_row_limit')) {
							$cnt = 0;
							echo '</tr><tr>';
						}
					?>
				<?php } ?>
			</tr>
		</table>
		<p>
			<?php
				$modulus = 3;
				$pages = ceil($count / $limit);
				if ($pages > 1) {
					$pageMin = $page - $modulus;
					if ($pageMin < 1) $pageMin = 1;
					$pageMax = $page + $modulus;
					if ($pageMax > $pages) $pageMax = $pages;
					
					if ($pageMin > 1) echo '<a href="'.makeLink(1).'">1</a>&nbsp;...';
					for ($i = $pageMin; $i < $page; $i++) echo '&nbsp;<a href="'.makeLink($i).'">'.$i.'</a>&nbsp;';
					echo '&nbsp;<b>'.$page.'</b>&nbsp;';
					for ($i = $page + 1; $i <= $pageMax; $i++) echo '&nbsp;<a href="'.makeLink($i).'">'.$i.'</a>&nbsp;';
					if ($pageMax < $pages) echo '...&nbsp;<a href="'.makeLink($pages).'">'.$pages.'</a>';
				}
			?>
		</p>
	<?php
}

function psProductPage() {
	global $wpdb;
	$id = (int)$_GET['pid'];
	$product = $wpdb->get_row("SELECT * FROM ps_products WHERE id = {$id}");
	if (!empty($product)) {
	?>
		<table>
			<tr>
				<td style="vertical-align: top;">
					<img src="<?php echo $product->image; ?>" style="width: 250px;" />
					<p class="products-price"><?php echo $product->price; ?> <?php echo ($product->currency == 'RUR' ? 'руб.' : $product->currency); ?></p>
					<p><a href="<?php echo $product->url; ?>" target="_blank">
						<img src="<?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/img/buy.png" alt="Купить <?php echo $product->title; ?>" />
					</a></p>
				</td>
				<td>&nbsp;</td>
				<td style="vertical-align: top;">
					<h3 style="margin-top: 0;"><?php echo $product->title; ?></h3>
					<div class="products-description"><p><?php echo nl2br(html_entity_decode($product->description)); ?></p></div>
				</td>
			</tr>
		</table>
	<?php
	}
	?>
	
	<h3>Похожие товары</h3>
	<table class="products-list">
		<tr>
			<?php
				$catIDs = getCategoriesChildren($product->category_id);
				$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND category_id IN (".implode(',', $catIDs).") AND id <> {$product->id} ORDER BY RAND() LIMIT ".get_option('ps_row_limit'));
			?>
			<?php foreach ($products as $item) { ?>
				<td style="text-align: left;">
					<div class="products-image"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php echo $item->image; ?>" style="width: 100px; height: 100px;" /></a></div>
					<p class="products-name"><?php echo $item->title; ?></p>
					<p class="products-price"><?php echo $item->price; ?> <?php echo ($item->currency == 'RUR' ? 'руб.' : $item->currency); ?></p>
					<p class="products-details"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/img/details.png" alt="Подробнее" /></a></p>
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
			<?php foreach ($products as $item) { ?>
				<td style="text-align: left;">
					<div class="products-image"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php echo $item->image; ?>" style="width: 100px; height: 100px;" /></a></div>
					<p class="products-name"><?php echo $item->title; ?></p>
					<p class="products-price"><?php echo $item->price; ?> <?php echo ($item->currency == 'RUR' ? 'руб.' : $item->currency); ?></p>
					<p class="products-details"><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" title="<?php echo $item->title; ?>"><img src="<?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/img/details.png" alt="Подробнее" /></a></p>
				</td>
			<?php } ?>
		</tr>
	</table>
    <?php } ?>
	<?php
}

function psCatalog($content) {
	global $wpdb, $post;
    if ($post->ID == get_option('ps_page')) {
    	
    	if (!empty($_GET['ps_search'])) {
    		if (empty($_GET['ps_search_cat'])) {
    			$_GET['pid'] = '';
    			$_GET['cat'] = '';
    		} else {
    			if (!empty($_GET['pid'])) {
    				$_GET['cat'] = $wpdb->get_var("SELECT category_id FROM ps_products WHERE id = {$_GET['pid']}");
    				$_GET['pid'] = '';
    			}
    		}
    	}
    	
    	echo '<p>';
    	$br = array('<a href="'.get_permalink(get_option('ps_page')).'">Каталог</a>');
    	$catId = null;
    	if (!empty($_GET['cat'])) $catId = $_GET['cat'];
    	if (!empty($_GET['pid'])) $catId = $wpdb->get_var("SELECT category_id FROM ps_products WHERE id = {$_GET['pid']}");
    	if (!empty($catId)) {
			$tops = array($catId);
			$parentId = $wpdb->get_var("SELECT parent_id FROM ps_categories WHERE id = {$catId}");
			while (!empty($parentId)) {
				$tops[] = $parentId;
				$parentId = $wpdb->get_var("SELECT parent_id FROM ps_categories WHERE id = {$parentId}");
			}
			$tops = array_reverse($tops);
			foreach ($tops as $catId) {
				$catName =  $wpdb->get_var("SELECT title FROM ps_categories WHERE id = {$catId}");
				$br[] = '<a href="'.get_permalink(get_option('ps_page')).'?cat='.$catId.'">'.$catName.'</a>';
			}
			if (!empty($_GET['pid'])) {
				$prodName =  $wpdb->get_var("SELECT title FROM ps_products WHERE id = {$_GET['pid']}");
				$br[] = '<a href="'.get_permalink(get_option('ps_page')).'?pid='.$_GET['pid'].'">'.$prodName.'</a>';
			}
    	}
    	echo implode(' &gt; ', $br);
    	echo '</p>';
    	
    	echo '<form method="get" action="">';
    	echo '<p style="text-align: left;">';
    		foreach ($_GET as $key => $val) {
    			if ($key != 'ps_search' && $key != 'ps_search_cat') {
    				echo '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
    			}
    		}
    		echo '<input type="text" name="ps_search" value="'.@$_GET['ps_search'].'" size="40" />';
    		echo '<input type="submit" value="Искать" />';
    		if (!empty($_GET['cat']) || !empty($_GET['pid'])) {
    			echo '<div style="text-align: left;"><input type="checkbox" name="ps_search_cat" id="ps_search_cat" value="1" '.(!empty($_GET['ps_search_cat']) ? 'checked="checked"' : '').' /> <label for="ps_search_cat">Искать в данной категории</label></div>';
    		}
    	echo '</p>';
    	echo '</form>';
    	
    	if (empty($_GET['pid'])) psMainPage();
    	if (!empty($_GET['pid'])) psProductPage();
    	
        return $content;
    } else return $content;
}

function psTitle($title) {
	global $wpdb;
	if (is_page(get_option('ps_page'))) {
		$name = '';
		if (!empty($_GET['pid'])) {
			$name = $wpdb->get_var("SELECT title FROM ps_products WHERE id = {$_GET['pid']}");
		}
		if (!empty($_GET['cat'])) {
			$name = $wpdb->get_var("SELECT title FROM ps_categories WHERE id = {$_GET['cat']}");
		}
		if (!empty($name)) $title = $name.' &laquo; '.$title;
		return $title;
	} else return $title;
}

function psStyles() {
	echo '<style type="text/css">';
	echo '.products-list { width: 100%; } ';
	echo '.products-list TD { border: 1px solid #aaa; padding: 4px; vertical-align: top; width: '.round(100 / get_option('ps_row_limit')).'%; } ';
	echo '.products-price { font-weight: bold; } ';
	echo '.products-description { font-size: 0.9em; text-align: left; color: #777; } ';
	echo '</style>';
}
?>