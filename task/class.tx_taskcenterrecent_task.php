<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
*  (c) 2019      Georg Ringer (typo3@ringerge.org)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * This class provides a task for the taskcenter
 *
 * @author		Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author		Georg Ringer <typo3@ringerge.org>
 * @package		TYPO3
 * @subpackage	tx_taskcenterrecent
 *
 */
class tx_taskcenterrecent_task implements tx_taskcenter_Task {
	var $numberOfRecent    = 6;
	var $numberOfRecentAll = 20;
	var $logWhere          = ' AND sys_log.event_pid>0 AND sys_log.type=1 AND sys_log.action=2 AND sys_log.error=0';

	/**
	 * Back-reference to the calling taskcenter module
	 *
	 * @var	SC_mod_user_task_index	$taskObject
	 */
	protected $taskObject;

	/**
	 * Constructor
	 */
	public function __construct(SC_mod_user_task_index $taskObject) {
		$this->taskObject = $taskObject;
		$GLOBALS['LANG']->includeLLFile('EXT:taskcenter_recent/locallang.php');
	}

	/**
	 * This method renders the task
	 *
	 * @return	string	The task as HTML
	 */
	public function getTask() {
		$content = $this->taskObject->description($GLOBALS['LANG']->getLL('recent_allRecs'), $GLOBALS['LANG']->getLL('link_allRecs'));
// @todo: check if this function can be avoided
//		$content .= $this->renderRecent();
		$content .= $this->_renderRecent();
		return $content;
	}

	/**
	 * Gemeral overview over the task in the taskcenter menu
	 * providing the recent edited pages of the user
	 *
	 * @return	string Overview as HTML
	 */
	public function getOverview() {
		$out = '';
		$lines = array();

			// get the records of the current user
		$res = $this->getRecentResPointer($GLOBALS['BE_USER']->user['uid']);
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pageRow = t3lib_BEfunc::getRecordWSOL('pages', $row['event_pid']);
			if (is_array($pageRow)) {
				$path	= t3lib_BEfunc::getRecordPath($pageRow['uid'], $this->taskObject->perms_clause, $GLOBALS['BE_USER']->uc['titleLen']);
				$title	= htmlspecialchars($path) . ' - ' . t3lib_BEfunc::titleAttribForPages($pageRow, '', 0);
				$icon	= t3lib_iconworks::getIconImage('pages', $pageRow, $GLOBALS['BACK_PATH'], 'hspace="2" align="top" title="' . $title . '"');

				$lines[] = $icon . $this->recent_linkLayoutModule($pageRow['title'], $pageRow['uid']);
			}
		}

		$GLOBALS['TYPO3_DB']->sql_free_result($res);

			// if any records found
		if (count($lines) > 0) {
			$out = '<div style="margin:3px"> ' . implode('<br />', $lines) . '</div>';
		}

		return $out;
	}

	/**
	 * Show the recend edited records of a given user or usergroup
	 *
	 * @return	string Overview as HTML
	 */
	protected function _renderRecent() {
			// @todo: rename function name
		$content = $iframe = '';

		$id = intval(t3lib_div::_GP('display'));
		if($id > 0) {
			$path = $GLOBALS['BACK_PATH'] . 'sysext/cms/layout/db_layout.php?id=' . $id;

			if (t3lib_extMgm::isLoaded('templavoila')) {
				$path = $GLOBALS['BACK_PATH'] . t3lib_extMgm::extRelPath('templavoila') . 'mod1/index.php?id=' . $id ;
			}

			return $this->taskObject->urlInIframe($path, 1);
		} else {
			// @todo: fix path
			if (is_a($this->pObj,'SC_mod_user_task_index')) {
				$iframe .= $this->urlInIframe('');
			}

				// get the records of the requested user
			$content .= $this->getUserSelection();

				// extend where clause
			if ($GLOBALS['BE_USER']->isAdmin()) {
				$selectUsers = array();
				$selUser = t3lib_div::_GP('user');

					// usergroups
				if (substr($selUser, 0, 3) == 'gr-') {
					$where = ' AND ' . $GLOBALS['TYPO3_DB']->listQuery('usergroup_cached_list', intval(substr($selUser,3)), 'be_users');
					$records = t3lib_BEfunc::getUserNames('username,uid', $where);
					foreach ($records as $record) {
						$selectUsers[] = $record['uid'];
					}
					$selectUsers[] = 0;
					$this->logWhere.= ' AND sys_log.userid IN (' . implode($selectUsers, ',') . ')';
				} elseif (substr($selUser,0,3) == 'us-') {
						// users
					$selectUsers[] = intval(substr($selUser,3));
					$this->logWhere.= ' AND sys_log.userid in ('.implode($selectUsers,',').')';
				} elseif ($selUser == -1) {
					// do nothing, any user
				} else {
						// own records
					$this->logWhere .= ' AND sys_log.userid=' . $GLOBALS['BE_USER']->user['uid'];
				}

			} else {
				$this->logWhere .= ' AND sys_log.userid=' . $GLOBALS['BE_USER']->user['uid'];
			}

				// limit of records
			$max = t3lib_div::_GP('max');
			if (!empty($max)) {
				if ($max === 'any') {
					$this->numberOfRecentAll = '';
				} else {
					$this->numberOfRecentAll = intval($max);
				}
			}

				// time range
			$timeRange = intval(t3lib_div::_GP('time'));
			$starttime = 0;
			$endtime = $GLOBALS['EXEC_TIME'];
			switch($timeRange) {
				case 1:
					// This week
					$week = (date('w') ? date('w') : 7) - 1;
					$starttime = mktime (0, 0, 0) - $week * 3600 * 24;
				break;
				case 2:
					// Last week
					$week = (date('w') ? date('w') : 7)-1;
					$starttime = mktime (0,0,0)-($week+7)*3600*24;
					$endtime = mktime (0,0,0)-$week*3600*24;
				break;
				case 3:
					// Last 7 days
					$starttime = mktime (0,0,0)-7*3600*24;
				break;
				case 10:
					// This month
					$starttime = mktime (0,0,0, date('m'),1);
				break;
				case 11:
					// Last month
					$starttime = mktime (0,0,0, date('m')-1,1);
					$endtime = mktime (0,0,0, date('m'),1);
				break;
				case 12:
					// Last 31 days
					$starttime = mktime (0,0,0)-31*3600*24;
				break;
			}
			if ($starttime > 0) {
				$this->logWhere .= ' AND sys_log.tstamp >= ' . $starttime . ' AND sys_log.tstamp < ' . $endtime;
			}

			if (t3lib_extMgm::isLoaded('version')) {
				$this->logWhere .= ' AND pages.t3ver_wsid=' . $GLOBALS['BE_USER']->workspace;
			}
				// create the query
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_log.tablename, sys_log.recuid, MAX(sys_log.tstamp) AS tstamp_MAX',
				'sys_log INNER JOIN pages ON pages.uid = sys_log.event_pid',
				' 1=1' . $this->logWhere .
				' AND ' . $this->taskObject->perms_clause,
				'tablename,recuid',
				'tstamp_MAX DESC',
				$this->numberOfRecentAll
			);

				// initialize click menu JS
			$CMparts=$this->taskObject->doc->getContextMenuCode();
			$this->taskObject->bodyTagAdditions = $CMparts[1];
			$this->taskObject->JScode .= $CMparts[0];
			$this->taskObject->postCode .= $CMparts[2];

			$lines = array();
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// get the single record
				$elRow = t3lib_BEfunc::getRecordWSOL($row['tablename'], $row['recuid']);

				if (is_array($elRow)) {
					$path = t3lib_BEfunc::getRecordPath($elRow['pid'], $this->taskObject->perms_clause, $GLOBALS['BE_USER']->uc['titleLen']);
					$editIcon = t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="11" height="12"');
					$recordIcon = t3lib_iconworks::getIconImage($row['tablename'], $elRow, $GLOBALS['BACK_PATH'], 'class="c-recicon" title="' . htmlspecialchars($path) . '"');
					$recordTitle = htmlspecialchars($elRow[$GLOBALS['TCA'][$row['tablename']]['ctrl']['label']]);
					if (empty($recordTitle)) {
						$recordTitle = '[<em>' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:labels.no_title') . '</em>]';
					}
					$recordTitle = $this->recent_linkEdit($recordTitle, $row['tablename'], $elRow['uid']);

					$editIcon = t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/i/pages_up.gif', 'width="11" height="12"s');
					$recordIcon = $this->taskObject->doc->wrapClickMenuOnIcon($recordIcon, $row['tablename'], $row['recuid'], 0);

						// put it all together
					$lines[] = '
				<tr class="db_list_normal">
					<td>' . $recordIcon . $recordTitle . '&nbsp;</td>
					<td>' . t3lib_BEfunc::dateTimeAge($row['tstamp_MAX']) . '</td>
				</tr>';
				}
			}

			$GLOBALS['TYPO3_DB']->sql_free_result($res);

				// if any records found to display
			if (count($lines) > 0) {

					// build the table
				$content .= '<table border="0" cellpadding="0" cellspacing="0" class="typo3-dblist">
							<thead>
								<tr class="t3-row-header">
									<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_general.xml:LGL.title') . '</th>
									<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_file_list.xml:c_tstamp') . '</th>
								</tr>
							</thead>
							' . implode('', $lines) . '
						</table>';
			} else {
					// no records found
				$flashMessage = t3lib_div::makeInstance (
					't3lib_FlashMessage',
					$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:labels.noRecordFound'),
					$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_tools_em.xml:details_info'),
					t3lib_FlashMessage::INFO
				);
				$content .= '<br />' . $flashMessage->render();
			}

			return $content . $iframe;
		}
	}

	/**
	 * Render a form holding a select field with all users and usergroups
	 * admin only
	 *
	 * @return	string Form with select field
	 */
	protected function getUserSelection() {
		$out = '';


			// put it all together
			// todo: add action url
		$out .= '<form action="" method="post">
					<fieldset class="fields">
						<legend>' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_wizards.xml:forms_config') . '</legend>';

			// user selection is for admins only
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$out .= '	<div class="row">
							<label for="select_user">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:users') . '</label>
							<select id="select_user" name="user" onchange="this.form.submit();">
								<option value="0">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:self') . '</option>
								<option value="-1">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:any') . '</option>' .
								$this->getOptionsUsers() .
							'</select>
						</div>';
		}

		$out .= '		<div class="row">
							<label for="select_max">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:max') . '</label>
							<select id="select_max" name="max" onchange="this.form.submit();">' .
								$this->getOptionsMax() .
							'</select>
						</div>

						<div class="row">
							<label for="select_time">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:time') . '</label>
							<select id="select_time" name="time" onchange="this.form.submit();">' .
								$this->getOptionsTime() .
							'</select>
						</div>
					</fieldset>
				</form>
				<br />';

		return $out;
	}

	/**
	 * Create the options for the select field to set a user or usergroup
	 *
	 * @return	html options
	 */
	protected function getOptionsUsers() {
		$out = '';

			// get all users and remove own record
		$users = t3lib_BEfunc::getUserNames();
		unset($users[$GLOBALS['BE_USER']->user['uid']]);
		if (count($users) > 0) {
			$titlePrefix = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:users') . ' ';
			$out .= '<option class="disabled" disabled="disabled">' . $titlePrefix . '</option>';

			foreach($users as $id => $user) {
				$id = 'us-' . $id;
				$selected = (t3lib_div::_GP('user') == $id) ? ' selected="selected" ' : '';
				$out .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($user['username']) . '</option>';
			}
		}

			// get all usergroups
		$usergroups = t3lib_BEfunc::getGroupNames();
		if (count($usergroups) > 0) {
			$titlePrefix = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:userGroup') . ' ';
			$out .= '<option class="disabled" disabled="disabled">' . $titlePrefix . '</option>';
			foreach($usergroups as $id => $group) {
				$id = 'gr-' . $id;
				$selected = (t3lib_div::_GP('user') == $id) ? ' selected="selected" ' : '';
				$out .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($group['title']) . '</option>';
			}
		}

		return $out;
	}

	/**
	 * Create the options for the select field to set a limit
	 *
	 * @return	html options
	 */
	protected function getOptionsMax() {
		$out = '';

		$itemList = array(20, 50, 100, 200, 'any');
		foreach ($itemList as $item) {
			$selected = (t3lib_div::_GP('max') == $item) ? ' selected="selected" ' : '';
			$title = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:' . $item);
			$out .= '<option value="' . $item . '"' . $selected . '>' . htmlspecialchars($title) . '</option>';
		}

		return $out;
	}

	/**
	 * Create the options for the select field to set a time limit
	 *
	 * @return	html options
	 */
	protected function getOptionsTime() {
		$out = '<option value=""></option>';

		$itemList = array (
			1 => 'thisWeek',
			2 => 'lastWeek',
			3 => 'last7Days',
			10 => 'thisMonth',
			11 => 'lastMonth',
			12 => 'last31Days',
//			20 => 'noLimit'
		);
		foreach ($itemList as $key => $value) {
			$selected = (t3lib_div::_GP('time') == $key) ? ' selected="selected" ' : '';
			$title = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:' . $value);
			$out .= '<option value="' . $key . '"' . $selected . '>' . htmlspecialchars($title) . '</option>';
		}

		return $out;
	}

	/**
	 * Create a link to show a record
	 * Either to link to backend module or with page module
	 *
	 * @param	string		$linkText: Text to be linked
	 * @param	int		$id: Record's id
	 * @return	html link
	 */
	protected function recent_linkLayoutModule($linkText, $id) {
		$link = '';
		$linkText = htmlspecialchars($linkText);
		$id = intval($id);

		if (1==1 || is_a($this->pObj,'SC_mod_user_task_index')) { //@todo: check & fix that
			$link = '<a href="mod.php?M=user_task&SET[function]=taskcenter_recent.tx_taskcenterrecent_task&display=' . $id .'" onClick="this.blur();">' . $linkText . '</a>';
		} else {
			$link = 'b<a target="_top" href="'.$GLOBALS['BACK_PATH'].'sysext/cms/layout/db_layout.php?id='.$id.'" onClick="this.blur();">' . $linkText . '</a>';
		}
		return $link;
	}

	/**
	 * Create a link to edit a record
	 *
	 * @param	string		$linkText: Text to be linked
	 * @param	string		$table: Record's table
	 * @param	int		$id: Record's id
	 * @return	html link
	 */
	protected function recent_linkEdit($linkText, $table, $id) {
		$link = '';;
		$table = htmlspecialchars($table);
		$id = intval($id);

		// @todo: can this be really removed, editing always via editOnClick ?!
		if (is_a($this->pObj,'SC_mod_user_task_index')) {
			$params = '&edit[' . $table . '][' . $id . ']=edit';
			$onclick = htmlspecialchars(t3lib_BEfunc::editOnClick($params, $GLOBALS['BACK_PATH'], 'dummy.php'));
			$link = 'a<a href="#" onclick="list_frame.' . $onclick . '">' . $linkText . '</a>';
		} else {
//			$str = '<a target="_top" href="mod.php?M=user_task&SET[function]=tx_taskcenterrecent&display='.$id.'" onClick="this.blur();">'.$str.'</a>';
			$params = '&edit[' . $table . '][' . $id . ']=edit';
			$onclick = htmlspecialchars(t3lib_BEfunc::editOnClick($params, $GLOBALS['BACK_PATH']));
			$link = '<a href="#" onclick="' . $onclick . '">' . $linkText . '</a>';
		}

		return $link;
	}

	/**
	 * Get the query resource for the recent page records of a given user
	 *
	 * @param	int		$userId: User uid
	 * @return	resource mysql resource
	 */
	protected function getRecentResPointer($userId) {
		$where = 'pages.module="" AND pages.doktype < 200 AND sys_log.userid=' . intval($userId) .
					$this->logWhere . ' AND ' . $this->taskObject->perms_clause;

			// versioning
		if (t3lib_extMgm::isLoaded('version')) {
			$where .= ' AND pages.t3ver_wsid=' . $GLOBALS['BE_USER']->workspace;
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'sys_log.event_pid, MAX(sys_log.tstamp) AS tstamp_MAX',
			'sys_log INNER JOIN pages ON pages.uid = sys_log.event_pid',
			$where,
			 'sys_log.event_pid',
			 'tstamp_MAX DESC',
			 $this->numberOfRecent
		 );

		return $res;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/taskcenter_recent/task/class.tx_taskcenterrecent_task.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/taskcenter_recent/task/class.tx_taskcenterrecent_task.php']);
}