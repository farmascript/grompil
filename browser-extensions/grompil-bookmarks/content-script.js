'use strict';

(() => {
    const marker = document.querySelector('meta[name="grompil-bookmark-sync"]');
    if (!marker) return;

    const nonce = String(marker.content || '');
    const baseHref = String(marker.dataset.baseHref || '');
    const companyId = Number(marker.dataset.companyId);
    const language = String(marker.dataset.language || '').toLowerCase();
    if (!/^[a-f0-9]{32}$/.test(nonce) || !baseHref || !Number.isInteger(companyId) || companyId < 0) return;

    chrome.runtime.sendMessage({
        type: 'GROMPIL_BOOKMARK_AUTO_SYNC',
        nonce,
        baseHref,
        companyId,
        language,
    }, () => { void chrome.runtime.lastError; });
})();
