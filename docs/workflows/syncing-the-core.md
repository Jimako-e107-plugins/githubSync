# Syncing the core

A core sync downloads the repository as a ZIP, extracts it, and copies its core directories over this installation. Existing files are overwritten. There is no undo.

Do it on a test site first, and take a backup of a live one.

## With GitHub Sync Lite

1. **Source** — set organization, repository and branch, then the two layout settings. See [Repository layout](../reference/repo-layout.md) if you are unsure which to pick.
2. **Core Sync** — press **Refresh plugin list** once, so the screen knows what the repository's plugins directory holds.
3. Tick the plugin folders you want and press **Save selection**.
4. Press **Run core sync**.

## With GitHub Sync

1. **Manual Sync** — add a row with type `core`, the repository location, and the two layout settings.
2. Edit the row, press **Refresh plugin list**, tick the folders you want and save the selection.
3. Run that row.

## What actually gets written

The repository's core directories are mapped onto this site's own directories, whatever they are named locally.

The plugins directory is the exception. Only the folders in your selection are written; everything else under it is skipped and counted in the report. With an empty selection, nothing under the plugins directory is written at all.

Repository housekeeping files — `.gitignore`, `composer.json`, `LICENSE`, `install.php`, `e107_config.php` and similar — are never copied.

## Reading the report

After a run you get counts: how many files were synced, how many plugin folders were included and which, how many items were skipped and how many of those were unselected plugin folders. Failures are listed individually.

A second run is occasionally worth doing — if a new directory appeared during the first pass, its contents can be skipped that time round.

## If it stops before writing anything

The sync checks the extracted archive against your layout settings and refuses to write when they contradict each other, naming the value that would work. It does not change the setting for you. See [Repository layout](../reference/repo-layout.md).
