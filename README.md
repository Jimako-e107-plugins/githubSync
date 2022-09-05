# githubSync

e107 plugin for extending core functionality

WARNING: because this is plugin mainly for personal use, install.xml contains repos I am actually working on. 
After installation you should clean it and backup your own xml file to import it with Data/Tools/Import if you need your own set somewhere else.

Importing this file by default is set by core:  see #4745


## version 1.2 

Added support for:
- theme pack (theme and plugins in related e107 folders)
- plugins pack (more plugins in related e107 folder)
- languages pack (plugins, themes and languages e107 folder)
- added note 

## version 1.1

Added support for:
- theme in repo
- repo with different name than needed folder


## It allows to sync to any repository 

Supported:
- core itself
- plugins

Planned:
themes


### Warning
This plugin is used for custom development. Don't use it if you don't know what are you doing.  It can very easily break your site. 

Its main reason is the minimalization of core file changes - to be able to sync with different than core repo that is under active development. 

Next reason (not plannned at first) - way how to download needed plugins from admin area without FTP




