<?php
/*
 * Register necessary class names with autoloader
 */
$extensionPath = t3lib_extMgm::extPath('taskcenter_recent');
return array(
	'tx_taskcenterrecent_task' => $extensionPath . 'task/class.tx_taskcenterrecent_task.php'
);
?>