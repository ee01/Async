<?php
/*
Plugin Name: A Synchronization
Plugin URI: http://IT.eexx.me/
Description: Synchronize articles or audios to other platforms.
Author: Esone
Version: 1.2
Author URI: http://IT.eexx.me
*/
namespace Async;
use Async\includes\postsync;
use Async\includes\setting;

define( 'ASYNC_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
define( 'ASYNC_PLUGIN_URL', plugins_url() . '/' . plugin_basename(dirname(__FILE__)) );
define( 'ASYNC_PLUGIN_OPTIONNAME', 'Async_setting' );

define( 'ASYNC_DEFAULT_ARTICLE_IMAGE', ASYNC_PLUGIN_URL.'/assets/default_acticle_image.png' );
define( 'ASYNC_DEFAULT_FOLLOW_IMAGE', ASYNC_PLUGIN_URL.'/assets/follow_tips.gif' );
define( 'ASYNC_DEFAULT_READSOURCE_IMAGE', ASYNC_PLUGIN_URL.'/assets/read_source.gif' );

spl_autoload_register('Async\autoload');

// Activation Limitation
register_activation_hook( __FILE__, 'Async\activation' );

// Localization
add_action('plugins_loaded', 'Async\load_languages_file');
// Add settings link on plugin page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'Async\plugin_settings_link' );
add_action( 'wp_ajax_Async_reset', 'Async\reset' );

// Sync up post to other platforms
new postsync();

// Settings
new setting();


function activation () {
	// if ( version_compare(PHP_VERSION, '7.0', '<') ) {
	// 	deactivate_plugins( plugin_basename( __FILE__ ) );
	// 	wp_die( 'This plugin requires PHP 7.0 or higher!' );
	// }
	// Do activate Stuff now.
	setting::Activation();
}

function load_languages_file(){
	load_plugin_textdomain( 'Async', false, plugin_basename(dirname(__FILE__)) . '/languages/' );
}

function plugin_settings_link($links) {
	$settings_link = '<a id="Async_reset_button" href="' . admin_url('admin-ajax.php') . '?action=Async_reset">'.__('Reset','Async').'</a>';
	$settings_link .= '<script>jQuery("#Async_reset_button").click(function(){
		if (!confirm("'.__('Reset to default setting?','Async').'")) return false;
		jQuery.get($(this).attr("href"), function(json){
			json = eval("("+json+")");
			if (!json.err) {
				alert("'.__('Reset Successfully!','Async').'");
			}
			console.log(json);
		})
		return false;
	})</script>';
	array_unshift($links, $settings_link);
	return $links;
}
function reset() {
	delete_option( ASYNC_PLUGIN_OPTIONNAME );
	setting::Activation();
	echo json_encode(array(
		'err' => 0
	));
	wp_die();
}


function autoload($class) {
	$prefix = 'Async\\';
	$base_dir = __DIR__ . '/';

	// does the class use the namespace prefix?
	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		// no, move to the next registered autoloader
		return;
	}
	$relative_class = substr($class, $len);
	$relative_dir = $base_dir . str_replace('\\', '/', $relative_class);

	// 兼容Linux文件找。Windows 下（/ 和 \）是通用的
	$file = $relative_dir . '.php';
	if (file_exists($file)) {
		require $file;
	}
}