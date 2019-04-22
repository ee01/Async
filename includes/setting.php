<?php
namespace Async\includes;
use Async\includes\sync_ximalaya;

class setting {

	public function __construct() {
        add_action( 'admin_init', array( $this, 'settings_init' ));
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ));
        add_action( 'wp_ajax_Async_validate', array( $this, 'validate' ));
        add_action( 'wp_ajax_Async_validate_with_smscode', array( $this, 'validate_with_smscode' ));
        add_action( 'admin_menu', array( $this, 'create_wechat_box' ), 11);
		add_action( 'manage_post_posts_columns', array( $this, 'posts_add_column' ), 11 );
		add_action( 'manage_post_posts_custom_column', array( $this, 'posts_render_column' ), 11, 2 );
	}

	static function Activation() {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		if ($options) return false;
		$options = array(
			'article_cover_image' => ASYNC_DEFAULT_ARTICLE_IMAGE,
		);
		$Awechat_option_name = defined(AWECHAT_PLUGIN_OPTIONNAME) ? AWECHAT_PLUGIN_OPTIONNAME : 'Awechat_setting';
		$Awechat_options = get_option( $Awechat_option_name );
		if ($Awechat_options) {
			$options['article_cover_image'] = $Awechat_options['article_cover_image'];
			$options['title_prefix'] = $Awechat_options['title_prefix'];
		}
		update_option( ASYNC_PLUGIN_OPTIONNAME, $options );
	}
	
    public function settings_init(){
		register_setting( ASYNC_PLUGIN_OPTIONNAME, ASYNC_PLUGIN_OPTIONNAME );
	}

    public function add_plugin_page(){
        // This page will be under "Settings"
        $page_title=__('Async Settings', 'Async');
        $menu_title=__('Async Settings', 'Async');
        $capability='manage_options';
        $menu_slug=ASYNC_PLUGIN_OPTIONNAME;
        
        add_options_page(
        	$page_title,
        	$menu_title,
        	$capability,
        	$menu_slug,
        	array( $this, 'create_admin_page' )
        );
    }
    public function create_admin_page(){
        // Set class property
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$interface_url = $options['token']!=''?home_url().'/?'.$options['token']:'none';
		wp_enqueue_media();
		wp_register_script('Async-custom-upload', ASYNC_PLUGIN_URL.'/assets/media_upload.js', array('jquery','media-upload','thickbox'),"2.0");
		wp_enqueue_script('Async-custom-upload');
		wp_enqueue_script('Async-custom-validate', ASYNC_PLUGIN_URL.'/assets/validate.js', array('jquery','thickbox'),"2.0");
		// wp_enqueue_style( 'thickbox' );
		wp_localize_script('Async-custom-validate', 'validate', array(
			'loginSuccess' => __('Login Successfully!', 'Async'),
			'send_smscode' => __('Send SMS Code', 'Async'),
			'correct' => __('SMS Code is correct!', 'Async'),
		));
	?>
		<div class="wrap">
			<h2><?php _e('A Synchronization','Async')?></h2>
			<form action="options.php" method="POST">
				<?php settings_fields( ASYNC_PLUGIN_OPTIONNAME );?>
				<hr>

				<h2><?php _e('Ximalaya Account Settings','Async')?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label><?php _e('Phone','Async')?></label></th>
						<td>
							<input type="text"
								size="30"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME ;?>[ximalaya][phone]"
								value="<?php echo $options['ximalaya']['phone'];?>"
								class="regular-text"/>
							<p class="description">
								<?php _e('Register podcaster on Ximalaya: ','Async')?><a href="http://studio.ximalaya.com/" target="_blank">http://studio.ximalaya.com/</a>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Password','Async')?></label></th>
						<td>
							<input type="password"
								size="30"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME ;?>[ximalaya][password]"
								value="<?php echo $options['ximalaya']['password'];?>"
								class="regular-password"/>
							<p class="description">
								<?php _e('If you are stuck on posting, please validate your account: ','Async')?><span id="validate_ximalaya"><a href="<?php echo admin_url('admin-ajax.php') ?>?action=Async_validate" target="_blank"><?php _e('Click to Validate','Async')?></a></span>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Album ID','Async')?></label></th>
						<td>
							<input type="text"
								size="30"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME ;?>[ximalaya][album_id]"
								value="<?php echo $options['ximalaya']['album_id'];?>"
								class="regular-text"/>
							<p class="description">
								<?php _e('Upload post audio to this album.','Async')?>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Enable sync from XML-RPC','Async')?></label></th>
						<td>
							<input type="checkbox"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME ;?>[xmlrpc_sync_ximalaya]"
								value="1"
								<?php echo $options['xmlrpc_sync_ximalaya']?'checked':'';?>
								class="regular-checkbox"/>
							<p class="description">
								<?php _e('Sync up to Ximalaya automatically when using destop app to write article.','Async')?>
							</p>
						</td>
					</tr>
				</table>

				<?php if (is_plugin_active('Awechat/Awechat.php')) { ?>
				<h2><?php _e('Wechat Account Settings', 'Async') ?></h2>
				<p><?php _e('Please go to A Wechat Setting page: ','Async')?><a href="/wp-admin/options-general.php?page=<?php echo AWECHAT_PLUGIN_OPTIONNAME ?>"><?php _e('WeChat Settings', 'Awechat') ?></a></p>
				<?php } ?>

				<h2><?php _e('Sync Up Settings','Async')?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label><?php _e('Title Prefix','Async')?></label></th>
						<td>
							<input type="text"
								size="30"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME ;?>[title_prefix]"
								value="<?php echo $options['title_prefix'];?>"
								class="regular-text"/>
							<p class="description">
								<?php _e('Add to synchronized article title automatically.','Async')?>
							</p>
						</td>
					</tr>
					<!-- Cover Image-->
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Default Cover', 'Async'); ?></label>
						</th>
						<td>
						<div class="preview-box large">
							<img src="<?php echo $options['article_cover_image']; ?>" style="max-width:500px" />
						</div>
						<input type="hidden"
								value="<?php echo $options['article_cover_image']; ?>"
								name="<?php echo ASYNC_PLUGIN_OPTIONNAME; ?>[article_cover_image]"
								rel="img-input" class="img-input large-text"/>
						<button class='media_upload_button button'>
							<?php _e('Upload', 'Async'); ?>
						</button>
						<button class='media_delete_button button'>
							<?php _e('Delete', 'Async'); ?>
						</button>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>

		<div id="send-smscode" style="display:none;">
			<input type="hidden" id="realmobile" />
			<input type="hidden" id="checkkey" />
			<p>
				<label for="smscode"><?php _e('SMS Code', 'Async'); ?></label>
				<input id="smscode" type="text" class="regular-text" />
			</p>
			<input type="submit" name="submit" id="smscode-send" class="button button-primary" value="<?php _e('Send', 'Async'); ?>" data-url="<?php echo admin_url('admin-ajax.php') ?>?action=Async_validate_with_smscode" />
		</div>
	<?php
	}

	public function validate() {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$ximalaya = new sync_ximalaya($options['ximalaya']['phone'], $options['ximalaya']['password']);
		$userinfo = $ximalaya->get_user_info();
		if (!$userinfo) {
			$login_result = $ximalaya->login();
			if (array_key_exists('gotoValidateMobile', $login_result)) {
				$smscode_result = $ximalaya->get_smscode($login_result['mobileReal']);
				echo json_encode(array(
					'err' => 51,
					'data' => array(
						'mobile' => $login_result['mobileReal'],
						'checkkey' => $login_result['checkKey'],
						'result' => $smscode_result,
					),
					'msg' => 'Need validate via phone sms code.',
				));
				wp_die();
			} elseif (!array_key_exists('uid', $login_result)) {
				echo json_encode(array('err' => 1));
				wp_die();
			}
			$userinfo = array('uid'=>$login_result['uid']);
		}
		echo json_encode(array(
			'err' => 0,
			'data' => $login_result,
		));
		wp_die();
	}
	public function validate_with_smscode() {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$ximalaya = new sync_ximalaya($options['ximalaya']['phone'], $options['ximalaya']['password']);
		$smscode_result = $ximalaya->validate_smscode($_GET['mobile'], $_GET['smscode'], $_GET['checkkey']);
		echo json_encode(array(
			'err' => 0,
			'data' => $smscode_result,
		));
		wp_die();
	}
	
	public function create_wechat_box() {
        // $post_types = array_keys(get_post_types());
        remove_meta_box('Awechat-meta-box', 'post', 'side');
        add_meta_box('Async-meta-box', __('A Sync', 'Async'), [$this, 'async_meta_box'], 'post', 'side', 'high');
	}
    public function async_meta_box() {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
    ?>
		<h4 style="margin-bottom:5px"><?php _e('Ximalaya Sync', 'Async'); ?></h4>
		<hr style="margin-top:0" />
        <p>
            <input type="checkbox" name="ximalaya_sync" id="ximalaya_sync" <?php if ($options['ximalaya']['phone']&&$options['ximalaya']['password']) echo 'checked';else echo 'disabled'; ?>/><label for="ximalaya_sync"><?php _e('Sync Ximalaya', 'Async') ?></label>
            <a href="/wp-admin/options-general.php?page=<?php echo ASYNC_PLUGIN_OPTIONNAME ?>" style="float: right;"><span aria-hidden="true"><?php _e('Help', 'Async') ?></span></a>
        </p>
		<?php if (is_plugin_active('Awechat/Awechat.php')) {
			require_once AWECHAT_PLUGIN_DIR . 'includes/setting.php'; ?>
			<h4 style="margin-bottom:5px"><?php _e('Wechat OA Sync', 'Async'); ?></h4>
			<hr style="margin-top:0" />
		<?php \Awechat_setting::wechat_meta_box(); } ?>
    <?php
	}

	public function posts_add_column($post_columns) {
		unset($post_columns['Awechat']);
		$post_columns['Async'] = __( 'Sync', 'Async' );
		return $post_columns;
	}
	public function posts_render_column($column_name, $post_ID) {
		if ( $column_name == 'Async') {
			date_default_timezone_set('PRC');
			if (is_plugin_active('Awechat/Awechat.php')) {
				require_once AWECHAT_PLUGIN_DIR . 'includes/setting.php';
				\Awechat_setting::posts_render_column('Awechat', $post_ID);
			}
			$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
			$ximalaya_article_id = get_post_meta( $post_ID, '_ximalaya_track_id', true );
			$ximalaya_article_url = 'https://www.ximalaya.com/keji/' . ($options['ximalaya']['album_id']?$options['ximalaya']['album_id']:0) . '/' . $ximalaya_article_id;
			if (!$ximalaya_article_id) $ximalaya_article_url = 'javascript:;';
			$ximalaya_sync_log = get_post_meta( $post_ID, '_ximalaya_sync_log', true );
			$icon_titles = array();
			if (is_array($ximalaya_sync_log) && count($ximalaya_sync_log) > 0) {
				$is_sync_successful = !!$ximalaya_sync_log[ count($ximalaya_sync_log)-1 ]['success'];
				if (!$is_sync_successful) $icon_bg_position = 'background-position:24px 0;';
				for ($i=count($ximalaya_sync_log)-1; $i >= 0; $i--) { 
					if (count($ximalaya_sync_log) - $i >= 5) {array_push($icon_titles, '...'); break;}
					$is_sync_successful = !!$ximalaya_sync_log[$i]['success'];
					array_push($icon_titles, date('Y-m-d H:i:s', $ximalaya_sync_log[$i]['date']) . ': ' . ($is_sync_successful ? __('Sync Successfully!', 'Async') :  __('Sync Failed!', 'Async').$ximalaya_sync_log[$i]['error'].'-'.$ximalaya_sync_log[$i]['msg']) );
				}
			}
			if (!$ximalaya_article_id) $icon_bg_position .= 'filter:grayscale(100%);';
			echo '<a href="' . $ximalaya_article_url . '" title=\'' . join("\n",$icon_titles) . '\' style="display:inline-block;margin:10px 5px;width:24px;height:25px;background-size:48px 25px;background-image:url(' . ASYNC_PLUGIN_URL.'/assets/ximalaya_icon.png' . ');' . $icon_bg_position . '" target="_blank" /></a>';
		}
	}
}
