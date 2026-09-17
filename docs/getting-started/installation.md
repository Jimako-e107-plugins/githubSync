# Installation

All three plugins install the normal e107 way.

1. Copy the plugin folder — `githubSync`, `githubSyncLite` or `githubFind` — from the repository's `e107_plugins/` directory into your site's plugins directory. On a standard e107 that is `e107_plugins/`; on a Lite fork it may be named differently.
2. Open **Admin → Plugin Manager** and press **Refresh plugin list** so e107 notices the new folder.
3. Install the plugin.

## Which ones need installing

**GitHub Sync** creates a database table, so it must be installed before it will run.

**GitHub Find** stores its catalog list in plugin preferences and must be installed as well — the cross-link in the other plugins' admin menus only appears for an installed plugin.

**GitHub Sync Lite** needs no table and no installation. Copying the folder in is enough; it appears in the admin area on its own. Installing it does no harm and makes its cross-link visible to the others.

## First steps after installing

| Plugin | Do this first |
| --- | --- |
| GitHub Sync | Open **Manual Sync** and add your first source row |
| GitHub Sync Lite | Open **Source**, set organization, repository, branch and layout, then go to **Core Sync** |
| GitHub Find | Open **Find Plugins Sources**, press **Refresh folder catalogs**, then tick the catalogs you want and save |

## Upgrading

Copy the new folder over the old one and run the upgrade the plugin manager offers.

For GitHub Sync the upgrade adds any new columns to its table through e107's own schema check and keeps every existing row. Rows that predate the layout columns are filled with `e` and `e107_plugins`, which reproduces the behaviour those rows had before — but check them, because a row pointing at a Lite repository needs `eplugins`. See [Repository layout](../reference/repo-layout.md).
