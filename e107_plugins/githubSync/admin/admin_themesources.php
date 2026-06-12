<?php

// e107 Plugin Admin Area — githubSync (mode: themesources) — "Find Theme Sources".
// Thin entry script. Reuses the shared sources UI (github_sources_handler.php) via
// github_themesources_ui. Manages the 'find_theme_sources' preference
// (theme catalogs: sources/themes/*.xml + remote URLs).

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php');                                 // shared dispatcher
e107_require_once(e_PLUGIN . 'githubSync/github_sync_sources.php');  // folder scan + read accessor
e107_require_once(e_PLUGIN . 'githubSync/github_sources_handler.php'); // shared sources UI (plugin + theme)


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
