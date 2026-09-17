<?php

/**
 * Plugin setup hooks (native e107 {plugin}_setup.php mechanism).
 *
 * The table layout lives in githubSync_sql.php; when it gains columns, the
 * core plugin manager's db_verify pass ALTERs the live table and keeps every
 * row, so no hand-written ALTER is needed. This file only flags the upgrade
 * when a column is missing, and fills layout defaults (folder_prefix 'e',
 * plugins_folder 'e107_plugins') into rows left empty. The plugin selection
 * stays empty on purpose.
 *
 * @package githubSync
 */

if (!defined('e107_INIT'))
{
	exit;
}

class githubSync_setup
{
	/** Columns added for the per-row source layout + plugin selection. */
	private $newColumns = array('folder_prefix', 'plugins_folder', 'plugin_list');

	/**
	 * TRUE when one of the layout / selection columns is missing from the live
	 * table. The upgrade itself is the core's own db_verify pass.
	 *
	 * @return bool
	 */
	public function upgrade_required()
	{
		$sql = e107::getDb();

		if (!$sql->isTable('github_sync'))
		{
			return false; // nothing to upgrade (install handles table creation)
		}

		foreach ($this->newColumns as $column)
		{
			if ($sql->field('github_sync', $column) !== true)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Runs after db_verify has added the columns; fills layout defaults into
	 * rows with an empty value. Rows and other columns are untouched.
	 *
	 * @param mixed $var  plugin object passed by the core (unused)
	 * @return bool
	 */
	public function upgrade_post($var = null)
	{
		$sql = e107::getDb();

		if ($sql->field('github_sync', 'folder_prefix') === true)
		{
			$sql->update('github_sync', array(
				'data'         => array('folder_prefix' => 'e'),
				'WHERE'        => "folder_prefix=''",
				'_FIELD_TYPES' => array('folder_prefix' => 'str'),
			));
		}

		if ($sql->field('github_sync', 'plugins_folder') === true)
		{
			$sql->update('github_sync', array(
				'data'         => array('plugins_folder' => 'e107_plugins'),
				'WHERE'        => "plugins_folder=''",
				'_FIELD_TYPES' => array('plugins_folder' => 'str'),
			));
		}

		e107::getLog()->add('githubSync upgrade', 'Layout columns checked; migration defaults applied to empty values.', E_LOG_INFORMATIVE, '');

		return true;
	}
}
