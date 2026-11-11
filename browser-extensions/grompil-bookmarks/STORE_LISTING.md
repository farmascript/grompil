# Chrome Web Store listing

## Product name

Grompil Bookmarks

## Short description

Add permitted Grompil modules and your URL Manager links to the browser bookmarks bar.

## Detailed description

Grompil Bookmarks puts your Grompil workspace within easy reach. After you sign
in and open Grompil Navigation or the URL Manager, choose **Sync bookmarks** to
create or refresh two folders on your bookmarks bar:

- **Grompil functions** lists the modules available to your signed-in account.
- **My navigator** mirrors your URL Manager pages, groups, and links.

The extension uses your current Grompil session to retrieve navigation and
access information from that same Grompil website. It creates bookmarks on your
device and remembers only the IDs of the folders it created, so it can update
those folders next time. It does not send bookmark data to third-party services.

Sync is started manually. Existing bookmarks outside the two Grompil folders
are not modified.

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
| `activeTab` | After the user clicks the extension, inspect the active tab to locate the Grompil URL Manager and its current language and company context. |
| `scripting` | Run the extension's packaged code in the active URL Manager page to retrieve its authenticated navigation data and access list from the same Grompil origin. |
| `bookmarks` | Create and refresh the two bookmark folders requested by the user. The extension removes only folder IDs it previously created and stored. |
| `storage` | Store the IDs of those generated folders locally, separated by Grompil site origin, so the extension can refresh them on a later manual sync. |

## Data use declaration draft

The extension handles user-specific navigation and access data from the Grompil
URL Manager. It uses that data only to build the requested browser bookmarks.
Navigation data is read from the signed-in Grompil website over the user's
existing HTTPS session. Generated bookmarks are stored by the browser. The
extension does not use analytics, advertising, or third-party data services.

Review this declaration against the final package and the Chrome Web Store
privacy questionnaire before submitting.
