<?php
namespace Async\includes;
use Async\includes\sync_ximalaya;

class postsync {

	public function __construct() {
		// Sync up current changes to wechat OA meterials when updating the posts
		add_action('save_post_post', array($this, 'action_save_post_post'), 10, 3);
		// Delete wechat OA meterials when deleting the posts
		add_action('before_delete_post', array($this, 'action_after_delete_post'));
	}

	public function action_save_post_post($post_ID, $post, $update) {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		if ($_POST['ximalaya_sync'] == 'on' || ($options['xmlrpc_sync_ximalaya'] && defined('XMLRPC_REQUEST')) ) {
			$ximalaya = new sync_ximalaya($options['ximalaya']['phone'], $options['ximalaya']['password']);
			$medias = $this->get_medias('mp3|wma|wav', $post->post_content, $post_ID);
			$ximalaya->sync($post_ID, $post, $medias);
		}
	}

	public function action_after_delete_post($post_ID) {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		if (!$options['appid'] || !$options['appsecret']) return false;
		require_once ASYNC_PLUGIN_DIR . 'includes/officialAccount.php';
		$app = new Awechat_oa(array(
			'app_id' => $options['appid'],
			'secret' => $options['appsecret']
		));
		$wechat_article_id = get_post_meta( $post_ID, '_wechat_article_id', true );
		if ($wechat_article_id) {
			$app->material->delete($wechat_article_id);
		}
	}

	private function get_medias($types, $content, $post_ID = null) {
		$types = str_replace(',', '|', $types);
		preg_match_all("/https?:\/\/\S*\.(".$types.")/i", $content, $matches);
		if ($post_ID) $has_beepress_podcast = get_post_meta($post_ID, 'enclosure', true);
		if ($has_beepress_podcast) {
			$enclosureURL = trim(explode("\n", $has_beepress_podcast, 4)[0]);
			array_push($matches[0], $enclosureURL);
		}
		$matches_dedeplication = array_keys(array_flip($matches[0]));

		$origin_prefix = site_url().'/files';
		$cdn_prefix = get_option('upload_url_path');
		$medias = array();
		foreach ($matches_dedeplication as $media) {
			$media_url = str_replace($cdn_prefix, $origin_prefix, $media);
			$url_info = parse_url($media_url);
			$media_url = isset($url_info['host']) ? $media_url : $_SERVER['HTTP_ORIGIN'] . $url_info['path'];
			$is_local_media = strstr($media_url, $_SERVER['HTTP_HOST']);
			if ($is_local_media) {
				$media_media_id = attachment_url_to_postid($media_url);
				$media_media_url = $media_url;
				$media_path = $this->_get_local_media_path($media_media_url);
			} else {
				$media_media_id = $this->_insert_wp_media($media_url, $post_ID);
				if (!$media_media_id) continue;
				$media_media_url = wp_get_attachment_image_url($media_media_id , 'single-post-thumbnail' );
				$media_path = $this->_get_local_media_path($media_media_url);
			}
			array_push($medias, array(
				'id' => $media_media_id,
				'url' => $media_media_url,
				'path' => $media_path,
				'original_url' => $media
			));
		}
		return $medias;
	}
	private function _get_local_media_path($media_url) {
		$parsed = parse_url(trim($this->_link_urlencode($media_url)));
		if (preg_match("/\/wp-content\//i", $parsed['path'])) {
			return urldecode(get_home_path() . $parsed['path']);
		}
		$upload_dir = wp_upload_dir();
		$media_path = empty($parsed['path']) ? '' : preg_replace("/\S*\/files/i", $upload_dir['basedir'], $parsed['path']);
		return urldecode($media_path);
	}
	private function _link_urlencode($url) {
		$uri = '';
		$cs = unpack('C*', $url);
		$len = count($cs);
		for ($i=1; $i<=$len; $i++) {
		  $uri .= $cs[$i] > 127 ? '%'.strtoupper(dechex($cs[$i])) : $url{$i-1};
		}
		return $uri;
	}
	private function _insert_wp_media($mediaUrl, $post_ID = null) {
        if (strpos($mediaUrl, $_SERVER['HTTP_HOST']) > 0) return false;
        $dataGet = wp_remote_get($mediaUrl);
        if (is_wp_error($request)) return false;

        $mediaInfo = parse_url($mediaUrl);
		$mediaFileName = basename($mediaInfo['path']);
		$mediaFileName = preg_match("/\.(jpg|jpeg|png|gif|mp3|wav|mp4)$/i", $mediaFileName) ? $mediaFileName : $mediaFileName.'.jpg';
		$uploadFile = wp_upload_bits($mediaFileName, null, wp_remote_retrieve_body($dataGet));
		if (array_key_exists('error', $uploadFile) && $uploadFile['error']) return false;

        $attach_id = wp_insert_attachment(array(
            'post_title' => $mediaFileName,
            'post_mime_type' => wp_remote_retrieve_header($dataGet, 'content-type'),
        ), $uploadFile['file'], $post_ID);
        $attachment_data = wp_generate_attachment_metadata($attach_id, $uploadFile['file']);
		wp_update_attachment_metadata($attach_id, $attachment_data);
		
		return $attach_id;
	}
}