# GitHub Sync Lite

One source, one job: bring the core from a repository onto this installation, together with the plugin folders you selected.

| | |
| --- | --- |
| Folder | `githubSyncLite` |
| Needs installing | No — copying the folder in is enough |
| Permission | Main admin only |
| Stores | Plugin preferences, no database table |

It is called Lite because it does less, not because it only works with Lite. The source can be a Lite fork or an upstream e107 repository — the layout settings say which.

## Screens

**Core Sync** is the everyday screen: a summary of the source, the plugin list as checkboxes, and the buttons.

**Source** is where you set the repository once — organization, repository, branch, its layout, and a token if it is private.

**Diagnostics** is read-only and touches nothing. It exists to answer the question "why did the download fail", which is otherwise very hard to answer from a failed sync alone.

## Diagnostics

Four checks plus one full-weight test:

| Check | Answers |
| --- | --- |
| Environment | Is a CA bundle installed at all (`curl.cainfo` / `openssl.cafile`) |
| DNS and SSRF guard | Does e107's own `isUrlSafe()` accept the GitHub host, or does it refuse it |
| Connection | Is this a DNS failure, a missing CA bundle, or a block inside the handler |
| Heavy test | Repeats the sync's real ZIP download, end to end |

The distinction the Connection check draws is the useful one: cURL error 6 is DNS, cURL error 60 is certificates, and a refusal without a cURL error at all is the guard. See [Troubleshooting](../reference/troubleshooting.md).

## The plugin selection

The Core Sync screen lists the repository's plugin folders as checkboxes. Ticked folders are extracted from the same archive the core comes from — no second download.

Press **Refresh plugin list** to read the list from GitHub. That is one API call, done only when you ask for it; every other page load reads the stored copy. Your saved selection survives a refresh, and folders that have disappeared from the repository are dropped and reported.

The base plugins are always included and the check-all and clear-all buttons skip them.

Ticks you have not saved are lost when you refresh the list, so save first.

The stored list is the whitelist: a folder name can only be selected if it is in the list, whatever the browser sends.
