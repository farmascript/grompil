<?

/**
 *
 * @file        fn_cookies.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:40:31
 * @dateLastMod Fri 2026-07-31 18:00:12
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
    string $strategy,
    string $name,
    string $value   = '',
    int $expires    = 0,
    string $path    = '/' . DIR_BASE . '/',
    string $domain  = '',
    bool $secure    = true,   # Standaard true voor moderne HTTPS-omgevingen
    bool $httpOnly  = true,   # Standaard true tegen XSS
    string $trace   = '212124: no trace',
    string $sameSite = 'Lax'  # Lax is de veilige browserstandaard
): bool|string {

	# Binnen fn_cookies():
	$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
	
	$arrOptions = [
	    'expires'  => $expires,
	    'path'     => '/',               # ALTIJD '/' tenzij u bewust in een submap isoleert
	    'domain'   => '',                # Leeg laten, de browser pakt automatisch het huidige domein
	    'secure'   => $isSecure,         # Schakelt automatisch mee met HTTP of HTTPS
	    'httponly' => true,
	    'samesite' => 'Lax'
	];
	

    $name = PREFIX_COOKIES . $name;
    
    # Als verloopdatum 0 is, zet een standaard van 1000 dagen (of laat 0 voor sessiecookie)
    if ($expires === 0 && $strategy === 'set') {
        $expires = time() + (60 * 60 * 24 * 1000);
    }

    $arrOptions = [
        'expires'  => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax' # Geactiveerd voor CSRF-bescherming
    ];

    switch ($strategy) {
        case 'get':
            return $_COOKIE[$name] ?? ''; # Geeft lege string i.p.v. FALSE (voorkomt type-mismatches)

        case 'set':
			if (headers_sent($file, $line)) {
    		exit("Fout: Headers zijn al verzonden in bestand $file op regel $line. Cookies kunnen niet worden gezet!");
}

            return setcookie($name, $value, $arrOptions);

        case 'delete':
            # Hardcode de leegmaak-parameters voor betrouwbare verwijdering
            $arrOptions['expires'] = time() - 3600;
            return setcookie($name, '', $arrOptions);

        default:
            $id = '670935'; 
            $msg = "Strategy '$strategy' is niet beschikbaar. Gebruik get, set of delete. $trace" . NL;
            if (function_exists('fn_debug')) {
                print fn_debug(id: $id, msg: $msg, class: 'fatal', ln: __LINE__, fi: __FILE__);
            }
            return false;
    }
}


/* #endregion fn_cookies */