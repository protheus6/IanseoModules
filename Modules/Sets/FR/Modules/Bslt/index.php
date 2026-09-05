<?php

require_once(dirname(__FILE__, 5) . '/config.php');
require_once('Common/Lib/CommonLib.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Various.inc.php');
require_once('Common/Fun_Sessions.inc.php');
CheckTourSession(true);
checkFullACL(AclQualification, '', AclReadWrite);




$IncludeJquery = true;
	$JS_SCRIPT=array(
		'<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Common/ajax/ObjXMLHttpRequest.js"></script>',
		'<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Common/js/Fun_JS.inc.js"></script>',
		'<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Qualification/Fun_AJAX_index.js"></script>',
		'<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Qualification/Fun_JS.js"></script>',
		'<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Qualification/index.js"></script>',
		'<script type="text/javascript" src="./index.js.php"></script>',
		'<script type="text/javascript" src="./index.js"></script>',
		'<link href="'.$CFG->ROOT_DIR.'Qualification/index.css" media="screen" rel="stylesheet" type="text/css" />',
		phpVars2js(array(
			'CmdPostUpdate'=>get_text('CmdPostUpdate'),
			'PostUpdating'=>get_text('PostUpdating'),
			'PostUpdateEnd'=>get_text('PostUpdateEnd'),
			'RootDir'=>$CFG->ROOT_DIR.'Qualification/',
			'MsgAreYouSure' => get_text('MsgAreYouSure'),
			'MsgWent2Home' => get_text('Went2Home', 'Tournament'),
			'MsgBackFromHome' => get_text('BackFromHome', 'Tournament'),
			'MsgSetDSQ' => get_text('Set-DSQ', 'Tournament'),
            'MsgUnsetDSQ' => get_text('Unset-DSQ', 'Tournament'),
            'TxtIrmTitle' => get_text('IrmStatus', 'Tournament'),
            'TxtIrmDns' => get_text('IRM-10', 'Tournament'),
            'TxtIrmDnf' => get_text('IRM-5', 'Tournament'),
            'TxtIrmDnfNoRank' => get_text('IRM-7', 'Tournament'),
            'TxtIrmUnset' => get_text('CmdUnset', 'Tournament', ''),
            'TxtCancel' => get_text('CmdCancel'),
		)),
	);
$PAGE_TITLE= "Beursault - Saisie Score";
include('Common/Templates/head.php');


	$Select
		= "SELECT ToId,ToNumSession,ToNumDist AS TtNumDist,ToGolds AS TtGolds,ToXNine AS TtXNine "
		. "FROM Tournament "
		. "WHERE ToId=" . StrSafe_DB($_SESSION['TourId']) . " ";
	$RsTour=safe_r_sql($Select);

	$RowTour=NULL;
	$ComboSes='';
	$TxtFrom='';
	$TxtTo='';
	$ComboDist='';
	$ChkG='';
	$ChkX='';
	if (safe_num_rows($RsTour)==1) {
		$RowTour=safe_fetch($RsTour);

		$ComboSes = '<select name="x_Session" id="x_Session" onChange="SelectSession();">';
		$ComboSes.= '<option value="-1">---</option>';



		foreach (GetSessions('Q') as $ses) {
            $ComboSes .= '<option value="' . $ses->SesOrder . '"' . ((isset($_REQUEST['x_Session']) AND $_REQUEST['x_Session'] == $ses->SesOrder) ? ' selected' : '') . '>' . $ses->Descr . '</option>';
        }
		$ComboSes.= '</select>';

		$TxtFrom = '<input type="text" name="x_From" id="x_From" size="5" maxlength="' . (TargetNoPadding +1) . '" value="' . (isset($_REQUEST['x_From']) ? $_REQUEST['x_From'] : '') . '">';
		$TxtTo = '<input type="text" name="x_To" id="x_To" size="5" maxlength="' . (TargetNoPadding +1) . '" value="' . (isset($_REQUEST['x_To']) ? $_REQUEST['x_To'] : '') . '">';
		
		if(empty($_REQUEST['x_To']) AND !empty($_REQUEST['x_From'])) {
            $_REQUEST['x_To'] = $_REQUEST['x_From'];
        }

		if (isset($_REQUEST['x_Arrows']) AND $_REQUEST['x_Arrows']==2 AND isset($_REQUEST['Command']) AND $_REQUEST['Command']=='OK' AND $_REQUEST['x_Session']>0 AND $_REQUEST['x_Dist']>0 AND !IsBlocked(BIT_BLOCK_QUAL)) {
			$v=0;
			if (isset($_REQUEST['x_AllArrows']) AND preg_match('/^[0-9]{1,4}$/i',$_REQUEST['x_AllArrows'])) {
				$v=	$_REQUEST['x_AllArrows'];

				$TargetFilter = "AND QuSession=".StrSafe_DB($_REQUEST['x_Session'])." AND QuTarget>=" . intval($_REQUEST['x_From']) . " AND QuTarget<=" . intval($_REQUEST['x_To']);
				$Where = "WHERE EnTournament=" . StrSafe_DB($_SESSION['TourId']) . " AND QuSession=" . StrSafe_DB($_REQUEST['x_Session']) . " AND EnStatus<=1 " . $TargetFilter . " ";
				$query = "UPDATE Qualifications INNER JOIN Entries ON EnId=QuId SET QuD" . $_REQUEST['x_Dist'] ."Hits=" . StrSafe_DB($v) . " " . $Where;
				$rs=safe_w_sql($query);

			// somma
				$query = "UPDATE Qualifications INNER JOIN Entries ON EnId=QuId  SET QuHits=QuD1Hits+QuD2Hits+QuD3Hits+QuD4Hits+QuD5Hits+QuD6Hits+QuD7Hits+QuD8Hits " . $Where;
				//	print $query;
				$rs=safe_w_sql($query);

			// per evitare puttanate riporto a zero la combo delle frecce
				unset($_REQUEST['x_Arrows']);
			}
		}


?>
<?php print prepareModalMask('PostUpdateMask','<div align="center" style="font-size: 20px; font-weight: bold;"><br/><br/><br/><br/><br/>'.get_text('PostUpdating').'</div>');?>

<form name="FrmParam" method="POST" action="">
<input type="hidden" name="Command" value="OK">
<input type="hidden" name="xxx" id="Command">
<input type="hidden" name="x_Gold" id="x_Gold" value="1">
<input type="hidden" name="x_Arrows" id="x_Arrows" value="0">
<input type="hidden" name="x_Dist" id="x_Dist" value="1">
<table class="Tabella">
<TR><TH class="Title" colspan="8">Beursault Saisie des scores</TH></TR>
<tr class="Divider"><TD colspan="8"></TD></tr>
<tr>
<th width="5%"><?php print get_text('Session');?></th>
<th width="8%"><?php print get_text('From','Tournament');?></th>
<th width="8%"><?php print get_text('To','Tournament');?></th>
<th width="5%">&nbsp;</th>
<th>&nbsp;</th>
</tr>
<tr>
<td class="Center"><?php print $ComboSes; ?></td>
<td class="Center"><?php print $TxtFrom; ?></td>
<td class="Center"><?php print $TxtTo; ?></td>
<td><input type="submit" value="<?php print get_text('CmdOk');?>"></td>
<td>
</td>
</tr>
<tr class="Divider"><td colspan="8"></td></tr>

<tr class="Divider"><td colspan="8" class="Bold">
	<span id="idPostUpdateMessage"></span>
</td></tr>
</table>
</form>
<br>
<?php
if (isset($_REQUEST['Command']) AND $_REQUEST['Command']=='OK' AND $_REQUEST['x_Session']!=-1 AND $_REQUEST['x_Dist']!=-1) {
    if(!empty($_REQUEST['x_Target'])) {
        $TargetFilter = "AND QuTarget=" . intval(substr($_REQUEST['x_Target'],0,-1));
    } else {
        $TargetFilter = "AND QuTarget>=" . intval($_REQUEST['x_From']) . " AND QuTarget<=" . intval($_REQUEST['x_To']);
    }

    $atSql = createAvailableTargetSQL($_REQUEST['x_Session'], $_SESSION['TourId']);
    $Select = "SELECT QuArrow, EnId,EnCode,EnName,EnFirstName,EnTournament,EnDivision,EnClass,EnCountry,CoCode, (EnStatus <=1) AS EnValid,EnStatus,
            QuSession, CONCAT(QuTarget, QuLetter) AS Target,
            QuD" . $_REQUEST['x_Dist'] . "Score AS SelScore,QuD" . $_REQUEST['x_Dist'] . "Hits AS SelHits,QuD" . $_REQUEST['x_Dist'] . "Gold AS SelGold,QuD" . $_REQUEST['x_Dist'] . "Xnine AS SelXNine,
            QuScore, QuHits,	QuGold,	QuXnine, QuClRank, QuIrmType, IrmType,
            ToId,ToType,ToNumDist AS TtNumDist
        FROM Entries
        INNER JOIN Countries ON EnCountry=CoId
        INNER JOIN Qualifications ON EnId=QuId
        INNER JOIN IrmTypes ON IrmId=QuIrmType
        RIGHT JOIN ($atSql) at ON QuSession=FullTgtSession AND QuTarget=FullTgtTarget AND QuLetter=FullTgtLetter
        INNER JOIN Tournament ON EnTournament=ToId AND ToId=" . StrSafe_DB($_SESSION['TourId']) . "
        WHERE EnTournament=" . StrSafe_DB($_SESSION['TourId']) . " AND EnAthlete=1 AND QuSession!=0 AND QuTarget!=0 AND QuSession=" . StrSafe_DB($_REQUEST['x_Session']) . " $TargetFilter 
        ORDER BY QuSession ASC, QuTarget ASC, QuLetter ASC ";

    $Rs=safe_r_sql($Select);
    // form elenco persone
    if (safe_num_rows($Rs)>0) {
        echo '<form name="Frm" method="POST" action="">';
        echo '<table class="Tabella">';
        echo '<tr>';
        echo '<td class="Title w-5" nowrap="nowrap">Status</td>';
        echo '<td class="Title w-5">'.get_text('Target').'</td>';
        echo '<td class="Title w-5">'.get_text('Code','Tournament').'</td>';
        echo '<td class="Title w-20">'.get_text('Archer').'</td>';
        echo '<td class="Title w-5">'.get_text('Country').'</td>';
        
        
        echo '<td class="Title w-5">1</td>';
        echo '<td class="Title w-5">2</td>';
        echo '<td class="Title w-5"><a class="LinkRevert" href="javascript:ChangeGoldXNine(\'OK\');">'.$RowTour->TtXNine.'</a></td>';
        echo '<td class="Title w-5"><a class="LinkRevert" href="javascript:ChangeGoldXNine(\'OK\');">'.$RowTour->TtGolds.'</a></td>';
        
        echo '<td class="Title w-5">'.get_text('TargetsHit', 'RunArchery').'</td>';
        
        echo '<td class="Title w-5">Score</td>';    
        
        echo '<td class="Title w-5">'.$RowTour->TtXNine.'</td>';
        echo '<td class="Title w-5">'.$RowTour->TtGolds.'</td>';
        echo '<td class="Title w-5">Score</td>';
        
        
        echo '</tr>';

        $CurTarget = 'xx';
        $TarStyle='';	// niene oppure warning se $RowStyle==''
        // elenco persone
        while ($MyRow=safe_fetch($Rs)) {
            $RowStyle='';	// NoShoot oppure niente
            if(!$MyRow->EnValid) {
                $RowStyle='NoShoot';
            }

            if ($CurTarget!='xx') {
                if ($CurTarget!=substr($MyRow->Target,0,-1) ) {
                    if ($TarStyle=='') {
                        $TarStyle='warning';
                    } elseif($TarStyle=='warning') {
                        $TarStyle='';
                    }
                }
            }
            
            
            $nb2 = ($MyRow->QuScore - (4 * $MyRow->SelGold) - (3 * $MyRow->SelXNine)) - ($MyRow->QuArrow - $MyRow->SelGold - $MyRow->SelXNine);
            $nb1 = $MyRow->QuArrow - $MyRow->SelGold - $MyRow->SelXNine - $nb2;
            
           
            
            $styleError = "";
            if($nb1 < 0 || $nb2 < 0  )
            {
                $styleError = "outline: 2px solid red;";
                 $nb1 = "";
                 $nb2 = "";
            }
            
            
            echo '<tr id="Row_'.$MyRow->EnId.'" class="'.$TarStyle.' '.$RowStyle.' Irm-'.$MyRow->QuIrmType.'" style="'.$styleError.'">';
            echo '<td class="Center" nowrap="nowrap" id="TD_'.$MyRow->EnId.'">';
            if($MyRow->QuIrmType) {
                echo '<div ref="'.$MyRow->QuIrmType.'">'.$MyRow->IrmType.'</div>';
            } else {
                echo '<div ref="'.$MyRow->QuIrmType.'"></div>';
            }

            echo '</td>';
            echo '<td>'.$MyRow->Target.'</td>';
            echo '<td>'.$MyRow->EnCode.'</td>';
            echo '<td>'.$MyRow->EnFirstName . ' ' . $MyRow->EnName.' ('.$MyRow->EnClass.$MyRow->EnDivision.' )</td>';
            echo '<td>'.$MyRow->CoCode.'</td>';
            echo '<td><input type="text" size="4" maxlength="5" name="d_Qu_1_' . $MyRow->EnId . '" id="d_Qu_1_' . $MyRow->EnId . '" value="'.$nb1.'" onBlur="CalcQuals(' . $MyRow->EnId . ')"></td>';
            echo '<td><input type="text" size="4" maxlength="5" name="d_Qu_2_' . $MyRow->EnId . '" id="d_Qu_2_' . $MyRow->EnId . '" value="'.$nb2.'" onBlur="CalcQuals(' . $MyRow->EnId . ')"></td>';
            echo '<td class="Center"><input type="text" size="4" maxlength="5" name="d_QuD' . $_REQUEST['x_Dist'] . 'Xnine_' . $MyRow->EnId . '" id="d_QuD' . $_REQUEST['x_Dist'] . 'Xnine_' . $MyRow->EnId . '" value="' . $MyRow->SelXNine . '" onBlur="CalcQuals(' . $MyRow->EnId . ')" ></td>';
            echo '<td class="Center"><input type="text" size="4" maxlength="5" name="d_QuD' . $_REQUEST['x_Dist'] . 'Gold_' . $MyRow->EnId . '" id="d_QuD' . $_REQUEST['x_Dist'] . 'Gold_' . $MyRow->EnId . '" value="' . $MyRow->SelGold . '" onBlur="CalcQuals(' . $MyRow->EnId . ')" ></td>';
            
            echo '<td class="Center"><input type="text" size="4" maxlength="5" name="d_QuArrow_' . $MyRow->EnId . '" id="d_QuArrow_' . $MyRow->EnId . '" value="' . $MyRow->QuArrow . '"  disabled></td>';
            
            echo '<td class="Center"><input type="text" size="4" maxlength="5" name="d_QuD' . $_REQUEST['x_Dist'] . 'Score_' . $MyRow->EnId . '" id="d_QuD' . $_REQUEST['x_Dist'] . 'Score_' . $MyRow->EnId . '" value="' . $MyRow->SelScore . '" disabled ></td>';
            
            
          
?>


<td class="Center Bold">
<div id="idXNine_<?php print $MyRow->EnId; ?>"><?php print $MyRow->QuXnine; ?></div>
</td>
<td class="Center Bold">
<div id="idGold_<?php print $MyRow->EnId; ?>"><?php print $MyRow->QuGold; ?></div>
</td>
<td class="Center Bold" onDblClick="javascript:window.open('<?php echo $CFG->ROOT_DIR.'Qualification/' ?>WriteScoreCard.php?Command=OK&x_Session=<?php print $_REQUEST['x_Session']; ?>&x_Dist=<?php print $_REQUEST['x_Dist']; ?>&x_Target=<?php print $MyRow->Target; ?>',<?php print $MyRow->EnId; ?>);">
<div id="idScore_<?php print $MyRow->EnId; ?>"><?php print $MyRow->QuScore; ?></div>
</td>
</tr>
<?php
					$CurTarget=	substr($MyRow->Target,0,-1);
				}	// fine elenco persone
?>
</table>
</form>
<?php
			}	// fine form elenco persone
		}
	}
	if(!empty($GoBack)) {
		echo '<table class="Tabella2" width="50%"><tr><th style="background-color:red">
			<a href="'.$GoBack.'" style="color:white">'.get_text('BackBarCodeCheck','Tournament').'</a>
			&nbsp;&nbsp;-&nbsp;&nbsp;
			<a href="'.$GoBack.'&C='.$_GET['B'].'" style="color:white">'.get_text('Confirm','Tournament').'</a>
			</th></tr></table>';
	}
	?>
<div id="idOutput"></div>
<?php
	include('Common/Templates/tail.php');
?>
