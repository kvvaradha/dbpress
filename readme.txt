===  DbPRess ===
Contributors: kvvaradha
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=kvvaradha@gmail.com&item_name=KV Plugin Hider	
Tags: dbpress,kvcodes, Second database,  
Requires at least: 3.1
Tested up to: 3.9

A simple wordpress plugin which helps you to use another database for your wordpress. 

== Description ==

A simple wordpress plugin which helps you to use another database for your wordpress. 

If you want to rework with the code, fine take a look at here before start using this code, It will be helpful to work with your own project.<a href="http://kvcodes.com/2014/03/dbpress-configurations/" target="blank" > Kvcodes.com </a> 

**Features**

* Easy install
* Easy to show hide from the plugins directory.

== Installation ==

You can use the built in installer and upgrader, or you can install the plugin manually.

**Installation via Wordpress**

1. Go to the menu 'Plugins' -> 'Install' and search for 'WP List PlugIns'
1. Click 'install'

**Manual Installation**

1. Upload folder `kv_backlink` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Screenshot of KV Backlink Checker.
2. Dashboard Widget.

== Functions And Usage ==

Create table,insert, delete, update, get results.
More similar functions like the core$ wpdb. If you encounter any problem or bug, please feel free to comment below.
Sample Codes :
<pre>
	global $kvdb ; 
	$test_table = $kvdb->prefix.'kv_test_table';
		
		$sql_query = "CREATE TABLE IF NOT EXISTS $test_table  (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `person_name` varchar(64) NOT NULL default '',
			  `contact_no` varchar(128) NOT NULL default '',
			  `email` varchar(15) NOT NULL default '',          
			  UNIQUE KEY id (id)
			)ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;";
			
	dbDelta($sql_query);
	</pre>
The above code helps you to create a  new table in your new database.
Likewise you query and get results from the second table,
<pre>
	$users = $kvdb->get_results( "SELECT * FROM kv_custom_table");
							
	foreach ( $users as $user ) {
		echo $user->username; 
	}
	</pre> 
You can do much more.

