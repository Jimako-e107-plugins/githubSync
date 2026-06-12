<?php

// e107 Plugin Admin Area — githubSync (mode: manual) — "Manual Sync".
// Thin entry script: shares the dispatcher from admin_menu.php, defines the
// UI + form classes for mode 'manual' (the manual github_sync table + sync),
// and delegates all sync work to the github_sync_engine handler. No sync logic
// lives here. (Renamed from admin_config.php; that filename is now the
// Preferences screen.)

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107::coreLan('db', true);

e107_require_once('admin_menu.php');                                  // dispatcher: githubSync_adminArea
e107_require_once(e_PLUGIN . 'githubSync/github_sync_engine.php');    // sync engine handler


class github_sync_ui extends e_admin_ui
{
	protected $pluginTitle	= 'Github Sync';
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
		'options'      => array('title' => LAN_OPTIONS,  'type' => 'method',  'data' => null,  'width' => '10%',  'thclass' => 'center last',  'class' => 'center last',  'forced' => 'value',  'readParms' => array(),  'writeParms' => array(),),
	);

	protected $fieldpref = array('type', 'organization', 'repo', 'branch', 'folder', 'note', 'lastsynced');

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

	// ------- Customize Create --------
	public function beforeCreate($new_data, $old_data) { return $new_data; }
	public function afterCreate($new_data, $old_data, $id) {}
	public function onCreateError($new_data, $old_data) {}

	// ------- Customize Update --------
	public function beforeUpdate($new_data, $old_data, $id) { return $new_data; }
	public function afterUpdate($new_data, $old_data, $id) {}
	public function onUpdateError($new_data, $old_data, $id) {}

	// left-panel help menu area (replaces e_help.php used in old plugins)
	public function renderHelp()
	{
		$text  = 'Sync <strong>type</strong> — what gets extracted from the repo:';
		$text .= '<ul>';
		$text .= '<li><strong>core</strong> — full core sync from any repo with e107 in its root</li>';
		$text .= '<li><strong>plugin</strong> — one plugin from e107_plugins/{folder}</li>';
		$text .= '<li><strong>theme</strong> — one theme (legacy root layout for now)</li>';
		$text .= '<li><strong>themepack</strong> — theme + plugins (2 folders)</li>';
		$text .= '<li><strong>language</strong> — language files (3 folders)</li>';
		$text .= '<li><strong>other</strong> — repo root into one plugin folder (ad-hoc / manual)</li>';
		$text .= '</ul>';
		$text .= 'The <strong>Folder</strong> field defaults to the <em>repo name</em> when left '
			. 'empty. For <em>plugin</em> it selects e107_plugins/{folder} inside the repo (and is '
			. 'the target folder); fill it only when that folder differs from the repo name.';
		$text .= '<br><br>Tested on e107 2.3.4 Lite. Use at your own risk.';

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

		$engine = new github_sync_engine();
		$result = $engine->sync(array(
			'organization' => $row['organization'],
			'repo'         => $row['repo'],
			'branch'       => $row['branch'],
			'folder'       => $row['folder'],
			'type'         => $row['type'],
			'token'        => $row['token'] ?? '',
			'public_repo'  => $row['public_repo'] ?? 1,
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
		if (!empty($result['skipped']))
		{
			$mes->addInfo(count($result['skipped']) . ' item(s) skipped.');
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
