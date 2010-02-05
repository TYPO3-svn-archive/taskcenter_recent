<?php


class tx_taskcenterrecent_task implements tx_taskcenter_Task {
	var $numberOfRecent = 6;
	var $numberOfRecentAll = 20;
	var $logWhere = ' AND sys_log.event_pid>0 AND sys_log.type=1 AND sys_log.action=2 AND sys_log.error=0';

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
		$content = $this->taskObject->description($GLOBALS['LANG']->getLL('mod_recent'), $GLOBALS['LANG']->getLL('link_allRecs'));
		$content .= $this->renderRecent();

		return $content;
	}


	public function getOverview() {
		$out = '';
		$lines = array();

		$res = $this->getRecentResPointer($GLOBALS['BE_USER']->user['uid']);

		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pageRow = t3lib_BEfunc::getRecord('pages', $row['event_pid']);
			if (is_array($pageRow)) {
				$path	= t3lib_BEfunc::getRecordPath($pageRow['uid'], $this->taskObject->perms_clause, $GLOBALS['BE_USER']->uc['titleLen']);
				$title	= htmlspecialchars($path) . ' - ' . t3lib_BEfunc::titleAttribForPages($pageRow, '', 0);
				$icon	= t3lib_iconworks::getIconImage('pages', $pageRow, $GLOBALS['BACK_PATH'], 'hspace="2" align="top" title="' . $title . '"');

				$lines[] = $icon . $this->recent_linkLayoutModule($pageRow['title'], $pageRow['uid']);
			}
		}

		$GLOBALS['TYPO3_DB']->sql_free_result($res);

			// if any records found
		if ($lines) {
			$lines[] = '<br /><em>' . $this->recent_linkLayoutModule($GLOBALS['LANG']->getLL('link_allRecs'),'').'</em>';

			$out = '<div style="margin:3px"> ' . implode('<br />', $lines) . '</div>';
		}

		return $out;
	}
	protected function renderRecent() {
		$out = $this->taskObject->doc->section($GLOBALS['LANG']->getLL("recent_allRecs"), $this->_renderRecent(), 0, 1);
		return $out;
	}

	// @todo: rename
	protected function _renderRecent() {
		$content = $iframe = '';
		$id = intval(t3lib_div::_GP('display'));
		if($id > 0) {
			// @todo: fix path
			return $this->taskObject->urlInIframe($GLOBALS['BACK_PATH'] . 'sysext/cms/layout/db_layout.php?id=' . $id, 1);
		} else {
			// @todo: fix path
			if (is_a($this->pObj,'SC_mod_user_task_index')) {
				$iframe .= $this->urlInIframe('');
			}

				// get the documents of a different user
				// todo: add label
			$content .= $this->getUserSelection();
			
				// extend where clause
			if ($GLOBALS['BE_USER']->isAdmin()) {
				$selectUsers = array();
				$selUser = t3lib_div::_GP('user');

				if (substr($selUser, 0, 3) == 'gr-') {	// groups
					$where = ' AND ' . $GLOBALS['TYPO3_DB']->listQuery('usergroup_cached_list', intval(substr($selUser,3)), 'be_users');
					$records = t3lib_BEfunc::getUserNames('username,uid', $where);
					foreach ($records as $record) {
						$selectUsers[] = $record['uid'];
					}
					$selectUsers[] = 0;
					$this->logWhere.= ' AND sys_log.userid IN (' . implode($selectUsers, ',') . ')';
				} elseif (substr($selUser,0,3) == 'us-')	{	// users
					$selectUsers[] = intval(substr($selUser,3));
					$this->logWhere.= ' AND sys_log.userid in ('.implode($selectUsers,',').')';
				} elseif ($selUser == -1) {
					// do nothing, any user
				} else {
					$this->logWhere .= ' AND sys_log.userid=' . $GLOBALS['BE_USER']->user['uid'];	// Self user
				}

			} else {
				$this->logWhere .= ' AND sys_log.userid=' . $GLOBALS['BE_USER']->user['uid'];	// Self user
			}

			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_log.*, max(sys_log.tstamp) AS tstamp_MAX',
				'sys_log,pages',
				'pages.uid=sys_log.event_pid ' . $this->logWhere .
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
			$zebra = 0;
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// get the single record
				$elRow = t3lib_BEfunc::getRecord($row['tablename'], $row['recuid']);

				if (is_array($elRow)) {
					$path = t3lib_BEfunc::getRecordPath($elRow['pid'], $this->taskObject->perms_clause, $GLOBALS['BE_USER']->uc['titleLen']);
					$editIcon = t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="11" height="12"');
					$recordIcon = t3lib_iconworks::getIconImage($row['tablename'], $elRow, $GLOBALS['BACK_PATH'], 'class="c-recicon" title="' . htmlspecialchars($path) . '"');
					$recordTitle = htmlspecialchars($elRow[$GLOBALS['TCA'][$row['tablename']]['ctrl']['label']]);
					$toggleClass = ($zebra++ % 2 == 0) ? 'bgColor4' : 'bgColor6';

					$editIcon = t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/i/pages_up.gif', 'width="11" height="12"');
					$recordIcon = $this->taskObject->doc->wrapClickMenuOnIcon($recordIcon, $row['tablename'], $row['recuid'], 0);
//					$this->recent_linkEdit('<img' . $editIcon . ' alt="" />', $row['tablename'], $row['recuid'])
					$lines[] = '
				<tr class="' . $toggleClass . '">
					<td>' . $recordIcon . $recordTitle . '&nbsp;</td>
					<td>' . t3lib_BEfunc::dateTimeAge($row['tstamp_MAX']) . '</td>
				</tr>';
				}
			}

			$GLOBALS['TYPO3_DB']->sql_free_result($res);

			if (count($lines) > 0) {

					// build the table
				$content .= '<table style="width:100%" id="record-table" border="0" cellpadding="1" cellspacing="1" class="typo3-recent-edited">
							<thead>
								<tr class="bgColor5">
									<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_general.xml:LGL.title') . '</th>
									<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_file_list.xml:c_tstamp') . '</th>
								</tr>
							</thead>
							' . implode('', $lines) . '
						</table>';
			} else {
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

	protected function getUserSelection() {
		$out = $options = '';

			// restricted to admins only
		if (!$GLOBALS['BE_USER']->isAdmin()) {
			return $out;
		}

			// get all users and remove own record
		$users = t3lib_BEfunc::getUserNames();
		$titlePrefix = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:users') . ' ';
		$options .= '<option disabled="disabled">' . $titlePrefix . '</option>';
		unset($users[$GLOBALS['BE_USER']->user['uid']]);
		foreach($users as $id => $user) {
			$id = 'us-' . $id;
			$selected = (t3lib_div::_GP('user') == $id) ? ' selected="selected" ' : '';
			$options .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($titlePrefix . $user['username']) . '</option>';
		}

			// get all user groups
		$usergroups = t3lib_BEfunc::getGroupNames();
		$titlePrefix = $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:userGroup') . ' ';
		$options .= '<option disabled="disabled">' . $titlePrefix . '</option>';
		foreach($usergroups as $id => $group) {
			$id = 'gr-' . $id;
			$selected = (t3lib_div::_GP('user') == $id) ? ' selected="selected" ' : '';
			$options .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($titlePrefix . $group['title']) . '</option>';
		}

		$out .= '<form action="" method="post">
					<label for="select_user">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:cm.select') . '</label>
					<select id="select_user" name="user" onchange="this.form.submit();">
						<option value="0">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:self') . '</option>
						<option value="-1">' . $GLOBALS['LANG']->sL('LLL:EXT:belog/mod/locallang.xml:any') . '</option>' .
						$options .
					'</select>
				</form>
				<br />';


		return $out;

	}

	protected function recent_linkLayoutModule($linkText, $id) {
		$link = '';
		$linkText = htmlspecialchars($linkText);
		$id = intval($id);

		if (is_a($this->pObj,'SC_mod_user_task_index')) { //@todo: check & fix that
			$link = 'a<a href="index.php?M=tools_txtaskcenterM1&SET[function]=taskcenter_recent.tasks&display=' . $id .'" onClick="this.blur();">' . $linkText . '</a>';
		} else {
			$link = 'b<a target="_top" href="'.$GLOBALS['BACK_PATH'].'sysext/cms/layout/db_layout.php?id='.$id.'" onClick="this.blur();">' . $linkText . '</a>';
		}
		return $link;
	}

	protected function recent_linkEdit($linkText, $table, $id) {
		$link = '';;
		$table = htmlspecialchars($table);
		$id = intval($id);

		// @todo: can this be really removed, editing always via editOnClick ?!
//		if (is_a($this->pObj,'SC_mod_user_task_index')) {
//			$params = '&edit[' . $table . '][' . $id . ']=edit';
//			$onclick = htmlspecialchars(t3lib_BEfunc::editOnClick($params, $GLOBALS['BACK_PATH'], 'dummy.php'));
//			$link = 'a<a href="#" onclick="list_frame.' . $onclick . '">' . $linkText . '</a>';
//		} else {
//			$str = '<a target="_top" href="index.php?SET[function]=tx_taskcenterrecent&display='.$id.'" onClick="this.blur();">'.$str.'</a>';
			$params = '&edit[' . $table . '][' . $id . ']=edit';
			$onclick = htmlspecialchars(t3lib_BEfunc::editOnClick($params, $GLOBALS['BACK_PATH']));
			$link = '<a href="#" onclick="' . $onclick . '">' . $linkText . '</a>';
//		}
		return $link;
	}


	protected function getRecentResPointer($userId) {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'sys_log.*, max(sys_log.tstamp) AS tstamp_MAX',
			'sys_log,pages',
			'pages.uid=event_pid AND sys_log.userid='.intval($userId) .
				$this->logWhere . ' AND pages.module="" AND pages.doktype < 200 AND ' . $this->taskObject->perms_clause,
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