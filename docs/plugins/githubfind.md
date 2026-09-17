# GitHub Find

Two browsers — Find Plugins and Find Themes — fed by catalog files, with download and install in one click.

| | |
| --- | --- |
| Folder | `githubFind` |
| Needs installing | Yes |
| Permission | Plugin-manager permission |
| Stores | Plugin preferences, no database table |

## Screens

**Find Plugins** and **Find Themes** are the browsers. Each entry shows its name, icon, description and the version published in its repository, and says whether you already have it.

**Find Plugins Sources** and **Find Theme Sources** are where the catalogs are managed. Plugins and themes have separate lists.

## Catalogs

A catalog is an XML file listing plugins or themes, each with its repository and folder. Two kinds:

**Bundled** — XML files shipped in the plugin's own `sources/plugins/` and `sources/themes/` folders. They are never loaded automatically. Press **Refresh folder catalogs** to import them; they appear as disabled rows. Tick **Enabled** on the ones you want and save. Run the refresh again after adding a file.

**Remote** — paste an `https` URL into the empty row. A catalog on GitHub works from its ordinary page URL (`github.com/…/blob/…`); it is fetched as raw content.

The list lives in plugin preferences, so a sync can never overwrite it.

## Choosing what a catalog shows

Every source row is followed by the list of plugins or themes that catalog offers, as checkboxes.

Ticked means shown in the Find browser. Unticked means hidden — for that source only, so something hidden in one catalog still appears from another that offers it. **Select all** and **Clear all** above a list change that one catalog; press Save to apply.

Anything newly added to a catalog shows up on its own. You only ever have to untick.

## Installing

Pressing download fetches the repository archive, extracts the single plugin or theme folder from it, and hands it to e107 to install. Nothing outside that one folder is written.

When something you already have has a newer version in its repository, the screen says so.
