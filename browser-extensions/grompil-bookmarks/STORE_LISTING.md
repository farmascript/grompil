# Chrome Web Store listing

## Product name

Grompil Bookmarks

## Short description

Add permitted Grompil modules and your URL Manager links to the browser bookmarks bar.

## Detailed description

Grompil Bookmarks puts your Grompil workspace within easy reach. After you sign
in to Grompil, the extension automatically creates or refreshes two folders on
your bookmarks bar. It refreshes them again when you switch company or language.
You can also choose **Sync bookmarks** manually in the extension popup:

- **gromPil-functions** lists the modules available to your signed-in account.
- **gromPil-navigator** mirrors your URL Manager pages, groups, and links.

The extension uses your current Grompil session to retrieve navigation and
access information from that same Grompil website. It creates bookmarks on your
device and remembers only the IDs of the folders it created, so it can update
those folders next time. It does not send bookmark data to third-party services.

Only the two Grompil folders are changed. Existing bookmarks outside those
folders are not modified.

## Category

Productivity

## Single purpose

Create and refresh browser bookmark folders from the signed-in user's permitted
Grompil modules and URL Manager navigation.

## Support contact

Lieven Maus  
[saas@grompil.com](mailto:saas@grompil.com)

## Permission justifications

| Permission | Justification |
| --- | --- |
| Host access to Grompil | Detect authenticated Grompil page context changes and request navigation/access data from the same Grompil origin to refresh bookmarks automatically. |
| `activeTab` | Let the user start a manual sync from the extension popup in the active Grompil URL Manager tab. |
| `scripting` | Run the packaged reader in the active URL Manager page for manual sync and in the authenticated Grompil page for automatic sync. |
| `bookmarks` | Create and refresh the two bookmark folders requested by the user. The extension removes only folder IDs it previously created and stored. |
| `storage` | Store the IDs of generated folders by site origin and a temporary session marker to avoid repeating the same automatic sync on every page load. |

## Data use declaration draft

The extension handles user-specific navigation and access data from the Grompil
URL Manager. It uses that data only to build the requested browser bookmarks.
Navigation data is read from the signed-in Grompil website over the user's
existing HTTPS session. Generated bookmarks are stored by the browser. The
extension does not use analytics, advertising, or third-party data services.

Review this declaration against the final package and the Chrome Web Store
privacy questionnaire before submitting.
