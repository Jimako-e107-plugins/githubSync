CREATE TABLE `github_sync` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`type` varchar(50) NOT NULL,
`organization` varchar(100) NOT NULL,
`repo` varchar(100) NOT NULL,
`branch` varchar(100) NOT NULL,
`lastsynced` int(11) NOT NULL,
`folder` varchar(50) NOT NULL,
`note` text NOT NULL,
`token` VARCHAR(255) NOT NULL DEFAULT '',
`public_repo` tinyint(1) NOT NULL DEFAULT '1',
`folder_prefix` varchar(10) NOT NULL DEFAULT 'e',
`plugins_folder` varchar(20) NOT NULL DEFAULT 'e107_plugins',
`plugin_list` text NOT NULL,
UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
