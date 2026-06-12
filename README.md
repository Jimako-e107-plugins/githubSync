# githubSync

e107 plugin for extending core functionality

WARNING: because this is plugin mainly for personal use, install.xml contains repos I am actually working on. 
After installation you should clean it and backup your own xml file to import it with Data/Tools/Import if you need your own set somewhere else.


## version 2.3

- multi source of plugin lists
- local source supported
- Find plugins supported 


## version 2.2

- reorganize admin part 
- extracted sync engine into a self-contained handler class
- improved synchronization of language's repo
- thined the controller and wired it to the engine
- improved language sync (existing-only plugin's folders + rename type to language)
- add/past Language Repo (quick-add from URL) functionality

## version 2.1

- Customized export batch option
- view button

## version 2.0

Added support for private repos

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
