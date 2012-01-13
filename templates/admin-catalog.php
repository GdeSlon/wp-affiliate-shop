<?php if ($isUpdated):?>
	<div class="updated"><p><strong>Данные сохранены</strong></p></div>
<?php endif?>
<div class="wrap">
	<h2>GdeSlon Affiliate Shop - Каталог</h2>
	<form name="form1" method="get" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="page" value="psCatalogPage">
		<input type="hidden" name="action" value="search">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="ps_search">Найти товар</label></th>
				<td><input name="ps_search" id="ps_search" type="text" value="<?php echo $ps_search; ?>" class="regular-text" style="width: 550px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_cat">Раздел</label></th>
				<td>
					<?php $tmp = array(); ?>
					<?php $cats = getCategoriesTreeList(null, 0, $tmp); ?>
					<select name="ps_cat"  id="ps_cat" style="width: 550px;">
						<option value=""></option>
						<?php foreach ($cats as $id => $val) { ?>
						<option value="<?php echo $id; ?>" <?php if ($ps_cat == $id) echo 'selected="selected"'; ?>><?php echo $val; ?></option>
						<?php } ?>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="Найти" />
		</p>
	</form>
	<hr />
	<?php if (!empty($ps_search) || !empty($ps_cat)):?>
	<form name="form2" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="action" value="update">
		<input type="hidden" name="product_id" id="ProductId" value="">
		<table style="width: 100%;">
			<tr>
				<th>Бестсел.</th>
				<th>ID</th>
				<th>Название</th>
				<th>Цена</th>
				<th>Статус</th>
				<th></th>
			</tr>
			<?php foreach ($products as $item) { ?>
			<tr>
				<td style="text-align: center;"><input type="checkbox" name="bs[]" value="<?php echo $item->id; ?>" <?php if (!empty($item->bestseller)) echo 'checked="checked"'; ?> /></td>
				<td style="padding: 2px;"><?php echo $item->id; ?></td>
				<td><a href="<?php echo get_permalink(get_option('ps_page')); ?>&pid=<?php echo $item->id; ?>" target="_blank"><?php echo $item->title; ?></a></td>
				<td style="text-align: right;"><?php echo $item->price; ?>&nbsp;<?php echo $item->currency; ?></td>
				<td style="text-align: center;"><?php echo $statuses[$item->status]; ?></td>
				<td><a href="#" onclick="
						jQuery('.row-edit').hide();
						jQuery('.row-edit INPUT, .row-edit TEXTAREA').attr('name', '');
						jQuery('#Edit<?php echo $item->id; ?>').show();
						jQuery('#Edit<?php echo $item->id; ?> INPUT.regular-text').attr('name', 'ps_title');
						jQuery('#Edit<?php echo $item->id; ?> TEXTAREA').attr('name', 'ps_description');
						jQuery('#ProductId').val(<?php echo $item->id; ?>);
						return false;">ред.</a>
					&nbsp;
					<?php if ($item->status != 2) { ?>
						<?php
	  			    					$link = array();
						foreach ($_GET as $key => $val) {
							if ($key != 'del') $link[] = $key.'='.$val;
						}
						$link = '?'.implode('&', $link);
						?>
						<a href="<?php echo $link; ?>&del=<?php echo $item->id; ?>" onclick="return confirm('Товар будет помечен как удаленный и не будет выводиться в каталоге.');">удал.</a>
						<?php } ?>
				</td>
			</tr>
			<tr id="Edit<?php echo $item->id; ?>" style="display: none;" class="row-edit">
				<td colspan="5">
					<input type="text" class="regular-text" name="" value="<?php echo $item->title; ?>" style="width: 600px;" />
					<textarea name="" style="width: 600px; height: 150px;"><?php echo $item->description; ?></textarea>
				</td>
				<td style="vertical-align: bottom;">
					<input type="submit" class="button-primary" value="Сохранить" />
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php if (!empty($products)) { ?>
		<p class="submit">
			<input type="submit" class="button-primary" value="Сохранить" />
		</p>
		<?php } ?>
	</form>
	<?php endif?>
</div>