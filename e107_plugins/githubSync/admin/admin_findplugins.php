<?php

// e107 Plugin Admin Area — githubSync (mode: online) — "Find Plugins".
// Thin entry script. The online UI lives in github_online_handler.php and is
// shared with Find Themes. Registry = the plugin's own github_marketplace
// (multisource); download = the plugin's own github_sync_engine. No core
// e_marketplace / unzipGithubArchive dependency, so it works on upstream too.

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php');     // shared dispatcher
e107::coreLan('plugin', true);           // EPL_* + repurposed LAN_* constants used by the UI
e107_require_once(e_PLUGIN . 'githubSync/github_marketplace.php');   // bundled registry (multisource)
e107_require_once(e_PLUGIN . 'githubSync/github_online_handler.php'); // shared online UI

// Defined by core eadmin/plugin.php; this standalone script must provide it.
if (!defined('PLUGIN_SCAN_INTERVAL'))
{
	define('PLUGIN_SCAN_INTERVAL', !empty($_SERVER['E_DEV']) ? 0 : 360);
}

// The download action opens in an e-modal iframe — render bare (no admin chrome).
if (isset($_GET['action']) && $_GET['action'] === 'download' && !defined('e_IFRAME'))
{
	define('e_IFRAME', true);
}


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
