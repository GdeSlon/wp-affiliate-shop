<?php if(!is_plugin_active('woocommerce/woocommerce.php')): ?>
<div class="error"><p>Внимание - Для корректной работы этого плагина, необходимо установить Woocommerce плагин.</p></div>
<?php endif; ?>
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
				<th scope="row" style="width:300px"><label for="ps_url">Ссылка на выгрузку</label></th>
				<td><input name="ps_url" id="ps_url" type="text" value="<?php echo $url; ?>" class="regular-text" style="width: 550px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_download_images">Загружать изображения к себе в WordPress</label></th>
				<td><input name="ps_download_images" id="ps_download_images" type="checkbox" value="1" <?php if (GS_Config::init()->get('ps_download_images')) echo ' checked="checked" '; ?>/></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ps_row_limit">Кол-во похожих товаров</label></th>
				<td><input name="ps_row_limit" id="ps_row_limit" type="text" value="<?php echo GS_Config::init()->get('ps_row_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">
					Кол-во товаров на главной
				</th>
				<td><a href="<?php echo admin_url('options-reading.php') ?>">Перейдите для изменения</a></td>
			</tr>

<!--			<tr valign="top">-->
<!--				<th scope="row"><label for="widget_depth">Глубина вложенности виджета<br/><small>(0 — показывать всё, 1 - только родительские категории)</small></label></th>-->
<!--				<td><input name="widget_depth" id="widget_depth" type="text" value="--><?php //echo get_option('widget_depth'); ?><!--" class="regular-text" style="width: 100px;" /></td>-->
<!--			</tr>-->
			<tr valign="top">
				<th scope="row"><label for="ps_get_enable">Обновлять по GET-запросу</label></th>
				<td><input name="ps_get_enable" id="ps_get_enable" type="checkbox" <?php echo $get_enable ? "checked='yes'" : ''?> value="enable" /></td>
			</tr>

			<tr>
				<td scope="row" colspan="2">
					<h3>Опции импорта</h3>
					<?php if (!GdeSlonImport::is_upload_directory_writeable()):?>
					<p style="color:red;">
						Внимание! Нет доступа на запись к директории <?php echo GdeSlonImport::get_upload_path()?>.<br/>
						Вам необходимо дать права на запись в эту директорию. <br/>
						Если вы не знаете, что это значит — обратитесь за помощью к тех.поддержке вашего хостинга.
						</p>
					<?php endif?>
				</td>
			</tr>
			<tr valign="top" style="border-top: 1px solid #aaa;border-left: 1px solid #aaa;border-right: 1px solid #aaa;">
				<th scope="row"><label for="import_price">Загружать товары дороже чем</label></th>
				<td><input name="import_price" id="import_price" type="text" value="<?php echo GS_Config::init()->get('import_price'); ?>" class="regular-text" style="width: 300px;" /> руб.</td>
			</tr>
			<tr valign="top" style="border-left: 1px solid #aaa;border-right: 1px solid #aaa;">
				<th scope="row"><label for="import_title">Загружать только товары, содержащие подстроку в названии</label></th>
				<td><input name="import_title" id="import_title" type="text" value="<?php echo GS_Config::init()->get('import_title'); ?>" class="regular-text" style="width: 300px;" /></td>
			</tr>
			<tr valign="top" style="border-bottom: 1px solid #aaa;border-left: 1px solid #aaa;border-right: 1px solid #aaa;">
				<th scope="row"><label for="import_vendor">Загружать только товары указанного производителя</label></th>
				<td><input name="import_vendor" id="import_vendor" type="text" value="<?php echo GS_Config::init()->get('import_vendor'); ?>" class="regular-text" style="width: 300px;" /></fieldset></td>
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
		<b>GET <?php echo admin_url( 'admin-ajax.php' )?>?action=parse_url&code=<?php echo GS_Config::init()->get('ps_access_code'); ?></b><br />
		<br />
		Либо запустите импорт товаров вручную:<br />
		<p>Для выкачивания файла будет использован <strong><?php echo GdeSlonImport::checkCurl() ? 'cUrl' : 'file_get_contents'?></strong></p>
		<form method="post" action="<?php echo admin_url( 'admin-ajax.php' )?>?action=parse_url" target="_blank">
			<input type="hidden" name="code" value="<?php echo GS_Config::init()->get('ps_access_code'); ?>" />
			<input type="submit" class="button-primary archive" value="Запустить импорт" />
		</form>
		<br />
		<p>Дождитесь, чтобы импорт товаров закончился, иначе Ваша выгрузка будет неполной.</p>
		<form method="post" action="<?php echo admin_url( 'admin-ajax.php' )?>?action=get_direct" target="_blank">
			<input type="hidden" class="code" name="code" value="<?php echo GS_Config::init()->get('ps_access_code'); ?>" />
			<input type="submit" class="button-primary yandex" value="Скачать выгрузку для ЯндексДиректа" />
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
			<li>Добавление <a href="<?php bloginfo('wpurl'); ?>/wp-admin/widgets.php">виджета</a> на боковую панель блога.
				<br>(перейдите по ссылке и перетяните GdeSlon Widget на вашу боковую панель)</li>
		</ol>
	</div>

	<?php if ($categoriesNumber+$productsNumber > 0):?>
	<h3>Удаление данных</h3>
	<div style="border: 1px solid #aaa; padding: 7px;">
		<p>В базе сейчас:</p>
		<p><b><?php echo $categoriesNumber?></b> категорий</p>
		<p><b><?php echo $productsNumber?></b> товаров</p>
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
