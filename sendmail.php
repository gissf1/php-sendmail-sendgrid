#!/usr/bin/php
<?php
# PHP script starts here...
/**
* PHP CLI script to implement a sendmail-compatible interface to the
* Twilio SendGrid API for systems with Curl, but lacking an SMTP relay.
* 
* Homepage: https://github.com/gissf1/php-sendmail-sendgrid
* Coded by Brian Gisseler (gissf1@gmail.com)
* License: MIT
**/

global $apikey;

function sendgrid_mail($from, $to, $subject, $message, $headers) {
	global $apikey;
	global $verbose;
	
	// prepare contentType
	$contentType = "text/plain";
	$charset = "utf-8";
	foreach($headers as $k => $v) {
		if (strtolower($k) == 'content-type') {
			$contentType = $v;
			unset($headers[$k]);
		}
	}
	if (($i = strpos($contentType, ';')) !== false) {
		$suffix = ltrim(substr($contentType, $i+1));
		if (str_starts_with($suffix, 'charset=')) {
			$charset = substr($suffix, 8);
			switch($charset) {
				case 'utf-8':
					break;
				default:
					throw new Exception("Unknown Content-Type charset: $charset");
			}
		} else {
			throw new Exception("Unknown Content-Type suffix: $suffix");
		}
		$contentType = substr($contentType, 0, $i);
	}
	
	// manipulate FROM address array into SendGrid address array format
	list($from, $name) = splitNameFromEmail($from);
	$from = array("email" => $from);
	if (!empty($name)) {
		$from["name"] = $name;
	}
	
	// manipulate TO address array into SendGrid address array format
	$a = $to;
	$to = array();
	foreach($a as $email) {
		list($email, $name) = splitNameFromEmail($email);
		$email = array("email" => $email);
		if (!empty($name)) {
			$email["name"] = $name;
		}
		array_push($to, $email);
	}
	unset($a);
	
	// build curl request
	$curl = curl_init();
	if ($curl === false) throw new Exception("curl_init() failed.");
	$ok = curl_setopt_array($curl, array(
// 		CURLOPT_VERBOSE => true,
// 		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
		CURLOPT_HTTPHEADER => array(
			"Authorization: Bearer $apikey",
			'Content-Type: application/json',
		),
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode(array(
			"personalizations" => array(
				array("to" => $to)
			),
			"from" => $from,
			"headers" => $headers,
			"subject" => $subject,
			"content" => array(
				array(
					"type" => $contentType,
					"value" => $message,
				)
			),
			/*
			"mail_settings" => array(
				"sandbox_mode" => array(
					"enable" => true,
				),
			),
			*/
		)),
	));
	if (!$ok) throw new Exception("curl_setopt_array() failed to set parameters");
	$ret = curl_exec($curl);
	// handle errors here
	if ($ret === false) {
		$code = curl_errno($curl);
		$msg = curl_error($curl);
		curl_close($curl);
		throw new Exception("curl_exec() failed: $code: $msg");
	}
	curl_close($curl);
	// handle data returned from curl_exec()
	list($ret, $body) = explode("\r\n\r\n", $ret, 2);
	$ret = explode("\r\n", $ret);
	$http_ver = NULL;
	$http_code = NULL;
	$http_status = NULL;
	foreach($ret as $line) {
		if (preg_match('~^HTTP/([01]\.[0-9]) ([1-5][0-9]{2}) (.*)$~', $line, $m)) {
			$http_ver = $m[1];
			$http_code = $m[2];
			$http_status = $m[3];
		}
	}
	$ret = !($http_code >= 300);
	if ($verbose) {
		// check body content
		if (is_null($http_ver)) {
			echo "HTTP Response version is NULL\n";
		} else {
			echo "HTTP Response v$http_ver: $http_code: $http_status\n";
		}
		if ($body != '') {
			echo "-------- BEGIN BODY --------\n";
			print_r($body);
			echo "-------- END - BODY --------\n";
		}
		if ($ret) {
			echo "return code is success: $http_code\n";
		} else {
			echo "return code is failure: $http_code\n";
		}
	}
	return $ret;
}

function splitNameFromEmail($email) {
	$name = NULL;
	if (preg_match('~^[-+.0-9A-Z_a-z]+@[-.0-9A-Za-z]+$~', $email)) {
		// nothing to do
	} elseif (preg_match('~^((")?(.*)\2 )?<([-+.0-9A-Z_a-z]+@[-.0-9A-Za-z]+)>$~', $email, $m)) {
		$email = $m[4];
		if ($m[3] > '') {
			$name = $m[3];
		}
	} else {
		global $myname;
		throw new Exception("$myname splitNameFromEmail() failed: unknown email format: $email");
	}
	return array($email, $name);
}

function is_valid_email($email) {
	try {
		list($email2, $name) = splitNameFromEmail($email);
	} catch (Exception) {
		return false;
	}
	return true;
}

function help() {
	global $myname;
	$me = basename($myname);
	echo <<<EOF
usage: $me [-h|-t|-v|-i] [-f FROM] [-ap APIKEY] [{-o|-am|-au} DUMMY] [TO...]

  -h --help   This help text.

  -t          Recipients are gathered from command line as well as TO headers.
              This is always done, but the flag is allowed for compatibility.

  -v          Verbose flag increases the output for debugging and such.

  -f FROM     Specify the required FROM email address, forcing emails with a
              different FROM address header to fail.  This option allows emails
              to include a "FROM" header with a value matching this one, or to
              omit the header entirely, using this value instead.

  -ap APIKEY  Specify the Twilio SendGrid API key for authentication.  The code
              checks the PHP INI option 'sendgrid_apikey' so one can specify
              the API key there instead if preferred.  If both are defined,
              this argument takes presidence.

  TO...       List of 0 or more space-separated destination email addresses.

  Any flags that require an additional argument can be packed with the argument
  value, omitting the whitespace between them.  So, for example, "-apAPIKEY" is
  the same as "-ap APIKEY".

  The remaining options are accepted for compatibility, but are ignored.
EOF;
	exit(1);
}

// setup variables
$verbose = 0;
if (empty($apikey)) $apikey = get_cfg_var('sendgrid_apikey');
if (empty($apikey)) $apikey = ini_get('sendgrid_apikey');
$to = array();
$from = NULL;
$subject = NULL;
$myname = array_shift($argv);

// parse command line arguments
$lastArg = NULL;
$hasArg = false;
foreach($argv as $arg) {
	if ($hasArg) {
	} elseif (str_starts_with($arg, '-') && strlen($arg) >= 3) {
		// split arguments packed with values
		$ch = $arg[1];
		switch($ch) {
			case 'a':
				$ch = $arg[2];
				switch($ch) {
					// ignore these
					case 'm':
					case 'u':
						if (strlen($arg) > 3) continue(3);
						$lastArg = '-au';
						$hasArg = true;
						break;
					// split if needed
					case 'p':
						$lastArg = '-ap';
						$hasArg = true;
						if (strlen($arg) > 3) {
							// split it
							$arg = substr($arg,3);
						} else {
							continue(3);
						}
						break;
					default:
						throw new Exception("$myname: unknown argument: $arg");
				}
				break;
			case 'o':
				if (strlen($arg) > 2) continue(2);
				$lastArg = '-o';
				$hasArg = true;
				break;
		}
	}
	if ($hasArg) {
		$hasArg = false;
		switch($lastArg) {
			case '-f':
				$from = $arg;
				break;
			case '-ap':
				$apikey = $arg;
				break;
			case '-o':
			case '-au':
				// ignore these
				break;
			default:
				throw new Exception("$myname: unknown argument value: $arg");
		}
		continue;
	}
	switch($arg) {
		case '-h':
		case '--help':
			help();
			break;
		case '-v':
			$verbose++;
			break;
		// ignored; for compatibility
		case '-i':
		case '-oi':
		case '-t': // Read additional recipients from message body, but we do this anyway
			break;
		case '-o': // ignored, but has a required argument
		case '-f': // For use in MAIL FROM:<sender>
			$hasArg = true;
			break;
		default:
			if (is_valid_email($arg)) {
				array_push($to, $arg);
			} else {
				throw new Exception("$myname: unknown argument: $arg");
			}
	}
	$lastArg = $arg;
}
if (empty($apikey)) {
	throw new Exception("$myname: apikey is not set.  Please set value in 'php.ini' or with -apPASS argument.");
}

// parse stdin
$headers = array();
$stdin = file('php://stdin');
$foundBody = false;
$body = '';
foreach($stdin as $line) {
	$line = rtrim($line, "\r\n");
	if ($foundBody) {
		$body .= "\r\n" . $line;
		continue;
	}
	if ($line == '') {
		$foundBody = true;
		continue;
	}
	list($k, $v) = explode(': ', $line, 2);
	if ($v > '') {
		switch(strtoupper($k)) {
			case 'TO':
				array_push($to, $v);
				break;
			case 'FROM':
				if (!is_null($from) && ($from != $v)) {
					throw new Exception("$myname: cannot change FROM address: '$from' -> '$v'");
				}
				$from = $v;
				break;
			case 'SUBJECT':
				if (!is_null($subject)) {
					throw new Exception("$myname: cannot have multiple Subject headers: '$subject' -> '$v'");
				}
				$subject = $v;
				break;
			default:
				if (isset($headers[$k])) {
					throw new Exception("$myname: multiple '$k' headers");
				}
				$headers[$k] = $v;
		}
	} else {
		throw new Exception("$myname: invalid header: $k");
	}
}
$body = substr($body, 2) . "\r\n";
if (is_null($from)) {
	throw new Exception("$myname: missing FROM header");
}
if (is_null($subject)) {
	throw new Exception("$myname: missing SUBJECT header");
}
if (count($to) == 0) {
	throw new Exception("$myname: missing TO header");
}

if ($verbose) {
	echo "argv:";
	var_export($argv);
	echo "\n";
	echo 'stdin:';
	var_export($stdin);
	echo "\n";
}

if (!sendgrid_mail($from, $to, $subject, $body, $headers)) {
	exit(1);
}
