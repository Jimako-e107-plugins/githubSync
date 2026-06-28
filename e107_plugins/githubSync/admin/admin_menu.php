<?php

// e107::lan('githubSync',true);
e107::coreLan('db', true);

define("ADMIN_GITSYNC_ICON", e107::getParser()->toGlyph('fa-file-text-o', array('fw' => 1)));

class githubSync_adminArea extends e_admin_dispatcher
{

	protected $defaultMode   = 'main';
	protected $defaultAction = 'prefs';

	protected $modes = array(

		'manual'	=> array(
			'controller' 	=> 'github_sync_ui',
			'path' 			=> null,
			'ui' 			=> 'github_sync_form_ui',
			'uipath' 		=> null
		),

		// Quick-add form for language repos. Controller is defined in its own
		// entry script (admin/admin_addlang.php), which is where this mode is
		// dispatched via the 'url' menu item below — so 'path' stays null.
		'addlang' => array(
			'controller'	=> 'github_addlang_ui',
			'path'			=> null,
			'ui'			=> 'github_addlang_form_ui',
			'uipath'		=> null
		),

		// Find Plugin source list (catalog XMLs). Controller defined in
		// admin/admin_sources.php; dispatched there via its 'url' menu item.
		'sources' => array(
			'controller'	=> 'github_sources_ui',
			'path'			=> null,
			'ui'			=> 'github_sources_form_ui',
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

		'online' => array(
			'controller'	=> 'github_online_ui',
			'path'			=> null,
			'ui'			=> 'github_online_form_ui',
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

		// Plugin preferences (placeholder for now). Controller defined in
		// admin/admin_config.php.
		'main' => array(
			'controller'	=> 'github_settings_ui',
			'path'			=> null,
			'ui'			=> 'github_settings_form_ui',
			'uipath'		=> null
		),

	);


	protected $adminMenu = array(

		// Find Plugins — same caption/icon as core Lite (EPL_ADLAN_220 / fas-search).
		'online/list'		=> array(
			'caption'	=> 'Find Plugins',
			'perm'		=> 'P',
			'icon'		=> 'fas-search',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_findplugins.php',
		),

		// Find Themes — same UI, empty until a themepack.xml source exists.
		'onlinethemes/list'	=> array(
			'caption'	=> 'Find Themes',
			'perm'		=> 'P',
			'icon'		=> 'fas-search',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_findthemes.php',
		),

		'manual/list'			=> array(
			'caption'	=> 'Manual Sync',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_manual.php',
		),
		'manual/create'		=> array(
			'caption'	=> 'Add Manual Sync',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_manual.php',
		),

		// Own file via 'url' -> admin/admin_addlang.php. The type is fixed to
		// 'language' by this menu choice (no type selector needed).
		'addlang/main'		=> array(
			'caption'	=> 'Add Language Repo',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_addlang.php',
		),

		'sources/prefs'		=> array(
			'caption'	=> 'Find Plugins Sources',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_sources.php',
		),
		'themesources/prefs'	=> array(
			'caption'	=> 'Find Theme Sources',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_themesources.php',
		),

		// General plugin preferences — last.
		'main/prefs'		=> array(
			'caption'	=> 'Preferences',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_config.php',
		),

	);

	protected $adminMenuAliases = array(
		'manual/edit'	=> 'manual/list'
	);

	protected $menuTitle = 'Github Sync';
}
