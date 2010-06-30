<?php
/*
 * Register necessary class names with autoloader
 *
 * $Id: ext_autoload.php 6536 2009-11-25 14:07:18Z stucki $
 */
$extensionPath = t3lib_extMgm::extPath('taskcenter_recent');
return array(
	'tx_taskcenterrecent_task' => $extensionPath . 'task/class.tx_taskcenterrecent_task.php',
);
?>
