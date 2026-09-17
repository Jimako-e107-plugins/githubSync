# Repository layout

e107 repositories do not all name their directories the same way, and the plugins do not guess. You tell them which convention the source repository uses.

## The two settings

**Repo folder prefix** — how the core directories are named.

| Value | Directories look like |
| --- | --- |
| `e` | `eadmin`, `ehandlers`, `elanguages`, `ethemes`, `ecore`, … |
| `e107_` | `e107_admin`, `e107_handlers`, `e107_languages`, `e107_themes`, … |

**Repo plugins folder** — how the plugins directory is named.

| Value | Directory |
| --- | --- |
| `eplugins` | `eplugins/` |
| `e107_plugins` | `e107_plugins/` |

The two are independent. A repository may use short core folders and a long plugins folder, or the other way round, and both combinations are accepted.

## These describe the source, not your site

This is the part that catches people out. The settings say nothing about how *your* installation is laid out — that comes from e107 itself and is handled automatically. They describe only the archive being downloaded.

So a Lite fork synced onto a standard e107 site is perfectly normal: the source is `e` + `eplugins`, the destination is whatever your site uses, and the mapping happens in between.

## Getting it wrong

Nothing matches. Every mapped prefix misses, and entries end up wherever the fallback sends them — which, for a language pack, used to mean folders appearing in the site root.

Both are now guarded:

**Before writing**, the sync inspects the extracted archive, works out which convention it actually uses, and stops if that contradicts the settings. The message names the value that would work. It does not change the setting for you — you pick it, because the archive is only evidence, not authority.

**During a language sync**, any entry that matches none of the three legitimate destinations is skipped and reported rather than written.

## Where to set them

| Plugin | Where |
| --- | --- |
| GitHub Sync | Per row, on the Manual Sync add or edit screen |
| GitHub Sync Lite | Once, on the Source screen |
| GitHub Find | Not a setting — its catalogs describe standard-layout repositories |

GitHub Sync keeps them per row because each row points at a different repository. GitHub Sync Lite has one source, so one setting is enough.

## After upgrading GitHub Sync

Rows that existed before these columns were added are filled with `e` and `e107_plugins`, which reproduces what those rows did previously. Check any row pointing at a Lite repository — it needs `eplugins`.
