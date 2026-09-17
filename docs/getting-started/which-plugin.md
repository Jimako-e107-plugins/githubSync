# Which plugin do I need?

The three plugins overlap a little, which is why this page exists. Work down the list — the first match is your answer.

## I want to keep my site's core up to date with one repository

**GitHub Sync Lite.** One source, set once on the Source screen, one button to run it. It can also pull selected plugin folders out of the same archive, so a repository that ships both a core and plugins is handled in a single download.

It has no database table and does not have to be installed in the plugin manager, so it is also the one to reach for when you want to drop it onto a site temporarily.

It is the only one with a Diagnostics screen, which is why it is worth having around even when GitHub Sync does your real work.

## I sync from several repositories, or I sync more than the core

**GitHub Sync.** It keeps a table: one row per source, each with its own type, layout and settings. Alongside the core it handles a single plugin, a single theme, a theme pack, a language pack, and an ad-hoc repository that follows no convention at all.

## I want to browse plugins or themes I do not have yet

**GitHub Find.** Two browsers, Find Plugins and Find Themes, fed by catalog files. Each entry shows its version and whether you already have it, and installs in one click.

This is discovery, not syncing. You point it at a catalog, not at a repository.

## Can I install more than one?

Yes, and it is the usual case. They share no code at runtime — each carries its own copy of the sync engine — so nothing breaks if you later remove one of them. When two of them are installed, each adds a link to the other in its admin menu.

The one thing to avoid is syncing the same target from two of them with different settings. Whichever runs last wins, and neither knows about the other.
