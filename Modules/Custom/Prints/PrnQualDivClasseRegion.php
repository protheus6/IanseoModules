<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Lib/Fun_PrintOuts.php');
require_once('Common/Lib/ArrTargets.inc.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

CheckTourSession(true);
checkFullACL(AclQualification, '', AclReadOnly);

// ── Paramètres ────────────────────────────────────────────────────────────────
$coFilter = '';
if (!empty($_REQUEST['CoFilter'])) {
	$_raw = trim($_REQUEST['CoFilter']);
	if (preg_match('/^\d{2}(\d{2})?$/', $_raw)) $coFilter = $_raw;
}
$reRank  = !empty($_REQUEST['ReRank']);
$cutRank = (isset($_REQUEST['CutRank']) && is_numeric($_REQUEST['CutRank']) && intval($_REQUEST['CutRank']) > 0)
	? intval($_REQUEST['CutRank']) : 0;

// ── Fonctions filtre (CoCode : XXYYZZZ — même logique que PrintFcn.php) ───────
function _applyCoFilter(&$rankData, $cf) {
	if (empty($cf)) return;
	foreach ($rankData['sections'] as &$section) {
		$section['items'] = array_values(array_filter($section['items'], function($item) use ($cf) {
			return isset($item['countryCode']) && strpos($item['countryCode'], $cf) === 0;
		}));
	}
	unset($section);
}
function _applyReRank(&$rankData) {
	foreach ($rankData['sections'] as &$section) {
		$pos = 0;
		foreach ($section['items'] as &$item) { $item['rank'] = ++$pos; }
		unset($item);
	}
	unset($section);
}
function _applyCutRank(&$rankData, $cr) {
	if (!$cr) return;
	foreach ($rankData['sections'] as &$section) {
		$section['items'] = array_filter($section['items'], function($item) use ($cr) {
			return isset($item['rank']) && $item['rank'] > 0 && $item['rank'] <= $cr;
		});
	}
	unset($section);
}

// ── Données + filtres ─────────────────────────────────────────────────────────
$PdfData  = getDivClasIndividual();
$rankData = $PdfData->rankData;
_applyCoFilter($rankData, $coFilter);
if ($reRank) _applyReRank($rankData);
_applyCutRank($rankData, $cutRank);
$PdfData->rankData = $rankData;

// ── Rendu PDF ─────────────────────────────────────────────────────────────────
if (!isset($isCompleteResultBook))
	$pdf = new ResultPDF($PdfData->Description);
require_once(PdfChunkLoader('DivClasIndividual.inc.php'));
if (!isset($isCompleteResultBook))
	$pdf->Output();
?>
