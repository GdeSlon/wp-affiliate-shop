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
<!--			<tr valign="top">-->
<!--				<th scope="row"><label for="ps_limit">Кол-во товаров на странице</label></th>-->
<!--				<td><input name="ps_limit" id="ps_limit" type="text" value="--><?php //echo get_option('ps_limit'); ?><!--" class="regular-text" style="width: 100px;" /></td>-->
<!--			</tr>-->
			<tr valign="top">
				<th scope="row"><label for="ps_row_limit">Кол-во товаров в строке</label></th>
				<td><input name="ps_row_limit" id="ps_row_limit" type="text" value="<?php echo get_option('ps_row_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
			</tr>
<!--			<tr valign="top">-->
<!--				<th scope="row"><label for="widget_depth">Глубина вложенности виджета<br/><small>(0 — показывать всё, 1 - только родительские категории)</small></label></th>-->
<!--				<td><input name="widget_depth" id="widget_depth" type="text" value="--><?php //echo get_option('widget_depth'); ?><!--" class="regular-text" style="width: 100px;" /></td>-->
<!--			</tr>-->
			<tr valign="top">
				<th scope="row"><label for="ps_get_enable">Обновлять по GET-запросу</label></th>
				<td><input name="ps_get_enable" id="ps_get_enable" type="checkbox" <?php if($get_enable) echo "checked='yes'"; ?> value="enable" /></td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
	<h3>Импорт</h3>
	<?php if (GdeSlonImport::checkCurl() || GdeSlonImport::checkFileGetContentsCurl()):?>
	<div style="border: 1px solid #aaa; padding: 7px;">
		Необходимо в крон добавить один из вариантов запуска модуля импорта:<br /><br />
		<b>php <?php echo ABSPATH; ?>wp-content/plugins/<?php echo $dirname; ?>/cron.php</b><br /><br />
		<b>GET <?php bloginfo('url'); ?>/wp-content/plugins/<?php echo $dirname; ?>/cron.php?code=<?php echo get_option('ps_access_code'); ?></b><br />
		<br />
		Либо запустите импорт товаров вручную:<br />
		<p>Для выкачивания файла будет использован <strong><?php echo GdeSlonImport::checkCurl() ? 'cUrl' : 'file_get_contents'?></strong></p>
		<form method="get" action="<?php bloginfo('url'); ?>/wp-content/plugins/<?php echo $dirname; ?>/cron.php" target="_blank">
			<input type="hidden" name="code" value="<?php echo get_option('ps_access_code'); ?>" />
			<input type="submit" class="button-primary" value="Запустить импорт" />
		</form>
	</div>
	<?php else:?>
	<p style="color:red">
		Внимание! Невозможно импортировать файл.
		Что решить эту проблему вам необходимо предпринять одно из следующих действий:
		<ul>
		<li>— Либо установить на сервере расширение для php <a href="http://www.php.net/manual/ru/book.curl.php" target="_blank">cUrl</a></li>
		<li>— Либо Включить в php.ini <a href="http://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen" target="_blank">allow_url_fopen</a></li>
		</ul>
	</p>
	<?php endif?>

	<h3>Виджет</h3>
	<div style="border: 1px solid #aaa; padding: 7px;">
		Для использования виджета необходимо:<br />
		<ol>
			<li>Поддержка виджетов в вашей теме</li>
			<li>Поддержка боковой панели в вашей теме</li>
			<li>Добавление <a href="<?php bloginfo('url'); ?>/wp-admin/widgets.php">виджета</a> на боковую панель блога.
				<br>(перейдите по ссылки и перетяните GdeSlon Widget на вашу боковую панель)</li>
		</ol>
	</div>

	<?php if (calcCategories()+calcProducts() > 0):?>
	<h3>Удаление данных</h3>
	<div style="border: 1px solid #aaa; padding: 7px;">
		<p>В базе сейчас:</p>
		<p><b><?php echo calcCategories()?></b> категорий</p>
		<p><b><?php echo calcProducts()?></b> товаров</p>
		<form method="post" action="">
			<input type="hidden" name="action" value="delete"/>
			<p style="color:red"><input type="checkbox" name="agree" value="1" id="input-agree"/> <label for="input-agree">Подтверждаю, что хочу удалить выбранные записи из базы данных навсегда без возможности восстановления</label></p>
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