<?

/**
 *
 * @file        fn_cookies.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:40:31
 * @dateLastMod Thu 2026-07-30 19:40:59
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_cookies */

# Note that the value portion of the cookie will automatically be urlencoded when you send the cookie,
# and when it is received, it is automatically decoded and assigned to a variable by the same name as the cookie name.
# If you don't want this, you can use setrawcookie() instead.

function fn_cookies(
	$strategy,
	$name,

	$value   = '',
	$expires = '',
	$path    = '/' . DIR_BASE .'/',
	$domain  = '',
		# Because setting a cookie with a value of false will try to delete the cookie,
		# you should not use boolean values. Instead, use 0 for false and 1 for true.
	$secure   = 0,
	$httpOnly = 1,
	$trace    = '212124: no trace',
	$sameSite = 'Lax',      # None, Lax or Strict
	)
{

$name   = PREFIX_COOKIES.$name;
$expires = $expires == '' ? time()+(60*60*24*1000) : $expires;
$value  = $value  == '' ? 'empty 882247' : $value;

$arrOptions = [
	'expires'  => $expires,
	'path'     => $path,
	'domain'   => $domain,		# leading dot for compatibility or use subdomain
	'secure'   => $secure,
	'httponly' => $httpOnly,
	# 'samesite' => $sameSite		#  None || Lax  || Strict
];


	# print '<pre>#324719:';print "$name trace:$trace, strategy:$strategy" ;print '</pre>';

	switch ($strategy) {
		case 'get':

			# print '<pre>#095817 var_dump: name:$name'; var_dump($_COOKIE); print '</pre>';

		if( isset($_COOKIE[$name] )) {

				# print '<pre>#744727:'; print "get:" . $_COOKIE[$name]; print '</pre>';

			return $_COOKIE[$name];
		} else {

				# print '<pre>#383181:'; print "get: false: $name trace:$trace"; print '</pre>';

			return FALSE;
		}
		break;

	case 'set':

		if( $bool = setcookie($name, $value, $arrOptions) ) {

				$secureShow   = $arrOptions['secure']   === 0 ? '0→false' : '1→true';
				$httpOnlyShow = $arrOptions['httponly'] === 0 ? '0→false' : '1→true';
				$boolShow     = var_export($bool, TRUE);

				$show = "set: bool returned '$boolShow': cookie with name <b>'$name'</b> was set. trace:$trace." . NL . "value:$value,  secure:$secureShow, httponly:$httpOnlyShow";

				# $id='049966'; $msg=$show . NL;
				# print fn_debug(id:$id, msg:$msg, class:'debug', ln:__LINE__, fi:__FILE__, array:$arrOptions);

			return $bool;
		}
		break;

	case 'delete':

		# cookie you want to delete must have exact the same parameters as when it was set. Except expiration time.


			# print '<pre>#456577:'; print "cookie $name deleted. trace:$trace"; print '</pre>';

		$arrOptions['expires'] = time()-(60*60*24);
		$bool = setcookie($name, $value, $arrOptions);
		# print "<pre>#219345 delete: \$bool for $name:"; var_dump($bool); print '</pre>';
		# do not use unset()!
		return $bool;
		break;

	default:

		$id='670935'; $msg="strategy '$strategy' is not available. Use get, set or delete. $trace" . NL;
		print fn_debug(id:$id, msg:$msg, class:'fatal', ln:__LINE__, fi:__FILE__);

		return FALSE;
		break;
}

return FALSE;
}

/* #endregion fn_cookies */