<?php
/**
 * Filename.......: class.smtp.inc
 * Project........: SMTP Class
 * Version........: 1.0.5
 * Last Modified..: 21 December 2001
 */

define('SMTP_STATUS_NOT_CONNECTED', 1, true);
define('SMTP_STATUS_CONNECTED', 2, true);

class smtp
{
	var $authenticated;
	var $connection;
	var $recipients;
	var $headers;
	var $timeout;
	var $errors;
	var $status;
	var $body;
	var $from;
	var $host;
	var $port;
	var $helo;
	var $auth;
	var $user;
	var $pass;

	/**
	 * Constructor function. Arguments:
	 * $params - An assoc array of parameters:
	 *
	 *   host    - The hostname of the smtp server        Default: localhost
	 *   port    - The port the smtp server runs on        Default: 25
	 *   helo    - What to send as the HELO command        Default: localhost
	 *             (typically the hostname of the
	 *             machine this script runs on)
	 *   auth    - Whether to use basic authentication    Default: FALSE
	 *   user    - Username for authentication            Default: <blank>
	 *   pass    - Password for authentication            Default: <blank>
	 *   timeout - The timeout in seconds for the call    Default: 5
	 *             to fsockopen()
	 */

	public function __construct($params = array())
	{
		if (!defined('CRLF'))
		{
			define('CRLF', "\r\n", true);
		}

		$this->authenticated = false;
		$this->timeout       = 5;
		$this->status        = SMTP_STATUS_NOT_CONNECTED;
		$this->host          = 'localhost';
		$this->port          = 25;
		$this->helo          = 'localhost';
		$this->auth          = false;
		$this->user          = '';
		$this->pass          = '';
		$this->errors        = array();

		foreach ($params as $key => $value)
		{
			$this->$key = $value;
		}
	}

	/**
	 * Create an instance and tries to connect it,
	 *
	 * @param array $params
	 * @return smtp
	 */
	public static function create($params = array())
	{
		$obj = new smtp($params);
		if ($obj->connect())
		{
			$obj->status = SMTP_STATUS_CONNECTED;
		}

		return $obj;
	}

	/**
	 * Connect function. This will, when called
	 * statically, create a new smtp object,
	 * call the connect function (ie this function)
	 * and return it. When not called statically,
	 * it will connect to the server and send
	 * the HELO command.
	 */
	public function &connect($params = array())
	{
		$this->connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
		if (function_exists('socket_set_timeout'))
		{
			@socket_set_timeout($this->connection, 5, 0);
		}

		if (!empty($this->user) && !empty($this->pass))
		{
			$this->auth = true;
		}

		$greeting = $this->get_data();
		if (is_resource($this->connection))
		{
			if ($this->auth)
			{
				$ret = $this->ehlo();
			}
			else
			{
				$ret = $this->helo();
			}

			return $ret;
		}
		else
		{
			$this->errors[] = 'Failed to connect to server: ' . $errstr;
			$ret            = false;

			return $ret;
		}
	}

	/**
	 * Function which handles sending the mail.
	 * Arguments:
	 * $params    - Optional assoc array of parameters.
	 *            Can contain:
	 *              recipients - Indexed array of recipients
	 *              from       - The from address. (used in MAIL FROM:),
	 *                           this will be the return path
	 *              headers    - Indexed array of headers, one header per array entry
	 *              body       - The body of the email
	 *            It can also contain any of the parameters from the connect()
	 *            function
	 */
	public function send($params = array())
	{
		foreach ($params as $key => $value)
		{
			$this->set($key, $value);
		}

		if ($this->is_connected())
		{

			if (!empty($this->user) && !empty($this->pass))
			{
				$this->auth = true;
			}

			// Do we auth or not? Note the distinction between the auth variable and auth() function
			if ($this->auth AND !$this->authenticated)
			{
				if (!$this->auth())
				{
					return false;
				}
			}

			$this->mail($this->from);
			if (is_array($this->recipients))
			{
				foreach ($this->recipients as $value)
				{
					$this->rcpt($value);
				}
			}
			else
			{
				$this->rcpt($this->recipients);
			}

			if (is_array($this->cc_recipients))
			{
				foreach ($this->cc_recipients as $value)
				{
					$this->rcpt($value);
				}
			}
			else
			{
				$this->rcpt($this->cc_recipients);
			}

			if (!$this->data())
			{
				return false;
			}

			// Transparency
			$headers = str_replace(CRLF . '.', CRLF . '..', trim(implode(CRLF, $this->headers)));
			$body    = str_replace(CRLF . '.', CRLF . '..', $this->body);
			$body    = substr($body, 0, 1) == '.' ? '.' . $body : $body;

			$this->send_data($headers);

			//sending the recipients as TO:
			if (is_array($this->recipients))
			{
				foreach ($this->recipients as $value)
				{
					$this->send_data('To: <' . $value . '>');
				}
			}
			else
			{
				$this->send_data('To: <' . $this->recipients . '>');
			}

			$this->send_data('');
			$this->send_data($body);
			$this->send_data('.');

			$result = (substr(trim($this->get_data()), 0, 3) === '250');

			//$this->rset();
			return $result;
		}
		else
		{
			$this->errors[] = 'Not connected!';

			return false;
		}
	}

	/**
	 * Function to implement HELO cmd
	 */
	public function helo()
	{
		if (is_resource($this->connection)
			AND $this->send_data('HELO ' . $this->helo)
			AND substr(trim($error = $this->get_data()), 0, 3) === '250'
		)
		{

			return true;
		}
		else
		{
			$this->errors[] = 'HELO command failed, output: ' . trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function to implement EHLO cmd
	 */
	public function ehlo()
	{
		if (is_resource($this->connection)
			AND $this->send_data('EHLO ' . $this->helo)
			AND substr(trim($error = $this->get_data()), 0, 3) === '250'
		)
		{

			return true;
		}
		else
		{
			$this->errors[] = 'EHLO command failed, output: ' . trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function to implement RSET cmd
	 */
	public function rset()
	{
		if (is_resource($this->connection)
			AND $this->send_data('RSET')
			AND substr(trim($error = $this->get_data()), 0, 3) === '250'
		)
		{

			return true;
		}
		else
		{
			$this->errors[] = 'RSET command failed, output: ' . trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function to implement QUIT cmd
	 */
	public function quit()
	{
		if (is_resource($this->connection)
			AND $this->send_data('QUIT')
			AND substr(trim($error = $this->get_data()), 0, 3) === '221'
		)
		{

			fclose($this->connection);
			$this->status = SMTP_STATUS_NOT_CONNECTED;

			return true;
		}
		else
		{
			$this->errors[] = 'QUIT command failed, output: ' . trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function to implement AUTH cmd
	 */
	public function auth()
	{
		if (is_resource($this->connection)
			AND $this->send_data('AUTH LOGIN')
			AND substr(trim($error = $this->get_data()), 0, 3) === '334'
			AND $this->send_data(base64_encode($this->user))            // Send username
			AND substr(trim($error = $this->get_data()), 0, 3) === '334'
			AND $this->send_data(base64_encode($this->pass))            // Send password
			AND substr(trim($error = $this->get_data()), 0, 3) === '235'
		)
		{

			$this->authenticated = true;

			return true;
		}
		else
		{
			$this->errors[] = 'AUTH command failed: ' . trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function that handles the MAIL FROM: cmd
	 */
	public function mail($from)
	{
		if ($this->is_connected()
			AND $this->send_data('MAIL FROM:<' . $from . '>')
			AND substr(trim($this->get_data()), 0, 2) === '250'
		)
		{

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Function that handles the RCPT TO: cmd
	 */
	public function rcpt($to)
	{
		if ($this->is_connected()
			AND $this->send_data('RCPT TO:<' . $to . '>')
			AND substr(trim($error = $this->get_data()), 0, 2) === '25'
		)
		{

			return true;
		}
		else
		{
			$this->errors[] = trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function that sends the DATA cmd
	 */
	public function data()
	{
		if ($this->is_connected()
			AND $this->send_data('DATA')
			AND substr(trim($error = $this->get_data()), 0, 3) === '354'
		)
		{

			return true;
		}
		else
		{
			$this->errors[] = trim(substr(trim($error), 3));

			return false;
		}
	}

	/**
	 * Function to determine if this object
	 * is connected to the server or not.
	 */
	public function is_connected()
	{
		return (is_resource($this->connection) AND ($this->status === SMTP_STATUS_CONNECTED));
	}

	/**
	 * Function to send a bit of data
	 */
	public function send_data($data)
	{
		if (is_resource($this->connection))
		{
			return fwrite($this->connection, $data . CRLF, strlen($data) + 2);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Function to get data.
	 */
	public function &get_data()
	{
		$return = '';
		$line   = '';
		$loops  = 0;

		if (is_resource($this->connection))
		{
			while ((strpos($return, CRLF) === false OR substr($line, 3, 1) !== ' ') AND $loops < 100)
			{
				$line = fgets($this->connection, 512);
				$return .= $line;
				$loops++;
			}

			return $return;
		}

		$return = false;

		return $return;
	}

	/**
	 * Sets a variable
	 */
	public function set($var, $value)
	{
		$this->$var = $value;

		return true;
	}

} // End of class

