CREATE TABLE `github_sync` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`type` varchar(50) NOT NULL,
`organization` varchar(100) NOT NULL,
`repo` varchar(100) NOT NULL,
`branch` varchar(100) NOT NULL,
`lastsynced` int(11) NOT NULL,
`folder` varchar(50) NOT NULL,
UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;