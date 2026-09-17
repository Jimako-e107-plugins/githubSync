<?php
/**
 * Read accessor for the Find Plugins/Themes sources.
 *
 * Sources live in plugin preferences (a core sync can never wipe them), with
 * a separate list per market type: 'plugin' -> pref find_sources + folder
 * sources/plugins/, 'theme' -> pref find_theme_sources + sources/themes/.
 *
 * Each row is one catalog: a remote https URL, or a builtin XML file from the
 * plugin's sources/ folder imported via the Sources screen. Folder catalogs
 * are never auto-loaded. Stored shape per pref key:
 *
 *   [ ['label','url','enabled','builtin','type','excluded'=>['org/repo/folder',…]], … ]
 *
 * 'excluded' is per-source: a plugin excluded in source A still appears from
 * source B. Absent key = none excluded.
 *
 * @package githubFind
 */

if (!defined('e107_INIT'))
{
	exit;
}

class github_sync_sources
{
	const PREF_PLUGIN = 'find_sources';
	const PREF_THEME  = 'find_theme_sources';

	/**
	 * Preference key for a market type.
	 *
	 * @param string $type 'plugin' | 'theme'
	 * @return string
	 */
	public static function prefKey($type = 'plugin')
	{
		return ($type === 'theme') ? self::PREF_THEME : self::PREF_PLUGIN;
	}

	/**
	 * All configured sources for a type, in order.
	 *
	 * @param string $type 'plugin' | 'theme'
	 * @return array
	 */
	public static function getAll($type = 'plugin')
	{
		$pluginName = 'githubFind';
		$sources    = e107::getPlugPref($pluginName, self::prefKey($type), array());
		return is_array($sources) ? array_values($sources) : array();
	}

	/**
	 * Every enabled row for a type. Folder catalogs must be imported and
	 * enabled first. Each row includes 'excluded' (may be empty) so
	 * github_marketplace can filter per-source exclusions.
	 *
	 * @param string $type 'plugin' | 'theme'
	 * @return array
	 */
	public static function getEnabled($type = 'plugin')
	{
		$enabled = array();

		foreach (self::getAll($type) as $source)
		{
			if (!empty($source['enabled']))
			{
				$enabled[] = $source;
			}
		}

		return $enabled;
	}

	/**
	 * Raw scan of the bundled catalog files for one type, returning
	 * ['label','url','type'] per file. SECURITY: the scan path is fixed (no
	 * user input) and only *.xml is read, so there is no traversal or upload
	 * surface here.
	 *
	 * @param string $type 'plugin' | 'theme'
	 * @return array
	 */
	public static function getFolderSources($type = 'plugin')
	{
		$sub = ($type === 'theme') ? 'themes' : 'plugins';
		$dir = e_PLUGIN . 'githubFind/sources/' . $sub . '/';

		if (!is_dir($dir))
		{
			return array();
		}

		$out = array();

		// Native e107 directory reader, filtered to .xml files.
		$files = e107::getFile()->get_files($dir, '\.xml$');

		foreach ((array) $files as $f)
		{
			$fname = isset($f['fname']) ? $f['fname'] : '';
			if ($fname === '' || !preg_match('/\.xml$/i', $fname))
			{
				continue;
			}

			$out[] = array(
				'label' => ucfirst($type) . ': ' . $fname,
				'url'   => $dir . $fname,
				'type'  => $type,
			);
		}

		return $out;
	}
}
