<?php

// Manual Sync (mode: manual): the github_sync table — one row per source
// repository, each with its own type, layout and plugin selection. Thin entry
// script; it shares the dispatcher from admin_menu.php and delegates the sync
// itself to the bundled engine.

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107::coreLan('db', true);

e107_require_once('admin_menu.php');                                  // dispatcher: githubSync_adminArea
e107_require_once(e_PLUGIN . 'githubSync/includes/github_sync_engine.php');    // sync engine handler
e107_require_once(e_PLUGIN . 'githubSync/includes/plugin_list.php');           // plugin-folder list + selection (per row)


class github_sync_ui extends e_admin_ui
{
	protected $pluginTitle	= 'GitHub Sync';
	protected $pluginName	= 'githubSync';
	protected $table		= 'github_sync';
	protected $pid			= 'id';
	protected $perPage		= 20;
	protected $batchDelete	= true;
	protected $batchExport	= true;
	protected $batchCopy	= true;

	protected $excludedExportFields = ['token', 'checkboxes', 'options'];

	protected $listOrder = 'id DESC';

	protected $fields = array(
		'checkboxes'   => array('title' => '',  'type' => null,  'data' => null,  'width' => '5%',  'thclass' => 'center',  'forced' => 'value',  'class' => 'center',  'toggle' => 'e-multiselect',  'readParms' => array(),  'writeParms' => array(),),
		'id'           => array('title' => LAN_ID,   'type' => 'number', 'data' => 'int',  'width' => '5%',  'help' => '',  'readParms' => array(),  'writeParms' => array(),  'class' => 'left',  'thclass' => 'left',),
		'type'         => array('title' => LAN_TYPE,  'type' => 'dropdown',  'data' => 'safestr',  'width' => 'auto',  'batch' => 'value',  'filter' => 'value',  'inline' => 'value',  'help' => '',  'readParms' => array(),  'writeParms' => array(),  'class' => 'left',  'thclass' => 'left',),
		'organization' => array(
			'title' => 'Organization',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',
			'filter' => 'value',  'help' => 'e107Inc, Jimako-e107-plugins',  'readParms' => array(),  'writeParms' => array(),
			'class' => 'left',  'thclass' => 'left',
		),
		'repo'         => array('title' => 'Repo',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => '',  'readParms' => array(),  'writeParms' => array(),  'class' => 'left',  'thclass' => 'left',),
		'branch'       => array('title' => 'Branch',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => '',  'readParms' => array(),  'writeParms' => array(),  'class' => 'left',  'thclass' => 'left',),
		'folder'       => array('title' => 'Folder',  'type' => 'text',  'data' => 'safestr',  'width' => 'auto',  'filter' => 'value',  'help' => 'Folder name if different than repo name',  'readParms' => array(),  'writeParms' => array(),  'class' => 'left',  'thclass' => 'left',),
		// Source-repo layout (per row). Whitelisted again in beforeCreate() /
		// beforeUpdate() — the dropdown alone is no guarantee — and once more
		// on read (rowLayout()) and inside the engine, because the values end
		// up as archive path prefixes and in a GitHub API URL segment.
		'folder_prefix' => array(
			'title' => 'Repo folder prefix',  'type' => 'dropdown',  'data' => 'str',  'width' => 'auto',  'filter' => 'value',
			'help'  => 'Prefix of the core directories in the SOURCE repo: <strong>e</strong> for the Lite layout (eadmin, ehandlers, …) '
				. 'or <strong>e107_</strong> for the standard layout (e107_admin, e107_handlers, …). Used by core, themepack and language syncs. '
				. 'Independent of the plugins folder setting.',
			'readParms' => array(),
			'writeParms' => array('optArray' => array('e' => 'e  (Lite: eadmin, ehandlers, …)', 'e107_' => 'e107_  (standard: e107_admin, …)')),
			'class' => 'left',  'thclass' => 'left',
		),
		'plugins_folder' => array(
			'title' => 'Repo plugins folder',  'type' => 'dropdown',  'data' => 'str',  'width' => 'auto',  'filter' => 'value',
			'help'  => 'Name of the plugins directory in the SOURCE repo: <strong>e107_plugins</strong> (standard e107 layout) or '
				. '<strong>eplugins</strong> (Lite layout). Used by plugin, core, themepack and language syncs, and by the plugin list of a core entry. '
				. 'After changing it on a core entry, save and click <em>Refresh plugin list</em>.',
			'readParms' => array(),
			'writeParms' => array('optArray' => array('e107_plugins' => 'e107_plugins (standard)', 'eplugins' => 'eplugins (Lite)')),
			'class' => 'left',  'thclass' => 'left',
		),
		'lastsynced'   => array('title' => 'Last Synced',  'type' => 'datestamp',  'writeParms' => 'type=datetime', 'readonly' => true, 'noedit' => true,  'data' => 'int',   'readParms' => array(),   'class' => 'left',  'thclass' => 'left',),
		'note'         => array('title' => 'Note',  'type' => 'textarea',   'data' => 'str',   'readParms' => array(),   'class' => 'left',  'thclass' => 'left',),
		'token' => [
			'title'      => 'GitHub Token',
			'type'       => 'text',
			'data'       => 'safestr',
			'width'      => 'auto',
			'filter'     => 'value',
			'help'       => 'Personal Access Token (fine-grained or classic) – required for private repositories. Leave empty for public repos.',
			'readParms'  => ['size' => '10'],
			'writeParms' => ['size' => 'block-level', 'maxlength' => '255', 'default' => ''],
			'class'      => 'left',
			'thclass'    => 'left',
		],
		'public_repo' => [
			'title' => 'Public Repository',
			'type'  => 'boolean',              // 1 = checked = public, 0 = unchecked = private
			'data'  => 'int',
			'tab'   => 0,
			'batch' => true,
			'help'  => 'Check if this is a public repo (no token needed). Uncheck for private repos (token becomes required).',
		],
		// Plugin selection of a 'core' entry (stored in the plugin_list column
		// as JSON — folder + list + selection). NOT a data field of the admin
		// form ('data' => false): the column is read and written only by the
		// plugin_list helper, which whitelists every name against the stored
		// list. Rendered by github_sync_form_ui::plugin_list() on the edit
		// screen; hidden on the list.
		'plugin_list'  => array(
			'title'  => 'Plugins (core sync)',
			'type'   => 'method',
			'data'   => false,
			'nolist' => true,
			'help'   => 'Only for type <strong>core</strong>: the plugin folders a core sync extracts from the repo archive. '
				. 'Everything else under the repo\'s plugins folder is skipped; with nothing selected nothing is written there.',
			'readParms' => array(),  'writeParms' => array(),
		),
		'options'      => array('title' => LAN_OPTIONS,  'type' => 'method',  'data' => null,  'width' => '10%',  'thclass' => 'center last',  'class' => 'center last',  'forced' => 'value',  'readParms' => array(),  'writeParms' => array(),),
	);

	protected $fieldpref = array('type', 'organization', 'repo', 'branch', 'folder', 'folder_prefix', 'plugins_folder', 'note', 'lastsynced');

	protected $prefs = array();

	public function __construct($request, $response, $params = array())
	{
		parent::__construct($request, $response, $params);

		// The confirmation form posts githubSyncProcess; route it to syncedPage().
		if ($this->getRequest()->getPosted('githubSyncProcess'))
		{
			$this->getRequest()->setAction('synced');
		}
	}

	public function init()
	{
		// This code may be removed once plugin development is complete.
		if (!e107::isInstalled('githubSync'))
		{
			e107::getMessage()->addWarning('This plugin is not yet installed. Saving and loading of preference or table data will fail.');
		}

		$this->fields['type']['writeParms']['optArray'] = array(
			'core'      => 'core',
			'plugin'    => 'plugin',
			'theme'     => 'theme',
			'themepack' => 'themepack',
			'language'  => 'language',
			'other'     => 'other',
		);
	}

	/**
	 * Whitelist the source-repo layout values. Anything outside the two known
	 * layouts falls back to the migration defaults ('e' / 'e107_plugins').
	 * Used on save (before the model's toDB pass) and on read.
	 *
	 * @param mixed $folderPrefix
	 * @param mixed $pluginsFolder
	 * @return array  array('folder_prefix' => string, 'plugins_folder' => string)
	 */
	public static function normalizeLayout($folderPrefix, $pluginsFolder)
	{
		$folderPrefix = (string) $folderPrefix;
		if (!in_array($folderPrefix, array('e', 'e107_'), true))
		{
			$folderPrefix = 'e';
		}

		$pluginsFolder = (string) $pluginsFolder;
		if (!in_array($pluginsFolder, array('eplugins', 'e107_plugins'), true))
		{
			$pluginsFolder = 'e107_plugins';
		}

		return array('folder_prefix' => $folderPrefix, 'plugins_folder' => $pluginsFolder);
	}

	/**
	 * The layout of a stored row, whitelisted on read (the values come from
	 * the table, which is not trusted more than the form).
	 *
	 * @param array $row  github_sync row
	 * @return array  array('folder_prefix' => string, 'plugins_folder' => string)
	 */
	protected function rowLayout(array $row)
	{
		return self::normalizeLayout($row['folder_prefix'] ?? '', $row['plugins_folder'] ?? '');
	}

	/**
	 * Load one github_sync row by id, or an empty array.
	 *
	 * @param int $id
	 * @return array
	 */
	protected function loadRow($id)
	{
		$id = (int) $id;
		if ($id < 1)
		{
			return array();
		}

		$row = e107::getDb()->retrieve('github_sync', '*', 'WHERE id=' . $id);

		return is_array($row) ? $row : array();
	}

	// ------- Customize Create --------

	/**
	 * Whitelist the layout dropdowns before the core saves (and toDB()s) the row.
	 */
	public function beforeCreate($new_data, $old_data)
	{
		$layout = self::normalizeLayout($new_data['folder_prefix'] ?? '', $new_data['plugins_folder'] ?? '');
		$new_data['folder_prefix']  = $layout['folder_prefix'];
		$new_data['plugins_folder'] = $layout['plugins_folder'];

		return $new_data;
	}
	public function afterCreate($new_data, $old_data, $id) {}
	public function onCreateError($new_data, $old_data) {}

	// ------- Customize Update --------

	/**
	 * Whitelist the layout dropdowns, and — when the plugin selection was on
	 * screen (core entry, main admin) — save the posted checkboxes with the
	 * row, so "what you see is what gets saved" also for the main Save
	 * button. The posted names are only ever matched against the STORED
	 * list inside the helper; nothing from $_POST becomes a path segment.
	 */
	public function beforeUpdate($new_data, $old_data, $id)
	{
		$layout = self::normalizeLayout($new_data['folder_prefix'] ?? '', $new_data['plugins_folder'] ?? '');
		$new_data['folder_prefix']  = $layout['folder_prefix'];
		$new_data['plugins_folder'] = $layout['plugins_folder'];

		$req  = $this->getRequest();
		$type = (string) ($new_data['type'] ?? ($old_data['type'] ?? ''));

		if ($req->getPosted('gs_plugins_form') && $type === 'core' && getperms('0'))
		{
			$this->saveSelection((int) $id, $layout['plugins_folder']);
		}

		return $new_data;
	}
	public function afterUpdate($new_data, $old_data, $id) {}
	public function onUpdateError($new_data, $old_data, $id) {}

	// ------- Plugin selection (core entries) --------

	/**
	 * Row id of the entry being edited (edit URL / posted id / model).
	 *
	 * @return int
	 */
	protected function editId()
	{
		$req = $this->getRequest();
		$id  = (int) $req->getQuery('id', 0);
		if ($id < 1)
		{
			$id = (int) $req->getPosted('id', 0);
		}
		if ($id < 1 && $this->getModel() !== null)
		{
			$id = (int) $this->getModel()->getId();
		}

		return $id;
	}

	/**
	 * Common guard for the two selection triggers: main admin, valid form
	 * token, existing row of type 'core'. Returns the row or an empty array
	 * (reason already reported).
	 *
	 * @return array
	 */
	protected function selectionRow()
	{
		$mes = e107::getMessage();

		if (!getperms('0'))
		{
			$mes->addError('Only the main admin can change the plugin selection.');
			return array();
		}

		// CSRF: the admin edit form carries the e107 form token.
		if (!e107::getSession()->checkFormToken($this->getRequest()->getPosted('e-token', '')))
		{
			$mes->addError('Invalid security token.');
			return array();
		}

		$row = $this->loadRow($this->editId());
		if (empty($row))
		{
			$mes->addError('Sync configuration not found. Save the entry first.');
			return array();
		}

		if (($row['type'] ?? '') !== 'core')
		{
			$mes->addError('The plugin selection applies to entries of type "core" only.');
			return array();
		}

		return $row;
	}

	/**
	 * POST etrigger_refresh_plugins on the edit screen: one deliberate GitHub
	 * API call for THIS row's repository, branch and plugins folder (the
	 * STORED values — save layout changes first). No clearCache() beforehand:
	 * refresh() merges the stored selection with the new list.
	 *
	 * @param mixed $value  posted trigger value (unused)
	 * @return void
	 */
	public function editRefreshPluginsTrigger($value = null)
	{
		$row = $this->selectionRow();
		if (empty($row))
		{
			return;
		}

		$layout = $this->rowLayout($row);

		$list = githubSyncLite_plugin_list::refresh(array(
			'id'             => (int) $row['id'],
			'organization'   => $row['organization'],
			'repo'           => $row['repo'],
			'branch'         => $row['branch'],
			'token'          => $row['token'] ?? '',
			'public_repo'    => (int) ($row['public_repo'] ?? 1),
			'plugins_folder' => $layout['plugins_folder'],
		));

		if ($list !== false)
		{
			e107::getMessage()->addSuccess(count($list) . ' plugin folder(s) found in the repo and stored with this entry. Your selection was kept.');
		}
	}

	/**
	 * POST etrigger_save_selection on the edit screen: store the posted
	 * checkboxes as the row's selection (whitelisted against the stored list).
	 *
	 * @param mixed $value  posted trigger value (unused)
	 * @return void
	 */
	public function editSaveSelectionTrigger($value = null)
	{
		$row = $this->selectionRow();
		if (empty($row))
		{
			return;
		}

		$layout = $this->rowLayout($row);
		$this->saveSelection((int) $row['id'], $layout['plugins_folder']);
	}

	/**
	 * Save the posted gs_plugins[] checkboxes as the stored selection of one
	 * row and report how many folders are selected. Only names present in
	 * the stored list survive (whitelist); the base plugins are always added.
	 * Does nothing (and says so) when no list is stored yet for this
	 * plugins-folder setting.
	 *
	 * @param int    $id             github_sync row id
	 * @param string $pluginsFolder  whitelisted plugins folder of the row
	 * @return void
	 */
	protected function saveSelection($id, $pluginsFolder)
	{
		$mes = e107::getMessage();
		$req = $this->getRequest();

		if (githubSyncLite_plugin_list::getCached($id, $pluginsFolder) === null)
		{
			$mes->addInfo('No plugin list stored for this entry (or it was made for a different plugins folder) — nothing to select. Click <strong>Refresh plugin list</strong> first.');
			return;
		}

		$posted = $req->getPosted('gs_plugins', array());
		if (!is_array($posted))
		{
			$posted = array();
		}

		$selected = githubSyncLite_plugin_list::saveSelection($posted, $id, $pluginsFolder);

		$mes->addSuccess(count($selected) . ' plugin folder(s) selected (including the base plugins) — saved with this entry.');
	}

	// left-panel help menu area (replaces e_help.php used in old plugins)
	public function renderHelp()
	{
		$text  = 'Sync <strong>type</strong> — what gets extracted from the repo:';
		$text .= '<ul>';
		$text .= '<li><strong>core</strong> — the repo\'s core directories, plus ONLY the '
			. '<strong>selected plugin folders</strong> from the repo\'s plugins folder (nothing there with an empty selection)</li>';
		$text .= '<li><strong>plugin</strong> — one plugin from {plugins folder}/{folder}</li>';
		$text .= '<li><strong>theme</strong> — one theme (legacy root layout for now)</li>';
		$text .= '<li><strong>themepack</strong> — theme + plugins (2 folders)</li>';
		$text .= '<li><strong>language</strong> — language files (3 folders)</li>';
		$text .= '<li><strong>other</strong> — repo root into one plugin folder (ad-hoc / manual)</li>';
		$text .= '</ul>';
		$text .= 'The <strong>Folder</strong> field defaults to the <em>repo name</em> when left '
			. 'empty. For <em>plugin</em> it selects {plugins folder}/{folder} inside the repo (and is '
			. 'the target folder); fill it only when that folder differs from the repo name.';
		$text .= '<br><br><strong>Repo folder prefix</strong> and <strong>Repo plugins folder</strong> describe '
			. 'the SOURCE repo\'s layout per entry: <em>e</em> + <em>eplugins</em> for a Lite repo, '
			. '<em>e107_</em> + <em>e107_plugins</em> for a standard e107 repo (the two are independent).';
		$text .= '<br><br>For a <strong>core</strong> entry, edit it to manage its <strong>plugin selection</strong>: '
			. '<em>Refresh plugin list</em> reads the repo\'s plugins folder once (one GitHub API call) and stores '
			. 'the list with the entry; tick the folders you want and <em>Save selection</em>. Main admin only.';
		$text .= '<br><br>Every sync overwrites files on disk. Back up first and try it on a test site. Use at your own risk.';

		return array(
			'caption' => LAN_HELP,
			'text'    => $text,
		);
	}

	/**
	 * Confirmation screen for a single sync entry (read-only). The actual
	 * sync runs on POST (githubSyncProcess) -> syncedPage().
	 */
	public function syncPage()
	{
		$frm = e107::getForm();
		$mes = e107::getMessage();

		if (!getperms('0'))
		{
			$mes->addError('Only the main admin can use this functionality!');
			return $mes->render();
		}

		$id = (int) $this->getRequest()->getQuery('id', 0);
		if ($id < 1)
		{
			$mes->addError('Invalid sync ID.');
			return $mes->render();
		}

		$data = e107::getDb()->retrieve('github_sync', '*', 'WHERE id=' . $id);
		if (empty($data))
		{
			$mes->addError('Sync configuration not found.');
			return $mes->render();
		}

		$organization = $data['organization'];
		$repo         = $data['repo'];
		$branch       = $data['branch'];
		$isPublic     = !empty($data['public_repo']); // 1 = public, 0 = private
		$hasToken     = !empty(trim($data['token'] ?? ''));

		if ($isPublic)
		{
			$remotefile = "https://codeload.github.com/{$organization}/{$repo}/zip/{$branch}";

			$note  = "You are syncing with public repo: <strong><a href='{$remotefile}' target='_blank'>{$remotefile}</a></strong><br>";
			$note .= "You can open this URL in your browser to download the ZIP file manually.<br>";
			$note .= "Clicking the button below will download and extract it – this will <strong>overwrite existing files</strong>.<br>";
			$note .= "<strong>Tip:</strong> If some files/folders are ignored on first run (especially new folders), run sync a second time.";

			$mes->addWarning($note);
		}
		else
		{
			$remotefile = "https://api.github.com/repos/{$organization}/{$repo}/zipball/{$branch}";

			$note  = "<strong>Private repository sync</strong><br><br>";
			$note .= "Repository: <strong>{$organization}/{$repo}</strong> (branch: {$branch})<br>";
			$note .= "Direct download via browser <strong>will not work</strong> for private repos.<br><br>";

			if (!$hasToken)
			{
				$note .= "<strong style='color:red'>No GitHub token is set!</strong><br>";
				$note .= "You must provide a valid Personal Access Token (PAT) in the sync settings.<br>";
				$note .= "Go back to the list, edit this entry and enter your token.<br><br>";
			}
			else
			{
				$note .= "Using stored token for authenticated download.<br><br>";
			}

			$note .= "Clicking the button below will attempt to download and extract via the authenticated API.<br>";
			$note .= "This will <strong>overwrite existing files</strong> in the target folder.";

			$mes->addWarning($note);
		}

		// Source-repo layout used for this entry (whitelisted on read), and the
		// plugin selection of a core entry — so the admin sees what a run writes.
		$layout      = $this->rowLayout($data);
		$safePrefix  = htmlspecialchars($layout['folder_prefix'], ENT_QUOTES, 'utf-8');
		$safePlugDir = htmlspecialchars($layout['plugins_folder'], ENT_QUOTES, 'utf-8');

		$layoutNote = "Repo layout: core folders <strong>{$safePrefix}*</strong>, plugins in <strong>{$safePlugDir}/</strong>.";

		if (($data['type'] ?? '') === 'core')
		{
			$selected = githubSyncLite_plugin_list::getSelected($id, $layout['plugins_folder']);
			$editUrl  = e_SELF . '?mode=' . $this->getMode() . '&action=edit&id=' . $id;

			if (empty($selected))
			{
				$layoutNote .= "<br>Plugin selection: <strong>none</strong> — nothing under {$safePlugDir}/ will be written. "
					. "<a href='" . $editUrl . "'>Edit the entry</a> to select plugin folders.";
			}
			else
			{
				$safeNames = array_map(function ($name) { return htmlspecialchars($name, ENT_QUOTES, 'utf-8'); }, $selected);
				$layoutNote .= "<br>Plugin selection (" . count($selected) . "): <strong>" . implode('</strong>, <strong>', $safeNames)
					. "</strong> — only these folders are extracted from {$safePlugDir}/; the rest is skipped.";
			}
		}

		$mes->addInfo($layoutNote);

		$min_php_version = '7.4';
		if (version_compare(PHP_VERSION, $min_php_version, '<'))
		{
			$mes->addWarning('The minimum required PHP version is <strong>' . $min_php_version . '</strong>. You are using PHP <strong>' . PHP_VERSION . '</strong>.<br /> Syncing with Github has been disabled to avoid broken functionality.');
		}
		else
		{
			$message  = $frm->open('githubSync', 'post', e_SELF . '?mode=' . $this->getMode() . '&action=sync&id=' . $id);
			$message .= $frm->token();          // CSRF: emits <input name="e-token"> (open() does NOT)
			$message .= $frm->hidden('id', $id);
			$message .= '<p>' . DBLAN_116 . ' <b>' . e_SYSTEM . 'temp</b> ' . DBLAN_117 . ' </p>';
			$message .= $frm->button('githubSyncProcess', 1, 'delete', DBLAN_113);
			$message .= $frm->close();

			$mes->addInfo($message);
		}

		return $mes->render();
	}

	/**
	 * Runs the sync (state-changing). CSRF-checked. Delegates to the engine.
	 */
	public function syncedPage()
	{
		$mes = e107::getMessage();

		if (!getperms('0'))
		{
			$mes->addError('Only the main admin can run a sync.');
			return $mes->render();
		}

		// CSRF: the confirmation form (frm->open) carries the e107 form token.
		if (!e107::getSession()->checkFormToken($this->getRequest()->getPosted('e-token', '')))
		{
			$mes->addError('Invalid security token.');
			return $mes->render();
		}

		$id = (int) $this->getRequest()->getPosted('id', $this->getRequest()->getQuery('id', 0));
		if ($id < 1)
		{
			$mes->addError('Invalid sync ID.');
			return $mes->render();
		}

		$row = e107::getDb()->retrieve('github_sync', '*', 'WHERE id=' . $id);
		if (empty($row))
		{
			$mes->addError('Sync configuration not found.');
			return $mes->render();
		}

		// Per-row source layout (whitelisted on read; the engine whitelists it
		// again) and, for a core entry, the stored plugin selection — already
		// a subset of the stored list; the engine re-validates every name
		// before it becomes a path segment. Same call shape as githubSyncLite's
		// Core Sync.
		$layout  = $this->rowLayout($row);
		$isCore  = ($row['type'] === 'core');
		$plugins = $isCore ? githubSyncLite_plugin_list::getSelected($id, $layout['plugins_folder']) : array();

		$engine = new github_sync_engine();
		$result = $engine->sync(array(
			'organization'   => $row['organization'],
			'repo'           => $row['repo'],
			'branch'         => $row['branch'],
			'folder'         => $row['folder'],
			'type'           => $row['type'],
			'token'          => $row['token'] ?? '',
			'public_repo'    => $row['public_repo'] ?? 1,
			'plugins_folder' => $layout['plugins_folder'],
			'folder_prefix'  => $layout['folder_prefix'],
			'plugins'        => $plugins,
		));

		if ($result === false)
		{
			// The engine has already reported the reason via getMessage().
			return $mes->render();
		}

		// Record sync time — native db->update(), not db->gen().
		e107::getDb()->update('github_sync', array(
			'data'         => array('lastsynced' => time()),
			'WHERE'        => 'id=' . $id,
			'_FIELD_TYPES' => array('lastsynced' => 'int'),
		));

		// Clean result output — counts, no print_a dumps.
		if (!empty($result['success']))
		{
			$mes->addSuccess(count($result['success']) . ' file(s)/folder(s) synced.');
		}

		$safePlugDir = htmlspecialchars($layout['plugins_folder'], ENT_QUOTES, 'utf-8');

		if ($isCore)
		{
			if (empty($plugins))
			{
				$mes->addInfo('No plugin folders were included in this run (nothing selected) — nothing was written under ' . $safePlugDir . '/.');
			}
			else
			{
				$safeNames = array_map(function ($name) { return htmlspecialchars($name, ENT_QUOTES, 'utf-8'); }, $plugins);
				$mes->addInfo(count($plugins) . ' plugin folder(s) included in this run from ' . $safePlugDir . '/: <strong>'
					. implode('</strong>, <strong>', $safeNames) . '</strong>');
			}
		}

		if (!empty($result['skipped']))
		{
			if ($isCore)
			{
				// Count how many of the skipped archive entries sit under the
				// repo's plugins folder ({zipBase}/{plugins_folder}/...), so the
				// admin can see the unselected plugin folders were left alone.
				$plugPattern  = '#^[^/]+/' . preg_quote($layout['plugins_folder'], '#') . '/#';
				$skippedPlugs = count(preg_grep($plugPattern, $result['skipped']));

				$mes->addInfo(count($result['skipped']) . ' item(s) skipped — ' . $skippedPlugs . ' of them under the repo\'s '
					. $safePlugDir . '/ directory (plugin folders not in your selection), the rest repo housekeeping files.');
			}
			else
			{
				$mes->addInfo(count($result['skipped']) . ' item(s) skipped.');
			}
		}
		if (!empty($result['error']))
		{
			$failed = array_map(function ($e) { return htmlspecialchars($e, ENT_QUOTES, 'utf-8'); }, $result['error']);
			$mes->addWarning(count($result['error']) . ' item(s) failed:<br>' . implode('<br>', $failed));
		}

		e107::getCache()->clearAll('system');

		return $mes->render();
	}

	/**
	 * Custom batch export handler — excludes the token field via SELECT.
	 */
	protected function handleListExportBatch($selected)
	{
		if (empty($selected))
		{
			e107::getMessage()->addError('No items selected for export.');
			$this->redirect();
			return;
		}

		$ids    = array_map('intval', $selected);
		$idList = implode(',', $ids);

		$exportFields = array_keys($this->fields ?? array());
		$exportFields = array_diff($exportFields, $this->excludedExportFields);
		$fieldsStr    = !empty($exportFields) ? implode(', ', $exportFields) : '*';

		$table   = $this->getTableName();   // 'github_sync'
		$primary = $this->getPrimaryName(); // 'id'

		$options = array(
			'file'   => "e107Export_{$table}_" . date('YmdHi') . '.xml',
			'query'  => "`{$primary}` IN ({$idList})",
			'fields' => $fieldsStr,          // key point: exclude token here
		);

		// Core export handler sends headers and exits.
		e107::getXml()->e107Export(null, array($table), null, null, $options);
	}
}


class github_sync_form_ui extends e_admin_form_ui
{
	/**
	 * Custom render for the 'plugin_list' field. Edit screen of a 'core' entry
	 * only: the row's stored plugin-folder list as checkboxes, checked from the
	 * stored selection, plus the refresh action.
	 *
	 * @param mixed $curVal
	 * @param string $mode
	 * @return string|null
	 */
	public function plugin_list($curVal, $mode, $parms = array())
	{
		if ($mode !== 'write')
		{
			return '';
		}

		$controller = $this->getController();
		$model      = $controller->getModel();
		$id         = ($model !== null) ? (int) $model->getId() : 0;

		if ($id < 1)
		{
			return "<div class='alert alert-info'>Save the entry first, then edit it to select the plugin folders a core sync should write.</div>";
		}

		$type = ($model !== null) ? (string) $model->get('type') : '';
		if ($type !== 'core')
		{
			return "<div class='alert alert-info'>The plugin selection applies to entries of type <strong>core</strong> only.</div>";
		}

		if (!getperms('0'))
		{
			return "<div class='alert alert-info'>Only the main admin can change the plugin selection.</div>";
		}

		$layout      = github_sync_ui::normalizeLayout($model->get('folder_prefix'), $model->get('plugins_folder'));
		$safePlugDir = htmlspecialchars($layout['plugins_folder'], ENT_QUOTES, 'utf-8');
		$safeLocal   = htmlspecialchars(e107::getFolder('PLUGINS'), ENT_QUOTES, 'utf-8');

		$refresh = $this->admin_button('etrigger_refresh_plugins', 1, 'other', 'Refresh plugin list');

		$cached = githubSyncLite_plugin_list::getCached($id, $layout['plugins_folder']);

		if ($cached === null)
		{
			$note  = "<div class='alert alert-info'>";
			$note .= "No plugin list stored for this entry yet (or the stored list was made for a different "
				. "plugins-folder setting). Click <strong>Refresh plugin list</strong> to read the repo's <strong>"
				. $safePlugDir . "/</strong> folder once (uses the SAVED organization, repo, branch and plugins folder) "
				. "and store it with this entry. Until then a core sync writes nothing under " . $safePlugDir . "/.";
			$note .= "</div>";

			return $note . "<div style='margin-bottom:10px'>" . $refresh . "</div>";
		}

		$base     = githubSyncLite_plugin_list::basePlugins();
		$selected = $cached['selected'];

		$rows = '';
		foreach ($cached['list'] as $folder)
		{
			$isBase    = in_array($folder, $base, true);
			$onDisk    = githubSyncLite_plugin_list::existsOnDisk($folder);
			$installed = githubSyncLite_plugin_list::isInstalled($folder);
			$checked   = in_array($folder, $selected, true);

			// Base plugins get their own class so the check/uncheck-all
			// buttons skip them — base is always handled manually.
			$boxClass = $isBase ? 'gs-plugin-base' : 'gs-plugin-select';

			$labelBits = array();
			if ($isBase)
			{
				$labelBits[] = "<span class='label label-primary'>base</span>";
			}
			if ($installed)
			{
				$labelBits[] = "<span class='label label-success'>installed</span>";
			}
			elseif ($onDisk)
			{
				$labelBits[] = "<span class='label label-info'>on disk</span>";
			}
			else
			{
				$labelBits[] = "<span class='label label-default'>not present</span>";
			}

			$safeFolder = htmlspecialchars($folder, ENT_QUOTES, 'utf-8');

			$rows .= "<tr>";
			$rows .= "<td style='width:5%' class='center'>"
				. $this->checkbox('gs_plugins[]', $safeFolder, $checked, array('class' => $boxClass))
				. "</td>";
			$rows .= "<td>{$safeFolder}</td>";
			$rows .= "<td>" . implode(' ', $labelBits) . "</td>";
			$rows .= "</tr>";
		}

		$legend  = "<table class='table table-striped'><tbody>";
		$legend .= "<tr><td style='width:28%'><strong>Checked</strong></td>"
			. "<td>the saved selection — the plugin folders a core sync extracts from the repo archive "
			. "(" . count($selected) . " selected). Change it and click <strong>Save selection</strong> "
			. "(the main <strong>Save</strong> button stores it too).</td></tr>";
		$legend .= "<tr><td><span class='label label-primary'>base</span></td>"
			. "<td>always selected; the check/uncheck-all buttons skip these</td></tr>";
		$legend .= "<tr><td><span class='label label-success'>installed</span></td>"
			. "<td>registered on this site (informational only)</td></tr>";
		$legend .= "<tr><td><span class='label label-info'>on disk</span></td>"
			. "<td>folder exists in " . $safeLocal . " but the plugin is not installed (informational only)</td></tr>";
		$legend .= "<tr><td><span class='label label-default'>not present</span></td>"
			. "<td>no local folder (informational only)</td></tr>";
		$legend .= "<tr><td><strong>List source</strong></td>"
			. "<td>the repo's <strong>" . $safePlugDir . "/</strong> folder, stored with this entry &middot; "
			. "<em>Refresh plugin list</em> re-reads it and keeps your selection; folders that disappeared "
			. "from the repo are dropped and reported</td></tr>";
		$legend .= '</tbody></table>';

		$toolbar  = "<script>function gsSetAll(state){var b=document.querySelectorAll('.gs-plugin-select');"
			. "for(var i=0;i<b.length;i++){b[i].checked=state;}}</script>";
		$toolbar .= $this->admin_button('etrigger_save_selection', 1, 'update', 'Save selection');
		$toolbar .= ' ' . $refresh;
		$toolbar .= " <button type='button' class='btn btn-default' onclick='gsSetAll(true)'>Check all</button>";
		$toolbar .= " <button type='button' class='btn btn-default' onclick='gsSetAll(false)'>Uncheck all</button>";

		$table  = "<table class='table table-striped'>";
		$table .= "<thead><tr><th style='width:5%'></th><th>Plugin folder</th><th>Status</th></tr></thead>";
		$table .= "<tbody>{$rows}</tbody>";
		$table .= "</table>";

		return $legend . "<div style='margin-bottom:10px'>" . $toolbar . "</div>" . $table . $this->hidden('gs_plugins_form', 1);
	}

	// Override the default Options column.
	function options($parms, $value, $id, $attributes)
	{
		if ($attributes['mode'] !== 'read')
		{
			return;
		}

		$model        = $this->getController()->getListModel();
		$organization = $model->get('organization');
		$repo         = $model->get('repo');
		$branch       = $model->get('branch');

		$text = '';

		// View on GitHub
		if (!empty($organization) && !empty($repo))
		{
			$githubUrl = "https://github.com/{$organization}/{$repo}/tree/{$branch}";
			$text .= "<a href='{$githubUrl}' target='_blank' class='btn btn-primary' title='View repository on GitHub' data-toggle='tooltip' data-bs-toggle='tooltip' data-placement='left'><i class='fa fa-eye'></i></a>";
		}
		else
		{
			$text .= "<button class='btn btn-primary disabled' title='Repository URL not available (missing organization/repo)'><i class='fa fa-eye'></i></button>";
		}

		// Edit
		$query    = array('mode' => $this->getController()->getMode(), 'action' => 'edit', 'id' => $id);
		$queryStr = http_build_query($query, '', '&amp;');
		$text    .= "<a href='" . e_SELF . "?{$queryStr}' class='btn btn-success' title='" . LAN_EDIT . "' data-toggle='tooltip' data-bs-toggle='tooltip' data-placement='left'><i class='S16 e-edit-16'></i></a>";

		// Delete (main admin only)
		if (getperms('0'))
		{
			$text .= $this->submit_image('etrigger_delete[' . $id . ']', $id, 'delete', LAN_DELETE . ' [ ID: ' . $id . ' ]', ['class' => 'action delete btn btn-danger']);
		}

		// Sync (opens the read-only confirmation page; the actual run is the
		// CSRF-checked POST from that page).
		$query2    = array('mode' => $this->getController()->getMode(), 'action' => 'sync', 'id' => $id);
		$query2Str = http_build_query($query2, '', '&amp;');
		$text     .= "<a href='" . e_SELF . "?{$query2Str}' class='btn btn-warning' title='Run Sync'>" . ADMIN_GITSYNC_ICON . "</a>";

		return $text;
	}
}


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
