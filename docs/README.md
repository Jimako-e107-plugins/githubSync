# GitHub Sync for e107

Three plugins that bring code onto your e107 site straight from GitHub — without shell access, without `git`, and without a marketplace account.

They cover two different jobs. **Syncing** means pulling a repository you already know about into this installation, overwriting what is there. **Finding** means browsing a catalog of plugins and themes and installing one you have not used before.

## The plugins at a glance

| Plugin | What it does | Stores its settings in | Role |
| --- | --- | --- | --- |
| [**GitHub Sync**](plugins/githubsync.md) | A table of source repositories — core, plugins, themes, theme packs, language packs | A database table, one row per source | Full sync |
| [**GitHub Sync Lite**](plugins/githubsynclite.md) | One source, core only, plus the plugin folders you select | Plugin preferences, no table | Core sync with diagnostics |
| [**GitHub Find**](plugins/githubfind.md) | Find Plugins and Find Themes browsers, fed by catalog files | Plugin preferences, no table | Discovery and install |

Each one is standalone — it carries its own copy of the sync engine and does not need the others installed.

## Two things to know before you start

**Every sync overwrites files on disk.** There is no backup step and no undo. Back up first, and try a new source on a test site before running it on a live one.

**The plugins do not guess your repository's layout.** An e107 Lite fork keeps its core folders as `eadmin`, `ehandlers`, `eplugins`; upstream e107 uses `e107_admin`, `e107_handlers`, `e107_plugins`. You tell the plugin which one it is looking at — see [Repository layout](reference/repo-layout.md).

## Where to go next

| I want to… | Go to |
| --- | --- |
| Work out which plugin I need | [Which plugin do I need?](getting-started/which-plugin.md) |
| Check what my server has to support | [Requirements](getting-started/requirements.md) |
| Install one of them | [Installation](getting-started/installation.md) |
| Update my site's core from a repo | [Syncing the core](workflows/syncing-the-core.md) |
| Sync a single plugin, theme or language pack | [Syncing plugins, themes and languages](workflows/syncing-plugins-and-themes.md) |
| Browse and install something new | [Finding plugins and themes](workflows/finding-new-plugins.md) |
| Work out why a sync failed | [Troubleshooting](reference/troubleshooting.md) |

## Source code

[github.com/Jimako-e107-plugins/githubSync](https://github.com/Jimako-e107-plugins/githubSync)
