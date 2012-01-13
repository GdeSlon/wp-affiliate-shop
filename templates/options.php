<?php if ($isUpdated):?>
<div class="updated"><p><strong>Настройки сохранены</strong></p></div>
<?php endif?>
<?php if ($isDeleted):?>
<div class="updated"><p><strong>Данные удалены</strong></p></div>
<?php endif?>
<?php if ($isError):?>
<div class="updated"><p><strong>Ошибка</strong></p></div>
<?php endif?>
<div class="wrap">
	<h2>GdeSlon Affiliate Shop - Настройки</h2>
	<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="action" value="update">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="ps_url">Ссылка на выгрузку</label></th>
				<td><input name="ps_url" id="ps_url" type="text" value="<?php echo $url; ?>" class="regular-text" style="width: 550px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_page">Страница каталога</label></th>
				<td>
					<?php $pages = get_pages(); ?>
					<select name="ps_page"  id="ps_page" style="width: 550px;">
						<?php foreach ($pages as $item) { ?>
						<option value="<?php echo $item->ID; ?>" <?php if ($ps_page == $item->ID) echo 'selected="selected"'; ?>><?php echo $item->post_title; ?></option>
						<?php } ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_limit">Кол-во товаров на странице</label></th>
				<td><input name="ps_limit" id="ps_limit" type="text" value="<?php echo get_option('ps_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_row_limit">Кол-во товаров в строке</label></th>
				<td><input name="ps_row_limit" id="ps_row_limit" type="text" value="<?php echo get_option('ps_row_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_get_enable">Обновлять по GET-запросу</label></th>
				<td><input name="ps_get_enable" id="ps_get_enable" type="checkbox" <?php if($get_enable) echo "checked='yes'"; ?> value="enable" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_use_posts">Использовать посты</label></th>
				<td><input name="ps_use_posts" id="ps_use_posts" type="checkbox" <?php if($use_posts) echo "checked='yes'"; ?> value="enable" /></td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
	<h3>Импорт</h3>
	<div style="border: 1px solid #aaa; padding: 7px;">
		Необходимо в крон добавить один из вариантов запуска модуля импорта:<br /><br />
		<b>php <?php echo ABSPATH; ?>wp-content/plugins/<?php echo $dirname; ?>/cron.php</b><br /><br />
		<b>GET <?php bloginfo('url'); ?>/wp-content/plugins/<?php echo $dirname; ?>/cron.php?code=<?php echo get_option('ps_access_code'); ?></b><br />
		<br />
		<form method="get" action="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo $dirname; ?>/cron.php" target="_blank">
			<input type="hidden" name="code" value="<?php echo get_option('ps_access_code'); ?>" />
			<input type="submit" class="button-primary" value="Запустить импорт" />
		</form>
	</div>
	<?php if (calcCategories()+calcProducts() > 0):?>
	<h3>Удаление данных</h3>
	<p style="color:red">Внимание! Посты удалены не будут!</p>
	<div style="border: 1px solid #aaa; padding: 7px;">
		<p>В базе сейчас:</p>
		<p><b><?php echo calcCategories()?></b> категорий</p>
		<p><b><?php echo calcProducts()?></b> товаров</p>
		<form method="post" action="">
			<input type="hidden" name="action" value="delete"/>
			<p style="color:red"><input type="checkbox" name="agree" value="1"/> Подтверждаю, что хочу удалить выбранные записи из базы данных навсегда без возможности восстановления</p>
			<select name="type" id="">
				<option value="all">Всё</option>
				<option value="categories">Категории</option>
				<option value="products">Товары</option>
			</select>
			<input type="submit" class="button-primary" value="Удалить" />
		</form>
	</div>
	<?php endif?>
</div>