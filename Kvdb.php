<?php
/**
 * Kvvaradha - A Supportive files of $kvdb. Which helps to create tables with another database.  
 *
 * * */

 
 
 
if ( file_exists(WP_CONTENT_DIR . '/install.php') )
	require (WP_CONTENT_DIR . '/install.php');

require_once(ABSPATH . 'wp-admin/includes/admin.php');
require_once(ABSPATH . 'wp-admin/includes/schema.php');

if ( !function_exists('wp_install') ) :

function wp_install( $blog_title, $user_name, $user_email, $public, $deprecated = '', $user_password = '' ) {
	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.6' );

	wp_check_mysql_version();
	wp_cache_flush();
	make_db_current_silent();
	populate_options();
	populate_roles();

	update_option('blogname', $blog_title);
	update_option('admin_email', $user_email);
	update_option('blog_public', $public);

	$guessurl = wp_guess_url();

	update_option('siteurl', $guessurl);

	
	if ( ! $public )
		update_option('default_pingback_flag', 0);

	
	$user_id = username_exists($user_name);
	$user_password = trim($user_password);
	$email_password = false;
	if ( !$user_id && empty($user_password) ) {
		$user_password = wp_generate_password( 12, false );
		$message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
		$user_id = wp_create_user($user_name, $user_password, $user_email);
		update_user_option($user_id, 'default_password_nag', true, true);
		$email_password = true;
	} else if ( !$user_id ) {
		// Password has been provided
		$message = '<em>'.__('Your chosen password.').'</em>';
		$user_id = wp_create_user($user_name, $user_password, $user_email);
	} else {
		$message = __('User already exists. Password inherited.');
	}

	$user = new WP_User($user_id);
	$user->set_role('administrator');

	wp_install_defaults($user_id);

	flush_rewrite_rules();

	wp_new_blog_notification($blog_title, $guessurl, $user_id, ($email_password ? $user_password : __('The password you chose during the install.') ) );

	wp_cache_flush();

	do_action( 'wp_install', $user );

	return array('url' => $guessurl, 'user_id' => $user_id, 'password' => $user_password, 'password_message' => $message);
}
endif;

if ( !function_exists('wp_install_defaults') ) :

function wp_install_defaults( $user_id ) {
	global $kvdb, $wp_rewrite, $table_prefix;

	// Default category
	$cat_name = __('Uncategorized');
	/* translators: Default category slug */
	$cat_slug = sanitize_title(_x('Uncategorized', 'Default category slug'));

	if ( global_terms_enabled() ) {
		$cat_id = $kvdb->get_var( $kvdb->prepare( "SELECT cat_ID FROM {$kvdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
		if ( $cat_id == null ) {
			$kvdb->insert( $kvdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
			$cat_id = $kvdb->insert_id;
		}
		update_option('default_category', $cat_id);
	} else {
		$cat_id = 1;
	}

	$kvdb->insert( $kvdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
	$kvdb->insert( $kvdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
	$cat_tt_id = $kvdb->insert_id;

	// First post
	$now = date('Y-m-d H:i:s');
	$now_gmt = gmdate('Y-m-d H:i:s');
	$first_post_guid = get_option('home') . '/?p=1';

	if ( is_multisite() ) {
		$first_post = get_site_option( 'first_post' );

		if ( empty($first_post) )
			$first_post = __( 'Welcome to <a href="SITE_URL">SITE_NAME</a>. This is your first post. Edit or delete it, then start blogging!' );

		$first_post = str_replace( "SITE_URL", esc_url( network_home_url() ), $first_post );
		$first_post = str_replace( "SITE_NAME", get_current_site()->site_name, $first_post );
	} else {
		$first_post = __('Welcome to WordPress. This is your first post. Edit or delete it, then start blogging!');
	}

	$kvdb->insert( $kvdb->posts, array(
								'post_author' => $user_id,
								'post_date' => $now,
								'post_date_gmt' => $now_gmt,
								'post_content' => $first_post,
								'post_excerpt' => '',
								'post_title' => __('Hello world!'),
								/* translators: Default post slug */
								'post_name' => sanitize_title( _x('hello-world', 'Default post slug') ),
								'post_modified' => $now,
								'post_modified_gmt' => $now_gmt,
								'guid' => $first_post_guid,
								'comment_count' => 1,
								'to_ping' => '',
								'pinged' => '',
								'post_content_filtered' => ''
								));
	$kvdb->insert( $kvdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => 1) );

	// Default comment
	$first_comment_author = __('Mr WordPress');
	$first_comment_url = 'https://wordpress.org/';
	$first_comment = __('Hi, this is a comment.
To delete a comment, just log in and view the post&#039;s comments. There you will have the option to edit or delete them.');
	if ( is_multisite() ) {
		$first_comment_author = get_site_option( 'first_comment_author', $first_comment_author );
		$first_comment_url = get_site_option( 'first_comment_url', network_home_url() );
		$first_comment = get_site_option( 'first_comment', $first_comment );
	}
	$kvdb->insert( $kvdb->comments, array(
								'comment_post_ID' => 1,
								'comment_author' => $first_comment_author,
								'comment_author_email' => '',
								'comment_author_url' => $first_comment_url,
								'comment_date' => $now,
								'comment_date_gmt' => $now_gmt,
								'comment_content' => $first_comment
								));

	// First Page
	$first_page = sprintf( __( "This is an example page. It's different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:

<blockquote>Hi there! I'm a bike messenger by day, aspiring actor by night, and this is my blog. I live in Los Angeles, have a great dog named Jack, and I like pi&#241;a coladas. (And gettin' caught in the rain.)</blockquote>

...or something like this:

<blockquote>The XYZ Doohickey Company was founded in 1971, and has been providing quality doohickeys to the public ever since. Located in Gotham City, XYZ employs over 2,000 people and does all kinds of awesome things for the Gotham community.</blockquote>

As a new WordPress user, you should go to <a href=\"%s\">your dashboard</a> to delete this page and create new pages for your content. Have fun!" ), admin_url() );
	if ( is_multisite() )
		$first_page = get_site_option( 'first_page', $first_page );
	$first_post_guid = get_option('home') . '/?page_id=2';
	$kvdb->insert( $kvdb->posts, array(
								'post_author' => $user_id,
								'post_date' => $now,
								'post_date_gmt' => $now_gmt,
								'post_content' => $first_page,
								'post_excerpt' => '',
								'post_title' => __( 'Sample Page' ),
								/* translators: Default page slug */
								'post_name' => __( 'sample-page' ),
								'post_modified' => $now,
								'post_modified_gmt' => $now_gmt,
								'guid' => $first_post_guid,
								'post_type' => 'page',
								'to_ping' => '',
								'pinged' => '',
								'post_content_filtered' => ''
								));
	$kvdb->insert( $kvdb->postmeta, array( 'post_id' => 2, 'meta_key' => '_wp_page_template', 'meta_value' => 'default' ) );

	// Set up default widgets for default theme.
	update_option( 'widget_search', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-posts', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-comments', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_archives', array ( 2 => array ( 'title' => '', 'count' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_categories', array ( 2 => array ( 'title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_meta', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'sidebars_widgets', array ( 'wp_inactive_widgets' => array (), 'sidebar-1' => array ( 0 => 'search-2', 1 => 'recent-posts-2', 2 => 'recent-comments-2', 3 => 'archives-2', 4 => 'categories-2', 5 => 'meta-2', ), 'sidebar-2' => array (), 'sidebar-3' => array (), 'array_version' => 3 ) );

	if ( ! is_multisite() )
		update_user_meta( $user_id, 'show_welcome_panel', 1 );
	elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) )
		update_user_meta( $user_id, 'show_welcome_panel', 2 );

	if ( is_multisite() ) {
		// Flush rules to pick up the new page.
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$user = new WP_User($user_id);
		$kvdb->update( $kvdb->options, array('option_value' => $user->user_email), array('option_name' => 'admin_email') );

		// Remove all perms except for the login user.
		$kvdb->query( $kvdb->prepare("DELETE FROM $kvdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
		$kvdb->query( $kvdb->prepare("DELETE FROM $kvdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

		// Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
		if ( !is_super_admin( $user_id ) && $user_id != 1 )
			$kvdb->delete( $kvdb->usermeta, array( 'user_id' => $user_id , 'meta_key' => $kvdb->base_prefix.'1_capabilities' ) );
	}
}
endif;

if ( !function_exists('wp_new_blog_notification') ) :

function wp_new_blog_notification($blog_title, $blog_url, $user_id, $password) {
	$user = new WP_User( $user_id );
	$email = $user->user_email;
	$name = $user->user_login;
	$message = sprintf(__("Your new WordPress site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

Username: %2\$s
Password: %3\$s

We hope you enjoy your new site. Thanks!

--The WordPress Team
https://wordpress.org/
"), $blog_url, $name, $password);

	@wp_mail($email, __('New WordPress Site'), $message);
}
endif;

if ( !function_exists('wp_upgrade') ) :

function wp_upgrade() {
	global $wp_current_db_version, $wp_db_version, $kvdb;

	$wp_current_db_version = __get_option('db_version');

	// We are up-to-date. Nothing to do.
	if ( $wp_db_version == $wp_current_db_version )
		return;

	if ( ! is_blog_installed() )
		return;

	wp_check_mysql_version();
	wp_cache_flush();
	pre_schema_upgrade();
	make_db_current_silent();
	upgrade_all();
	if ( is_multisite() && is_main_site() )
		upgrade_network();
	wp_cache_flush();

	if ( is_multisite() ) {
		if ( $kvdb->get_row( "SELECT blog_id FROM {$kvdb->blog_versions} WHERE blog_id = '{$kvdb->blogid}'" ) )
			$kvdb->query( "UPDATE {$kvdb->blog_versions} SET db_version = '{$wp_db_version}' WHERE blog_id = '{$kvdb->blogid}'" );
		else
			$kvdb->query( "INSERT INTO {$kvdb->blog_versions} ( `blog_id` , `db_version` , `last_updated` ) VALUES ( '{$kvdb->blogid}', '{$wp_db_version}', NOW());" );
	}


	do_action( 'wp_upgrade', $wp_db_version, $wp_current_db_version );
}
endif;

function upgrade_all() {
	global $wp_current_db_version, $wp_db_version;
	$wp_current_db_version = __get_option('db_version');

	// We are up-to-date. Nothing to do.
	if ( $wp_db_version == $wp_current_db_version )
		return;

	// If the version is not set in the DB, try to guess the version.
	if ( empty($wp_current_db_version) ) {
		$wp_current_db_version = 0;

		// If the template option exists, we have 1.5.
		$template = __get_option('template');
		if ( !empty($template) )
			$wp_current_db_version = 2541;
	}

	if ( $wp_current_db_version < 6039 )
		upgrade_230_options_table();

	populate_options();

	if ( $wp_current_db_version < 2541 ) {
		upgrade_100();
		upgrade_101();
		upgrade_110();
		upgrade_130();
	}

	if ( $wp_current_db_version < 3308 )
		upgrade_160();

	if ( $wp_current_db_version < 4772 )
		upgrade_210();

	if ( $wp_current_db_version < 4351 )
		upgrade_old_slugs();

	if ( $wp_current_db_version < 5539 )
		upgrade_230();

	if ( $wp_current_db_version < 6124 )
		upgrade_230_old_tables();

	if ( $wp_current_db_version < 7499 )
		upgrade_250();

	if ( $wp_current_db_version < 7935 )
		upgrade_252();

	if ( $wp_current_db_version < 8201 )
		upgrade_260();

	if ( $wp_current_db_version < 8989 )
		upgrade_270();

	if ( $wp_current_db_version < 10360 )
		upgrade_280();

	if ( $wp_current_db_version < 11958 )
		upgrade_290();

	if ( $wp_current_db_version < 15260 )
		upgrade_300();

	if ( $wp_current_db_version < 19389 )
		upgrade_330();

	if ( $wp_current_db_version < 20080 )
		upgrade_340();

	if ( $wp_current_db_version < 22422 )
		upgrade_350();

	if ( $wp_current_db_version < 25824 )
		upgrade_370();

	if ( $wp_current_db_version < 26148 )
		upgrade_372();

	if ( $wp_current_db_version < 26691 )
		upgrade_380();

	maybe_disable_link_manager();

	maybe_disable_automattic_widgets();

	update_option( 'db_version', $wp_db_version );
	update_option( 'db_upgraded', true );
}

function upgrade_100() {
	global $kvdb;

	// Get the title and ID of every post, post_name to check if it already has a value
	$posts = $kvdb->get_results("SELECT ID, post_title, post_name FROM $kvdb->posts WHERE post_name = ''");
	if ($posts) {
		foreach($posts as $post) {
			if ('' == $post->post_name) {
				$newtitle = sanitize_title($post->post_title);
				$kvdb->query( $kvdb->prepare("UPDATE $kvdb->posts SET post_name = %s WHERE ID = %d", $newtitle, $post->ID) );
			}
		}
	}

	$categories = $kvdb->get_results("SELECT cat_ID, cat_name, category_nicename FROM $kvdb->categories");
	foreach ($categories as $category) {
		if ('' == $category->category_nicename) {
			$newtitle = sanitize_title($category->cat_name);
			$kvdb->update( $kvdb->categories, array('category_nicename' => $newtitle), array('cat_ID' => $category->cat_ID) );
		}
	}

	$kvdb->query("UPDATE $kvdb->options SET option_value = REPLACE(option_value, 'wp-links/links-images/', 'wp-images/links/')
	WHERE option_name LIKE 'links_rating_image%'
	AND option_value LIKE 'wp-links/links-images/%'");

	$done_ids = $kvdb->get_results("SELECT DISTINCT post_id FROM $kvdb->post2cat");
	if ($done_ids) :
		foreach ($done_ids as $done_id) :
			$done_posts[] = $done_id->post_id;
		endforeach;
		$catwhere = ' AND ID NOT IN (' . implode(',', $done_posts) . ')';
	else:
		$catwhere = '';
	endif;

	$allposts = $kvdb->get_results("SELECT ID, post_category FROM $kvdb->posts WHERE post_category != '0' $catwhere");
	if ($allposts) :
		foreach ($allposts as $post) {
			// Check to see if it's already been imported
			$cat = $kvdb->get_row( $kvdb->prepare("SELECT * FROM $kvdb->post2cat WHERE post_id = %d AND category_id = %d", $post->ID, $post->post_category) );
			if (!$cat && 0 != $post->post_category) { // If there's no result
				$kvdb->insert( $kvdb->post2cat, array('post_id' => $post->ID, 'category_id' => $post->post_category) );
			}
		}
	endif;
}

function upgrade_101() {
	global $kvdb;

	// Clean up indices, add a few
	add_clean_index($kvdb->posts, 'post_name');
	add_clean_index($kvdb->posts, 'post_status');
	add_clean_index($kvdb->categories, 'category_nicename');
	add_clean_index($kvdb->comments, 'comment_approved');
	add_clean_index($kvdb->comments, 'comment_post_ID');
	add_clean_index($kvdb->links , 'link_category');
	add_clean_index($kvdb->links , 'link_visible');
}

function upgrade_110() {
	global $kvdb;

	// Set user_nicename.
	$users = $kvdb->get_results("SELECT ID, user_nickname, user_nicename FROM $kvdb->users");
	foreach ($users as $user) {
		if ('' == $user->user_nicename) {
			$newname = sanitize_title($user->user_nickname);
			$kvdb->update( $kvdb->users, array('user_nicename' => $newname), array('ID' => $user->ID) );
		}
	}

	$users = $kvdb->get_results("SELECT ID, user_pass from $kvdb->users");
	foreach ($users as $row) {
		if (!preg_match('/^[A-Fa-f0-9]{32}$/', $row->user_pass)) {
			$kvdb->update( $kvdb->users, array('user_pass' => md5($row->user_pass)), array('ID' => $row->ID) );
		}
	}

	// Get the GMT offset, we'll use that later on
	$all_options = get_alloptions_110();

	$time_difference = $all_options->time_difference;

		$server_time = time()+date('Z');
	$weblogger_time = $server_time + $time_difference * HOUR_IN_SECONDS;
	$gmt_time = time();

	$diff_gmt_server = ($gmt_time - $server_time) / HOUR_IN_SECONDS;
	$diff_weblogger_server = ($weblogger_time - $server_time) / HOUR_IN_SECONDS;
	$diff_gmt_weblogger = $diff_gmt_server - $diff_weblogger_server;
	$gmt_offset = -$diff_gmt_weblogger;

	// Add a gmt_offset option, with value $gmt_offset
	add_option('gmt_offset', $gmt_offset);
	
	$got_gmt_fields = ! ($kvdb->get_var("SELECT MAX(post_date_gmt) FROM $kvdb->posts") == '0000-00-00 00:00:00');

	if (!$got_gmt_fields) {

		// Add or subtract time to all dates, to get GMT dates
		$add_hours = intval($diff_gmt_weblogger);
		$add_minutes = intval(60 * ($diff_gmt_weblogger - $add_hours));
		$kvdb->query("UPDATE $kvdb->posts SET post_date_gmt = DATE_ADD(post_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
		$kvdb->query("UPDATE $kvdb->posts SET post_modified = post_date");
		$kvdb->query("UPDATE $kvdb->posts SET post_modified_gmt = DATE_ADD(post_modified, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE) WHERE post_modified != '0000-00-00 00:00:00'");
		$kvdb->query("UPDATE $kvdb->comments SET comment_date_gmt = DATE_ADD(comment_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
		$kvdb->query("UPDATE $kvdb->users SET user_registered = DATE_ADD(user_registered, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
	}

}

function upgrade_130() {
	global $kvdb;

	$posts = $kvdb->get_results("SELECT ID, post_title, post_content, post_excerpt, guid, post_date, post_name, post_status, post_author FROM $kvdb->posts");
	if ($posts) {
		foreach($posts as $post) {
			$post_content = addslashes(deslash($post->post_content));
			$post_title = addslashes(deslash($post->post_title));
			$post_excerpt = addslashes(deslash($post->post_excerpt));
			if ( empty($post->guid) )
				$guid = get_permalink($post->ID);
			else
				$guid = $post->guid;

			$kvdb->update( $kvdb->posts, compact('post_title', 'post_content', 'post_excerpt', 'guid'), array('ID' => $post->ID) );

		}
	}

	// Remove extraneous backslashes.
	$comments = $kvdb->get_results("SELECT comment_ID, comment_author, comment_content FROM $kvdb->comments");
	if ($comments) {
		foreach($comments as $comment) {
			$comment_content = deslash($comment->comment_content);
			$comment_author = deslash($comment->comment_author);

			$kvdb->update($kvdb->comments, compact('comment_content', 'comment_author'), array('comment_ID' => $comment->comment_ID) );
		}
	}

	// Remove extraneous backslashes.
	$links = $kvdb->get_results("SELECT link_id, link_name, link_description FROM $kvdb->links");
	if ($links) {
		foreach($links as $link) {
			$link_name = deslash($link->link_name);
			$link_description = deslash($link->link_description);

			$kvdb->update( $kvdb->links, compact('link_name', 'link_description'), array('link_id' => $link->link_id) );
		}
	}

	$active_plugins = __get_option('active_plugins');

	// If plugins are not stored in an array, they're stored in the old
	// newline separated format. Convert to new format.
	if ( !is_array( $active_plugins ) ) {
		$active_plugins = explode("\n", trim($active_plugins));
		update_option('active_plugins', $active_plugins);
	}

	// Obsolete tables
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'optionvalues');
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'optiontypes');
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'optiongroups');
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'optiongroup_options');

	// Update comments table to use comment_type
	$kvdb->query("UPDATE $kvdb->comments SET comment_type='trackback', comment_content = REPLACE(comment_content, '<trackback />', '') WHERE comment_content LIKE '<trackback />%'");
	$kvdb->query("UPDATE $kvdb->comments SET comment_type='pingback', comment_content = REPLACE(comment_content, '<pingback />', '') WHERE comment_content LIKE '<pingback />%'");

	// Some versions have multiple duplicate option_name rows with the same values
	$options = $kvdb->get_results("SELECT option_name, COUNT(option_name) AS dupes FROM `$kvdb->options` GROUP BY option_name");
	foreach ( $options as $option ) {
		if ( 1 != $option->dupes ) { // Could this be done in the query?
			$limit = $option->dupes - 1;
			$dupe_ids = $kvdb->get_col( $kvdb->prepare("SELECT option_id FROM $kvdb->options WHERE option_name = %s LIMIT %d", $option->option_name, $limit) );
			if ( $dupe_ids ) {
				$dupe_ids = join($dupe_ids, ',');
				$kvdb->query("DELETE FROM $kvdb->options WHERE option_id IN ($dupe_ids)");
			}
		}
	}

	make_site_theme();
}

function upgrade_160() {
	global $kvdb, $wp_current_db_version;

	populate_roles_160();

	$users = $kvdb->get_results("SELECT * FROM $kvdb->users");
	foreach ( $users as $user ) :
		if ( !empty( $user->user_firstname ) )
			update_user_meta( $user->ID, 'first_name', wp_slash($user->user_firstname) );
		if ( !empty( $user->user_lastname ) )
			update_user_meta( $user->ID, 'last_name', wp_slash($user->user_lastname) );
		if ( !empty( $user->user_nickname ) )
			update_user_meta( $user->ID, 'nickname', wp_slash($user->user_nickname) );
		if ( !empty( $user->user_level ) )
			update_user_meta( $user->ID, $kvdb->prefix . 'user_level', $user->user_level );
		if ( !empty( $user->user_icq ) )
			update_user_meta( $user->ID, 'icq', wp_slash($user->user_icq) );
		if ( !empty( $user->user_aim ) )
			update_user_meta( $user->ID, 'aim', wp_slash($user->user_aim) );
		if ( !empty( $user->user_msn ) )
			update_user_meta( $user->ID, 'msn', wp_slash($user->user_msn) );
		if ( !empty( $user->user_yim ) )
			update_user_meta( $user->ID, 'yim', wp_slash($user->user_icq) );
		if ( !empty( $user->user_description ) )
			update_user_meta( $user->ID, 'description', wp_slash($user->user_description) );

		if ( isset( $user->user_idmode ) ):
			$idmode = $user->user_idmode;
			if ($idmode == 'nickname') $id = $user->user_nickname;
			if ($idmode == 'login') $id = $user->user_login;
			if ($idmode == 'firstname') $id = $user->user_firstname;
			if ($idmode == 'lastname') $id = $user->user_lastname;
			if ($idmode == 'namefl') $id = $user->user_firstname.' '.$user->user_lastname;
			if ($idmode == 'namelf') $id = $user->user_lastname.' '.$user->user_firstname;
			if (!$idmode) $id = $user->user_nickname;
			$kvdb->update( $kvdb->users, array('display_name' => $id), array('ID' => $user->ID) );
		endif;

		// FIXME: RESET_CAPS is temporary code to reset roles and caps if flag is set.
		$caps = get_user_meta( $user->ID, $kvdb->prefix . 'capabilities');
		if ( empty($caps) || defined('RESET_CAPS') ) {
			$level = get_user_meta($user->ID, $kvdb->prefix . 'user_level', true);
			$role = translate_level_to_role($level);
			update_user_meta( $user->ID, $kvdb->prefix . 'capabilities', array($role => true) );
		}

	endforeach;
	$old_user_fields = array( 'user_firstname', 'user_lastname', 'user_icq', 'user_aim', 'user_msn', 'user_yim', 'user_idmode', 'user_ip', 'user_domain', 'user_browser', 'user_description', 'user_nickname', 'user_level' );
	$kvdb->hide_errors();
	foreach ( $old_user_fields as $old )
		$kvdb->query("ALTER TABLE $kvdb->users DROP $old");
	$kvdb->show_errors();

	// populate comment_count field of posts table
	$comments = $kvdb->get_results( "SELECT comment_post_ID, COUNT(*) as c FROM $kvdb->comments WHERE comment_approved = '1' GROUP BY comment_post_ID" );
	if ( is_array( $comments ) )
		foreach ($comments as $comment)
			$kvdb->update( $kvdb->posts, array('comment_count' => $comment->c), array('ID' => $comment->comment_post_ID) );

	// Some alpha versions used a post status of object instead of attachment and put
	// the mime type in post_type instead of post_mime_type.
	if ( $wp_current_db_version > 2541 && $wp_current_db_version <= 3091 ) {
		$objects = $kvdb->get_results("SELECT ID, post_type FROM $kvdb->posts WHERE post_status = 'object'");
		foreach ($objects as $object) {
			$kvdb->update( $kvdb->posts, array(	'post_status' => 'attachment',
												'post_mime_type' => $object->post_type,
												'post_type' => ''),
										 array( 'ID' => $object->ID ) );

			$meta = get_post_meta($object->ID, 'imagedata', true);
			if ( ! empty($meta['file']) )
				update_attached_file( $object->ID, $meta['file'] );
		}
	}
}

function upgrade_210() {
	global $kvdb, $wp_current_db_version;

	if ( $wp_current_db_version < 3506 ) {
		// Update status and type.
		$posts = $kvdb->get_results("SELECT ID, post_status FROM $kvdb->posts");

		if ( ! empty($posts) ) foreach ($posts as $post) {
			$status = $post->post_status;
			$type = 'post';

			if ( 'static' == $status ) {
				$status = 'publish';
				$type = 'page';
			} else if ( 'attachment' == $status ) {
				$status = 'inherit';
				$type = 'attachment';
			}

			$kvdb->query( $kvdb->prepare("UPDATE $kvdb->posts SET post_status = %s, post_type = %s WHERE ID = %d", $status, $type, $post->ID) );
		}
	}

	if ( $wp_current_db_version < 3845 ) {
		populate_roles_210();
	}

	if ( $wp_current_db_version < 3531 ) {
		// Give future posts a post_status of future.
		$now = gmdate('Y-m-d H:i:59');
		$kvdb->query ("UPDATE $kvdb->posts SET post_status = 'future' WHERE post_status = 'publish' AND post_date_gmt > '$now'");

		$posts = $kvdb->get_results("SELECT ID, post_date FROM $kvdb->posts WHERE post_status ='future'");
		if ( !empty($posts) )
			foreach ( $posts as $post )
				wp_schedule_single_event(mysql2date('U', $post->post_date, false), 'publish_future_post', array($post->ID));
	}
}

function upgrade_230() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version < 5200 ) {
		populate_roles_230();
	}

	// Convert categories to terms.
	$tt_ids = array();
	$have_tags = false;
	$categories = $kvdb->get_results("SELECT * FROM $kvdb->categories ORDER BY cat_ID");
	foreach ($categories as $category) {
		$term_id = (int) $category->cat_ID;
		$name = $category->cat_name;
		$description = $category->category_description;
		$slug = $category->category_nicename;
		$parent = $category->category_parent;
		$term_group = 0;

		// Associate terms with the same slug in a term group and make slugs unique.
		if ( $exists = $kvdb->get_results( $kvdb->prepare("SELECT term_id, term_group FROM $kvdb->terms WHERE slug = %s", $slug) ) ) {
			$term_group = $exists[0]->term_group;
			$id = $exists[0]->term_id;
			$num = 2;
			do {
				$alt_slug = $slug . "-$num";
				$num++;
				$slug_check = $kvdb->get_var( $kvdb->prepare("SELECT slug FROM $kvdb->terms WHERE slug = %s", $alt_slug) );
			} while ( $slug_check );

			$slug = $alt_slug;

			if ( empty( $term_group ) ) {
				$term_group = $kvdb->get_var("SELECT MAX(term_group) FROM $kvdb->terms GROUP BY term_group") + 1;
				$kvdb->query( $kvdb->prepare("UPDATE $kvdb->terms SET term_group = %d WHERE term_id = %d", $term_group, $id) );
			}
		}

		$kvdb->query( $kvdb->prepare("INSERT INTO $kvdb->terms (term_id, name, slug, term_group) VALUES
		(%d, %s, %s, %d)", $term_id, $name, $slug, $term_group) );

		$count = 0;
		if ( !empty($category->category_count) ) {
			$count = (int) $category->category_count;
			$taxonomy = 'category';
			$kvdb->query( $kvdb->prepare("INSERT INTO $kvdb->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count) );
			$tt_ids[$term_id][$taxonomy] = (int) $kvdb->insert_id;
		}

		if ( !empty($category->link_count) ) {
			$count = (int) $category->link_count;
			$taxonomy = 'link_category';
			$kvdb->query( $kvdb->prepare("INSERT INTO $kvdb->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count) );
			$tt_ids[$term_id][$taxonomy] = (int) $kvdb->insert_id;
		}

		if ( !empty($category->tag_count) ) {
			$have_tags = true;
			$count = (int) $category->tag_count;
			$taxonomy = 'post_tag';
			$kvdb->insert( $kvdb->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count') );
			$tt_ids[$term_id][$taxonomy] = (int) $kvdb->insert_id;
		}

		if ( empty($count) ) {
			$count = 0;
			$taxonomy = 'category';
			$kvdb->insert( $kvdb->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count') );
			$tt_ids[$term_id][$taxonomy] = (int) $kvdb->insert_id;
		}
	}

	$select = 'post_id, category_id';
	if ( $have_tags )
		$select .= ', rel_type';

	$posts = $kvdb->get_results("SELECT $select FROM $kvdb->post2cat GROUP BY post_id, category_id");
	foreach ( $posts as $post ) {
		$post_id = (int) $post->post_id;
		$term_id = (int) $post->category_id;
		$taxonomy = 'category';
		if ( !empty($post->rel_type) && 'tag' == $post->rel_type)
			$taxonomy = 'tag';
		$tt_id = $tt_ids[$term_id][$taxonomy];
		if ( empty($tt_id) )
			continue;

		$kvdb->insert( $kvdb->term_relationships, array('object_id' => $post_id, 'term_taxonomy_id' => $tt_id) );
	}
	
	if ( $wp_current_db_version < 3570 ) {
		
		$link_cat_id_map = array();
		$default_link_cat = 0;
		$tt_ids = array();
		$link_cats = $kvdb->get_results("SELECT cat_id, cat_name FROM " . $kvdb->prefix . 'linkcategories');
		foreach ( $link_cats as $category) {
			$cat_id = (int) $category->cat_id;
			$term_id = 0;
			$name = wp_slash($category->cat_name);
			$slug = sanitize_title($name);
			$term_group = 0;

			// Associate terms with the same slug in a term group and make slugs unique.
			if ( $exists = $kvdb->get_results( $kvdb->prepare("SELECT term_id, term_group FROM $kvdb->terms WHERE slug = %s", $slug) ) ) {
				$term_group = $exists[0]->term_group;
				$term_id = $exists[0]->term_id;
			}

			if ( empty($term_id) ) {
				$kvdb->insert( $kvdb->terms, compact('name', 'slug', 'term_group') );
				$term_id = (int) $kvdb->insert_id;
			}

			$link_cat_id_map[$cat_id] = $term_id;
			$default_link_cat = $term_id;

			$kvdb->insert( $kvdb->term_taxonomy, array('term_id' => $term_id, 'taxonomy' => 'link_category', 'description' => '', 'parent' => 0, 'count' => 0) );
			$tt_ids[$term_id] = (int) $kvdb->insert_id;
		}

		// Associate links to cats.
		$links = $kvdb->get_results("SELECT link_id, link_category FROM $kvdb->links");
		if ( !empty($links) ) foreach ( $links as $link ) {
			if ( 0 == $link->link_category )
				continue;
			if ( ! isset($link_cat_id_map[$link->link_category]) )
				continue;
			$term_id = $link_cat_id_map[$link->link_category];
			$tt_id = $tt_ids[$term_id];
			if ( empty($tt_id) )
				continue;

			$kvdb->insert( $kvdb->term_relationships, array('object_id' => $link->link_id, 'term_taxonomy_id' => $tt_id) );
		}

		// Set default to the last category we grabbed during the upgrade loop.
		update_option('default_link_category', $default_link_cat);
	} else {
		$links = $kvdb->get_results("SELECT link_id, category_id FROM $kvdb->link2cat GROUP BY link_id, category_id");
		foreach ( $links as $link ) {
			$link_id = (int) $link->link_id;
			$term_id = (int) $link->category_id;
			$taxonomy = 'link_category';
			$tt_id = $tt_ids[$term_id][$taxonomy];
			if ( empty($tt_id) )
				continue;
			$kvdb->insert( $kvdb->term_relationships, array('object_id' => $link_id, 'term_taxonomy_id' => $tt_id) );
		}
	}

	if ( $wp_current_db_version < 4772 ) {
		// Obsolete linkcategories table
		$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'linkcategories');
	}

	$terms = $kvdb->get_results("SELECT term_taxonomy_id, taxonomy FROM $kvdb->term_taxonomy");
	foreach ( (array) $terms as $term ) {
		if ( ('post_tag' == $term->taxonomy) || ('category' == $term->taxonomy) )
			$count = $kvdb->get_var( $kvdb->prepare("SELECT COUNT(*) FROM $kvdb->term_relationships, $kvdb->posts WHERE $kvdb->posts.ID = $kvdb->term_relationships.object_id AND post_status = 'publish' AND post_type = 'post' AND term_taxonomy_id = %d", $term->term_taxonomy_id) );
		else
			$count = $kvdb->get_var( $kvdb->prepare("SELECT COUNT(*) FROM $kvdb->term_relationships WHERE term_taxonomy_id = %d", $term->term_taxonomy_id) );
		$kvdb->update( $kvdb->term_taxonomy, array('count' => $count), array('term_taxonomy_id' => $term->term_taxonomy_id) );
	}
}


function upgrade_230_options_table() {
	global $kvdb;
	$old_options_fields = array( 'option_can_override', 'option_type', 'option_width', 'option_height', 'option_description', 'option_admin_level' );
	$kvdb->hide_errors();
	foreach ( $old_options_fields as $old )
		$kvdb->query("ALTER TABLE $kvdb->options DROP $old");
	$kvdb->show_errors();
}

function upgrade_230_old_tables() {
	global $kvdb;
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'categories');
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'link2cat');
	$kvdb->query('DROP TABLE IF EXISTS ' . $kvdb->prefix . 'post2cat');
}

function upgrade_old_slugs() {
	// upgrade people who were using the Redirect Old Slugs plugin
	global $kvdb;
	$kvdb->query("UPDATE $kvdb->postmeta SET meta_key = '_wp_old_slug' WHERE meta_key = 'old_slug'");
}

function upgrade_250() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 6689 ) {
		populate_roles_250();
	}

}

function upgrade_252() {
	global $kvdb;

	$kvdb->query("UPDATE $kvdb->users SET user_activation_key = ''");
}

function upgrade_260() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 8000 )
		populate_roles_260();
}

function upgrade_270() {
	global $kvdb, $wp_current_db_version;

	if ( $wp_current_db_version < 8980 )
		populate_roles_270();

	// Update post_date for unpublished posts with empty timestamp
	if ( $wp_current_db_version < 8921 )
		$kvdb->query( "UPDATE $kvdb->posts SET post_date = post_modified WHERE post_date = '0000-00-00 00:00:00'" );
}

function upgrade_280() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version < 10360 )
		populate_roles_280();
	if ( is_multisite() ) {
		$start = 0;
		while( $rows = $kvdb->get_results( "SELECT option_name, option_value FROM $kvdb->options ORDER BY option_id LIMIT $start, 20" ) ) {
			foreach( $rows as $row ) {
				$value = $row->option_value;
				if ( !@unserialize( $value ) )
					$value = stripslashes( $value );
				if ( $value !== $row->option_value ) {
					update_option( $row->option_name, $value );
				}
			}
			$start += 20;
		}
		refresh_blog_details( $kvdb->blogid );
	}
}

function upgrade_290() {
	global $wp_current_db_version;

	if ( $wp_current_db_version < 11958 ) {
		// Previously, setting depth to 1 would redundantly disable threading, but now 2 is the minimum depth to avoid confusion
		if ( get_option( 'thread_comments_depth' ) == '1' ) {
			update_option( 'thread_comments_depth', 2 );
			update_option( 'thread_comments', 0 );
		}
	}
}

function upgrade_300() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version < 15093 )
		populate_roles_300();

	if ( $wp_current_db_version < 14139 && is_multisite() && is_main_site() && ! defined( 'MULTISITE' ) && get_site_option( 'siteurl' ) === false )
		add_site_option( 'siteurl', '' );

	// 3.0 screen options key name changes.
	if ( is_main_site() && !defined('DO_NOT_UPGRADE_GLOBAL_TABLES') ) {
		$prefix = like_escape($kvdb->base_prefix);
		$kvdb->query( "DELETE FROM $kvdb->usermeta WHERE meta_key LIKE '{$prefix}%meta-box-hidden%' OR meta_key LIKE '{$prefix}%closedpostboxes%' OR meta_key LIKE '{$prefix}%manage-%-columns-hidden%' OR meta_key LIKE '{$prefix}%meta-box-order%' OR meta_key LIKE '{$prefix}%metaboxorder%' OR meta_key LIKE '{$prefix}%screen_layout%'
					 OR meta_key = 'manageedittagscolumnshidden' OR meta_key='managecategoriescolumnshidden' OR meta_key = 'manageedit-tagscolumnshidden' OR meta_key = 'manageeditcolumnshidden' OR meta_key = 'categories_per_page' OR meta_key = 'edit_tags_per_page'" );
	}

}

function upgrade_330() {
	global $wp_current_db_version, $kvdb, $wp_registered_widgets, $sidebars_widgets;

	if ( $wp_current_db_version < 19061 && is_main_site() && ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
		$kvdb->query( "DELETE FROM $kvdb->usermeta WHERE meta_key IN ('show_admin_bar_admin', 'plugins_last_view')" );
	}

	if ( $wp_current_db_version >= 11548 )
		return;

	$sidebars_widgets = get_option( 'sidebars_widgets', array() );
	$_sidebars_widgets = array();

	if ( isset($sidebars_widgets['wp_inactive_widgets']) || empty($sidebars_widgets) )
		$sidebars_widgets['array_version'] = 3;
	elseif ( !isset($sidebars_widgets['array_version']) )
		$sidebars_widgets['array_version'] = 1;

	switch ( $sidebars_widgets['array_version'] ) {
		case 1 :
			foreach ( (array) $sidebars_widgets as $index => $sidebar )
			if ( is_array($sidebar) )
			foreach ( (array) $sidebar as $i => $name ) {
				$id = strtolower($name);
				if ( isset($wp_registered_widgets[$id]) ) {
					$_sidebars_widgets[$index][$i] = $id;
					continue;
				}
				$id = sanitize_title($name);
				if ( isset($wp_registered_widgets[$id]) ) {
					$_sidebars_widgets[$index][$i] = $id;
					continue;
				}

				$found = false;

				foreach ( $wp_registered_widgets as $widget_id => $widget ) {
					if ( strtolower($widget['name']) == strtolower($name) ) {
						$_sidebars_widgets[$index][$i] = $widget['id'];
						$found = true;
						break;
					} elseif ( sanitize_title($widget['name']) == sanitize_title($name) ) {
						$_sidebars_widgets[$index][$i] = $widget['id'];
						$found = true;
						break;
					}
				}

				if ( $found )
					continue;

				unset($_sidebars_widgets[$index][$i]);
			}
			$_sidebars_widgets['array_version'] = 2;
			$sidebars_widgets = $_sidebars_widgets;
			unset($_sidebars_widgets);

		case 2 :
			$sidebars_widgets = retrieve_widgets();
			$sidebars_widgets['array_version'] = 3;
			update_option( 'sidebars_widgets', $sidebars_widgets );
	}
}

function upgrade_340() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version < 19798 ) {
		$kvdb->hide_errors();
		$kvdb->query( "ALTER TABLE $kvdb->options DROP COLUMN blog_id" );
		$kvdb->show_errors();
	}

	if ( $wp_current_db_version < 19799 ) {
		$kvdb->hide_errors();
		$kvdb->query("ALTER TABLE $kvdb->comments DROP INDEX comment_approved");
		$kvdb->show_errors();
	}

	if ( $wp_current_db_version < 20022 && is_main_site() && ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
		$kvdb->query( "DELETE FROM $kvdb->usermeta WHERE meta_key = 'themes_last_view'" );
	}

	if ( $wp_current_db_version < 20080 ) {
		if ( 'yes' == $kvdb->get_var( "SELECT autoload FROM $kvdb->options WHERE option_name = 'uninstall_plugins'" ) ) {
			$uninstall_plugins = get_option( 'uninstall_plugins' );
			delete_option( 'uninstall_plugins' );
			add_option( 'uninstall_plugins', $uninstall_plugins, null, 'no' );
		}
	}
}

function upgrade_350() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version < 22006 && $kvdb->get_var( "SELECT link_id FROM $kvdb->links LIMIT 1" ) )
		update_option( 'link_manager_enabled', 1 ); // Previously set to 0 by populate_options()

	if ( $wp_current_db_version < 21811 && is_main_site() && ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
		$meta_keys = array();
		foreach ( array_merge( get_post_types(), get_taxonomies() ) as $name ) {
			if ( false !== strpos( $name, '-' ) )
			$meta_keys[] = 'edit_' . str_replace( '-', '_', $name ) . '_per_page';
		}
		if ( $meta_keys ) {
			$meta_keys = implode( "', '", $meta_keys );
			$kvdb->query( "DELETE FROM $kvdb->usermeta WHERE meta_key IN ('$meta_keys')" );
		}
	}

	if ( $wp_current_db_version < 22422 && $term = get_term_by( 'slug', 'post-format-standard', 'post_format' ) )
		wp_delete_term( $term->term_id, 'post_format' );
}

function upgrade_370() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 25824 )
		wp_clear_scheduled_hook( 'wp_auto_updates_maybe_update' );
}

function upgrade_372() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 26148 )
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
}

function upgrade_380() {
	global $wp_current_db_version;
	if ( $wp_current_db_version < 26691 ) {
		deactivate_plugins( array( 'mp6/mp6.php' ), true );
	}
}

function upgrade_network() {
	global $wp_current_db_version, $kvdb;
	
	if ( is_main_network() ) {
		
		$time = time();
		$kvdb->query("DELETE a, b FROM $kvdb->sitemeta a, $kvdb->sitemeta b WHERE
			a.meta_key LIKE '\_site\_transient\_%' AND
			a.meta_key NOT LIKE '\_site\_transient\_timeout\_%' AND
			b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )
			AND b.meta_value < $time");
	}

	// 2.8
	if ( $wp_current_db_version < 11549 ) {
		$wpmu_sitewide_plugins = get_site_option( 'wpmu_sitewide_plugins' );
		$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( $wpmu_sitewide_plugins ) {
			if ( !$active_sitewide_plugins )
				$sitewide_plugins = (array) $wpmu_sitewide_plugins;
			else
				$sitewide_plugins = array_merge( (array) $active_sitewide_plugins, (array) $wpmu_sitewide_plugins );

			update_site_option( 'active_sitewide_plugins', $sitewide_plugins );
		}
		delete_site_option( 'wpmu_sitewide_plugins' );
		delete_site_option( 'deactivated_sitewide_plugins' );

		$start = 0;
		while( $rows = $kvdb->get_results( "SELECT meta_key, meta_value FROM {$kvdb->sitemeta} ORDER BY meta_id LIMIT $start, 20" ) ) {
			foreach( $rows as $row ) {
				$value = $row->meta_value;
				if ( !@unserialize( $value ) )
					$value = stripslashes( $value );
				if ( $value !== $row->meta_value ) {
					update_site_option( $row->meta_key, $value );
				}
			}
			$start += 20;
		}
	}

	// 3.0
	if ( $wp_current_db_version < 13576 )
		update_site_option( 'global_terms_enabled', '1' );

	// 3.3
	if ( $wp_current_db_version < 19390 )
		update_site_option( 'initial_db_version', $wp_current_db_version );

	if ( $wp_current_db_version < 19470 ) {
		if ( false === get_site_option( 'active_sitewide_plugins' ) )
			update_site_option( 'active_sitewide_plugins', array() );
	}

	// 3.4
	if ( $wp_current_db_version < 20148 ) {
		// 'allowedthemes' keys things by stylesheet. 'allowed_themes' keyed things by name.
		$allowedthemes  = get_site_option( 'allowedthemes'  );
		$allowed_themes = get_site_option( 'allowed_themes' );
		if ( false === $allowedthemes && is_array( $allowed_themes ) && $allowed_themes ) {
			$converted = array();
			$themes = wp_get_themes();
			foreach ( $themes as $stylesheet => $theme_data ) {
				if ( isset( $allowed_themes[ $theme_data->get('Name') ] ) )
					$converted[ $stylesheet ] = true;
			}
			update_site_option( 'allowedthemes', $converted );
			delete_site_option( 'allowed_themes' );
		}
	}

	// 3.5
	if ( $wp_current_db_version < 21823 )
		update_site_option( 'ms_files_rewriting', '1' );

	// 3.5.2
	if ( $wp_current_db_version < 24448 ) {
		$illegal_names = get_site_option( 'illegal_names' );
		if ( is_array( $illegal_names ) && count( $illegal_names ) === 1 ) {
			$illegal_name = reset( $illegal_names );
			$illegal_names = explode( ' ', $illegal_name );
			update_site_option( 'illegal_names', $illegal_names );
		}
	}
}


function maybe_create_table($table_name, $create_ddl) {
	global $kvdb;
	if ( $kvdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name )
		return true;
	//didn't find it try to create it.
	$q = $kvdb->query($create_ddl);
	// we cannot directly tell that whether this succeeded!
	if ( $kvdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name )
		return true;
	return false;
}

function drop_index($table, $index) {
	global $kvdb;
	$kvdb->hide_errors();
	$kvdb->query("ALTER TABLE `$table` DROP INDEX `$index`");
	// Now we need to take out all the extra ones we may have created
	for ($i = 0; $i < 25; $i++) {
		$kvdb->query("ALTER TABLE `$table` DROP INDEX `{$index}_$i`");
	}
	$kvdb->show_errors();
	return true;
}

function add_clean_index($table, $index) {
	global $kvdb;
	drop_index($table, $index);
	$kvdb->query("ALTER TABLE `$table` ADD INDEX ( `$index` )");
	return true;
}

function maybe_add_column($table_name, $column_name, $create_ddl) {
	global $kvdb;
	foreach ($kvdb->get_col("DESC $table_name", 0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	//didn't find it try to create it.
	$q = $kvdb->query($create_ddl);
	// we cannot directly tell that whether this succeeded!
	foreach ($kvdb->get_col("DESC $table_name", 0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	return false;
}

function get_alloptions_110() {
	global $kvdb;
	$all_options = new stdClass;
	if ( $options = $kvdb->get_results( "SELECT option_name, option_value FROM $kvdb->options" ) ) {
		foreach ( $options as $option ) {
			if ( 'siteurl' == $option->option_name || 'home' == $option->option_name || 'category_base' == $option->option_name )
				$option->option_value = untrailingslashit( $option->option_value );
			$all_options->{$option->option_name} = stripslashes( $option->option_value );
		}
	}
	return $all_options;
}

function __get_option($setting) {
	global $kvdb;

	if ( $setting == 'home' && defined( 'WP_HOME' ) )
		return untrailingslashit( WP_HOME );

	if ( $setting == 'siteurl' && defined( 'WP_SITEURL' ) )
		return untrailingslashit( WP_SITEURL );

	$option = $kvdb->get_var( $kvdb->prepare("SELECT option_value FROM $kvdb->options WHERE option_name = %s", $setting ) );

	if ( 'home' == $setting && '' == $option )
		return __get_option( 'siteurl' );

	if ( 'siteurl' == $setting || 'home' == $setting || 'category_base' == $setting || 'tag_base' == $setting )
		$option = untrailingslashit( $option );

	return maybe_unserialize( $option );
}

function deslash($content) {
	
	$content = preg_replace("/\\\+'/", "'", $content);

	$content = preg_replace('/\\\+"/', '"', $content);

	$content = preg_replace("/\\\+/", "\\", $content);

	return $content;
}


function dbDelta( $queries = '', $execute = true ) {
	global $kvdb;

	if ( in_array( $queries, array( '', 'all', 'blog', 'global', 'ms_global' ), true ) )
	    $queries = wp_get_db_schema( $queries );

	// Separate individual queries into an array
	if ( !is_array($queries) ) {
		$queries = explode( ';', $queries );
		$queries = array_filter( $queries );
	}
		
	$queries = apply_filters( 'dbdelta_queries', $queries );

	$cqueries = array(); // Creation Queries
	$iqueries = array(); // Insertion Queries
	$for_update = array();

	// Create a tablename index for an array ($cqueries) of queries
	foreach($queries as $qry) {
		if (preg_match("|CREATE TABLE ([^ ]*)|", $qry, $matches)) {
			$cqueries[ trim( $matches[1], '`' ) ] = $qry;
			$for_update[$matches[1]] = 'Created table '.$matches[1];
		} else if (preg_match("|CREATE DATABASE ([^ ]*)|", $qry, $matches)) {
			array_unshift($cqueries, $qry);
		} else if (preg_match("|INSERT INTO ([^ ]*)|", $qry, $matches)) {
			$iqueries[] = $qry;
		} else if (preg_match("|UPDATE ([^ ]*)|", $qry, $matches)) {
			$iqueries[] = $qry;
		} else {
			// Unrecognized query type
		}
	}
		
	$cqueries = apply_filters( 'dbdelta_create_queries', $cqueries );
	
	$iqueries = apply_filters( 'dbdelta_insert_queries', $iqueries );

	$global_tables = $kvdb->tables( 'global' );
	foreach ( $cqueries as $table => $qry ) {
		// Upgrade global tables only for the main site. Don't upgrade at all if DO_NOT_UPGRADE_GLOBAL_TABLES is defined.
		if ( in_array( $table, $global_tables ) && ( !is_main_site() || defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) ) {
			unset( $cqueries[ $table ], $for_update[ $table ] );
			continue;
		}

		// Fetch the table column structure from the database
		$suppress = $kvdb->suppress_errors();
		$tablefields = $kvdb->get_results("DESCRIBE {$table};");
		$kvdb->suppress_errors( $suppress );

		if ( ! $tablefields )
			continue;

		// Clear the field and index arrays
		$cfields = $indices = array();
		// Get all of the field names in the query from between the parens
		preg_match("|\((.*)\)|ms", $qry, $match2);
		$qryline = trim($match2[1]);

		// Separate field lines into an array
		$flds = explode("\n", $qryline);

		foreach ($flds as $fld) {
			// Extract the field name
			preg_match("|^([^ ]*)|", trim($fld), $fvals);
			$fieldname = trim( $fvals[1], '`' );

			// Verify the found field name
			$validfield = true;
			switch (strtolower($fieldname)) {
			case '':
			case 'primary':
			case 'index':
			case 'fulltext':
			case 'unique':
			case 'key':
				$validfield = false;
				$indices[] = trim(trim($fld), ", \n");
				break;
			}
			$fld = trim($fld);

			// If it's a valid field, add it to the field array
			if ($validfield) {
				$cfields[strtolower($fieldname)] = trim($fld, ", \n");
			}
		}

		// For every field in the table
		foreach ($tablefields as $tablefield) {
			// If the table field exists in the field array...
			if (array_key_exists(strtolower($tablefield->Field), $cfields)) {
				// Get the field type from the query
				preg_match("|".$tablefield->Field." ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield->Field)], $matches);
				$fieldtype = $matches[1];

				// Is actual field type different from the field type in query?
				if ($tablefield->Type != $fieldtype) {
					// Add a query to change the column type
					$cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN {$tablefield->Field} " . $cfields[strtolower($tablefield->Field)];
					$for_update[$table.'.'.$tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}";
				}

				// Get the default value from the array
					//echo "{$cfields[strtolower($tablefield->Field)]}<br>";
				if (preg_match("| DEFAULT '(.*?)'|i", $cfields[strtolower($tablefield->Field)], $matches)) {
					$default_value = $matches[1];
					if ($tablefield->Default != $default_value) {
						// Add a query to change the column's default value
						$cqueries[] = "ALTER TABLE {$table} ALTER COLUMN {$tablefield->Field} SET DEFAULT '{$default_value}'";
						$for_update[$table.'.'.$tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
					}
				}

				// Remove the field from the array (so it's not added)
				unset($cfields[strtolower($tablefield->Field)]);
			} else {
				// This field exists in the table, but not in the creation queries?
			}
		}

		// For every remaining field specified for the table
		foreach ($cfields as $fieldname => $fielddef) {
			// Push a query line into $cqueries that adds the field to that table
			$cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
			$for_update[$table.'.'.$fieldname] = 'Added column '.$table.'.'.$fieldname;
		}
	
		$tableindices = $kvdb->get_results("SHOW INDEX FROM {$table};");

		if ($tableindices) {
			// Clear the index array
			unset($index_ary);

			// For every index in the table
			foreach ($tableindices as $tableindex) {
				// Add the index to the index data array
				$keyname = $tableindex->Key_name;
				$index_ary[$keyname]['columns'][] = array('fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part);
				$index_ary[$keyname]['unique'] = ($tableindex->Non_unique == 0)?true:false;
			}

			// For each actual index in the index array
			foreach ($index_ary as $index_name => $index_data) {
				// Build a create string to compare to the query
				$index_string = '';
				if ($index_name == 'PRIMARY') {
					$index_string .= 'PRIMARY ';
				} else if($index_data['unique']) {
					$index_string .= 'UNIQUE ';
				}
				$index_string .= 'KEY ';
				if ($index_name != 'PRIMARY') {
					$index_string .= $index_name;
				}
				$index_columns = '';
				// For each column in the index
				foreach ($index_data['columns'] as $column_data) {
					if ($index_columns != '') $index_columns .= ',';
					// Add the field to the column list string
					$index_columns .= $column_data['fieldname'];
					if ($column_data['subpart'] != '') {
						$index_columns .= '('.$column_data['subpart'].')';
					}
				}
				// Add the column list to the index create string
				$index_string .= ' ('.$index_columns.')';
				if (!(($aindex = array_search($index_string, $indices)) === false)) {				unset($indices[$aindex]);		}
			}
		}

		foreach ( (array) $indices as $index ) {
			
			$cqueries[] = "ALTER TABLE {$table} ADD $index";
			$for_update[] = 'Added index ' . $table . ' ' . $index;
		}		
		unset( $cqueries[ $table ], $for_update[ $table ] );
	}

	$allqueries = array_merge($cqueries, $iqueries);
	if ($execute) {
		foreach ($allqueries as $query) {
			//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">".print_r($query, true)."</pre>\n";
			$kvdb->query($query);
		}
	}
	return $for_update;
}

function make_db_current( $tables = 'all' ) {
	$alterations = dbDelta( $tables );
	echo "<ol>\n";
	foreach($alterations as $alteration) echo "<li>$alteration</li>\n";
	echo "</ol>\n";
}

function make_db_current_silent( $tables = 'all' ) {$alterations = dbDelta( $tables );}

function make_site_theme_from_oldschool($theme_name, $template) {
	$home_path = get_home_path();
	$site_dir = WP_CONTENT_DIR . "/themes/$template";

	if (! file_exists("$home_path/index.php"))
		return false;

	$files = array('index.php' => 'index.php', 'wp-layout.css' => 'style.css', 'wp-comments.php' => 'comments.php', 'wp-comments-popup.php' => 'comments-popup.php');

	foreach ($files as $oldfile => $newfile) {
		if ($oldfile == 'index.php')
			$oldpath = $home_path;
		else
			$oldpath = ABSPATH;

		if ($oldfile == 'index.php') { // Check to make sure it's not a new index
			$index = implode('', file("$oldpath/$oldfile"));
			if (strpos($index, 'WP_USE_THEMES') !== false) {
				if (! @copy(WP_CONTENT_DIR . '/themes/' . WP_DEFAULT_THEME . '/index.php', "$site_dir/$newfile"))
					return false;
				continue; // Don't copy anything
				}
		}

		if (! @copy("$oldpath/$oldfile", "$site_dir/$newfile"))
			return false;

		chmod("$site_dir/$newfile", 0777);

		// Update the blog header include in each file.
		$lines = explode("\n", implode('', file("$site_dir/$newfile")));
		if ($lines) {
			$f = fopen("$site_dir/$newfile", 'w');

			foreach ($lines as $line) {
				if (preg_match('/require.*wp-blog-header/', $line))
					$line = '//' . $line;

				// Update stylesheet references.
				$line = str_replace("<?php echo __get_option('siteurl'); ?>/wp-layout.css", "<?php bloginfo('stylesheet_url'); ?>", $line);

				// Update comments template inclusion.
				$line = str_replace("<?php include(ABSPATH . 'wp-comments.php'); ?>", "<?php comments_template(); ?>", $line);

				fwrite($f, "{$line}\n");
			}
			fclose($f);
		}
	}
	
	$header = "/*\nTheme Name: $theme_name\nTheme URI: " . __get_option('siteurl') . "\nDescription: A theme automatically created by the update.\nVersion: 1.0\nAuthor: Moi\n*/\n";

	$stylelines = file_get_contents("$site_dir/style.css");
	if ($stylelines) {
		$f = fopen("$site_dir/style.css", 'w');

		fwrite($f, $header);
		fwrite($f, $stylelines);
		fclose($f);
	}

	return true;
}

function make_site_theme_from_default($theme_name, $template) {
	$site_dir = WP_CONTENT_DIR . "/themes/$template";
	$default_dir = WP_CONTENT_DIR . '/themes/' . WP_DEFAULT_THEME;

	$theme_dir = @ opendir($default_dir);
	if ($theme_dir) {
		while(($theme_file = readdir( $theme_dir )) !== false) {
			if (is_dir("$default_dir/$theme_file"))
				continue;
			if (! @copy("$default_dir/$theme_file", "$site_dir/$theme_file"))
				return;
			chmod("$site_dir/$theme_file", 0777);
		}
	}
	@closedir($theme_dir);

	// Rewrite the theme header.
	$stylelines = explode("\n", implode('', file("$site_dir/style.css")));
	if ($stylelines) {
		$f = fopen("$site_dir/style.css", 'w');

		foreach ($stylelines as $line) {
			if (strpos($line, 'Theme Name:') !== false) $line = 'Theme Name: ' . $theme_name;
			elseif (strpos($line, 'Theme URI:') !== false) $line = 'Theme URI: ' . __get_option('url');
			elseif (strpos($line, 'Description:') !== false) $line = 'Description: Your theme.';
			elseif (strpos($line, 'Version:') !== false) $line = 'Version: 1';
			elseif (strpos($line, 'Author:') !== false) $line = 'Author: You';
			fwrite($f, $line . "\n");
		}
		fclose($f);
	}

	// Copy the images.
	umask(0);
	if (! mkdir("$site_dir/images", 0777)) {
		return false;
	}

	$images_dir = @ opendir("$default_dir/images");
	if ($images_dir) {
		while(($image = readdir($images_dir)) !== false) {
			if (is_dir("$default_dir/images/$image"))
				continue;
			if (! @copy("$default_dir/images/$image", "$site_dir/images/$image"))
				return;
			chmod("$site_dir/images/$image", 0777);
		}
	}
	@closedir($images_dir);
}

function make_site_theme() {
	// Name the theme after the blog.
	$theme_name = __get_option('blogname');
	$template = sanitize_title($theme_name);
	$site_dir = WP_CONTENT_DIR . "/themes/$template";

	// If the theme already exists, nothing to do.
	if ( is_dir($site_dir)) {
		return false;
	}

	// We must be able to write to the themes dir.
	if (! is_writable(WP_CONTENT_DIR . "/themes")) {
		return false;
	}

	umask(0);
	if (! mkdir($site_dir, 0777)) {
		return false;
	}

	if (file_exists(ABSPATH . 'wp-layout.css')) {
		if (! make_site_theme_from_oldschool($theme_name, $template)) {
			// TODO: rm -rf the site theme directory.
			return false;
		}
	} else {
		if (! make_site_theme_from_default($theme_name, $template))
			// TODO: rm -rf the site theme directory.
			return false;
	}

	// Make the new site theme active.
	$current_template = __get_option('template');
	if ($current_template == WP_DEFAULT_THEME) {
		update_option('template', $template);
		update_option('stylesheet', $template);
	}
	return $template;
}

function translate_level_to_role($level) {
	switch ($level) {
	case 10:
	case 9:
	case 8:
		return 'administrator';
	case 7:
	case 6:
	case 5:
		return 'editor';
	case 4:
	case 3:
	case 2:
		return 'author';
	case 1:
		return 'contributor';
	case 0:
		return 'subscriber';
	}
}


function wp_check_mysql_version() {
	global $kvdb;
	$result = $kvdb->check_database_version();
	if ( is_wp_error( $result ) )
		die( $result->get_error_message() );
}

function maybe_disable_automattic_widgets() {
	$plugins = __get_option( 'active_plugins' );

	foreach ( (array) $plugins as $plugin ) {
		if ( basename( $plugin ) == 'widgets.php' ) {
			array_splice( $plugins, array_search( $plugin, $plugins ), 1 );
			update_option( 'active_plugins', $plugins );
			break;
		}
	}
}


function maybe_disable_link_manager() {
	global $wp_current_db_version, $kvdb;

	if ( $wp_current_db_version >= 22006 && get_option( 'link_manager_enabled' ) && ! $kvdb->get_var( "SELECT link_id FROM $kvdb->links LIMIT 1" ) )
		update_option( 'link_manager_enabled', 0 );
}

function pre_schema_upgrade() {
	global $wp_current_db_version, $kvdb;

	// Upgrade versions prior to 2.9
	if ( $wp_current_db_version < 11557 ) {
		// Delete duplicate options. Keep the option with the highest option_id.
		$kvdb->query("DELETE o1 FROM $kvdb->options AS o1 JOIN $kvdb->options AS o2 USING (`option_name`) WHERE o2.option_id > o1.option_id");

		// Drop the old primary key and add the new.
		$kvdb->query("ALTER TABLE $kvdb->options DROP PRIMARY KEY, ADD PRIMARY KEY(option_id)");

		// Drop the old option_name index. dbDelta() doesn't do the drop.
		$kvdb->query("ALTER TABLE $kvdb->options DROP INDEX option_name");
	}

	// Multisite schema upgrades.
	if ( $wp_current_db_version < 25448 && is_multisite() && ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) && is_main_network() ) {

		// Upgrade verions prior to 3.7
		if ( $wp_current_db_version < 25179 ) {
			// New primary key for signups.
			$kvdb->query( "ALTER TABLE $kvdb->signups ADD signup_id BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST" );
			$kvdb->query( "ALTER TABLE $kvdb->signups DROP INDEX domain" );
		}

		if ( $wp_current_db_version < 25448 ) {
			// Convert archived from enum to tinyint.
			$kvdb->query( "ALTER TABLE $kvdb->blogs CHANGE COLUMN archived archived varchar(1) NOT NULL default '0'" );
			$kvdb->query( "ALTER TABLE $kvdb->blogs CHANGE COLUMN archived archived tinyint(2) NOT NULL default 0" );
		}
	}
}

if ( !function_exists( 'install_global_terms' ) ) :
function install_global_terms() {
	global $kvdb, $charset_collate;
	$ms_queries = "
CREATE TABLE $kvdb->sitecategories (
  cat_ID bigint(20) NOT NULL auto_increment,
  cat_name varchar(55) NOT NULL default '',
  category_nicename varchar(200) NOT NULL default '',
  last_updated timestamp NOT NULL,
  PRIMARY KEY  (cat_ID),
  KEY category_nicename (category_nicename),
  KEY last_updated (last_updated)
) $charset_collate;
";
// now create tables
	dbDelta( $ms_queries );
}
endif;
