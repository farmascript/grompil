/**
 *
 * @date_cre: Sat 2026-10-24
 * @date_mut: Sat 2026-10-24
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Checks URL manager visibility, refresh failures and stale access responses.
 *
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../url-manager/app.js'), 'utf8');
assert.equal(source.split('    init();').length, 2, 'Test harness replaces only the startup call');
const testSource = source.replace('    init();', `
    globalThis.navigationTest = {
        state, remoteSync, canAccessCatalogItem, canAccessPersonalLink, render, syncModuleAccess, t,
        setAccess(allowed, known = []) { allowedModules = allowed === null ? null : new Set(allowed); knownModules = new Set(known); }
    };`);

function setup(protocol = 'https:') {
    const elements = new Map();
    const element = (selector) => {
        if (!elements.has(selector)) elements.set(selector, {
            innerHTML: '', textContent: '', value: '', hidden: false,
            setAttribute() {}, style: { setProperty() {} }, dataset: {},
        });
        return elements.get(selector);
    };
    const pending = [];
    const context = {
        URL, location: { protocol, href: `${protocol}//example.test/grompil/url-manager/index.html` },
        window: { addEventListener() {}, GROMPIL_NAVIGATION: JSON.parse(fs.readFileSync(path.join(__dirname, '../navigation.json'), 'utf8')) },
        localStorage: { getItem() { return null; } },
        document: { querySelector: element, querySelectorAll: () => [],
            documentElement: { style: { setProperty() {} }, dataset: {} } },
        fetch: () => new Promise((resolve, reject) => pending.push({ resolve, reject })),
    };
    vm.runInNewContext(testSource, context);
    const api = context.navigationTest;
    api.state.catalog = { application: 'grompil', languages: ['nl'], defaultLanguage: 'nl',
        groups: [{ id: 'platform', i18n: { nl: { title: 'Beheer' } } }, { id: 'company', i18n: { nl: { title: 'Bedrijf' } } }],
        items: [
            { id: 1, module: 'users.php', group: 'platform', public_visibility: false },
            { id: 2, module: 'company_translations.php', group: 'company', public_visibility: false },
            { id: 3, module: 'account.php', group: 'company', public_visibility: true },
        ] };
    return { api, elements, pending };
}

async function run() {
    const { api, elements, pending } = setup();
    const managerHtml = fs.readFileSync(path.join(__dirname, '../url-manager/index.html'), 'utf8');
    const logoutMarkup = managerHtml.match(/<form\b[^>]*id="manager-logout-form"[^>]*>[\s\S]*?<\/form>/)?.[0];
    assert(logoutMarkup && /method="post"/.test(logoutMarkup) && /action="\.\.\/logout.php"/.test(logoutMarkup), 'logout submits to the existing POST endpoint');
    assert(/target="_top"/.test(logoutMarkup), 'logout navigates out of an embedded manager');
    assert(/name="csrf_token"/.test(logoutMarkup) && /name="c"/.test(logoutMarkup), 'logout submits the session token and company context');
    assert.equal(api.t('nav_logout'), 'Uitloggen', 'logout reuses the existing published translation');
    const modules = ['users.php', 'company_translations.php', 'account.php', 'roles.php'];
    api.setAccess(['company_translations.php', 'account.php'], modules);
    assert(api.canAccessCatalogItem(api.state.catalog.items[1]), 'company translations follow actual permissions rather than the old platform flag');
    assert(!api.canAccessCatalogItem(api.state.catalog.items[0]), 'unauthorized platform module stays hidden');
    api.state.personal.favorites = ['grompil:1', 'grompil:2'];
    api.state.personal.links = [
        { id: 'denied', url: '/grompil/users.php?edit=123', title: 'Denied saved link', referenceNumber: 100 },
        { id: 'external', url: 'https://external.test/users.php', title: 'External link', referenceNumber: 101, groupId: 'catalog:company' },
    ];
    api.render();
    assert(elements.get('#manager-logout-form').hidden, 'logout stays hidden until the session is verified');
    const rendered = elements.get('#catalog').innerHTML;
    assert(!rendered.includes('/grompil/users.php') && !rendered.includes('Denied saved link'), 'denied catalog and saved links are absent from rendered groups');
    assert(rendered.includes('company_translations.php') && rendered.includes('External link'), 'authorized company and external links remain visible');
    assert(!elements.get('#favorites-grid').innerHTML.includes('users.php'), 'favorites cannot reintroduce denied modules');
    assert(!rendered.includes('<h2>Beheer</h2>'), 'empty fixed management blocks are hidden');
    api.state.query = 'users';
    api.render();
    assert(!elements.get('#catalog').innerHTML.includes('/grompil/users.php'), 'search cannot reintroduce denied modules');
    api.state.query = '';
    assert(!api.canAccessPersonalLink({ url: './roles.php#edit' }), 'known non-catalog modules use the same access filter');
    assert(api.canAccessPersonalLink({ url: 'https://other.test/grompil/users.php' }), 'external URLs do not inherit the current session restrictions');

    const firstRefresh = api.syncModuleAccess();
    assert(!api.canAccessCatalogItem({ module: 'account.php' }), 'old grants are hidden while refreshing');
    const secondRefresh = api.syncModuleAccess();
    pending[1].resolve({ ok: true, json: async () => ({ allowedModules: ['account.php'], knownModules: modules,
        logout: { csrfToken: 'fake-csrf-current', companySlug: 'current-company' } }) });
    await secondRefresh;
    assert.equal(elements.get('#manager-logout-form').hidden, false, 'verified sessions can log out');
    assert.equal(elements.get('#manager-logout-button').disabled, false);
    assert.equal(elements.get('#manager-logout-form').action, 'https://example.test/grompil/logout.php');
    assert.equal(elements.get('#manager-logout-csrf').value, 'fake-csrf-current');
    assert.equal(elements.get('#manager-logout-company').value, 'current-company', 'logout returns to the active company login');
    assert(!JSON.stringify(api.state.personal).includes('fake-csrf-current'), 'logout token is never persisted or exported');
    pending[0].resolve({ ok: true, json: async () => ({ allowedModules: modules, knownModules: modules,
        logout: { csrfToken: 'fake-csrf-old', companySlug: 'old-company' } }) });
    await firstRefresh;
    assert.equal(elements.get('#manager-logout-company').value, 'current-company', 'older requests cannot change the company used for logout');
    assert(!api.canAccessCatalogItem({ module: 'users.php' }), 'an older response cannot restore revoked permissions');
    assert(api.canAccessCatalogItem({ module: 'account.php' }));
    const failedRefresh = api.syncModuleAccess();
    pending[2].reject(new Error('offline'));
    await failedRefresh;
    assert(elements.get('#manager-logout-form').hidden && elements.get('#manager-logout-button').disabled, 'failed session checks hide and disable logout');
    assert.equal(elements.get('#manager-logout-csrf').value, '', 'expired session tokens are cleared');
    assert.equal(elements.get('#manager-logout-company').value, '');
    assert(!api.canAccessCatalogItem({ module: 'account.php' }), 'connection failures never restore protected tiles');
    assert(api.canAccessCatalogItem({ module: 'index.php' }), 'public home remains available after a failure');

    const generalLogout = api.syncModuleAccess();
    pending[3].resolve({ ok: true, json: async () => ({ allowedModules: ['account.php'], knownModules: modules,
        logout: { csrfToken: 'fake-csrf-platform', companySlug: '' } }) });
    await generalLogout;
    assert.equal(elements.get('#manager-logout-form').hidden, false, 'platform sessions without a company can also log out');
    assert.equal(elements.get('#manager-logout-company').value, '', 'no active company uses the general login screen');
    const anonymous = api.syncModuleAccess();
    pending[4].resolve({ ok: true, json: async () => ({ allowedModules: ['index.php'], knownModules: modules, logout: null }) });
    await anonymous;
    assert(elements.get('#manager-logout-form').hidden, 'signed-out sessions do not offer logout');

    const { api: local, elements: localElements } = setup('file:');
    assert(local.canAccessCatalogItem({ module: 'users.php' }), 'standalone offline catalogs retain their complete function list');
    local.render();
    assert(localElements.get('#manager-logout-form').hidden, 'local-file managers do not submit session logout requests');
    console.log('Navigation visibility, logout and refresh checks passed.');
}

run().catch((error) => { console.error(error); process.exitCode = 1; });
