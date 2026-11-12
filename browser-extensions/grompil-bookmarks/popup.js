'use strict';

const statusElement = document.querySelector('#status');
const syncButton = document.querySelector('#sync');

function showStatus(message, error = false) {
    statusElement.textContent = message;
    statusElement.dataset.error = error ? 'true' : 'false';
}

syncButton.addEventListener('click', async () => {
    syncButton.disabled = true;
    showStatus('Reading the URL Manager and your access permissions…');
    try {
        const [tab] = await new Promise((resolve, reject) => chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
            const error = chrome.runtime.lastError;
            if (error) reject(new Error(error.message));
            else resolve(tabs);
        }));
        if (!tab?.id || !tab.url) throw new Error('Open the Grompil URL Manager in the active tab first.');
        const payload = await GrompilBookmarks.readPayloadFromTab(tab.id, { managerOnly: true });
        const count = await GrompilBookmarks.syncBookmarks(payload);
        showStatus(`${count} bookmarks updated in two folders. Your other bookmarks were left untouched.`);
    } catch (error) {
        showStatus(error?.message || 'Bookmark sync failed.', true);
    } finally {
        syncButton.disabled = false;
    }
});
