<?php
/** Manage global platform administrator IP restrictions. */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$user = grompil_require_module_access('platform-security.php');
$currentIp = grompil_client_ip_address();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add_network') {
        $networkInput = trim((string) ($_POST['network'] ?? ''));
        $parts = explode('/', $networkInput, 2);
        $ip = $parts[0] ?? '';
        $packed = filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : false;
        $maxPrefix = str_contains($ip, ':') ? 128 : 32;
        $prefix = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_INT) : $maxPrefix;
        if ($packed === false || !is_int($prefix) || $prefix < 0 || $prefix > $maxPrefix) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_security_invalid_ip')) . '</div>';
        } else {
            $network = $ip . '/' . $prefix;
            $saved = pg_query_params(
                db(),
                "INSERT INTO access_ip_allowlist (company_id, scope, network, description, is_active)
                 VALUES (NULL, 'admin', $1::inet, 'Platform administration', TRUE)
                 ON CONFLICT (scope, network) WHERE company_id IS NULL DO UPDATE
                    SET is_active = TRUE, description = EXCLUDED.description",
                [$network]
            );
            $message = $saved
                ? '<div class="alert alert-success" role="status">' . e(fn_t('platform_security_saved')) . '</div>'
                : '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_security_save_failed')) . '</div>';
        }
    } elseif ($action === 'remove_network') {
        $ruleId = filter_var($_POST['rule_id'] ?? null, FILTER_VALIDATE_INT);
        $removed = is_int($ruleId) && $ruleId > 0
            ? pg_query_params(db(), "DELETE FROM access_ip_allowlist WHERE id = $1 AND company_id IS NULL AND scope = 'admin'", [$ruleId])
            : false;
        $message = $removed && pg_affected_rows($removed) === 1
            ? '<div class="alert alert-success" role="status">' . e(fn_t('platform_security_removed')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_security_save_failed')) . '</div>';
    }
}

$rulesResult = pg_query(
    db(),
    "SELECT id, network::text AS network
     FROM access_ip_allowlist
     WHERE company_id IS NULL AND scope = 'admin' AND is_active
     ORDER BY id"
);
$rules = [];
while ($rulesResult && $row = pg_fetch_assoc($rulesResult)) {
    $rules[] = $row;
}
$rulesHtml = '';
foreach ($rules as $rule) {
    $rulesHtml .= '<li class="list-group-item d-flex justify-content-between align-items-center gap-3"><code>'
        . e((string) $rule['network']) . '</code><form method="post" class="m-0">' . csrf_field()
        . '<input type="hidden" name="action" value="remove_network"><input type="hidden" name="rule_id" value="'
        . e((string) $rule['id']) . '"><button class="btn btn-sm btn-outline-danger" type="submit">'
        . e(fn_t('platform_security_remove')) . '</button></form></li>';
}

page_start(fn_t('platform_security_title'), $user);
?>
<main class="container py-4 flex-grow-1">
    <h1 class="h2 mb-2"><?= e(fn_t('platform_security_title')) ?></h1>
    <p class="text-secondary"><?= e(fn_t('platform_security_intro')) ?></p>
    <?= $message ?>
    <section class="card shadow-sm mt-4"><div class="card-body">
        <h2 class="h5"><?= e(fn_t('platform_security_current_ip')) ?></h2>
        <p><?= e($currentIp !== '' ? $currentIp : 'unknown') ?></p>
        <?php if ($rules === []): ?>
            <p class="alert alert-warning"><?= e(fn_t('platform_security_unrestricted')) ?></p>
        <?php else: ?>
            <p><?= e(fn_t('platform_security_allowed')) ?></p>
            <ul class="list-group mb-3"><?= $rulesHtml ?></ul>
        <?php endif; ?>
        <form method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_network">
            <div class="col-sm-8"><label class="form-label" for="platform-network"><?= e(fn_t('platform_security_network')) ?></label>
                <input class="form-control" id="platform-network" name="network" maxlength="64" required value="<?= e($currentIp) ?>"></div>
            <div class="col-sm-4"><button class="btn btn-primary" type="submit"><?= e(fn_t('platform_security_add')) ?></button></div>
        </form>
        <p class="form-text mt-3"><?= e(fn_t('platform_security_recovery')) ?></p>
    </div></section>
</main>
<?php
page_end();
