# Requirements

| | |
| --- | --- |
| e107 | 2.4 |
| PHP | 7.4 or newer (tested to 8.3) |
| Extensions | cURL, and the ZIP handling e107 ships (PclZip) |
| Permissions | Main admin for GitHub Sync Lite; plugin-manager permission for the others |
| Network | Outbound HTTPS to `github.com`, `codeload.github.com`, `api.github.com` and `raw.githubusercontent.com` |

## Write access

A sync writes into your e107 tree. Which directories it touches depends on what you sync — the core directories for a core sync, the plugins directory for a plugin, the themes directory for a theme, the languages directory for a language pack. They have to be writable by the web server user.

The download lands in the system temp directory first and is deleted afterwards, whether the sync succeeded or not.

## Private repositories

A public repository needs nothing. A private one needs a GitHub Personal Access Token with read access to it. The token is stored in the plugin settings, is sent only as a request header, and never appears in a message or in the admin log.

## A note about local servers

On some Windows and WAMP setups, DNS lookups from PHP fail in a way that makes e107's own outbound-request guard refuse even ordinary GitHub addresses. The symptom is unmistakable — see [Troubleshooting](../reference/troubleshooting.md). GitHub Sync Lite's Diagnostics screen exists largely to identify this case.
