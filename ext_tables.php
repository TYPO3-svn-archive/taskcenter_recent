<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter']['taskcenter_recent']['tx_taskcenterrecent_task'] = array(
		'title'       => 'LLL:EXT:taskcenter_recent/locallang.php:mod_recent',
		'description' => 'LLL:EXT:taskcenter_recent/locallang.php:recent_allRecs',
		'icon'		  => 'EXT:taskcenter_recent/ext_icon.gif'
	);
?>