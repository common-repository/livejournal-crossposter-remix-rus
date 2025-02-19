<?php

/*

Plugin Name: LiveJournal Crossposter Remix RUS

Plugin URI: http://вблокноте.рф/2011/03/livejournal-crossposter-remix-rus/

Description: Автоматически копирует все сообщения размещаемые на вашем блоге в Живой Журнал а так же ресурсы основанные при использовании LiveJournal Server. Текст помещенный между тегами текст [/ nocrosspost] перепечатываться не будет. Исправлена ошибка с исчезновением панели визуального редактора.
Automatically copies all the messages posted on your blog in Live Journal as well as resource-based using LiveJournal Server. The text is placed between the tags [nocrosspost] text [/ nocrosspost] will not be republished. Fixed a bug with the disappearance of the panel visual editor.
== Installation == Оригинальный плагин Арсений Иванов, Эван Бродер.

Version: 3.4
Author: Pilguy A 
Author URI: http://вблокноте.рф

*//*
Translate by Pilguy A. http://вблокноте.рф/ 2011

	Copyright (c) 2007 Evan Broder
	Copyright (c) 2008 Arseniy Ivanov
	Copyright (c) 2009 Sergey M.
	Copyright (c) 2011 Anatoliy pilguy


	Permission is hereby granted, free of charge, to any person obtaining a

	copy of this software and associated documentation files (the "Software"),

	to deal in the Software without restriction, including without limitation

	the rights to use, copy, modify, merge, publish, distribute, sublicense,

	and/or sell copies of the Software, and to permit persons to whom the

	Software is furnished to do so, subject to the following conditions:



	The above copyright notice and this permission notice shall be included in

	all copies or substantial portions of the Software.



	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR

	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,

	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE

	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER

	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING

	FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER

	DEALINGS IN THE SOFTWARE.

*/



define('LJXP_DOMAIN', '/ljxp/lang/ljxp');

load_plugin_textdomain(LJXP_DOMAIN);



require_once(ABSPATH . '/wp-includes/class-IXR.php');



require(ABSPATH . '/wp-includes/version.php');



if((bool)version_compare($wp_version, '2.5', '<')  && file_exists(ABSPATH . '/wp-includes/template-links.php')) {

	require_once(ABSPATH . '/wp-includes/template-links.php');

}



// Simulate wp-lj-comments by A-Bishop:



if(!function_exists('lj_comments')){

	function lj_comments($post_id){

        $link = "http://".$hostname = getenv("HTTP_HOST")."/wp-lj-comments.php?post_id=".$post_id;

		return '<img src="'.$link.'" border="0">';

	}

}



// Create the LJXP Options Page

function ljxp_add_pages() {

	add_options_page("LiveJournal", "LiveJournal", 6, __FILE__, 'ljxp_display_options');

}



// Display the options page

function ljxp_display_options() {

	global $wpdb;





	// List all options to load

	$option_list = array(	'ljxp_host'		=> 'www.livejournal.com',

				'ljxp_username'		=> '',

				'ljxp_password'		=> '',

				'ljxp_custom_name_on'	=> false,

				'ljxp_custom_name'	=> '',

				'ljxp_privacy'		=> 'public',

				'ljxp_comments'		=> 0,

				'ljxp_tag'		=> '1',

				'ljxp_more'		=> 'link',

				'ljxp_community'	=> '',

				'ljxp_skip_cats'	=> array(),

				'ljxp_header_loc'	=> 0,		// 0 means top, 1 means bottom

				'ljxp_custom_header'	=> '', ); // I love trailing commas





	// Options to be filtered with 'stripslashes'

	$option_stripslash = array('ljxp_host', 'ljxp_username', 'ljxp_custom_name', 'ljxp_community', 'ljxp_custom_header', );



	foreach($option_list as $_opt => $_default){

		add_option($_opt); // Just in case it does not exist

		$options[$_opt] =(in_array($_opt, $option_stripslash) ? stripslashes(get_option($_opt))  : get_option($_opt));  // Listed in $option_stripslash? Filter : Give away



		// If the option remains empty, set it to the default

		if($options[$_opt] == '' && $_default !== ''){

			update_option($_opt, $_default);

			$options[$_opt] = $_default;

		}



	}





	// If we're handling a submission, save the data

	if(isset($_REQUEST['update_lj_options']) || isset($_REQUEST['crosspost_all'])) {

		// Grab a list of all entries that have been crossposted

		$repost_ids = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='ljID'");



		// Set the update flag

		$need_update = 0;



		/*

		*   Warning. This is rather UNSAFE code. The only reason for it to remain unchanged so far is that it is inside a protected area. -- FreeAtNet

		*	TODO: fix security where appropriate

		*/



		$request_names = array('ljxp_host' 				=> 'host',

								'ljxp_username' 		=> 'username',

								'ljxp_custom_name_on'	=> 'custom_name_on',

								'ljxp_custom_name'		=> 'custom_name',

								'ljxp_privacy'			=> 'privacy',

								'ljxp_comments'			=> 'comments',

								'ljxp_tag'				=> 'tag',

								'ljxp_more'				=> 'more',

								'ljxp_community'		=> 'community',

								'ljxp_header_loc'		=> 'header_loc',

								'ljxp_custom_header'	=> 'custom_header',

								);



		foreach($request_names as $_orig => $_reqname){

			if(isset($_REQUEST[$_reqname]) && $_REQUEST[$_reqname] != $options[$_orig]){

				// Do the general stuff

				update_option($_orig, $_REQUEST[$_reqname]);

				$options[$_orig] = $_REQUEST[$_reqname]; // TODO: xss_clean($_REQUEST[$_reqname])



				// And then the custom actions

				switch($_orig){ // this is kinda harsh, I guess

					case 'ljxp_post' :

					case 'ljxp_username' :

					case 'ljxp_comments' :

					case 'ljxp_community' :

							ljxp_delete_all($repost_ids);

					case 'ljxp_custom_name_on' :

					case 'ljxp_privacy' :

					case 'ljxp_tag' :

					case 'ljxp_more' :

					case 'ljxp_custom_header' :

							$need_update = 1;

						break;

					case 'ljxp_custom_name' :

							if($options['ljxp_custom_name']) {

								$need_update = 1;

							}

						break;

					default:

							continue;

						break;

				}

			}

		}



		sort($options['ljxp_skip_cats']);

		$new_skip_cats = array_diff(get_all_category_ids(), (array)$_REQUEST['post_category']);

		sort($new_skip_cats);

		if($options['ljxp_skip_cats'] != $new_skip_cats) {

			update_option('ljxp_skip_cats', $new_skip_cats);

			$options['ljxp_skip_cats'] = $new_skip_cats;

		}



		unset($new_skip_cats);



		if($_REQUEST['password'] != "") {

			update_option('ljxp_password', md5($_REQUEST['password']));

		}



		if($need_update && isset($_REQUEST['update_lj_options'])) {

			@set_time_limit(0);

			ljxp_post_all($repost_ids);

		}



		if(isset($_REQUEST['crosspost_all'])) {

			@set_time_limit(0);

			ljxp_post_all($wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status='publish' AND post_type='post'"));

		}



		// Copied from another options page

		echo '<div id="message" class="updated fade"><p><strong>';

		_e('Настройки сохранены', LJXP_DOMAIN);

		echo '</strong></p></div>';

	}



	// And, finally, output the form

	// May add some Javascript to disable the custom_name field later - don't

	// feel like it now, though

?>

<div class="wrap">

	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">

		<h2><?php _e('Настройки публикации в ЖЖ', LJXP_DOMAIN); ?></h2>

		<table width="100%" cellspacing="2" cellpadding="5" class="editform">

			<tr valign="top">

				<th width="33%" scope="row"><?php _e('LiveJournal-compliant host:', LJXP_DOMAIN) ?></th>

				<td><input name="host" type="text" id="host" value="<?=htmlentities($options['ljxp_host']); ?>" size="40" /><br />

				<?php



				_e('Если вы используете LiveJournal-совместимый сайт, но не LiveJournal (например, http://www.deadjournal.com/), введите его адрес тут. Пользователям ЖЖ необходимо оставить поле без изменения.', LJXP_DOMAIN);



				?>

				</td>

			</tr>

			<tr valign="top">

				<th scope="row"><?php _e('LJ Логин', LJXP_DOMAIN); ?></th>

				<td><input name="username" type="text" id="username" value="<?=htmlentities($options['ljxp_username'], ENT_COMPAT, 'UTF-8'); ?>" size="40" /></td>

			</tr>

			<tr valign="top">

				<th scope="row"><?php _e('LJ Пароль', LJXP_DOMAIN); ?></th>

				<td><input name="password" type="password" id="password" value="" size="40" /><br />

				<?php



				_e('Вводите пароль если  хотите изменить уже сохраненный. Незаполненное поле сохраненный пароль не стирает', LJXP_DOMAIN);



				?>

				</td>

			</tr>

			<tr valign="top">

				<th scope="row"><?php _e('Сообщество', LJXP_DOMAIN); ?></th>

				<td><input name="community" type="text" id="community" value="<?=htmlentities($options['ljxp_community'], ENT_COMPAT, 'UTF-8'); ?>" size="40" /><br />

				<?php



				_e("Для размещения ваших записей в сообществе введите в данное поле имя сообщества.   ", LJXP_DOMAIN);



				?>

				</td>

			</tr>

		</table>

		<fieldset class="options">

			<legend><?php _e('Заголовок блога', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33%" scope="row"><?php _e('Настройки шапки для публикации в ЖЖ', LJXP_DOMAIN); ?></th>

					<td>

					<label>

						<input name="header_loc" type="radio" value="0" <?php checked($options['ljxp_header_loc'], 0); ?>/>

						<?php _e('Сверху записи', LJXP_DOMAIN); ?>

					</label>

					<br />

					<label>

						<input name="header_loc" type="radio" value="1" <?php checked($options['ljxp_header_loc'], 1); ?> /> <? _e('Снизу записи', LJXP_DOMAIN); ?></label></td>

				</tr>

				<tr valign="top">

					<th scope="row"><?php _e('Выберите заголовок блога', LJXP_DOMAIN); ?></th>

					<td>

						<label>

							<input name="custom_name_on" type="radio" value="0" <?php checked($options['ljxp_custom_name_on'], 0); ?>/>

							<?php printf(__('Использовать заголовок блога (%s)', LJXP_DOMAIN), get_settings('blogname')); ?>

						</label>

						<br />

						<label>

							<input name="custom_name_on" type="radio" value="1" <?php checked($options['ljxp_custom_name_on'], 1); ?>/>

							<? _e('Использовать свое название', LJXP_DOMAIN); ?>

						</label>

					</td>

				</tr>

				<tr valign="top">

					<th scope="row"><?php _e('Введите ваше название для заголовка', LJXP_DOMAIN); ?></th>

					<td><input name="custom_name" type="text" id="custom_name" value="<?=htmlentities($options['ljxp_custom_name'], ENT_COMPAT, 'UTF-8'); ?>" size="40" /><br />

					<?php



					_e('Заголовок будет использован для каждой записи в ЖЖ, и будет использоваться в качестве ссылки на ваш блог.', LJXP_DOMAIN);



					?>

					</td>

				</tr>

				<tr valign="top">

					<th scope="row"><?php _e('Вы можете построить собственную модель заголовка который будет использоваться в шапке.', LJXP_DOMAIN); ?></th>

					<td><textarea name="custom_header" id="custom_header" rows="3" cols="40"><?=htmlentities($options['ljxp_custom_header'], ENT_COMPAT, 'UTF-8'); ?></textarea><br />

					<?php



					_e("Если вам не нравиться формат и содержание заголовка по умолчанию используйте данное поля для создания своего варианта заголовка.Для подстановки вы пожете использовать следующие значения (зависят от регистра)", LJXP_DOMAIN);



					?>

					<dl>

						<dt>[blog_name]</dt>

						<dd><?php _e('Заголовок блога', LJXP_DOMAIN); ?></dd>



						<dt>[blog_link]</dt>

						<dd><?php _e("Ссылка на ваш блог", LJXP_DOMAIN); ?></dd>



						<dt>[permalink]</dt>

						<dd><?php _e('Ссылка на страницу записи, которая будет опубликована в ЖЖ', LJXP_DOMAIN); ?></dd>



						<dt>[comments_link]</dt>

						<dd><?php _e('Ссылка на комментарии. Обычно, это ссылка на запись, с #comments на конце"

#', LJXP_DOMAIN); ?></dd>



						<dt>[tags]</dt>

						<dd><?php _e('Список тегов-ссылок', LJXP_DOMAIN); ?></dd>



						<dt>[categories]</dt>

						<dd><?php _e('Список категорий-ссылок', LJXP_DOMAIN); ?></dd>



						<dt>[comments_count]</dt>

						<dd><?php _e('Счетчик комментариев Wordpress', LJXP_DOMAIN); ?></dd>



					</dl>

					</td>

			</table>

		</fieldset>

		<fieldset class="options">

			<legend><?php _e('Уровень доступа', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33%" scope="row"><?php _e('Уровень доступа к просмотру статей', LJXP_DOMAIN); ?></th>

					<td>

						<label>

							<input name="privacy" type="radio" value="public" <?php checked($options['ljxp_privacy'], 'public'); ?>/>

							<?php _e('Открытый(для всех пользователей)', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="privacy" type="radio" value="private" <?php checked($options['ljxp_privacy'], 'private'); ?> />

							<?php _e('Частный (только для себя)', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="privacy" type="radio" value="friends" <?php checked($options['ljxp_privacy'], 'friends'); ?>/>

							<?php _e('Только для друзей', LJXP_DOMAIN); ?>

						</label>

						<br />

					</td>

				</tr>

			</table>

		</fieldset>

		<fieldset class="options">

			<legend><?php _e('LiveJournal Comments', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33%" scope="row"><?php _e('Should comments be allowed on LiveJournal?', LJXP_DOMAIN); ?></th>

					<td>

					<label>

						<input name="comments" type="radio" value="0" <?php checked($options['ljxp_comments'], 0); ?>/>

						<?php _e('Require users to comment on WordPress', LJXP_DOMAIN); ?>

					</label>

					<br />

					<label>

						<input name="comments" type="radio" value="1" <?php checked($options['ljxp_comments'], 1); ?>/>

						<?php _e('Разрешить комментарии в LiveJournal', LJXP_DOMAIN); ?>

					</label>

					<br />

				</tr>

			</table>

		</fieldset>

		<fieldset class="options">

			<legend><?php _e('LiveJournal Теги', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33% scope="row"><?php _e('Помечать записи в LiveJournal?', LJXP_DOMAIN); ?></th>

					<td>

					<?php

						/* PHP-only comment:

						 *

						 * Yes, 1 -> 3 -> 2 -> 0 is a wierd order, but

						 * if categories = 1 and tags = 2,

						 * nothing would equal 0

						 * and

						 * tags+categories = 3

						 */

					?>

						<label>

							<input name="tag" type="radio" value="1" <?php checked($options['ljxp_tag'], 1); ?>/>

							<?php _e('Называть рубрики в ЖЖ именами рубрик в WordPress', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="tag" type="radio" value="3" <?php checked($options['ljxp_tag'], 3); ?>/>

							<?php _e('Совмещать рубрики и тэги Wordpress в пометках ЖЖ', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="tag" type="radio" value="2" <?php checked($options['ljxp_tag'], 2); ?>/>

							<?php _e('Tag LiveJournal entries with WordPress tags only', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="tag" type="radio" value="0" <?php checked($options['ljxp_tag'], 0); ?>/>

							<?php _e('Не помечать записи в ЖЖ', LJXP_DOMAIN); ?>

						</label>

						<br />

						<?php

						_e('Вы можете отключить эту функцию, если ваши записи не в латинице. ЖЖ не поддерживает нелатинские символы в тегах.', LJXP_DOMAIN);

						?>

					</td>

				</tr>

			</table>

		</fieldset>

		<fieldset class="options">

			<legend><?php _e('Использование &lt;!--More--&gt;', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33%" scope="row"><?php _e('Как обрабатывать тег More?', LJXP_DOMAIN); ?></th>

					<td>

						<label>

							<input name="more" type="radio" value="link" <?php checked($options['ljxp_more'], 'link'); ?>/>

							<?php _e('Запись привязанная к одной из этих рубрик  WordPress всегда публикуется в ЖЖ', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="more" type="radio" value="lj-cut" <?php checked($options['ljxp_more'], 'lj-cut'); ?>/>

							<?php _e('Use an lj-cut', LJXP_DOMAIN); ?>

						</label>

						<br />

						<label>

							<input name="more" type="radio" value="copy" <?php checked($options['ljxp_more'], 'copy'); ?>/>

							<?php _e('Copy the entire entry to LiveJournal', LJXP_DOMAIN); ?>

						</label>

						<br />

					</td>

				</tr>

			</table>

		</fieldset>

		<fieldset class="options">

			<legend><?php _e('Category Selection', LJXP_DOMAIN); ?></legend>

			<table width="100%" cellspacing="2" cellpadding="5" class="editform">

				<tr valign="top">

					<th width="33%" scope="row"><?php _e('Select which categories should be crossposted', LJXP_DOMAIN); ?></th>

					<td>

					<?php

					( function_exists('write_nested_categories') ?

						write_nested_categories(ljxp_cat_select(get_nested_categories(), $options['ljxp_skip_cats']))

						: wp_category_checklist(false, false, array_diff(get_all_category_ids(), (array)$options['ljxp_skip_cats']))

					);

					?><br />

				

					</td>

				</tr>

			</table>

		</fieldset>

		<p class="submit">

			<input type="submit" name="crosspost_all" value="<?php _e('Сохранить настройки и опубликовать все записи из WordPress', LJXP_DOMAIN); ?>" />

			<input type="submit" name="update_lj_options" value="<?php _e('Сохранить настройки'); ?>" style="font-weight: bold;" />

		</p>

	</form>

</div>

<?php

}



function ljxp_cat_select($cats, $selected_cats) {

	foreach((array)$cats as $key=>$cat) {

		$cats[$key]['checked'] = !in_array($cat['cat_ID'], $selected_cats);

		$cats[$key]['children'] = ljxp_cat_select($cat['children'], $selected_cats);

	}

	return $cats;

}



function ljxp_post($post_id) {

	global $wpdb, $tags, $cats; // tags/cats are going to be filtered thru an external function



	// If the post was manually set to not be crossposted, give up now

	if(get_post_meta($post_id, 'no_lj', true)) {

		return $post_id;

	}



	// Get the relevent info out of the database

	$options = array(

						'host' => stripslashes(get_option('ljxp_host')),

						'user' => stripslashes(get_option('ljxp_username')),

						'pass' => get_option('ljxp_password'),

						'custom_name_on' => get_option('ljxp_custom_name_on'),

						'custom_name' => stripslashes(get_option('ljxp_custom_name')),

						'privacy' => ( (get_post_meta($post_id, 'ljxp_privacy', true) != 0) ?

									get_post_meta($post_id, 'ljxp_privacy', true) :

										get_option('ljxp_privacy') ),

						'comments' => ( (get_post_meta($post_id, 'ljxp_comments', true != 0) ) ? ( 2 - get_post_meta($post_id, 'ljxp_comments', true) ) : get_option('ljxp_comments') ),

						'tag' => get_option('ljxp_tag'),

						'more' => get_option('ljxp_more'),

						'community' => stripslashes(get_option('ljxp_community')),

						'skip_cats' => get_option('ljxp_skip_cats'),

						'copy_cats' => array_diff(get_all_category_ids(), get_option('ljxp_skip_cats')),

						'header_loc' => get_option('ljxp_header_loc'),

						'custom_header' => stripslashes(get_option('ljxp_custom_header')),

	);







	// If the post shows up in the forbidden category list and it has been

	// crossposted before (so the forbidden category list must have changed),

	// delete the post. Otherwise, just give up now

	$do_crosspost = 0;



	foreach(wp_get_post_cats(1, $post_id) as $cat) {

		if(in_array($cat, $options['copy_cats'])) {

			$do_crosspost = 1;

			break; // decision made and cannot be altered, fly on

		}

	}



	if(!$do_crosspost) {

		return ljxp_delete($post_id);

	}



	// And create our connection

	$client = new IXR_Client($options['host'], '/interface/xmlrpc');



	// Get the challenge string

	// Using challenge for the most security. Allows pwd hash to be stored

	// instead of pwd

	if (!$client->query('LJ.XMLRPC.getchallenge')) {

		wp_die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());

	}



	// And retrieve the challenge string

	$response = $client->getResponse();

	$challenge = $response['challenge'];



	$post = & get_post($post_id);



	// Insert the name of the page we're linking back to based on the options set

	if(!$options['custom_name_on']) {

		$blogName = get_option("blogname");

	}

	else {

		$blogName = $options['custom_name'];

	}







	// Tagging and categorizing ? for LJ tags

	// Not to be moved down: the else case of custom header is using $cats and $tags



	$cats = array();

	$tags = array();



	$cats = wp_get_post_categories($post_id, array('fields' => 'all')); // wp_get_post_cats is deprecated as of WP2.5

																		// the new function can get names itself, too

	$tags = wp_get_post_tags($post_id, array('fields' => 'all'));





	// Need advice on merging all ( /\ and \/ ) this code



	// convert retrieved objects to arrays of (term_id => name) pairs

	$modify = create_function('$f, $n, $obj', 'global $$f; $p = &$$f; unset($p[$n]); $p[$obj->term_id] = $obj->name;');



	if(count($tags) > 0) array_map($modify, array_fill(0, count($tags), 'tags'), array_keys($tags), array_values($tags));

	if(count($cats) > 0) array_map($modify, array_fill(0, count($cats), 'cats'), array_keys($cats), array_values($cats));





	switch($options['tag']){

		case 0 :

				// pass

			break;

		case 1 :

				$cat_string = implode(", ", $cats);

			break;

		case 2 :

				$cat_string = implode(", ", $tags);

			break;

		case 3 :

				$cat_string = implode(", ", array_unique(array_merge($cats, $tags)));

			break;

	}



	if($options['custom_header'] == '') {

		$postHeader = '<p style="border: 1px solid black; padding: 3px;"><strong>';



		// If the post is not password protected, follow standard procedure

		if(!$post->post_password) {

			$postHeader .= __('Запись опубликована', LJXP_DOMAIN);

			$postHeader .= ' <a href="'.get_permalink($post_id).'">';

			$postHeader .= $blogName;

			$postHeader .= '</a>.';

		}

		// If the post is password protected, put up a special message

		else {

			$postHeader .= __('Эта запись защищена паролем. Вы можете прочитать ее на', LJXP_DOMAIN);

			$postHeader .= ' <a href="'.get_permalink($post_id).'">';

			$postHeader .= $blogName;

			$postHeader .= '</a>, ';

			$postHeader .= __('где она была опубликована', LJXP_DOMAIN);

			$postHeader .= '.';

		}



		// Depending on whether comments or allowed or not, alter the header

		// appropriately

		if($options['comments']) {

			$postHeader .= sprintf(__(' Вы можете оставить комментарии здесь или <a href="%s#comments">здесь</a>.', LJXP_DOMAIN), get_permalink($post_id));

		}

		else {

			$postHeader .= sprintf(__(' Пожалуйста оставляйте свои  <a href="%s#comments">комментарии</a> тут.', LJXP_DOMAIN), get_permalink($post_id));

		}



		$postHeader .= '</strong></p>';

	}

	else {

		$postHeader = $options['custom_header'];





		// pre-post formatting for tags and categories

		$htags = '';

		$hcats = '';



		foreach($tags as $_term_id => $_name) $htags[] = '<a href="'.get_tag_link($_term_id).'" rel="bookmark">'.$_name.'</a>';

		foreach($cats as $_term_id => $_name) $hcats[] = '<a href="'.get_category_link($_term_id).'" rel="bookmark">'.$_name.'</a>';



		$htags = implode(', ', (array)$htags);

		$hcats = implode(', ', (array)$hcats);



		$find = array('[blog_name]', '[blog_link]', '[permalink]', '[comments_link]', '[comments_count]', '[tags]', '[categories]');

		$replace = array($blogName, get_settings('home'), get_permalink($post_id), get_permalink($post_id).'#comments', lj_comments($post_id), $htags, $hcats);

		$postHeader = str_replace($find, $replace, $postHeader);

	}



	// $the_event will eventually be passed to the LJ XML-RPC server.

	$the_event = "";



	// and if the post isn't password protected, we need to put together the

	// actual post


	if(!$post->post_password) {
		

		// and if there's no <!--more--> tag, we can spit it out and go on our

		// merry way
		
		if(preg_match('/<!--more(.*?)?-->/', $post->post_content, $matches)) {
			
			//cut text in shortcode whish is not for crosspost
			$post_content=preg_replace('~\[nocrosspost\](.|\s)*?\[\/nocrosspost\]~','',$post->post_content);
			
			$content = explode($matches[0], $post_content, 2);
			if ( !empty($matches[1]) )
				$more_link_text = strip_tags(wp_kses_no_null(trim($matches[1])));

			$the_event .= apply_filters('the_content', $content[0]);
			
			//type of crossposting
			
			switch($options['more']) {

			case "copy":

				$the_event .= apply_filters('the_content', $content[1]);

				break;

			case "link":

				$the_event .= sprintf('<p><a href="%s#more-%s">', get_permalink($post_id), $post_id) .

					__($more_link_text.' &raquo;', LJXP_DOMAIN) .

					'</a></p>';

				break;

			case "lj-cut":

				$the_event .= '<lj-cut text="' .

					__('Читать запись полностью &amp;raquo;', LJXP_DOMAIN) .

					'">' . apply_filters('the_content', $content[1]) . '</lj-cut>';

				break;

			}

		}
		//if is not cut

		else {
			//cut text in shortcode whish is not for crosspost
			$post_content=preg_replace('~\[nocrosspost\](.|\s)*?\[\/nocrosspost\]~','',$post->post_content);
			
			$the_event .= apply_filters('the_content', $post_content);

		}

	}



	// Either prepend or append the header to $the_event, depending on the

	// config setting

	// Remember that 0 is at the top, 1 at the bottom

	if($options['header_loc']) {

		$the_event .= $postHeader;

	}

	else {

		$the_event = $postHeader.$the_event;

	}



	// Get the most recent post (to see if this is it - it it's not, backdate)

	$recent_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_status='publish' AND post_type='post' ORDER BY post_date DESC LIMIT 1");



	// Get a timestamp for retrieving dates later

	$date = strtotime($post->post_date);



	$args = array('username'			=> $options['user'],

					'auth_method'		=> 'challenge',

					'auth_challenge'	=> $challenge,

					'auth_response'		=> md5($challenge . $options['pass']),	// By spec, auth_response is md5(challenge + md5(pass))

					'ver'				=> '1', 	// Receive UTF-8 instead of ISO-8859-1

					'event'				=> $the_event,

					'subject'			=> apply_filters('the_title', $post->post_title),

					'year'				=> date('Y', $date),

					'mon'				=> date('n', $date),

					'day'				=> date('j', $date),

					'hour'				=> date('G', $date),

					'min'				=> date('i', $date),

					'props'				=> array('opt_nocomments'	=> !$options['comments'], // allow comments?

												 'opt_preformatted'	=> true, // event text is preformatted

												 'opt_backdated'	=> !($post_id == $recent_id), // prevent updated

																	// post from being show on top

												'taglist'			=> ($options['tag'] != 0 ? $cat_string : ''),

												),

					'usejournal'		=> (!empty($options['community']) ? $options['community'] : $options['user']),

					);

	// Set the privacy level according to the settings

	switch($options['privacy']) {

		case "public":

			$args['security'] = 'public';

			break;

		case "private":

			$args['security'] = 'private';

			break;

		case "friends":

			$args['security'] = 'usemask';

			$args['allowmask'] = 1;

			break;

		default :

			$args['security'] = "public";

			break;

	}



	// Assume this is a new post

	$method = 'LJ.XMLRPC.postevent';



	// But check to see if there's an LJ post associated with our WP post

	if(get_post_meta($post_id, 'ljID', true)) {

		// If there is, add the itemid attribute and change from posting to editing

		$args['itemid'] = get_post_meta($post_id, 'ljID', true);

		$method = 'LJ.XMLRPC.editevent';

	}



	// And awaaaayyy we go!

	if (!$client->query($method, $args)) {

		wp_die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());

	}



	// If we were making a new post on LJ, we need the itemid for future reference

	if('LJ.XMLRPC.postevent' == $method) {

		$response = $client->getResponse();

		// Store it to the metadata

		add_post_meta($post_id, 'ljID', $response['itemid']);

	}

	// If you don't return this, other plugins and hooks won't work

	return $post_id;

}



function ljxp_delete($post_id) {

	// Pull the post_id

	$ljxp_post_id = get_post_meta($post_id, 'ljID', true);



	// Ensures that there's actually a value. If the post was never

	// cross-posted, the value wouldn't be set, and there's no point in

	// deleting entries that don't exist

	if($ljxp_post_id == 0) {

		return $post_id;

	}



	// Get the necessary login info

	$host = get_option('ljxp_host');

	$user = get_option('ljxp_username');

	$pass = get_option('ljxp_password');



	// And open the XMLRPC interface

	$client = new IXR_Client($host, '/interface/xmlrpc');



	// Request the challenge for authentication

	if (!$client->query('LJ.XMLRPC.getchallenge')) {

		wp_die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());

	}



	// And retrieve the challenge that LJ returns

	$response = $client->getResponse();

	$challenge = $response['challenge'];



	// Most of this is the same as before. The important difference is the

	// value of $args[event]. By setting it to a null value, LJ deletes the

	// entry. Really rather klunky way of doing things, but not my code!

	$args = array(



				'username' => $user,

				'auth_method' => 'challenge',

				'auth_challenge' => $challenge,

				'auth_response' => md5($challenge . $pass),

				'itemid' => $ljxp_post_id,

				'event' => "",

				'subject' => "Delete this entry",

				// I probably don't need to set these, but, hell, I've got it working

				'year' => date('Y'),

				'mon' => date('n'),

				'day' => date('j'),

				'hour' => date('G'),

				'min' => date('i'),



	);





	// And awaaaayyy we go!

	if (!$client->query('LJ.XMLRPC.editevent', $args)) {

		wp_die('Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage());

	}



	delete_post_meta($post_id, 'ljID');



	return $post_id;

}



function ljxp_edit($post_id) {

	// This function will delete a post from LJ if it's changed from the

	// published status or if crossposting was just disabled on this post



	// Pull the post_id

	$ljxp_post_id = get_post_meta($post_id, 'ljID', true);



	// Ensures that there's actually a value. If the post was never

	// cross-posted, the value wouldn't be set, so we're done

	if(0 == $ljxp_post_id) {

		return $post_id;

	}



	$post = & get_post($post_id);



	// See if the post is currently published. If it's been crossposted and its

	// state isn't published, then it should be deleted

	// Also, if it has been crossposted but it's set to not crosspost, then

	// delete it

	if('publish' != $post->post_status || 1 == get_post_meta($post_id, 'no_lj', true)) {

		ljxp_delete($post_id);

	}



	return $post_id;

}



function ljxp_sidebar() {

	global $post, $wp_version;

?>

	<<?=((bool)version_compare($wp_version, '2.5', '>=')? 'div class="postbox closed"' : 'fieldset class="dbx-box"' )?> id="ljxpdiv">

		<h3<?=((bool)version_compare($wp_version, '2.5', '<=')?'><a class="togbox">+</a':' class="dbx-handle"')?>> <?php _e('Живой Журнал', LJXP_DOMAIN); ?>:</h3>

		<div <?=((bool)version_compare($wp_version, '2.5', '>=')?'class="inside"':'class="dbx-content"')?>>

        	<?=((bool)version_compare($wp_version, '2.5', '>=')?'<p>':'')?>



			<label class="selectit" for="ljxp_crosspost_go">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'no_lj', true), 0); ?> value="1" name="ljxp_crosspost" id="ljxp_crosspost_go"/>

				<?php _e('Crosspost', LJXP_DOMAIN); ?>

			</label>



			<label class="selectit" for="ljxp_crosspost_nogo">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'no_lj', true), 1); ?> value="0" name="ljxp_crosspost" id="ljxp_crosspost_nogo"/>

				<?php _e('Не публиковать', LJXP_DOMAIN); ?>

			</label>



			<br/>



			<label class="selectit" for="ljxp_comments_default">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_comments', true), 0); ?> value="0" name="ljxp_comments" id="ljxp_comments_default"/>

				<?php _e('Default comments setting', LJXP_DOMAIN); ?>

			</label>

			<label class="selectit" for="ljxp_comments_on">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_comments', true), 1); ?> value="1" name="ljxp_comments" id="ljxp_comments_on"/>

				<?php _e('Comments on', LJXP_DOMAIN); ?>

			</label>

			<label class="selectit" for="ljxp_comments_off">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_comments', true), 2); ?> value="2" name="ljxp_comments" id="ljxp_comments_off"/>

				<?php _e('Comments off', LJXP_DOMAIN); ?>

			</label>



			<br/>



			<label class="selectit" for="ljxp_privacy_default">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_privacy', true), 0); ?> value="0" name="ljxp_privacy" id="ljxp_privacy_default"/>

				<?php _e('Настройка приватности для записи', LJXP_DOMAIN); ?>

			</label>

			<label class="selectit" for="ljxp_privacy_public">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_privacy', true), 'public'); ?> value="public" name="ljxp_privacy" id="ljxp_privacy_public"/>

				<?php _e('Публичный', LJXP_DOMAIN); ?>

			</label>

			<label class="selectit" for="ljxp_privacy_private">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_privacy', true), 'private'); ?> value="private" name="ljxp_privacy" id="ljxp_privacy_private"/>

				<?php _e('Для частного просмотра', LJXP_DOMAIN); ?>

			</label>

			<label class="selectit" for="ljxp_privacy_friends">

				<input type="radio" <?php checked(get_post_meta($post->ID, 'ljxp_privacy', true), 'friends'); ?> value="friends" name="ljxp_privacy" id="ljxp_privacy_friends"/>

				<?php _e('Friends only', LJXP_DOMAIN); ?>

			</label>

        	<?=((bool)version_compare($wp_version, '2.5', '>=')?'</p>':'')?>

		</div>

	</<?=((bool)version_compare($wp_version, '2.5', '>=')? 'div' : 'fieldset' )?>>



<?php

}



function ljxp_save($post_id) {

	// If the magic crossposting variable isn't equal to 'crosspost', then the

	// box wasn't checked

	// Using publish_post hook for the case of a state change---this will

	// be called before crossposting occurs

	// Using save_post for the case where it's draft or private - the value

	// still needs to be saved

	// Using edit_post for the case in which it's changed from crossposted to

	// not crossposted in an edit



	// At least one of those hooks is probably unnecessary, but I can't figure

	// out which one

	if(isset($_POST['ljxp_crosspost'])) {

		delete_post_meta($post_id, 'no_lj');

		if(0 == $_POST['ljxp_crosspost']) {

			add_post_meta($post_id, 'no_lj', '1');

		}

	}

	if(isset($_POST['ljxp_comments'])) {

		delete_post_meta($post_id, 'ljxp_comments');

		if($_POST['ljxp_comments'] !== 0) {

			add_post_meta($post_id, 'ljxp_comments', $_POST['ljxp_comments']);

		}

	}



	if(isset($_POST['ljxp_privacy'])) {

			delete_post_meta($post_id, 'ljxp_privacy');

		if($_POST['ljxp_privacy'] !== 0) {

			add_post_meta($post_id, 'ljxp_privacy', $_POST['ljxp_privacy']);

		}

	}

}



function ljxp_delete_all($repost_ids) {

	foreach((array)$repost_ids as $id) {

		ljxp_delete($id);

	}

}



function ljxp_post_all($repost_ids) {

	foreach((array)$repost_ids as $id) {

		ljxp_post($id);

	}

}







add_action('admin_menu', 'ljxp_add_pages');

if(get_option('ljxp_username') != "") {

	add_action('publish_post', 'ljxp_post');

	add_action('publish_future_post', 'ljxp_post');

	add_action('edit_post', 'ljxp_edit');

	add_action('delete_post', 'ljxp_delete');

	add_action('dbx_post_sidebar', 'ljxp_sidebar');

	add_action('publish_post', 'ljxp_save', 1);

	add_action('save_post', 'ljxp_save', 1);

	add_action('edit_post', 'ljxp_save', 1);

}

//Filter for cut text in [nocrosspost][/nocrosspost]  in RSS
add_filter('the_content','ljxp_cut_rss',6);
function ljxp_cut_rss($content){
if(is_feed()) 
	//if is RSS - clean shortcodes and text inside
		$content=preg_replace('~\[nocrosspost\](.|\s)*?\[\/nocrosspost\]~','',$content);
	else 
		//and if is not - clean shortcodes
		$content=preg_replace('~\[\/?nocrosspost\]~','',$content);
	return $content;
}

//add non-visual button
add_action('admin_head','ljxp_add_shortcode_admin');
function ljxp_add_shortcode_admin(){
	//only in edit posts
	if(preg_match('~post(-new)?.php~',$_SERVER['REQUEST_URI'])){
		//insert js-file of pseudo-editor directly now
		wp_print_scripts( 'quicktags' );
		
		//insert new button in array
		echo "<script type=\"text/javascript\">"."\n";
		echo "/* <![CDATA[ */"."\n";
		echo "edButtons[edButtons.length] = new edButton"."\n";
		echo "\t('ed_nocross',"."\n";
		echo "\t'NOCROSS'"."\n";
		echo "\t,'[nocrosspost]'"."\n";
		echo "\t,'[/nocrosspost]'"."\n";
		echo "\t,'n'"."\n";
		echo "\t);"."\n";
		echo "/* ]]> */"."\n";
		echo "</script>"."\n";
		}
}

//change TinyMCE visual editor
add_action('init', 'ljxp_add_shortcode_mce');
function ljxp_add_shortcode_mce() {
	if(!preg_match('~post(-new)?.php~',$_SERVER['REQUEST_URI']) && !current_user_can('edit_posts') 
		&& ! current_user_can('edit_pages')) {
		return;
	}
	if('true' == get_user_option('rich_editing')) {
		add_filter("mce_external_plugins", "ljxp_tinymce_addplugin");
		add_filter("mce_buttons", "ljxp_tinymce_registerbutton");
		//for 2.6 and below
		add_filter("mce_plugins", "ljxp_tinymce_old_addplugin");
		add_action("tinymce_before_init", "ljxp_tinymce_old_load");
	}
}

//add buttons to TinyMCE
function &ljxp_tinymce_registerbutton(&$buttons) {
	array_push($buttons, 'separator', 'nocross');

	return $buttons;
}

//add plugin to TinyMCE
//for 2.7 and upper
function &ljxp_tinymce_addplugin(&$plugin_array) {
	if (function_exists ('plugins_url') )
		$plugin_array['nocross'] = plugins_url('livejournal-crossposter-remix-rus/tinymce/plugins/ljxp/editor_plugin.js');
	else
		$plugin_array['ljusers'] = get_option('url') . '/wp-content/plugins/livejournal-crossposter-remix-rus/tinymce/plugins/ljxp/editor_plugin.js';

	return $plugin_array;
}

//add plugin to TinyMCE
//for 2.6 and below
function &ljxp_tinymce_old_addplugin(&$plugins) {
	array_push($plugins, '-nocross');

	return $plugins;
}

//add plugin's loading to TinyMCE
//for 2.4 and below
function ljxp_tinymce_old_load() {
	if (function_exists ('plugins_url') )
		echo "tinyMCE.loadPlugin('nocross', '". plugins_url("/wp-content/plugins/livejournal-crossposter-remix-rus/tinymce/plugins/ljxp/") ."');\n";
	else
		echo "tinyMCE.loadPlugin('nocross', '".get_bloginfo('url')."/wp-content/plugins/livejournal-crossposter-remix-rus/tinymce/plugins/ljxp/');\n";
}


?>