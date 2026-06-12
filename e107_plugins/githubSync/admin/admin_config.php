<?php

// e107 Plugin Admin Area — githubSync (mode: main, action: prefs) — "Preferences".
// Placeholder for general plugin preferences. Settings will be added here as the
// plugin grows (using the native $prefs + beforePrefsSave pattern, like the
// Find Plugins Sources screen). Listed last in the admin menu.
// (The manual sync UI that used to live in this file is now admin_manual.php.)

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php'); // shared dispatcher


class github_settings_ui extends e_admin_ui
{
	protected $pluginName    = 'githubSync';
	protected $table         = ''; // prefs only — no table
	protected $pid           = '';
	protected $defaultAction = 'prefs';

	public function prefsPage()
	{
		$text  = "<div class='alert alert-info'>";
		$text .= "There are no plugin preferences yet. Settings will appear here as the plugin grows.";
		$text .= "</div>";

		return $text;
	}

	public function renderHelp()
	{
		return array(
			'caption' => LAN_HELP,
			'text'    => 'General githubSync preferences. Find Plugins <em>sources</em> have '
				. 'their own screen; this page is for plugin-wide settings (added later).',
		);
	}
}


class github_settings_form_ui extends e_admin_form_ui
{
}


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
