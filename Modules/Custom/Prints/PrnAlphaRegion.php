<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

CheckTourSession(true);
checkFullACL(AclParticipants, 'pEntries', AclReadOnly);

// ── Paramètre filtre région/département ───────────────────────────────────────
// NationCode = upper(CoCode), format XXYYZZZ (digits) — upper() ne change pas les chiffres
$coFilter = '';
if (!empty($_REQUEST['CoFilter'])) {
	$_raw = trim($_REQUEST['CoFilter']);
	if (preg_match('/^\d{2}(\d{2})?$/', $_raw)) $coFilter = strtoupper($_raw);
}

// ── Données ───────────────────────────────────────────────────────────────────
$PdfData = getStartListAlphabetical();

// ── Filtre sur NationCode (= CoCode en majuscules) ────────────────────────────
if (!empty($coFilter)) {
	foreach ($PdfData->Data['Items'] as $group => &$items) {
		$items = array_values(array_filter($items, function($item) use ($coFilter) {
			return isset($item->NationCode) && strpos($item->NationCode, $coFilter) === 0;
		}));
	}
	unset($items);
	// Supprimer les groupes vides pour éviter des sections vides dans le PDF
	$PdfData->Data['Items'] = array_filter($PdfData->Data['Items'], function($g) {
		return !empty($g);
	});
}

// ── Rendu PDF ─────────────────────────────────────────────────────────────────
if (!isset($isCompleteResultBook))
	$pdf = new ResultPDF($PdfData->Description);
require_once(PdfChunkLoader('Alphabetical.inc.php'));
if (!isset($isCompleteResultBook))
	$pdf->Output();
?>
