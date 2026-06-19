<?php

/**
 * github_marketplace — the plugin's own copy of the Lite GitHub registry reader.
 *
 * Bundled (not calling core e_marketplace) so Find Plugins works on UPSTREAM e107
 * too, where e_marketplace has no GitHub registry. This is the live Lite
 * e_marketplace class, renamed; the dead SOAP/XML-RPC adapters are NOT carried.
 *
 * The ONLY behavioural change vs. core is getRegistryList(): instead of one fixed
 * file (pluginpack.xml / themepack.xml) it reads ALL enabled catalogs from
 * github_sync_sources::getEnabled() (folder catalogs + remote URLs) and merges
 * them (de-dup by folder, first source wins). Everything downstream — parseRegistry,
 * fetchRemotePluginXml, validateRemote, isValidSegment, sanitizeRemoteUrl, etc. —
 * is unchanged. XML parsing uses e107 xmlClass (XXE-safe: no LIBXML_NOENT).
 *
 * @package githubSync
 */

if (!defined('e107_INIT'))
{
	exit;
}

class github_marketplace
{
	/**
	 * Empty $xdata skeleton — returned on any error so callers never receive null.
	 *
	 * @param  string $type
	 * @return array
	 */
	private function emptyResult($type)
	{
		return array(
			'params' => array(
				'count'         => 0,
				'type'          => $type,
				'authenticated' => 0,
			),
			'data' => array(),
		);
	}	


	/**
	 * Read pluginpack.xml and return $xdata matching original marketplace format.
	 * Fetches remote plugin.xml per entry to populate version, author, icon etc.
	 *
	 * @param  string $type  'plugin' or 'theme'
	 * @return array
	 */
	public function getRegistryList($type = 'plugin')
	{
		if ($type !== 'plugin' && $type !== 'theme')
		{
			e107::getMessage()->addWarning('Unknown registry type: ' . $type);
			return $this->emptyResult($type);
		}

		e107_require_once(e_PLUGIN . 'githubSync/github_sync_sources.php');
		$sources = github_sync_sources::getEnabled($type);

		$result = $this->emptyResult($type);
		$seen   = array(); // de-dup by folder — first source wins
		$i      = 0;

		foreach ($sources as $src)
		{
			$url = isset($src['url']) ? (string) $src['url'] : '';
			if ($url === '')
			{
				continue;
			}

			$data = $this->loadCatalog($url);
			if (empty($data))
			{
				e107::getMessage()->addDebug('Catalog could not be read/parsed: ' . $url);
				continue;
			}

			$parsed = $this->parseRegistry($data, $type);

		// Build exclusion lookup for this source: 'org/repo/folder' => true.
			$excluded = array();
			if (!empty($src['excluded']) && is_array($src['excluded']))
			{
				foreach ($src['excluded'] as $key)
				{
					$excluded[(string) $key] = true;
				}
			}
 
			foreach ($parsed['data'] as $row)
			{
				$folder = isset($row['folder']) ? $row['folder'] : '';
				if ($folder === '' || isset($seen[$folder]))
				{
					continue; // first source wins on folder collisions
				}
 
				// Per-source exclusion check — key is 'org/repo/folder'.
				if (!empty($excluded))
				{
					$org    = isset($row['params']['organization']) ? $row['params']['organization'] : '';
					$repo   = isset($row['params']['repo'])         ? $row['params']['repo']         : '';
					$exKey  = $org . '/' . $repo . '/' . $folder;
					if (isset($excluded[$exKey]))
					{
						continue; // excluded for this source
					}
				}
 
				$seen[$folder]      = true;
				$result['data'][$i] = $row;
				$i++;
			}
		}

		$result['params']['count'] = $i;

		return $result;
	}

	/**
	 * Load one catalog into the 'advanced' (xml2array) structure parseRegistry
	 * expects. Local paths (e107 constants resolved) via loadXMLfile; remote
	 * https catalogs fetched then parsed. xmlClass is XXE-safe.
	 *
	 * @param  string $url  source location (https URL or local path/constant)
	 * @return array
	 */
	private function loadCatalog($url)
	{
		$xml = e107::getXml();

		if (preg_match('#^https?://#i', $url))
		{
			$url = $this->normalizeRemoteCatalogUrl($url);
			$raw = $xml->getRemoteFile($url);
			if (empty($raw))
			{
				return array();
			}
			// false = xml2array (advanced) — same shape as loadXMLfile('advanced')
			$data = $xml->parseXml($raw, false);
			return is_array($data) ? $data : array();
		}

		$path = e107::getParser()->replaceConstants($url);
		if (!is_readable($path))
		{
			return array();
		}

		$data = $xml->loadXMLfile($path, 'advanced');
		return is_array($data) ? $data : array();
	}

	/**
	 * Convert a GitHub "blob" page URL (what you copy from the browser) into the
	 * raw-content URL, so getRemoteFile() receives XML rather than an HTML page:
	 *   https://github.com/{org}/{repo}/blob/{branch}/{path}
	 *     -> https://raw.githubusercontent.com/{org}/{repo}/{branch}/{path}
	 * Also drops a trailing ?raw=1/?raw=true. Any other URL is returned unchanged.
	 *
	 * @param  string $url
	 * @return string
	 */
	private function normalizeRemoteCatalogUrl($url)
	{
		if (preg_match('#^https?://(?:www\.)?github\.com/([^/]+)/([^/]+)/blob/(.+)$#i', $url, $m))
		{
			$url = 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $m[3];
		}

		return preg_replace('/\?raw=(?:1|true)$/i', '', $url);
	}

		/**
	 * Parse the array produced by xmlClass::loadXMLfile($file, 'advanced').
	 * Iterates <plugin> (or <theme>) nodes, fetches remote plugin.xml per entry.
	 *
	 * xmlClass 'advanced' (xml2array) produces attributes under '@attributes':
	 *   $node['@attributes']['folder']
	 *   $node['@attributes']['organization']
	 *   etc.
	 *
	 * @param  array  $data  Parsed XML array from xmlClass
	 * @param  string $type  'plugin' or 'theme'
	 * @return array
	 */
	private function parseRegistry($data, $type)
	{
		$result = $this->emptyResult($type);

		// xmlClass returns multiple same-name nodes as numeric array,
		// single node as associative — normalize to numeric
		$nodes = isset($data[$type]) ? $data[$type] : array();

		if (empty($nodes))
		{
			return $result;
		}

		// Single entry — xmlClass returns assoc instead of array of assoc
		if (isset($nodes['@attributes']))
		{
			$nodes = array($nodes);
		}

		$i = 0;
 
		foreach ($nodes as $node)
		{
			$attr = isset($node['@attributes']) ? $node['@attributes'] : array();

			$folder = isset($attr['folder'])      ? trim($attr['folder'])       : '';
			$org    = isset($attr['organization']) ? trim($attr['organization']) : '';
			$repo   = isset($attr['repo'])         ? trim($attr['repo'])         : '';
			$branch = isset($attr['branch'])       ? trim($attr['branch'])       : 'main';

			// Required fields — skip and notify if missing
			if (empty($folder))
			{
				e107::getMessage()->addDebug('pluginpack.xml: entry missing folder attribute, skipped.');
				continue;
			}

			if (empty($org) || empty($repo))
			{
				e107::getMessage()->addDebug('pluginpack.xml: entry "' . $folder . '" missing organization or repo, skipped.');
				continue;
			}

			// Reject poisoned path segments before they can be interpolated into any
			// remote URL (prevents redirecting fetches off raw.githubusercontent.com).
			if (!$this->isValidSegment($folder) || !$this->isValidSegment($org)
				|| !$this->isValidSegment($repo) || !$this->isValidSegment($branch))
			{
				e107::getMessage()->addDebug('pluginpack.xml: entry "' . $folder . '" has an invalid path segment, skipped.');
				continue;
			}

			// Compatibility gate
			$compat = isset($attr['compatibility']) ? trim($attr['compatibility']) : '';

			if (!empty($compat) && defined('e_VERSION') && version_compare(e_VERSION, $compat, '<'))
			{
				continue;
			}
 
			// Registry-level name and description (may be overridden by plugin.xml)
			$registryName = isset($attr['name']) ? trim($attr['name']) : $folder;
			$registryDesc = $this->extractRegistryDescription($node);

			$infourl = isset($attr['infourl']) ? trim($attr['infourl']) : '';

			$downloadUrl = $this->buildDownloadUrl($org, $repo, $branch);
 
			// Fetch remote plugin.xml — version, author, icon, category, date
			$remote = $this->fetchRemotePluginXml($org, $repo, $branch, $folder, $type);

			// Registry-rules runtime gate — see REGISTRY-RULES.md
			$installable  = $this->validateRemote($remote);
			$installError = $installable ? '' : $this->getValidationError($remote, $folder);

			if (!$installable)
			{
				e107::getMessage()->addDebug('pluginpack: entry "' . $folder . '" not installable — ' . $installError);
			}

			// plugin.xml wins over registry for shared fields
			$name         = (!empty($remote['name']))          ? $remote['name']          : $registryName;
			$desc         = (!empty($remote['description']))   ? $remote['description']   : $registryDesc;
			$version      = (!empty($remote['version']))       ? $remote['version']       : '';
			$author       = (!empty($remote['author']))        ? $remote['author']        : '';
			$authorURL    = (!empty($remote['authorURL']))     ? $remote['authorURL']     : $infourl;
			$date         = (!empty($remote['date']))          ? $remote['date']          : '';
			$category     = (!empty($remote['category']))      ? $remote['category']      : '';
			$icon         = (!empty($remote['icon']))          ? $remote['icon']          : '';
			$remoteCompat = (!empty($remote['compatibility'])) ? $remote['compatibility'] : $compat;
 
			$result['data'][$i] = array(
				'icon'          => $icon,
				'name'          => $name,
				'folder'        => $folder,
				'version'       => $version,
				'author'        => $author,
				'authorURL'     => $authorURL,
				'date'          => $date,
				'compatibility' => $remoteCompat,
				'url'           => $downloadUrl,
				'urlView'       => $infourl,
				'description'   => $desc,
				'category'      => $category,
				'thumbnail'     => $icon,
				'featured'      => 0,
				'screenshots'   => '',
				'livedemo'      => '',
				'price'         => '',
				'installable'   => $installable,
				'install_error' => $installError,
				'params'        => array(
					'organization' => $org,
					'repo'         => $repo,
					'branch'       => $branch,
					'type'         => $type,
					'mode'         => 'github',
				),
			);

			$i++;
		}

		$result['params']['count'] = $i;

		return $result;
	}


	/**
	 * Check that a remote plugin.xml payload satisfies the runtime registry rules.
	 * Returns false for null/empty/non-array input or when required fields are missing.
	 *
	 * @param  mixed $remote  Result from fetchRemotePluginXml()
	 * @return bool
	 */
	private function validateRemote($remote)
	{
		if (empty($remote) || !is_array($remote))
		{
			return false;
		}

		if (empty($remote['name']) || empty($remote['version']))
		{
			return false;
		}

		return true;
	}


	/**
	 * Human-readable validation error for the disabled Install button tooltip.
	 *
	 * @param  mixed  $remote  Result from fetchRemotePluginXml()
	 * @param  string $folder  Registry folder (used in the not-found message)
	 * @return string          Empty string if $remote is valid
	 */
	private function getValidationError($remote, $folder = '{folder}')
	{
		if (empty($remote) || !is_array($remote))
		{
			$path = 'e107_plugins/' . $folder . '/plugin.xml';
			return 'plugin.xml not found at expected path (' . $path . ')';
		}

		$missing = array();

		if (empty($remote['name']))
		{
			$missing[] = 'name';
		}

		if (empty($remote['version']))
		{
			$missing[] = 'version';
		}

		if (empty($missing))
		{
			return '';
		}

		return 'plugin.xml is missing required fields: ' . implode(', ', $missing);
	}


	/**
	 * Fetch remote plugin.xml and return displayable fields.
	 * Uses e107 xmlClass — HTTP via e107::getFile()->getRemoteContent().
	 *
	 * Returns null if unreachable or unparseable.
	 *
	 * @param  string $org
	 * @param  string $repo
	 * @param  string $branch
	 * @param  string $folder
	 * @param  string $type   'plugin' or 'theme'
	 * @return array|null
	 */
	public function fetchRemotePluginXml($org, $repo, $branch, $folder, $type = 'plugin')
	{
		$url = $this->buildRawUrl($org, $repo, $branch, $folder, $type);

		if ($url === '')
		{
			e107::getMessage()->addDebug('pluginpack: invalid path segment — plugin.xml not fetched.');
			return null;
		}

		$xml  = e107::getXml();
		$data = $xml->getRemoteFile($url);
 
		if (empty($data))
		{
			e107::getMessage()->addDebug('pluginpack: could not fetch plugin.xml — ' . $url);
			return null;
		}

		// false = xml2array (advanced) so attributes come under '@attributes'
		$parsed = $xml->parseXml($data, false);

		if (empty($parsed))
		{
			e107::getMessage()->addDebug('pluginpack: invalid plugin.xml — ' . $url);
			return null;
		}

	// Root attributes
	$rootAttr = isset($parsed['@attributes']) ? $parsed['@attributes'] : array();

	// <author name="" url="" />
	$authorAttr = isset($parsed['author']['@attributes']) ? $parsed['author']['@attributes'] : array();

	// <description> — má @value
	$desc = '';
	if (isset($parsed['description']['@value']))
	{
		$desc = trim($parsed['description']['@value']);
	}

	// <summary> — fallback ak nie je description
	if (empty($desc) && isset($parsed['summary']['@value']))
	{
		$desc = trim($parsed['summary']['@value']);
	}

	// <category> — priamy string
	$category = isset($parsed['category']) ? trim($parsed['category']) : '';
 
		// // Icon from <adminLinks><link icon="" iconSmall="" primary="true">
	$base = $this->buildRawBase($org, $repo, $branch, $folder, $type);

	// Screenshots / icon — build candidate URL then validate (http/https only).
	// The filename segment is attacker-controlled (remote <screenshots><image>);
	// validation drops anything that could break out of the <img src='...'> attribute.
	$screenshot = '';
	$imgRel     = (isset($parsed['screenshots']['image'][0]) && is_string($parsed['screenshots']['image'][0]))
		? trim($parsed['screenshots']['image'][0])
		: '';
	// Only build a URL when there is an actual filename — an empty <image> would
	// otherwise yield the folder URL ".../{folder}/" (no file), which the icon
	// renderer then mistakes for a glyph name.
	if ($base !== '' && $imgRel !== '')
	{
		$candidate  = rtrim($base, '/') . '/' . ltrim($imgRel, '/');
		$screenshot = $this->sanitizeRemoteUrl($candidate);
	}

		// Sanitise at the trust boundary — every remote field leaves this handler clean.
		$tp = e107::getParser();

		return array(
			'name'          => isset($rootAttr['name'])          ? $tp->toText(trim($rootAttr['name']))          : '',
			'version'       => isset($rootAttr['version'])       ? $tp->toText(trim($rootAttr['version']))       : '',
			'date'          => isset($rootAttr['date'])          ? $tp->toText(trim($rootAttr['date']))          : '',
			'compatibility' => isset($rootAttr['compatibility']) ? $tp->toText(trim($rootAttr['compatibility'])) : '',
			'author'        => isset($authorAttr['name'])        ? $tp->toText(trim($authorAttr['name']))        : '',
			'authorURL'     => isset($authorAttr['url'])         ? $this->sanitizeRemoteUrl($authorAttr['url'])  : '',
			'description'   => $tp->toText($desc),
			'category'      => $category,
			'icon'          => $screenshot,
		);
	}


	/**
	* Extract <description> text from the registry node produced by
	* xmlClass::loadXMLfile($file, 'advanced'). xml2array yields any
	* of four shapes depending on attributes and sibling count:
	*   - string
	*   - array with '@value' key
	*   - indexed array of strings
	*   - indexed array of arrays with '@value' keys
	*
	* @param  array $node  Single <plugin> or <theme> node from registry
	* @return string       Trimmed description or '' if none usable
	*/
	private function extractRegistryDescription($node)
	{
		if (!isset($node['description']))
		{
			return '';
		}

		$d = $node['description'];

		if (is_string($d))
		{
			return trim($d);
		}

		if (is_array($d))
		{
			if (isset($d['@value']))
			{
				return trim($d['@value']);
			}

			if (isset($d[0]))
			{
				$first = $d[0];

				if (is_string($first))
				{
					return trim($first);
				}

				if (is_array($first) && isset($first['@value']))
				{
					return trim($first['@value']);
				}
			}
		}

		return '';
	}

	/**
	 * Extract icon URL from parsed adminLinks.
	 * Priority: primary link icon > any link icon > primary iconSmall > any iconSmall
	 *
	 * xmlClass 'advanced' (xml2array) produces:
	 *   multiple links: $parsed['adminLinks']['link'][n]['@attributes']['icon']
	 *   single link:    $parsed['adminLinks']['link']['@attributes']['icon']
	 *
	 * @param  array  $parsed  Parsed plugin.xml array
	 * @param  string $base    Raw GitHub base URL for plugin folder
	 * @return string          Full URL or empty string
	 */
	private function extractIcon($parsed, $base)
	{
		if (empty($parsed['screenshots']['image']))
		{
			return '';
		}

		 $icon = trim($parsed['screenshots']['image'][0]);
 
		 

		$path = $icon;

		if (empty($path))
		{
			return '';
		}
 
		return rtrim($base, '/') . '/' . ltrim($path, '/');
	}


	/**
	 * @param $data - e107.org plugin/theme feed data.
	 * @return bool|string
	 */
	public function getDownloadModal($type='plugin',$data=array())
	{

		$url = false;

		if($type === 'plugin')
		{

			if(empty($data['plugin_id']))
			{

				$srcData = array(
					'plugin_id'     => $data['params']['id'],
					'plugin_folder' => $data['folder'],
					'plugin_price'  => $data['price'],
					'plugin_mode'   => $data['params']['mode'],
					'plugin_url'    => $data['url'],
				);
			}
			else
			{
				$srcData = $data;
			}

			$d = http_build_query($srcData,false,'&');

		//	if(deftrue('e_DEBUG_PLUGMANAGER'))
			{
				$url = e_ADMIN.'plugin.php?mode=online&action=download&e-token='.e_TOKEN.'&src='.base64_encode($d);
			}
		//	else
			{
			//	$url = e_ADMIN.'plugin.php?mode=download&src='.base64_encode($d);
			}


		}

		if($type === 'theme')
		{
			$srcData = array(
				'id'    => $data['params']['id'],
				'url'   => $data['url'],
				'mode'  => 'addon',
				'price' => $data['price']
			);

			$d = http_build_query($srcData,false,'&');
			$url = e_ADMIN.'theme.php?mode=main&action=download&e-token='.e_TOKEN.'&src='.base64_encode($d);//$url.'&amp;action=download';

		}


		return $url;

	}


	/**
	 * Get version list for installed plugins — used by admin.php update checks.
	 * Returns array keyed by folder, matching original getVersionList() output.
	 *
	 * @param  string $type
	 * @return array  ['folder' => ['version'=>'', 'name'=>'', 'url'=>'', 'download'=>'', 'icon'=>'']]
	 */
	public function getVersionList($type = 'plugin')
	{
		$xdata  = $this->getRegistryList($type);
		$result = array();
 
		foreach ($xdata['data'] as $entry)
		{
			$folder = $entry['folder'];

			if (empty($folder) || empty($entry['version']))
			{
				continue;
			}

			$result[$folder] = array(
				'version'  => $entry['version'],
				'name'     => $entry['name'],
				'url'      => $entry['urlView'],
				'download' => $entry['url'],
				'icon'     => $entry['icon'],
			);
		}

		return $result;
	}


	/**
	 * Validate a GitHub path segment (organization / repo / branch / folder)
	 * before interpolating it into a remote URL. Anything outside the allowed
	 * character set is rejected so a poisoned pluginpack.xml cannot redirect
	 * fetches off raw.githubusercontent.com or inject path traversal.
	 *
	 * @param  string $segment
	 * @return bool
	 */
	private function isValidSegment($segment)
	{
		return is_string($segment) && $segment !== '' && (bool) preg_match('/^[A-Za-z0-9._-]+$/', $segment);
	}


	/**
	 * Validate an untrusted remote URL (icon / screenshot / author URL).
	 * Accepts only syntactically valid absolute http(s) URLs. Rejects
	 * javascript:, data:, schemeless values and any attribute-breaking
	 * characters (quotes, spaces, angle brackets — all refused by
	 * FILTER_VALIDATE_URL). Returns '' when invalid so callers can drop it.
	 *
	 * @param  string $url
	 * @return string
	 */
	private function sanitizeRemoteUrl($url)
	{
		$url = trim((string) $url);

		if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false)
		{
			return '';
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

		if ($scheme !== 'http' && $scheme !== 'https')
		{
			return '';
		}

		return $url;
	}


	/**
	 * Build zip download URL.
	 * https://github.com/{org}/{repo}/archive/refs/heads/{branch}.zip
	 *
	 * @param  string $org
	 * @param  string $repo
	 * @param  string $branch
	 * @return string  Empty string when any segment is invalid.
	 */
	private function buildDownloadUrl($org, $repo, $branch)
	{
		if (!$this->isValidSegment($org) || !$this->isValidSegment($repo) || !$this->isValidSegment($branch))
		{
			return '';
		}

		return 'https://github.com/' . $org . '/' . $repo
			. '/archive/refs/heads/' . $branch . '.zip';
	}


	/**
	 * Build raw GitHub base URL for a plugin folder.
	 * https://raw.githubusercontent.com/{org}/{repo}/refs/heads/{branch}/e107_plugins/{folder}
	 *
	 * @param  string $org
	 * @param  string $repo
	 * @param  string $branch
	 * @param  string $folder
	 * @param  string $type
	 * @return string
	 */
	private function buildRawBase($org, $repo, $branch, $folder, $type = 'plugin')
	{
		if (!$this->isValidSegment($org) || !$this->isValidSegment($repo)
			|| !$this->isValidSegment($branch) || !$this->isValidSegment($folder))
		{
			return '';
		}

		$dir = ($type === 'theme') ? 'e107_themes' : 'e107_plugins';

		return 'https://raw.githubusercontent.com/'
			. $org . '/' . $repo . '/refs/heads/' . $branch
			. '/' . $dir . '/' . $folder;
	}

	/**
	 * Build raw URL to plugin.xml inside the repo.
	 *
	 * @param  string $org
	 * @param  string $repo
	 * @param  string $branch
	 * @param  string $folder
	 * @param  string $type
	 * @return string
	 */
	private function buildRawUrl($org, $repo, $branch, $folder, $type = 'plugin')
	{
		$base = $this->buildRawBase($org, $repo, $branch, $folder, $type);

		if ($base === '')
		{
			return '';
		}

		return $base . '/plugin.xml';
	}

}
