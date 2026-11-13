#!/usr/bin/env bash
set -euo pipefail

# Create starter DokuWiki pages for the tracked Grompil PHP source files.
# The destination is the filesystem directory for the DokuWiki `broncode:` namespace.
# Keep FILES in sync whenever a Grompil PHP source file is added.
PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINATION="${1:-/home/grompilc/dwan/saas/data/pages/broncode}"

FILES=(
    'account.php'
    'address_api.php'
    'admin.php'
    'assets/index.php'
    'authenticator.php'
    'companies.php'
    'company.php'
    'company_members.php'
    'company_roles.php'
    'company_security.php'
    'company_translations.php'
    'compound-products-test.php'
    'compound-products.php'
    'customers.php'
    'dashboard.php'
    'dd-varia.php'
    'dispense.php'
    'docs/index.php'
    'forgot-password.php'
    'includes/bootstrap.php'
    'includes/company_admin.php'
    'includes/compounding_products.php'
    'includes/contact_locations.php'
    'includes/credentials_config.php'
    'includes/database_config.php'
    'includes/database_queries.php'
    'includes/dd_varia.php'
    'includes/dokuwiki.php'
    'includes/do_6_char_code.php'
    'includes/do_6_char_code_test.php'
    'includes/error_page.php'
    'includes/index.php'
    'includes/kbo.php'
    'includes/mailer.php'
    'includes/module_access.php'
    'includes/module_guard.php'
    'includes/password_reset.php'
    'includes/platform_authenticator.php'
    'includes/sam_products.php'
    'includes/session.php'
    'includes/templates.php'
    'includes/user_codes.php'
    'index.php'
    'login.php'
    'logout.php'
    'navigation_access.php'
    'navigation_keywords.php'
    'navigation_labels.php'
    'navigation_preferences.php'
    'not_allowed.php'
    'not_found_404.php'
    'php_info.php'
    'platform-security.php'
    'platform-settings.php'
    'platform_navigation_preferences.php'
    'register.php'
    'reset-password.php'
    'roles.php'
    'sam-products-test.php'
    'sam-products.php'
    'scripts/check_templates.php'
    'scripts/backfill_user_codes.php'
    'scripts/fill_fr_de_translations.php'
    'scripts/generate_navigation.php'
    'scripts/index.php'
    'scripts/sam_atc_classifications_export.php'
    'scripts/sam_delivery_modes.php'
    'scripts/sam_public_catalog_export.php'
    'scripts/sam_public_inventory.php'
    'scripts/test_credentials_config.php'
    'scripts/test_database_connection.php'
    'scripts/test_db_mysql.php'
    'scripts/test_navigation_access.php'
    'scripts/test_password_reset.php'
    'scripts/translations_1_import.php'
    'scripts/translations_2_export.php'
    'select-company.php'
    'setup-admin.php'
    'src/account/account.php'
    'src/account/authenticator.php'
    'src/account/dashboard.php'
    'src/account/forgot_password.php'
    'src/account/login.php'
    'src/account/logout.php'
    'src/account/register.php'
    'src/account/reset_password.php'
    'src/account/select_company.php'
    'src/admin/admin.php'
    'src/admin/dd_varia.php'
    'src/admin/compound_products_test.php'
    'src/admin/platform_security.php'
    'src/admin/platform_settings.php'
    'src/admin/sam_products_test.php'
    'src/admin/setup_admin.php'
    'src/api/address_api.php'
    'src/companies/companies.php'
    'src/companies/company.php'
    'src/companies/company_members.php'
    'src/companies/company_roles.php'
    'src/companies/company_security.php'
    'src/companies/company_translations.php'
    'src/companies/customers.php'
    'src/index.php'
    'src/lang/companies/4/lang_nl.php'
    'src/lang/index.php'
    'src/lang/lang_de.php'
    'src/lang/lang_en.php'
    'src/lang/lang_fr.php'
    'src/lang/lang_nl.php'
    'src/navigation/navigation_access.php'
    'src/navigation/navigation_keywords.php'
    'src/navigation/navigation_labels.php'
    'src/navigation/navigation_preferences.php'
    'src/navigation/platform_navigation_preferences.php'
    'src/pharmacy/dispensing.php'
    'src/site/index.php'
    'src/store/store.php'
    'src/translations/translations.php'
    'src/users/roles.php'
    'src/users/users.php'
    'store.php'
    'tmpl/core/index.php'
    'tmpl/index.php'
    'translations.php'
    'users.php'
)

created=0
skipped=0
mkdir -p -- "$DESTINATION"

for relative in "${FILES[@]}"; do
    source_file="$PROJECT_ROOT/$relative"
    if [[ ! -f "$source_file" ]]; then
        printf 'Source file is missing: %s\n' "$relative" >&2
        exit 1
    fi

    page_relative="${relative%.php}.txt"
    page_path="$DESTINATION/$page_relative"
    title="${relative##*/}"
    title="${title%.php}"
    mkdir -p -- "$(dirname -- "$page_path")"

    if [[ -e "$page_path" ]]; then
        printf 'Already exists; left unchanged: %s\n' "$page_relative"
        skipped=$((skipped + 1))
        continue
    fi

    if (set -o noclobber; printf '~~DISCUSSION~~\n~~NOTRANSLATE~~\n====== %s ======\n' "$title" > "$page_path") 2>/dev/null; then
        printf 'Created: %s\n' "$page_relative"
        created=$((created + 1))
    else
        printf 'Could not create page: %s\n' "$page_relative" >&2
        exit 1
    fi
done

printf '\nFinished. Created: %d; already existed: %d.\n' "$created" "$skipped"
