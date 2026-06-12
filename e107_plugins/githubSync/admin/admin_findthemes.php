<?php

// e107 Plugin Admin Area — githubSync (mode: onlinethemes) — "Find Themes".
// Thin entry script. Reuses the shared online UI (github_online_handler.php) with
// marketType 'theme'. With no themepack.xml present the registry is empty, so this
// renders the same chrome with an empty list. Theme install/activation is future.

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

if (!defined('PLUGIN_SCAN_INTERVAL'))
{
	define('PLUGIN_SCAN_INTERVAL', !empty($_SERVER['E_DEV']) ? 0 : 360);
}

if (isset($_GET['action']) && $_GET['action'] === 'download' && !defined('e_IFRAME'))
{
	define('e_IFRAME', true);
}


// Theme variant of the shared online UI — only the market type differs.
class github_onlinethemes_ui extends github_online_ui
{
	protected $marketType = 'theme';
}

class github_onlinethemes_form_ui extends github_online_form_ui
{
}


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
