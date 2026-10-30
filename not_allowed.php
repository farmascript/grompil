<?

/**
 *
 * @file        not_allowed.php
 * @author      lm
 * @dateCreated Sat 2018-12-29 21:08:13
 * @dateLastMod Fri 2026-10-23 10:37:47
 *
 */

$theEpoch = time();

/**
 *  Returns the client IP observed by PHP, consulting forwarded headers only behind an explicitly trusted proxy.
 *
 * @return string
 */
function grompil_client_ip_address_2(): string
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return '';
    }

    $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    if ($forwarded === [] || in_array('', $forwarded, true)) {
        return $address;
    }
    foreach ($forwarded as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            return $address;
        }
    }
}

$ip = grompil_client_ip_address_2();

$template = <<<_EOF
<!-- 226733 b-->

<!doctype html>
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/svg+xml" href="media/gromPilLogo.svg">
        <title>no access · Grompil</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
            .error-card { width: min(100%, 38rem); }
        </style>
    </head>
    <body class="bg-light">
        <main class="error-card card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="text-primary fw-semibold mb-2">Grompil</div>
                <h1 class="h3 mb-3">no access</h1>
                <p class="text-secondary mb-4">action not allowed</p>
                <div class="alert alert-light border mb-4" role="status">
                    warning <strong>#670081</strong>
					for ip address: $ip, epoch:$theEpoch
                </div>
                <a class="btn btn-outline-secondary ms-2" href="grompil/index.php">start page</a>
            </div>
        </main>
    </body>
</html>

<!-- 226733 e-->
_EOF;

print $template;


exit(1);
