'use strict';

const statusElement = document.querySelector('#status');
const syncButton = document.querySelector('#sync');
const versionElement = document.querySelector('#version');
versionElement.textContent = 'Version ' + chrome.runtime.getManifest().version;

const bookmarkFolderSelect = document.querySelector('#bookmark-folder');
const bookmarkFolderSearch = document.querySelector('#bookmark-folder-search');
let bookmarkFolders = [];
let selectedBookmarkFolderId = '';

function renderBookmarkFolders() {
    const filter = bookmarkFolderSearch.value.trim().toLocaleLowerCase();
    const visibleFolders = bookmarkFolders.filter((folder) => {
        const searchableText = `${folder.path} ${folder.title} ${folder.isBookmarksBar ? 'Bookmark Bar' : ''}`.toLocaleLowerCase();
        return !filter || searchableText.includes(filter);
    });
    bookmarkFolderSelect.replaceChildren(new Option('Choose a bookmark folder…', ''));
    for (const folder of visibleFolders) {
        const label = folder.isBookmarksBar ? `Bookmark Bar — ${folder.path}` : folder.path;
        bookmarkFolderSelect.add(new Option(label, folder.id));
    }
    if (visibleFolders.some((folder) => folder.id === selectedBookmarkFolderId)) {
        bookmarkFolderSelect.value = selectedBookmarkFolderId;
    }
}

async function loadBookmarkFolders() {
    bookmarkFolders = await GrompilBookmarks.getBookmarkFolders();
    selectedBookmarkFolderId = await GrompilBookmarks.getBookmarksBarId().catch(() => '');
    renderBookmarkFolders();
}

bookmarkFolderSearch.addEventListener('input', renderBookmarkFolders);
bookmarkFolderSelect.addEventListener('change', async () => {
    if (!bookmarkFolderSelect.value) return;
    try {
        await GrompilBookmarks.setBookmarkBarFolder(bookmarkFolderSelect.value);
        selectedBookmarkFolderId = bookmarkFolderSelect.value;
        showStatus('Bookmark Bar folder saved. You can sync now.');
    } catch (error) {
        showStatus(error?.message || 'Could not save the Bookmark Bar folder.', true);
    }
});

loadBookmarkFolders().catch((error) => showStatus(error?.message || 'Could not load bookmark folders.', true));

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
