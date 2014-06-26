<?php
/*
Plugin Name: DbPress
Plugin URI: http://kvcodes.com
Description: A simple wordpress plugin . With the Help of DbPress, your wordpress helps you to play with second database. 
Version: 1.0
Author: kvvaradha
Author URI: http://profiles.wordpress.org/kvvaradha
*/

	global $kvdb; 
	
define( 'KV_DBPRESS', dirname( __FILE__ )  );

define('KV_DBPRESS_URL', plugin_dir_url( __FILE__ ));

require_once(KV_DBPRESS.'/Kvdb.php'); 



if(!function_exists('kv_admin_menu')) {
	function kv_admin_menu() { 		
		add_menu_page('Kvcodes', 'Kvcodes', 'manage_options', 'kvcodes' , 'kv_codes_plugins', KV_DBPRESS_URL.'/images/kv_logo.png', 66);	
		add_submenu_page( 'kvcodes', 'DbPress', 'DbPress', 'manage_options', 'dbpress', 'dbpress_admin_settings' );
		add_submenu_page( 'kvcodes', 'Refereence', 'Reference', 'manage_options', 'dbpress_ref', 'kv_dbpress_reference' );
	}
add_action('admin_menu', 'kv_admin_menu');


function kv_codes_plugins() {

?>
 <div class="wrap">
    <div class="icon32" id="icon-tools"><br/></div>
    <h2><?php _e('KvCodes', 'kvcodes') ?></h2>		
	<div class="welcome-panel">
		Thank you for using Kvcodes Plugins . Here is my few Plugins work .My plugins are very light weight and Simple.  <p>
		<a href="http://www.kvcodes.com/" target="_blank" ><h3> Visit My Blog</h3></a></p> 
	</div> 
	
	<div id="poststuff" > 
		<div id="post-body" class="metabox-holder columns-2" >
			<div id="post-body-content" > 
				<div class="meta-box-sortables"> 
					<div id="dashboard_right_now" class="postbox">
						<div class="handlediv" > <br> </div>
						<h3 class="hndle"  ><img src="<?php echo KV_DBPRESS_URL.'/images/kv_logo.png'; ?>" >  My plugins </h3> 
						<div class="inside" style="padding: 10px; "> 								
							<?php $kv_wp =  kv_get_web_page('http://profiles.wordpress.org/kvvaradha'); 
									
									 $kv_first_pos = strpos($kv_wp['content'], '<div id="content-plugins" class="info-group plugin-theme main-plugins inactive">');
									
									$kv_first_trim = substr($kv_wp['content'] , $kv_first_pos ); 
										
									$kv_sec_pos = strpos($kv_first_trim, '</div>');
									
									$kv_sec_trim = substr($kv_first_trim ,0, $kv_sec_pos );  
									
									echo $kv_sec_trim; 	?> 
						</div>
					</div>
				</div>							
			</div>
		</div>
	</div> 			
	<div id="postbox-container-1" class="postbox-container" > 
		<div class="meta-box-sortables"> 
			<div id="postbox-container-2" class="postbox-container" >
				<div id="dashboard_right_now" class="postbox">
					<div class="handlediv" > <br> </div>
					<h3 class="hndle" ><img src="<?php echo KV_DBPRESS_URL.'/images/kv_logo.png'; ?>" >  Donate </h3> 
					<div class="inside" style="padding: 10px; " > 
						<b>If i helped you, you can buy me a coffee, just press the donation button :)</b> 
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
							<input type="hidden" name="cmd" value="_donations" />
							<input type="hidden" name="business" value="<?php echo 'kvvaradha@gmail.com'; ?>" />
							<input type="image" src="https://www.paypalobjects.com/en_US/GB/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
							<img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
						</form>
					</div> 
				</div> 
			</div>
			<div id="postbox-container-2" class="postbox-container" > 
				<div id="dashboard_quick_press" class="postbox">
					<div class="handlediv" > <br> </div>
					<h3 class="hndle" ><img src="<?php echo KV_DBPRESS_URL.'/images/kv_logo.png'; ?>" >  Support me from Facebook </h3> 
					<div class="inside" style="padding: 10px; "> 
						<p><iframe allowtransparency="true" frameborder="0" scrolling="no" src="//www.facebook.com/plugins/likebox.php?href=https%3A%2F%2Fwww.facebook.com%2Fkvcodes&amp;width=180&amp;height=300&amp;show_faces=true&amp;colorscheme=light&amp;stream=false&amp;show_border=false&amp;header=false&amp;appId=117935585037426" style="border:none; overflow:hidden; width:250px; height:300px;"></iframe></p>
					</div> 
				</div> 
			</div>
		</div>
	</div> 				
</div> <!-- /wrap -->
<?php

}

function kv_get_web_page( $url )
{
	$options = array(
		CURLOPT_RETURNTRANSFER => true,     // return web page
		CURLOPT_HEADER         => false,    // don't return headers
		CURLOPT_FOLLOWLOCATION => true,     // follow redirects
		CURLOPT_ENCODING       => "",       // handle compressed
		CURLOPT_USERAGENT      => "spider", // who am i
		CURLOPT_AUTOREFERER    => true,     // set referer on redirect
		CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
		CURLOPT_TIMEOUT        => 120,      // timeout on response
		CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
	);

	$ch      = curl_init( $url );
	curl_setopt_array( $ch, $options );
	$content = curl_exec( $ch );
	$err     = curl_errno( $ch );
	$errmsg  = curl_error( $ch );
	$header  = curl_getinfo( $ch );
	curl_close( $ch );

	$header['errno']   = $err;
	$header['errmsg']  = $errmsg;
	$header['content'] = $content;
	return $header;
}

add_action( 'admin_print_styles', 'kv_admin_css' );
function kv_admin_css() {
	 wp_enqueue_style("kvcodes_admin", KV_DBPRESS_URL."/kv_admi_style.css", false, "1.0", "all");
}

} else {
	function kv_admin_submenu_kv_dbpress_page() { 		
		add_submenu_page( 'kvcodes', 'DbPress', 'DbPress', 'manage_options', 'dbpress', 'dbpress_admin_settings' );
		add_submenu_page( 'kvcodes', 'Reference', 'Reference', 'manage_options', 'dbpress_ref', 'kv_dbpress_reference' );
	}
add_action('admin_menu', 'kv_admin_submenu_kv_dbpress_page');
	
}


add_action('admin_init', 'kv_dbpress_register_options');

function kv_dbpress_register_options() {
	register_setting('kvcodes' , 'kv_db_host');
	register_setting('kvcodes' , 'kv_db_name');
	register_setting('kvcodes' , 'kv_db_user');
	register_setting('kvcodes' , 'kv_db_user_pass');
}

function dbpress_admin_settings() {

?>
<div class="wrap">
        <div class="icon32" id="icon-tools"><br/></div>
        <h2><?php _e('Kv Front Post Settings', 'kvcodes') ?></h2>
		<div class="welcome-panel">
			This is a test plugin. If you gave wrong database details, It will stuck the whole site and you need to remove the details from phpmyadmin to work properly. <p>
			<a href="http://kvcodes.com/2014/03/dbpress-configurations/" target="_blank" ><h4> Read Here for More info. </h4></a></p> 
		</div> 
		<div id="dashboard-widget-wrap" >
			<div id="dashboard-widgets" class="metabox-holder columns-2" >
				<div id="postbox-container-1" class="postbox-container" > 
					<div class="meta-box-sortables"> 
						<div id="dashboard_right_now" class="postbox">
							<div class="handlediv" > <br> </div>
							<h3 class="hndle" > General Settings </h3> 
							<div class="inside" style="padding: 10px; " > 
								
								<form method="post" action="options.php">
								    <?php settings_fields( 'kvcodes' ); ?>
								    <?php do_settings_sections( 'kvcodes' ); ?>
									<p> Remember,  If the entered database is invalid, you will be in a headache.  <a href="http://kvcodes.com/2014/03/dbpress-configurations/" target="_blank" > Read Here for More info. </a>! </p> 
								    <table class="form-table">								                 
								        <tr valign="top"><th scope="row">DataBase Host</th><td> <input type="text" name="kv_db_host" value="<?php echo get_option('kv_db_host'); ?>" > </td> </tr>								        
										<tr valign="top"><th scope="row">DataBase Name</th><td> <input type="text" name="kv_db_name" value="<?php echo get_option('kv_db_name'); ?>" > </td> </tr>								        
										<tr valign="top"><th scope="row">DataBase User</th><td> <input type="text" name="kv_db_user" value="<?php echo get_option('kv_db_user'); ?>" > </td> </tr>								        
										<tr valign="top"><th scope="row">DataBase User Password</th><td> <input type="text" name="kv_db_user_pass" value="<?php echo get_option('kv_db_user_pass'); ?>" > </td> </tr>								        
										
								    </table>								    
								    <?php submit_button(); ?>
								</form>
							</div> 
						</div> 
					</div>
				</div> 
			</div>
		</div> 
</div> <!-- /wrap -->
<?php	
}

//$kvdb = new wpdb(get_option('kv_db_user'), get_option('kv_db_user_pass'), get_option('kv_db_name'), get_option('kv_db_host')); 
/*
$kv_verify = mysql_connect( get_option('kv_db_host'), get_option('kv_db_user'), get_option('kv_db_user_pass'));
if (!$kv_verify) {
	die('Connection Error : ' . mysql_error());
} else { 
	$db_selected = mysql_select_db(get_option('kv_db_name'), $kv_verify);
	if (!$db_selected) {
		$kv_db_create_query = "CREATE DATABASE IF NOT EXISTS ". get_option('kv_db_name'); 
		mysql_query($kv_db_create_query); 
		//$db_selected = mysql_select_db(get_option('kv_db_name'), $kv_verify);
	} else {		
		
	}
}
*/
/*
if(!mysql_connect('localhost','username','password')){
    $kv_dbpress = "Connection Error : Check your Host, and its user credentials!" ; 
} else {
		
	
*/

function kv_dbpress_reference() {?>
<div class="wrap">
        <div class="icon32" id="icon-tools"><br/></div>
        <h2><?php _e('Kv Front Post Settings', 'kvcodes') ?></h2>
		<div id="dashboard-widget-wrap" >
			<div id="dashboard-widgets" class="metabox-holder columns-2" >
				<div id="postbox-container-1" class="postbox-container" > 
					<div class="meta-box-sortables"> 
						<div id="dashboard_right_now" class="postbox">
							<div class="handlediv" > <br> </div>
							<h3 class="hndle" > General Settings </h3> 
							<div class="inside" style="padding: 10px; " > 
Functions And Usage :
<ul>
	<li>Create table,</li>
	<li>insert, delete, update, get results.</li>
	<li>More similar functions like the core$ wpdb. If you encounter any problem or bug, please feel free to comment below.</li>
</ul>
Sample Codes :
<pre class="lang:default decode:true">global $kvdb ; 
$test_table = $kvdb-&gt;prefix.'kv_test_table';
	
	$sql_query = "CREATE TABLE IF NOT EXISTS $test_table  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `person_name` varchar(64) NOT NULL default '',
		  `contact_no` varchar(128) NOT NULL default '',
		  `email` varchar(15) NOT NULL default '',		  
		  UNIQUE KEY id (id)
		)ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;";
		
dbDelta($sql_query);
</pre>
The above code helps you to create a new table in your new database.

Likewise you query and get results from the second table,
<pre class="lang:default decode:true">$users = $kvdb-&gt;get_results( "SELECT * FROM kv_custom_table");
						
foreach ( $users as $user ) {
	echo $user-&gt;username; 
}</pre>
You can do much more.
</div> 
						</div> 
					</div>
				</div> 
			</div>
		</div> 
</div> <!-- /wrap -->
<?php }

?>