<?php

########################################################################
# Extension Manager/Repository config file for ext "taskcenter_recent".
#
# Auto generated 30-06-2010 09:02
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'User>Task Center, Recent',
	'description' => 'Lists most recently edited pages and records in Task Center',
	'category' => 'module',
	'shy' => 0,
	'version' => '1.0.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Georg Ringer, Patrick Gaumond',
	'author_email' => '',
	'author_company' => 'Just2b, Infoglobe',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.4.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:6:{s:29:"class.tx_taskcenterrecent.php";s:4:"0dcf";s:16:"ext_autoload.php";s:4:"4a9f";s:12:"ext_icon.gif";s:4:"691d";s:14:"ext_tables.php";s:4:"a54b";s:13:"locallang.php";s:4:"2396";s:39:"task/class.tx_taskcenterrecent_task.php";s:4:"3fdc";}',
);

?>