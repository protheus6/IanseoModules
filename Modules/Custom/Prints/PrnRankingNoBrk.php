<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Lib/Fun_PrintOuts.php');
require_once('Common/Lib/Obj_RankFactory.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

if (!isset($_SESSION['TourId']) && isset($_REQUEST['TourId']))
{
	CreateTourSession($_REQUEST['TourId']);
}
checkFullACL(AclIndividuals, '', AclReadOnly);

$PdfData = getRankingIndividual();

$pdf = new ResultPDF($PdfData->Description);

$pdf->setDocUpdate($PdfData->rankData['meta']['lastUpdate']);

// Déterminer quelle variante du chunk utiliser (FR ou Common)
// On reproduit la logique ici sans saut de page forcé entre catégories

$FirstSection = true;
foreach ($PdfData->rankData['sections'] as $section) {

	$ElimCols = 0;
	if ($section['meta']['elimType'] != 5) {
		if ($section['meta']['elim1']) $ElimCols++;
		if ($section['meta']['elim2']) $ElimCols++;
	}

	$NumPhases = $section['meta']['firstPhase'] ? ceil(log($section['meta']['firstPhase'], 2)) + 1 : 1;

	if (!count($section['items'])) continue;

	// Hauteur minimale pour le titre + header + 1 ligne de données
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
		$pdf->Cell(12, 4, number_format($item['qualScore'], 0, $PdfData->NumberDecimalSeparator, $PdfData->NumberThousandsSeparator) . '-' . substr('00' . $item['qualRank'], -2, 2), 1, 0, 'R', 0);
		if ($section['meta']['elimType'] != 5) {
			if (array_key_exists('e1', $item['elims']))
				$pdf->Cell(12, 4, number_format($item['elims']['e1']['score'], 0, $PdfData->NumberDecimalSeparator, $PdfData->NumberThousandsSeparator) . '-' . substr('00' . $item['elims']['e1']['rank'], -2, 2), 1, 0, 'R', 0);
			if (array_key_exists('e2', $item['elims']))
				$pdf->Cell(12, 4, number_format($item['elims']['e2']['score'], 0, $PdfData->NumberDecimalSeparator, $PdfData->NumberThousandsSeparator) . '-' . substr('00' . $item['elims']['e2']['rank'], -2, 2), 1, 0, 'R', 0);
		}
		foreach ($item['finals'] as $k => $v) {
			if ($v['tie'] == 2) {
				$pdf->Cell(12, 4, $PdfData->Bye, 1, 0, 'R', 0);
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

$pdf->DrawShootOffLegend();

if (isset($_REQUEST['TourId'])) EraseTourSession();

$pdf->Output();
?>
