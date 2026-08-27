<?php

// e107 Plugin Admin Area dispatcher — findPlugins.
// Split out of githubSync: hosts the Find Plugins browser (mode 'online') and
// its catalog Sources screen (mode 'main'). The UI/form classes themselves are
// defined inline in the entry scripts (admin_config.php / admin_findplugins.php),
// and the shared sync engine / marketplace includes live in githubSync.

// e107::lan('findPlugins',true);
e107::coreLan('db', true);

class findPlugins_adminArea extends e_admin_dispatcher
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

	);


	protected $adminMenu = array(

		// Sources screen for plugin catalogs.
		'main/prefs'		=> array(
			'caption'	=> 'Find Plugins Sources',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}findPlugins/admin/admin_config.php',
		),

		// Find Plugins — same caption/icon as core Lite (EPL_ADLAN_220 / fas-search).
		'online/list'		=> array(
			'caption'	=> 'Find Plugins',
			'perm'		=> 'P',
			'icon'		=> 'fas-search',
			'url'		=> '{e_PLUGIN}findPlugins/admin/admin_findplugins.php',
		),

	);

	protected $menuTitle = 'Find Plugins';

	public function init()
	{
		// findPlugins depends on githubSync for its shared includes (engine,
		// marketplace, sources scanner). Without it nothing here can work.
		if (!e107::isInstalled('githubSync'))
		{
			e107::getMessage()->addError('githubSync plugin is required.');
			return;
		}

		// Append cross-plugin navigation (everything except our own links).
		e107_require_once(e_PLUGIN . 'githubSync/includes/admin_links.php');

		if (class_exists('githubSync_admin_links'))
		{
			$this->adminMenu = array_merge(
				$this->adminMenu,
				githubSync_admin_links::get(array('findPlugins'))
			);
		}
	}
}
