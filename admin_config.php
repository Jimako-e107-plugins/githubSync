<?php

// Generated e107 Plugin Admin Area 

require_once('../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

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


	);


	protected $adminMenu = array(

		'main/list'			=> array('caption' => LAN_MANAGE, 'perm' => 'P'),
		'main/create'		=> array('caption' => LAN_CREATE, 'perm' => 'P'),

		// 'main/div0'      => array('divider'=> true),
		// 'main/custom'		=> array('caption'=> 'Custom Page', 'perm' => 'P'),

	);

	protected $adminMenuAliases = array(
		'main/edit'	=> 'main/list'
	);

	protected $menuTitle = 'Github Sync';
}





class github_sync_ui extends e_admin_ui
{

	private $sync_organization = '';
	private $sync_repo = '';
	private $sync_branch = '';
	private $sync_type = '';
	private $sync_id = '';

	protected $pluginTitle		= 'Github Sync';
	protected $pluginName		= 'githubSync';
	//	protected $eventName		= 'githubSync-github_sync'; // remove comment to enable event triggers in admin. 		
	protected $table			= 'github_sync';
	protected $pid				= 'id';
	protected $perPage			= 10;
	protected $batchDelete		= true;
	protected $batchExport     = true;
	protected $batchCopy		= true;

	//	protected $sortField		= 'somefield_order';
	//	protected $sortParent      = 'somefield_parent';
	//	protected $treePrefix      = 'somefield_title';

	//	protected $tabs				= array('Tabl 1','Tab 2'); // Use 'tab'=>0  OR 'tab'=>1 in the $fields below to enable. 

	//	protected $listQry      	= "SELECT * FROM `#tableName` WHERE field != '' "; // Example Custom Query. LEFT JOINS allowed. Should be without any Order or Limit.

	protected $listOrder		= 'id DESC';

	protected $fields 		= array(
		'checkboxes'              => array('title' => '',  'type' => null,  'data' => null,  'width' => '5%',  'thclass' => 'center',  'forced' => 'value',  'class' => 'center',  'toggle' => 'e-multiselect',  'readParms' =>  array(),  'writeParms' =>  array(),),
		'id'                      => array('title' => LAN_ID,   'type' => 'number', 'data' => 'int',  'width' => '5%',  'help' => '',  'readParms' =>  array(),  'writeParms' =>  array(),  'class' => 'left',  'thclass' => 'left',),
		'type'                    => array('title' => LAN_TYPE,  'type' => 'dropdown',  'data' => 'safestr',  'width' => 'auto',  'batch' => 'value',  'filter' => 'value',  'inline' => 'value',  'help' => '',  'readParms' =>  array(),  'writeParms' =>  array(),  'class' => 'left',  'thclass' => 'left',),
		'organization'            => array(
			'title' => 'Organization',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',
			'filter' => 'value',  'help' => 'e107Inc, Jimako-e107-plugins',  'readParms' =>  array(),  'writeParms' =>  array(),
			'class' => 'left',  'thclass' => 'left',
		),
		'repo'                    => array('title' => 'Repo',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => '',  'readParms' =>  array(),  'writeParms' =>  array(),  'class' => 'left',  'thclass' => 'left',),
		'branch'                  => array('title' => 'Branch',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => '',  'readParms' =>  array(),  'writeParms' =>  array(),  'class' => 'left',  'thclass' => 'left',),
		'folder'                  => array('title' => 'Folder',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => 'Folder name if different than repo name',  'readParms' =>  array(),  'writeParms' =>  array(),  'class' => 'left',  'thclass' => 'left',),
		'lastsynced'             => array('title' => 'Last Synced',  'type' => 'datestamp',  'writeParms' => 'type=datetime', 'readonly' => true, 'noedit' => true,  'data' => 'int',   'readParms' =>  array(),   'class' => 'left',  'thclass' => 'left',),
		'options'                 => array('title' => LAN_OPTIONS,  'type' => 'method',  'data' => null,  'width' => '10%',  'thclass' => 'center last',  'class' => 'center last',  'forced' => 'value',  'readParms' =>  array(),  'writeParms' =>  array(),),
	);

	protected $fieldpref = array('type', 'organization', 'repo', 'branch', 'lastsynced');


	//	protected $preftabs        = array('General', 'Other' );
	protected $prefs = array();

	public function __construct($request, $response, $params = [])
	{
		parent::__construct($request, $response, $params = []);

		if (!empty($_POST['githubSyncProcess']))
		{
			$this->getRequest()->setAction('synced');

			$id = e107::getParser()->filter($_GET['id'], "int");

			$data = e107::getDb()->retrieve('github_sync', '*', 'WHERE id=' . $id);

			$this->sync_organization = $data['organization'];
			$this->sync_repo = $data['repo'];
			$this->sync_branch = $data['branch'];
			$this->sync_type = $data['type'];
			$this->sync_id = $id;
			$this->sync_folder = !empty($data['folder']) ? $data['folder'] : $this->sync_repo;
		}
	}

	public function init()
	{
		// This code may be removed once plugin development is complete. 
		if (!e107::isInstalled('githubSync'))
		{
			e107::getMessage()->addWarning("This plugin is not yet installed. Saving and loading of preference or table data will fail.");
		}

		// Set drop-down values (if any). 
		$this->fields['type']['writeParms']['optArray'] = array('core' => 'core', 'plugin' => 'plugin', 'theme' => 'theme'); // Example Drop-down array. 

	}


	// ------- Customize Create --------

	public function beforeCreate($new_data, $old_data)
	{
		return $new_data;
	}

	public function afterCreate($new_data, $old_data, $id)
	{
		// do something
	}

	public function onCreateError($new_data, $old_data)
	{
		// do something		
	}


	// ------- Customize Update --------

	public function beforeUpdate($new_data, $old_data, $id)
	{
		return $new_data;
	}

	public function afterUpdate($new_data, $old_data, $id)
	{
		// do something	
	}

	public function onUpdateError($new_data, $old_data, $id)
	{
		// do something		
	}

	// left-panel help menu area. (replaces e_help.php used in old plugins)
	public function renderHelp()
	{
		$caption = LAN_HELP;
		$text = 'Some help text';

		return array('caption' => $caption, 'text' => $text);
	}

	public function syncPage()
	{
		$frm = e107::getForm();
		$mes = e107::getMessage();
		$pref = e107::pref();

		$key = 'github';
		$val['label'] = DBLAN_112;
		$val['diz'] = DBLAN_115;

		//get data 
		$id = e107::getParser()->filter($_GET['id'], "int");

		$data = e107::getDb()->retrieve('github_sync', '*', 'WHERE id=' . $id);

		$organization = $data['organization'];
		$repo = $data['repo'];
		$branch = $data['branch'];

		$text = "<h4 style='margin-bottom:3px'><a href='" . e_SELF . '?mode=' . $key . "' title=\"" . $val['label'] . '">' . $val['label'] . '</a></h4><small>' . $val['diz'] . '</small>';

		if (!getperms('0'))
		{
			$text = e107::getMessage()->addError('Only main admin can use this functionality!');
			$text = $mes->render();

			return $text;
		}

		$remotefile = "https://codeload.github.com/{$organization}/{$repo}/zip/{$branch}";
		$note = 'You are syncing with repo: <b>' . $remotefile;
		$note .= '</b><br>You can put this URL to the browser and download the file manually. If you click on the button below, you will overwrite existing files.';

		e107::getMessage()->addWarning($note);

		$text = $mes->render();

		$min_php_version = '7.4';

		if (version_compare(PHP_VERSION, $min_php_version, '<'))
		{
			$mes->addWarning('The minimum required PHP version is <strong>' . $min_php_version . '</strong>. You are using PHP version <strong>' . PHP_VERSION . '</strong>. <br /> Syncing with Github has been disabled to avoid broken fuctionality.'); // No need to translate, developer mode only
		}
		else
		{
			$message = $frm->open('githubSync');
			$message .= '<p>' . DBLAN_116 . ' <b>' . e_SYSTEM . 'temp</b> ' . DBLAN_117 . ' </p>';
			$message .= $frm->button('githubSyncProcess', 1, 'delete', DBLAN_113);
			$message .= $frm->close();

			$mes->addInfo($message);
		}

		$text .= $mes->render();

		return $text;
	}

	public function syncedPage()
	{
		$result = $this->unzipGithubArchive();

		if ($result === false)
		{
			e107::getMessage()->addError(DBLAN_118);

			return null;
		}

		$success = $result['success'];
		$error = $result['error'];
		$skipped = $result['skipped'];

		//		$message = e107::getParser()->lanVars(DBLAN_121, array('x'=>$oldPath, 'y'=>$newPath));

		if (!empty($success))
		{
			e107::getMessage()->addSuccess(print_a($success, true));
		}

		if (!empty($skipped))
		{
			e107::getMessage()->setTitle('Skipped', E_MESSAGE_INFO)->addInfo(print_a($skipped, true));
		}

		if (!empty($error))
		{
			//e107::getMessage()->addError(print_a($error,true));
			e107::getMessage()->setTitle('Ignored', E_MESSAGE_WARNING)->addWarning(print_a($error, true));
		}

		e107::getRender()->tablerender(SEP . DBLAN_112, e107::getMessage()->render());

		e107::getCache()->clearAll('system');
	}


	/**
	 * Download and extract a zipped copy of e107 plugin. Copy of method from file_class.php
	 *
	 * @param string $url              "core" to download the e107 core from Git master or
	 *                                 a custom download URL
	 * @param string $destination_path The e107 root where the downloaded archive should be extracted,
	 *                                 with a directory separator at the end
	 *
	 * @return array|bool FALSE on failure;
	 *                    An array of successful and failed path extractions
	 */

	public function unzipGithubArchive($url = 'plugin', $destination_path = e_BASE)
	{

		$organization = $this->sync_organization;
		$repo = $this->sync_repo;
		$branch = $this->sync_branch;
		$folder = $this->sync_folder;


		if ($this->sync_type == 'plugin')
		{
			$destination_path = $destination_path . e107::getFolder('PLUGINS');

			$localfile = "{$repo}.zip";

			$newFolders = [
				"{$repo}-{$branch}" => $destination_path . "{$folder}",
			];
		}
		if ($this->sync_type == 'theme')
		{
			$destination_path = $destination_path . e107::getFolder('THEMES');

			$localfile = "{$repo}.zip";

			$newFolders = [
				"{$repo}-{$branch}" => $destination_path . "{$folder}",
			];
		}
		elseif ($this->sync_type == 'core')
		{
			$localfile = "{$folder}-{$branch}.zip";
			$destination_path = e_BASE;
		}


		$remotefile = "https://codeload.github.com/{$organization}/{$repo}/zip/{$branch}";
		$excludes = [
			"{$repo}-{$branch}/.codeclimate.yml",
			"{$repo}-{$branch}/.editorconfig",
			"{$repo}-{$branch}/.gitignore",
			"{$repo}-{$branch}/.gitmodules",
			"{$repo}-{$branch}/CONTRIBUTING.md", // moved to ./.github/CONTRIBUTING.md
			"{$repo}-{$branch}/LICENSE",
			//	"{$repo}-{$branch}/README.md",
			"{$repo}-{$branch}/composer.json",
			"{$repo}-{$branch}/composer.lock",
			"{$repo}-{$branch}/install.php",
			"{$repo}-{$branch}/favicon.ico",
			"{$repo}-{$branch}/e107_config.php",
		];
		$excludeMatch = [
			'/.github/',
			'/e107_tests/',
		];

		// Delete any existing file.
		if (file_exists(e_TEMP . $localfile))
		{
			unlink(e_TEMP . $localfile);
		}


		$result = e107::getFile()->getRemoteFile($remotefile, $localfile);

		if ($result === false)
		{
			return false;
		}

		chmod(e_TEMP . $localfile, 0755);
		require_once e_HANDLER . 'pclzip.lib.php';

		$zipBase = str_replace('.zip', '', $localfile); // eg. e107-master
		$excludes[] = $zipBase;

		if ($this->sync_type == 'core')
		{
			$newFolders = array(
				$zipBase . '/e107_admin/'     => $destination_path . e107::getFolder('ADMIN'),
				$zipBase . '/e107_core/'      => $destination_path . e107::getFolder('CORE'),
				$zipBase . '/e107_docs/'      => $destination_path . e107::getFolder('DOCS'),
				$zipBase . '/e107_handlers/'  => $destination_path . e107::getFolder('HANDLERS'),
				$zipBase . '/e107_images/'    => $destination_path . e107::getFolder('IMAGES'),
				$zipBase . '/e107_languages/' => $destination_path . e107::getFolder('LANGUAGES'),
				$zipBase . '/e107_media/'     => $destination_path . e107::getFolder('MEDIA'),
				$zipBase . '/e107_plugins/'   => $destination_path . e107::getFolder('PLUGINS'),
				$zipBase . '/e107_system/'    => $destination_path . e107::getFolder('SYSTEM'),
				$zipBase . '/e107_themes/'    => $destination_path . e107::getFolder('THEMES'),
				$zipBase . '/e107_web/'       => $destination_path . e107::getFolder('WEB'),
				$zipBase . '/'                => $destination_path
			);
		}

		$srch = array_keys($newFolders);
		$repl = array_values($newFolders);

		$archive = new PclZip(e_TEMP . $localfile);

		$unarc = ($fileList = $archive->extract(PCLZIP_OPT_PATH, e_TEMP, PCLZIP_OPT_SET_CHMOD, 0755)); // Store in TEMP first.

		$error = [];
		$success = [];
		$skipped = [];

		foreach ($unarc as $k => $v)
		{
			if (
				$this->matchFound($v['stored_filename'], $excludeMatch) ||
				in_array($v['stored_filename'], $excludes)
			)
			{
				$skipped[] = $v['stored_filename'];
				continue;
			}

			$oldPath = $v['filename'];
			$newPath = str_replace($srch, $repl, $v['stored_filename']);

			if ($v['folder'] == 1 && is_dir($newPath))
			{
				// $skipped[] =  $newPath. " (already exists)";
				continue;
			}
			@mkdir(dirname($newPath), 0755, true);
			if (!rename($oldPath, $newPath))
			{
				$error[] = $newPath;
			}
			else
			{
				$success[] = $newPath;
			}
		}

		//UPDATE `e107jm_github_sync` SET `lastsynced` = '11' WHERE `e107jm_github_sync`.`id` = 2; 
		$query = "UPDATE #github_sync SET lastsynced = '" . time() . "' WHERE id=" . $this->sync_id;

		e107::getDb()->gen($query);
		return ['success' => $success, 'error' => $error, 'skipped' => $skipped];
	}

	/**
	 * @param $file
	 * @param $array
	 *
	 * @return bool
	 */
	private function matchFound($file, $array)
	{
		if (empty($array))
		{
			return false;
		}

		foreach ($array as $term)
		{
			if (strpos($file, $term) !== false)
			{
				return true;
			}
		}

		return false;
	}
}



class github_sync_form_ui extends e_admin_form_ui
{

	// Override the default Options field. 
	function options($parms, $value, $id, $attributes)
	{

		if ($attributes['mode'] !== 'read')
		{
			return;
		}

		if ($attributes['mode'] == 'read')
		{
			$id			= $this->getController()->getListModel()->get('id');

			$query['action'] = 'edit';
			$query['id'] = $id;
			$query = http_build_query($query, '', '&amp;');

			$text = "<a href='" . e_SELF . "?{$query}' class='btn btn-default' title='" . LAN_EDIT . "' data-toggle='tooltip' data-bs-toggle='tooltip' data-placement='left'>
								<i class='S16 e-edit-16' ></i></a>";

			if (getperms('0'))   // only main admins
			{
				$text .= $this->submit_image('etrigger_delete[' . $id . ']', $id, 'delete', LAN_DELETE . ' [ ID: ' . $id . ' ]', array('class' => 'action delete btn btn-default'));
			}

			if (true)  //change to active
			{

				$query2['action'] = 'sync';
				$query2['id'] = $id;
				$query2 = http_build_query($query2, '', '&amp;');

				$link = e_SELF . "?{$query2}";
				$text .= "<a href='" . $link . "' class='btn btn-info' title='Run Sync'>" . ADMIN_GITSYNC_ICON . "</a>";  //
			}

			return $text;
		}
	}
}


new githubSync_adminArea();

require_once(e_ADMIN . "auth.php");
e107::getAdminUI()->runPage();

require_once(e_ADMIN . "footer.php");
exit;
