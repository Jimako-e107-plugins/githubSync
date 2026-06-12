<?php

// e107 Plugin Admin Area — githubSync (mode: sources) — "Find Plugin Sources".
// Thin entry script. The sources UI lives in github_sources_handler.php and is
// shared with Find Theme Sources. Manages the 'find_sources' preference
// (plugin catalogs: sources/plugins/*.xml + remote URLs).

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php');                                 // shared dispatcher
e107_require_once(e_PLUGIN . 'githubSync/github_sync_sources.php');  // folder scan + read accessor
e107_require_once(e_PLUGIN . 'githubSync/github_sources_handler.php'); // shared sources UI


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
