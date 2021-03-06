<?php
/**
 * Filename.......: class.html.mime.mail.inc
 * Project........: HTML Mime mail class
 * Last Modified..: $Date: 2002/07/24 13:14:10 $
 * CVS Revision...: $Revision: 1.4 $
 * Copyright......: 2001, 2002 Richard Heyes
 */

require_once(dirname(__FILE__) . '/mimePart.php');

class htmlMimeMail
{
	/**
	 * The html part of the message
	 *
	 * @var string
	 */
	var $html;

	/**
	 * The text part of the message(only used in TEXT only messages)
	 *
	 * @var string
	 */
	var $text;

	/**
	 * The main body of the message after building
	 *
	 * @var string
	 */
	var $output;

	/**
	 * The alternative text to the HTML part (only used in HTML messages)
	 *
	 * @var string
	 */
	var $html_text;

	/**
	 * An array of embedded images/objects
	 *
	 * @var array
	 */
	var $html_images;

	/**
	 * An array of recognised image types for the findHtmlImages() method
	 *
	 * @var array
	 */
	var $image_types;

	/**
	 * Parameters that affect the build process
	 *
	 * @var array
	 */
	var $build_params;

	/**
	 * Array of attachments
	 *
	 * @var array
	 */
	var $attachments;

	/**
	 * The main message headers
	 *
	 * @var array
	 */
	var $headers;

	/**
	 * Whether the message has been built or not
	 *
	 * @var boolean
	 */
	var $is_built;

	/**
	 * The return path address. If not set the From:
	 * address is used instead
	 *
	 * @var string
	 */
	var $return_path;

	/**
	 * Array of information needed for smtp sending
	 *
	 * @var array
	 */
	var $smtp_params;

	/**
	 * Constructor function. Sets the headers
	 * if supplied.
	 */

	public function __construct()
	{
		/**
		 * Initialise some variables.
		 */
		$this->html_images = array();
		$this->headers     = array();
		$this->is_built    = false;

		/**
		 * If you want the auto load functionality
		 * to find other image/file types, add the
		 * extension and content type here.
		 */
		$this->image_types = array(
			'gif'  => 'image/gif',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'bmp'  => 'image/bmp',
			'png'  => 'image/png',
			'tif'  => 'image/tiff',
			'tiff' => 'image/tiff',
			'swf'  => 'application/x-shockwave-flash',
		);

		/**
		 * Set these up
		 */
		$this->build_params['html_encoding'] = 'quoted-printable';
		$this->build_params['text_encoding'] = '7bit';
		$this->build_params['html_charset']  = 'ISO-8859-1';
		$this->build_params['text_charset']  = 'ISO-8859-1';
		$this->build_params['head_charset']  = 'ISO-8859-1';
		$this->build_params['text_wrap']     = 998;

		/**
		 * Defaults for smtp sending
		 */
		if (!empty($GLOBALS['HTTP_SERVER_VARS']['HTTP_HOST']))
		{
			$helo = $GLOBALS['HTTP_SERVER_VARS']['HTTP_HOST'];
		}
		elseif (!empty($GLOBALS['HTTP_SERVER_VARS']['SERVER_NAME']))
		{
			$helo = $GLOBALS['HTTP_SERVER_VARS']['SERVER_NAME'];
		}
		else
		{
			$helo = 'localhost';
		}

		$this->smtp_params['host'] = 'localhost';
		$this->smtp_params['port'] = 25;
		$this->smtp_params['helo'] = $helo;
		$this->smtp_params['auth'] = false;
		$this->smtp_params['user'] = '';
		$this->smtp_params['pass'] = '';

		/**
		 * Make sure the MIME version header is first.
		 */
		$this->headers['MIME-Version'] = '1.0';
	}

	/**
	 * This function will read a file in
	 * from a supplied filename and return
	 * it. This can then be given as the first
	 * argument of the the functions
	 * add_html_image() or add_attachment().
	 */
	public function getFile($filename)
	{
		$return = '';
		if ($fp = fopen($filename, 'rb'))
		{
			while (!feof($fp))
			{
				$return .= fread($fp, 1024);
			}
			fclose($fp);

			return $return;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Accessor to set the CRLF style
	 */
	public function setCrlf($crlf = "\n")
	{
		if (!defined('CRLF'))
		{
			define('CRLF', $crlf, true);
		}

		if (!defined('MAIL_MIMEPART_CRLF'))
		{
			define('MAIL_MIMEPART_CRLF', $crlf, true);
		}
	}

	/**
	 * Accessor to set the SMTP parameters
	 */
	public function setSMTPParams($host = null, $port = null, $helo = null, $auth = null, $user = null, $pass = null)
	{
		if (!is_null($host))
		{
			$this->smtp_params['host'] = $host;
		}
		if (!is_null($port))
		{
			$this->smtp_params['port'] = $port;
		}
		if (!is_null($helo))
		{
			$this->smtp_params['helo'] = $helo;
		}
		if (!is_null($auth))
		{
			$this->smtp_params['auth'] = $auth;
		}
		if (!is_null($user))
		{
			$this->smtp_params['user'] = $user;
		}
		if (!is_null($pass))
		{
			$this->smtp_params['pass'] = $pass;
		}
	}

	/**
	 * Accessor function to set the text encoding
	 */
	public function setTextEncoding($encoding = '7bit')
	{
		$this->build_params['text_encoding'] = $encoding;
	}

	/**
	 * Accessor function to set the HTML encoding
	 */
	public function setHtmlEncoding($encoding = 'quoted-printable')
	{
		$this->build_params['html_encoding'] = $encoding;
	}

	/**
	 * Accessor function to set the text charset
	 */
	public function setTextCharset($charset = 'ISO-8859-1')
	{
		$this->build_params['text_charset'] = $charset;
	}

	/**
	 * Accessor function to set the HTML charset
	 */
	public function setHtmlCharset($charset = 'ISO-8859-1')
	{
		$this->build_params['html_charset'] = $charset;
	}

	/**
	 * Accessor function to set the header encoding charset
	 */
	public function setHeadCharset($charset = 'ISO-8859-1')
	{
		$this->build_params['head_charset'] = $charset;
	}

	/**
	 * Accessor function to set the text wrap count
	 */
	public function setTextWrap($count = 998)
	{
		$this->build_params['text_wrap'] = $count;
	}

	/**
	 * Accessor to set a header
	 */
	public function setHeader($name, $value)
	{
		$this->headers[$name] = $value;
	}

	/**
	 * Accessor to add a Subject: header
	 */
	public function setSubject($subject)
	{
		$this->headers['Subject'] = $subject;
	}

	/**
	 * Accessor to add a From: header
	 */
	public function setFrom($from)
	{
		$this->headers['From'] = $from;
	}

	/**
	 * Accessor to set the return path
	 */
	public function setReturnPath($return_path)
	{
		$this->return_path = $return_path;
	}

	/**
	 * Accessor to add a Cc: header
	 */
	public function setCc($cc)
	{
		$cc                  = str_replace(';', ',', $cc);
		$this->headers['Cc'] = $cc;
	}

	/**
	 * Accessor to add a Bcc: header
	 */
	public function setBcc($bcc)
	{
		$this->headers['Bcc'] = $bcc;
	}

	/**
	 * Adds plain text. Use this function
	 * when NOT sending html email
	 */
	public function setText($text = '')
	{
		$this->text = $text;
	}

	/**
	 * Adds a html part to the mail.
	 * Also replaces image names with
	 * content-id's.
	 */
	public function setHtml($html, $text = null,
		$images_dir = null, $TempImagesDir = '',
		$RootPath = '', $RootURLs = array())
	{
		$this->html      = $html;
		$this->html_text = $text;

		if (isset($images_dir))
		{
			$this->_findHtmlImages($images_dir, $TempImagesDir, $RootPath, $RootURLs);
		}
	}

	protected function _findHtmlImages($images_dir, $TempImagesDir,
		$RootPath, $RootURLs)
	{
		$extensions  = array();
		$html_images = array();

		// Build the list of image extensions
		while (list($key,) = each($this->image_types))
		{
			$extensions[] = $key;
		}

		$original_html = $this->html;

		$images              = array();
		$imagesWithOutFormat = array();

		preg_match_all('/(?:"|\')([^"\']+\.(' . implode('|', $extensions) . '))(?:"|\')/Ui', $this->html, $images);
		preg_match_all('/(?:"|\')([^"\']+(candidate_picture|user_picture)[^"\']+)(?:"|\')/Ui', $this->html, $imagesWithOutFormat);

		for ($i = 0; $i < count($imagesWithOutFormat[1]); $i++)
		{
			$html_imagesWithOutFormat[basename($imagesWithOutFormat[1][$i])] = array('original_url'  => $imagesWithOutFormat[1][$i],
																					 'path_on_disk'  => $imagesWithOutFormat[1][$i],
																					 'reduced_image' => false);
			$this->html                                                      = str_replace($imagesWithOutFormat[1][$i], basename($imagesWithOutFormat[1][$i]), $this->html);
		}


		if (!empty($html_imagesWithOutFormat))
		{
			ksort($html_imagesWithOutFormat);

			foreach ($html_imagesWithOutFormat as $anImage)
			{
				if ($anImage['reduced_image'] !== false)
				{
					$Filename = $anImage['reduced_image'];
				}
				else
				{
					$Filename = $anImage['path_on_disk'];
				}

				if ($image = $this->getFile($Filename))
				{
					$this->addHtmlImage($image, basename($Filename));
				}
			}
		}

		for ($i = 0; $i < count($images[1]); $i++)
		{
			$bImageFoundOnDisk = false;
			foreach ($RootURLs as $rootURL)
			{
				$imagePathOnDisk = str_replace($rootURL, $RootPath, $images[1][$i]);

				if (file_exists($imagePathOnDisk))
				{
					$html_images[basename($images[1][$i])] = array('original_url'  => $images[1][$i],
																   'path_on_disk'  => $imagePathOnDisk,
																   'reduced_image' => false);
					$this->html                            = str_replace($images[1][$i], basename($images[1][$i]), $this->html);
					$bImageFoundOnDisk                     = true;
					break;
				}
			}

			if (!$bImageFoundOnDisk && file_exists($images_dir . $images[1][$i]))
			{
				$html_images[basename($images[1][$i])] = array('original_url'  => $images[1][$i],
															   'path_on_disk'  => $images_dir . $images[1][$i],
															   'reduced_image' => false);
				$this->html                            = str_replace($images[1][$i], basename($images[1][$i]), $this->html);
			}
		}

		if (!empty($html_images))
		{
			ksort($html_images);

			// now find <img tags, check if one of the images is in it, and find if there is a width/height parameter

			$matches = array();
			preg_match_all('/<img.*(?:"|\')([^"\']+\.(' . implode('|', $extensions) . '))(?:"|\').*>/Ui', $original_html, $matches);

			$ReducedImages = array();

			foreach ($matches[0] as $k => $imageTag)
			{
				$imageSrc = $matches[1][$k];

				if (in_array(basename($imageSrc), array_keys($html_images)))
				{
					// the image exists, we check the width and height
					$parts = array();
					if (!preg_match('/width=(?:"|\')([^"\']+)(?:"|\').*/i', $imageTag, $parts))
					{
						continue;
					} // with the next image;

					if (!preg_match('/height=(?:"|\')([^"\']+)(?:"|\').*/i', $imageTag, $parts))
					{
						continue;
					} // with the next image;

					$reducedFileName = $TempImagesDir . basename($imageSrc);

					if (file_exists($reducedFileName))
					{
						$html_images[basename($imageSrc)]['reduced_image'] = $reducedFileName;
					}
				}
			}

			foreach ($html_images as $anImage)
			{
				if ($anImage['reduced_image'] !== false)
				{
					$Filename = $anImage['reduced_image'];
				}
				else
				{
					$Filename = $anImage['path_on_disk'];
				}

				if ($image = $this->getFile($Filename))
				{
					$ext          = substr($Filename, strrpos($Filename, '.') + 1);
					$content_type = $this->image_types[strtolower($ext)];
					$this->addHtmlImage($image, basename($Filename), $content_type);
				}
			}
		}
	}

	/**
	 * Adds an image to the list of embedded
	 * images.
	 */
	public function addHtmlImage($file, $name = '', $c_type = 'application/octet-stream')
	{
		$cid = md5(uniqid(time()));

		$this->html_images[] = array(
			'body'   => $file,
			'name'   => $name,
			'c_type' => $c_type,
			'cid'    => $cid,
		);

		return $cid;
	}

	/**
	 * Adds a file to the list of attachments.
	 */
	public function addAttachment($file, $name = '', $c_type = 'application/octet-stream', $encoding = 'base64')
	{
		$this->attachments[] = array(
			'body'     => $file,
			'name'     => $name,
			'c_type'   => $c_type,
			'encoding' => $encoding,
		);
	}

	/**
	 * Adds a text subpart to a mime_part object
	 */
	protected function &_addTextPart(&$obj, $text)
	{
		$params['charset']      = $this->build_params['text_charset'];
		$params['content_type'] = 'text/plain';
		$params['encoding']     = $this->build_params['text_encoding'];
		if (is_object($obj))
		{
			return $obj->addSubpart($text, $params);
		}
		else
		{
			$NewMailMimePart = new Mail_mimePart($text, $params);

			return $NewMailMimePart;
		}
	}

	/**
	 * Adds a html subpart to a mime_part object
	 */
	protected function &_addHtmlPart(&$obj)
	{
		$params['charset']      = $this->build_params['html_charset'];
		$params['content_type'] = 'text/html';
		$params['encoding']     = $this->build_params['html_encoding'];
		if (is_object($obj))
		{
			return $obj->addSubpart($this->html, $params);
		}
		else
		{
			$NewMailMimePart = new Mail_mimePart($this->html, $params);

			return $NewMailMimePart;
		}
	}

	/**
	 * Starts a message with a mixed part
	 */
	protected function &_addMixedPart()
	{
		$params['charset']      = $this->build_params['head_charset'];
		$params['content_type'] = 'multipart/mixed';
		$NewMailMimePart        = new Mail_mimePart('', $params);

		return $NewMailMimePart;
	}

	/**
	 * Adds an alternative part to a mime_part object
	 */
	protected function &_addAlternativePart(&$obj)
	{
		$params['charset']      = $this->build_params['head_charset'];
		$params['content_type'] = 'multipart/alternative';
		if (is_object($obj))
		{
			return $obj->addSubpart('', $params);
		}
		else
		{
			$NewMailMimePart = new Mail_mimePart('', $params);

			return $NewMailMimePart;
		}
	}

	/**
	 * Adds a html subpart to a mime_part object
	 */
	protected function &_addRelatedPart(&$obj)
	{
		$params['charset']      = $this->build_params['head_charset'];
		$params['content_type'] = 'multipart/related';
		if (is_object($obj))
		{
			return $obj->addSubpart('', $params);
		}
		else
		{
			$NewMailMimePart = new Mail_mimePart('', $params);

			return $NewMailMimePart;
		}
	}

	/**
	 * Adds an html image subpart to a mime_part object
	 */
	protected function _addHtmlImagePart(&$obj, $value)
	{
		$params['charset']      = $this->build_params['head_charset'];
		$params['content_type'] = $value['c_type'];
		$params['encoding']     = 'base64';
		$params['disposition']  = 'attachment';
		$params['dfilename']    = $value['name'];
		$params['cid']          = $value['cid'];
		$obj->addSubpart($value['body'], $params);
	}

	/**
	 * Adds an attachment subpart to a mime_part object
	 */
	protected function _addAttachmentPart(&$obj, $value)
	{
		$params['charset']      = $this->build_params['head_charset'];
		$params['content_type'] = $value['c_type'];
		$params['encoding']     = $value['encoding'];
		$params['disposition']  = 'attachment';
		$params['dfilename']    = $value['name'];
		$obj->addSubpart($value['body'], $params);
	}

	/**
	 * Compare images so that the shortest length filenames appear last
	 */
	public function CompareEmbeddedImages($a, $b)
	{
		return strlen($b['name']) - strlen($a['name']);
	}

	/**
	 * Builds the multipart message from the
	 * list ($this->_parts). $params is an
	 * array of parameters that shape the building
	 * of the message. Currently supported are:
	 *
	 * $params['html_encoding'] - The type of encoding to use on html. Valid options are
	 *                            "7bit", "quoted-printable" or "base64" (all without quotes).
	 *                            7bit is EXPRESSLY NOT RECOMMENDED. Default is quoted-printable
	 * $params['text_encoding'] - The type of encoding to use on plain text Valid options are
	 *                            "7bit", "quoted-printable" or "base64" (all without quotes).
	 *                            Default is 7bit
	 * $params['text_wrap']     - The character count at which to wrap 7bit encoded data.
	 *                            Default this is 998.
	 * $params['html_charset']  - The character set to use for a html section.
	 *                            Default is ISO-8859-1
	 * $params['text_charset']  - The character set to use for a text section.
	 *                          - Default is ISO-8859-1
	 * $params['head_charset']  - The character set to use for header encoding should it be needed.
	 *                          - Default is ISO-8859-1
	 */
	public function buildMessage($params = array())
	{
		if (!empty($params))
		{
			while (list($key, $value) = each($params))
			{
				$this->build_params[$key] = $value;
			}
		}

		if (!empty($this->html_images))
		{
			usort($this->html_images, array($this, 'CompareEmbeddedImages'));

			foreach ($this->html_images as $value)
			{
				$this->html = str_replace($value['name'], 'cid:' . $value['cid'], $this->html);
			}
		}

		$null        = null;
		$attachments = !empty($this->attachments) ? true : false;
		$html_images = !empty($this->html_images) ? true : false;
		$html        = !empty($this->html) ? true : false;
		$text        = isset($this->text) ? true : false;

		switch (true)
		{
			case $text AND !$attachments:
				$message = &$this->_addTextPart($null, $this->text);
				break;

			case !$text AND $attachments AND !$html:
				$message = &$this->_addMixedPart();

				for ($i = 0; $i < count($this->attachments); $i++)
				{
					$this->_addAttachmentPart($message, $this->attachments[$i]);
				}
				break;

			case $text AND $attachments:
				$message = &$this->_addMixedPart();
				$this->_addTextPart($message, $this->text);

				for ($i = 0; $i < count($this->attachments); $i++)
				{
					$this->_addAttachmentPart($message, $this->attachments[$i]);
				}
				break;

			case $html AND !$attachments AND !$html_images:
				if (!is_null($this->html_text))
				{
					$message = &$this->_addAlternativePart($null);
					$this->_addTextPart($message, $this->html_text);
					$this->_addHtmlPart($message);
				}
				else
				{
					$message = &$this->_addHtmlPart($null);
				}
				break;

			case $html AND !$attachments AND $html_images:
				if (!is_null($this->html_text))
				{
					$message = &$this->_addAlternativePart($null);
					$this->_addTextPart($message, $this->html_text);
					$related = &$this->_addRelatedPart($message);
				}
				else
				{
					$message = &$this->_addRelatedPart($null);
					$related = &$message;
				}
				$this->_addHtmlPart($related);
				for ($i = 0; $i < count($this->html_images); $i++)
				{
					$this->_addHtmlImagePart($related, $this->html_images[$i]);
				}
				break;

			case $html AND $attachments AND !$html_images:
				$message = &$this->_addMixedPart();
				if (!is_null($this->html_text))
				{
					$alt = &$this->_addAlternativePart($message);
					$this->_addTextPart($alt, $this->html_text);
					$this->_addHtmlPart($alt);
				}
				else
				{
					$this->_addHtmlPart($message);
				}
				for ($i = 0; $i < count($this->attachments); $i++)
				{
					$this->_addAttachmentPart($message, $this->attachments[$i]);
				}
				break;

			case $html AND $attachments AND $html_images:
				$message = &$this->_addMixedPart();
				if (!is_null($this->html_text))
				{
					$alt = &$this->_addAlternativePart($message);
					$this->_addTextPart($alt, $this->html_text);
					$rel = &$this->_addRelatedPart($alt);
				}
				else
				{
					$rel = &$this->_addRelatedPart($message);
				}
				$this->_addHtmlPart($rel);
				for ($i = 0; $i < count($this->html_images); $i++)
				{
					$this->_addHtmlImagePart($rel, $this->html_images[$i]);
				}
				for ($i = 0; $i < count($this->attachments); $i++)
				{
					$this->_addAttachmentPart($message, $this->attachments[$i]);
				}
				break;
		}

		if (isset($message))
		{
			$output        = $message->encode();
			$this->output  = $output['body'];
			$this->headers = array_merge($this->headers, $output['headers']);

			// Add message ID header
			srand((double)microtime() * 10000000);

			$servername = 'MyServer';
			if (!empty($GLOBALS['HTTP_SERVER_VARS']['HTTP_HOST']))
			{
				$servername = $GLOBALS['HTTP_SERVER_VARS']['HTTP_HOST'];
			}
			else if (!empty($GLOBALS['HTTP_SERVER_VARS']['SERVER_NAME']))
			{
				$servername = $GLOBALS['HTTP_SERVER_VARS']['SERVER_NAME'];
			}

			$message_id                  = sprintf('<%s.%s@%s>', base_convert(time(), 10, 36), base_convert(rand(), 10, 36), $servername);
			$this->headers['Message-ID'] = $message_id;

			$this->is_built = true;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Function to encode a header if necessary
	 * according to RFC2047
	 */
	protected function _encodeHeader($input, $charset = 'ISO-8859-1', $bUseAlternativeEncoding = false)
	{
		if (!$bUseAlternativeEncoding)
		{
			$matches = array();
			preg_match_all('/(\w*[\x80-\xFF]+\w*)/', $input, $matches);
			foreach ($matches[1] as $value)
			{
				$replacement = preg_replace_callback(
					'/([\x80-\xFF])/',
					function ($m)
					{
						return "=" . strtoupper(dechex(ord($m[1])));
					},
					$value
				);
				$input       = str_replace($value, '=?' . $charset . '?Q?' . $replacement . '?=', $input);
			}
		}
		else
		{
			if (!empty($input))
			{
				$input = '=?' . $charset . '?B?' . base64_encode($input) . '?=';
			}
		}

		return $input;
	}

	/**
	 * Sends the mail.
	 *
	 * @param  array  $recipients
	 * @param  string $type OPTIONAL
	 * @return mixed
	 */
	public function send($recipients, $type = 'mail')
	{
		$this->prepareHeaderForAntiSpoofing();

		// Sends mail to the test catch-all sub address or domain.
		if (!empty($GLOBALS['htmlMimeMailTestCatchAll']))
		{
			if (!empty($recipients))
			{
				foreach ($recipients as $k => $aRecipient)
				{
					$recipients[$k] = str_replace('@', '_AT_', $aRecipient) . $GLOBALS['htmlMimeMailTestCatchAll'];
				}
			}
		}

		if (!empty($GLOBALS['htmlMimeMailTestCatchAllUnique']))
		{
			if (!empty($recipients))
			{
				foreach ($recipients as $k => $aRecipient)
				{
					$recipients[$k] = $GLOBALS['htmlMimeMailTestCatchAllUnique'];
				}
			}
		}

		if (!defined('CRLF'))
		{
			$this->setCrlf($type == 'mail' ? "\n" : "\r\n");
		}

		if (!$this->is_built)
		{
			$this->buildMessage();
		}

		if (!empty($GLOBALS['htmlMimeMailTestCcAll']))
		{
			if (empty($this->headers['Cc']))
			{
				$this->headers['Cc'] = $GLOBALS['htmlMimeMailTestCcAll'];
			}
			else
			{
				$this->headers['Cc'] .= ', ' . $GLOBALS['htmlMimeMailTestCcAll'];
			}
		}

		if (!empty($GLOBALS['htmlMimeMailTestReplyToAll']))
		{
			$this->headers['Reply-To'] = $GLOBALS['htmlMimeMailTestReplyToAll'];
		}

		if (!empty($GLOBALS['htmlMimeMailTestFromAll']))
		{
			$this->headers['From'] = $GLOBALS['htmlMimeMailTestFromAll'];
		}

		switch ($type)
		{
			case 'mail':
				$subject = '';
				if (!empty($this->headers['Subject']))
				{
					$subject = $this->_encodeHeader($this->headers['Subject'], $this->build_params['head_charset'], true);
					unset($this->headers['Subject']);
				}

				// Get flat representation of headers
				foreach ($this->headers as $name => $value)
				{
					$headers[] = $name . ': ' . $this->_encodeHeader($value, $this->build_params['head_charset']);
				}

				$to = $this->_encodeHeader(implode(', ', $recipients), $this->build_params['head_charset']);

				if (!empty($this->return_path))
				{
					$result = mail($to, $subject, $this->output, implode(CRLF, $headers), ' -f ' . $this->return_path);
				}
				else
				{
					$result = mail($to, $subject, $this->output, implode(CRLF, $headers));
				}

				// Reset the subject in case mail is resent
				if ($subject !== '')
				{
					$this->headers['Subject'] = $subject;
				}

				// Return
				return $result;

			case 'smtp':
				require_once(dirname(__FILE__) . '/smtp.php');
				require_once(dirname(__FILE__) . '/RFC822.php');

//				$smtp = &smtp::connect($this->smtp_params);
				$smtp            = smtp::create($this->smtp_params);
				$smtp_recipients = array();
				$cc_recipients   = array();

				// Parse recipients argument for internet addresses
				foreach ($recipients as $recipient)
				{
					$addresses = Mail_RFC822::create()->parseAddressList($recipient, $this->smtp_params['helo'], null, false);
					if (empty($addresses))
					{
						continue;
					}

					foreach ($addresses as $address)
					{
						if (!empty($GLOBALS['htmlMimeMailTestCatchAll']))
						{
							$smtp_recipients[] = sprintf('%s_AT_%s%s', $address->mailbox, $address->host, $GLOBALS['htmlMimeMailTestCatchAll']);
						}
						else if (!empty($GLOBALS['htmlMimeMailTestCatchAllUnique']))
						{
							$smtp_recipients[] = $GLOBALS['htmlMimeMailTestCatchAllUnique'];
						}
						else
						{
							$smtp_recipients[] = sprintf('%s@%s', $address->mailbox, $address->host);
						}
					}
				}

				unset($addresses); // These are reused
				unset($address);   // These are reused

				// Get flat representation of headers, parsing
				// Cc and Bcc as we go
				foreach ($this->headers as $name => $value)
				{
					if ($name == 'Cc' OR $name == 'Bcc')
					{
						$addresses = Mail_RFC822::create()->parseAddressList($value, $this->smtp_params['helo'], null, false);
						foreach ($addresses as $address)
						{
							// Send to the test catch-all sub address or domain.
							if (!empty($GLOBALS['htmlMimeMailTestCatchAll']))
							{
								$cc_recipients[] = sprintf('%s_AT_%s%s', $address->mailbox, $address->host, $GLOBALS['htmlMimeMailTestCatchAll']);
							}
							else if (!empty($GLOBALS['htmlMimeMailTestCatchAllUnique']))
							{
								$cc_recipients[] = $GLOBALS['htmlMimeMailTestCatchAllUnique'];
							}
							else
							{
								$cc_recipients[] = sprintf('%s@%s', $address->mailbox, $address->host);
							}
						}
					}
					if ($name == 'Bcc')
					{
						continue;
					}

					if ($name == 'Subject')
					{
						$headers[$name] = $name . ': ' . $this->_encodeHeader($value, $this->build_params['head_charset'], true);
					}
					else
					{
						$headers[$name] = $name . ': ' . $this->_encodeHeader($value, $this->build_params['head_charset']);
					}
				}

				// Add headers to send_params
				$send_params                  = array();
				$send_params['headers']       = $headers;
				$send_params['recipients']    = array_values(array_unique($smtp_recipients));
				$send_params['cc_recipients'] = array_values(array_unique($cc_recipients));
				$send_params['body']          = $this->output;

				if (empty($GLOBALS['htmlMimeMailTestFromAll']))
				{
					//this is the global override
					if (isset($GLOBALS['conf']['ReturnPathAddress']))
					{
						$this->setReturnPath($GLOBALS['conf']['ReturnPathAddress']);
					}

					if (!empty($this->headers['From']))
					{
						$from                = Mail_RFC822::create()->parseAddressList($this->headers['From']);
						$send_params['from'] = sprintf('%s@%s', $from[0]->mailbox, $from[0]->host);
					}

					if (empty($send_params['from']))
					{
						$send_params['from'] = 'postmaster@' . $this->smtp_params['helo'];
					}
				}
				else
				{
					$send_params['from'] = $GLOBALS['htmlMimeMailTestFromAll'];
				}

				//no from? we just use the send_params one
				if (empty($send_params['headers']['From']))
				{
					$send_params['headers']['From'] = $send_params['from'];
				}

				// Send it
				if (!$smtp->send($send_params))
				{
					$this->errors = $smtp->errors;

					return false;
				}

				return true;

			case 'unittestSendMail':
				$GLOBALS["mail_headers_sent"] = $this->headers;

				return true;
		}
	}

	/**
	 * Use this method to return the email
	 * in message/rfc822 format. Useful for
	 * adding an email to another email as
	 * an attachment. there's a commented
	 * out example in example.php.
	 */
	public function getRFC822($recipients, $type = 'mail')
	{
		// Make up the date header as according to RFC822
		$this->setHeader('Date', date('D, d M y H:i:s O'));

		if (!defined('CRLF'))
		{
			$this->setCrlf($type == 'mail' ? "\n" : "\r\n");
		}

		if (!$this->is_built)
		{
			$this->buildMessage();
		}

		// Return path ?
		if (isset($this->return_path))
		{
			$headers[] = 'Return-Path: ' . $this->return_path;
		}

		// Get flat representation of headers
		foreach ($this->headers as $name => $value)
		{
			$headers[] = $name . ': ' . $value;
		}
		$headers[] = 'To: ' . implode(', ', $recipients);

		return implode(CRLF, $headers) . CRLF . CRLF . $this->output;
	}

	private function prepareHeaderForAntiSpoofing()
	{
		if (!empty($GLOBALS['conf']['spoofEmails']['from'])
			&& !empty($GLOBALS['conf']['spoofEmails']['trustedDomains'])
			&& !empty($this->headers['From'])
		)
		{
			if (substr($this->headers['From'], -1) === ">") // ex: John Smith <john.smith@domain.com>
			{
				$fromAdress = rtrim(substr($this->headers['From'], strrpos($this->headers['From'], "<") + 1), ">");
				$fromName   = rtrim(substr($this->headers['From'], 0, strrpos($this->headers['From'], "<")), " ");
			}
			else
			{
				$fromAdress = $this->headers['From'];
				$fromName   = "";
			}

			$explodeAddress = explode('@', $fromAdress);
			$domain = array_pop($explodeAddress); // Get "domain.com" from "john.smith@domain.com"

			if (!in_array($domain, $GLOBALS['conf']['spoofEmails']['trustedDomains'])) // Domain not allowed
			{
				if (!empty($this->headers['From']) && empty($this->headers['Reply-To']))
				{
					$this->headers['Reply-To'] = $this->headers['From'];
				}

				unset($this->headers['From']);
				$fromAdress = $GLOBALS['conf']['spoofEmails']['from'];

				if (!empty($GLOBALS['conf']['spoofEmails']['fromName']) && empty($fromName))
				{
					$fromName = $GLOBALS['conf']['spoofEmails']['fromName'];
				}
			}

			if (!empty($fromName))
			{
				$this->headers['From'] = $fromName . " <" . $fromAdress . ">";
			}
			else
			{
				$this->headers['From'] = $fromAdress;
			}
		}
	}
} // End of class.
