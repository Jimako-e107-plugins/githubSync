# Finding plugins and themes

GitHub Find browses catalogs rather than repositories. You point it at a list of things, not at one thing.

## Setting up catalogs

1. Open **Find Plugins Sources**.
2. Press **Refresh folder catalogs**. The XML files bundled with the plugin are imported and appear as disabled rows.
3. Tick **Enabled** on the catalogs you want and press **Save**.
4. Do the same on **Find Theme Sources** for themes — the two lists are separate.

To add a catalog of your own, paste its `https` URL into the empty row and save. A catalog file on GitHub works from its ordinary page URL.

## Hiding entries you do not want

Under each catalog row is the list of plugins or themes it offers. Ticked means shown in the browser, unticked means hidden for that source only.

This is worth doing when two catalogs overlap, or when a catalog carries things irrelevant to your site. It changes nothing about the catalog itself — only what you see.

New entries added to a catalog appear on their own; you only ever untick.

## Installing

Open **Find Plugins** or **Find Themes**. Each entry shows its version and whether you already have it. Press download and the plugin is fetched, extracted and handed to e107 to install.

Only that one folder is written, whatever else the repository contains.

## Keeping things up to date

When something you have installed has a newer version in its repository, the screen says so. Downloading again replaces the files on disk; e107 then offers the upgrade in the plugin manager as usual.

{% hint style="info" %}
This is a different job from syncing. GitHub Find installs something new, or refreshes a single plugin from its own repository. Keeping a whole site aligned with a repository is what [GitHub Sync](../plugins/githubsync.md) and [GitHub Sync Lite](../plugins/githubsynclite.md) are for.
{% endhint %}
