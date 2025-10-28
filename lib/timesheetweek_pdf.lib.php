<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        lib/timesheetweek_pdf.lib.php
 * \ingroup     timesheetweek
 * \brief       Helper functions for TimesheetWeek PDF exports
 */

// EN: Load Dolibarr helpers required to build PDF documents.
// FR: Charge les helpers Dolibarr nécessaires pour construire les documents PDF.
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

defined('TIMESHEETWEEK_PDF_SUMMARY_SUBDIR') || define('TIMESHEETWEEK_PDF_SUMMARY_SUBDIR', 'summaries');

/**
 * EN: Escape provided value into an UTF-8 safe HTML fragment for TCPDF output.
 * FR: Échappe la valeur fournie en fragment HTML UTF-8 sûr pour la sortie TCPDF.
 *
 * @param string $value
 * @return string
 */
function tw_pdf_format_cell_html($value)
{
	// EN: Ensure the value is cast to string before escaping.
	// FR: Garantit la conversion de la valeur en chaîne avant l'échappement.
	$value = (string) $value;
	return '<span>'.dol_htmlentities($value, ENT_COMPAT | ENT_HTML401, 'UTF-8').'</span>';
}

/**
 * EN: Render the Dolibarr-styled header containing the logo and company name.
 * FR: Dessine l'entête au style Dolibarr avec le logo et le nom de l'entreprise.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $topMargin
 * @param string $title
 * @param string $subtitle
 * @return float
 */
function tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $title = '', $subtitle = '')
{
	global $mysoc;

	$defaultFontSize = pdf_getPDFFontSize($langs);
	$logoHeight = 0.0;
	$posX = $leftMargin;
	$posY = $topMargin;
	$logoPath = '';
	$logoDisplayed = false;
	$pageWidth = $pdf->getPageWidth();
	$margins = method_exists($pdf, 'getMargins') ? (array) $pdf->getMargins() : array();
	$rightMargin = isset($margins['right']) ? (float) $margins['right'] : (float) getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$rightBlockWidth = max(90.0, $pageWidth * 0.28);
	$rightBlockX = max($leftMargin, $pageWidth - $rightMargin - $rightBlockWidth);
	$rightBlockBottom = $posY;

	if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
		// EN: Resolve the preferred logo file between large and thumbnail versions.
		// FR: Résout le fichier logo privilégié entre les versions grande et miniature.
		if (!empty($mysoc->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$conf->entity] ?? null)) {
				$logodir = $conf->mycompany->multidir_output[$conf->entity];
			}
			if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($mysoc->logo_small)) {
				$logoCandidate = $logodir.'/logos/thumbs/'.$mysoc->logo_small;
				if (is_readable($logoCandidate)) {
					$logoPath = $logoCandidate;
				}
			}
			if ($logoPath === '') {
				$logoCandidate = $logodir.'/logos/'.$mysoc->logo;
				if (is_readable($logoCandidate)) {
					$logoPath = $logoCandidate;
				}
			}
		}
		if ($logoPath === '') {
			$defaultLogo = DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.svg';
			if (is_readable($defaultLogo)) {
				$logoPath = $defaultLogo;
			}
		}
		if ($logoPath !== '') {
		$logoHeight = pdf_getHeightForLogo($logoPath);
		$pdf->Image($logoPath, $posX, $posY, 0, $logoHeight);
		// EN: Track that a logo is displayed to hide the company name for visual consistency.
		// FR: Indique qu'un logo est affiché pour masquer le nom de la société et préserver la cohérence visuelle.
		$logoDisplayed = true;
		}
	}

	$companyName = !empty($mysoc->name) ? $mysoc->name : 'Dolibarr ERP & CRM';
	$leftBlockWidth = max(60.0, $rightBlockX - $posX - 2.0);
	if (!$logoDisplayed) {
		// EN: Show the company name only when no logo is available to avoid duplicate branding.
		// FR: Affiche le nom de la société uniquement lorsqu'aucun logo n'est disponible pour éviter une double identité visuelle.
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->SetXY($posX, $posY + max($logoHeight - 6.0, 0.0));
		$pdf->MultiCell($leftBlockWidth, 5, tw_pdf_format_cell_html($langs->convToOutputCharset($companyName)), 0, 'L', 0, 1, '', '', true, 0, true);
	}

	// EN: Render the summary title and metadata within the right column of the header.
	// FR: Affiche le titre de synthèse et les métadonnées dans la colonne droite de l'entête.
	// EN: Remove unnecessary spaces around the header title for accurate checks.
	// FR: Supprime les espaces superflus autour du titre d'entête pour des vérifications précises.
	$trimmedTitle = trim((string) $title);
	if (dol_strlen($trimmedTitle) > 0) {
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($rightBlockX, $posY);
		$pdf->MultiCell($rightBlockWidth, 6, tw_pdf_format_cell_html($langs->convToOutputCharset($trimmedTitle)), 0, 'R', 0, 1, '', '', true, 0, true);
		$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
	}
	// EN: Remove unnecessary spaces around the header subtitle before rendering.
	// FR: Supprime les espaces superflus autour du sous-titre d'entête avant affichage.
	$trimmedSubtitle = trim((string) $subtitle);
	if (dol_strlen($trimmedSubtitle) > 0) {
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($rightBlockX, $rightBlockBottom + 1.0);
		$pdf->MultiCell($rightBlockWidth, 5, tw_pdf_format_cell_html($langs->convToOutputCharset($trimmedSubtitle)), 0, 'R', 0, 1, '', '', true, 0, true);
		$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
	}

	$pdf->SetTextColor(0, 0, 0);

	return max($posY + max($logoHeight, 16.0), $rightBlockBottom);
}

/**
 * EN: Draw the footer using the standard Dolibarr helper to keep consistent branding.
 * FR: Dessine le pied de page avec le helper Dolibarr standard pour conserver la charte.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $rightMargin
 * @param float $bottomMargin
 * @param CommonObject|null $object
 * @param int $hideFreeText
 * @param float|null $autoPageBreakMargin
 * @return int
 */
function tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, $object = null, $hideFreeText = 0, $autoPageBreakMargin = null)
{
	global $mysoc;

	// EN: Backup automatic page break configuration to avoid splitting the footer on two pages.
	// FR: Sauvegarde la configuration de saut automatique pour éviter de scinder le pied entre deux pages.
	$previousAutoBreak = method_exists($pdf, 'getAutoPageBreak') ? $pdf->getAutoPageBreak() : true;
	$previousBreakMargin = null;
	if (method_exists($pdf, 'getBreakMargin')) {
		$previousBreakMargin = (float) $pdf->getBreakMargin();
	} elseif ($autoPageBreakMargin !== null) {
		$previousBreakMargin = (float) $autoPageBreakMargin;
	} elseif (isset($pdf->bMargin)) {
		// EN: Fallback on TCPDF public margin when helper methods are unavailable.
		// FR: Utilise la marge publique de TCPDF si les helpers sont indisponibles.
		$previousBreakMargin = (float) $pdf->bMargin;
	} else {
		$previousBreakMargin = (float) $bottomMargin;
	}
	$pdf->SetAutoPageBreak(false, 0);

	// EN: Determine if Dolibarr must show the detailed footer blocks (tax numbers, contacts, ...).
	// FR: Détermine si Dolibarr doit afficher les blocs détaillés du pied (numéros fiscaux, contacts, ...).
	$showDetails = empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 0 : $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;

	// EN: Delegate the rendering to pdf_pagefoot to mirror the official Dolibarr layout and logic.
	// FR: Délègue le rendu à pdf_pagefoot pour reproduire la mise en forme et la logique officielles de Dolibarr.
	$footHeight = pdf_pagefoot($pdf, $langs, 'INVOICE_FREE_TEXT', $mysoc, $bottomMargin, $leftMargin, $pdf->getPageHeight(), $object, $showDetails, $hideFreeText);

	// EN: Restore the automatic page break configuration so the following content keeps the same flow.
	// FR: Restaure la configuration de saut automatique pour conserver le même flux pour le contenu suivant.
	$pdf->SetAutoPageBreak($previousAutoBreak, $previousBreakMargin);

	return $footHeight;
}


/**
 * EN: Create a new landscape page and ensure header/footer are drawn.
 * FR: Crée une nouvelle page paysage et dessine l'entête/pied de page.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $topMargin
 * @param float $rightMargin
 * @param float $bottomMargin
 * @param float|null $autoPageBreakMargin
 * @param string $headerTitle
 * @param string $headerSubtitle
 * @return float
 */
function tw_pdf_add_landscape_page($pdf, $langs, $conf, $leftMargin, $topMargin, $rightMargin, $bottomMargin, &$headerState = null, $autoPageBreakMargin = null, $headerTitle = '', $headerSubtitle = '')
{
	$pdf->AddPage('L');
	// EN: Detect if TCPDF automatic callbacks manage header/footer rendering.
	// FR: Détecte si les callbacks automatiques de TCPDF gèrent le rendu entête/pied.
	$callbacksOn = is_array($headerState) && !empty($headerState['automatic']);

	if ($callbacksOn) {
		// EN: Recompute the header height when missing to avoid duplicated footer calls.
		// FR: Recalcule la hauteur d'entête lorsqu'elle manque pour éviter les appels de pied dupliqués.
		$headerBottom = !empty($headerState['value'])
				? (float) $headerState['value']
				: tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $headerTitle, $headerSubtitle);
	} else {
		$headerBottom = tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $headerTitle, $headerSubtitle);
		tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, null, 0, $autoPageBreakMargin);
		if (is_array($headerState)) {
			// EN: Store the header height for further pages when callbacks remain disabled.
			// FR: Mémorise la hauteur d'entête pour les prochaines pages lorsque les callbacks restent inactifs.
			$headerState['value'] = $headerBottom;
		}
	}
	$contentStart = $headerBottom + 4.0;
	// EN: Force the top margin below the header so every page keeps data between header and footer.
	// FR: Force la marge haute sous l'entête pour que chaque page maintienne les données entre entête et pied.
	$pdf->SetTopMargin($contentStart);
	$pdf->SetXY($leftMargin, $contentStart);
	return $contentStart;
}

/**
 * EN: Display the employee banner for the current section on the PDF.
 * FR: Affiche la bannière de l'employé pour la section courante du PDF.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param User $userObject
 * @param float $defaultFontSize
 * @return void
 */
function tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize)
{
	$pdf->SetFont('', 'B', $defaultFontSize + 1);
	$pdf->SetTextColor(0, 0, 60);
	$pdf->MultiCell(0, 6, tw_pdf_format_cell_html($langs->trans('TimesheetWeekSummaryUserTitle', $userObject->getFullName($langs))), 0, 'L', 0, 1, '', '', true, 0, true);
	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);
}


/**
 * EN: Determine the height required for a table row considering wrapped content.
 * FR: Détermine la hauteur nécessaire pour une ligne du tableau en considérant les retours à la ligne.
 *
 * @param TCPDF $pdf
 * @param float[] $columnWidths
 * @param string[] $values
 * @param float $lineHeight
 * @return float
 */
function tw_pdf_estimate_row_height($pdf, array $columnWidths, array $values, $lineHeight)
{
	// EN: Track the maximum number of lines across the row to harmonise heights.
	// FR: Suit le nombre maximal de lignes pour harmoniser les hauteurs.
	$maxLines = 1;
	foreach ($values as $index => $value) {
		$text = tw_pdf_format_cell_html($value);
		$plain = dol_string_nohtmltag($text);
		$currentLines = max(1, $pdf->getNumLines($plain, $columnWidths[$index]));
		$maxLines = max($maxLines, $currentLines);
	}
	return $lineHeight * $maxLines;
}

/**
 * EN: Render a row with uniform cell height and consistent column widths.
 * FR: Affiche une ligne avec une hauteur uniforme des cellules et des largeurs cohérentes par colonne.
 *
 * @param TCPDF $pdf
 * @param float[] $columnWidths
 * @param string[] $values
 * @param float $lineHeight
 * @param array $options
 * @return void
 */
function tw_pdf_render_row($pdf, array $columnWidths, array $values, $lineHeight, array $options = array())
{
	$border = $options['border'] ?? 1;
	$fill = !empty($options['fill']);
	$alignments = $options['alignments'] ?? array();
	// EN: Compute the shared height to align every cell in the row.
	// FR: Calcule la hauteur commune pour aligner toutes les cellules de la ligne.
	$rowHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $values, $lineHeight);
	$initialX = $pdf->GetX();
	$initialY = $pdf->GetY();
	$offset = 0.0;
	foreach ($values as $index => $value) {
		$width = $columnWidths[$index];
		$align = $alignments[$index] ?? 'L';
		// EN: Position each cell manually to guarantee column alignment.
		// FR: Positionne chaque cellule manuellement pour garantir l'alignement des colonnes.
		$pdf->SetXY($initialX + $offset, $initialY);
		$pdf->MultiCell(
		$width,
		$rowHeight,
		tw_pdf_format_cell_html($value),
		$border,
		$align,
		$fill,
		0,
		'',
		'',
		true,
		0,
		true,
		true,
		$rowHeight,
		'T',
		false
		);
		$offset += $width;
	}
	// EN: Move the cursor under the row for the next drawing operations.
	// FR: Replace le curseur sous la ligne pour les prochaines opérations de dessin.
	$pdf->SetXY($initialX, $initialY + $rowHeight);
}

/**
 * EN: Estimate the full height required to display a user table without page breaks.
 * FR: Estime la hauteur complète nécessaire pour afficher un tableau utilisateur sans saut de page.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param User $userObject
 * @param float[] $columnWidths
 * @param string[] $columnLabels
 * @param string[][] $recordRows
 * @param string[] $totalsRow
 * @param float $lineHeight
 * @param float $contentWidth
 * @return float
 */
function tw_pdf_estimate_user_table_height($pdf, $langs, $userObject, array $columnWidths, array $columnLabels, array $recordRows, array $totalsRow, $lineHeight, $contentWidth)
{
	$bannerText = $langs->trans('TimesheetWeekSummaryUserTitle', $userObject->getFullName($langs));
	$bannerPlain = dol_string_nohtmltag(tw_pdf_format_cell_html($bannerText));
	// EN: Evaluate banner height using the same line width as the MultiCell call.
	// FR: Évalue la hauteur de la bannière en utilisant la même largeur de ligne que l'appel MultiCell.
	$bannerLines = max(1, $pdf->getNumLines($bannerPlain, $contentWidth));
	$bannerHeight = 6 * $bannerLines;
	
	// EN: Account for the spacing introduced before the table header.
	// FR: Prend en compte l'espacement introduit avant l'entête du tableau.
	$headerHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $columnLabels, $lineHeight);
	$totalHeight = $bannerHeight + 2 + $headerHeight;
	
	foreach ($recordRows as $rowValues) {
		$totalHeight += tw_pdf_estimate_row_height($pdf, $columnWidths, $rowValues, $lineHeight);
	}
	
	$totalHeight += tw_pdf_estimate_row_height($pdf, $columnWidths, $totalsRow, $lineHeight);
	
	return $totalHeight;
}

/**
 * EN: Format decimal hours into the HH:MM representation expected by HR teams.
 * FR: Formate les heures décimales en représentation HH:MM attendue par les équipes RH.
 *
 * @param float $hours
 * @return string
 */
function tw_format_hours_decimal($hours)
{
	$hours = (float) $hours;
	$hoursInt = (int) floor($hours);
	$minutes = (int) round(($hours - $hoursInt) * 60);
	if ($minutes === 60) {
		$hoursInt++;
		$minutes = 0;
	}
	return sprintf('%02d:%02d', $hoursInt, $minutes);
}

/**
 * EN: Build the dataset required to generate a PDF summary of weekly timesheets.
 * FR: Construit l'ensemble de données nécessaire pour générer un résumé PDF des feuilles hebdomadaires.
 *
 * @param DoliDB   $db                 Database handler
 * @param int[]    $timesheetIds       Selected timesheet identifiers
 * @param User     $user               Current Dolibarr user
 * @param bool     $permReadOwn        Permission to read own sheets
 * @param bool     $permReadChild      Permission to read subordinates sheets
 * @param bool     $permReadAll        Permission to read all sheets
 * @return array{users:array<int,array{user:User,records:array<int,array<string,mixed>>,totals:array<string,float|int>>>,errors:string[]>|
 *              array{errors:string[]}
 */
function tw_collect_summary_data($db, array $timesheetIds, User $user, $permReadOwn, $permReadChild, $permReadAll)
{
	$ids = array();
	foreach ($timesheetIds as $candidate) {
		$candidate = (int) $candidate;
		if ($candidate > 0) {
			$ids[] = $candidate;
		}
	}
	$ids = array_values(array_unique($ids));
	if (empty($ids)) {
		return array('errors' => array('TimesheetWeekSummaryNoSelection'));
	}

	$idList = implode(',', $ids);
$sql = "SELECT t.rowid, t.entity, t.year, t.week, t.total_hours, t.overtime_hours, t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count, t.fk_user, t.fk_user_valid, u.lastname, u.firstname, u.weeklyhours, uv.lastname as validator_lastname, uv.firstname as validator_firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as uv ON uv.rowid = t.fk_user_valid";
	$sql .= " WHERE t.rowid IN (".$idList.")";
	$sql .= " AND t.entity IN (".getEntity('timesheetweek').")";

	$resql = $db->query($sql);
	if (!$resql) {
		return array('errors' => array($db->lasterror()));
	}

	$dataset = array();
	$errors = array();

	while ($row = $db->fetch_object($resql)) {
		$targetUserId = (int) $row->fk_user;
		$canRead = tw_can_act_on_user($targetUserId, $permReadOwn, $permReadChild, ($permReadAll || !empty($user->admin)), $user);
		if (!$canRead) {
			$errors[] = 'TimesheetWeekSummaryUnauthorizedSheet';
			continue;
		}

		$week = (int) $row->week;
		$year = (int) $row->year;

		$weekStart = new DateTime();
		$weekStart->setISODate($year, $week);
		$weekEnd = clone $weekStart;
		$weekEnd->modify('+6 days');

		$contractHours = (float) $row->weeklyhours;
		if ($contractHours <= 0) {
			$contractHours = 35.0;
		}
		$contractHours = min($contractHours, (float) $row->total_hours);

		if (!isset($dataset[$targetUserId])) {
			$userSummary = new User($db);
			if ($userSummary->fetch($targetUserId) <= 0) {
				// EN: Skip users that cannot be fetched due to deletion or entity mismatch.
				// FR: Ignore les utilisateurs introuvables suite à une suppression ou à un écart d'entité.
				$errors[] = 'TimesheetWeekSummaryMissingUser';
				continue;
			}
			$dataset[$targetUserId] = array(
				'user' => $userSummary,
				'records' => array(),
				'totals' => array(
					'total_hours' => 0.0,
					'contract_hours' => 0.0,
					'overtime_hours' => 0.0,
					'meal_count' => 0,
					'zone1_count' => 0,
					'zone2_count' => 0,
					'zone3_count' => 0,
					'zone4_count' => 0,
					'zone5_count' => 0
				)
			);
		}

$approvedBy = '';
if (!empty($row->validator_lastname) || !empty($row->validator_firstname)) {
// EN: Build the approver full name respecting Dolibarr formatting.
// FR: Construit le nom complet de l'approbateur selon le format Dolibarr.
$approvedBy = dolGetFirstLastname($row->validator_firstname, $row->validator_lastname);
}

$record = array(
'id' => (int) $row->rowid,
'week' => $week,
'year' => $year,
'week_start' => $weekStart,
'week_end' => $weekEnd,
'total_hours' => (float) $row->total_hours,
'contract_hours' => (float) $contractHours,
'overtime_hours' => (float) $row->overtime_hours,
'meal_count' => (int) $row->meal_count,
'zone1_count' => (int) $row->zone1_count,
'zone2_count' => (int) $row->zone2_count,
'zone3_count' => (int) $row->zone3_count,
'zone4_count' => (int) $row->zone4_count,
'zone5_count' => (int) $row->zone5_count,
'approved_by' => $approvedBy
);

		$dataset[$targetUserId]['records'][] = $record;
		$dataset[$targetUserId]['totals']['total_hours'] += $record['total_hours'];
		$dataset[$targetUserId]['totals']['contract_hours'] += $record['contract_hours'];
		$dataset[$targetUserId]['totals']['overtime_hours'] += $record['overtime_hours'];
		$dataset[$targetUserId]['totals']['meal_count'] += $record['meal_count'];
		$dataset[$targetUserId]['totals']['zone1_count'] += $record['zone1_count'];
		$dataset[$targetUserId]['totals']['zone2_count'] += $record['zone2_count'];
		$dataset[$targetUserId]['totals']['zone3_count'] += $record['zone3_count'];
		$dataset[$targetUserId]['totals']['zone4_count'] += $record['zone4_count'];
		$dataset[$targetUserId]['totals']['zone5_count'] += $record['zone5_count'];
	}

	$db->free($resql);

	if (empty($dataset)) {
		$errors[] = 'TimesheetWeekSummaryNoData';
	}

	return array(
		'users' => $dataset,
		'errors' => array_values(array_unique($errors))
	);
}

/**
 * EN: Generate the PDF file summarising selected weekly timesheets.
 * FR: Génère le fichier PDF résumant les feuilles de temps hebdomadaires sélectionnées.
 *
 * @param DoliDB    $db                 Database handler
 * @param Conf      $conf               Dolibarr configuration
 * @param Translate $langs              Translator
 * @param User      $user               Current Dolibarr user
 * @param int[]     $timesheetIds       Selected timesheet identifiers
 * @param bool      $permReadOwn        Permission to read own sheets
 * @param bool      $permReadChild      Permission to read subordinates sheets
 * @param bool      $permReadAll        Permission to read all sheets
 * @return array{success:bool,file?:string,relative?:string,errors?:string[],warnings?:string[]}
 */
function tw_generate_summary_pdf($db, $conf, $langs, User $user, array $timesheetIds, $permReadOwn, $permReadChild, $permReadAll)
{
	// EN: Ensure all translations required by the PDF summary are available before rendering.
	// FR: Garantit la disponibilité des traductions nécessaires à la synthèse PDF avant le rendu.
	if (method_exists($langs, 'loadLangs')) {
		$langs->loadLangs(array('timesheetweek@timesheetweek', 'errors'));
	} else {
		// EN: Fallback for older Dolibarr versions that expose only the singular loader.
		// FR: Solution de secours pour les versions de Dolibarr ne proposant que le chargeur unitaire.
		$langs->load('timesheetweek@timesheetweek');
		$langs->load('errors');
	}

	// EN: Guarantee the Dolibarr "main" dictionary is available even when not preloaded.
	// FR: Garantit la disponibilité du dictionnaire « main » de Dolibarr même s'il n'est pas préchargé.
	if (!property_exists($langs, 'loadedlangs') || empty($langs->loadedlangs['main'])) {
		$langs->load('main');
	}

	// EN: Guarantee the Dolibarr "companies" dictionary is available to translate company details.
	// FR: Garantit la disponibilité du dictionnaire « companies » pour traduire les informations société.
	if (!property_exists($langs, 'loadedlangs') || empty($langs->loadedlangs['companies'])) {
		$langs->load('companies');
	}

	$dataResult = tw_collect_summary_data($db, $timesheetIds, $user, $permReadOwn, $permReadChild, $permReadAll);
	$rawWarnings = !empty($dataResult['errors']) ? $dataResult['errors'] : array();
	$warnings = array();
	foreach ($rawWarnings as $warn) {
		if ($warn === null) {
			continue;
		}
		$warnings[] = $langs->trans($warn);
	}
	$warnings = array_values(array_unique(array_filter($warnings)));

	if (empty($dataResult['users'])) {
		return array('success' => false, 'errors' => (!empty($warnings) ? $warnings : array($langs->trans('TimesheetWeekSummaryNoData'))));
	}

	$dataset = $dataResult['users'];
	if (empty($dataset)) {
		return array('success' => false, 'errors' => (!empty($warnings) ? $warnings : array($langs->trans('TimesheetWeekSummaryNoData'))));
	}

	uasort($dataset, function ($a, $b) {
		$aName = dol_string_nohtmltag($a['user']->lastname);
		$bName = dol_string_nohtmltag($b['user']->lastname);
		return strcasecmp($aName, $bName);
	});
	$sortedUsers = array_values($dataset);

	// EN: Track the lowest and highest ISO weeks among the selected records for filename generation.
	// FR: Suit les semaines ISO minimale et maximale parmi les enregistrements sélectionnés pour le nom du fichier.
	$earliestWeek = null;
	$latestWeek = null;
	foreach ($sortedUsers as $userSummary) {
		if (empty($userSummary['records']) || !is_array($userSummary['records'])) {
			continue;
		}
		foreach ($userSummary['records'] as $record) {
			if (!isset($record['week']) || !isset($record['year'])) {
				continue;
			}
			$weekValue = (int) $record['week'];
			$yearValue = (int) $record['year'];
			$compositeKey = sprintf('%04d%02d', $yearValue, $weekValue);
			if ($earliestWeek === null || strcmp($compositeKey, $earliestWeek['key']) < 0) {
				$earliestWeek = array(
					'key' => $compositeKey,
					'week' => $weekValue
				);
			}
			if ($latestWeek === null || strcmp($compositeKey, $latestWeek['key']) > 0) {
				$latestWeek = array(
					'key' => $compositeKey,
					'week' => $weekValue
				);
			}
		}
	}
	$firstWeekLabel = $earliestWeek !== null ? sprintf('%02d', $earliestWeek['week']) : '00';
	$lastWeekLabel = $latestWeek !== null ? sprintf('%02d', $latestWeek['week']) : $firstWeekLabel;

	$uploaddir = !empty($conf->timesheetweek->multidir_output[$conf->entity] ?? null)
		? $conf->timesheetweek->multidir_output[$conf->entity]
		: (!empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek');

	$targetDir = rtrim($uploaddir, '/').'/'.TIMESHEETWEEK_PDF_SUMMARY_SUBDIR;
	if (dol_mkdir($targetDir) < 0) {
		return array('success' => false, 'errors' => array($langs->trans('ErrorCanNotCreateDir', $targetDir)));
	}

	$timestamp = dol_now();
	// EN: Generate the human-readable filename using translations before sanitising it for storage.
	// FR: Génère le nom lisible via les traductions avant de le nettoyer pour l'enregistrement.
	$displayFilename = $langs->trans('TimesheetWeekSummaryFilename', $firstWeekLabel, $lastWeekLabel);
	// EN: Sanitize the filename to match Dolibarr's document security checks and avoid missing file errors.
	// FR: Nettoie le nom de fichier pour correspondre aux contrôles de sécurité Dolibarr et éviter les erreurs d'absence de fichier.
	$filename = dol_sanitizeFileName($displayFilename);
	if ($filename === '') {
		// EN: Fallback on the original label when sanitisation returns an empty value (extreme edge cases).
		// FR: Revient au libellé initial si le nettoyage renvoie une valeur vide (cas extrêmes).
		$filename = dol_sanitizeFileName('timesheetweek-summary-'.$firstWeekLabel.'-'.$lastWeekLabel.'.pdf');
	}
	$filepath = $targetDir.'/'.$filename;

	// EN: Prepare the title and metadata strings reused inside the header block.
	// FR: Prépare les libellés du titre et des métadonnées réemployés dans l'entête.
	$headerTitle = $langs->trans('TimesheetWeekSummaryTitle');
	$headerSubtitle = $langs->trans('TimesheetWeekSummaryGeneratedOnBy', dol_print_date($timestamp, 'dayhour'), $user->getFullName($langs));

	$format = pdf_getFormat();
	$pdfFormat = array($format['width'], $format['height']);
	$margeGauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
	$margeDroite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$margeHaute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
	$margeBasse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
	$footerReserve = 12;
	// EN: Compute the effective auto-break margin to guarantee enough room for the full footer block.
	// FR: Calcule la marge effective des sauts automatiques pour réserver l'espace complet du pied de page.
	$autoPageBreakMargin = $margeBasse + $footerReserve;

	$pdf = pdf_getInstance($pdfFormat);
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$pdf->SetPageOrientation('L');
	$pdf->SetAutoPageBreak(true, $autoPageBreakMargin);
	$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
	$headerState = array('value' => 0.0, 'automatic' => false);
	if (method_exists($pdf, 'setHeaderCallback') && method_exists($pdf, 'setFooterCallback')) {
		// EN: Delegate header rendering to TCPDF so every page created by the engine receives it automatically.
		// FR: Confie le rendu de l'entête à TCPDF afin que chaque page créée par le moteur le reçoive automatiquement.
		$pdf->setPrintHeader(true);
		$pdf->setHeaderCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeHaute, &$headerState, $headerTitle, $headerSubtitle) {
			$headerState['value'] = tw_pdf_draw_header($pdfInstance, $langs, $conf, $margeGauche, $margeHaute, $headerTitle, $headerSubtitle);
			$headerState['automatic'] = true;
		});
		// EN: Delegate footer drawing to TCPDF to guarantee presence on automatic page breaks.
		// FR: Confie le dessin du pied de page à TCPDF pour garantir sa présence lors des sauts automatiques.
		$pdf->setPrintFooter(true);
		$pdf->setFooterCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeDroite, $margeBasse, $autoPageBreakMargin) {
			tw_pdf_draw_footer($pdfInstance, $langs, $conf, $margeGauche, $margeDroite, $margeBasse, null, 0, $autoPageBreakMargin);
		});
	} else {
		// EN: Disable default TCPDF decorations when callbacks are unavailable and rely on manual drawing.
		// FR: Désactive les décorations TCPDF par défaut si les callbacks sont indisponibles et s'appuie sur le dessin manuel.
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
	}
	// EN: Enable alias replacement for total pages when the method exists on the PDF engine.
	// FR: Active le remplacement de l'alias pour le nombre total de pages quand la méthode existe sur le moteur PDF.
	if (method_exists($pdf, 'AliasNbPages')) {
		$pdf->AliasNbPages();
	} elseif (method_exists($pdf, 'setAliasNbPages')) {
		// EN: Fallback for engines exposing the alias configuration through a setter.
		// FR: Solution de secours pour les moteurs exposant la configuration de l'alias via un setter.
		$pdf->setAliasNbPages();
	}
	$pdf->SetCreator('Dolibarr '.DOL_VERSION);
	$pdf->SetAuthor($user->getFullName($langs));
	$pdf->SetTitle($langs->convToOutputCharset($langs->trans('TimesheetWeekSummaryTitle')));
	$pdf->SetSubject($langs->convToOutputCharset($langs->trans('TimesheetWeekSummaryTitle')));
	$pdf->SetFont(pdf_getPDFFont($langs), '', $defaultFontSize);
	$pdf->Open();
	$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $headerState, $autoPageBreakMargin, $headerTitle, $headerSubtitle);
	$pageHeight = $pdf->getPageHeight();

	// EN: Offset the cursor slightly below the header to leave breathing space before the content.
	// FR: Décale le curseur juste sous l'entête pour laisser un espace avant le contenu.
	$contentTop += 2.0;
	$pdf->SetXY($margeGauche, $contentTop);
	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);

	$columnWidthWeights = array(14, 20, 20, 16, 18, 18, 14, 11, 11, 11, 11, 11, 24);
	$columnWidths = $columnWidthWeights;
	$usableWidth = $pdf->getPageWidth() - $margeGauche - $margeDroite;
	$widthSum = array_sum($columnWidthWeights);
	if ($widthSum > 0 && $usableWidth > 0) {
		// EN: Scale each column proportionally so the table spans the full printable width.
		// FR: Redimensionne chaque colonne proportionnellement pour couvrir toute la largeur imprimable.
		foreach ($columnWidthWeights as $index => $weight) {
			$columnWidths[$index] = ($weight / $widthSum) * $usableWidth;
		}
	}
	$columnLabels = array(
		$langs->trans('TimesheetWeekSummaryColumnWeek'),
		$langs->trans('TimesheetWeekSummaryColumnStart'),
		$langs->trans('TimesheetWeekSummaryColumnEnd'),
		$langs->trans('TimesheetWeekSummaryColumnTotalHours'),
		$langs->trans('TimesheetWeekSummaryColumnContractHours'),
		$langs->trans('TimesheetWeekSummaryColumnOvertime'),
		$langs->trans('TimesheetWeekSummaryColumnMeals'),
		$langs->trans('TimesheetWeekSummaryColumnZone1'),
		$langs->trans('TimesheetWeekSummaryColumnZone2'),
		$langs->trans('TimesheetWeekSummaryColumnZone3'),
		$langs->trans('TimesheetWeekSummaryColumnZone4'),
		$langs->trans('TimesheetWeekSummaryColumnZone5'),
		$langs->trans('TimesheetWeekSummaryColumnApprovedBy')
	);

	$lineHeight = 6;

	$isFirstUser = true;
	foreach ($sortedUsers as $userSummary) {
		$userObject = $userSummary['user'];
		$records = $userSummary['records'];
		$totals = $userSummary['totals'];
		
		$recordRows = array();
		foreach ($records as $record) {
			$recordRows[] = array(
			sprintf('%d / %d', $record['week'], $record['year']),
			dol_print_date($record['week_start']->getTimestamp(), 'day'),
			dol_print_date($record['week_end']->getTimestamp(), 'day'),
			tw_format_hours_decimal($record['total_hours']),
			tw_format_hours_decimal($record['contract_hours']),
			tw_format_hours_decimal($record['overtime_hours']),
			(string) $record['meal_count'],
			(string) $record['zone1_count'],
			(string) $record['zone2_count'],
			(string) $record['zone3_count'],
			(string) $record['zone4_count'],
			(string) $record['zone5_count'],
			$record['approved_by']
			);
		}
		
		$totalsRow = array(
		$langs->trans('TimesheetWeekSummaryTotalsLabel'),
		'',
		'',
		tw_format_hours_decimal($totals['total_hours']),
		tw_format_hours_decimal($totals['contract_hours']),
		tw_format_hours_decimal($totals['overtime_hours']),
		(string) $totals['meal_count'],
		(string) $totals['zone1_count'],
		(string) $totals['zone2_count'],
		(string) $totals['zone3_count'],
		(string) $totals['zone4_count'],
		(string) $totals['zone5_count'],
		''
		);
		
$tableHeight = tw_pdf_estimate_user_table_height($pdf, $langs, $userObject, $columnWidths, $columnLabels, $recordRows, $totalsRow, $lineHeight, $usableWidth);
		$spacingBeforeTable = $isFirstUser ? 0 : 4;
		$availableHeight = ($pageHeight - ($margeBasse + $footerReserve)) - $pdf->GetY();
		if (($spacingBeforeTable + $tableHeight) > $availableHeight) {
			$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $headerState, $autoPageBreakMargin, $headerTitle, $headerSubtitle);
			$pageHeight = $pdf->getPageHeight();
			$availableHeight = ($pageHeight - ($margeBasse + $footerReserve)) - $pdf->GetY();
			if (($spacingBeforeTable + $tableHeight) > $availableHeight) {
				// EN: Warn users when a table cannot fit even on a fresh page.
				// FR: Avertit les utilisateurs lorsqu'un tableau ne tient pas même sur une page vierge.
				$warnings[] = $langs->trans('TimesheetWeekSummaryTableTooTall', $userObject->getFullName($langs));
			}
		}
		
		if ($isFirstUser) {
			// EN: Skip the initial spacer so the first table begins on the opening page.
			// FR: Ignore l'espacement initial pour que le premier tableau démarre sur la page d'ouverture.
			$isFirstUser = false;
		} else {
			$pdf->Ln(4);
		}
		
		tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);
		$headerY = $pdf->GetY() + 2;
		// EN: Position the table header just after the employee banner.
		// FR: Positionne l'entête du tableau juste après l'en-tête salarié.
		$pdf->SetY($headerY);
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetLineWidth(0.2);
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$pdf->SetX($margeGauche);
		// EN: Draw the header row with uniform dimensions for every column.
		// FR: Dessine la ligne d'entête avec des dimensions uniformes pour chaque colonne.
		tw_pdf_render_row($pdf, $columnWidths, $columnLabels, $lineHeight, array(
		'fill' => true,
		'alignments' => array_fill(0, count($columnLabels), 'C')
		));
		
		$pdf->SetFont('', '', $defaultFontSize - 1);
		$alignments = array('C', 'C', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L');
	// EN: Render each data row while keeping consistent heights across the table.
		// FR: Affiche chaque ligne de données en conservant des hauteurs cohérentes dans le tableau.
		foreach ($recordRows as $rowData) {
			$pdf->SetX($margeGauche);
			tw_pdf_render_row($pdf, $columnWidths, $rowData, $lineHeight, array(
			'alignments' => $alignments
			));
		}
		
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$pdf->SetX($margeGauche);
		// EN: Print the totals row with left-aligned label and right-aligned figures.
		// FR: Imprime la ligne de totaux avec libellé aligné à gauche et chiffres alignés à droite.
		tw_pdf_render_row($pdf, $columnWidths, $totalsRow, $lineHeight, array(
		'alignments' => array('L', 'C', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L')
		));
		
	}
	$pdf->Output($filepath, 'F');

	return array(
		'success' => true,
		'file' => $filepath,
		'relative' => TIMESHEETWEEK_PDF_SUMMARY_SUBDIR.'/'.$filename,
		'warnings' => $warnings
	);
}
