'use strict';

importScripts('bookmark-sync.js');

const inFlightSyncs = new Map();

function extensionApi(owner, method, ...args) {
    return new Promise((resolve, reject) => {
        method.apply(owner, [...args, (result) => {
            const error = chrome.runtime.lastError;
            if (error) reject(new Error(error.message));
            else resolve(result);
        }]);
    });
}

function isSupportedGrompilUrl(value) {
    try {
        const url = new URL(value);
        const host = url.hostname.toLowerCase();
        const grompilHost = host === 'grompil.com' || host.endsWith('.grompil.com');
        const localHost = ['localhost', '127.0.0.1'].includes(host);
        return (grompilHost && url.protocol === 'https:') || (localHost && ['http:', 'https:'].includes(url.protocol));
    } catch {
        return false;
    }
}

async function autoSyncFromPage(message, sender) {
    const tabId = sender.tab?.id;
    const pageUrl = sender.url || sender.tab?.url || '';
    if (!Number.isInteger(tabId) || !isSupportedGrompilUrl(pageUrl)) return;
    if (typeof message.nonce !== 'string' || !/^[a-f0-9]{32}$/.test(message.nonce)) return;
    if (!Number.isInteger(message.companyId) || message.companyId < 0) return;
    if (typeof message.language !== 'string' || !/^(nl|fr|en|de)$/.test(message.language)) return;

    let baseUrl;
    try { baseUrl = new URL(String(message.baseHref || ''), pageUrl); } catch { return; }
    if (baseUrl.origin !== new URL(pageUrl).origin || !baseUrl.pathname.endsWith('/') || baseUrl.search || baseUrl.hash) return;

    const origin = baseUrl.origin;
    const contextKey = `${origin}:${message.nonce}:${message.companyId}:${message.language}`;
    const stateKey = `grompil-bookmarks:auto-context:${origin}`;
    const running = inFlightSyncs.get(contextKey);
    if (running) return running;

    const sync = (async () => {
        const stored = await extensionApi(chrome.storage.session, chrome.storage.session.get, stateKey);
        const eventKey = `${message.nonce}:${message.companyId}:${message.language}`;
        if (stored[stateKey] === eventKey) return;
        const payload = await GrompilBookmarks.readPayloadFromTab(tabId, {
            baseHref: baseUrl.href,
            companyId: message.companyId,
            language: message.language,
        });
        const count = await GrompilBookmarks.syncBookmarks(payload);
        await extensionApi(chrome.storage.session, chrome.storage.session.set, {
            [stateKey]: eventKey,
        });
        await extensionApi(chrome.action, chrome.action.setBadgeText, { text: '✓', tabId });
        await extensionApi(chrome.action, chrome.action.setBadgeBackgroundColor, { color: '#176b53', tabId });
        console.info(`Grompil bookmarks synchronized automatically (${count} links).`);
    })();

    inFlightSyncs.set(contextKey, sync);
    try {
        await sync;
    } catch (error) {
        console.warn('Automatic Grompil bookmark sync failed.', error);
        await extensionApi(chrome.action, chrome.action.setBadgeText, { text: '!', tabId }).catch(() => {});
        await extensionApi(chrome.action, chrome.action.setBadgeBackgroundColor, { color: '#b02a37', tabId }).catch(() => {});
    } finally {
        inFlightSyncs.delete(contextKey);
    }
}

chrome.runtime.onMessage.addListener((message, sender) => {
    if (message?.type === 'GROMPIL_BOOKMARK_AUTO_SYNC') {
        void autoSyncFromPage(message, sender);
    }
});
