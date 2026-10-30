/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Sat 2026-10-24
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Handles search, grouping, drag and drop, and saving in the URL manager.
 *
 */

(() => {
    'use strict';

    // Lets the management navbar reuse this manager tab by its stable target name.
    window.name = 'grompil-url-manager';

    const DB_NAME = 'grompil-url-manager';
    const DB_VERSION = 1;
    const STORE = 'state';
    const $ = (selector) => document.querySelector(selector);
    const bundledCatalog = window.GROMPIL_NAVIGATION || null;
    const defaultPages = () => [{ id: 'page:1', title: 'blad1', defaultNumber: 1 }, { id: 'page:2', title: 'blad2', defaultNumber: 2 }];
    const emptyPersonalState = () => ({ favorites: [], hidden: [], overrides: {}, links: [], settings: {}, groups: [], assignments: {}, groupOrder: [], pages: defaultPages() });
    const state = { catalog: null, personal: emptyPersonalState(), platformLayout: { groups: [], assignments: {}, groupColors: {}, groupTitles: {}, groupOrder: [] }, query: '', favoritesOnly: false, editingLinkId: null, editingGroupId: null, selectedGroupId: null, groupEditScope: 'company' };
    const remoteSync = { enabled: false, canEdit: true, isPlatformOperator: false, phpVersion: '', userName: '', userRole: '', companyContextName: '', application: 'grompil', companyId: 0, companyName: '', storageStatus: 'local', csrfToken: '', updatedAt: '' };
    let platformCsrfToken = '';
    let platformWrite = Promise.resolve();
    let remoteWrite = Promise.resolve();
    let remoteSyncErrorShown = false;
    let activePageId = 'page:1';
    let activePageContext = null;
    let managerLabels = bundledCatalog?.managerI18n || {};
    let allowedModules = null;
    let knownModules = new Set();
    let logoutContext = null;
    let accessRequestSequence = 0;

    function canAccessCatalogItem(item) {
        // Standalone local files have no authenticated server context.
        if (!['http:', 'https:'].includes(location.protocol)) return true;
        return allowedModules ? allowedModules.has(item.module) : item.module === 'index.php';
    }

    function canAccessPersonalLink(link) {
        if (!['http:', 'https:'].includes(location.protocol)) return true;
        try {
            const base = new URL('../', location.href);
            const target = new URL(personalLinkUrl(link.url));
            if (target.origin !== base.origin || !target.pathname.startsWith(base.pathname)) return true;
            const module = decodeURIComponent(target.pathname.slice(base.pathname.length));
            const known = knownModules.has(module) || (state.catalog?.items || []).some((item) => item.module === module);
            return !known || canAccessCatalogItem({ module });
        } catch { return false; }
    }

    async function syncModuleAccess() {
        if (!['http:', 'https:'].includes(location.protocol)) return;
        const sequence = ++accessRequestSequence;
        allowedModules = null;
        logoutContext = null;
        render();
        try {
            const response = await fetch('../navigation_access.php', {
                headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin',
            });
            if (!response.ok) return;
            const result = await response.json();
            if (sequence !== accessRequestSequence || !Array.isArray(result.allowedModules)
                || !Array.isArray(result.knownModules)
                || ![...result.allowedModules, ...result.knownModules].every((module) => typeof module === 'string')) return;
            allowedModules = new Set(result.allowedModules);
            knownModules = new Set(result.knownModules);
            if (typeof result.logout?.csrfToken === 'string' && result.logout.csrfToken
                && typeof result.logout.companySlug === 'string') {
                logoutContext = result.logout;
            }
            render();
        } catch { /* Keep protected tiles hidden when session access cannot be checked. */ }
    }

    function t(key, values = {}) {
        const token = key === 'nav_logout' ? key : `manager_${key}`;
        const candidates = [managerLabels[language()]?.[token], managerLabels.en?.[token], managerLabels.nl?.[token]];
        const text = candidates.find((value) => typeof value === 'string' && value.trim()) || `!${token}!`;
        return text.replace(/\{(\w+)\}/g, (match, name) => String(values[name] ?? match));
    }

    function pageTitle(page) {
        return localizedGroupTitle(page.titles) || (page.defaultNumber ? t('page_number', { number: page.defaultNumber }) : page.title);
    }

    function translateControls() {
        document.querySelectorAll('[data-i18n]').forEach((element) => { element.textContent = t(element.dataset.i18n); });
        for (const attribute of ['title', 'aria-label', 'placeholder']) {
            document.querySelectorAll(`[data-i18n-${attribute}]`).forEach((element) => {
                element.setAttribute(attribute, t(element.getAttribute(`data-i18n-${attribute}`)));
            });
        }
    }

    async function loadManagerLabels() {
        if (!['http:', 'https:'].includes(location.protocol)) return;
        try {
            const response = await fetch('../navigation_labels.php', { cache: 'no-store' });
            if (!response.ok) return;
            const labels = await response.json();
            if (labels && typeof labels === 'object' && !Array.isArray(labels)) managerLabels = labels;
        } catch { /* The bundled catalog supplies labels for offline use. */ }
    }
    function storageStateKey() { return remoteSync.companyId ? `company:${remoteSync.companyId}:personal` : 'personal'; }

    function rememberActivePage() {
        try { localStorage.setItem(`grompil-manager:${storageStateKey()}:page`, activePageId); } catch { /* Navigation remains available without browser storage. */ }
    }

    async function readPersonalCopy(key) {
        const db = await openDatabase();
        try {
            return await new Promise((resolve, reject) => {
                const request = db.transaction(STORE).objectStore(STORE).get(key);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        } finally { db.close(); }
    }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => request.result.createObjectStore(STORE);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function readState() {
        try {
            const db = await openDatabase();
            const value = await new Promise((resolve, reject) => {
                const request = db.transaction(STORE).objectStore(STORE).get(storageStateKey());
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            const savedCatalog = await new Promise((resolve, reject) => {
                const request = db.transaction(STORE).objectStore(STORE).get('catalog');
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            db.close();
            if (value && typeof value === 'object') state.personal = normalizePersonalState({ ...state.personal, ...value });
            if (savedCatalog && validCatalog(savedCatalog)) state.catalog = savedCatalog;
        } catch (error) { toast('Lokale opslag is niet beschikbaar in deze browser.'); }
        $('#base-url').value = state.personal.settings.baseUrl || guessBaseUrl();
    }

    async function saveState(syncRemote = true) {
        state.personal = normalizePersonalState(state.personal);
        try {
            const db = await openDatabase();
            await new Promise((resolve, reject) => {
                const tx = db.transaction(STORE, 'readwrite');
                tx.objectStore(STORE).put(state.personal, storageStateKey());
                if (state.catalog) tx.objectStore(STORE).put(state.catalog, 'catalog');
                tx.oncomplete = resolve;
                tx.onerror = () => reject(tx.error);
            });
            db.close();
        } catch (error) { toast('Opslaan in de browser is mislukt.'); }
        if (syncRemote && remoteSync.enabled && remoteSync.canEdit) await saveRemotePreferences();
    }

    function normalizePersonalState(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return emptyPersonalState();
        const normalized = { ...emptyPersonalState(), ...value };
        for (const key of ['favorites', 'hidden', 'links', 'groups']) {
            if (!Array.isArray(normalized[key])) normalized[key] = [];
        }
        if (!Array.isArray(normalized.groupOrder)) normalized.groupOrder = [];
        for (const key of ['overrides', 'settings', 'assignments']) {
            if (!normalized[key] || typeof normalized[key] !== 'object' || Array.isArray(normalized[key])) normalized[key] = {};
        }
        const baseUrl = normalized.settings.baseUrl || guessBaseUrl();
        const pageIds = new Set();
        normalized.pages = (Array.isArray(normalized.pages) ? normalized.pages : defaultPages()).filter((page) => {
            if (!page || typeof page.id !== 'string' || !page.id || typeof page.title !== 'string' || !page.title.trim() || pageIds.has(page.id)) return false;
            pageIds.add(page.id);
            return true;
        }).map((page) => ({ id: page.id, title: page.title.trim().slice(0, 60),
            defaultNumber: Number.isSafeInteger(page.defaultNumber) && page.defaultNumber > 0 ? page.defaultNumber : (/^blad[1-9]\d*$/.test(page.title) ? Number(page.title.slice(4)) : undefined),
            titles: page.titles && typeof page.titles === 'object' && !Array.isArray(page.titles)
                ? Object.fromEntries(Object.entries(page.titles).filter(([lang, title]) => ['nl', 'fr', 'en', 'de'].includes(lang) && typeof title === 'string').map(([lang, title]) => [lang, title.trim().slice(0, 60)])) : {},
        }));
        if (!normalized.pages.length) normalized.pages = defaultPages();
        normalized.groups = normalized.groups.map((group) => ({ ...group,
            pageId: normalized.pages.some((page) => page.id === group.pageId) ? group.pageId : normalized.pages[0].id,
        }));
        let baseOrigin = '';
        try { baseOrigin = new URL(baseUrl).origin; } catch { /* Leave links untouched without a valid base URL. */ }
        if (baseOrigin) {
            normalized.links = normalized.links.map((link) => {
                if (typeof link.url !== 'string' || !/^https?:\/\//i.test(link.url)) return link;
                try {
                    const parsed = new URL(link.url);
                    if (parsed.origin !== baseOrigin) return link;
                    return { ...link, url: `${parsed.pathname}${parsed.search}${parsed.hash}` };
                } catch { return link; }
            });
        }
        const usedNumbers = new Set();
        let nextNumber = 100;
        normalized.links = normalized.links.map((link) => {
            const number = Number(link.referenceNumber);
            if (Number.isSafeInteger(number) && number >= 100 && !usedNumbers.has(number)) {
                usedNumbers.add(number);
                return link;
            }
            while (usedNumbers.has(nextNumber)) nextNumber++;
            const numberedLink = { ...link, referenceNumber: nextNumber };
            usedNumbers.add(nextNumber++);
            return numberedLink;
        });
        return normalized;
    }

    async function saveRemotePreferences() {
        remoteWrite = remoteWrite.catch(() => {}).then(async () => {
            const response = await fetch(`../navigation_preferences.php?application=${encodeURIComponent(remoteSync.application)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ csrfToken: remoteSync.csrfToken, configuration: state.personal }),
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('preferences_not_saved');
            const result = await response.json();
            remoteSync.updatedAt = result.updatedAt || remoteSync.updatedAt;
            remoteSyncErrorShown = false;
        });
        try {
            await remoteWrite;
        } catch (error) {
            if (!remoteSyncErrorShown) toast('Opslaan op de server is niet gelukt. De browserkopie is wel bewaard.');
            remoteSyncErrorShown = true;
        }
    }

    let remoteSyncPromise = null;

    function syncRemotePreferences() {
        if (remoteSyncPromise) return remoteSyncPromise;
        remoteSyncPromise = performRemoteSync().finally(() => { remoteSyncPromise = null; });
        return remoteSyncPromise;
    }

    async function performRemoteSync() {
        if (location.protocol !== 'http:' && location.protocol !== 'https:') return;
        // The platform endpoint is loaded first. A platform administrator without
        // an active company has no company preferences to fetch; avoid an expected
        // 409 request and retain the browser's personal layout in that case.
        if (!remoteSync.userName || (remoteSync.isPlatformOperator && !remoteSync.companyContextName)) {
            const previousCompanyId = remoteSync.companyId;
            const standalone = previousCompanyId ? await readPersonalCopy('personal') : state.personal;
            remoteSync.enabled = false;
            remoteSync.canEdit = true;
            remoteSync.companyId = 0;
            remoteSync.companyName = '';
            remoteSync.storageStatus = 'local';
            remoteSync.csrfToken = '';
            remoteSync.updatedAt = '';
            state.personal = normalizePersonalState(standalone);
            render();
            return;
        }
        try {
            const response = await fetch(`../navigation_preferences.php?application=${encodeURIComponent(remoteSync.application)}`, {
                headers: { 'Accept': 'application/json' }, cache: 'no-store',
            });
            if (response.status === 401) {
                remoteSync.enabled = false;
                remoteSync.storageStatus = 'local';
                render();
                return;
            }
            if (response.status === 409) {
                const previousCompanyId = remoteSync.companyId;
                // Without a company context, keep the standalone browser layout.
                // When leaving a company, restore that separate layout rather than
                // copying company data into the global browser configuration.
                const standalone = previousCompanyId ? await readPersonalCopy('personal') : state.personal;
                remoteSync.enabled = false;
                remoteSync.canEdit = true;
                remoteSync.companyId = 0;
                remoteSync.companyName = '';
                remoteSync.storageStatus = 'local';
                remoteSync.csrfToken = '';
                remoteSync.updatedAt = '';
                state.personal = normalizePersonalState(standalone);
                render();
                return;
            }
            if (!response.ok) {
                remoteSync.storageStatus = 'unavailable';
                render();
                if (!remoteSyncErrorShown) toast('Serveropslag is nog niet beschikbaar. Voer de navigatievoorkeuren-migratie uit.');
                remoteSyncErrorShown = true;
                return;
            }
            const result = await response.json();
            if (!result.authenticated || !result.csrfToken) {
                remoteSync.enabled = false;
                remoteSync.storageStatus = 'local';
                render();
                return;
            }
            const previousCompanyId = remoteSync.companyId;
            const nextCompanyId = Number(result.companyId) || 0;
            if (remoteSync.companyId !== nextCompanyId) remoteSync.updatedAt = '';
            remoteSync.enabled = true;
            remoteSync.storageStatus = 'saas';
            remoteSync.canEdit = Boolean(result.canEdit);
            remoteSync.csrfToken = result.csrfToken;
            remoteSync.companyId = nextCompanyId;
            remoteSync.companyName = String(result.companyName || t('company'));
            let companyLocalState = null;
            let legacyLocalState = null;
            if (remoteSync.companyId) {
                const db = await openDatabase();
                companyLocalState = await new Promise((resolve, reject) => {
                    const request = db.transaction(STORE).objectStore(STORE).get(storageStateKey());
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });
                db.close();
                if (companyLocalState && typeof companyLocalState === 'object') {
                    state.personal = normalizePersonalState(companyLocalState);
                } else {
                    // Never copy an old browser layout from a different or unknown company.
                    legacyLocalState = previousCompanyId === 0
                        ? normalizePersonalState(state.personal)
                        : { ...emptyPersonalState(), settings: state.personal.settings || {} };
                    state.personal = {
                        ...legacyLocalState,
                        hidden: [], overrides: {}, links: [], groups: [], assignments: {}, pages: defaultPages(), groupOrder: [],
                    };
                }
            }
            if (result.configuration && typeof result.configuration === 'object') {
                const remoteTimestamp = Date.parse(result.updatedAt || '') || 0;
                const localTimestamp = Date.parse(remoteSync.updatedAt || '') || 0;
                if (!remoteSync.updatedAt || remoteTimestamp > localTimestamp) {
                    state.personal = normalizePersonalState(result.configuration);
                    remoteSync.updatedAt = result.updatedAt || '';
                    await saveState();
                    $('#base-url').value = state.personal.settings.baseUrl || guessBaseUrl();
                    render();
                    toast('Bedrijfsnavigatie gesynchroniseerd.');
                }
            } else if (remoteSync.canEdit) {
                const hasLegacyLayout = legacyLocalState && (
                    legacyLocalState.links.length > 0 || legacyLocalState.groups.length > 0
                    || legacyLocalState.hidden.length > 0 || Object.keys(legacyLocalState.overrides).length > 0
                    || Object.keys(legacyLocalState.assignments).length > 0
                    || legacyLocalState.pages.length !== 2
                    || legacyLocalState.pages.some((page, index) => page.id !== `page:${index + 1}` || page.title !== `blad${index + 1}` || Object.keys(page.titles || {}).length > 0)
                );
                const shouldImportLegacy = previousCompanyId === 0 && !companyLocalState && hasLegacyLayout
                    && window.confirm(`Er staat nog een lokale managerindeling in deze browser. Wil je die overnemen als gedeelde navigatie voor ${result.companyName || 'dit bedrijf'}?`);
                if (shouldImportLegacy) {
                    state.personal = legacyLocalState;
                    await saveState();
                    toast('Lokale indeling overgenomen als bedrijfsnavigatie.');
                } else if (!companyLocalState) {
                    await saveState(false);
                }
            } else if (!companyLocalState) {
                await saveState(false);
            }
            render();
        } catch (error) {
            remoteSync.storageStatus = 'unavailable';
            render();
            if (!remoteSyncErrorShown) toast('De server voor bedrijfsnavigatie is niet bereikbaar.');
            remoteSyncErrorShown = true;
        }
    }

    function guessBaseUrl() {
        if (location.protocol === 'http:' || location.protocol === 'https:') return new URL('../', location.href).href;
        return '';
    }

    function language() {
        const available = state.catalog?.languages || ['nl'];
        const preferred = state.personal.settings.language;
        const selected = available.includes(preferred) ? preferred : (available.includes(state.catalog?.defaultLanguage) ? state.catalog.defaultLanguage : available[0]);
        return String(selected || 'nl').toLowerCase();
    }

    function localizedGroupTitle(titles, lang = language()) {
        if (!titles || typeof titles !== 'object') return '';
        const key = Object.keys(titles).find((entry) => entry.toLowerCase() === lang.toLowerCase());
        return key ? titles[key] : '';
    }

    function platformGroupTitle(group, lang = language()) {
        return localizedGroupTitle(group.titles, lang)
            || localizedGroupTitle(group.titles, state.catalog?.defaultLanguage || 'nl')
            || group.title;
    }

    function textFor(record, lang = language()) {
        const translations = record?.i18n || {};
        const value = translations[lang] || translations.en || translations.nl || Object.values(translations)[0] || {};
        return value;
    }

    function itemKey(item) { return `${state.catalog?.application || 'grompil'}:${item.id}`; }
    function groupKey(group) { return `catalog:${group.id}`; }
    function canEditPlatformLayout() { return remoteSync.isPlatformOperator; }
    function catalogNumber(item) {
        const fixedNumber = Number(item.sequence_number);
        if (Number.isSafeInteger(fixedNumber) && fixedNumber > 0) return fixedNumber;
        const index = (state.catalog?.items || []).findIndex((entry) => entry.id === item.id);
        return index >= 0 ? index + 1 : '';
    }
    function formatReferenceNumber(number) {
        return String(number).padStart(2, '0');
    }

    function allGroups() {
        const base = (state.catalog?.groups || []).map((group) => ({ key: groupKey(group), title: localizedGroupTitle(state.platformLayout.groupTitles?.[groupKey(group)]) || textFor(group).title || group.id, kind: 'catalog', color: state.platformLayout.groupColors?.[groupKey(group)] || '' }));
        const platform = (state.platformLayout.groups || []).map((group) => ({ ...group, key: group.id, title: platformGroupTitle(group), kind: 'platform' }));
        const custom = (state.personal.groups || []).map((group) => ({ ...group, key: group.id, title: localizedGroupTitle(group.titles) || group.title, kind: 'custom' }));
        const fixed = [...base, ...platform];
        const fixedSlots = state.platformLayout.groupOrder.length ? state.platformLayout.groupOrder : fixed.map((group) => group.key);
        const companySlots = state.personal.groupOrder.length ? state.personal.groupOrder : custom.map((group) => group.key);
        const placeSlots = (availableGroups, slots, family) => {
            const byKey = new Map(availableGroups.map((group) => [group.key, group]));
            const placed = new Set();
            const result = slots.map((key, index) => {
                if (key === null) return { key: `gap:${family}:${index}`, title: '', kind: 'gap', family, slotIndex: index };
                const group = byKey.get(key);
                if (!group) return null;
                placed.add(key);
                return group;
            }).filter(Boolean);
            for (const group of availableGroups) if (!placed.has(group.key)) result.push(group);
            return result;
        };
        // Shared Grompil groups always follow generated groups and precede tenant groups.
        return [...placeSlots(fixed, fixedSlots, 'platform'), ...placeSlots(custom, companySlots, 'company')];
    }

    async function syncPlatformLayout() {
        if (!['http:', 'https:'].includes(location.protocol)) return;
        try {
            const response = await fetch(`../platform_navigation_preferences.php?application=${encodeURIComponent(remoteSync.application)}`, {
                headers: { 'Accept': 'application/json' }, cache: 'no-store',
            });
            if (!response.ok) return;
            const result = await response.json();
            remoteSync.isPlatformOperator = Boolean(result.isPlatformOperator);
            remoteSync.phpVersion = typeof result.phpVersion === 'string' ? result.phpVersion : '';
            remoteSync.userName = typeof result.userName === 'string' ? result.userName : '';
            remoteSync.userRole = typeof result.userRole === 'string' ? result.userRole : '';
            remoteSync.companyContextName = typeof result.companyName === 'string' ? result.companyName : '';
            platformCsrfToken = result.csrfToken || '';
            if (result.configuration && typeof result.configuration === 'object') {
                state.platformLayout = {
                    groups: Array.isArray(result.configuration.groups) ? result.configuration.groups : [],
                    assignments: result.configuration.assignments && typeof result.configuration.assignments === 'object' ? result.configuration.assignments : {},
                    groupColors: result.configuration.groupColors && typeof result.configuration.groupColors === 'object' ? result.configuration.groupColors : {},
                    groupTitles: result.configuration.groupTitles && typeof result.configuration.groupTitles === 'object' ? result.configuration.groupTitles : {},
                    groupOrder: Array.isArray(result.configuration.groupOrder) ? result.configuration.groupOrder : [],
                };
            }
            render();
        } catch { /* Keep the generated catalog visible if platform preferences are offline. */ }
    }

    async function savePlatformLayout() {
        if (!canEditPlatformLayout() || !platformCsrfToken) { toast('Alleen de platformbeheerder kan de vaste Grompil-navigatie aanpassen.'); return false; }
        platformWrite = platformWrite.catch(() => {}).then(async () => {
            const response = await fetch(`../platform_navigation_preferences.php?application=${encodeURIComponent(remoteSync.application)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ csrfToken: platformCsrfToken, configuration: state.platformLayout }),
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('platform_preferences_not_saved');
            const result = await response.json();
            remoteSync.platformUpdatedAt = result.updatedAt || '';
            return true;
        });
        try { return await platformWrite; }
        catch { toast('De vaste Grompil-indeling kon niet op de server worden opgeslagen.'); return false; }
    }

    function shownItem(item) {
        const override = state.personal.overrides[itemKey(item)] || {};
        const original = textFor(item);
        return { ...item, title: override.title ?? original.title ?? item.module, description: override.description ?? original.description ?? '', keywords: original.keywords || [], href: moduleUrl(item.module) };
    }

    function moduleUrl(modulePath) {
        const base = state.personal.settings.baseUrl || guessBaseUrl();
        if (!base) return '#';
        try { return new URL(modulePath.split('/').map(encodeURIComponent).join('/'), base.endsWith('/') ? base : `${base}/`).href; }
        catch { return '#'; }
    }

    function personalLinkUrl(value) {
        const url = String(value || '').trim();
        if (!url || url.startsWith('//')) return '#';
        try {
            const base = state.personal.settings.baseUrl || guessBaseUrl();
            const parsed = new URL(url, base || location.href);
            return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '#';
        } catch { return '#'; }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    }

    let descriptionTooltip = null;
    let tooltipTarget = null;

    function showDescriptionTooltip(target) {
        const description = target?.getAttribute('data-bs-title');
        if (!description) return;
        if (!descriptionTooltip) {
            descriptionTooltip = document.createElement('div');
            descriptionTooltip.id = 'manager-description-tooltip';
            descriptionTooltip.className = 'tooltip bs-tooltip-top';
            descriptionTooltip.setAttribute('role', 'tooltip');
            descriptionTooltip.innerHTML = '<div class="tooltip-arrow"></div><div class="tooltip-inner"></div>';
            document.body.append(descriptionTooltip);
        }
        tooltipTarget = target;
        descriptionTooltip.querySelector('.tooltip-inner').textContent = description;
        descriptionTooltip.hidden = false;
        target.setAttribute('aria-describedby', descriptionTooltip.id);

        const targetRect = target.getBoundingClientRect();
        const tooltipRect = descriptionTooltip.getBoundingClientRect();
        const gap = 8;
        const showAbove = targetRect.top >= tooltipRect.height + gap + 8;
        const left = Math.max(8, Math.min(
            targetRect.left + (targetRect.width - tooltipRect.width) / 2,
            window.innerWidth - tooltipRect.width - 8
        ));
        const top = showAbove ? targetRect.top - tooltipRect.height - gap : targetRect.bottom + gap;
        const arrowLeft = Math.max(10, Math.min(targetRect.left + targetRect.width / 2 - left, tooltipRect.width - 10));
        descriptionTooltip.className = `tooltip bs-tooltip-${showAbove ? 'top' : 'bottom'}`;
        descriptionTooltip.style.left = `${left}px`;
        descriptionTooltip.style.top = `${top}px`;
        descriptionTooltip.style.setProperty('--tooltip-arrow-left', `${arrowLeft}px`);
    }

    function hideDescriptionTooltip(target = null) {
        if (!descriptionTooltip || (target && target !== tooltipTarget)) return;
        tooltipTarget?.removeAttribute('aria-describedby');
        tooltipTarget = null;
        descriptionTooltip.hidden = true;
    }

    function matches(item) {
        const term = state.query.trim().toLocaleLowerCase();
        if (state.favoritesOnly && !state.personal.favorites.includes(itemKey(item))) return false;
        if (!term) return true;
        const shown = shownItem(item);
        return [formatReferenceNumber(catalogNumber(item)), shown.title, shown.description, ...(shown.keywords || []), item.module, item.action].filter(Boolean).join(' ').toLocaleLowerCase().includes(term);
    }

    function tile(item, personal = false) {
        const canEditShared = !remoteSync.enabled || remoteSync.canEdit;
        const linkTarget = window.parent !== window ? '_top' : '_blank';
        if (personal) {
            const href = personalLinkUrl(item.url);
            const number = Number(item.referenceNumber) || 100;
            return `<article class="tile" draggable="${canEditShared}" data-drag-key="link:${escapeHtml(item.id)}"><a href="${escapeHtml(href)}" target="${linkTarget}" rel="noopener noreferrer" class="tile-link"><span class="tile-number" aria-label="Nummer ${number}">${number}</span><span class="tile-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M6.2 4.2 4.7 5.7a3 3 0 0 0 4.2 4.2l1.4-1.4M9.8 11.8l1.5-1.5a3 3 0 0 0-4.2-4.2L5.7 7.5"/></svg></span><span class="tile-title">${escapeHtml(item.title)}</span></a>${canEditShared ? `<button type="button" class="favorite link-edit-button" data-edit-link="${escapeHtml(item.id)}" title="${escapeHtml(t('edit_link'))}" aria-label="${escapeHtml(t('edit_link'))}"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m11.8 2.2 2 2a1 1 0 0 1 0 1.4l-7.9 7.9-3.3.8.8-3.3 7.9-7.9a1 1 0 0 1 1.5 0Z"/><path d="m9.9 4.1 2 2"/></svg></button>` : ''}</article>`;
        }
        const shown = shownItem(item);
        const number = formatReferenceNumber(catalogNumber(item));
        const fav = state.personal.favorites.includes(itemKey(item));
        const disabled = shown.href === '#';
        const key = itemKey(item);
        const tooltipAttributes = shown.description
            ? `data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="${escapeHtml(shown.description)}"`
            : '';
        const canMoveFixed = canEditPlatformLayout();
        const fixedDragAttributes = canMoveFixed ? `draggable="true" data-drag-key="fixed:${escapeHtml(key)}"` : 'draggable="false" data-fixed-link="1"';
        return `<article class="tile" ${fixedDragAttributes}><a draggable="false" href="${disabled ? '#' : escapeHtml(shown.href)}" target="${linkTarget}" rel="noopener noreferrer" ${disabled ? 'data-needs-base="1"' : ''} ${tooltipAttributes} class="tile-link"><span class="tile-number" aria-label="Vast nummer ${number}">${number}</span><span class="tile-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M8 4.5v3M3 7.5h10M3 7.5v3m10-3v3"/><rect x="6.2" y="1.5" width="3.6" height="3" rx=".6"/><rect x="1.3" y="10.2" width="3.4" height="3" rx=".6"/><rect x="11.3" y="10.2" width="3.4" height="3" rx=".6"/></svg></span><span class="tile-title">${escapeHtml(shown.title)}</span></a>${canEditShared ? `<button class="favorite ${fav ? 'is-favorite' : ''}" data-favorite="${escapeHtml(key)}" title="${escapeHtml(t(fav ? 'favorite_remove' : 'favorite_add'))}" aria-label="${escapeHtml(t(fav ? 'favorite_remove' : 'favorite_add'))}">${fav ? '★' : '☆'}</button>` : ''}</article>`;
    }

    function render() {
        const logoutForm = $('#manager-logout-form');
        logoutForm.hidden = !logoutContext;
        $('#manager-logout-button').disabled = !logoutContext;
        $('#manager-logout-csrf').value = logoutContext?.csrfToken || '';
        $('#manager-logout-company').value = logoutContext?.companySlug || '';
        if (logoutContext) logoutForm.action = new URL('../logout.php', location.href).href;
        $('#manager-version').textContent = window.GROMPIL_URL_MANAGER_VERSION || '0.2.1';
        const phpVersion = $('#php-version');
        phpVersion.hidden = !remoteSync.phpVersion;
        phpVersion.textContent = remoteSync.phpVersion ? `PHP ${remoteSync.phpVersion}` : '';
        const userContext = $('#user-context');
        userContext.hidden = !remoteSync.userName;
        userContext.textContent = remoteSync.userName
            ? `Ingelogd: ${remoteSync.userName}${remoteSync.userRole ? ` · ${remoteSync.userRole}` : ''}`
            : '';
        const workContext = $('#work-context');
        workContext.hidden = !remoteSync.companyContextName;
        workContext.textContent = remoteSync.companyContextName
            ? `${remoteSync.isPlatformOperator ? 'Werkend voor' : 'Ingelogd bij'} ${remoteSync.companyContextName}`
            : '';
        hideDescriptionTooltip();
        if (activePageContext !== storageStateKey()) {
            activePageContext = storageStateKey();
            try { activePageId = localStorage.getItem(`grompil-manager:${activePageContext}:page`) || state.personal.pages[0].id; }
            catch { activePageId = state.personal.pages[0].id; }
        }
        if (!state.personal.pages.some((page) => page.id === activePageId)) activePageId = state.personal.pages[0].id;
        const canEditPages = !remoteSync.enabled || remoteSync.canEdit;
        const basePageId = state.personal.pages[0]?.id;
        $('#page-navigation').innerHTML = state.personal.pages.map((page) => {
            const canRenamePage = remoteSync.isPlatformOperator || (page.id !== basePageId && (!remoteSync.enabled || remoteSync.canEdit));
            const lockedBasePage = !remoteSync.isPlatformOperator && page.id === basePageId;
            const pageEditor = canRenamePage
                ? `<button type="button" class="icon-button page-rename" data-rename-page="${escapeHtml(page.id)}" aria-label="${escapeHtml(t('rename_page_label', { name: pageTitle(page) }))}" title="${escapeHtml(t('rename_page'))}">✎</button>`
                : (lockedBasePage ? '<span class="page-lock" role="img" aria-label="Alleen de platformbeheerder kan dit blad wijzigen" title="Alleen de platformbeheerder kan dit blad wijzigen"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3" y="7" width="10" height="8" rx="1.5"></rect><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"></path></svg></span>' : '');
            return `<span class="page-button-group"><button type="button" class="button ${page.id === activePageId ? 'button-primary' : 'button-light'}" data-page="${escapeHtml(page.id)}" ${page.id === activePageId ? 'aria-current="page"' : ''}>${escapeHtml(pageTitle(page))}</button>${pageEditor}</span>`;
        }).join('')
            + (canEditPages ? `<button type="button" class="button button-light" id="add-page">${escapeHtml(t('add_page'))}</button>` : '');
        const storageStatus = $('#storage-status');
        if (canEditPlatformLayout()) {
            storageStatus.textContent = t('platform_operator');
            storageStatus.className = 'storage-status is-platform';
            storageStatus.title = 'Je beheert hier de vaste Grompil-navigatie die voor alle bedrijven geldt.';
        } else if (remoteSync.storageStatus === 'saas') {
            storageStatus.textContent = `SaaS · ${remoteSync.companyName || t('company')}`;
            storageStatus.className = 'storage-status is-saas';
            storageStatus.title = 'Deze managerindeling is gekoppeld aan het bedrijf en wordt gedeeld via de database.';
        } else if (remoteSync.storageStatus === 'unavailable') {
            storageStatus.textContent = t('unavailable');
            storageStatus.className = 'storage-status is-unavailable';
            storageStatus.title = 'De bedrijfsindeling kon niet met de server worden gecontroleerd.';
        } else {
            storageStatus.textContent = t('local');
            storageStatus.className = 'storage-status is-local';
            storageStatus.title = 'Deze managerindeling wordt alleen in deze browser bewaard.';
        }
        const canEditShared = !remoteSync.enabled || remoteSync.canEdit;
        $('#add-group').hidden = !canEditShared;
        $('#add-platform-group').hidden = !canEditPlatformLayout() || activePageId !== state.personal.pages[0].id;
        $('#add-group').textContent = t('add_company_group');
        $('#favorites-filter').textContent = t(state.favoritesOnly ? 'favorites_only' : 'favorites');
        $('#add-link').hidden = !canEditShared;
        $('#import-button').hidden = !canEditShared;
        if (!state.personal.settings || typeof state.personal.settings !== 'object') {
            state.personal.settings = {};
        }
        const languageSelect = $('#language-select');
        const columns = [1, 2, 3].includes(Number(state.personal.settings.columns))
            ? Number(state.personal.settings.columns)
            : 2;
        const savedTextSize = Number(state.personal.settings.textSize ?? 1);
        const textSize = [0, 1, 2].includes(savedTextSize) ? savedTextSize : 1;
        state.personal.settings.columns = columns;
        state.personal.settings.textSize = textSize;
        $('#column-layout').value = String(columns);
        document.documentElement.style.setProperty('--manager-columns', String(columns));
        document.documentElement.dataset.textSize = String(textSize);
        $('#text-size-reset').setAttribute('aria-pressed', textSize === 1 ? 'true' : 'false');
        $('#text-size-reset').title = t('text_size');
        $('#text-smaller').disabled = textSize === 0;
        $('#text-larger').disabled = textSize === 2;
        const availableLanguages = state.catalog?.languages || [];
        languageSelect.innerHTML = availableLanguages.map((code) => `<option value="${escapeHtml(code)}">${escapeHtml(code.toUpperCase())}</option>`).join('');
        languageSelect.disabled = availableLanguages.length < 2;
        languageSelect.value = language();
        const catalog = $('#catalog');
        if (!state.catalog && !state.personal.groups.length && !state.personal.links.length) {
            catalog.innerHTML = `<div class="content-section empty-note">Nog geen functielijst geladen. Open ⚙ en kies navigation.json.</div>`;
            $('#catalog-status').textContent = 'Geen catalogus geladen';
        } else {
            const everyGroup = allGroups();
            const firstPage = state.personal.pages[0].id;
            const groups = everyGroup.filter((group) => group.kind === 'custom' ? group.pageId === activePageId : activePageId === firstPage);
            const items = (state.catalog?.items || []).filter((item) =>
                canAccessCatalogItem(item)
                && !state.personal.hidden.includes(itemKey(item))
                && matches(item)
            );
            const links = (state.personal.links || []).filter((link) => canAccessPersonalLink(link) && !state.favoritesOnly && (!state.query || `${link.referenceNumber} ${link.title} ${link.url}`.toLocaleLowerCase().includes(state.query.trim().toLocaleLowerCase())));
            const contents = new Map(groups.map((group) => [group.key, []]));
            for (const item of items) {
                const key = itemKey(item);
                const target = state.platformLayout.assignments[key] || state.personal.assignments[key] || groupKey({ id: item.group });
                if (!contents.has(target)) continue;
                contents.get(target).push(tile(item));
            }
            for (const link of links) {
                const target = everyGroup.some((group) => group.key === link.groupId && group.kind !== 'gap') ? link.groupId : (everyGroup.find((group) => group.kind !== 'gap')?.key || '');
                if (!contents.has(target)) continue;
                contents.get(target).push(tile(link, true));
            }
            catalog.innerHTML = groups.map((group) => {
                const filteredView = state.favoritesOnly || Boolean(state.query.trim());
                if (group.kind === 'gap') return filteredView ? '' : `<div class="group-placeholder" data-drop-group="${escapeHtml(group.key)}" data-group-kind="gap" data-drop-family="${group.family}" data-slot-index="${group.slotIndex}" aria-label="Lege plek in de indeling"></div>`;
                const cards = contents.get(group.key) || [];
                if (cards.length === 0 && group.kind !== 'custom' && !canEditPlatformLayout()) return '';
                if (filteredView && cards.length === 0) return '';
                const kind = group.kind === 'custom' ? 'BEDRIJF' : 'GROMPIL';
                const hint = group.kind === 'custom' ? t('drop_company') : (canEditPlatformLayout() ? t('drop_platform') : '');
                const colorStyle = /^#[0-9a-f]{6}$/i.test(group.color || '') ? ` style="--group-background:${escapeHtml(group.color)}"` : '';
                const canManageGroup = (group.kind === 'custom' && canEditShared) || (['platform', 'catalog'].includes(group.kind) && canEditPlatformLayout());
                const groupControls = canManageGroup
                    ? `<div class="group-controls"><button class="icon-button group-drag-handle" type="button" draggable="true" data-drag-key="group:${escapeHtml(group.key)}" title="${escapeHtml(t('move_group'))}" aria-label="${escapeHtml(t('move_group'))}">⠿</button><button class="icon-button" type="button" data-group-actions="${escapeHtml(group.key)}" title="${escapeHtml(t('group_options'))}" aria-label="${escapeHtml(t('group_options'))}">⋯</button></div>`
                    : '';
                return `<section class="content-section group-section" data-drop-group="${escapeHtml(group.key)}" data-group-kind="${group.kind}"${colorStyle}><div class="section-heading"><div><div class="eyebrow">${kind}</div><h2>${escapeHtml(group.title)}</h2></div>${groupControls}</div><div class="tile-grid">${cards.length ? cards.join('') : (hint ? `<div class="drop-hint">${hint}</div>` : '')}</div></section>`;
            }).join('') || `<div class="content-section empty-note">${escapeHtml(t('empty_page'))}</div>`;
            const visibleCount = [...contents.values()].reduce((total, cards) => total + cards.length, 0);
            $('#catalog-status').textContent = t(visibleCount === 1 ? 'count_one' : 'count_many', { count: visibleCount });
        }
        const favorites = (state.catalog?.items || []).filter((item) => canAccessCatalogItem(item) && state.personal.favorites.includes(itemKey(item)) && !state.personal.hidden.includes(itemKey(item)) && matchesWithoutFavoriteFilter(item));
        $('#favorites-section').hidden = true;
        $('#favorites-grid').innerHTML = favorites.map((item) => tile(item)).join('');
        document.documentElement.lang = language();
        translateControls();
    }

    function matchesWithoutFavoriteFilter(item) {
        const was = state.favoritesOnly;
        state.favoritesOnly = false;
        const result = matches(item);
        state.favoritesOnly = was;
        return result;
    }

    function validCatalog(data) {
        return data && data.schemaVersion === 1 && typeof data.application === 'string' && Array.isArray(data.items) && Array.isArray(data.languages) && data.items.every((item) => Number.isSafeInteger(item.id) && typeof item.module === 'string' && typeof item.group === 'string');
    }

    function loadCatalog(data) {
        if (!validCatalog(data)) { toast('Dit JSON-bestand heeft niet de verwachte functielijststructuur.'); return false; }
        state.catalog = data;
        if (data.managerI18n && typeof data.managerI18n === 'object') managerLabels = data.managerI18n;
        const validKeys = new Set(data.items.map(itemKey));
        state.personal.favorites = state.personal.favorites.filter((key) => !key.startsWith(`${data.application}:`) || validKeys.has(key));
        saveState();
        render();
        toast(`${data.items.length} functies geladen.`);
        return true;
    }

    function parseJsonFile(file, callback) {
        const reader = new FileReader();
        reader.onload = () => { try { callback(JSON.parse(reader.result)); } catch { toast('Het gekozen bestand bevat geen geldige JSON.'); } };
        reader.onerror = () => toast('Het bestand kon niet worden gelezen.');
        reader.readAsText(file, 'utf-8');
    }

    function toast(message) {
        const box = $('#toast');
        box.textContent = message;
        box.classList.add('visible');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => box.classList.remove('visible'), 2600);
    }

    function downloadJson(filename, value) {
        const blob = new Blob([JSON.stringify(value, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
    }

    function wireEvents() {
        $('#page-navigation').addEventListener('click', async (event) => {
            const pageButton = event.target.closest('[data-page]');
            if (pageButton) { activePageId = pageButton.dataset.page; rememberActivePage(); render(); return; }
            if (remoteSync.enabled && !remoteSync.canEdit) return;
            const renameButton = event.target.closest('[data-rename-page]');
            if (renameButton) {
                const page = state.personal.pages.find((entry) => entry.id === renameButton.dataset.renamePage);
                if (!page || (!remoteSync.isPlatformOperator && page.id === state.personal.pages[0]?.id)) return;
                $('#page-id').value = page.id;
                $('#page-title').value = pageTitle(page);
                $('#page-dialog').showModal();
            } else if (event.target.closest('#add-page')) {
                const id = `page:${crypto.randomUUID()}`;
                let number = state.personal.pages.length + 1;
                while (state.personal.pages.some((page) => page.defaultNumber === number)) number++;
                state.personal.pages.push({ id, title: `blad${number}`, defaultNumber: number });
                activePageId = id;
                rememberActivePage();
                await saveState();
                render();
            }
        });
        $('#page-form').addEventListener('submit', async (event) => {
            if (event.submitter?.value === 'cancel') return;
            event.preventDefault();
            if (remoteSync.enabled && !remoteSync.canEdit) return;
            const page = state.personal.pages.find((entry) => entry.id === $('#page-id').value);
            const title = $('#page-title').value.trim();
            if (!page || !title) return;
            if (!remoteSync.isPlatformOperator && page.id === state.personal.pages[0]?.id) return;
            page.titles ||= {};
            page.titles[language()] = title;
            $('#page-dialog').close();
            await saveState();
            render();
        });
        document.addEventListener('click', (event) => {
            const selectedLink = event.target.closest('a.tile-link');
            if (!selectedLink || selectedLink.hasAttribute('data-needs-base') || window.parent === window) return;

            event.preventDefault();
            event.stopPropagation();
            window.top.location.assign(selectedLink.href);
        }, true);
        document.addEventListener('pointerover', (event) => {
            const target = event.target.closest('[data-bs-toggle="tooltip"]');
            if (target && !target.contains(event.relatedTarget)) showDescriptionTooltip(target);
        });
        document.addEventListener('pointerout', (event) => {
            const target = event.target.closest('[data-bs-toggle="tooltip"]');
            if (target && !target.contains(event.relatedTarget)) hideDescriptionTooltip(target);
        });
        document.addEventListener('focusin', (event) => {
            const target = event.target.closest('[data-bs-toggle="tooltip"]');
            if (target) showDescriptionTooltip(target);
        });
        document.addEventListener('focusout', (event) => {
            const target = event.target.closest('[data-bs-toggle="tooltip"]');
            if (target && !target.contains(event.relatedTarget)) hideDescriptionTooltip(target);
        });
        window.addEventListener('scroll', () => hideDescriptionTooltip(), true);
        $('#column-layout').addEventListener('change', async (event) => {
            const columns = Number(event.target.value);
            if (![1, 2, 3].includes(columns)) return;
            state.personal.settings.columns = columns;
            await saveState();
            render();
        });
        const changeTextSize = async (step) => {
            const current = Number(state.personal.settings.textSize ?? 1);
            state.personal.settings.textSize = Math.max(0, Math.min(2, current + step));
            await saveState();
            render();
        };
        $('#text-smaller').addEventListener('click', () => changeTextSize(-1));
        $('#text-larger').addEventListener('click', () => changeTextSize(1));
        $('#text-size-reset').addEventListener('click', async () => {
            state.personal.settings.textSize = 1;
            await saveState();
            render();
        });
        $('#search').addEventListener('input', (event) => { state.query = event.target.value; render(); });
        $('#language-select').addEventListener('change', async (event) => {
            const nextLanguage = event.target.value;
            if (!(state.catalog?.languages || []).includes(nextLanguage)) return;
            state.personal.settings.language = nextLanguage;
            await saveState();
            render();
        });
        $('#favorites-filter').addEventListener('click', () => { state.favoritesOnly = !state.favoritesOnly; $('#favorites-filter').textContent = state.favoritesOnly ? t('favorites_only') : t('favorites'); render(); });
        $('#show-all').addEventListener('click', () => { state.favoritesOnly = false; $('#favorites-filter').textContent = t('favorites'); $('#search').value = ''; state.query = ''; render(); });
        $('#settings-toggle').addEventListener('click', () => { $('#settings-panel').hidden = !$('#settings-panel').hidden; });
        $('#settings-close').addEventListener('click', () => { $('#settings-panel').hidden = true; });
        $('#save-settings').addEventListener('click', async () => {
            const value = $('#base-url').value.trim();
            if (value) { try { const parsed = new URL(value); if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error(); state.personal.settings.baseUrl = parsed.href.endsWith('/') ? parsed.href : `${parsed.href}/`; } catch { toast('Vul een geldig http- of https-adres in.'); return; } }
            else state.personal.settings.baseUrl = '';
            await saveState(); render(); toast('Basisadres bewaard.');
        });
        $('#load-catalog').addEventListener('click', () => $('#catalog-file').click());
        $('#catalog-file').addEventListener('change', (event) => { const file = event.target.files[0]; if (file) parseJsonFile(file, loadCatalog); event.target.value = ''; });
        $('#import-button').addEventListener('click', () => $('#import-file').click());
        $('#import-file').addEventListener('change', (event) => { const file = event.target.files[0]; if (file) parseJsonFile(file, importPersonal); event.target.value = ''; });
        $('#export-button').addEventListener('click', () => downloadJson('grompil-persoonlijke-navigatie.json', { schemaVersion: 1, application: state.catalog?.application || 'grompil', exportedAt: new Date().toISOString(), personal: state.personal }));
        $('#add-link').addEventListener('click', () => {
            if (!allGroups().length) { toast('Maak eerst een blok aan.'); return; }
            openLinkDialog();
        });
        $('#add-group').addEventListener('click', () => openGroupDialog(null, 'company'));
        $('#add-platform-group').addEventListener('click', () => openGroupDialog(null, 'platform'));
        $('#group-form').addEventListener('submit', async (event) => {
            if (event.submitter?.value === 'cancel') return;
            event.preventDefault();
            const title = $('#group-title').value.trim();
            if (!title) return;
            if (state.groupEditScope === 'catalog') {
                state.platformLayout.groupTitles[state.editingGroupId] ||= {};
                state.platformLayout.groupTitles[state.editingGroupId][language()] = title;
                $('#group-dialog').close();
                await savePlatformLayout();
                render();
                toast(`Bloktitel gewijzigd in “${title}”.`);
                state.editingGroupId = null;
                return;
            }
            const groups = state.groupEditScope === 'platform' ? state.platformLayout.groups : state.personal.groups;
            if (state.editingGroupId) {
                const group = groups.find((entry) => entry.id === state.editingGroupId);
                if (group && state.groupEditScope === 'platform') {
                    group.titles ||= {};
                    group.titles[language()] = title;
                } else if (group) {
                    group.titles ||= {};
                    group.titles[language()] = title;
                    group.title ||= title;
                    group.pageId = $('#group-page').value;
                }
            } else {
                if (state.groupEditScope === 'platform') state.platformLayout.groups.push({ id: `platform:${crypto.randomUUID()}`, title, titles: { [language()]: title } });
                else state.personal.groups.push({ id: `custom:${crypto.randomUUID()}`, title, titles: { [language()]: title }, pageId: $('#group-page').value });
            }
            $('#group-dialog').close();
            if (state.groupEditScope === 'platform') await savePlatformLayout(); else await saveState();
            render(); toast(state.editingGroupId ? `Bloknaam gewijzigd in “${title}”.` : `Blok “${title}” toegevoegd.`);
            state.editingGroupId = null;
        });
        $('#group-actions-close').addEventListener('click', () => $('#group-actions-dialog').close());
        $('#save-group-color').addEventListener('click', async () => {
            const group = findSelectedGroup();
            const color = $('#group-color').value;
            if (!group || !/^#[0-9a-f]{6}$/i.test(color)) return;
            if (state.groupEditScope === 'catalog') state.platformLayout.groupColors[group.id] = color;
            else {
                const groups = state.groupEditScope === 'platform' ? state.platformLayout.groups : state.personal.groups;
                const savedGroup = groups.find((entry) => entry.id === state.selectedGroupId);
                if (savedGroup) savedGroup.color = color;
            }
            const saved = state.groupEditScope === 'platform' || state.groupEditScope === 'catalog'
                ? await savePlatformLayout()
                : (await saveState(), true);
            render();
            if (saved) toast('Achtergrondkleur bewaard.');
        });
        $('#reset-group-color').addEventListener('click', async () => {
            const group = findSelectedGroup();
            if (!group) return;
            if (state.groupEditScope === 'catalog') delete state.platformLayout.groupColors[group.id];
            else {
                const groups = state.groupEditScope === 'platform' ? state.platformLayout.groups : state.personal.groups;
                const savedGroup = groups.find((entry) => entry.id === state.selectedGroupId);
                if (savedGroup) delete savedGroup.color;
            }
            $('#group-color').value = '#ffffff';
            const saved = state.groupEditScope === 'platform' || state.groupEditScope === 'catalog'
                ? await savePlatformLayout()
                : (await saveState(), true);
            render();
            if (saved) toast('Standaardkleur hersteld.');
        });
        $('#rename-group').addEventListener('click', () => {
            const group = findSelectedGroup();
            $('#group-actions-dialog').close();
            if (group) openGroupDialog(group, state.groupEditScope);
        });
        $('#delete-group').addEventListener('click', async () => {
            const group = findSelectedGroup();
            if (!group) return;
            if (window.confirm(`Blok “${group.title}” verwijderen? De links gaan naar het eerste standaardblok.`)) {
                const key = group.id;
                if (state.groupEditScope === 'platform') {
                    state.platformLayout.groups = state.platformLayout.groups.filter((entry) => entry.id !== key);
                    for (const [item, assigned] of Object.entries(state.platformLayout.assignments)) if (assigned === key) delete state.platformLayout.assignments[item];
                    await savePlatformLayout();
                } else {
                    state.personal.groups = state.personal.groups.filter((entry) => entry.id !== key);
                    for (const link of state.personal.links) if (link.groupId === key) link.groupId = allGroups()[0]?.key || '';
                    for (const [item, assigned] of Object.entries(state.personal.assignments)) if (assigned === key) delete state.personal.assignments[item];
                    await saveState();
                }
                $('#group-actions-dialog').close();
                render(); toast('Blok verwijderd.');
            }
        });
        $('#link-form').addEventListener('submit', (event) => {
            if (event.submitter?.value === 'cancel') return;
            event.preventDefault();
            if (remoteSync.enabled && !remoteSync.canEdit) return;
            saveLink();
        });
        $('#confirm-link-delete').addEventListener('change', (event) => {
            $('#link-delete').disabled = !event.target.checked;
        });
        $('#link-delete').addEventListener('click', async () => {
            if (remoteSync.enabled && !remoteSync.canEdit) return;
            const id = state.editingLinkId;
            if (!id || !$('#confirm-link-delete').checked) return;
            state.personal.links = state.personal.links.filter((link) => link.id !== id);
            state.editingLinkId = null;
            $('#link-dialog').close();
            await saveState();
            render();
            toast('Persoonlijke link verwijderd.');
        });
        document.addEventListener('click', async (event) => {
            const favoriteButton = event.target.closest('[data-favorite]');
            if (favoriteButton) { const key = favoriteButton.dataset.favorite; state.personal.favorites = state.personal.favorites.includes(key) ? state.personal.favorites.filter((value) => value !== key) : [...state.personal.favorites, key]; await saveState(); render(); }
            const editButton = event.target.closest('[data-edit-link]');
            if (editButton && (!remoteSync.enabled || remoteSync.canEdit)) openLinkDialog(editButton.dataset.editLink);
            const groupActionsButton = event.target.closest('[data-group-actions]');
            if (groupActionsButton) {
                state.selectedGroupId = groupActionsButton.dataset.groupActions;
                const kind = groupActionsButton.closest('[data-group-kind]')?.dataset.groupKind;
                state.groupEditScope = kind === 'catalog' ? 'catalog' : (kind === 'platform' ? 'platform' : 'company');
                const group = findSelectedGroup();
                if (group) {
                    $('#group-actions-name').textContent = group.title;
                    $('#group-color').value = /^#[0-9a-f]{6}$/i.test(group.color || '') ? group.color : '#ffffff';
                    $('#rename-group').hidden = false;
                    $('#rename-group').textContent = kind === 'custom' ? t('rename_group_page') : t('rename_group');
                    $('#delete-group').hidden = kind === 'catalog';
                    $('#group-actions-dialog').showModal();
                }
            }
            const needsBase = event.target.closest('[data-needs-base]');
            if (needsBase) { event.preventDefault(); $('#settings-panel').hidden = false; $('#base-url').focus(); toast('Stel eerst het basisadres in.'); }
        });
        document.addEventListener('dragstart', (event) => {
            if (event.target.closest('[data-fixed-link]')) { event.preventDefault(); return; }
            if (event.target.closest('[data-drag-key^="fixed:"]') && !canEditPlatformLayout()) { event.preventDefault(); return; }
            const card = event.target.closest('[data-drag-key]');
            if (!card) return;
            event.dataTransfer.setData('text/plain', card.dataset.dragKey);
            event.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => card.classList.add('is-dragging'));
        });
        document.addEventListener('dragend', (event) => event.target.closest('[data-drag-key]')?.classList.remove('is-dragging'));
        document.addEventListener('dragover', (event) => {
            const area = event.target.closest('[data-drop-group]');
            const grid = $('#catalog');
            const emptyGrid = !area && grid && !event.target.closest('.welcome, .settings-panel, dialog')
                && event.clientY >= grid.getBoundingClientRect().top
                && event.clientY <= grid.getBoundingClientRect().bottom;
            if (area) { event.preventDefault(); area.classList.add('is-drop-target'); }
            else if (emptyGrid) { event.preventDefault(); grid.classList.add('is-drop-target'); }
        });
        document.addEventListener('dragleave', (event) => {
            const area = event.target.closest('[data-drop-group]');
            if (area && !area.contains(event.relatedTarget)) area.classList.remove('is-drop-target');
            const grid = event.target.closest('#catalog');
            if (grid && !grid.contains(event.relatedTarget)) grid.classList.remove('is-drop-target');
        });
        document.addEventListener('drop', async (event) => {
            const canEditShared = !remoteSync.enabled || remoteSync.canEdit;
            const area = event.target.closest('[data-drop-group]');
            const grid = $('#catalog');
            const emptyGrid = !area && grid && !event.target.closest('.welcome, .settings-panel, dialog')
                && event.clientY >= grid.getBoundingClientRect().top
                && event.clientY <= grid.getBoundingClientRect().bottom;
            if (!area && !emptyGrid) return;
            event.preventDefault(); area?.classList.remove('is-drop-target'); if (emptyGrid) grid.classList.remove('is-drop-target');
            const dragged = event.dataTransfer.getData('text/plain');
            if (!dragged) return;
            if (dragged.startsWith('group:')) {
                const draggedKey = dragged.slice(6);
                const targetKey = area?.dataset.dropGroup || '';
                if (draggedKey === targetKey) return;
                const groups = allGroups();
                const source = groups.find((group) => group.key === draggedKey);
                const target = groups.find((group) => group.key === targetKey);
                if (!source) return;
                const family = source.kind === 'custom' ? 'company' : (['catalog', 'platform'].includes(source.kind) ? 'platform' : '');
                if (!family || (family === 'company' && !canEditShared) || (family === 'platform' && !canEditPlatformLayout())) return;
                const slots = family === 'company'
                    ? (state.personal.groupOrder.length ? [...state.personal.groupOrder] : groups.filter((group) => group.kind === 'custom').map((group) => group.key))
                    : (state.platformLayout.groupOrder.length ? [...state.platformLayout.groupOrder] : groups.filter((group) => ['catalog', 'platform'].includes(group.kind)).map((group) => group.key));
                const from = slots.indexOf(draggedKey);
                if (from < 0) return;
                const targetFamily = area?.dataset.dropFamily || (target?.kind === 'custom' ? 'company' : (target && ['catalog', 'platform'].includes(target.kind) ? 'platform' : ''));
                if (area && targetFamily !== family) return;
                if (area?.dataset.groupKind !== 'gap' && area && (!target || targetFamily !== family)) return;
                if (area?.dataset.groupKind === 'gap') {
                    const to = Number(area.dataset.slotIndex);
                    if (!Number.isInteger(to) || to < 0 || to >= slots.length || slots[to] !== null) return;
                    slots[from] = null;
                    slots[to] = draggedKey;
                } else if (emptyGrid) {
                    slots[from] = null;
                    let insertionIndex = slots.length;
                    let nearestDistance = Number.POSITIVE_INFINITY;
                    const dropElements = new Map([...document.querySelectorAll('#catalog [data-drop-group]')].map((entry) => [entry.dataset.dropGroup, entry]));
                    for (const candidate of groups) {
                        const sameFamily = family === 'company'
                            ? candidate.kind === 'custom'
                            : ['catalog', 'platform'].includes(candidate.kind);
                        if (!sameFamily) continue;
                        const element = dropElements.get(candidate.key);
                        if (!element) continue;
                        const rect = element.getBoundingClientRect();
                        const centerX = rect.left + rect.width / 2;
                        const centerY = rect.top + rect.height / 2;
                        const distance = Math.hypot(event.clientX - centerX, event.clientY - centerY);
                        if (distance < nearestDistance) {
                            nearestDistance = distance;
                            const candidateIndex = slots.indexOf(candidate.key);
                            if (candidateIndex >= 0) {
                                const after = event.clientY > centerY || (Math.abs(event.clientY - centerY) < rect.height * 0.2 && event.clientX > centerX);
                                insertionIndex = candidateIndex + (after ? 1 : 0);
                            }
                        }
                    }
                    slots.splice(insertionIndex, 0, draggedKey);
                } else {
                    const to = slots.indexOf(targetKey);
                    if (to < 0) return;
                    const [moved] = slots.splice(from, 1);
                    slots.splice(from < to ? to - 1 : to, 0, moved);
                }
                if (family === 'company') { state.personal.groupOrder = slots; await saveState(); }
                else { state.platformLayout.groupOrder = slots; await savePlatformLayout(); }
                render();
                toast('Blok verplaatst.');
            } else if (dragged.startsWith('fixed:')) {
                if (!area) return;
                if (!canEditPlatformLayout() || !['catalog', 'platform'].includes(area.dataset.groupKind)) return;
                state.platformLayout.assignments[dragged.slice(6)] = area.dataset.dropGroup;
                await savePlatformLayout();
            } else if (dragged.startsWith('link:')) {
                if (!area) return;
                if (!canEditShared) return;
                if (area.dataset.groupKind === 'catalog') return;
                const link = state.personal.links.find((entry) => entry.id === dragged.slice(5));
                if (link) link.groupId = area.dataset.dropGroup;
                await saveState();
            } else return;
            render(); toast('Tegel verplaatst.');
        });
        document.addEventListener('keydown', (event) => { if (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) { event.preventDefault(); $('#search').focus(); } if (event.key === 'Escape') $('#settings-panel').hidden = true; });
    }

    function openLinkDialog(id = null) {
        if (remoteSync.enabled && !remoteSync.canEdit) return;
        state.editingLinkId = id;
        const link = state.personal.links.find((entry) => entry.id === id);
        $('#dialog-title').textContent = link ? t('edit_link') : t('add_link_title');
        $('#link-title').value = link?.title || '';
        $('#link-url').value = link?.url || '';
        const groups = allGroups().filter((group) => group.kind !== 'gap');
        $('#link-group').innerHTML = groups.map((group) => {
            const page = state.personal.pages.find((entry) => entry.id === group.pageId) || state.personal.pages[0];
            return `<option value="${escapeHtml(group.key)}">${escapeHtml(pageTitle(page))} · ${escapeHtml(group.title)}</option>`;
        }).join('');
        const defaultGroup = groups.find((group) => group.pageId === activePageId || (!group.pageId && activePageId === state.personal.pages[0].id));
        if (!link && !defaultGroup) { toast(t('first_group')); return; }
        if (defaultGroup) $('#link-group').value = defaultGroup.key;
        if (link?.groupId) $('#link-group').value = link.groupId;
        $('#link-delete-confirmation').hidden = !link;
        $('#confirm-link-delete').checked = false;
        $('#link-delete').hidden = !link;
        $('#link-delete').disabled = true;
        $('#link-dialog').showModal();
    }

    function findSelectedGroup() {
        if (state.groupEditScope === 'catalog') {
            const group = (state.catalog?.groups || []).find((entry) => groupKey(entry) === state.selectedGroupId);
            return group ? { ...group, id: groupKey(group), title: localizedGroupTitle(state.platformLayout.groupTitles?.[groupKey(group)]) || textFor(group).title || group.id, color: state.platformLayout.groupColors?.[groupKey(group)] || '' } : null;
        }
        const groups = state.groupEditScope === 'platform' ? state.platformLayout.groups : state.personal.groups;
        const group = groups.find((entry) => entry.id === state.selectedGroupId) || null;
        if (!group) return null;
        return { ...group, title: state.groupEditScope === 'platform' ? platformGroupTitle(group) : (localizedGroupTitle(group.titles) || group.title) };
    }

    function openGroupDialog(group = null, scope = 'company') {
        state.groupEditScope = scope;
        state.editingGroupId = group?.id || null;
        $('#group-dialog-title').textContent = group ? (scope === 'company' ? t('edit_group_page_title') : t('edit_group_title')) : (scope === 'platform' || scope === 'catalog' ? t('platform_group_title') : t('company_group_title'));
        $('#group-title').value = group?.title || '';
        $('#group-page-field').hidden = scope !== 'company';
        $('#group-page').innerHTML = state.personal.pages.map((page) => `<option value="${escapeHtml(page.id)}">${escapeHtml(pageTitle(page))}</option>`).join('');
        $('#group-page').value = group?.pageId || activePageId;
        $('#group-save').textContent = group ? t('save_name') : t('make_group');
        $('#group-dialog').showModal();
    }

    async function saveLink() {
        if (remoteSync.enabled && !remoteSync.canEdit) return;
        const title = $('#link-title').value.trim();
        const url = $('#link-url').value.trim();
        try {
            if (!url || url.startsWith('//')) throw new Error();
            const base = state.personal.settings.baseUrl || guessBaseUrl();
            const parsed = new URL(url, base || location.href);
            if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error();
        }
        catch { toast('Vul een pad of een volledig http(s)-webadres in.'); return; }
        const id = state.editingLinkId || crypto.randomUUID();
        const existingLink = state.personal.links.find((link) => link.id === id);
        const nextReference = Math.max(99, ...state.personal.links.map((link) => Number(link.referenceNumber) || 0)) + 1;
        const entry = { id, title, url, groupId: $('#link-group').value, referenceNumber: existingLink?.referenceNumber || nextReference };
        const index = state.personal.links.findIndex((link) => link.id === id);
        if (index >= 0) state.personal.links[index] = entry; else state.personal.links.push(entry);
        $('#link-dialog').close();
        await saveState(); render(); toast('Persoonlijke link bewaard.');
    }

    async function importPersonal(data) {
        if (!data || data.schemaVersion !== 1 || !data.personal || typeof data.personal !== 'object') { toast('Dit is geen geldige navigatie-export.'); return; }
        const replace = window.confirm('OK = bestaande indeling vervangen. Annuleren = samenvoegen.');
        if (replace) state.personal = normalizePersonalState(data.personal);
        else {
            const incoming = normalizePersonalState(data.personal);
            const knownPages = new Set(state.personal.pages.map((page) => page.id));
            state.personal.pages.push(...incoming.pages.filter((page) => !knownPages.has(page.id)));
            state.personal.favorites = [...new Set([...state.personal.favorites, ...(incoming.favorites || [])])];
            state.personal.hidden = [...new Set([...state.personal.hidden, ...(incoming.hidden || [])])];
            state.personal.overrides = { ...state.personal.overrides, ...(incoming.overrides || {}) };
            const knownGroups = new Set(state.personal.groups.map((group) => group.id));
            state.personal.groups.push(...(incoming.groups || []).filter((group) => !knownGroups.has(group.id)));
            state.personal.assignments = { ...state.personal.assignments, ...(incoming.assignments || {}) };
            const known = new Set(state.personal.links.map((link) => link.id));
            state.personal.links.push(...(incoming.links || []).filter((link) => !known.has(link.id)));
            state.personal.settings = { ...state.personal.settings, ...(incoming.settings || {}) };
        }
        state.personal = normalizePersonalState(state.personal);
        await saveState();
        $('#base-url').value = state.personal.settings.baseUrl || '';
        render(); toast('Persoonlijke indeling geïmporteerd.');
    }

    async function init() {
        wireEvents();
        await readState();
        await loadManagerLabels();
        if (validCatalog(bundledCatalog)) state.catalog = bundledCatalog;
        remoteSync.application = state.catalog?.application || 'grompil';
        await syncModuleAccess();
        await syncPlatformLayout();
        await syncRemotePreferences();
        await saveState();
        render();
        if (location.protocol === 'http:' || location.protocol === 'https:') {
            fetch('../navigation.json', { cache: 'no-store' }).then((response) => { if (!response.ok) throw new Error(); return response.json(); }).then((data) => { if (!state.catalog) loadCatalog(data); }).catch(() => {});
        }
    }
    window.addEventListener('focus', () => { syncModuleAccess().then(syncPlatformLayout).then(syncRemotePreferences); });
    init();
})();

