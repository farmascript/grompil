'use strict';

const statusElement = document.querySelector('#status');
const syncButton = document.querySelector('#sync');
const storagePrefix = 'grompil-bookmarks:';

function browserApi(owner, method, ...args) {
    return new Promise((resolve, reject) => {
        try {
            method.apply(owner, [...args, (result) => {
                const error = chrome.runtime.lastError;
                if (error) reject(new Error(error.message));
                else resolve(result);
            }]);
        } catch (error) { reject(error); }
    });
}

function showStatus(message, error = false) {
    statusElement.textContent = message;
    statusElement.dataset.error = error ? 'true' : 'false';
}

function localized(record, language, fallback = '') {
    const translations = record?.i18n || {};
    const value = translations[language] || translations.en || translations.nl || Object.values(translations)[0] || {};
    return String(value.title || fallback || '');
}

function localizedTitle(titles, language, fallback = '') {
    if (!titles || typeof titles !== 'object') return fallback;
    const key = Object.keys(titles).find((entry) => entry.toLowerCase() === language.toLowerCase());
    return (key && titles[key]) || titles.nl || titles.en || fallback;
}

function moduleUrl(baseUrl, modulePath, application) {
    const url = new URL(String(modulePath).split('/').map(encodeURIComponent).join('/'), baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`);
    if (['navigation_preferences.php', 'platform_navigation_preferences.php'].includes(modulePath)) {
        url.searchParams.set('application', application || 'grompil');
    }
    if (modulePath === 'address_api.php') {
        url.searchParams.set('type', 'postal');
        url.searchParams.set('q', '1000');
    }
    return url.href;
}

function personalLinkUrl(link, baseUrl, knownModules, allowedModules) {
    const raw = String(link?.url || '').trim();
    if (!raw || raw.startsWith('//')) return null;
    let url;
    try { url = new URL(raw, baseUrl); } catch { return null; }
    if (!['http:', 'https:'].includes(url.protocol)) return null;
    const base = new URL(baseUrl);
    if (url.origin === base.origin && url.pathname.startsWith(base.pathname)) {
        const modulePath = decodeURIComponent(url.pathname.slice(base.pathname.length));
        if (knownModules.has(modulePath) && !allowedModules.has(modulePath)) return null;
    }
    return url.href;
}

function sortByNumber(items) {
    return [...items].sort((left, right) => (Number(left.sequence_number) || 999999) - (Number(right.sequence_number) || 999999));
}

function buildTrees(payload) {
    const { catalog, access, platformLayout, personal, baseUrl, language } = payload;
    const allowedModules = new Set(access.allowedModules);
    const knownModules = new Set(access.knownModules || []);
    const itemKey = (item) => `${catalog.application || 'grompil'}:${item.id}`;
    const catalogGroupKey = (id) => `catalog:${id}`;
    const platformGroups = Array.isArray(platformLayout.groups) ? platformLayout.groups : [];
    const platformAssignments = platformLayout.assignments || {};
    const personalAssignments = personal.assignments || {};
    const catalogGroups = Array.isArray(catalog.groups) ? catalog.groups : [];
    const catalogItems = sortByNumber((catalog.items || []).filter((item) => allowedModules.has(item.module)));
    const fixedGroupNodes = new Map();

    for (const group of catalogGroups) {
        const groupKey = catalogGroupKey(group.id);
        fixedGroupNodes.set(catalogGroupKey(group.id), {
            title: localizedTitle(platformLayout.groupTitles?.[groupKey], language, localized(group, language, group.id)),
            children: [],
            order: 1000 + catalogGroups.indexOf(group),
        });
    }
    platformGroups.forEach((group, index) => {
        fixedGroupNodes.set(group.id, {
            title: localizedTitle(group.titles, language, group.title || group.id),
            children: [],
            order: 100 + index,
        });
    });

    for (const item of catalogItems) {
        const key = itemKey(item);
        const target = platformAssignments[key] || personalAssignments[key] || catalogGroupKey(item.group);
        const destination = fixedGroupNodes.get(target) || fixedGroupNodes.get(catalogGroupKey(item.group));
        if (!destination) continue;
        destination.children.push({
            title: localized(item, language, item.module),
            url: moduleUrl(baseUrl, item.module, catalog.application),
            order: Number(item.sequence_number) || 999999,
        });
    }

    const fixedOrder = Array.isArray(platformLayout.groupOrder) ? platformLayout.groupOrder : [];
    const fixedNodes = [...fixedGroupNodes.entries()].sort((left, right) => {
        const li = fixedOrder.indexOf(left[0]);
        const ri = fixedOrder.indexOf(right[0]);
        return (li < 0 ? fixedOrder.length + left[1].order : li) - (ri < 0 ? fixedOrder.length + right[1].order : ri);
    }).map(([, node]) => ({ ...node, children: sortByNumber(node.children) }));

    const pages = Array.isArray(personal.pages) && personal.pages.length
        ? personal.pages
        : [{ id: 'page:1', title: 'blad1', defaultNumber: 1 }, { id: 'page:2', title: 'blad2', defaultNumber: 2 }];
    const groups = Array.isArray(personal.groups) ? personal.groups : [];
    const groupById = new Map(groups.map((group) => [group.id, group]));
    const pageTrees = pages.map((page) => ({
        title: localizedTitle(page.titles, language, page.title || (page.defaultNumber ? `blad${page.defaultNumber}` : 'Blad')),
        children: [],
        groupNodes: new Map(),
    }));
    const pageById = new Map(pages.map((page, index) => [page.id, pageTrees[index]]));
    const fixedGroupKeys = new Set();
    for (const [key, group] of fixedGroupNodes.entries()) {
        if (!pageTrees[0]) break;
        pageTrees[0].groupNodes.set(key, { title: group.title, children: [], order: group.order });
        fixedGroupKeys.add(key);
    }
    for (const group of groups) {
        const pageTree = pageById.get(group.pageId) || pageTrees[0];
        pageTree.groupNodes.set(group.id, {
            title: localizedTitle(group.titles, language, group.title || group.id),
            children: [],
            order: group.order ?? groups.indexOf(group),
        });
    }

    const personalItems = [];
    for (const item of catalogItems) {
        const key = itemKey(item);
        const destination = platformAssignments[key] || personalAssignments[key] || catalogGroupKey(item.group);
        if (!groupById.has(destination) || (personal.hidden || []).includes(key)) continue;
        const override = personal.overrides?.[key] || {};
        personalItems.push({
            groupId: destination,
            title: String(override.title || localized(item, language, item.module)),
            url: moduleUrl(baseUrl, item.module, catalog.application),
            order: Number(item.sequence_number) || 999999,
        });
    }
    for (const link of personal.links || []) {
        const url = personalLinkUrl(link, baseUrl, knownModules, allowedModules);
        if (!url) continue;
        personalItems.push({
            groupId: link.groupId,
            title: String(link.title || url),
            url,
            order: Number(link.referenceNumber) || 999999,
        });
    }
    for (const item of personalItems) {
        const group = groupById.get(item.groupId);
        const pageTree = group ? (pageById.get(group.pageId) || pageTrees[0]) : pageTrees[0];
        let node = group ? pageTree.groupNodes.get(group.id) : null;
        if (!node) {
            const key = 'grompil-bookmarks:other-links';
            if (!pageTree.groupNodes.has(key)) pageTree.groupNodes.set(key, { title: localizedTitle({ nl: 'Overige links', fr: 'Autres liens', en: 'Other links', de: 'Weitere Links' }, language), children: [], order: 999999 });
            node = pageTree.groupNodes.get(key);
        }
        node.children.push({ title: item.title, url: item.url, order: item.order });
    }

    const companyGroupOrder = Array.isArray(personal.groupOrder) ? personal.groupOrder : [];
    const fixedGroupOrder = Array.isArray(platformLayout.groupOrder) ? platformLayout.groupOrder : [];
    for (const page of pageTrees) {
        page.children = [...page.groupNodes.entries()].sort((left, right) => {
            const leftFixed = fixedGroupKeys.has(left[0]);
            const rightFixed = fixedGroupKeys.has(right[0]);
            if (leftFixed !== rightFixed) return leftFixed ? -1 : 1;
            if (leftFixed) {
                const li = fixedGroupOrder.indexOf(left[0]);
                const ri = fixedGroupOrder.indexOf(right[0]);
                return (li < 0 ? fixedGroupOrder.length + left[1].order : li) - (ri < 0 ? fixedGroupOrder.length + right[1].order : ri);
            }
            const li = companyGroupOrder.indexOf(left[0]);
            const ri = companyGroupOrder.indexOf(right[0]);
            return (li < 0 ? companyGroupOrder.length + left[1].order : li) - (ri < 0 ? companyGroupOrder.length + right[1].order : ri);
        }).filter(([, group]) => group.children.length > 0)
            .map(([, group]) => ({ title: group.title, children: sortByNumber(group.children) }));
    }

    const labels = {
        nl: ['Grompil-functies', 'Mijn navigator'],
        fr: ['Fonctions Grompil', 'Mon navigateur'],
        en: ['Grompil functions', 'My navigator'],
        de: ['Grompil-Funktionen', 'Mein Navigator'],
    }[language] || ['Grompil-functies', 'Mijn navigator'];
    return [
        { title: labels[0], children: fixedNodes.filter((node) => node.children.length > 0) },
        { title: labels[1], children: pageTrees.filter((page) => page.children.length > 0).map((page) => ({ title: page.title, children: page.children })) },
    ];
}

async function createBookmarkTree(parentId, nodes) {
    for (const node of nodes) {
        if (node.url) {
            await browserApi(chrome.bookmarks, chrome.bookmarks.create, { parentId, title: node.title.slice(0, 240), url: node.url });
            continue;
        }
        const folder = await browserApi(chrome.bookmarks, chrome.bookmarks.create, { parentId, title: String(node.title || 'Links').slice(0, 240) });
        await createBookmarkTree(folder.id, node.children || []);
    }
}

async function getBookmarksBarId() {
    const tree = await browserApi(chrome.bookmarks, chrome.bookmarks.getTree);
    const roots = tree[0]?.children || [];
    const bar = roots.find((node) => node.id === '1' || node.folderType === 'bookmarks-bar') || roots[0];
    if (!bar) throw new Error('De bookmarkbalk kon niet worden gevonden.');
    return bar.id;
}

async function removeOldFolders(key) {
    const saved = await browserApi(chrome.storage.local, chrome.storage.local.get, key);
    for (const id of saved[key] || []) {
        try { await browserApi(chrome.bookmarks, chrome.bookmarks.removeTree, id); } catch { /* A user may have removed a generated menu manually. */ }
    }
}

async function syncBookmarks(payload) {
    const trees = buildTrees(payload);
    const originKey = `${storagePrefix}${new URL(payload.baseUrl).origin}`;
    const generatedKey = `${originKey}:folders`;
    await removeOldFolders(generatedKey);
    const barId = await getBookmarksBarId();
    const created = [];
    try {
        for (let index = 0; index < trees.length; index++) {
            const folder = await browserApi(chrome.bookmarks, chrome.bookmarks.create, { parentId: barId, title: trees[index].title, index });
            created.push(folder.id);
            await createBookmarkTree(folder.id, trees[index].children);
        }
    } catch (error) {
        for (const id of created) {
            try { await browserApi(chrome.bookmarks, chrome.bookmarks.removeTree, id); } catch { /* Clean up any partial menu. */ }
        }
        throw error;
    }
    await browserApi(chrome.storage.local, chrome.storage.local.set, { [generatedKey]: created });
    return trees.reduce((sum, tree) => sum + countBookmarks(tree), 0);
}

function countBookmarks(node) {
    if (node.url) return 1;
    return (node.children || []).reduce((sum, child) => sum + countBookmarks(child), 0);
}

syncButton.addEventListener('click', async () => {
    syncButton.disabled = true;
    showStatus('De URL-manager en toegangsrechten ophalen…');
    try {
        const [tab] = await browserApi(chrome.tabs, chrome.tabs.query, { active: true, currentWindow: true });
        if (!tab?.id || !tab.url) throw new Error('Open eerst de Grompil-URL-manager in de actieve tab.');
        const current = new URL(tab.url);
        if (!['http:', 'https:'].includes(current.protocol) || !/\/url-manager\/(?:index\.html)?$/.test(current.pathname)) {
            throw new Error('Open de online URL-manager, kies eventueel het juiste bedrijf en start de synchronisatie opnieuw.');
        }
        const execution = await browserApi(chrome.scripting, chrome.scripting.executeScript, {
            target: { tabId: tab.id },
            world: 'MAIN',
            func: async () => {
                const baseUrl = new URL('../', location.href).href;
                const fetchJson = async (url, allowStatus = []) => {
                    const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    if (!response.ok && !allowStatus.includes(response.status)) {
                        throw new Error(response.status === 401
                            ? 'Je Grompil-sessie is verlopen. Meld je opnieuw aan en synchroniseer opnieuw.'
                            : `Grompil gaf een fout bij het ophalen van de bookmarkgegevens (HTTP ${response.status}).`);
                    }
                    if (allowStatus.includes(response.status)) return { httpStatus: response.status };
                    try { return await response.json(); }
                    catch { throw new Error('Grompil gaf geen geldige JSON-gegevens terug.'); }
                };
                const access = await fetchJson(new URL('navigation_access.php', baseUrl));
                if (!Array.isArray(access.allowedModules) || !Array.isArray(access.knownModules)) {
                    throw new Error('De Grompil-toegangscontrole gaf geen geldige lijst terug.');
                }
                if (!access.logout || typeof access.logout.csrfToken !== 'string') {
                    throw new Error('Meld je aan bij Grompil voordat je de bookmarkmappen synchroniseert.');
                }
                let catalog = window.GROMPIL_NAVIGATION;
                if (!catalog || !Array.isArray(catalog.items)) catalog = await fetchJson(new URL('navigation.json', baseUrl));
                if (!catalog || !Array.isArray(catalog.items) || !Array.isArray(catalog.groups)) {
                    throw new Error('navigation.json kon niet worden gelezen.');
                }
                const application = catalog.application || 'grompil';
                const platform = await fetchJson(new URL(`platform_navigation_preferences.php?application=${encodeURIComponent(application)}`, baseUrl));
                const company = await fetchJson(new URL(`navigation_preferences.php?application=${encodeURIComponent(application)}`, baseUrl), [409]);
                const readLocalState = async (key) => new Promise((resolve) => {
                    let request;
                    try { request = indexedDB.open('grompil-url-manager', 1); } catch { resolve(null); return; }
                    request.onerror = () => resolve(null);
                    request.onsuccess = () => {
                        const db = request.result;
                        if (!db.objectStoreNames.contains('state')) { db.close(); resolve(null); return; }
                        const get = db.transaction('state').objectStore('state').get(key);
                        get.onsuccess = () => { const result = get.result || null; db.close(); resolve(result); };
                        get.onerror = () => { db.close(); resolve(null); };
                    };
                });
                let personal;
                let companyId = Number(company.companyId) || 0;
                if (companyId) {
                    const localCompany = await readLocalState(`company:${companyId}:personal`);
                    personal = company.configuration && typeof company.configuration === 'object' ? company.configuration : localCompany;
                } else {
                    personal = await readLocalState('personal');
                }
                personal = personal && typeof personal === 'object' ? personal : { pages: [], groups: [], groupOrder: [], links: [], assignments: {}, overrides: {}, hidden: [], settings: {} };
                const layout = platform.configuration && typeof platform.configuration === 'object'
                    ? platform.configuration
                    : { groups: [], assignments: {}, groupColors: {}, groupTitles: {}, groupOrder: [] };
                const language = document.querySelector('#language-select')?.value
                    || personal.settings?.language
                    || catalog.defaultLanguage
                    || 'nl';
                return { baseUrl: personal.settings?.baseUrl || baseUrl, language, catalog, access, platformLayout: layout, personal };
            },
        });
        const payload = execution?.[0]?.result;
        if (!payload || typeof payload !== 'object') throw new Error('De manager gaf geen navigatiegegevens terug.');
        const count = await syncBookmarks(payload);
        showStatus(`${count} bookmarks bijgewerkt in twee mappen. Andere bookmarks bleven onaangeraakt.`);
    } catch (error) {
        showStatus(error?.message || 'Synchronisatie is mislukt.', true);
    } finally {
        syncButton.disabled = false;
    }
});
