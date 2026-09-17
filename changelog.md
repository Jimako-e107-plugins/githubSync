# Changelog

## GitHub Sync 2.5.0

- Per-row source layout: each row records the repository's core-folder prefix (`e` / `e107_`) and plugins directory (`eplugins` / `e107_plugins`), replacing the previously hardcoded folder names
- Per-row plugin selection: a `core` row stores the repository's plugin-folder list and the folders a sync should extract
- Sync engine unified with the GitHub Sync Lite version; a `core` sync now writes only the selected plugin folders and nothing else under the plugins directory
- A language sync no longer writes archive entries that match none of its three destinations; unmatched entries are skipped and reported instead of landing in the site root
- A sync aborts before writing when the archive's layout contradicts the row's settings, and names the value that would work
- Find Themes, theme sources and the marketplace reader moved out to GitHub Find
- Renamed to GitHub Sync in the admin area

## GitHub Sync Lite 0.4.1

- Selective plugin sync: the plugin-folder list and the selection are stored in the plugin preferences and extracted from the same archive as the core
- Source screen gained the repository layout settings
- Core Sync screen restructured: actions above the list, two-column plugin list, descriptive text collapsed
- Site fixes screen removed — the fixes are part of e107 core
- Renamed to GitHub Sync Lite in the admin area

## GitHub Find 1.1.1

- Standalone: bundles its own copy of the sync engine and no longer requires GitHub Sync
- Find Themes and theme sources merged in from GitHub Sync — one plugin now covers both plugins and themes
- Source screens: ticked means shown in the Find browser (previously ticked meant hidden), with select-all and clear-all per catalog
- Fixed the plugin-sources screen saving under the wrong plugin name, which left plugin catalogs permanently empty
- Renamed to GitHub Find in the admin area
