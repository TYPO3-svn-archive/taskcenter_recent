<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=='BE')	{
	t3lib_extMgm::insertModuleFunction(
		'user_task',
		'tx_taskcenterrecent',
		t3lib_extMgm::extPath($_EXTKEY).'class.tx_taskcenterrecent.php',
		'LLL:EXT:taskcenter_recent/locallang.php:mod_recent'
	);
}
?>