<?php
/**
 * github_sync_sources — read accessor for the Find Plugins/Themes "sources".
 *
 * Sources live in plugin preferences (so a core sync can never wipe them), with a
 * SEPARATE list per market type:
 *   - 'plugin' -> pref 'find_sources'        + folder sources/plugins/
 *   - 'theme'  -> pref 'find_theme_sources'  + folder sources/themes/
 *
 * Each row is one catalog:
 *   - remote:  an https URL the admin added by hand, or
 *   - builtin: an XML file from the plugin's sources/ folder, imported (disabled)
 *     via the Sources screen, with the local file path as 'url'.
 *
 * Folder catalogs are discovered by getFolderSources($type) and imported/refreshed
 * by the Sources admin screen; they are not auto-loaded. github_marketplace reads
 * only the enabled rows via getEnabled($type).
 *
 * Stored shape (per pref key):
 *   [ ['label'=>string, 'url'=>string, 'enabled'=>1|0, 'builtin'=>1|0, 'type'=>?,
 *      'excluded'=>array('org/repo/folder', ...)], ... ]
 *
 * The 'excluded' array is per-source: a plugin excluded in source A still appears
 * from source B if it is not excluded there. Absent 'excluded' key = none excluded.
 *
 * @package githubSync
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
		$sources = e107::getPlugConfig('githubSync')->get(self::prefKey($type), array());
		return is_array($sources) ? array_values($sources) : array();
	}

	/**
	 * Sources that Find Plugins/Themes should actually read — every enabled row
	 * (enabled remote URLs + enabled builtin folder catalogs) for the type.
	 * Folder catalogs are NOT auto-loaded; they must be imported (Refresh/Save in
	 * the Sources screen) and enabled first.
	 *
	 * Each returned row includes the 'excluded' array (may be empty) so that
	 * github_marketplace::getRegistryList() can filter per-source exclusions.
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
	 * Raw scan of the bundled catalog files for one type:
	 *   'plugin' -> {e_PLUGIN}githubSync/sources/plugins/*.xml
	 *   'theme'  -> {e_PLUGIN}githubSync/sources/themes/*.xml
	 * Returns ['label','url','type'] per file. Used by the Sources admin screen to
	 * import/refresh builtin rows. The scan path is fixed (no user input) and only
	 * *.xml is read, so there is no traversal or upload surface here.
	 *
	 * @param string $type 'plugin' | 'theme'
	 * @return array
	 */
	public static function getFolderSources($type = 'plugin')
	{
		$sub = ($type === 'theme') ? 'themes' : 'plugins';
		$dir = e_PLUGIN . 'githubSync/sources/' . $sub . '/';

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
