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
		$out = $iframe = '';
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
			$out .= $this->getUserSelection();
			$userId = ($GLOBALS['BE_USER']->isAdmin() && intval(t3lib_div::_GP('user')) > 0) ? intval(t3lib_div::_GP('user')) : $GLOBALS['BE_USER']->user['uid'];

			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_log.*, max(sys_log.tstamp) AS tstamp_MAX',
				'sys_log,pages',
				'pages.uid=sys_log.event_pid AND sys_log.userid=' . $userId . $this->logWhere .
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

				// build the table
			$out .= '<table style="width:100%" id="record-table" border="0" cellpadding="1" cellspacing="1" class="typo3-recent-edited">
						<thead>
							<tr class="bgColor5">
								<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_general.xml:LGL.title') . '</th>
								<th>' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_file_list.xml:c_tstamp') . '</th>
							</tr>
						</thead>
						' . implode('', $lines) . '
					</table>';

			return $out.$iframe;
		}
	}

	protected function getUserSelection() {
		$out = '';

			// restricted to admins only
		if (!$GLOBALS['BE_USER']->isAdmin()) {
			return $out;
		}

			// get all users and remove own record
		$users = t3lib_BEfunc::getUserNames();
		unset($users[$GLOBALS['BE_USER']->user['uid']]);

		if (count($users) > 0) {
			$out .= '<form action="" method="post"><select name="user" onchange="this.form.submit();">
						<option value="0"></option>';
			foreach($users as $id => $user) {
				$selected = (t3lib_div::_GP('user') == $id) ? ' selected="selected" ' : '';
				$out .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($user['username']) . '</option>';
			}
			$out .= '</select></form>';
		}

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