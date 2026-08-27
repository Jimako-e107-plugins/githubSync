<?php

// e107 Plugin Admin Area — findPlugins (mode: main) — "Find Plugins Sources".
// Self-contained entry script: the sources UI classes (github_sources_ui /
// github_sources_form_ui) live inline below. Manages the 'find_sources'
// preference (plugin catalogs: findPlugins/sources/plugins/*.xml + remote URLs).
// Depends on githubSync for the shared includes (engine/marketplace/sources).

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

// findPlugins relies on githubSync for its shared includes; bail out cleanly
// if the dependency is not installed.
if (!e107::isInstalled('githubSync'))
{
	e107::getMessage()->addError('githubSync plugin is required.');
	e107::redirect(e_ADMIN . 'admin.php');
	exit;
}

e107_require_once('admin_menu.php');                                 // shared dispatcher
e107_require_once(e_PLUGIN . 'githubSync/includes/github_sync_sources.php');  // folder scan + read accessor


class github_sources_ui extends e_admin_ui
{
	protected $pluginTitle = 'Find Plugins';
	protected $pluginName  = 'findPlugins';
	protected $table       = ''; // prefs only — no table
	protected $pid         = '';

	// Overridden by the theme subclass.
	protected $marketType  = 'plugin';

	protected $prefs = array(
		'find_sources' => array(
			'title'      => 'Find Plugins Sources',
			'tab'        => 0,
			'type'       => 'method',
			'data'       => 'array',
			'writeParms' => array('nolabel' => 1),
		),
	);

	// Pref key for the current market type (matches the $prefs key + form method name).
	protected function prefKey()
	{
		return ($this->marketType === 'theme') ? 'find_theme_sources' : 'find_sources';
	}

	public function init()
	{
	}

	/**
	 * Handle the "Refresh folder catalogs" button before the page renders.
	 * Plain submit (not etrigger_save), so the core prefs save does not run;
	 * we only rescan the folder and persist the reconciled list.
	 */
	public function PrefsObserver()
	{
		$this->addTitle();

		if ($this->getPosted('refresh_sources'))
		{
			$this->refreshFolderSources();
		}
	}

	private function refreshFolderSources()
	{
		if (!e107::getSession()->checkFormToken($this->getPosted('e-token', '')))
		{
			e107::getMessage()->addError('Invalid security token.');
			return;
		}

		// Pass the stored rows as the "posted" set too, so manually-added remote
		// rows are preserved; only the builtin rows are rebuilt from the scan.
		$key        = $this->prefKey();
		$stored     = $this->getConfig()->get($key, array());
		$reconciled = $this->reconcile($stored, $stored);
		$this->getConfig()->set($key, $reconciled)->save(false);

		e107::getMessage()->addSuccess('Folder catalogs refreshed.');
	}

	/**
	 * Validate + reconcile the rows before the core saves them. The admin's
	 * Enabled choice is taken from the posted rows; builtin locations are NOT
	 * trusted from POST — they are rebuilt from the folder scan.
	 */
	public function beforePrefsSave($new_data, $old_data)
	{
		$key = $this->prefKey();

		if (isset($new_data[$key]) && is_array($new_data[$key]))
		{
			$new_data[$key] = $this->reconcile($old_data[$key] ?? array(), $new_data[$key]);
		}

		return $new_data;
	}

	/**
	 * Build the stored list = builtin rows (from the trusted folder scan for this
	 * market type) + validated remote rows.
	 *
	 * Preserves the 'excluded' array per source row keyed by URL, so it survives
	 * both Save and Refresh (builtin rows are rebuilt from the scan, which loses
	 * unknown keys — we carry excluded across explicitly).
	 *
	 * @param array $stored current pref value
	 * @param array $posted posted rows (or empty, e.g. on Refresh)
	 * @return array
	 */
	private function reconcile($stored, $posted)
	{
		$stored = is_array($stored) ? $stored : array();
		$posted = is_array($posted) ? $posted : array();

		// Enabled state and excluded list keyed by URL — prefer posted, fall back to stored.
		$enabledByUrl  = array();
		$excludedByUrl = array();

		foreach ($stored as $row)
		{
			if (!empty($row['url']))
			{
				$url                  = $row['url'];
				$enabledByUrl[$url]   = !empty($row['enabled']) ? 1 : 0;
				$excludedByUrl[$url]  = isset($row['excluded']) && is_array($row['excluded'])
					? $row['excluded']
					: array();
			}
		}

		$remote = array();
		foreach ($posted as $row)
		{
			$url = trim($row['url'] ?? '');
			if ($url === '')
			{
				continue; // empty / removed row
			}

			// Excluded checkboxes posted as array of 'org/repo/folder' strings.
			$postedExcluded = isset($row['excluded']) && is_array($row['excluded'])
				? array_values(array_filter(array_map('strval', $row['excluded'])))
				: array();

			if (!empty($row['builtin']))
			{
				$enabledByUrl[$url]  = !empty($row['enabled']) ? 1 : 0;
				$excludedByUrl[$url] = $postedExcluded;
				continue; // location rebuilt from scan below
			}

			if (!$this->isValidLocation($url))
			{
				e107::getMessage()->addError('Please enter a valid catalog URL (http/https): '
					. e107::getParser()->toHTML($url, false, 'defs'));
				continue;
			}

			$label    = trim($row['label'] ?? '');
			$remote[] = array(
				'label'    => ($label !== '') ? $label : $url,
				'url'      => $url,
				'enabled'  => !empty($row['enabled']) ? 1 : 0,
				'builtin'  => 0,
				'excluded' => $postedExcluded,
			);
		}

		// Builtin rows — authoritative location from the scan for THIS type.
		$builtin = array();
		foreach (github_sync_sources::getFolderSources($this->marketType) as $f)
		{
			$url       = $f['url'];
			$builtin[] = array(
				'label'    => $f['label'],
				'url'      => $url,
				'type'     => $f['type'],
				'enabled'  => isset($enabledByUrl[$url]) ? $enabledByUrl[$url] : 0,
				'builtin'  => 1,
				'excluded' => isset($excludedByUrl[$url]) ? $excludedByUrl[$url] : array(),
			);
		}

		return array_merge($builtin, $remote);
	}

	public function renderHelp()
	{
		$isTheme = ($this->marketType === 'theme');
		$kind    = $isTheme ? 'themes'      : 'plugins';
		$finder  = $isTheme ? 'Find Themes' : 'Find Plugins';
		$sub     = $isTheme ? 'sources/themes/' : 'sources/plugins/';

		$text  = 'Sources are catalogs of available ' . $kind . ' that ' . $finder . ' reads.';
		$text .= '<br><br><strong>Folder catalogs</strong> — XML files in the plugin\'s '
			. '<code>' . $sub . '</code> folder. Press <strong>Refresh folder catalogs</strong> to '
			. 'import them (they appear as <em>disabled</em> rows; run this once after install and '
			. 'again after adding files). Then tick <strong>Enabled</strong> on the ones you want and '
			. 'press <strong>Save</strong>.';
		$text .= '<br><br><strong>Remote catalogs</strong>: paste the catalog\'s https URL in the '
			. 'empty row. For a catalog on GitHub, the file\'s normal page URL '
			. '(<code>github.com/&hellip;/blob/&hellip;</code>) works — it is fetched as raw content.';
		$text .= '<br><br>The list is stored in plugin preferences, so a sync never overwrites it.';
		$text .= '<br><br><strong>Excluded plugins</strong>: expand a source row to see its plugin '
			. 'list. Check any plugin to hide it in Find Plugins for that source only.';

		return array(
			'caption' => LAN_HELP,
			'text'    => $text,
		);
	}

	private function isValidLocation($loc)
	{
		// Remote rows only — local catalogs come from the folder scan, not here.
		if ($loc === '')
		{
			return false;
		}
		if (!preg_match('#^https?://#i', $loc))
		{
			return false;
		}
		return (bool) filter_var($loc, FILTER_VALIDATE_URL);
	}
}


class github_sources_form_ui extends e_admin_form_ui
{
	// e107 calls the form method named after the pref key; both delegate to the
	// shared renderer, passing their own field-name prefix.
	public function find_sources($curVal, $mode)
	{
		return $this->renderSourceTable($curVal, $mode, 'find_sources');
	}

	/**
	 * Renders the editable source list. Field names use $prefKey so the posted
	 * data lands under the right preference.
	 *
	 * Each source row is followed by its plugin checklist (always visible),
	 * loaded from the catalog XML. Checked plugins are excluded from Find Plugins.
	 *
	 * @param mixed  $curVal  current pref value (array of rows)
	 * @param string $mode    'read' | 'write'
	 * @param string $prefKey 'find_sources' | 'find_theme_sources'
	 * @return string|null
	 */
	protected function renderSourceTable($curVal, $mode, $prefKey)
	{
		if ($mode !== 'write')
		{
			$count = is_array($curVal) ? count($curVal) : 0;
			return $count . ' source(s)';
		}

		$rows = is_array($curVal) ? array_values($curVal) : array();

		$text  = '<table class="table table-striped table-bordered">';
		$text .= '<thead><tr>';
		$text .= '<th style="width:25%">Label</th>';
		$text .= '<th>Catalog</th>';
		$text .= '<th style="width:80px">Type</th>';
		$text .= '<th class="center" style="width:80px">Enabled</th>';
		$text .= '</tr></thead><tbody>';

		$i = 0;
		foreach ($rows as $s)
		{
			$url      = $s['url'] ?? '';
			$enabled  = !empty($s['enabled']);
			$excluded = isset($s['excluded']) && is_array($s['excluded']) ? $s['excluded'] : array();
			$excLookup = array_flip($excluded); // fast isset() check

			$text .= '<tr>';

			if (!empty($s['builtin']))
			{
				// Shipped file: read-only location, only the Enabled toggle is editable.
				$text .= '<td>' . htmlspecialchars($s['label'] ?? $url) . '</td>';
				$text .= '<td><small class="text-muted">' . htmlspecialchars($url) . '</small>'
					. $this->hidden("{$prefKey}[{$i}][url]", $url)
					. $this->hidden("{$prefKey}[{$i}][builtin]", 1) . '</td>';
				$text .= '<td>' . htmlspecialchars($s['type'] ?? '') . '</td>';
			}
			else
			{
				$text .= '<td>' . $this->text("{$prefKey}[{$i}][label]", $s['label'] ?? '', 100) . '</td>';
				$text .= '<td>' . $this->text("{$prefKey}[{$i}][url]", $url, 255, array('size' => 'block-level')) . '</td>';
				$text .= '<td><small class="text-muted">remote</small></td>';
			}

			$text .= '<td class="center">' . $this->checkbox("{$prefKey}[{$i}][enabled]", 1, $enabled) . '</td>';
			$text .= '</tr>';

			// Plugin list for this source — always visible.
			if ($url !== '')
			{
				$plugins = $this->loadSourcePlugins($url);

				if (!empty($plugins))
				{
					$text .= '<tr>';
					$text .= '<td colspan="4" style="padding:8px 16px">';
					$text .= '<div class="row">';

					$col = 0;
					foreach ($plugins as $p)
					{
						$exKey   = $p['org'] . '/' . $p['repo'] . '/' . $p['folder'];
						$checked = isset($excLookup[$exKey]);

						if ($col % 3 === 0 && $col > 0)
						{
							$text .= '</div><div class="row" style="margin-top:4px">';
						}

						// Three columns: col-sm-4 each.
						// The checkbox field name is an array — posted as
						// find_sources[i][excluded][] = 'org/repo/folder'
						$text .= '<div class="col-sm-4" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">';
						$text .= '<label style="font-weight:normal;cursor:pointer" title="' . htmlspecialchars($exKey) . '">';
						$text .= '<input type="checkbox"'
							. ' name="' . htmlspecialchars("{$prefKey}[{$i}][excluded][]") . '"'
							. ' value="' . htmlspecialchars($exKey) . '"'
							. ($checked ? ' checked="checked"' : '')
							. ' style="margin-right:4px">';
						$text .= htmlspecialchars($p['name'] ?: $p['folder']);
						$text .= '</label>';
						$text .= '</div>';

						$col++;
					}

					$text .= '</div>'; // .row
					$text .= '</td></tr>';
				}
			}

			$i++;
		}

		// One blank remote row (disabled by default).
		$text .= '<tr>';
		$text .= '<td>' . $this->text("{$prefKey}[{$i}][label]", '', 100) . '</td>';
		$text .= '<td>' . $this->text("{$prefKey}[{$i}][url]", '', 255, array('size' => 'block-level')) . '</td>';
		$text .= '<td><small class="text-muted">remote</small></td>';
		$text .= '<td class="center">' . $this->checkbox("{$prefKey}[{$i}][enabled]", 1, false) . '</td>';
		$text .= '</tr>';

		$text .= '</tbody></table>';

		// Rescan the sources/ folder. Plain submit (not etrigger_save) — handled
		// in github_sources_ui::PrefsObserver(), so the prefs Save does not run.
		$text .= '<div class="buttons-bar left">'
			. $this->admin_button('refresh_sources', 1, 'other', 'Refresh folder catalogs')
			. '</div>';

		return $text;
	}

	/**
	 * Load the plugin/theme list from one catalog source.
	 * Returns array of ['folder', 'org', 'repo', 'name'] per entry.
	 * Returns empty array on failure (source unreachable, not yet enabled, etc.).
	 *
	 * This is called at render time, so results are NOT cached between page loads.
	 * The catalog file is small (local XML) or remote (may be slow on first load);
	 * caching can be added later if needed.
	 *
	 * @param string $url source URL or local path
	 * @return array
	 */
	private function loadSourcePlugins($url)
	{
		if ($url === '')
		{
			return array();
		}

		e107_require_once(e_PLUGIN . 'githubSync/includes/github_marketplace.php');

		$mp  = new github_marketplace();
		$xml = e107::getXml();

		// Load the catalog — mirror the logic in github_marketplace::loadCatalog()
		// but without fetching remote plugin.xml per entry (we only need folder/org/repo/name).
		if (preg_match('#^https?://#i', $url))
		{
			// Normalize GitHub blob URL to raw.
			if (preg_match('#^https?://(?:www\.)?github\.com/([^/]+)/([^/]+)/blob/(.+)$#i', $url, $m))
			{
				$url = 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $m[3];
			}
			$url  = preg_replace('/\?raw=(?:1|true)$/i', '', $url);
			$raw  = $xml->getRemoteFile($url);
			if (empty($raw))
			{
				return array();
			}
			$data = $xml->parseXml($raw, false);
		}
		else
		{
			$path = e107::getParser()->replaceConstants($url);
			if (!is_readable($path))
			{
				return array();
			}
			$data = $xml->loadXMLfile($path, 'advanced');
		}

		if (empty($data) || !is_array($data))
		{
			return array();
		}

		// Detect type from data keys.
		$type  = isset($data['plugin']) ? 'plugin' : (isset($data['theme']) ? 'theme' : '');
		if ($type === '')
		{
			return array();
		}

		$nodes = $data[$type];

		// Single entry — xmlClass returns assoc instead of array of assoc.
		if (isset($nodes['@attributes']))
		{
			$nodes = array($nodes);
		}

		$plugins = array();
		foreach ((array) $nodes as $node)
		{
			$attr   = isset($node['@attributes']) ? $node['@attributes'] : array();
			$folder = isset($attr['folder'])       ? trim($attr['folder'])       : '';
			$org    = isset($attr['organization']) ? trim($attr['organization']) : '';
			$repo   = isset($attr['repo'])         ? trim($attr['repo'])         : '';
			$name   = isset($attr['name'])         ? trim($attr['name'])         : '';

			if ($folder === '' || $org === '' || $repo === '')
			{
				continue;
			}

			$plugins[] = array(
				'folder' => $folder,
				'org'    => $org,
				'repo'   => $repo,
				'name'   => $name,
			);
		}

		// Sort by name / folder for consistent display.
		usort($plugins, function($a, $b) {
			$an = $a['name'] ?: $a['folder'];
			$bn = $b['name'] ?: $b['folder'];
			return strcasecmp($an, $bn);
		});

		return $plugins;
	}
}

new findPlugins_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
