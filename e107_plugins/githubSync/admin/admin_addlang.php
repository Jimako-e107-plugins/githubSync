<?php

// e107 Plugin Admin Area — githubSync (mode: addlang)
// Quick-add form for a single language repository. Type is fixed to 'language'
// (the menu choice determines it), branch is always 'master'. Standalone entry
// script — reachable from the menu or any link to
// admin/admin_addlang.php?mode=addlang&action=main.

require_once('../../../class2.php');
if (!getperms('P'))
{
	e107::redirect('admin');
	exit;
}

e107_require_once('admin_menu.php');                               // shared dispatcher
e107_require_once(e_PLUGIN . 'githubSync/github_sync_engine.php'); // for isValidSegment()


class github_addlang_ui extends e_admin_controller
{
	protected $defaultAction = 'main';

	public function mainPage()
	{
		$frm = e107::getForm();
		$mes = e107::getMessage();

		$value = '';

		if ($this->getRequest()->getPosted('addrepo'))
		{
			// On failure keep the entered value so the user can fix it.
			if (!$this->processAdd())
			{
				$value = trim((string) $this->getRequest()->getPosted('repo_url', ''));
			}
		}

		$text  = $frm->open('githubAddLang');
		$text .= $frm->token();
		$text .= "<table class='table adminform'>";
		$text .= "<tr><td style='width:25%'>GitHub repository URL</td><td>";
		$text .= $frm->text('repo_url', $value, 255, array('size' => 'xxlarge'));
		$text .= "<div class='field-help'>Public GitHub repo "
			. "Type is set to <strong>language</strong>, branch <strong>master</strong>.</div>";
		$text .= "</td></tr>";
		$text .= "</table>";
		$text .= "<div class='buttons-bar center'>"
			. $frm->admin_button('addrepo', 1, 'submit', 'Add Language Repo')
			. "</div>";
		$text .= $frm->close();

		// Messages first (added by processAdd), then the form.
		return $mes->render() . $text;
	}

	/**
	 * Validate, de-dupe and insert the language repo.
	 *
	 * @return bool TRUE on insert, FALSE otherwise (message already added).
	 */
	private function processAdd()
	{
		$mes = e107::getMessage();

		// CSRF
		if (!e107::getSession()->checkFormToken($this->getRequest()->getPosted('e-token', '')))
		{
			$mes->addError('Invalid security token.');
			return false;
		}

		$url  = trim((string) $this->getRequest()->getPosted('repo_url', ''));
		$repo = $this->parseGithubRepoUrl($url);

		if ($repo === false)
		{
			$mes->addError('Please enter a valid GitHub repository URL, e.g. https://github.com/e107sk/Czech');
			return false;
		}

		list($organization, $repository) = $repo;

		// Duplicate check (organization + repo + type).
		$where = "type='language' AND organization='" . $organization . "' AND repo='" . $repository . "'";
		if (e107::getDb()->count('github_sync', '(*)', $where) > 0)
		{
			$mes->addWarning("This repository is already in the list: {$organization}/{$repository}");
			return false;
		}

		$inserted = e107::getDb()->insert('github_sync', array(
			'data' => array(
				'type'         => 'language',
				'organization' => $organization,
				'repo'         => $repository,
				'branch'       => 'master',
				'folder'       => '',
				'lastsynced'   => 0,
				'note'         => '',
				'token'        => '',
				'public_repo'  => 1,
			),
			'_FIELD_TYPES' => array(
				'type'         => 'todb',
				'organization' => 'todb',
				'repo'         => 'todb',
				'branch'       => 'todb',
				'folder'       => 'todb',
				'note'         => 'todb',
				'token'        => 'todb',
				'lastsynced'   => 'int',
				'public_repo'  => 'int',
			),
		));

		if ($inserted)
		{
			$mes->addSuccess("Added language repo: {$organization}/{$repository}");
			return true;
		}

		$mes->addError('Could not save the repository.');
		return false;
	}

	/**
	 * Parse + validate a GitHub repository URL.
	 * Accepts only https://github.com/{org}/{repo} (optionally with a trailing
	 * slash, .git, or /tree/<branch>). Returns array($org, $repo) or FALSE.
	 *
	 * @param string $url
	 * @return array|false
	 */
	private function parseGithubRepoUrl($url)
	{
		if ($url === '')
		{
			return false;
		}

		$parts = parse_url($url);

		if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true))
		{
			return false;
		}

		$host = strtolower($parts['host'] ?? '');
		if ($host !== 'github.com' && $host !== 'www.github.com')
		{
			return false;
		}

		$path = trim($parts['path'] ?? '', '/');
		if ($path === '')
		{
			return false;
		}

		$seg = explode('/', $path);
		if (count($seg) < 2)
		{
			return false; // needs at least {org}/{repo}
		}

		$organization = $seg[0];
		$repository   = preg_replace('/\.git$/i', '', $seg[1]);

		// Reject GitHub reserved first-segments that are not user/org names.
		$reserved = array('orgs', 'sponsors', 'settings', 'marketplace', 'topics',
			'explore', 'notifications', 'about', 'pricing', 'features', 'login', 'join', 'apps');
		if (in_array(strtolower($organization), $reserved, true))
		{
			return false;
		}

		if (!github_sync_engine::isValidSegment($organization) || !github_sync_engine::isValidSegment($repository))
		{
			return false;
		}

		return array($organization, $repository);
	}
}


new githubSync_adminArea();

require_once(e_ADMIN . 'auth.php');
e107::getAdminUI()->runPage();

require_once(e_ADMIN . 'footer.php');
exit;
