<?php

/**
 * Plugin list source: the plugin folders present in a GitHub repo's plugins
 * directory, plus the selection a core sync should extract. One Contents API
 * call on refresh; every page load reads the stored copy.
 *
 * Row-storage copy of the githubSyncLite helper: githubSync has one row per
 * source, so the data lives in the `plugin_list` column of that row of the
 * `github_sync` table instead of a plugin preference. Every public method
 * therefore takes the row id; the merge rules and validation are unchanged,
 * and the class name is kept so the two copies stay diffable (each plugin
 * loads only its own).
 *
 * Stored format, identical in both copies:
 *
 *   {"folder": "eplugins", "list": ["banner", ...], "selected": ["news", ...]}
 *
 * 'folder' is stored with the list so a list made for one layout is never
 * served for the other. SECURITY: 'list' is the whitelist for the selection
 * — a name can only be selected when it is in the list, whatever the browser
 * posts. Refresh MERGES the new list with the old selection and reports what
 * it had to drop.
 *
 * @package githubSync
 */
class githubSyncLite_plugin_list
{
	/** Table the rows live in (no prefix). */
	const TABLE = 'github_sync';

	/** Column of the row the list + selection are stored in. */
	const COLUMN = 'plugin_list';

	/**
	 * SECURITY: the value becomes a GitHub API URL segment, so only the two
	 * known layouts are accepted. Duplicated in the sync engine on purpose —
	 * both files are standalone by design.
	 *
	 * @param mixed $value
	 * @return string  'eplugins' or 'e107_plugins'
	 */
	public static function normalizePluginsFolder($value)
	{
		return in_array($value, array('eplugins', 'e107_plugins'), true) ? $value : 'eplugins';
	}

	/**
	 * The stored data, or null when nothing is stored or it belongs to a
	 * different plugins-folder setting (caller should prompt for a refresh).
	 * Never hits the network. 'selected' is always present and always a subset
	 * of 'list'; data stored without it reads as basePlugins() ∩ list.
	 *
	 * @param int    $id             github_sync row id
	 * @param string $pluginsFolder  the row's 'plugins_folder' column
	 * @return array|null  array('folder', 'list', 'selected'), or null
	 */
	public static function getCached($id, $pluginsFolder = 'eplugins')
	{
		$pluginsFolder = self::normalizePluginsFolder($pluginsFolder);

		$raw = self::load($id);
		if (!is_string($raw) || $raw === '')
		{
			return null;
		}

		$data = json_decode($raw, true);

		// Stored format: {'folder': <plugins folder>, 'list': [...], 'selected': [...]}.
		// A list made for the other layout counts as stale — prompt for a
		// refresh instead.
		if (!is_array($data) || !isset($data['folder'], $data['list'])
			|| $data['folder'] !== $pluginsFolder || !is_array($data['list']))
		{
			return null;
		}

		$list = self::cleanNames($data['list']);

		// Data without a selection: base plugins only.
		$selected = (isset($data['selected']) && is_array($data['selected']))
			? self::cleanNames($data['selected'])
			: self::basePlugins();

		return array(
			'folder'   => $pluginsFolder,
			'list'     => $list,
			'selected' => self::mergeSelection($selected, $list),
		);
	}

	/**
	 * The stored selection, ready for the sync engine's 'plugins' param. Empty
	 * when nothing is stored — a core sync then writes nothing under the
	 * plugins directory.
	 *
	 * @param int    $id             github_sync row id
	 * @param string $pluginsFolder  the row's 'plugins_folder' column
	 * @return array
	 */
	public static function getSelected($id, $pluginsFolder = 'eplugins')
	{
		$cached = self::getCached($id, $pluginsFolder);

		return ($cached === null) ? array() : $cached['selected'];
	}

	/**
	 * Store a selection posted from the Manual Sync edit screen. SECURITY: only
	 * names present in the stored list are kept, no exceptions; the base
	 * plugins are always added. 'list' and 'folder' are untouched.
	 *
	 * @param array  $posted         raw gs_plugins[] values from the form
	 * @param int    $id             github_sync row id
	 * @param string $pluginsFolder  the row's 'plugins_folder' column
	 * @return array  the selection actually saved
	 */
	public static function saveSelection(array $posted, $id, $pluginsFolder = 'eplugins')
	{
		$cached = self::getCached($id, $pluginsFolder);
		if ($cached === null)
		{
			return array();
		}

		$selected = self::mergeSelection(self::cleanNames($posted), $cached['list']);

		self::store($id, $cached['folder'], $cached['list'], $selected);

		return $selected;
	}

	/**
	 * Fetch the list fresh from GitHub (one Contents API call) and store it in
	 * the row's column. The selection is merged, not reset:
	 *   selected = (old selected ∩ new list) ∪ (basePlugins() ∩ new list)
	 * Dropped entries are reported. The old data must still be stored when this
	 * runs — do NOT call clearCache() first.
	 *
	 * @param array $p  id, organization, repo, branch, token, public_repo, plugins_folder
	 * @return array|false
	 */
	public static function refresh(array $p)
	{
		$mes = e107::getMessage();

		$id     = (int) ($p['id'] ?? 0);
		$org    = trim((string) ($p['organization'] ?? ''));
		$repo   = trim((string) ($p['repo'] ?? ''));
		$branch = trim((string) ($p['branch'] ?? ''));
		$token  = trim((string) ($p['token'] ?? ''));
		$plugDir = self::normalizePluginsFolder($p['plugins_folder'] ?? 'eplugins');

		if ($id < 1)
		{
			$mes->addError('Save the sync entry before refreshing the plugin list.');
			return false;
		}

		if ($org === '' || $repo === '' || $branch === '')
		{
			$mes->addError('Set organization, repo and branch before refreshing the plugin list.');
			return false;
		}

		// Validate the segments the same way the sync engine does, before they
		// go into the API URL — reject anything that isn't a plain GitHub
		// path segment (blocks '/', '#', '?', '..' and other URL-altering input).
		foreach (array('organization' => $org, 'repo' => $repo, 'branch' => $branch) as $label => $seg)
		{
			if (!preg_match('/^[A-Za-z0-9._-]+$/', $seg) || strpos($seg, '..') !== false)
			{
				$mes->addError('Invalid ' . $label . ' — only letters, digits, dot, underscore and hyphen are allowed.');
				return false;
			}
		}

		// $plugDir is whitelisted above ('eplugins'/'e107_plugins' only), so it
		// is safe to place in the URL path.
		$url = 'https://api.github.com/repos/' . rawurlencode($org) . '/' . rawurlencode($repo)
			. '/contents/' . $plugDir . '?ref=' . rawurlencode($branch);

		// SSL verification stays ON in production; relaxed only under e_DEBUG
		// (local development), matching the sync engine's behaviour.
		$verifySsl = !(defined('e_DEBUG') && e_DEBUG);

		$headers = array(
			'Accept: application/vnd.github+json',
			'User-Agent: e107-githubSyncLite',
		);
		if ($token !== '')
		{
			// Authenticated calls get a much higher rate limit.
			$headers[] = 'Authorization: token ' . $token;
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => $verifySsl,
			CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
			CURLOPT_TIMEOUT        => 30,
		));
		$body     = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErr  = curl_error($ch);
		curl_close($ch);

		if ($curlErr !== '')
		{
			$mes->addError('Could not reach GitHub to refresh the plugin list.');
			return false;
		}
		if ($httpCode === 404)
		{
			$mes->addError('No ' . $plugDir . '/ folder found in ' . htmlspecialchars($org . '/' . $repo, ENT_QUOTES, 'utf-8') . ' (branch ' . htmlspecialchars($branch, ENT_QUOTES, 'utf-8') . '). Check the \'Repo plugins folder\' setting of this sync entry.');
			return false;
		}
		if ($httpCode === 403)
		{
			$mes->addError('GitHub API rate limit reached (HTTP 403). Add a token for a higher limit, or try again later.');
			return false;
		}
		if ($httpCode !== 200)
		{
			$mes->addError("GitHub API returned HTTP {$httpCode} while listing plugins.");
			return false;
		}

		$data = json_decode($body, true);
		if (!is_array($data))
		{
			$mes->addError('Unexpected response from GitHub while listing plugins.');
			return false;
		}

		// Keep only directory entries; collect their names. Names come from a
		// remote API, so validate them like any other path segment before they
		// are stored and later rendered as checkbox values / checked on disk.
		$folders = array();
		foreach ($data as $entry)
		{
			if (isset($entry['type'], $entry['name']) && $entry['type'] === 'dir'
				&& preg_match('/^[A-Za-z0-9._-]+$/', (string) $entry['name'])
				&& strpos((string) $entry['name'], '..') === false)
			{
				$folders[] = (string) $entry['name'];
			}
		}
		sort($folders, SORT_STRING);

		// Merge the previous selection (if any, and only if it was made for
		// the same plugins-folder layout) with the fresh list. Anything that
		// is no longer in the repo is dropped — and reported, never silently.
		$oldSelected = self::getSelected($id, $plugDir);
		$selected    = self::mergeSelection($oldSelected, $folders);

		$dropped = array_values(array_diff($oldSelected, $folders));
		if (!empty($dropped))
		{
			$safeDropped = array_map(static function ($name) {
				return htmlspecialchars($name, ENT_QUOTES, 'utf-8');
			}, $dropped);
			$mes->addInfo(count($dropped) . ' previously selected plugin folder(s) no longer exist in the repo and were removed from the selection: <strong>'
				. implode('</strong>, <strong>', $safeDropped) . '</strong>');
		}

		// Store in the ROW (survives every cache clear — the core sync itself
		// clears the system cache when it finishes). The plugins-folder name
		// is stored alongside the list so getCached() can reject a list made
		// for the other layout.
		self::store($id, $plugDir, $folders, $selected);

		return $folders;
	}

	/**
	 * Clear the stored data — list and selection together; they live in one
	 * column and are never split. Not used by refresh(), which needs the old
	 * selection to merge.
	 *
	 * @param int $id  github_sync row id
	 * @return void
	 */
	public static function clearCache($id)
	{
		self::write($id, '');
	}

	/**
	 * Plugins always part of the selection.
	 *
	 * @return array
	 */
	public static function basePlugins()
	{
		return array('navigation', 'news', 'page', 'siteinfo', 'tinymce4', 'user');
	}

	/**
	 * Reduce an arbitrary array (decoded JSON or raw $_POST) to de-duplicated
	 * plain folder names: letters, digits, dot, underscore, hyphen; no '..'.
	 * SECURITY: passing here is not acceptance — names are still matched
	 * against the stored list in mergeSelection().
	 *
	 * @param array $names
	 * @return array
	 */
	private static function cleanNames(array $names)
	{
		$clean = array();
		foreach ($names as $name)
		{
			if (!is_string($name) || $name === '' || $name === '.'
				|| !preg_match('/^[A-Za-z0-9._-]+$/', $name) || strpos($name, '..') !== false)
			{
				continue;
			}
			$clean[$name] = $name;
		}

		return array_values($clean);
	}

	/**
	 * The one rule for a selection: (wanted ∩ list) ∪ (basePlugins() ∩ list).
	 * SECURITY: the list is the whitelist. Result keeps the list's order.
	 *
	 * @param array $wanted  candidate names (old selection, or posted values)
	 * @param array $list    stored plugin-folder list
	 * @return array
	 */
	private static function mergeSelection(array $wanted, array $list)
	{
		$keep = array_merge($wanted, self::basePlugins());

		return array_values(array_intersect($list, $keep));
	}

	/**
	 * Write the whole structure to the row's column in one go.
	 *
	 * @param int    $id        github_sync row id
	 * @param string $folder    'eplugins' or 'e107_plugins' (already whitelisted)
	 * @param array  $list      plugin-folder list
	 * @param array  $selected  selection (already a subset of $list)
	 * @return void
	 */
	private static function store($id, $folder, array $list, array $selected)
	{
		self::write($id, json_encode(array(
			'folder'   => $folder,
			'list'     => array_values($list),
			'selected' => array_values($selected),
		)));
	}

	/**
	 * Read the raw stored column of one row. Never hits the network.
	 *
	 * @param int $id  github_sync row id
	 * @return string|null  the raw JSON string, or null when the row is missing
	 */
	private static function load($id)
	{
		$id = (int) $id;
		if ($id < 1)
		{
			return null;
		}

		// Two columns are requested so retrieve() always returns a row array
		// (a single-column request would return the bare value instead).
		$row = e107::getDb()->retrieve(self::TABLE, 'id, ' . self::COLUMN, 'WHERE id=' . $id);
		if (empty($row) || !isset($row[self::COLUMN]))
		{
			return null;
		}

		return (string) $row[self::COLUMN];
	}

	/**
	 * Write the raw column value of one row (native db->update(); the 'str'
	 * field type escapes it). SECURITY: every folder name inside the JSON has
	 * already passed the strict [A-Za-z0-9._-] rule, so no HTML encoding step
	 * is needed — and $tp->toDB() would break the JSON quotes.
	 *
	 * @param int    $id    github_sync row id
	 * @param string $json  encoded structure, or '' to clear
	 * @return void
	 */
	private static function write($id, $json)
	{
		$id = (int) $id;
		if ($id < 1)
		{
			return;
		}

		e107::getDb()->update(self::TABLE, array(
			'data'         => array(self::COLUMN => (string) $json),
			'WHERE'        => 'id=' . $id,
			'_FIELD_TYPES' => array(self::COLUMN => 'str'),
		));
	}

	/**
	 * Is the folder present in the local plugins directory? Filesystem check
	 * only. On disk is NOT installed — a standard e107 ships every core plugin
	 * folder; use isInstalled() for the real state.
	 *
	 * @param string $folder
	 * @return bool
	 */
	public static function existsOnDisk($folder)
	{
		$folder = trim((string) $folder, '/');
		if ($folder === '' || strpos($folder, '..') !== false || strpos($folder, '/') !== false)
		{
			return false;
		}

		return is_dir(e_PLUGIN . $folder);
	}

	/**
	 * Is the plugin actually INSTALLED on this site (registered in the
	 * plugin table), as opposed to merely present on disk?
	 *
	 * @param string $folder
	 * @return bool
	 */
	public static function isInstalled($folder)
	{
		$folder = trim((string) $folder, '/');
		if ($folder === '' || strpos($folder, '..') !== false || strpos($folder, '/') !== false)
		{
			return false;
		}

		return e107::isInstalled($folder);
	}
}
