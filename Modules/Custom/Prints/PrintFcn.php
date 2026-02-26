<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Lib/Fun_PrintOuts.php');
require_once('Common/Lib/Obj_RankFactory.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

CheckTourSession(true);
checkFullACL(array(AclIndividuals, AclTeams), '', AclReadOnly);

// ── Paramètre optionnel : nombre de places à afficher ────────────────────────
$cutRank = (isset($_REQUEST['CutRank']) && is_numeric($_REQUEST['CutRank']) && intval($_REQUEST['CutRank']) > 0)
	? intval($_REQUEST['CutRank'])
	: 0;

// ── 1. Charger les données ───────────────────────────────────────────────────

$finalOptions = $cutRank ? array('cutRank' => $cutRank) : array();

$PdfDataFinalInd  = getRankingIndividual('', false, false);
$PdfDataFinalTeam = getRankingTeams('', false, false);
$PdfDataQualInd   = getQualificationIndividual();
$PdfDataQualTeam  = getQualificationTeam();

// ── 2. Identifier les catégories qui ont eu des finales ──────────────────────

$eventsWithFinalInd = array();
foreach ($PdfDataFinalInd->rankData['sections'] as $evCode => $section) {
	if (count($section['items'])) {
		$eventsWithFinalInd[] = $evCode;
	}
}

$eventsWithFinalTeam = array();
foreach ($PdfDataFinalTeam->rankData['sections'] as $evCode => $section) {
	if (count($section['items'])) {
		$eventsWithFinalTeam[] = $evCode;
	}
}

// ── 3. Identifier les catégories sans finale (→ classement qualifs) ──────────

$eventsQualOnlyInd = array();
foreach ($PdfDataQualInd->rankData['sections'] as $evCode => $section) {
	if (!in_array($evCode, $eventsWithFinalInd) && count($section['items'])) {
		$eventsQualOnlyInd[] = $evCode;
	}
}

$eventsQualOnlyTeam = array();
foreach ($PdfDataQualTeam->rankData['sections'] as $evCode => $section) {
	if (!in_array($evCode, $eventsWithFinalTeam) && count($section['items'])) {
		$eventsQualOnlyTeam[] = $evCode;
	}
}

// ── 4. Créer le PDF ──────────────────────────────────────────────────────────

$pdf = new ResultPDF(get_text('ResultClass', 'Tournament'));
$isCompleteResultBook = true;

$FirstSection = true;

// ── Fonction utilitaire : troncature des items selon cutRank ─────────────────
function _applyCutRank(&$rankData, $cutRank) {
	if (!$cutRank) return;
	foreach ($rankData['sections'] as $evCode => &$section) {
		$section['items'] = array_filter($section['items'], function($item) use ($cutRank) {
			return isset($item['rank']) && $item['rank'] > 0 && $item['rank'] <= $cutRank;
		});
	}
	unset($section);
}

// Appliquer la troncature sur toutes les données
_applyCutRank($PdfDataFinalInd->rankData, $cutRank);
_applyCutRank($PdfDataFinalTeam->rankData, $cutRank);
_applyCutRank($PdfDataQualInd->rankData, $cutRank);
_applyCutRank($PdfDataQualTeam->rankData, $cutRank);

// ── Fonction utilitaire : séparation entre sections ──────────────────────────
function _bookSeparator(&$pdf, &$FirstSection, $headerHeight) {
	if ($FirstSection) {
		$FirstSection = false;
	} else {
		if (!$pdf->SamePage($headerHeight + 4)) {
			$pdf->AddPage();
		} else {
			$pdf->SetY($pdf->GetY() + 5);
		}
	}
}

// ════════════════════════════════════════════════════════════════════════════
// FINALES INDIVIDUELLES
// ════════════════════════════════════════════════════════════════════════════
foreach ($PdfDataFinalInd->rankData['sections'] as $section) {

	if (!count($section['items'])) continue;

	$ElimCols = 0;
	if ($section['meta']['elimType'] != 5) {
		if ($section['meta']['elim1']) $ElimCols++;
		if ($section['meta']['elim2']) $ElimCols++;
	}
	$NumPhases   = $section['meta']['firstPhase'] ? ceil(log($section['meta']['firstPhase'], 2)) + 1 : 1;
	$HeaderHeight = ($section['meta']['printHeader'] ? 7.5 : 0) + 7.5 + 5;

	_bookSeparator($pdf, $FirstSection, $HeaderHeight);

	$NeedTitle = true;
	foreach ($section['items'] as $item) {
		if (!$pdf->SamePage(4)) $NeedTitle = true;

		if ($NeedTitle) {
			if (!$pdf->SamePage($HeaderHeight + 4)) $pdf->AddPage();

			if ($section['meta']['printHeader']) {
				$pdf->SetFont($pdf->FontStd, 'B', 10);
				$pdf->Cell(190, 7.5, $section['meta']['printHeader'], 0, 1, 'R', 0);
			}
			$pdf->SetFont($pdf->FontStd, 'B', 10);
			$pdf->Cell(190, 7.5, $section['meta']['descr'], 1, 1, 'C', 1);

			$pdf->SetFont($pdf->FontStd, 'B', 7);
			$pdf->Cell(8, 5, $section['meta']['fields']['rank'], 1, 0, 'C', 1);
			$pdf->Cell(40 + (12 * (7 - $NumPhases - $ElimCols)), 5, $section['meta']['fields']['athlete'], 1, 0, 'C', 1);
			$pdf->Cell(46, 5, $section['meta']['fields']['countryName'], 1, 0, 'C', 1);
			$pdf->Cell(12, 5, $section['meta']['fields']['qualRank'], 1, 0, 'C', 1);
			for ($i = 1; $i <= $ElimCols; $i++)
				$pdf->Cell(12, 5, $section['meta']['fields']['elims']['e' . $i], 1, 0, 'C', 1);
			foreach ($section['meta']['fields']['finals'] as $k => $v) {
				if (is_numeric($k) && $k != 1)
					$pdf->Cell(12, 5, $v, 1, 0, 'C', 1);
			}
			$pdf->Cell(0, 5, '', 0, 1, 'C', 0);
			$NeedTitle = false;
		}

		$pdf->SetFont($pdf->FontStd, 'B', 8);
		$pdf->Cell(8, 4, ($item['rank'] ? $item['rank'] : ''), 1, 0, 'C', 0);
		$pdf->SetFont($pdf->FontStd, '', 8);
		$pdf->Cell(40 + (12 * (7 - $NumPhases - $ElimCols)), 4, $item['athlete'], 'RBT', 0, 'L', 0);
		$pdf->Cell(10, 4, $item['countryCode'], 'LTB', 0, 'C', 0);
		$pdf->Cell(36, 4, $item['countryName'], 'RTB', 0, 'L', 0);
		$pdf->SetFont($pdf->FontFix, '', 7);
		$pdf->Cell(12, 4, number_format($item['qualScore'], 0, $PdfDataFinalInd->NumberDecimalSeparator, $PdfDataFinalInd->NumberThousandsSeparator) . '-' . substr('00' . $item['qualRank'], -2, 2), 1, 0, 'R', 0);
		if ($section['meta']['elimType'] != 5) {
			if (array_key_exists('e1', $item['elims']))
				$pdf->Cell(12, 4, number_format($item['elims']['e1']['score'], 0, $PdfDataFinalInd->NumberDecimalSeparator, $PdfDataFinalInd->NumberThousandsSeparator) . '-' . substr('00' . $item['elims']['e1']['rank'], -2, 2), 1, 0, 'R', 0);
			if (array_key_exists('e2', $item['elims']))
				$pdf->Cell(12, 4, number_format($item['elims']['e2']['score'], 0, $PdfDataFinalInd->NumberDecimalSeparator, $PdfDataFinalInd->NumberThousandsSeparator) . '-' . substr('00' . $item['elims']['e2']['rank'], -2, 2), 1, 0, 'R', 0);
		}
		foreach ($item['finals'] as $k => $v) {
			if ($v['tie'] == 2) {
				$pdf->Cell(12, 4, $PdfDataFinalInd->Bye, 1, 0, 'R', 0);
			} else {
				if ($k == 4 && $section['meta']['matchMode'] != 0 && $item['rank'] >= 5) {
					$pdf->Cell(8, 4, '(' . $v['score'] . ')', 'LTB', 0, 'R', 0);
					$pdf->Cell(4, 4, $v['setScore'], 'RTB', 0, 'R', 0);
				} else {
					$pdf->Cell(12 - (strlen($v['tiebreak']) > 0 && $k <= 1 ? 6 : 0), 4, ($section['meta']['matchMode'] == 0 ? $v['score'] : $v['setScore']) . ($k <= 1 && $v['tie'] == 1 && strlen($v['tiebreak']) == 0 ? '*' : ''), ($k <= 1 && strlen($v['tiebreak']) > 0 ? 'LTB' : 1), 0, 'R', 0);
					if (strlen($v['tiebreak']) > 0 && $k <= 1)
						$pdf->Cell(6, 4, "T." . str_replace('|', ',', $v['tiebreak']), 'RTB', 0, 'R', 0);
				}
			}
		}
		$pdf->Cell(0.1, 4, '', 0, 1, 'C', 0);
	}
}

// ════════════════════════════════════════════════════════════════════════════
// FINALES EQUIPES
// ════════════════════════════════════════════════════════════════════════════
foreach ($PdfDataFinalTeam->rankData['sections'] as $section) {

	if (!count($section['items'])) continue;

	$NumPhases   = $section['meta']['firstPhase'] ? ceil(log($section['meta']['firstPhase'], 2)) + 1 : 1;
	$HeaderHeight = ($section['meta']['printHeader'] ? 7.5 : 0) + 7.5 + 5;

	_bookSeparator($pdf, $FirstSection, $HeaderHeight);

	$NeedTitle = true;
	foreach ($section['items'] as $item) {
		$NumComponenti = max(1, count($item['athletes']));
		if (!$pdf->SamePage(4 * $NumComponenti)) $NeedTitle = true;

		if ($NeedTitle) {
			if (!$pdf->SamePage($HeaderHeight + 4 * $NumComponenti)) $pdf->AddPage();

			if ($section['meta']['printHeader']) {
				$pdf->SetFont($pdf->FontStd, 'B', 10);
				$pdf->Cell(190, 7.5, $section['meta']['printHeader'], 0, 1, 'R', 0);
			}
			$pdf->SetFont($pdf->FontStd, 'B', 10);
			$pdf->Cell(190, 7.5, $section['meta']['descr'], 1, 1, 'C', 1);

			$pdf->SetFont($pdf->FontStd, 'B', 7);
			$pdf->Cell(10, 5, $section['meta']['fields']['rank'], 1, 0, 'C', 1);
			$pdf->Cell(55 + (15 * (7 - $NumPhases)), 5, $section['meta']['fields']['countryName'], 1, 0, 'C', 1);
			$pdf->Cell(20, 5, $section['meta']['fields']['qualRank'], 1, 0, 'C', 1);
			foreach ($section['meta']['fields']['finals'] as $k => $v) {
				if (is_numeric($k) && $k != 1)
					$pdf->Cell(15, 5, $v, 1, 0, 'C', 1);
			}
			$pdf->Cell(0, 5, '', 0, 1, 'C', 0);
			$NeedTitle = false;
		}

		$pdf->SetFont($pdf->FontStd, 'B', 1);
		$pdf->Cell(190, 0.2, '', 0, 1, 'C', 0);
		$pdf->SetFont($pdf->FontStd, 'B', 8);
		$pdf->Cell(10, 4 * $NumComponenti, ($item['rank'] ? $item['rank'] : ''), 1, 0, 'C', 0);
		$pdf->SetFont($pdf->FontStd, '', 8);
		$pdf->Cell(10, 4 * $NumComponenti, $item['countryCode'], 'LTB', 0, 'C', 0);
		$pdf->Cell(25 + (15 * (5 - $NumPhases)), 4 * $NumComponenti, $item['countryName'] . ($item['subteam'] <= 1 ? '' : ' (' . $item['subteam'] . ')'), 'TB', 0, 'L', 0);

		if (count($item['athletes'])) {
			$tmpX = $pdf->GetX();
			$tmpY = $pdf->GetY();
			$NameCount = 0;
			foreach ($item['athletes'] as $k => $v) {
				$pdf->SetXY($tmpX, $tmpY + (4 * $NameCount++));
				$pdf->Cell(50, 4, $v['athlete'], 1, 0, 'L', 0);
			}
			$pdf->SetXY($tmpX + 50, $tmpY);
		} else {
			$pdf->Cell(50, 4 * $NumComponenti, '', 'RTB', 0, 'L', 0);
		}

		$pdf->SetFont($pdf->FontFix, '', 8);
		$pdf->Cell(20, 4 * $NumComponenti, number_format($item['qualScore'], 0, $PdfDataFinalTeam->NumberDecimalSeparator, $PdfDataFinalTeam->NumberThousandsSeparator) . '-' . substr('00' . $item['qualRank'], -2, 2), 1, 0, 'R', 0);

		foreach ($item['finals'] as $k => $v) {
			if ($v['tie'] == 2) {
				$pdf->Cell(15, 4 * $NumComponenti, $PdfDataFinalTeam->Bye, 1, 0, 'R', 0);
			} else {
				$pdf->SetFont($pdf->FontFix, '', 8);
				if ($k == 4 && $section['meta']['matchMode'] != 0 && $item['rank'] >= 5) {
					$pdf->Cell(11, 4 * $NumComponenti, '(' . $v['score'] . ')', 'LTB', 0, 'R', 0);
					$pdf->Cell(4, 4 * $NumComponenti, $v['setScore'], 'RTB', 0, 'R', 0);
				} else {
					$pdf->SetFont($pdf->FontFix, '', 7);
					$pdf->Cell(15 - (strlen($v['tiebreak']) > 0 && $k <= 1 ? 7 : 0), 4 * $NumComponenti, ($section['meta']['matchMode'] == 0 ? $v['score'] : $v['setScore']) . ($k <= 1 && $v['tie'] == 1 && strlen($v['tiebreak']) == 0 ? '*' : ''), ($k <= 1 && strlen($v['tiebreak']) > 0 ? 'LTB' : 1), 0, 'R', 0);
					if (strlen($v['tiebreak']) > 0 && $k <= 1) {
						$tmpTxt = "";
						$tmpArr = explode("|", $v['tiebreak']);
						for ($countArr = 0; $countArr < count($tmpArr); $countArr += $NumComponenti)
							$tmpTxt .= array_sum(array_slice($tmpArr, $countArr, $NumComponenti)) . ",";
						$pdf->Cell(7, 4 * $NumComponenti, "T." . substr($tmpTxt, 0, -1), 'RTB', 0, 'R', 0);
					}
				}
			}
		}
		$pdf->Cell(0.1, 4 * $NumComponenti, '', 0, 1, 'C', 0);
	}
}

// ════════════════════════════════════════════════════════════════════════════
// QUALIFICATIONS INDIVIDUELLES (catégories sans finale seulement)
// ════════════════════════════════════════════════════════════════════════════
if (count($eventsQualOnlyInd)) {
	$PdfData  = getQualificationIndividual($eventsQualOnlyInd);
	$rankData = $PdfData->rankData;
	$pdf->NumberThousandsSeparator = $PdfData->NumberThousandsSeparator;
	$pdf->NumberDecimalSeparator   = $PdfData->NumberDecimalSeparator;
	$pdf->Continue    = $PdfData->Continue;
	$pdf->TotalShort  = $PdfData->TotalShort;
	$pdf->ShotOffShort  = $PdfData->ShotOffShort;
	$pdf->CoinTossShort = $PdfData->CoinTossShort;

	foreach ($rankData['sections'] as $section) {
		$HeaderHeight = (!empty($section['meta']['printHeader']) ? 7.5 : 0) + 15 + ($section['meta']['sesArrows'] ? 8 : 0);
		_bookSeparator($pdf, $FirstSection, $HeaderHeight);

		if (!$pdf->SamePage($HeaderHeight)) $pdf->AddPage();
		$pdf->writeGroupHeaderPrnIndividualAbs($section['meta'], 11, 0, $section['meta']['running'], $section['meta']['numDist'], $rankData['meta']['double'], false);
		$EndQualified  = ($section['meta']['qualifiedNo'] == 0);
		$StartQualified = ($section['meta']['firstQualified'] == 1);

		foreach ($section['items'] as $item) {
			if (!$StartQualified && ($section['meta']['finished'] ? $item['rank'] : $item['rankBeforeSO'] + $item['ct']) >= $section['meta']['firstQualified']) {
				$pdf->SetFont($pdf->FontStd, '', 1);
				$pdf->Cell(190, 1, '', 1, 1, 'C', 1);
				if (!$pdf->SamePage(4 * ($rankData['meta']['double'] ? 2 : 1))) {
					$pdf->AddPage();
					$pdf->writeGroupHeaderPrnIndividualAbs($section['meta'], 11, 0, $section['meta']['running'], $section['meta']['numDist'], $rankData['meta']['double'], true);
				}
				$StartQualified = true;
			}
			if (!$EndQualified && $item['rank'] > ($section['meta']['qualifiedNo'] + $section['meta']['firstQualified'] - 1)) {
				$pdf->SetFont($pdf->FontStd, '', 1);
				$pdf->Cell(190, 1, '', 1, 1, 'C', 1);
				if (!$pdf->SamePage(4 * ($rankData['meta']['double'] ? 2 : 1))) {
					$pdf->AddPage();
					$pdf->writeGroupHeaderPrnIndividualAbs($section['meta'], 11, 0, $section['meta']['running'], $section['meta']['numDist'], $rankData['meta']['double'], true);
				}
				$EndQualified = true;
			}
			if (!$pdf->SamePage(4 * ($rankData['meta']['double'] ? 2 : 1))) {
				$pdf->AddPage();
				$pdf->writeGroupHeaderPrnIndividualAbs($section['meta'], 11, 0, $section['meta']['running'], $section['meta']['numDist'], $rankData['meta']['double'], true);
			}
			$pdf->writeDataRowPrnIndividualAbs($item, 11, 0, $section['meta']['running'], $section['meta']['numDist'], $rankData['meta']['double'], ($PdfData->family == 'Snapshot' ? $section['meta']['snapDistance'] : 0));
		}
		$pdf->SetY($pdf->GetY() + 5);
	}
}

// ════════════════════════════════════════════════════════════════════════════
// QUALIFICATIONS EQUIPES (catégories sans finale seulement)
// ════════════════════════════════════════════════════════════════════════════
if (count($eventsQualOnlyTeam)) {
	$PdfData  = getQualificationTeam($eventsQualOnlyTeam);
	$rankData = $PdfData->rankData;
	$pdf->NumberThousandsSeparator = $PdfData->NumberThousandsSeparator;
	$pdf->NumberDecimalSeparator   = $PdfData->NumberDecimalSeparator;
	$pdf->Continue    = $PdfData->Continue;
	$pdf->TotalShort  = $PdfData->TotalShort;
	$pdf->ShotOffShort  = $PdfData->ShotOffShort;
	$pdf->CoinTossShort = $PdfData->CoinTossShort;

	foreach ($rankData['sections'] as $section) {
		$meta = $section['meta'];
		$HeaderHeight = (!empty($meta['printHeader']) ? 7.5 : 0) + 15 + ($meta['sesArrows'] ? 8 : 0);
		_bookSeparator($pdf, $FirstSection, $HeaderHeight);

		if (!$pdf->SamePage($HeaderHeight)) $pdf->AddPage();
		$pdf->writeGroupHeaderPrnTeamAbs($meta, false);

		foreach ($section['items'] as $item) {
			if (!$pdf->SamePage(4 * count($item['athletes']))) {
				$pdf->AddPage();
				$pdf->writeGroupHeaderPrnTeamAbs($meta, true);
			}
			$pdf->writeDataRowPrnTeamAbs($item, ($item['rank'] > $meta['qualifiedNo']), $meta['running']);
		}
		$pdf->SetY($pdf->GetY() + 5);
	}
}

// ── Légende shoot-off et sortie ──────────────────────────────────────────────
$pdf->DrawShootOffLegend();
$pdf->Output();
?>
