<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Fri 2026-11-13
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages company settings, including IP access rules and the dispensing layout.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:company_security
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
$templateOptions = array_values(array_filter(
    dd_varia_get_options('dispensing_templates', '_root'),
    static function (array $option): bool {
        $key = (string) ($option['key'] ?? '');
        return preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key) === 1
            && is_dir(GROMPIL_ROOT . '/tmpl/modules/dispensing/' . $key);
    }
));
$selectedTemplate = 'standard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_dispensing_template') {
        $requestedTemplate = (string) ($_POST['dispensing_template'] ?? '');
        $allowedTemplates = array_column($templateOptions, 'key');
        if (!in_array($requestedTemplate, $allowedTemplates, true)) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('company_template_invalid')) . '</div>';
        } else {
            try {
                $savedTemplate = db_query_params(
                    'company_settings.dispensing_template.save',
                    'INSERT INTO ct_company_preferences (company_id, dispensing_template_key, updated_at)
                     VALUES ($1, $2, now())
                     ON CONFLICT (company_id) DO UPDATE
                     SET dispensing_template_key = EXCLUDED.dispensing_template_key,
                         updated_at = now()',
                    [$companyId, $requestedTemplate]
                );
            } catch (GrompilDatabaseQueryException) {
                $savedTemplate = false;
            }
            if ($savedTemplate) {
                $selectedTemplate = $requestedTemplate;
                $message = '<div class="alert alert-success" role="status">' . e(fn_t('company_template_saved')) . '</div>';
            } else {
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('company_template_save_failed')) . '</div>';
            }
        }
    } elseif ($action === 'restrict_current_ip') {
        if ($currentIp === '') {
            $message = '<div class="alert alert-danger" role="alert">The IP address of this connection could not be determined.</div>';
        } else {
            $network = str_contains($currentIp, ':') ? $currentIp . '/128' : $currentIp . '/32';
            try {
                $started = (bool) db_query_params('company_security.restrict.begin', 'BEGIN');
            } catch (GrompilDatabaseQueryException) {
                $started = false;
            }
            try {
                $deleted = $started ? db_query_params(
                    'company_security.restrict.clear_existing',
                    "DELETE FROM ft_access_ip_allowlist WHERE company_id = $1 AND scope = 'admin'",
                    [$companyId]
                ) : false;
            } catch (GrompilDatabaseQueryException) {
                $deleted = false;
            }
            try {
                $inserted = $deleted ? db_query_params(
                    'company_security.restrict.add_current',
                    "INSERT INTO ft_access_ip_allowlist (company_id, scope, network, description, is_active)
                     VALUES ($1, 'admin', $2::inet, 'Company administration', TRUE)",
                    [$companyId, $network]
                ) : false;
            } catch (GrompilDatabaseQueryException) {
                $inserted = false;
            }
            try {
                $saved = $inserted && (bool) db_query_params('company_security.restrict.commit', 'COMMIT');
            } catch (GrompilDatabaseQueryException) {
                $saved = false;
            }
            if (!$saved && $started) {
                try {
                    db_query_params('company_security.restrict.rollback', 'ROLLBACK');
                } catch (GrompilDatabaseQueryException) {
                    // The helper already logged the rollback failure.
                }
            }
            $message = $saved
                ? '<div class="alert alert-success" role="status">Company administration is now restricted to this connection\'s IP address.</div>'
                : '<div class="alert alert-danger" role="alert">The IP restriction could not be saved.</div>';
        }
    } elseif ($action === 'remove_restriction') {
        try {
            $deleted = db_query_params(
                'company_security.remove_restriction',
                "DELETE FROM ft_access_ip_allowlist WHERE company_id = $1 AND scope = 'admin'",
                [$companyId]
            );
        } catch (GrompilDatabaseQueryException) {
            $deleted = false;
        }
        $message = $deleted
            ? '<div class="alert alert-warning" role="status">The IP restriction for company administration has been disabled.</div>'
            : '<div class="alert alert-danger" role="alert">The IP restriction could not be changed.</div>';
    }
}

try {
    $templateResult = db_query_params(
        'company_settings.dispensing_template.current',
        'SELECT preference.dispensing_template_key
         FROM ct_company_preferences AS preference
         WHERE preference.company_id = $1',
        [$companyId]
    );
    $templateRow = $templateResult ? pg_fetch_assoc($templateResult) : false;
    if (is_array($templateRow)) {
        $storedTemplate = (string) $templateRow['dispensing_template_key'];
        if (in_array($storedTemplate, array_column($templateOptions, 'key'), true)) {
            $selectedTemplate = $storedTemplate;
        }
    }
} catch (GrompilDatabaseQueryException) {
    // Keep the standard variant selected if no preference is available.
}

try {
    $rulesResult = db_query_params(
        'company_security.list_restrictions',
        "SELECT host(network) AS address
         FROM ft_access_ip_allowlist
         WHERE company_id = $1 AND scope = 'admin' AND is_active
         ORDER BY id",
        [$companyId]
    );
} catch (GrompilDatabaseQueryException) {
    $rulesResult = false;
}
$allowedIps = [];
while ($rulesResult && $row = pg_fetch_assoc($rulesResult)) {
    $allowedIps[] = (string) $row['address'];
}

page_start(fn_t('company_settings_title'), $user);
?>
<main class="container py-4 flex-grow-1">
    <h1 class="h2 mb-2"><?= e(fn_t('company_settings_title')) ?></h1>
    <p class="text-secondary"><?= e(fn_t('company_settings_intro')) ?> <?= e((string) $company['name']) ?></p>
    <?= $message ?>

    <section class="card shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5"><?= e(fn_t('company_template_heading')) ?></h2>
            <p class="text-secondary"><?= e(fn_t('company_template_help')) ?></p>
            <form method="post" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_dispensing_template">
                <div class="col-md-8">
                    <label class="form-label" for="dispensing-template"><?= e(fn_t('company_template_label')) ?></label>
                    <select class="form-select" id="dispensing-template" name="dispensing_template" required>
                        <?php foreach ($templateOptions as $option): ?>
                            <option value="<?= e((string) $option['key']) ?>" <?= $selectedTemplate === (string) $option['key'] ? 'selected' : '' ?>><?= e((string) $option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success" type="submit"><?= e(fn_t('save')) ?></button>
                </div>
            </form>
        </div>
    </section>

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

