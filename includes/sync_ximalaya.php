<?php
namespace Async\includes;
use Async\includes\sync;
use PHPHtmlParser\Dom;
require_once ASYNC_PLUGIN_DIR . 'vendor/autoload.php';

class sync_ximalaya implements sync {
	const FILE_PIECE_SIZE = 1048576;
	public $dom;
	public $uid;
	public $userinfo;
	public $post_ID;
	private $phone;
	private $password;
	private $public_key =
'-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCVhaR3Or7suUlwHUl2Ly36uVmb
oZ3+HhovogDjLgRE9CbaUokS2eqGaVFfbxAUxFThNDuXq/fBD+SdUgppmcZrIw4H
MMP4AtE2qJJQH/KxPWmbXH7Lv+9CisNtPYOlvWJ/GHRqf9x3TBKjjeJ2CjuVxlPB
DX63+Ecil2JR9klVawIDAQAB
-----END PUBLIC KEY-----';

	public function __construct($phone, $password) {
		$this->phone = $phone;
		$this->password = $password;
		$this->cookie_file = $this->get_cookie_file();
		$this->dom = new Dom;
	}

	public function sync($post_ID, $post, $medias = array()) {
		$this->post_ID = $post_ID;
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$this->userinfo = $this->get_user_info();
		if (!$this->userinfo) $this->login();
		if (!$this->uid) $this->uid = $this->userinfo['uid'];
		foreach ($medias as $media) {
			$ximalaya_track_id = get_post_meta( $post_ID, '_ximalaya_track_id', true );
			if ($ximalaya_track_id) $ximalaya_track = $this->get_track_info($ximalaya_track_id, $options['ximalaya']['album_id']);
			$is_update_existed_material = $ximalaya_track_id && $ximalaya_track['ret'] == 200;
			if ($is_update_existed_material) {
				$track_result = $this->update_track($options['ximalaya']['album_id'], $ximalaya_track_id, $options['title_prefix'].$post->post_title, $post->post_excerpt, $post->post_content, get_the_author_meta('display_name', $post->post_author));
			} else {
				$file_result = $this->upload($media['path']);
				$track_result = $this->create_track($options['ximalaya']['album_id'], $file_result['callbackData']['fileId'], $options['title_prefix'].$post->post_title, $post->post_excerpt, $post->post_content);
				if ($track_result && $track_result['redirect_to']) {
					$track_id = $this->get_track_id_by_title($options['title_prefix'].$post->post_title);
					if ($track_id) update_post_meta( $post_ID, '_ximalaya_track_id', $track_id );
				}
			}
			break;	// support 1 audio per post
		}
		$this->log($post_ID, !!$track_result['redirect_to'], $track_result);
	}

	public function get_user_info() {
		$this->dom->load('http://studio.ximalaya.com/api/home/userInfo', [
			'curl' => [
				CURLOPT_REFERER => 'http://studio.ximalaya.com/',
				CURLOPT_COOKIEFILE => $this->cookie_file,
			]
		]);
		$userinfo = json_decode($this->dom->outerHtml, true);
		if ($userinfo == null || $userinfo['code'] != 0) return false;
		return $userinfo['data'];
	}

	public function login() {
		$token = $this->get_login_token();
		$password_encrypted = $this->encrypt_password($this->password, $token);
		$login_result = $this->do_login($password_encrypted);
		return $login_result;
	}
	private function get_login_token() {
		$this->dom->load('https://www.ximalaya.com/passport/token/login');
		$tokenResponse = json_decode($this->dom->outerHtml, true);
		return $tokenResponse['token'];
	}
	private function encrypt_password($password, $token) {
		openssl_public_encrypt(md5($password).$token, $password_encrypted, $this->public_key);
		return $password_encrypted = base64_encode($password_encrypted);
	}
	private function do_login($password_encrypted, $rememberMe = 'true') {
		$this->dom->load('https://www.ximalaya.com/passport/v4/security/popupLogin', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_REFERER => 'https://www.ximalaya.com/passport/login',
				CURLOPT_COOKIEJAR => $this->cookie_file,
				CURLOPT_HTTPHEADER => [
					'Connection: keep-alive',
				],
				CURLOPT_POSTFIELDS => [
					'account' => $this->phone,
					'password' => $password_encrypted,
					'rememberMe' => $rememberMe,
				]
			]
		]);
		$login_result = json_decode($this->dom->outerHtml, true);
		if (array_key_exists('gotoValidateMobile', $login_result)) $this->error(51, __('Need mobile validation! Please go to setting page to validate your account.', 'Async'));
		if ($login_result['ret'] != 0) $this->error($login_result['ret'], $login_result['errorMsg']);
		$this->uid = $login_result['uid'];
		return $login_result;
	}

	public function get_smscode($mobile) {
		$token = $this->get_login_token();
		$this->dom->load('https://www.ximalaya.com/passport/v1/sms/send', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_COOKIEFILE => $this->cookie_file,
				CURLOPT_REFERER => 'https://www.ximalaya.com/passport/login',
				CURLOPT_POSTFIELDS => [
					'phone_num' => $mobile,
					'nonce' => $token,
					'sendType' => 1,
					'msgType' => 32,
				]
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	public function validate_smscode($mobile, $smsCode, $checkKey) {
		$token = $this->get_login_token();
		$this->dom->load('https://www.ximalaya.com/passport/v1/phone/validate', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_COOKIEFILE => $this->cookie_file,
				CURLOPT_REFERER => 'https://www.ximalaya.com/passport/login',
				CURLOPT_POSTFIELDS => [
					'phone_num' => $mobile,
					'nonce' => $token,
					'smsCode' => $smsCode,
					'checkKey' => $checkKey,
				]
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	private function upload($file_path) {
		$file_size = filesize($file_path);
		$authorization = $this->get_upload_authorization(basename($file_path), $file_size);
		$file_ctxs = array();
		if ($file_size < self::FILE_PIECE_SIZE) {
			$file_ctx = $this->upload_file_pieces($file_path, $authorization);
			array_push($file_ctxs, $file_ctx['ctx']);
		} else {
			for ($offset=0; $offset < $file_size; $offset+=self::FILE_PIECE_SIZE) { 
				$file_ctx = $this->upload_file_pieces($file_path, $authorization, $offset, $file_ctx?$file_ctx['serverIp']:null);
				array_push($file_ctxs, $file_ctx['ctx']);
			}
		}
		return $file_result = $this->get_upload_file_url($file_ctxs, filesize($file_path), $file_ctx['serverIp'], $authorization);
	}
	private function get_upload_authorization($file_name, $file_size) {
		$this->dom->load('http://upload.ximalaya.com/clamper-token/token', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_COOKIEFILE => $this->cookie_file,
				CURLOPT_REFERER => 'http://www.ximalaya.com/upload/',
				CURLOPT_POSTFIELDS => http_build_query([
					'fileName' => $file_name,
					'fileSize' => $file_size,
					'uploadType' => 'audio',
					'callerType' => 'ting',
				])
			]
		]);
		$authorization = json_decode($this->dom->outerHtml, true);
		if ($authorization['ret'] != 0) $this->error($authorization['ret'], $authorization['msg']);
		return $authorization['token'];
	}
	private function upload_file_pieces($file_path, $authorization, $file_offset = -1, $server_ip = null) {
		$file_size = filesize($file_path);
		$fp = fopen($file_path, "rb");
		$chunk_params = $file_offset!=-1 ? '&chunks=' . ceil($file_size/self::FILE_PIECE_SIZE) . '&chunk=' . floor($file_offset/self::FILE_PIECE_SIZE) : '';
		$this->dom->load('http://upload.ximalaya.com/clamper-server/mkblk?id=WU_FILE_0&name='.basename($file_path).'&type=.mp3&lastModifiedDate=Fri+Dec+21+2018+23%3A19%3A33+GMT%2B0800+(China+Standard+Time)'.$chunk_params.'&uid='.$this->uid.'&size=' . filesize($file_path), [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_REFERER => 'http://www.ximalaya.com/upload/',
				CURLOPT_HTTPHEADER => [
					'Authorization: ' . $authorization,
					$server_ip ? 'x-clamper-server-ip: ' . $server_ip : '',
					'Content-Type: application/octet-stream',
				],
				CURLOPT_POSTFIELDS => stream_get_contents($fp, $file_offset!=-1?self::FILE_PIECE_SIZE:-1, $file_offset!=-1?$file_offset:-1)
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}
	private function get_upload_file_url($file_ctxs, $filesize, $server_ip, $authorization) {
		$this->dom->load('http://upload.ximalaya.com/clamper-server/mkfile/' . $filesize . '/ext/mp3', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_REFERER => 'http://www.ximalaya.com/upload/',
				CURLOPT_HTTPHEADER => [
					'Authorization: ' . $authorization,
					'Accept: application/json, text/javascript, */*; q=0.01',
					'Origin: http://www.ximalaya.com',
					'Content-Type: text/plain;',
					'x-clamper-server-ip: ' . $server_ip,
				],
				CURLOPT_POSTFIELDS => 'ctxList=' . implode(',', $file_ctxs)
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	private function create_track($album_id, $file_id, $title, $description = '', $content = '', $tags = array('互联网','创业汇','公开客')) {
		$this->dom->load('http://www.ximalaya.com/upload2/create', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_COOKIEFILE => $this->cookie_file,
				CURLOPT_REFERER => 'https://www.ximalaya.com/passport/login',
				CURLOPT_POSTFIELDS => http_build_query([
					'is_album' => 'false',
					'isVideo' => 'false',
					'files[]' => $title,
					'fileids[]' => $file_id,
					'choose_album' => $album_id,
					'soundInfo.sound_title' => $title,
					'soundInfo.sound_intro' => $description,
					'soundInfo.sound_rich_intro' => $content,
					'soundInfo.sound_tags' => join(',', $tags),
					'sound_image[]' => 'image_inherit',
					'date' => date('Y-m-d', time()),
					'hour' => date('H', time()),
					'minutes' => date('i', time()),
					'is_hold_copyright' => 'on',
				])
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	private function update_track($album_id, $file_id, $title, $description = '', $content = '', $author = '') {
		if (!$description) $description = $content;
		$this->dom->load('http://www.ximalaya.com/edit_track/' . $file_id . '/update', [
			'curl' => [
				CURLOPT_POST => 1,
				CURLOPT_COOKIEFILE => $this->cookie_file,
				CURLOPT_REFERER => 'https://www.ximalaya.com/passport/login',
				CURLOPT_POSTFIELDS => [
					'sound_image[]' => '0',
					'album_id' => $album_id,
					'soundInfo.sound_title' => $title,
					'soundInfo.sound_intro' => $description,
					'soundInfo.sound_rich_intro' => $content,
					'soundInfo.sound_author' => $author,
					'soundInfo.sound_announcer' => $author,
					'soundInfo.sound_lyric' => $description,
					'activity_id' => '0',
					'is_hold_copyright' => 'on',
				]
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	private function get_track_id_by_title($title) {
		$this->dom->load('http://www.ximalaya.com/center/voice', [
			'curl' => [
				CURLOPT_COOKIEFILE => $this->cookie_file
			]
		]);
		if (strstr($this->dom->find('title')->innerHtml, '登录')) $this->error(50, '登录过期');
		$title_dom = $this->dom->find('#trackList .album-msg a[title="'.$title.'"]', 0);
		$id_dom = $title_dom->parent;
		return $id_dom->sound_id;
	}

	private function get_track_info($track_id, $album_id = 0) {
		$this->dom->load('https://www.ximalaya.com/revision/seo/getTdk?typeName=TRACK&uri=%2Fkeji%2F' . $album_id . '%2F' . $track_id, [
			'curl' => [
				CURLOPT_COOKIEFILE => $this->cookie_file
			]
		]);
		return json_decode($this->dom->outerHtml, true);
	}

	private function error($ret, $msg) {
		switch ($ret) {
			case 50:
				$this->login();
				break;
			default:
				// echo $msg;
				break;
		}
		$this->log($this->post_ID, false, array('ret'=>$ret, 'msg'=>$msg));
		exit;
	}

	private function log($post_ID, $success = false, $log_obj = array()) {
		if (!is_array($log_obj)) $log_obj = array();
		$log_obj['success'] = $success ? 1 : 0;
		$log_obj['date'] = time();
		$ximalaya_sync_log = get_post_meta( $post_ID, '_ximalaya_sync_log', true );
		if (!is_array($ximalaya_sync_log)) $ximalaya_sync_log = array();
		array_push($ximalaya_sync_log, $log_obj);
		update_post_meta( $post_ID, '_ximalaya_sync_log', $ximalaya_sync_log );
		return $ximalaya_sync_log;
	}

	private function get_cookie_file() {
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$this->cookie_file = $options['ximalaya']['cookie_file'];
		if (!$this->cookie_file || !file_exists($this->cookie_file)) $this->create_cookie_file();
		return $this->cookie_file;
	}
	private function create_cookie_file() {
		$this->cookie_file = tempnam('./tmp','cookie');
		$options = get_option( ASYNC_PLUGIN_OPTIONNAME );
		$options['ximalaya']['cookie_file'] = $this->cookie_file;
		update_option( ASYNC_PLUGIN_OPTIONNAME, $options );
		return $this->cookie_file;
	}
	private function getCookieFromFile($cookie_file) {
		$lines = file($cookie_file);
		$cookies = array();
		foreach($lines as $line) {
			if($line[0] != '#' && substr_count($line, "\t") == 6) {
				$tokens = explode("\t", $line);
				$tokens = array_map('trim', $tokens);
				$tokens[4] = date('Y-m-d h:i:s', $tokens[4]);
				$cookies[ $tokens[5] ] = $tokens[6];
			}
		}
		return $cookies;
	}
}