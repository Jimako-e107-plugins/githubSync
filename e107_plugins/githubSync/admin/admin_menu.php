<?php

// e107::lan('githubSync',true);
e107::coreLan('db', true);

define("ADMIN_GITSYNC_ICON", e107::getParser()->toGlyph('fa-file-text-o', array('fw' => 1)));

class githubSync_adminArea extends e_admin_dispatcher
{

	protected $modes = array(

		'main'	=> array(
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
			'ui'			=> null,
			'uipath'		=> null
		),

	);


	protected $adminMenu = array(

		'main/list'			=> array(
			'caption'	=> LAN_MANAGE,
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_config.php',
		),
		'main/create'		=> array(
			'caption'	=> LAN_CREATE,
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_config.php',
		),

		// Own file via 'url' -> admin/admin_addlang.php. The type is fixed to
		// 'language' by this menu choice (no type selector needed).
		'addlang/main'		=> array(
			'caption'	=> 'Add Language Repo',
			'perm'		=> 'P',
			'url'		=> '{e_PLUGIN}githubSync/admin/admin_addlang.php',
		),

	);

	protected $adminMenuAliases = array(
		'main/edit'	=> 'main/list'
	);

	protected $menuTitle = 'Github Sync';
}
