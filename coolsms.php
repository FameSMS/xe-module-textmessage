<?php

/**
 *
 *   Copyright (C) 2008-2015 NURIGO
 *   http://www.coolsms.co.kr
 *
 **/
class coolsms
{
	public static $host = "https://solapi.com";
	public static $api_version = '3';
	private static $api_key;
	private static $api_secret;
	// Access Token is not working as now.
	private static $access_token;
	private static $resource;
	private static $result;
	private static $basecamp;
	private static $content;
	private static $user_agent;
	private static $date;
	private static $salt;
	private static $signature;
	public static $api_name = 'GroupMessage';
	public static $error_flag = false;

	// This is for KaKao
	public static $atHost = 'http://api.coolsms.co.kr/';

	private static $atResource;
	private static $atPath;
	private static $atMethod;
	private static $atVersion = '1.6';
	private static $atSdkVersion = '1.1';
	private static $atSalt;
	private static $atTimestamp;
	private static $atContent;

	/**
	 * @brief construct
	 */
	public function __construct($api_key, $api_secret, $basecamp = false, $access_token = null)
	{
		if($basecamp)
		{
			self::$access_token = $access_token;
			self::$basecamp = true;
		}
		else
		{
			self::$api_key = $api_key;
		}

		self::$api_secret = $api_secret;
		self::$user_agent = $_SERVER['HTTP_USER_AGENT'];
	}


	/**
	 * @brief process curl
	 */
	public static function curlProcess()
	{
		$ch = curl_init();
		$url = sprintf("%s/%s/%s/%s", self::$host, self::$api_name, self::$api_version, self::$resource);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		$header = array(
			"Content-Type: application/json",
			'Authorization: HMAC-MD5 ApiKey='.self::$api_key.', Date='.self::$date.', Salt='.self::$salt.', Signature='.self::$signature,
		);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POSTFIELDS, self::$content);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		self::$result = json_decode(curl_exec($ch));
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code != 200)
		{
			self::$error_flag = true;
		}

		// Check connect errors
		if(curl_errno($ch))
		{
			self::$error_flag = true;
			self::$result = curl_error($ch);
		}

		curl_close($ch);
	}



	/**
	 * make a signature with hash_hamac then return the signature
	 */
	private static function getSignature($date, $salt)
	{
		return hash_hmac('md5', $date . $salt, self::$api_secret);
	}

	/**
	 * @brief return result
	 */
	public static function getResult()
	{
		return self::$result;
	}

	/**
	 * @POST send method
	 * @param $options (options must contain api_key, salt, signature, to, from, text)
	 * @type, image, refname, country, datetime, mid, gid, subject, charset (optional)
	 * @returns object(recipient_number, group_id, message_id, result_code, result_message)
	 */
	public static function send($options)
	{
		$options->type = strtolower($options->type);

		if(in_array($options->type, array('ata', 'cta')))
		{
			if (!$options->kakaoOptions->senderKey)
			{
				$options->type = 'sms';
				unset($options->kakaoOptions);
			}
			else if ($options->type === 'ata')
			{
				if(!$options->kakaoOptions->template_code)
				{
					$options->type = 'sms';
					unset($options->kakaoOptions);
				}
			}
		}
		$options = self::setSmsData($options);

		return self::$result;
	}

	protected static function setSmsData($options)
	{

		$createOutput = self::createGroup($options);



		if($createOutput)
		{
			$options->groupId = $createOutput->groupId;
			if($options->extension)
			{
				$addGroupMessageOutput = self::fixExtensionAddGroupMessage($options);
			}
			else
			{
				$addGroupMessageOutput = self::addGroupMessage($options);
			}


			$sendGroupMessageOutput = self::sendGroupMessage($options->groupId);
		}

		return true;
	}

	/**
	 * Add to Group message for purplebook module.
	 * @param $options
	 * @return bool
	 */
	protected static function fixExtensionAddGroupMessage($options)
	{
		if(!$options->extension)
		{
			return false;
		}

		if (!isset($options->groupId))
		{
			return false;
		}

		$extension = json_decode($options->extension);
		$args = new \stdClass();
		$args->messages = array();
		foreach ($extension as $key => $value)
		{
			if(!$value->to)
			{
				continue;
			}

			$args->messages[$key] = new \stdClass();
			$sendNumber = explode(',', $value->to);
			$args->messages[$key]->to = new \stdClass();
			$args->messages[$key]->to->recipients = $sendNumber;
			$args->messages[$key]->from = $options->from;
			$args->messages[$key]->text = $value->text;
			if ($options->type)
			{
				$args->messages[$key]->type = $options->type;
			}
			else
			{
				$args->messages[$key]->type = 'SMS';
			}

			if ($options->country)
			{
				$args->messages[$key]->country = $options->country;
			}

			if ($options->subject)
			{
				$args->messages[$key]->subject = $options->subject;
			}

			if ($options->imageId)
			{
				$args->messages[$key]->imageId = $options->imageId;
			}

			if ($options->kakaoOptions)
			{
				$args->messages[$key]->kakaoOptions = new \stdClass();
				if ($options->kakaoOptions->senderKey)
				{
					$args->messages[$key]->kakaoOptions->senderKey = $options->kakaoOptions->senderKey;
				}

				if ($options->kakaoOptions->templateCode)
				{
					$args->messages[$key]->kakaoOptions->templateCode = $options->kakaoOptions->templateCode;
				}

				if ($options->kakaoOptions->buttonName)
				{
					$args->messages[$key]->kakaoOptions->buttonName = $options->kakaoOptions->buttonName;
				}

				if ($options->kakaoOptions->buttonUrl)
				{
					$args->messages[$key]->kakaoOptions->buttonUrl = $options->kakaoOptions->buttonUrl;
				}
			}
		}
		$encoding_json_data = json_encode($args);
		$obj = new stdClass();
		$obj->encoding_json_data = $encoding_json_data;
		return self::smsRequest(sprintf('group/%s/addMessages', $options->groupId), $obj);
	}

	/**
	 * @param $options
	 * @return bool|object
	 */
	public static function createGroup($options)
	{
		return self::smsRequest('createGroup', $options);
	}

	public static function addGroupMessage($options)
	{
		if (!isset($options->groupId) || !isset($options->to) || !isset($options->text) || !isset($options->from))
		{
			return false;
		}
		$args = new \stdClass();
		$args->messages = array();
		$args->messages[0] = new \stdClass();
		$sendNumber = explode(',', $options->to);
		$args->messages[0]->to = new \stdClass();
		$args->messages[0]->to->recipients = $sendNumber;
		$args->messages[0]->from = $options->from;
		$args->messages[0]->text = $options->text;
		if ($options->type)
		{
			$args->messages[0]->type = $options->type;
		}
		else
		{
			$args->messages[0]->type = 'SMS';
		}

		if ($options->country)
		{
			$args->messages[0]->country = $options->country;
		}

		if ($options->subject)
		{
			$args->messages[0]->subject = $options->subject;
		}

		if ($options->imageId)
		{
			$args->messages[0]->imageId = $options->imageId;
		}

		if ($options->kakaoOptions)
		{
			$args->messages[0]->kakaoOptions = new \stdClass();
			if ($options->kakaoOptions->senderKey)
			{
				$args->messages[0]->kakaoOptions->senderKey = $options->kakaoOptions->senderKey;
			}

			if ($options->kakaoOptions->templateCode)
			{
				$args->messages[0]->kakaoOptions->templateCode = $options->kakaoOptions->templateCode;
			}

			if ($options->kakaoOptions->buttonName)
			{
				$args->messages[0]->kakaoOptions->buttonName = $options->kakaoOptions->buttonName;
			}

			if ($options->kakaoOptions->buttonUrl)
			{
				$args->messages[0]->kakaoOptions->buttonUrl = $options->kakaoOptions->buttonUrl;
			}
		}

		$encoding_json_data = json_encode($args);
		$obj = new stdClass();
		$obj->encoding_json_data = $encoding_json_data;
		return self::smsRequest(sprintf('group/%s/addMessages', $options->groupId), $obj);
	}

	protected static function sendGroupMessage($groupId)
	{
		if(!$groupId)
		{
			return false;
		}

		$obj = new stdClass();
		$obj->encoding_json_data = null;
		return self::smsRequest(sprintf('group/%s/sendMessages', $groupId), $obj);
	}

	/**
	 * @param $resource
	 * @param $options
	 * @return bool|object
	 */
	protected static function smsRequest($resource, $options)
	{
		if(!$resource)
		{
			return false;
		}

		self::setResource($resource);

		$options = self::addInfos($options);
		self::setContent($options);
		self::curlProcess();
		return self::getResult();
	}

	private static function setContent($options)
	{
		if ($options->encoding_json_data)
		{
			if ($options->json_option == 'SimpleMessage')
			{
				self::setApiConfig('SimpleMessage', '3');
			}
			self::$content = $options->encoding_json_data;
			return;
		}

		self::$content = new \stdClass;
		if ($options->json_option)
		{
			$json_option = $options->json_option;
		}
		else
		{
			$json_option = 'groupOptions';
		}
		self::$content->$json_option = new \stdClass;

		foreach ($options as $key => $val)
		{
			self::$content->$json_option->$key = $val;
		}
		if ($options->json_option !== 'groupOptions')
		{
			self::$content->$json_option = array(self::$content->$json_option);
		}

		self::$content = json_encode(self::$content);
	}

	protected static function setApiConfig($api_name, $api_version)
	{
		if (!isset($api_name) || !isset($api_version))
		{
			return false;
		}
		self::$api_name = $api_name;
		self::$api_version = $api_version;
	}

	protected static function setResource($resource, $is_post = false)
	{
		self::$resource = $resource;
	}

	/**
	 * @GET sent method
	 * @param $options (options can be optional)
	 * @count,  page, s_rcpt, s_start, s_end, mid, gid (optional)
	 * @returns object(total count, list_count, page, data['type', 'accepted_time', 'recipient_number', 'group_id', 'message_id', 'status', 'result_code', 'result_message', 'sent_time', 'text'])
	 */
	public static function sent($options = null)
	{
		if(!$options)
		{
			$options = new stdClass();
		}
		self::setAtMethod('sms', 'sent', 0);
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * @POST cancel method
	 * @options must contain api_key, salt, signature
	 * @mid, gid (either one must be entered.)
	 */
	public static function cancel($options)
	{
		self::setAtMethod('sms', 'cancel', 1);
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * @GET balance method
	 * @options must contain api_key, salt, signature
	 * @return object(cash, point)
	 */
	public static function balance()
	{
		self::setAtMethod('sms', 'balance', 0);
		self::addAtInfos($options = new stdClass());
		return self::$result;
	}

	/**
	 * @GET status method
	 * @options must contain api_key, salt, signature
	 * @return object(registdate, sms_average, sms_sk_average, sms_kt_average, sms_lg_average, mms_average, mms_sk_average, mms_kt_average, mms_lg_average)
	 *   this method is made for Coolsms inc. internal use
	 */
	public static function status($options)
	{
		self::setAtMethod('sms', 'status', 0);
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * @POST register method
	 * @options must contains api_key, salt, signature, phone, site_user(optional)
	 * @return object(handle_key, ars_number)
	 */
	public static function register($options)
	{
		self::setAtMethod('senderid', 'register', 1, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * @POST verify method
	 * @options must contains api_key, salt, signature, handle_key
	 * return nothing
	 */
	public static function verify($options)
	{
		self::setAtMethod('senderid', 'verify', 1, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * POST delete method
	 * $options must contains api_key, salt, signature, handle_key
	 * return nothing
	 */
	public static function delete($options)
	{
		self::setAtMethod('senderid', 'delete', 1, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * GET list method
	 * $options must conatins api_key, salt, signature, site_user(optional)
	 * return json object(idno, phone_number, flag_default, updatetime, regdate)
	 */
	public static function get_senderid_list($options = null)
	{
		self::setAtMethod('senderid', 'list', 0, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * POST set_default
	 * $options must contains api_key, salt, signature, handle_key, site_user(optional)
	 * return nothing
	 */
	public static function set_default($options)
	{
		self::setAtMethod('senderid', 'set_default', 1, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * GET get_default
	 * $options must conatins api_key, salt, signature, site_user(optional)
	 * return json object(handle_key, phone_number)
	 */
	public static function get_default($options)
	{
		self::setAtMethod('senderid', 'get_default', 0, "1.1");
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * POST register alimtalk
	 * options must contain api_key, salt, signature, yellow_id, templates
	 * return json array(request template list)
	 */
	public static function register_alimtalk($options)
	{
		self::setAtMethod('alimtalk', 'register', 1, '1');
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * POST get alimtalk templates
	 * options must contain api_key, salt, signature, yellow_id
	 * return json array(request template list)
	 */
	public static function get_alimtalk_templates($options)
	{
		self::setAtMethod('alimtalk', "templates/{$options->yellow_id}", 0, '1');
		self::addAtInfos($options);
		return self::$result;
	}

	/**
	 * return user's current OS
	 */
	public static function getOS()
	{
		$user_agent = self::$user_agent;
		$os_platform = "Unknown OS Platform";
		$os_array = array(
			'/windows nt 10/i' => 'Windows 10',
			'/windows nt 6.3/i' => 'Windows 8.1',
			'/windows nt 6.2/i' => 'Windows 8',
			'/windows nt 6.1/i' => 'Windows 7',
			'/windows nt 6.0/i' => 'Windows Vista',
			'/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
			'/windows nt 5.1/i' => 'Windows XP',
			'/windows xp/i' => 'Windows XP',
			'/windows nt 5.0/i' => 'Windows 2000',
			'/windows me/i' => 'Windows ME',
			'/win98/i' => 'Windows 98',
			'/win95/i' => 'Windows 95',
			'/win16/i' => 'Windows 3.11',
			'/macintosh|mac os x/i' => 'Mac OS X',
			'/mac_powerpc/i' => 'Mac OS 9',
			'/linux/i' => 'Linux',
			'/ubuntu/i' => 'Ubuntu',
			'/iphone/i' => 'iPhone',
			'/ipod/i' => 'iPod',
			'/ipad/i' => 'iPad',
			'/android/i' => 'Android',
			'/blackberry/i' => 'BlackBerry',
			'/webos/i' => 'Mobile'
		);

		foreach($os_array as $regex => $value)
		{
			if(preg_match($regex, $user_agent))
			{
				$os_platform = $value;
			}
		}
		return $os_platform;
	}

	/**
	 * return user's current browser
	 */
	public static function getBrowser()
	{
		$user_agent = self::$user_agent;
		$browser = "Unknown Browser";
		$browser_array = array(
			'/msie/i' => 'Internet Explorer',
			'/firefox/i' => 'Firefox',
			'/safari/i' => 'Safari',
			'/chrome/i' => 'Chrome',
			'/opera/i' => 'Opera',
			'/netscape/i' => 'Netscape',
			'/maxthon/i' => 'Maxthon',
			'/konqueror/i' => 'Konqueror',
			'/mobile/i' => 'Handheld Browser'
		);

		foreach($browser_array as $regex => $value)
		{
			if(preg_match($regex, $user_agent))
			{
				$browser = $value;
			}
		}
		return $browser;
	}

	/**
	 * Add to sns info for REST API V3.
	 * @param $options
	 * @return stdClass
	 */
	public static function addInfos($options)
	{
		if (!isset($options)) $options = new \stdClass();
		if (!isset($options->appId)) $options->appId = null;
		if (!isset($options->devLanguage)) $options->devLanguage = sprintf("PHP REST API %s", self::$api_version);
		if (!isset($options->osPlatform)) $options->osPlatform = self::getOS();
		if (!isset($options->sdkVersion)) $options->sdkVersion = sprintf("PHP SDK %s", phpversion());
		if (!isset($options->appVersion)) $options->appVersion = null;
		$options->siteUser = '__private__';
		$options->mode = 'real';
		$options->forceSms = 'false';
		$options->onlyAta = 'false';
		$options->country = '82';
		$options->subject = '';

		// set salt & timestamp
		$options->salt = uniqid();
		$options->date = date('Y-m-d H:i:s');
		self::$salt = $options->salt;
		self::$date = $options->date;
		// If basecamp is true '$coolsms_user' use
		isset(self::$basecamp) ? $options->coolsms_user = self::$api_key : $options->api_key = self::$api_key;

		$options->signature = self::getSignature($options->date, $options->salt);
		self::$signature = $options->signature;

		return $options;
	}

	/**
	 * Set authentivate information for kakao.
	 * @param $options
	 */
	private static function addAtInfos($options)
	{
		self::$atSalt = uniqid();
		self::$atTimestamp = (string)time();
		if(!$options->User_Agent)
		{
			$options->User_Agent = sprintf("PHP REST API %s", self::$atVersion);
		}
		if(!$options->os_platform)
		{
			$options->os_platform = self::getOS();
		}
		if(!$options->dev_lang)
		{
			$options->dev_lang = sprintf("PHP %s", phpversion());
		}
		if(!$options->sdk_version)
		{
			$options->sdk_version = sprintf("PHP SDK %s", self::$atSdkVersion);
		}
		$options->salt = self::$atSalt;
		$options->timestamp = self::$atTimestamp;

		$options->api_key = self::$api_key;

		$options->signature = self::kakaoGetSignature();
		self::setAtContent($options);
		self::kakaoCurlProcess();
	}
	private static function kakaoGetSignature()
	{
		return hash_hmac('md5', (string)self::$atTimestamp . self::$atSalt, self::$api_secret);
	}

	private static function setAtMethod($resource, $path, $method, $version = "1.6")
	{
		self::$atResource = $resource;
		self::$atPath = $path;
		self::$atMethod = $method;
		self::$atVersion = $version;
	}
	/**
	 * set http body content
	 */
	private static function setAtContent($options)
	{
		if(self::$atMethod)
		{
			self::$atContent = array();
			foreach($options as $key => $val)
			{
				if($key != "image")
				{
					self::$atContent[$key] = sprintf("%s", $val);
				}
				else
				{
					self::$atContent[$key] = "@" . realpath("./$val");
				}
			}
		}
		else
		{
			foreach($options as $key => $val)
			{
				self::$atContent .= $key . "=" . urlencode($val) . "&";
			}
		}
	}

	private static function kakaoCurlProcess()
	{
		$ch = curl_init();
		// Set host. 1 = POST , 0 = GET
		if(self::$atMethod == 1)
		{
			$host = sprintf("%s%s/%s/%s", self::$atHost, self::$atResource, self::$atVersion, self::$atPath);
		}
		else
		{
			$host = sprintf("%s%s/%s/%s?%s", self::$atHost, self::$atResource, self::$atVersion, self::$atPath, self::$atContent);
		}
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3); // SSL 버젼 (https 접속시에 필요)
		curl_setopt($ch, CURLOPT_HEADER, 0); // 헤더 출력 여부
		curl_setopt($ch, CURLOPT_POST, self::$atMethod); // Post Get 접속 여부
		// Set POST DATA
		if(self::$atMethod)
		{
			$header = array("Content-Type:multipart/form-data");
			// route가 있으면 header에 붙여준다.
			if(self::$atContent['route'])
			{
				$header[] = "User-Agent:" . self::$atContent['route'];
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_POSTFIELDS, self::$atContent);
		}
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); // TimeOut 값
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 결과값을 받을것인지
		self::$result = json_decode(curl_exec($ch));
		// unless http status code is 200. throw exception.
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code != 200)
		{
			self::$error_flag = true;
		}
		// Check connect errors
		if(curl_errno($ch))
		{
			self::$error_flag = true;
			self::$result = curl_error($ch);
		}
		curl_close($ch);
	}
}
