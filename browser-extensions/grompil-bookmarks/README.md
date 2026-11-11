# Grompil Bookmarks

Grompil Bookmarks adds two folders to the browser bookmarks bar:

- **Grompil functions** contains modules the signed-in user is allowed to open.
- **My navigator** contains the user's URL Manager layout and links.

The extension only replaces the contents of the two folders it manages. Other
browser bookmarks are left untouched. Synchronization starts only when the user
clicks **Sync bookmarks**.

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

## Sync the bookmark folders

1. Sign in to the desired Grompil installation.
2. Open **Navigation** in Grompil, or open the URL Manager directly, for
   example `https://test.grompil.com/url-manager/index.html`.
3. Select the company and language you want to use in the URL Manager.
4. Open the **Grompil Bookmarks** extension and click **Sync bookmarks**.
5. Show the bookmarks bar if needed (usually `Ctrl+Shift+B`).

The extension stores the IDs of its generated folders locally so it can replace
those folders during the next sync. Do not put personal bookmarks inside the
two folders managed by the extension.

## Store publication files

See `STORE_LISTING.md`, `PRIVACY_POLICY.html`, and `SUBMISSION_CHECKLIST.md` for
the English Chrome Web Store submission materials and remaining publication
steps. The privacy policy must be published at a public HTTPS URL before the
listing is submitted.
