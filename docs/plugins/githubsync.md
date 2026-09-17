# GitHub Sync

The full plugin: a table of source repositories, each synced on its own terms.

| | |
| --- | --- |
| Folder | `githubSync` |
| Needs installing | Yes — it creates the `github_sync` table |
| Permission | Plugin-manager permission |
| Stores | One database row per source |

## Screens

**Manual Sync** is the plugin. It lists your sources and runs them. Adding a row means saying where the repository is, what kind of content it holds, and how its directories are named.

**Add Language Repo** is a shortcut for the most common repetitive case. Paste a language repository's GitHub URL and it creates the row for you, with the type set to `language` and the branch to `master`. The repository has to be public.

**Preferences** holds plugin-wide settings.

## What a row contains

| Field | Meaning |
| --- | --- |
| Type | What gets extracted — see below |
| Organization / Repository / Branch | Where it is on GitHub |
| Folder | Target folder for a plugin or theme; defaults to the repository name |
| Repo folder prefix | `e` or `e107_` — the source repository's core-directory naming |
| Repo plugins folder | `eplugins` or `e107_plugins` — the source repository's plugins directory |
| Public repository | Off means private, which requires a token |
| Plugin selection | `core` rows only: which plugin folders to take out of the archive |
| Note | Yours to use |
| Last synced | Filled in after a successful run |

## Sync types

| Type | What it extracts |
| --- | --- |
| `core` | The repository's core directories, plus only the plugin folders you selected on that row |
| `plugin` | One plugin, from `{plugins folder}/{folder}` inside the repository |
| `theme` | One theme |
| `themepack` | A theme together with its plugins |
| `language` | Language files into the languages, plugins and themes directories |
| `other` | The whole repository root into one named plugin folder — for repositories that follow no convention |

`other` exists because not every repository is laid out the way e107 expects. It cannot overwrite core directories: everything lands inside the single plugin folder you name.

## The plugin selection on a core row

A core sync never writes the whole plugins directory. Edit the row, press **Refresh plugin list** — one GitHub API call reads the repository's plugins directory and stores the folder names with the row — then tick the folders you want and save.

Anything not ticked is skipped and reported. With nothing ticked, nothing is written under the plugins directory at all.

The stored list is also the whitelist: a folder name can only be selected if it is in the list, whatever the browser sends.
