<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Wed 2026-10-21 10:22:30
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages IP access rules for company administration.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';

$user = require_login();
$company = require_company_context($user);
$canManage = grompil_can_access_module('company_security.php', $user, $company);
if (!$canManage) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}

$companyId = (int) $company['id'];
$currentIp = grompil_client_ip_address();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'restrict_current_ip') {
        if ($currentIp === '') {
            $message = '<div class="alert alert-danger" role="alert">The IP address of this connection could not be determined.</div>';
        } else {
            $network = str_contains($currentIp, ':') ? $currentIp . '/128' : $currentIp . '/32';
            $started = (bool) pg_query(db(), 'BEGIN');
            $deleted = $started ? pg_query_params(
                db(),
                "DELETE FROM access_ip_allowlist WHERE company_id = $1 AND scope = 'admin'",
                [$companyId]
            ) : false;
            $inserted = $deleted ? pg_query_params(
                db(),
                "INSERT INTO access_ip_allowlist (company_id, scope, network, description, is_active)
                 VALUES ($1, 'admin', $2::inet, 'Company administration', TRUE)",
                [$companyId, $network]
            ) : false;
            $saved = $inserted && (bool) pg_query(db(), 'COMMIT');
            if (!$saved && $started) {
                pg_query(db(), 'ROLLBACK');
            }
            $message = $saved
                ? '<div class="alert alert-success" role="status">Company administration is now restricted to this connection\'s IP address.</div>'
                : '<div class="alert alert-danger" role="alert">The IP restriction could not be saved.</div>';
        }
    } elseif ($action === 'remove_restriction') {
        $deleted = pg_query_params(
            db(),
            "DELETE FROM access_ip_allowlist WHERE company_id = $1 AND scope = 'admin'",
            [$companyId]
        );
        $message = $deleted
            ? '<div class="alert alert-warning" role="status">The IP restriction for company administration has been disabled.</div>'
            : '<div class="alert alert-danger" role="alert">The IP restriction could not be changed.</div>';
    }
}

$rulesResult = pg_query_params(
    db(),
    "SELECT host(network) AS address
     FROM access_ip_allowlist
     WHERE company_id = $1 AND scope = 'admin' AND is_active
     ORDER BY id",
    [$companyId]
);
$allowedIps = [];
while ($rulesResult && $row = pg_fetch_assoc($rulesResult)) {
    $allowedIps[] = (string) $row['address'];
}

page_start('Company administration IP access', $user);
?>
<main class="container py-4 flex-grow-1">
    <h1 class="h2 mb-2">Company administration IP access</h1>
    <p class="text-secondary">Control which IP addresses users of <?= e((string) $company['name']) ?> can use to sign in and access company administration. The public website remains available to visitors.</p>
    <?= $message ?>

    <section class="card shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5">Current connection</h2>
            <p class="mb-3">The IP address detected by the web server is <strong><code><?= e($currentIp !== '' ? $currentIp : 'unknown') ?></code></strong>.</p>
            <?php if ($allowedIps !== []): ?>
                <p class="mb-2">Allowed IP addresses for company administration:</p>
                <ul class="mb-3">
                    <?php foreach ($allowedIps as $ip): ?><li><code><?= e($ip) ?></code></li><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="alert alert-warning">No IP restriction has been configured. Company administration is currently accessible from any IP address.</p>
            <?php endif; ?>
            <p class="small text-secondary">An IP address may change, for example after switching internet providers. Platform administrators can restore access if a company is locked out.</p>
            <form method="post" class="d-flex flex-wrap gap-2">
                <?= csrf_field() ?>
                <button class="btn btn-success" type="submit" name="action" value="restrict_current_ip" <?= $currentIp === '' ? 'disabled' : '' ?>>Restrict to this IP address</button>
                <?php if ($allowedIps !== []): ?>
                    <button class="btn btn-outline-danger" type="submit" name="action" value="remove_restriction">Disable IP restriction</button>
                <?php endif; ?>
                <a class="btn btn-outline-primary" href="dashboard.php"><?= e(fn_t('return_to_navigation')) ?></a>
            </form>
        </div>
    </section>
</main>
<?php
page_end();

