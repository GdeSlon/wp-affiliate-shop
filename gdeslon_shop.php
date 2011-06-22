<?php
/*
Plugin Name: GdeSlon Affiliate Shop
Version: 1.0
Author: Sergey Yalanskiy
*/
require('gs_tools.php');
require('gs_pages.php');

add_filter("the_content", "psCatalog");
add_filter("wp_title", "psTitle");
add_action("wp_head", "psStyles", 100);

// *****************************************************************************************************

function psOptionsPage() {
    if ($_POST['action'] == 'update') {
        $url = $_POST['ps_url'];
        update_option('ps_url', $url);
        update_option('ps_page', $_POST['ps_page']);
        update_option('ps_limit', $_POST['ps_limit']);
        update_option('ps_row_limit', $_POST['ps_row_limit']);
    ?>
    <div class="updated"><p><strong>Настройки сохранены</strong></p></div>
    <?php
    }
    $url = get_option('ps_url');
    $ps_page = get_option('ps_page');
    ?>
    <div class="wrap">
    <h2>GdeSlon Affiliate Shop - Настройки</h2>
    <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="action" value="update">
    <table class="form-table">
	    <tr valign="top">
	    	<th scope="row"><label for="ps_url">Ссылка на выгрузку</label></th>
	    	<td><input name="ps_url" type="text" value="<?php echo $url; ?>" class="regular-text" style="width: 550px;" /></td>
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
	    	<td><input name="ps_limit" type="text" value="<?php echo get_option('ps_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
	    </tr>
	    <tr valign="top">
	    	<th scope="row"><label for="ps_row_limit">Кол-во товаров в строке</label></th>
	    	<td><input name="ps_row_limit" type="text" value="<?php echo get_option('ps_row_limit'); ?>" class="regular-text" style="width: 100px;" /></td>
	    </tr>
    </table>
    <p class="submit">
    	<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    </form>
    <div style="border: 1px solid #aaa; padding: 7px;">
    	Необходимо в крон добавить один из вариантов запуска модуля импорта:<br /><br />
    	<b>php <?php echo ABSPATH; ?>wp-content/plugins/GdeSlon_Affiliate_Shop/cron.php</b><br /><br />
    	<b>GET <?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/cron.php?code=<?php echo get_option('ps_access_code'); ?></b><br />
    	<br />
    	<form method="get" action="<?php bloginfo('home'); ?>/wp-content/plugins/GdeSlon_Affiliate_Shop/cron.php" target="_blank">
    		<input type="hidden" name="code" value="<?php echo get_option('ps_access_code'); ?>" />
    		<input type="submit" class="button-primary" value="Запустить импорт" />
    	</form>
    </div><br />
    <div style="border: 1px solid #aaa; padding: 7px;">
    	Для вывода блока с категориями добавьте этот код:
    	<pre><b>&lt;?php if (function_exists('psCategories')) psCategories(); ?&gt;</b></pre>
    	Обычно он добавляется в файл sidebar.php
    </div>
    </div>
    <?php
}

function psCatalogPage() {
	global $wpdb;
    if( $_POST['action'] == 'update') {
	    ?>
	    <div class="updated"><p><strong>Данные сохранены</strong></p></div>
	    <?php
    }
    $ps_search = @$_GET['ps_search'];
    $ps_cat = @$_GET['ps_cat'];
    ?>
    <div class="wrap">
    <h2>GdeSlon Affiliate Shop - Каталог</h2>
    <form name="form1" method="get" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="page" value="psCatalogPage">
    <input type="hidden" name="action" value="search">
    <table class="form-table">
	    <tr valign="top">
	    	<th scope="row"><label for="ps_search">Найти товар</label></th>
	    	<td><input name="ps_search" type="text" value="<?php echo $ps_search; ?>" class="regular-text" style="width: 550px;" /></td>
	    </tr>
	    <tr valign="top">
	    	<th scope="row"><label for="ps_cat">Раздел</label></th>
	    	<td>
	    		<?php $cats = getCategoriesTreeList(null, 0, array()); ?>
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
    <?php if (!empty($ps_search) || !empty($ps_cat)) { ?>
    	<?php
    		$where = array('1=1');
    		if (!empty($ps_search)) {
    			$ps_search - mysql_real_escape_string($ps_search);
    			$where[] = "UPPER(title) LIKE UPPER('%$ps_search%')";
    		}
    		if (!empty($ps_cat)) {
    			$allCats = getCategoriesChildren($ps_cat);
    			$where[] = "category_id IN (".implode(',', $allCats).")";
    		}
    		
    		if (!empty($_GET['del'])) {
    			$wpdb->query("UPDATE ps_products SET status = 2 WHERE id = {$_GET['del']}");
    		}
    		if (@$_POST['action'] == 'update') {
    			if (!empty($_POST['product_id'])) {
    				$id = $_POST['product_id'];
    				$title = mysql_real_escape_string($_POST['ps_title']);
    				$description = mysql_real_escape_string($_POST['ps_description']);
    				$wpdb->query("UPDATE ps_products SET title = '$title', description = '$description', manual = 1 WHERE id = $id");
    			}
    			
    			$products = $wpdb->get_results("SELECT id FROM ps_products WHERE ".implode(' AND ', $where)." ");
    			$IDs = array();
    			foreach ($products as $item) $IDs[] = $item->id;
    			$wpdb->query("UPDATE ps_products SET bestseller = 0 WHERE id IN (".implode(',', $IDs).")");
    			if (!empty($_POST['bs']) && is_array($_POST['bs'])) {
   					$wpdb->query("UPDATE ps_products SET bestseller = 1 WHERE id IN (".implode(',', $_POST['bs']).")");
    			}
    		}

    		$statuses = array(
    			0 => 'Неакт.',
    			1 => 'Актив.',
    			2 => 'Удален'
    		);
    		
    		$products = $wpdb->get_results("SELECT * FROM ps_products WHERE ".implode(' AND ', $where)." ORDER BY title ASC");
    	?>
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
		    			<td><a href="<?php echo get_permalink(get_option('ps_page')); ?>?pid=<?php echo $item->id; ?>" target="_blank"><?php echo $item->title; ?></a></td>
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
    <?php } ?>
    </div>
    <?php
}

add_action("admin_menu", "psAdminPage");

function psAdminPage() {
	add_menu_page('GdeSlon Shop', 'GdeSlon Shop', 8, __FILE__, 'psOptionsPage');
	add_submenu_page(__FILE__, 'Настройки', 'Настройки', 8, __FILE__, 'psOptionsPage');
	add_submenu_page(__FILE__, 'Каталог', 'Каталог', 8, 'psCatalogPage', 'psCatalogPage');
}

function psActivate() {
	global $wpdb;
    update_option('ps_url', '');
    update_option('ps_access_code', md5(rand(1, 10000).rand(1, 1000).time()));
    $wpdb->query("
		CREATE TABLE `ps_categories` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`parent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
			`title` VARCHAR(200) NOT NULL,
			PRIMARY KEY (`id`),
			INDEX `FKps_categories_parent_id` (`parent_id`),
			CONSTRAINT `FKps_categories_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `ps_categories` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;
    ");
    $wpdb->query("
		CREATE TABLE `ps_products` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`category_id` INT(11) UNSIGNED NOT NULL,
			`title` TEXT NOT NULL,
			`description` TEXT NULL,
			`url` VARCHAR(255) NOT NULL,
			`price` VARCHAR(50) NOT NULL,
			`currency` VARCHAR(10) NOT NULL,
			`image` VARCHAR(255) NULL DEFAULT NULL,
			`bestseller` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`status` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
			`marked` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`manual` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
			`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			INDEX `FKps_products_category_id` (`category_id`),
			INDEX `ps_products_status` (`status`),
			INDEX `ps_products_marked` (`marked`),
			INDEX `ps_products_title` (`title`(100)),
			CONSTRAINT `FKps_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `ps_categories` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
		)
		COLLATE='utf8_general_ci'
		ENGINE=InnoDB;
    ");
}

function psDeactivate() {
	global $wpdb;
    delete_option('ps_url');
    delete_option('ps_access_code');
    $wpdb->query("DROP TABLE `ps_products`;");
    $wpdb->query("DROP TABLE `ps_categories`;");
}

register_activation_hook(__FILE__, 'psActivate');
register_deactivation_hook(__FILE__, 'psDeactivate');
?>