<?php
/**
 * PrnAlphabeticalTAE.php
 * Liste alphabétique filtrée par Classes[] (TAE TAEDI / TAEDN).
 * Copie locale de getStartListAlphaQuery() + getStartListAlphabetical()
 * avec support du paramètre Classes[] en plus du Divisions LIKE existant.
 * Uniquement utilisé depuis Modules/Custom/Prints/index.php.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
checkFullACL(AclParticipants, 'pEntries', AclReadOnly);
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

// ── Copie locale de getStartListAlphaQuery() + filtre Classes[] ──────────────
function getStartListAlphaQueryTAE($ORIS=false, $Athlete=false) {
	$TmpWhere="";
	if(isset($_REQUEST["ArcherName"]) && preg_match("/^[-,0-9A-Z]*$/i",str_replace(" ","",$_REQUEST["ArcherName"]))) {
		foreach(explode(",",$_REQUEST["ArcherName"]) as $Value) {
			$Tmp=NULL;
			if(preg_match("/^([0-9A-Z]*)\-([0-9A-Z]*)$/i",str_replace(" ","",$Value),$Tmp)) {
				$TmpWhere .= "(EnFirstName >= " . StrSafe_DB(stripslashes($Tmp[1]) ) . " AND EnFirstName <= " . StrSafe_DB(stripslashes($Tmp[2].chr(255))) . ") OR ";
			} else {
				$TmpWhere .= "EnFirstName LIKE " . StrSafe_DB(stripslashes(trim($Value)) . "%") . " OR ";
			}
		}
		$TmpWhere = substr($TmpWhere,0,-3);
	}

	$Collation = '';

	$MyQuery = "SELECT distinct
			upper(substr(EnFirstname $Collation,1,1)) as FirstLetter,
			SesName,
			EnCode as Bib,
			concat(upper(EnFirstName $Collation), ' ', EnName $Collation) AS Athlete,
			QuSession AS Session,
			CONCAT(QuTarget,QuLetter) AS TargetNo,
			QuTarget AS TargetButt,
			upper(c.CoCode) AS NationCode, upper(c.CoName) AS Nation,
			upper(c2.CoCode) AS NationCode2, upper(c2.CoName) AS Nation2,
			upper(c3.CoCode) AS NationCode3, upper(c3.CoName) AS Nation3,
			DivDescription, ClDescription,
			EnSubTeam, EnClass AS ClassCode, EnDivision AS DivCode,
			DivAthlete and ClAthlete as IsAthlete,
			EnAgeClass as AgeClass,
			EnSubClass as SubClass,
			EnStatus as Status,
			EnIndClEvent AS `IC`,
			EnTeamClEvent AS `TC`,
			EnIndFEvent AS `IF`,
			EnTeamFEvent as `TF`,
			EnTeamMixEvent as `TM`,
			EvCode, EnTimestamp,
			IFNULL(EvCode,CONCAT(TRIM(EnDivision),TRIM(EnClass))) as EventCode,
			DATE_FORMAT(EnDob,'%d %b %Y') as DOB,
			IFNULL(GROUP_CONCAT(EvEventName order by EvProgr SEPARATOR ', '), if(DivAthlete and ClAthlete, CONCAT('|',DivDescription, '| |', ClDescription), ClDescription)) as EventName ,
			IFNULL(GROUP_CONCAT(DISTINCT RankRanking order by EvProgr SEPARATOR ', '), '') as Ranking ,
			TfName,
			concat(DvMajVersion, '.', DvMinVersion) as DocVersion,
			date_format(DvPrintDateTime, '%e %b %Y %H:%i UTC') as DocVersionDate,
			DvNotes as DocNotes,
			'' as Location,
			DiDescription
		FROM Entries AS e
		inner join Tournament on ToId=EnTournament
		LEFT JOIN DocumentVersions on EnTournament=DvTournament AND DvFile = 'EN'
		LEFT JOIN Qualifications AS q ON e.EnId=q.QuId
		left join (select TdTournament, TdClasses, Di1.DiSession, trim('|' from concat(
				if(Td1!='-' and Di1.DiStart>0, concat(Td1, ': ', left(Di1.DiStart, 5)), '')
				, '|', if(Td2!='-' and Di2.DiStart>0, concat(Td2, ': ', left(Di2.DiStart, 5)), '')
				, '|', if(Td3!='-' and Di3.DiStart>0, concat(Td3, ': ', left(Di3.DiStart, 5)), '')
				, '|', if(Td4!='-' and Di4.DiStart>0, concat(Td4, ': ', left(Di4.DiStart, 5)), '')
				, '|', if(Td5!='-' and Di5.DiStart>0, concat(Td5, ': ', left(Di5.DiStart, 5)), '')
				, '|', if(Td6!='-' and Di6.DiStart>0, concat(Td6, ': ', left(Di6.DiStart, 5)), '')
				, '|', if(Td7!='-' and Di7.DiStart>0, concat(Td7, ': ', left(Di7.DiStart, 5)), '')
				, '|', if(Td8!='-' and Di8.DiStart>0, concat(Td8, ': ', left(Di8.DiStart, 5)), '')
				)) as DiDescription from TournamentDistances
			left join DistanceInformation Di1 on Di1.DiTournament=TdTournament and Di1.DiDistance=1
			left join DistanceInformation Di2 on Di2.DiTournament=TdTournament and Di2.DiDistance=2 and Di2.DiSession=Di1.DiSession
			left join DistanceInformation Di3 on Di3.DiTournament=TdTournament and Di3.DiDistance=3 and Di3.DiSession=Di1.DiSession
			left join DistanceInformation Di4 on Di4.DiTournament=TdTournament and Di4.DiDistance=4 and Di4.DiSession=Di1.DiSession
			left join DistanceInformation Di5 on Di5.DiTournament=TdTournament and Di5.DiDistance=5 and Di5.DiSession=Di1.DiSession
			left join DistanceInformation Di6 on Di6.DiTournament=TdTournament and Di6.DiDistance=6 and Di6.DiSession=Di1.DiSession
			left join DistanceInformation Di7 on Di7.DiTournament=TdTournament and Di7.DiDistance=7 and Di7.DiSession=Di1.DiSession
			left join DistanceInformation Di8 on Di8.DiTournament=TdTournament and Di1.DiDistance=8 and Di8.DiSession=Di1.DiSession
			group by TdTournament, TdClasses, Di1.DiSession) Distances on TdTournament=EnTournament and DiSession=QuSession and concat(EnDivision, EnClass) like TdClasses
		LEFT JOIN Individuals on IndId=EnId AND EnTournament=IndTournament
		left join Events on EvCode=IndEvent and EvTournament=EnTournament and EvTeamEvent=0 and EvCodeParent=''
		LEFT JOIN Divisions ON TRIM(EnDivision)=TRIM(DivId) AND EnTournament=DivTournament
		LEFT JOIN Classes ON TRIM(EnClass)=TRIM(ClId) AND EnTournament=ClTournament
		LEFT JOIN Countries AS c ON e.EnCountry=c.CoId AND e.EnTournament=c.CoTournament
		LEFT JOIN Countries AS c2 ON e.EnCountry2=c2.CoId AND e.EnTournament=c2.CoTournament
		LEFT JOIN Countries AS c3 ON e.EnCountry3=c3.CoId AND e.EnTournament=c3.CoTournament
		LEFT JOIN Session on EnTournament=SesTournament and SesType='Q' and SesOrder=QuSession
		LEFT JOIN TargetFaces ON EnTournament=TfTournament AND EnTargetFace=TfId
		LEFT JOIN Rankings on EnTournament=RankTournament and RankEvent=IF(EvWaCategory!='',EvWaCategory,EvCode) and RankTeam=0 and EnCode=RankCode and ToIocCode='FITA' and EnIocCode in ('', 'FITA') and RankIocCode='FITA'
		WHERE EnTournament = " . StrSafe_DB($_SESSION['TourId']) ;
	if(isset($_REQUEST["Session"]) and is_numeric($_REQUEST["Session"])) $MyQuery .= " AND QuSession = " . StrSafe_DB($_REQUEST["Session"]) ;
	if(!empty($_REQUEST["Divisions"])) $MyQuery .= " AND concat(EnDivision, EnClass) like '{$_REQUEST["Divisions"]}'";

	// ── Filtre Classes[] : EnClass IN ('S1F','S2H',…) ────────────────────────
	if(!empty($_REQUEST["Classes"])) {
		$_alphaClasses = is_array($_REQUEST["Classes"]) ? $_REQUEST["Classes"] : array($_REQUEST["Classes"]);
		$MyQuery .= " AND EnClass IN (" . implode(',', array_map('StrSafe_DB', $_alphaClasses)) . ")";
	}

	if($Athlete) {
		$MyQuery .= " AND DivAthlete=1 and ClAthlete=1";
	}
	if($TmpWhere) $MyQuery .= " AND (" . $TmpWhere . ")";
	$MyQuery.= " GROUP BY FirstLetter, SesName, Bib, Athlete, Session, TargetNo, NationCode, Nation, NationCode2, Nation2, NationCode3, Nation3,
		DivDescription, ClDescription, EnSubTeam, ClassCode, DivCode, IsAthlete, AgeClass, SubClass, Status, `IC`, `TC`, `IF`, `TF`, `TM`,
		DOB, TfName ";
	$MyQuery.= " ORDER BY Athlete, TargetNo ";

	return $MyQuery;
}

// ── Copie locale de getStartListAlphabetical() ───────────────────────────────
function getStartListAlphabeticalTAE($ORIS='', $Athlete=false) {
	$Data=new StdClass();

	$Data->Code='C32B';
	$Data->Order='3';
	$Data->Description='Entries by Name';
	$Data->Header=array("Name","NOC","Country", '#W. Rank    ', "#Date of Birth  ", "#Back No.  ", "Event");
	$Data->HeaderWidth=array(45, 10, 40, 15, 25, 15, 40);
	$Data->Phase='';
	$Data->Continue=get_text('Continue');
	$Data->Data=array();
	$Data->IndexName='Entries by Name';
	$Data->DocVersion='';
	$Data->DocVersionDate='';
	$Data->DocVersionNotes='';
	$Data->LastUpdate='';

	$Locations=array();
	if($FopLocations=Get_Tournament_Option('FopLocations')) {
		foreach($FopLocations as $loc) {
			foreach(range($loc->Tg1, $loc->Tg2) as $t) {
				$Locations[$t]=$loc->Loc;
			}
		}
	}

	$Data->Data['Fields'] = array(
		'SesName' => get_text('Session'),
		'Athlete' => get_text('Athlete'),
		'Bib' => get_text('Code','Tournament'),
		"Session" => get_text('SessionShort','Tournament'),
		'TargetNo' => get_text('Target'),
		'Nation' => get_text('Country'),
		'NationCode' => get_text('Country'),
		'AgeClass' => get_text('AgeCl'),
		'SubClass' => get_text('SubCl','Tournament'),
		'DivDescription' => get_text('Division'),
		'ClDescription' => get_text('Class'),
		'Category' => get_text('Event'),
		'Status' => get_text('Status', 'Tournament'),
		'TargetFace' => get_text('TargetType'),
		);

	$RsTour=safe_r_sql("SELECT (ToCategory&12!=0) as BisTarget, ToNumEnds AS TtNumEnds, (select max(RankRanking) as IsRanked from Rankings where RankTournament={$_SESSION['TourId']}) as IsRanked
		FROM Tournament
		WHERE ToId=" . StrSafe_DB($_SESSION['TourId']));
	if ($r=safe_fetch($RsTour)) {
		$Data->BisTarget = $r->BisTarget;
		$Data->NumEnd = $r->TtNumEnds;
		$Data->IsRanked = $r->IsRanked;
	}

	if($ORIS) {
		$Data->Data['Fields']['Athlete'] = "Name";
		$Data->Data['Fields']['TargetNo'] = "Target";
		$Data->Data['Fields']['NationCode'] = "NOC";
		$Data->Data['Fields']['Nation'] = "Country";
		$Data->Data['Fields']['Category'] = "Event";
		$Data->Data['Fields']['SesName'] = "Session";
	} else {
		$Data->HideCols = GetParameter("IntEvent");
		$Data->Description=get_text('StartlistAlpha','Tournament');
		$Data->IndexName=get_text('StartlistAlpha','Tournament');
		$Data->Header=array(get_text('Name', 'Tournament'), get_text('Country'), get_text('Nation'), get_text('DOB', 'Tournament')."   #", get_text('Target'), get_text("Event"));
	}

	$MyQuery = getStartListAlphaQueryTAE($ORIS, $Athlete);

	$OldLetter='';
	$Group=0;

	$Rs=safe_r_sql($MyQuery);
	while ($MyRow=safe_fetch($Rs)) {
		if($MyRow->EnTimestamp>$Data->LastUpdate) {
			$Data->LastUpdate=$MyRow->EnTimestamp;
		}
		if(isset($Locations[intval($MyRow->TargetButt)])) {
			$MyRow->Location=$Locations[intval($MyRow->TargetButt)];
		}
		if($OldLetter != strtoupper($MyRow->FirstLetter)) {
			$Group++;
			$OldLetter = strtoupper($MyRow->FirstLetter);
		}

		$MyRow->EventName=get_text($MyRow->EventName,'','',true);
		$MyRow->TfName=get_text($MyRow->TfName,'Tournament','',true);
		$Data->Data['Items'][$Group][]=$MyRow;

		if(!empty($MyRow->DocVersion)) {
			$Data->DocVersion=$MyRow->DocVersion;
			$Data->DocVersionDate=$MyRow->DocVersionDate;
			$Data->DocVersionNotes=$MyRow->DocNotes;
		}
	}

	return $Data;
}

// ── Génération du PDF (identique à PrnAlphabetical.php) ─────────────────────
$PdfData = getStartListAlphabeticalTAE();

if(!isset($isCompleteResultBook))
	$pdf = new ResultPDF($PdfData->Description);

require_once(PdfChunkLoader('Alphabetical.inc.php'));

if(!isset($isCompleteResultBook))
{
	if(isset($_REQUEST['ToFitarco']))
	{
		$Dest='D';
		if (isset($_REQUEST['Dest']))
			$Dest=$_REQUEST['Dest'];
		$pdf->Output($_REQUEST['ToFitarco'],$Dest);
	}
	else
		$pdf->Output();
}
?>
