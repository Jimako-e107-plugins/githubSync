<?php

/**
 * github_sources_handler — shared "Find Plugin/Theme Sources" admin UI.
 *
 * One screen per market type, both built from the same base (analogous to the
 * Find Plugins/Themes online handler):
 *   - admin/admin_sources.php       -> github_sources_ui        (plugin, pref 'find_sources')
 *   - admin/admin_themesources.php  -> github_themesources_ui   (theme,  pref 'find_theme_sources')
 *
 * Each screen manages one plugin preference (a list of catalog sources) using the
 * native e107 admin-UI prefs mechanism: a type='method' field rendered by the form
 * class, with the core handling Save (PrefsSaveTrigger), CSRF and storage.
 * beforePrefsSave() validates + reconciles; PrefsObserver() handles the
 * "Refresh folder catalogs" button.
 *
 * Two kinds of rows:
 *   * builtin — XML files found in sources/plugins|themes/ (imported disabled,
 *               location read-only; only the Enabled toggle is editable),
 *   * remote  — https catalog URLs the admin adds by hand.
 *
 * Read side (github_marketplace): github_sync_sources::getEnabled($type).
 *
 * @package githubSync
 */

if (!defined('e107_INIT'))
{
	exit;
}


class github_sources_ui extends e_admin_ui
{
	protected $pluginTitle = 'Github Sync';
	protected $pluginName  = 'githubSync';
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
	 * @param array $stored current pref value
	 * @param array $posted posted rows (or empty, e.g. on Refresh)
	 * @return array
	 */
	private function reconcile($stored, $posted)
	{
		$stored = is_array($stored) ? $stored : array();
		$posted = is_array($posted) ? $posted : array();

		// Enabled state keyed by URL — prefer posted, fall back to stored.
		$enabledByUrl = array();
		foreach ($stored as $row)
		{
			if (!empty($row['url']))
			{
				$enabledByUrl[$row['url']] = !empty($row['enabled']) ? 1 : 0;
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

			if (!empty($row['builtin']))
			{
				$enabledByUrl[$url] = !empty($row['enabled']) ? 1 : 0;
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
				'label'   => ($label !== '') ? $label : $url,
				'url'     => $url,
				'enabled' => !empty($row['enabled']) ? 1 : 0,
				'builtin' => 0,
			);
		}

		// Builtin rows — authoritative location from the scan for THIS type.
		$builtin = array();
		foreach (github_sync_sources::getFolderSources($this->marketType) as $f)
		{
			$url       = $f['url'];
			$builtin[] = array(
				'label'   => $f['label'],
				'url'     => $url,
				'type'    => $f['type'],
				'enabled' => isset($enabledByUrl[$url]) ? $enabledByUrl[$url] : 0,
				'builtin' => 1,
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


// Theme variant — same screen, different pref key + folder type.
class github_themesources_ui extends github_sources_ui
{
	protected $marketType = 'theme';

	protected $prefs = array(
		'find_theme_sources' => array(
			'title'      => 'Find Theme Sources',
			'tab'        => 0,
			'type'       => 'method',
			'data'       => 'array',
			'writeParms' => array('nolabel' => 1),
		),
	);
}


class github_sources_form_ui extends e_admin_form_ui
{
	// e107 calls the form method named after the pref key; both delegate to the
	// shared renderer, passing their own field-name prefix.
	public function find_sources($curVal, $mode)
	{
		return $this->renderSourceTable($curVal, $mode, 'find_sources');
	}

	public function find_theme_sources($curVal, $mode)
	{
		return $this->renderSourceTable($curVal, $mode, 'find_theme_sources');
	}

	/**
	 * Renders the editable source list. Field names use $prefKey so the posted
	 * data lands under the right preference.
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
			$url     = $s['url'] ?? '';
			$enabled = !empty($s['enabled']);

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
}
