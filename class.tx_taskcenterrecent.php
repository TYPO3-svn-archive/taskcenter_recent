<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
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
 * @author	Kasper Skårhøj <kasper@typo3.com>
 */

class tx_taskcenterrecent extends mod_user_task {
	var $numberOfRecent=6;
	var $numberOfRecentAll=20;
	var $logWhere=" AND sys_log.event_pid>0 AND sys_log.type=1 AND sys_log.action=2 AND sys_log.error=0";

	/**
	 * Makes the content for the overview frame...
	 */
	function overview_main()	{
		$mC = $this->accessMod("web_layout") ? $this->renderRecentList() : "";
		$icon = '<img src="'.$this->backPath.t3lib_extMgm::extRelPath('taskcenter_recent').'ext_icon.gif" width="18" height="16" class="absmiddle" alt="" />';
		return $this->mkMenuConfig($icon.'&nbsp;'.$this->headLink('tx_taskcenterrecent',1),'',$mC);
	}
	function main() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		return $this->renderRecent();
	}

	//TODO: linkene skal kalde tilbage til modulet med en parameter der får den til at åbne editering i en iframe.

	// ************************
	// RECENT
	// ***********************
	function renderRecentList()	{
		global $LANG;

		$res = $this->getRecentResPointer($this->BE_USER->user['uid']);
		$lines=array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$pageRow = t3lib_BEfunc::getRecord('pages',$row['event_pid']);
			if (is_array($pageRow))	{
				$path = t3lib_BEfunc::getRecordPath ($pageRow['uid'],$this->perms_clause,$this->BE_USER->uc['titleLen']);
				$lines[]='<nobr>'.t3lib_iconworks::getIconImage('pages',$pageRow,$this->backPath,'hspace="2" align="top" title="'.htmlspecialchars($path).' - '.t3lib_BEfunc::titleAttribForPages($pageRow,"",0).'"').$this->recent_linkLayoutModule($this->fixed_lgd($pageRow["title"]),$pageRow["uid"]).'</nobr><br />';
			}
		}
		$out = implode("",$lines);
		
		return $out;
	}
	function renderRecent()	{
		global $LANG, $TCA;
		$out = $this->pObj->doc->section($LANG->getLL("recent_allRecs"),$this->_renderRecent(),0,1);
		return $out;
	}
	function _renderRecent()	{
		global $LANG, $TCA;
// 		if($id = t3lib_div::_GP('display')) {
// 			return $this->urlInIframe($this->backPath.'sysext/cms/layout/db_layout.php?id='.$id,1);
// 		} else {
			$id = t3lib_div::_GP('display');
			$iframe .= $this->urlInIframe($this->backPath.'sysext/cms/layout/db_layout.php?id='.$id);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'sys_log.*, max(sys_log.tstamp) AS tstamp_MAX', 
						'sys_log,pages', 
						'pages.uid=sys_log.event_pid AND sys_log.userid='.intval($this->BE_USER->user['uid']).
							$this->logWhere.
							' AND '.$this->perms_clause,
						'tablename,recuid',
						'tstamp_MAX DESC',
						$this->numberOfRecentAll
					);
			$lines = array();
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$elRow = t3lib_BEfunc::getRecord($row['tablename'],$row['recuid']);
				if (is_array($elRow))	{
					$path = t3lib_BEfunc::getRecordPath ($elRow['pid'],$this->perms_clause,$this->BE_USER->uc['titleLen']);
					$lines[] = '
				<tr>
					<td class="bgColor4">'.$this->recent_linkEdit('<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/edit2.gif','width="11" height="12"').' alt="" />',$row['tablename'],$row['recuid']).'</td>
					<td class="bgColor4">'.$this->recent_linkEdit(t3lib_iconworks::getIconImage($row['tablename'],$elRow,$this->backPath,'class="c-recicon" title="'.htmlspecialchars($path).'"').htmlspecialchars($this->fixed_lgd($elRow[$TCA[$row['tablename']]['ctrl']['label']])),$row['tablename'],$row['recuid']).'&nbsp;</td>
					<td class="bgColor4">'.$this->dateTimeAge($row['tstamp_MAX']).'</td>
				</tr>';
			}
		}
			$out = implode('',$lines);
			$out = '
		
		<!--
			Table with a listing of recently edited records. 
			The listing links to the editform loaded with each record
		-->
		<table id="record-table" border="0" cellpadding="1" cellspacing="1" class="typo3-recent-edited">
			'.$out.'
		</table>';
			return $out.$iframe;
// 		}
	}
	function recent_linkLayoutModule($str,$id)	{
		$str = '<a href="index.php?SET[function]=tx_taskcenterrecent&display='.$id.'" onClick="this.blur();">'.$str.'</a>';

#		$str='<a href="'.$this->backPath.'sysext/cms/layout/db_layout.php?id='.$id.'" onClick="this.blur();">'.htmlspecialchars($str).'</a>';
		return $str;
	}
	function recent_linkEdit($str,$table,$id)	{
#		$params = '&edit['.$table.']['.$id.']=edit';
		$str = '<a href="index.php?SET[function]=tx_taskcenterrecent&display='.$id.'" onClick="this.blur();">'.$str.'</a>';
#		$str='<a href="#" onclick="'.htmlspecialchars(t3lib_BEfunc::editOnClick($params,$GLOBALS['BACK_PATH'])).'">'.$str.'</a>';
		return $str;
	}
	function getRecentResPointer($be_user_id)	{
		return $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'sys_log.*, max(sys_log.tstamp) AS tstamp_MAX', 
						'sys_log,pages', 
						'pages.uid=event_pid
							 AND sys_log.userid='.intval($be_user_id).
							 $this->logWhere.'
							 AND pages.module=""
							 AND pages.doktype < 200
							 AND '.$this->perms_clause,
						'sys_log.event_pid',
						'tstamp_MAX DESC',
						$this->numberOfRecent
					);
	}
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/taskcenter_recent/class.tx_taskcenterrecent.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/taskcenter_recent/class.tx_taskcenterrecent.php"]);
}

?>