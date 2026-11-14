# Grompil Bookmarks

Grompil Bookmarks adds two folders to the browser bookmarks bar:

- **gromPil-functions** contains modules the signed-in user is allowed to open.
- **gromPil-navigator** contains the user's URL Manager layout and links.

The extension only replaces the contents of the two folders it manages. Other
browser bookmarks are left untouched. After sign-in, or after changing company
or language, it refreshes those folders automatically. A manual sync is also
available from the extension popup.

## Supported browsers

This package targets Chromium browsers that can install extensions from the
Chrome Web Store. Chrome is the primary target. Vivaldi supports installing
Chrome Web Store extensions. Compatibility with other Chromium browsers must
be confirmed separately.

## Load the development build

1. Open the browser's extension page:
   - Chrome: `chrome://extensions`
   - Vivaldi: `vivaldi://extensions`
2. Enable **Developer mode**.
3. Select **Load unpacked**.
4. Choose this directory, the one containing `manifest.json`.
5. Pin **Grompil Bookmarks** to the browser toolbar if desired.

## Automatic and manual synchronization

Sign in to Grompil in a supported browser. The extension refreshes the folders
when an authenticated page opens after sign-in, or when the active company or
language changes. It uses the access permissions and URL Manager layout for
that signed-in session.

To refresh manually, open Navigation or the URL Manager and click
**Sync bookmarks** in the extension popup. Show the bookmarks bar if needed
(usually `Ctrl+Shift+B`).

The extension stores the IDs of its generated folders locally so it can replace
those folders during the next sync. Do not put personal bookmarks inside the
two folders managed by the extension.

## Store publication files

See `STORE_LISTING.md`, `privacy-policy.html`, and `SUBMISSION_CHECKLIST.md` for
the English Chrome Web Store submission materials and remaining publication
steps. The privacy policy must be published at a public HTTPS URL before the
listing is submitted.
