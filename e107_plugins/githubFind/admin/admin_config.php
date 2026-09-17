<?php

// e107 Plugin Admin Area — githubFind (mode: main) — "Find Plugins Sources".
// Self-contained entry script: the sources UI classes (github_sources_ui /
// github_sources_form_ui) live inline below. Manages the 'find_sources'
// preference (plugin catalogs: githubFind/sources/plugins/*.xml + remote URLs).
// All includes (engine/marketplace/sources) live in githubFind/includes/.

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php');                                 // shared dispatcher
e107_require_once(e_PLUGIN . 'githubFind/includes/github_sync_sources.php');  // folder scan + read accessor


class github_sources_ui extends e_admin_ui
{
	protected $pluginTitle = 'GitHub Find';
	protected $pluginName  = 'githubFind';
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
	 * Build the stored list = builtin rows (from the trusted folder scan) +
	 * validated remote rows.
	 *
	 * The form posts the SHOWN selection per row (ticked = shown in the Find
	 * browser); it is converted back to the stored opt-out 'excluded' list here
	 * (excluded = catalog − ticked), so a new catalog entry keeps showing up
	 * until somebody unticks it. 'excluded' is carried across explicitly per
	 * URL, because builtin rows are rebuilt from the scan and would lose it.
	 *
	 * @param array $stored current pref value
	 * @param array $posted posted rows (or empty, e.g. on Refresh)
	 * @return array
	 */
	private function reconcile($stored, $posted)
	{
		$stored = is_array($stored) ? $stored : array();
		$posted = is_array($posted) ? $posted : array();
		$tp     = e107::getParser();

		// Enabled state keyed by URL — prefer posted, fall back to stored.
		// Stored excluded list keyed by URL — the fallback for rows whose
		// checklist was not posted.
		$enabledByUrl  = array();
		$excludedByUrl = array();

		// Ticked ("shown") keys keyed by URL, only for rows whose checklist was
		// part of this submission (hidden 'shown_posted' marker). A row with
		// nothing ticked posts no 'shown' array at all, so the marker is what
		// tells an intentionally cleared row apart from a row never rendered.
		$shownByUrl = array();

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

			if (!empty($row['shown_posted']))
			{
				// Shown checkboxes posted as array of 'org/repo/folder' strings.
				// Matched against the source's catalog in excludedFor() — posted
				// keys are never stored as they are.
				$postedShown = isset($row['shown']) && is_array($row['shown'])
					? $row['shown']
					: array();

				$shownByUrl[$url] = array_values(array_filter(array_map(function($v) use ($tp)
				{
					return $tp->toDB((string) $v);
				}, $postedShown)));
			}

			if (!empty($row['builtin']))
			{
				$enabledByUrl[$url] = !empty($row['enabled']) ? 1 : 0;
				continue; // location + excluded list resolved from the scan below
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
				'excluded' => $this->excludedFor($url, $shownByUrl, $excludedByUrl),
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
				'excluded' => $this->excludedFor($url, $shownByUrl, $excludedByUrl),
			);
		}

		return array_merge($builtin, $remote);
	}

	/**
	 * Excluded list for one source: the posted selection converted back to
	 * exclusions (catalog − ticked), or the stored list when the row's checklist
	 * was not part of this submission. SECURITY: only keys present in the
	 * catalog loaded from the source are ever stored.
	 *
	 * @param string $url           builtin path (from the scan) or validated remote URL
	 * @param array  $shownByUrl    posted ticked keys keyed by URL
	 * @param array  $excludedByUrl stored excluded keys keyed by URL
	 * @return array
	 */
	private function excludedFor($url, $shownByUrl, $excludedByUrl)
	{
		$stored = isset($excludedByUrl[$url]) ? $excludedByUrl[$url] : array();

		if (!isset($shownByUrl[$url]))
		{
			return $stored; // checklist not posted for this row — keep what is stored
		}

		// Same loader as the form, so both sides work from the same catalog set.
		$catalog = github_sources_form_ui::loadSourcePlugins($url);
		if (empty($catalog))
		{
			return $stored; // catalog unreadable right now — do not wipe the stored list
		}

		$ticked   = array_flip($shownByUrl[$url]);
		$excluded = array();
		foreach ($catalog as $p)
		{
			$exKey = $p['org'] . '/' . $p['repo'] . '/' . $p['folder'];
			if (!isset($ticked[$exKey]))
			{
				$excluded[] = $exKey;
			}
		}

		return $excluded;
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
		$text .= '<br><br><strong>Shown in ' . $finder . '</strong>: every source row lists the '
			. $kind . ' its catalog offers. Ticked ' . $kind . ' are shown in ' . $finder . '; untick '
			. 'one to hide it for that source only. New ' . $kind . ' added to a catalog are shown '
			. 'until you untick them. <strong>Select all</strong> / <strong>Clear all</strong> above '
			. 'the list change the whole row at once; press <strong>Save</strong> to apply.';

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
	 * data lands under the right preference. Each row is followed by its
	 * checklist: ticked = shown in the Find browser, unticked = hidden for that
	 * source only. A hidden 'shown_posted' field per row marks the checklist as
	 * part of the submission, so a row with nothing ticked can be stored too.
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

		$isTheme = ($prefKey === 'find_theme_sources');
		$kind    = $isTheme ? 'themes'      : 'plugins';
		$finder  = $isTheme ? 'Find Themes' : 'Find Plugins';

		// Helpers for the per-row Select all / Clear all buttons and the muted
		// label of unticked entries. Client-side only — scoped by the row's
		// wrapper id, so one row's buttons never touch another catalog.
		$text  = '<script>'
			. 'function gfShownToggle(cb){var l=cb.parentNode;'
			. 'if(l&&l.classList){l.classList.toggle("text-muted",!cb.checked);}}'
			. 'function gfShownSetAll(id,state){var w=document.getElementById(id);if(!w){return;}'
			. 'var b=w.querySelectorAll("input[type=checkbox]");'
			. 'for(var i=0;i<b.length;i++){b[i].checked=state;gfShownToggle(b[i]);}}'
			. '</script>';

		$text .= '<table class="table table-striped table-bordered">';
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
			$excLookup = array_flip(array_map('strval', $excluded)); // fast isset() check

			$text .= '<tr>';

			if (!empty($s['builtin']))
			{
				// Shipped file: read-only location, only the Enabled toggle is editable.
				$text .= '<td>' . htmlspecialchars($s['label'] ?? $url, ENT_QUOTES, 'utf-8') . '</td>';
				$text .= '<td><small class="text-muted">' . htmlspecialchars($url, ENT_QUOTES, 'utf-8') . '</small>'
					. $this->hidden("{$prefKey}[{$i}][url]", $url)
					. $this->hidden("{$prefKey}[{$i}][builtin]", 1) . '</td>';
				$text .= '<td>' . htmlspecialchars($s['type'] ?? '', ENT_QUOTES, 'utf-8') . '</td>';
			}
			else
			{
				$text .= '<td>' . $this->text("{$prefKey}[{$i}][label]", $s['label'] ?? '', 100) . '</td>';
				$text .= '<td>' . $this->text("{$prefKey}[{$i}][url]", $url, 255, array('size' => 'block-level')) . '</td>';
				$text .= '<td><small class="text-muted">remote</small></td>';
			}

			$text .= '<td class="center">' . $this->checkbox("{$prefKey}[{$i}][enabled]", 1, $enabled) . '</td>';
			$text .= '</tr>';

			// Checklist for this source — always visible. Ticked = shown in the
			// Find browser; unticked = hidden for this source only.
			if ($url !== '')
			{
				$plugins = self::loadSourcePlugins($url);

				if (!empty($plugins))
				{
					// Per-row wrapper id — the buttons act on this wrapper only.
					$blockId = htmlspecialchars('gf-shown-' . $prefKey . '-' . $i, ENT_QUOTES, 'utf-8');

					$text .= '<tr>';
					$text .= '<td colspan="4" style="padding:8px 16px">';
					$text .= '<div id="' . $blockId . '">';

					// Heading + Select all / Clear all. type="button" so they can
					// never submit the form.
					$text .= '<div style="margin-bottom:6px">';
					$text .= '<strong>Shown in ' . $finder . '</strong> ';
					$text .= '<button type="button" class="btn btn-default btn-sm"'
						. ' onclick="gfShownSetAll(\'' . $blockId . '\', true)">Select all</button> ';
					$text .= '<button type="button" class="btn btn-default btn-sm"'
						. ' onclick="gfShownSetAll(\'' . $blockId . '\', false)">Clear all</button>';
					$text .= '<br><small class="text-muted">Unticked ' . $kind . ' stay hidden in '
						. $finder . ' for this source only.</small>';
					$text .= '</div>';

					// Marks this row's checklist as part of the submission. A row
					// with nothing ticked posts no 'shown' array at all; without
					// this marker the save handler could not tell it from a row
					// that was never rendered and would keep the old values.
					$text .= $this->hidden("{$prefKey}[{$i}][shown_posted]", 1);

					$text .= '<div class="row">';

					$col = 0;
					foreach ($plugins as $p)
					{
						$exKey   = $p['org'] . '/' . $p['repo'] . '/' . $p['folder'];
						$checked = !isset($excLookup[$exKey]); // ticked = not excluded

						if ($col % 3 === 0 && $col > 0)
						{
							$text .= '</div><div class="row" style="margin-top:4px">';
						}

						// Three columns: col-sm-4 each.
						// The checkbox field name is an array — posted as
						// find_sources[i][shown][] = 'org/repo/folder'
						// Unticked entries get a muted label (kept in sync by JS).
						$text .= '<div class="col-sm-4" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">';
						$text .= '<label' . ($checked ? '' : ' class="text-muted"')
							. ' style="font-weight:normal;cursor:pointer"'
							. ' title="' . htmlspecialchars($exKey, ENT_QUOTES, 'utf-8') . '">';
						$text .= '<input type="checkbox"'
							. ' name="' . htmlspecialchars("{$prefKey}[{$i}][shown][]", ENT_QUOTES, 'utf-8') . '"'
							. ' value="' . htmlspecialchars($exKey, ENT_QUOTES, 'utf-8') . '"'
							. ($checked ? ' checked="checked"' : '')
							. ' onchange="gfShownToggle(this)"'
							. ' style="margin-right:4px">';
						$text .= htmlspecialchars($p['name'] ?: $p['folder'], ENT_QUOTES, 'utf-8');
						$text .= '</label>';
						$text .= '</div>';

						$col++;
					}

					$text .= '</div>'; // .row
					$text .= '</div>'; // #gf-shown-… wrapper
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
	 * Load the plugin/theme list from one catalog source, as
	 * ['folder', 'org', 'repo', 'name'] per entry; empty on failure. Shared by
	 * the render and the save handler so both work from the same catalog set.
	 * Called at render and save time, so results are not cached between loads.
	 *
	 * @param string $url source URL or local path
	 * @return array
	 */
	public static function loadSourcePlugins($url)
	{
		if ($url === '')
		{
			return array();
		}

		e107_require_once(e_PLUGIN . 'githubFind/includes/github_marketplace.php');

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

new githubFind_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
