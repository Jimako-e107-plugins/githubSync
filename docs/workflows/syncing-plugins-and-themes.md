# Syncing plugins, themes and languages

This is GitHub Sync's territory. GitHub Sync Lite does the core only.

Each of these is a row in the Manual Sync table with its own type.

## A single plugin

Type `plugin`. The plugin is taken from `{plugins folder}/{folder}` inside the repository and written into your site's plugins directory.

**Folder** defaults to the repository name. Fill it in only when the folder inside the repository is named differently.

Nothing outside that one folder is extracted, whatever else the repository contains.

## A single theme

Type `theme`. The repository root becomes the named theme folder.

## A theme pack

Type `themepack`, for a repository that ships a theme together with the plugins it depends on.

## A language pack

Type `language`, or use **Add Language Repo** to create the row from a GitHub URL in one step.

A language pack has exactly three legitimate destinations: the languages directory, a plugin's folder, and a theme's folder. Anything in the archive that matches none of them is skipped and reported — it is never written to the site root.

Translations for plugins and themes you do not have are skipped too. A language repository usually covers far more than any one site installs, and there is no point creating folders for plugins that are not there.

{% hint style="warning" %}
A language pack is the case where a wrong layout setting shows most clearly. If the repository uses `e107_languages/` and `e107_themes/` but the row says the prefix is `e`, nothing matches and you get a pile of skipped entries. Set **Repo folder prefix** to `e107_` for such a repository.
{% endhint %}

## An ad-hoc repository

Type `other`, for a repository that follows no e107 convention at all. Its whole root goes into one named plugin folder. There is deliberately no path remapping and no fallback into the site root, so it cannot overwrite core directories.
