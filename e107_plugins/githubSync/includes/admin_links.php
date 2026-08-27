<?php

// Shared cross-plugin admin navigation for the githubSync / findPlugins pair.
// Each dispatcher merges the OTHER plugin's links into its own left admin menu
// so the two screens feel like one tool, regardless of which one you entered
// from. Links to plugins that are not installed are skipped silently.

if (!defined('e107_INIT')) { exit; }

class githubSync_admin_links
{
	/**
	 * Build admin-menu entries (e_admin_dispatcher $adminMenu format) for the
	 * cross-links, excluding the calling plugin's own entries.
	 *
	 * @param array $exclude plugin folder names to omit (e.g. the caller)
	 * @return array key => array('caption','perm','url'[,'divider'])
	 */
	public static function get(array $exclude = array())
	{
		$nav = array(

			'githubSync' => array(
				'url'   => '{e_PLUGIN}githubSync/admin/admin_config.php',
				'items' => array(
					'main' => array(
						'caption' => 'Github Sync',
						 'query' => '',
					),
				),
			),

			'findPlugins' => array(
				'url'   => '{e_PLUGIN}findPlugins/admin/admin_config.php',
				'items' => array(
					'main' => array(
						'caption' => 'Find Plugins',
						 'query' => '',
					),
				),
			),

		);

		$out   = array();
		$first = true;

		foreach ($nav as $plugin => $def)
		{
			if (in_array($plugin, $exclude, true))
			{
				continue;
			}

			if (!e107::isInstalled($plugin))
			{
				continue;
			}

			if ($first)
			{
				$out['divider_crosslinks'] = array('divider' => true);
				$first = false;
			}

			foreach ($def['items'] as $key => $item)
			{
				$out[$key] = array(
					'caption' => $item['caption'],
					'perm'    => 'P',
					'url'     => $def['url'] . $item['query'],
				);
			}
		}

		return $out;
	}
}
