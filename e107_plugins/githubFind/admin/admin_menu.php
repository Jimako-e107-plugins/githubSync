<?php

// e107 Plugin Admin Area dispatcher — githubFind.
// Standalone plugin: hosts the Find Plugins browser (mode 'online') and its
// catalog Sources screen (mode 'main'), plus the Find Themes browser
// (mode 'onlinethemes') and its Theme Sources screen (mode 'themesources').
// The UI/form classes themselves are defined inline in the entry scripts, and
// the sync engine / marketplace / sources includes live in githubFind/includes/.

// e107::lan('githubFind',true);
e107::coreLan('db', true);

class githubFind_adminArea extends e_admin_dispatcher
{

	protected $defaultMode   = 'main';
	protected $defaultAction = 'prefs';

	protected $modes = array(

		// Find Plugins source list (catalog XMLs). Controller defined in
		// admin/admin_config.php; dispatched there via its 'url' menu item.
		'main' => array(
			'controller'	=> 'github_sources_ui',
			'path'			=> null,
			'ui'			=> 'github_sources_form_ui',
			'uipath'		=> null
		),

		// Find Plugins browser. Controller defined in admin/admin_findplugins.php.
		'online' => array(
			'controller'	=> 'github_online_ui',
			'path'			=> null,
			'ui'			=> 'github_online_form_ui',
			'uipath'		=> null
		),

		// Find Theme source list — marketType 'theme'. Controller + form UI defined
		// inline in admin/admin_themesources.php.
		'themesources' => array(
			'controller'	=> 'github_themesources_ui',
			'path'			=> null,
			'ui'			=> 'github_sources_form_ui',
			'uipath'		=> null
		),

		// Find Themes — marketType 'theme'. Controller + form UI defined inline in
		// admin/admin_findthemes.php.
		'onlinethemes' => array(
			'controller'	=> 'github_onlinethemes_ui',
			'path'			=> null,
			'ui'			=> 'github_onlinethemes_form_ui',
			'uipath'		=> null
		),

	);


	protected $adminMenu = array(

		// Sources screen for plugin catalogs.
		'main/prefs'		=> array(
			'caption'	=> 'Find Plugins Sources',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubFind/admin/admin_config.php',
		),

		// Find Plugins — same caption/icon as core Lite (EPL_ADLAN_220 / fas-search).
		'online/list'		=> array(
			'caption'	=> 'Find Plugins',
			'perm'		=> 'P',
			'icon'		=> 'fas-search',
			'url'		=> '{e_PLUGIN}githubFind/admin/admin_findplugins.php',
		),

		// Find Themes — same UI, empty until a themepack.xml source exists.
		'onlinethemes/list'	=> array(
			'caption'	=> 'Find Themes',
			'perm'		=> 'P',
			'icon'		=> 'fas-search',
			'url'		=> '{e_PLUGIN}githubFind/admin/admin_findthemes.php',
		),

		// Sources screen for theme catalogs.
		'themesources/prefs'	=> array(
			'caption'	=> 'Find Theme Sources',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubFind/admin/admin_themesources.php',
		),

	);

	protected $menuTitle = 'GitHub Find';

	public function init()
	{
		// Append cross-plugin navigation (everything except our own links).
		// Uses the plugin's own copy of the helper; links to plugins that are
		// not installed (e.g. githubSync) are skipped silently.
		e107_require_once(e_PLUGIN . 'githubFind/includes/admin_links.php');

		if (class_exists('githubFind_admin_links'))
		{
			$this->adminMenu = array_merge(
				$this->adminMenu,
				githubFind_admin_links::get(array('githubFind'))
			);
		}
	}
}
