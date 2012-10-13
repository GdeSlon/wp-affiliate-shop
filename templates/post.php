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
				<?php if (get_post_meta($post->ID, 'vendor', TRUE) || get_post_meta($post->ID, 'params_list', TRUE)):?>
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
				<?php endif?>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<a href="<?php echo GS_PLUGIN_URL?>go.php?url=<?php echo get_post_meta($post->ID, 'url', TRUE); ?>" target="_blank" >
				<img src="<?php echo GS_PLUGIN_URL?>img/buy.png" alt="Купить <?php echo $post->post_title; ?>" height="25px"/>
			</a>
		</td>
		<td>
		</td>
		<td>

			<?php if (!is_single()):?>
			<a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>" style="display: block;">
				<img src="<?php echo GS_PLUGIN_URL?>img/details.png" alt="Подробнее" />
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
			'numberposts'	=> GS_Config::init()->get('ps_row_limit'),
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
			<p class="products-details"><a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php echo GS_PLUGIN_URL?>img/details.png" alt="Подробнее" /></a></p>
		</td>
		<?php endforeach?>
	</tr>
</table>

<?php
	$products = $wpdb->get_results("SELECT * FROM ps_products WHERE status = 1 AND bestseller = 1 ORDER BY RAND() LIMIT ".GS_Config::init()->get('ps_row_limit'));
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
				<p class="products-details"><a href="<?php echo get_permalink($relatedItem->ID) ?>" title="<?php echo $relatedItem->post_title; ?>"><img src="<?php echo GS_PLUGIN_URL?>img/details.png" alt="Подробнее" /></a></p>
			</td>
			<?php } ?>
		</tr>
	</table>
	<?php } ?>
<?php endif; ?>