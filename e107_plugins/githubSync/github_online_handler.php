<?php

/**
 * github_online_handler — shared "Find Plugins / Find Themes" admin UI.
 *
 * The online list + form (a 1:1 copy of the core Lite Find Plugins UI) live here
 * so both entry scripts reuse them:
 *   - admin/admin_findplugins.php  -> github_online_ui            (marketType 'plugin')
 *   - admin/admin_findthemes.php   -> github_onlinethemes_ui      (marketType 'theme')
 *
 * Registry uses the plugin's own github_marketplace (multisource: reads every
 * enabled catalog from github_sync_sources::getEnabled()). Download uses the
 * plugin's own github_sync_engine (folder-scoped). Both are self-contained, so
 * Find Plugins works on Lite AND upstream e107. Themes reuse the same UI; with no
 * theme catalog the registry is empty, so Find Themes renders an empty list.
 *
 * @package githubSync
 */

if (!defined('e107_INIT'))
{
	exit;
}

class github_online_ui extends e_admin_ui
{
	protected $pluginTitle   = ADLAN_98;
	protected $pluginName    = 'core';
	protected $marketType    = 'plugin'; // 'plugin' | 'theme' — set by the theme subclass
	protected $table         = false;
	protected $pid           = 'plugin_id';
	protected $perPage       = 10;
	protected $batchDelete   = true;
	protected $batchExport   = true;
	protected $batchCopy     = true;

	protected $listOrder     = '';
	protected $fields        = array();

	protected $fieldpref = array('plugin_icon', 'plugin_name', 'plugin_version', 'plugin_description', 'plugin_license', 'plugin_compatible', 'plugin_category', 'plugin_installflag');

	protected $prefs = array();

	/** @var github_marketplace */
	protected $mp = null;

	// Memoised registry data (one fetch shared by the list and renderHelp).
	protected $_registry = null;


	public function __construct($request, $response, $params = array())
	{
		$this->fields = $this->pluginManagerFields();
		unset($this->fields['checkboxes']);

		$this->fields['plugin_category']['writeParms']['optArray'] = e107::getPlug()->getCategoryList();
		$this->fields['plugin_license']['nolist']  = false;
		$this->fields['plugin_category']['inline'] = false;

		// Online-list icon: dedicated class (drops the default 32px 'icon' rules),
		// sized via the CSS added in init().
		$this->fields['plugin_icon']['readParms'] = 'class=plugin-icon-lg';

		// Version cell also carries the release date, so the header reflects both.
		$this->fields['plugin_version']['title'] = 'Online Version';

		// Released + Author now sit under the version / name; nolist drops the
		// columns AND the column selector (overrides saved per-user prefs).
		$this->fields['plugin_date']['nolist']   = true;
		$this->fields['plugin_author']['nolist'] = true;
		$this->fields['plugin_name']['title']    = 'Title / Author';

		// Repurpose the Installed flag column to show the installed version.
		$this->fields['plugin_installflag']['type']      = 'text';
		$this->fields['plugin_installflag']['title']     = 'Installed version';
		$this->fields['plugin_installflag']['readParms'] = '';
		$this->fields['plugin_installflag']['class']     = 'left';
		$this->fields['plugin_installflag']['thclass']   = 'left';

		// Place the Installed version column directly after Online Version.
		$ordered = array();
		foreach ($this->fields as $k => $v)
		{
			if ($k === 'plugin_installflag') { continue; }
			$ordered[$k] = $v;
			if ($k === 'plugin_version' && isset($this->fields['plugin_installflag']))
			{
				$ordered['plugin_installflag'] = $this->fields['plugin_installflag'];
			}
		}
		$this->fields = $ordered;

		parent::__construct($request, $response, $params);
	}

	/**
	 * Field schema, copied verbatim from core plugman_adminArea::getPluginManagerFields()
	 * (that core dispatcher class is not loaded here, so the schema is inlined).
	 */
	private function pluginManagerFields()
	{
		return array(
			'checkboxes'         => array('title' => '', 'type' => null, 'data' => null, 'width' => '5%', 'thclass' => 'center', 'forced' => '1', 'class' => 'center', 'toggle' => 'e-multiselect',),
			'plugin_id'          => array('title' => LAN_ID, 'data' => 'int', 'width' => '5%', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_icon'        => array('title' => LAN_ICON, 'type' => 'icon', 'data' => false, 'width' => '5%', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_name'        => array('title' => LAN_TITLE, 'type' => 'text', 'data' => 'str', 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_version'     => array('title' => LAN_VERSION, 'type' => 'text', 'data' => false, 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_description' => array('title' => LAN_DESCRIPTION, 'type' => 'textarea', 'data' => false, 'width' => 'auto', 'help' => '', 'readParms' => 'expand=1&truncate=180&bb=1', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_date'        => array('title' => LAN_RELEASED, 'type' => 'text', 'data' => false, 'width' => '8%', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_category'    => array('title' => LAN_CATEGORY, 'type' => 'dropdown', 'data' => 'str', 'width' => 'auto', 'batch' => true, 'filter' => true, 'inline' => true, 'help' => '', 'readParms' => '', 'writeParms' => array(), 'class' => 'left', 'thclass' => 'left',),
			'plugin_author'      => array('title' => LAN_AUTHOR, 'type' => 'text', 'data' => false, 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_license'     => array('title' => 'License', 'nolist' => false, 'data' => false, 'type' => 'text', 'width' => '5%', 'thclass' => 'left'),
			'plugin_compatible'  => array('title' => EPL_ADLAN_13, 'type' => 'method', 'data' => false, 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'center', 'thclass' => 'center',),
			'plugin_path'        => array('title' => LAN_PATH, 'type' => 'text', 'data' => 'str', 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'plugin_installflag' => array('title' => EPL_ADLAN_22, 'type' => 'boolean', 'data' => 'int', 'width' => 'auto', 'filter' => false, 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'center', 'thclass' => 'center',),
			'plugin_addons'      => array('title' => LAN_ADDONS, 'type' => 'method', 'data' => 'str', 'width' => 'auto', 'help' => '', 'readParms' => '', 'writeParms' => '', 'class' => 'left', 'thclass' => 'left',),
			'options'            => array('title' => LAN_OPTIONS, 'type' => 'method', 'data' => null, 'width' => '10%', 'thclass' => 'center last', 'class' => 'center last', 'forced' => '1',),
		);
	}

	public function init()
	{
		e107_require_once(e_PLUGIN . 'githubSync/github_marketplace.php');

		// Larger icons in the Find Plugins listing.
		e107::css('inline', 'img.plugin-icon-lg{height:100px;width:auto;max-width:120px;vertical-align:middle}');
	}

	/**
	 * Help-column panel for the Find Plugins view. Two states per installed
	 * registry plugin: repo newer than disk -> download; disk newer than DB ->
	 * apply upgrade. Runs from the constructor (before the list observers), so it
	 * uses the memoised registry data. Skipped on AJAX and the download action.
	 */
	public function renderHelp()
	{
		if (deftrue('e_AJAX_REQUEST') || $this->getAction() === 'download')
		{
			return null;
		}

		$xdata = $this->getRegistryData();
		if (empty($xdata['data']))
		{
			// Nothing to list — almost always because no catalog source is ENABLED.
			$kind = ($this->marketType === 'theme') ? 'themes' : 'plugins';
			return array(
				'caption' => 'No ' . $kind . ' listed',
				'text'    => $this->sourcesHint(),
			);
		}

		$tp        = e107::getParser();
		$plg       = e107::getPlug();
		$installed = $plg->getInstalled(); // folder => DB (installed) version
		$rows      = '';

		foreach ($xdata['data'] as $row)
		{
			$folder = $row['folder'];

			if (!isset($installed[$folder]))
			{
				continue;
			}

			$online = (string) vartrue($row['version']);
			$dbVer  = (string) $installed[$folder];
			$disk   = (string) $plg->load($folder)->getVersion(); // version of files on disk

			$icon = !empty($row['icon'])
				? "<img src='" . $tp->toAttribute($row['icon']) . "' alt='' style='height:32px;width:auto' />"
				: $tp->toGlyph('fa-download');

			if ($online !== '' && $disk !== '' && version_compare($disk, $online, '<'))
			{
				// Repo newer than the files on disk -> download the new release.
				$url = e_SELF . '?' . http_build_query(array(
					'mode'         => $this->getMode(),
					'action'       => 'download',
					'install'      => 0,
					'organization' => (string) vartrue($row['params']['organization']),
					'repo'         => (string) vartrue($row['params']['repo']),
					'branch'       => (string) vartrue($row['params']['branch'], 'main'),
					'folder'       => $folder,
					'e-token'      => defset('e_TOKEN'),
				));
				$caption   = 'Download ' . $row['name'] . ' v' . $online;
				$title     = 'New release - download ' . $row['name'] . ' ' . $disk . ' -> ' . $online;
				$linkClass = 'e-modal';
				$linkAttr  = " data-modal-caption=\"" . $tp->toAttribute($caption) . "\"";
				$state     = " <small class='text-warning'>" . htmlspecialchars($disk, ENT_QUOTES, 'utf-8') . " &rarr; " . htmlspecialchars($online, ENT_QUOTES, 'utf-8') . "</small>";
			}
			elseif ($dbVer !== '' && $disk !== '' && version_compare($dbVer, $disk, '<'))
			{
				// Files already downloaded, DB behind -> apply the upgrade.
				$url       = e_ADMIN . 'plugin.php?mode=installed&action=upgrade&path=' . $folder . '&e-token=' . defset('e_TOKEN');
				$title     = 'Apply update ' . $row['name'] . ' ' . $dbVer . ' -> ' . $disk;
				$linkClass = 'e-spinner';
				$linkAttr  = " target='_top'";
				$state     = " <small class='text-success'>v" . htmlspecialchars($disk, ENT_QUOTES, 'utf-8') . " ready to upgrade</small>";
			}
			else
			{
				continue; // up to date
			}

			$rows .= "<li class='media'>
				<div class='media-left'><a class='" . $linkClass . "'" . $linkAttr . " href='" . $url . "'>" . $icon . "</a></div>
				<div class='media-body'><a class='" . $linkClass . "'" . $linkAttr . " href='" . $url . "' title=\"" . $tp->toAttribute($title) . "\">"
				. htmlspecialchars((string) $row['name'], ENT_QUOTES, 'utf-8') . $state . "</a></div></li>";
		}

		if ($rows === '')
		{
			return null;
		}

		return array('caption' => 'New release available', 'text' => "<ul class='media-list'>" . $rows . "</ul>");
	}

	/**
	 * Help text shown when the list is empty: point the admin at the Sources
	 * screen for this market type. The common gotcha is that bundled folder
	 * catalogs are imported DISABLED, so the list stays empty until a source is
	 * ticked Enabled (+ Save) — that is what this stresses.
	 */
	private function sourcesHint()
	{
		$isTheme = ($this->marketType === 'theme');
		$screen  = $isTheme ? 'Find Theme Sources'     : 'Find Plugins Sources';
		$file    = $isTheme ? 'admin_themesources.php' : 'admin_sources.php';
		$mode    = $isTheme ? 'themesources'           : 'sources';
		$kind    = $isTheme ? 'themes'                 : 'plugins';

		$url = e_PLUGIN_ABS . 'githubSync/admin/' . $file . '?mode=' . $mode . '&amp;action=prefs';

		return 'This list is built only from catalog <strong>sources</strong> that are '
			. '<strong>enabled</strong> for ' . $kind . '. An empty list usually means no source has '
			. 'been imported or switched on yet.'
			. '<br><br>Open <a href="' . $url . '"><strong>' . $screen . '</strong></a> (also in the '
			. 'left admin menu). After install, press <strong>Refresh folder catalogs</strong> to import '
			. 'the bundled catalogs — they appear <em>disabled</em> — then tick <strong>Enabled</strong> '
			. 'on the one(s) you want and press <strong>Save</strong>. You can also paste a remote '
			. 'catalog URL there. Sources live in plugin preferences, so a sync never wipes them.';
	}

	public function pluginCheck($force = false)
	{
		if (!PLUGIN_SCAN_INTERVAL)
		{
			e107::getPlugin()->update_plugins_table('update');
			return;
		}

		if ((time() > vartrue($_SESSION['nextPluginFolderScan'], 0)) || $force == true)
		{
			e107::getPlugin()->update_plugins_table('update');
		}

		$_SESSION['nextPluginFolderScan'] = time() + PLUGIN_SCAN_INTERVAL;
	}

	// Modal download / install handler (runs under e_IFRAME).
	public function downloadPage()
	{
		if (empty($_GET['e-token']))
		{
			echo e107::getMessage()->addError("Invalid Token")->render('default', 'error');
			return null;
		}

		$tp  = e107::getParser();
		$mes = e107::getMessage();

		$params = array(
			'organization' => $tp->filter($_GET['organization'], 'str'),
			'repo'         => $tp->filter($_GET['repo'],         'str'),
			'branch'       => $tp->filter($_GET['branch'],       'str'),
			'folder'       => $tp->filter($_GET['folder'],       'str'),
		);

		if (empty($params['organization']) || empty($params['repo']) || empty($params['branch']) || empty($params['folder']))
		{
			echo $mes->addError("Missing required parameters.")->render('default', 'error');
			return null;
		}

		// install=0 => download only (re-download / refresh files, no install routine)
		$doInstall = !(isset($_GET['install']) && (string) $_GET['install'] === '0');

		if (deftrue('e_DEBUG_MARKETPLACE'))
		{
			echo "<b>DEBUG MODE ACTIVE (no downloading)</b><br />";
			print_a($params);
			return false;
		}

		$mes->addSuccess(EPL_ADLAN_94);

		// Use the plugin's own engine instead of core e107::getFile()->unzipGithubArchive():
		// the engine extracts ONLY e107_plugins/{folder}/ -> eplugins/{folder}/
		// (folder-scoped) correctly on BOTH Lite and upstream e107, whereas the
		// upstream unzipGithubArchive does not folder-scope. Return shape matches
		// (false on hard failure, else ['success'=>[], 'error'=>[], 'skipped'=>[]]).
		e107_require_once(e_PLUGIN . 'githubSync/github_sync_engine.php');
		$engine = new github_sync_engine();
		$result = $engine->sync(array(
			'organization' => $params['organization'],
			'repo'         => $params['repo'],
			'branch'       => $params['branch'],
			'folder'       => $params['folder'],
			'type'         => $this->marketType, // 'plugin' | 'theme'
			'public_repo'  => 1,                  // marketplace catalogs are public
			'token'        => '',
		));

		if ($result === false)
		{
			echo $mes->addError(EPL_ADLAN_95)->render('default', 'error');
			return null;
		}

		if (!empty($result['success']))
		{
			$this->pluginCheck(true); // rescan plugin directory

			if ($doInstall && $this->marketType === 'plugin')
			{
				$text = e107::getPlugin()->install($params['folder']);
				$mes->addInfo($text);

				// Show upgrade button if local version is behind plugin.xml version.
				$upgradable = e107::getPlug()->getUpgradableList();
				if (!empty($upgradable[$params['folder']]))
				{
					$upgradeUrl = e_ADMIN . "plugin.php?mode=installed&action=upgrade&path=" . $params['folder'] . "&e-token=" . defset('e_TOKEN');
					$mes->addSuccess("<a target='_top' href='" . $upgradeUrl . "' class='btn btn-primary'>" . LAN_UPDATE . "</a>");
				}
			}
			else
			{
				$mes->addSuccess('Files downloaded. Plugin was not installed - use Install to enable it.');
			}

			echo $mes->render('default', 'success');
		}
		else
		{
			echo $mes->addError(EPL_ADLAN_95)->render('default', 'error');
		}

		if (!empty($result['error']))
		{
			echo $mes->setTitle('Ignored', E_MESSAGE_WARNING)
					->addWarning(print_a($result['error'], true))
					->render('default', 'warning');
		}

		echo $mes->render('default', 'debug');
		return null;
	}

	public function ListObserver()
	{
		// Keep table descriptions readable.
		$this->fields['plugin_description']['readParms'] = 'expand=1&truncate=200&bb=1';
		$this->setPlugData();
		parent::ListObserver();
	}

	public function ListAjaxObserver()
	{
		parent::ListAjaxObserver();
		$this->setPlugData();
	}

	/**
	 * @return github_marketplace|null
	 */
	public function getMarketplace()
	{
		if (null === $this->mp)
		{
			e107_require_once(e_PLUGIN . 'githubSync/github_marketplace.php');
			$this->mp = new github_marketplace();
		}

		return $this->mp;
	}

	/**
	 * Registry list, memoised for the request so the list view and renderHelp()
	 * share a single fetch.
	 */
	protected function getRegistryData()
	{
		if (null === $this->_registry)
		{
			$this->_registry = $this->getMarketplace()->getRegistryList($this->marketType);
		}

		return $this->_registry;
	}

	private function compatibilityLabel($val = '')
	{
		$badge = (vartrue($val) > 1.9) ? "<span class='label label-warning'>" . EPL_ADLAN_88 . "</span>" : '1.x';
		return $badge;
	}

	private function truncateSentence($string, $limit = 120)
	{
		if (strlen($string) <= $limit)
		{
			return $string;
		}

		$tmp   = explode(".", $string);
		$chars = 0;
		$arr   = array();

		foreach ($tmp as $line)
		{
			$line = str_replace("\n", '', trim($line));
			$len  = strlen($line);

			if ($chars >= $limit)
			{
				break;
			}

			$arr[] = $line;
			$chars += $len;
		}

		$text = implode('. ', $arr) . '.';
		$text = nl2br($text);

		return $text;
	}

	private function setPlugData()
	{
		$mp = $this->getMarketplace();

		// do the request, retrieve and parse data
		$xdata = $this->getRegistryData();

		// Installed versions (folder => version) for the Installed version column.
		$installedList = e107::getPlug()->getInstalled();

		// Core plugin folder list — used to derive the Type badge (core/fork/external).
		$coreList = e107::getPlug()->getCorePluginList();

		$total = (int) $xdata['params']['count'];

		$tree = $this->getTreeModel();
		$tree->setTotal($total);
		$tp = e107::getParser();

		// The registry holds the FULL catalog (renderHelp() needs all of it to spot
		// upgrades). setTotal() above drives the pager, but only the current page's
		// rows may become tree nodes — otherwise every page renders the same set.
		$from     = (int) $this->getQuery('from', 0);
		$perPage  = (int) $this->getPerPage();
		$pageData = ($perPage > 0) ? array_slice($xdata['data'], $from, $perPage, true) : $xdata['data'];

		foreach ($pageData as $id => $row)
		{
			$v['id'] = $id;

			$model = new e_model($v);
			$tree->setNode($id, $model);

			$featured = ($row['featured'] == 1) ? " <span class='label label-info'>" . EPL_ADLAN_91 . "</span>" : '';
			$price    = (!empty($row['price'])) ? "<span class='label label-primary'>" . $row['price'] . " " . $row['currency'] . "</span>" : "<span class='label label-success'>" . EPL_ADLAN_93 . "</span>";

			// Version cell = repo (plugin.xml) version + release date on a small
			// second line (the separate Released column was removed).
			$verDisplay = htmlspecialchars((string) $row['version'], ENT_QUOTES, 'utf-8');
			if (!empty($row['date']))
			{
				// toDate('relative') already returns safe e107 livestamp markup, do NOT escape it.
				$verDisplay .= "<br><small class='text-muted'>" . $tp->toDate(strtotime($row['date']), 'relative') . "</small>";
			}

			$nameDisplay = htmlspecialchars(stripslashes((string) $row['name']), ENT_QUOTES, 'utf-8');
			$author      = trim((string) vartrue($row['author']));
			if ($author !== '')
			{
				$nameDisplay .= "<br><small class='text-muted'>" . htmlspecialchars($author, ENT_QUOTES, 'utf-8') . "</small>";
			}

			// Path cell = folder + a Type badge: core / fork / external.
			$pOrg        = (string) vartrue($row['params']['organization']);
			$isCore      = in_array($row['folder'], $coreList, true);
			$pType       = !$isCore ? 'external' : (strtolower($pOrg) === 'e107inc' ? 'core' : 'fork');
			$pClass      = array('core' => 'label-success', 'fork' => 'label-warning', 'external' => 'label-default');
			$pathDisplay = htmlspecialchars((string) $row['folder'], ENT_QUOTES, 'utf-8')
				. "<br><small><span class='label " . $pClass[$pType] . "'>" . $pType . "</span></small>";

			$node = array(
				'plugin_id'           => $row['folder'],          // folder used as unique ID
				'plugin_mode'         => $row['params']['mode'],  // 'github'
				'plugin_organization' => $row['params']['organization'],
				'plugin_repo'         => $row['params']['repo'],
				'plugin_branch'       => $row['params']['branch'],
				'plugin_icon'         => vartrue($row['icon'], e_IMAGE . "logo_template.png"),
				'plugin_name'         => $nameDisplay,
				'plugin_description'  => $this->truncateSentence(vartrue($row['description'])),
				'plugin_featured'     => $featured,
				'plugin_sef'          => '',
				'plugin_folder'       => $row['folder'],
				'plugin_path'         => $pathDisplay,
				'plugin_date'         => $tp->toDate(strtotime($row['date']), 'relative'),
				'plugin_category'     => vartrue($row['category'], 'n/a'),
				'plugin_author'       => htmlspecialchars((string) vartrue($row['author']), ENT_QUOTES, 'utf-8'),
				'plugin_version'      => $verDisplay,
				'plugin_compatible'   => $row['compatibility'],
				'plugin_website'      => vartrue($row['authorUrl']),
				'plugin_url'          => $row['urlView'],
				'plugin_notes'        => '',
				'plugin_price'        => $row['price'],
				'plugin_license'      => $price,
				'plugin_installflag'  => (isset($installedList[$row['folder']]) && $installedList[$row['folder']] !== '')
					? htmlspecialchars((string) $installedList[$row['folder']], ENT_QUOTES, 'utf-8')
					: "<small class='text-muted'>&mdash;</small>",
				'options'             => $row, // raw registry row -> consumed by the form options()
			);

			$model->setData($node);
		}
	}
}


class github_online_form_ui extends e_admin_form_ui
{
	function plugin_name($curVal, $mode)
	{
		$frm = e107::getForm();

		switch ($mode)
		{
			case 'read':
				return $curVal;
			break;

			case 'write':
				return $frm->text('plugin_name', $curVal, 255, 'size=large');
			break;

			case 'filter':
			case 'batch':
				return array();
			break;
		}
	}

	function plugin_addons($curVal, $mode)
	{
		$frm = e107::getForm();

		switch ($mode)
		{
			case 'read':
				return $curVal;
			break;

			case 'write':
				return $frm->text('plugin_addons', $curVal, 255, 'size=large');
			break;

			case 'filter':
			case 'batch':
				return array();
			break;
		}
	}

	function plugin_compatible($curVal, $mode)
	{
		$frm = e107::getForm();

		switch ($mode)
		{
			case 'read':
				if (intval($curVal) > 1)
				{
					return "<span class='label label-warning'>" . $curVal . "</span>";
				}
				return $curVal;
			break;

			case 'write':
				return $frm->text('plugin_name', $curVal, 255, 'size=large');
			break;

			case 'filter':
			case 'batch':
				return array();
			break;
		}
	}

	function plugin_icon($curVal, $mode)
	{
		$curVal = (string) $curVal;

		// A remote screenshot/icon is a URL — render it as an image. toIcon()
		// would otherwise treat a URL with no image extension (e.g. a folder URL)
		// as a glyph name and emit a broken "glyphicon-https://..." class.
		if (preg_match('#^https?://#i', $curVal))
		{
			return "<img src='" . e107::getParser()->toAttribute($curVal) . "' alt='' class='plugin-icon-lg' />";
		}

		return e107::getParser()->toIcon($curVal);
	}

	function options($bla, $data)
	{
		$tp     = e107::getParser();
		// Build self-URLs for the current dispatcher mode ('online' or 'onlinethemes').
		$mode   = e107::getParser()->filter(vartrue($_GET['mode'], 'online'), 'str');
		$folder = isset($data['folder'])         ? $data['folder']         : '';
		$params = isset($data['params'])         ? $data['params']         : array();
		$org    = isset($params['organization']) ? $params['organization'] : '';
		$repo   = isset($params['repo'])         ? $params['repo']         : '';
		$branch = isset($params['branch'])       ? $params['branch']       : 'main';

		// Upgrade available? Online (repo) version newer than installed version.
		$installedList = e107::getPlug()->getInstalled();
		$instVer   = isset($installedList[$folder]) ? (string) $installedList[$folder] : '';
		$onlineVer = (string) (isset($data['version']) ? $data['version'] : '');
		$upgrade   = ($instVer !== '' && $onlineVer !== '' && version_compare($instVer, $onlineVer, '<'));

		// Info buttons (GitHub repo + info URL) - ALWAYS shown, incl. installed.
		$infoButtons = '';

		if (!empty($org) && !empty($repo))
		{
			$repoUrl = 'https://github.com/' . rawurlencode($org)
				. '/' . rawurlencode($repo)
				. '/tree/' . rawurlencode($branch);

			$infoButtons .= ' <a class="btn btn-sm btn-default btn-secondary" '
				. 'href="' . $tp->toAttribute($repoUrl) . '" target="_blank" rel="noopener" '
				. 'title="View repository on GitHub">'
				. $tp->toGlyph('fa-github') . '</a>';
		}

		if (!empty($data['urlView']))
		{
			$infoButtons .= ' <a class="btn btn-sm btn-info" '
				. 'href="' . $tp->toAttribute($data['urlView']) . '" target="_blank" rel="noopener" '
				. 'title="More information">'
				. $tp->toGlyph('fa-external-link') . '</a>';
		}

		// Download button - download ONLY (no install). For every entry,
		// including installed plugins. Warning colour when an upgrade exists.
		$downloadButton = '';

		if (!empty($org) && !empty($repo) && !empty($folder))
		{
			$dlSrc = array(
				'mode'         => $mode,
				'action'       => 'download',
				'install'      => 0,
				'organization' => $org,
				'repo'         => $repo,
				'branch'       => $branch,
				'folder'       => $folder,
				'e-token'      => defset('e_TOKEN'),
			);
			$dlUrl     = e_SELF . '?' . http_build_query($dlSrc);
			$dlCaption = LAN_DOWNLOAD . ' ' . $data['name'] . ' ' . $data['version'];
			$dlClass   = $upgrade ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-default btn-secondary';
			$dlTitle   = $upgrade ? 'Update available - download v' . $onlineVer : LAN_DOWNLOAD;

			$downloadButton = ' <a title="' . $tp->toAttribute($dlTitle) . '" class="e-modal ' . $dlClass . '" '
				. 'href="' . $dlUrl . '" rel="external" '
				. 'data-loading="' . e_IMAGE . '/generic/loading_32.gif" data-cache="false" '
				. 'data-modal-caption="' . $tp->toAttribute($dlCaption) . '" target="_blank">' . $tp->toGlyph('fa-download') . '</a>';
		}

		// Installed - green status icon + download (re-download) + info buttons.
		if (e107::isInstalled($folder))
		{
			$status = '<button type="button" class="btn btn-sm btn-success" disabled title="' . $tp->toAttribute(LAN_INSTALLED) . '">' . $tp->toGlyph('fa-check') . '</button>';

			return $status . $downloadButton . $infoButtons;
		}

		// Compatibility check - flag incompatible plugins with the warning colour.
		$compatWarning = false;
		$version       = $tp->filter(e_VERSION, 'version');
		$compat        = (float) $tp->filter($data['compatibility'], 'version');

		if ($compat == 2)
		{
			$compat = $version;
		}

		if (!e107::isCompatible($compat, 'plugin'))
		{
			$compatWarning = true;
		}

		// Not installable (remote plugin.xml missing/invalid) - disabled grey Install.
		if (isset($data['installable']) && $data['installable'] === false)
		{
			$errAttr = $tp->toAttribute(isset($data['install_error']) ? $data['install_error'] : '');

			$installButton = '<button type="button" class="btn btn-sm btn-default btn-secondary" disabled title="' . $errAttr . '">' . $tp->toGlyph('fa-bolt') . '</button>';

			return $installButton . $downloadButton . $infoButtons;
		}

		// Installable & not installed - grey Install (yellow if maybe incompatible).
		$modalCaption = (!empty($data['price']))
			? EPL_ADLAN_92 . ' ' . $data['name'] . ' ' . $data['version']
			: EPL_ADLAN_230 . ' ' . $data['name'] . ' ' . $data['version'];

		$srcData = array(
			'mode'         => $mode,
			'action'       => 'download',
			'install'      => 1,
			'organization' => $org,
			'repo'         => $repo,
			'branch'       => $branch,
			'folder'       => $folder,
			'e-token'      => defset('e_TOKEN'),
		);
		$url = e_SELF . '?' . http_build_query($srcData);

		$title    = $compatWarning ? 'Install: May not be compatible' : EPL_ADLAN_237;
		$btnClass = $compatWarning ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-default btn-secondary';

		$installButton = '<a title="' . $tp->toAttribute($title) . '" class="e-modal ' . $btnClass . '" '
			. 'href="' . $url . '" rel="external" '
			. 'data-loading="' . e_IMAGE . '/generic/loading_32.gif" data-cache="false" '
			. 'data-modal-caption="' . $tp->toAttribute($modalCaption) . '" target="_blank">' . $tp->toGlyph('fa-bolt') . '</a>';

		return $installButton . $downloadButton . $infoButtons;
	}
}
