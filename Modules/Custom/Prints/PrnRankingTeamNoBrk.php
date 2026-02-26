<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Lib/Fun_PrintOuts.php');
require_once('Common/Lib/Obj_RankFactory.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

if (!isset($_SESSION['TourId']) && isset($_REQUEST['TourId']))
{
	CreateTourSession($_REQUEST['TourId']);
}
checkFullACL(AclTeams, '', AclReadOnly);

$PdfData = getRankingTeams();

$pdf = new ResultPDF($PdfData->Description);

$pdf->setDocUpdate($PdfData->rankData['meta']['lastUpdate']);

$FirstSection = true;
foreach ($PdfData->rankData['sections'] as $section) {
	$NumPhases = $section['meta']['firstPhase'] ? ceil(log($section['meta']['firstPhase'], 2)) + 1 : 1;
	$NeedTitle = true;

	if (!count($section['items'])) continue;

	$HeaderHeight = ($section['meta']['printHeader'] ? 7.5 : 0) + 7.5 + 5;

	if ($FirstSection) {
		$FirstSection = false;
	} else {
		// Pas de saut de page forcé : on vérifie juste s'il y a la place
		if (!$pdf->SamePage($HeaderHeight + 4)) {
			$pdf->AddPage();
		} else {
			// Petit espace de séparation entre catégories
			$pdf->SetY($pdf->GetY() + 5);
		}
	}

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
		$pdf->Cell(20, 4 * $NumComponenti, number_format($item['qualScore'], 0, $PdfData->NumberDecimalSeparator, $PdfData->NumberThousandsSeparator) . '-' . substr('00' . $item['qualRank'], -2, 2), 1, 0, 'R', 0);

		foreach ($item['finals'] as $k => $v) {
			if ($v['tie'] == 2) {
				$pdf->Cell(15, 4 * $NumComponenti, $PdfData->Bye, 1, 0, 'R', 0);
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

if (isset($_REQUEST['TourId'])) EraseTourSession();

$pdf->Output();
?>
