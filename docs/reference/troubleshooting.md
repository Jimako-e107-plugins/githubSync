# Troubleshooting

## "Refused to fetch URL with an unresolvable host, a non-HTTP(S) scheme or a private/reserved IP"

This message comes from e107's own outbound-request guard, not from the plugin. The guard resolves the host before allowing the request and refuses when the lookup fails or returns a private address.

On a live server it usually means what it says. On a local Windows or WAMP setup it usually does not — PHP's DNS functions fail there in a way that makes the guard reject ordinary GitHub addresses.

Open **Diagnostics** in GitHub Sync Lite and run the DNS and SSRF guard check. Things that have resolved it in practice: making sure the site URL is set with `https`, saving the plugin preferences again, and checking whether antivirus software with HTTPS scanning is intercepting the connection.

## The sync stops and says the archive uses a different layout

Working as intended. Your row, or the Source screen, declares a layout the downloaded archive contradicts, so the sync stopped before writing anything. The message names the value that would work — set it and run again. See [Repository layout](repo-layout.md).

## A language sync reports a lot of skipped entries

Two innocent reasons and one real one.

Translations for plugins and themes you do not have are skipped deliberately — a language repository usually covers far more than one site installs.

Entries matching none of the three legitimate destinations are skipped too, and reported so you can see it happened.

The real reason: a wrong **Repo folder prefix**. If nearly everything was skipped, check that setting first.

## The core synced but no plugins appeared

Expected. A core sync writes only the plugin folders in that row's selection. Refresh the plugin list, tick what you want, save the selection, and run again.

## Find Plugins is empty

No catalog is enabled. Open **Find Plugins Sources**, press **Refresh folder catalogs**, tick the catalogs you want and save. Bundled catalogs are never loaded automatically.

If catalogs are enabled and entries still do not appear, check whether they are unticked in that catalog's list — unticked means hidden.

## cURL error 6 / cURL error 60

Error 6 is DNS: the host could not be resolved. Error 60 is certificates: no CA bundle, or one the server cannot read. The Diagnostics **Environment** check reports whether `curl.cainfo` or `openssl.cafile` is set at all.

## A private repository will not download

Check that **Public repository** is switched off on that row or on the Source screen, and that the token has read access to that repository. A token is only sent when the source is marked private.

## Nothing is written and there is no error

Check the target directories are writable by the web server user, and look at the skipped count in the report — a sync that skipped everything reports it rather than failing.
