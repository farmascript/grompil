<?

/**
 *
 * @file        fn_cookies.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:40:31
 * @dateLastMod Wed 2026-08-05 17:09:28
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_cookies */

# Note that the value portion of the cookie will automatically be urlencoded when you send the cookie,
# and when it is received, it is automatically decoded and assigned to a variable by the same name as the cookie name.
# If you don't want this, you can use setrawcookie() instead.

# $_COOKIE is a superglobal associative array in PHP that contains all the cookies sent by the client (browser) to the server. Each cookie is represented as a key-value pair, where the key is the name of the cookie and the value is its corresponding value.

function fn_cookies(
    string $strategy,
    string $name,
    string $value   = '',
    int $expires    = 0,
    string $path    = '/',
    string $domain  = '',
    bool $secure    = true,   # Standaard true voor moderne HTTPS-omgevingen
    bool $httpOnly  = true,   # Standaard true tegen XSS
    string $trace   = '212124: no trace',
    string $sameSite = 'Lax'  # Lax is de veilige browserstandaard
): bool|string {

	$name = PREFIX_COOKIES . $name;
    
    # if expires is 0 and strategy is 'set', set expires to 1000 days from now
    if ($expires === 0 && $strategy === 'set') {
        $expires = time() + (60 * 60 * 24 * 1000);
    }

    switch ($strategy) {
        case 'get':
            return $_COOKIE[$name] ?? ''; # gives empty string instead of FALSE (prevents type mismatches)

        case 'set':
			if (headers_sent($file, $line)) {
    			exit("Error: headers already sent in file $file on line $line. Cookies cannot be set!");
			}
            return setcookie($name, $value);

        case 'delete':
            # Hardcode the expiration time to a past time to delete the cookie
            $arrOptions['expires'] = time() - 3600;
            return setcookie($name, '');

        default:
            $id = '670935'; 
            $msg = "Strategy '$strategy' is not available. Use get, set or delete. $trace" . NL;
            if (function_exists('fn_debug')) {
                print fn_debug(id: $id, msg: $msg, class: 'fatal', ln: __LINE__, fi: __FILE__);
            }
            return false;
    }
}


/* #endregion fn_cookies */