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
 * EN: Render a structured header inspired by the standard timesheet layout.
 * FR: Dessine un en-tête structuré inspiré de la mise en page standard des feuilles de temps.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $topMargin
 * @param float $rightMargin
 * @param array $context
 * @return float
 */
function tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $rightMargin, array $context = array())
{
	global $mysoc;

	// EN: Ensure core and module translations are available for the header labels.
	// FR: Garantit la disponibilité des traductions cœur et module pour les libellés d'entête.
	$langs->loadLangs(array('main', 'bills', 'companies', 'timesheetweek@timesheetweek'));

	$pageWidth = $pdf->getPageWidth();
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$headerTop = $topMargin;
	$headerHeight = 38;
	$availableWidth = $pageWidth - $leftMargin - $rightMargin;
	if ($availableWidth < 60) {
		$availableWidth = 60;
	}
	$infoWidth = 110;
	if ($infoWidth > $availableWidth - 60) {
		$infoWidth = max(80, (int) ($availableWidth / 2));
	}
	$companyWidth = $availableWidth - $infoWidth;
	$infoPosX = $pageWidth - $rightMargin - $infoWidth;

	$pdf->SetFillColor(240, 240, 240);
	$pdf->Rect($leftMargin, $headerTop, $availableWidth, $headerHeight, 'F');

	$logoX = $leftMargin + 3;
	$logoY = $headerTop + 3;
	$textX = $logoX;
	$textWidth = $companyWidth - 6;
	$logoPrinted = false;

	if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
		// EN: Resolve the best matching logo between the large and thumbnail versions.
		// FR: Détermine le logo le plus adapté entre les versions grande et miniature.
		if (!empty($mysoc->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$conf->entity] ?? null)) {
				$logodir = $conf->mycompany->multidir_output[$conf->entity];
			}
			$logo = '';
			if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($mysoc->logo_small)) {
				$logoCandidate = $logodir.'/logos/thumbs/'.$mysoc->logo_small;
				if (is_readable($logoCandidate)) {
					$logo = $logoCandidate;
				}
			}
			if ($logo === '' && !empty($mysoc->logo)) {
				$logoCandidate = $logodir.'/logos/'.$mysoc->logo;
				if (is_readable($logoCandidate)) {
					$logo = $logoCandidate;
				}
			}
			if ($logo !== '') {
				$height = pdf_getHeightForLogo($logo);
				if ($height > ($headerHeight - 8)) {
					$height = $headerHeight - 8;
				}
				$pdf->Image($logo, $logoX, $logoY, 0, $height);
				$logoPrinted = true;
				$textX = $logoX + 38;
				$textWidth = $companyWidth - ($textX - $leftMargin) - 6;
			}
		}
		if (!$logoPrinted) {
			$defaultLogo = DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.svg';
			if (is_readable($defaultLogo)) {
				$height = pdf_getHeightForLogo($defaultLogo);
				if ($height > ($headerHeight - 8)) {
					$height = $headerHeight - 8;
				}
				$pdf->Image($defaultLogo, $logoX, $logoY, 0, $height);
				$logoPrinted = true;
				$textX = $logoX + 38;
				$textWidth = $companyWidth - ($textX - $leftMargin) - 6;
			}
		}
	}

	if ($textWidth < 40) {
		$textWidth = 40;
	}

	$companyName = !empty($mysoc->name) ? $mysoc->name : 'Dolibarr ERP & CRM';
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize + 1);
	$pdf->SetXY($textX, $headerTop + 4);
	$pdf->MultiCell($textWidth, 5, $langs->convToOutputCharset($companyName), 0, 'L');

	$pdf->SetFont('', '', $defaultFontSize - 1);
	$pdf->SetTextColor(0, 0, 0);
	$companyLines = array();
	if (!empty($mysoc->address)) {
		$companyLines[] = $mysoc->address;
	}
	$cityLine = trim(($mysoc->zip ? $mysoc->zip.' ' : '').$mysoc->town);
	if ($cityLine !== '') {
		$companyLines[] = $cityLine;
	}
	if (!empty($mysoc->phone)) {
		$companyLines[] = $langs->transnoentities('Phone').': '.$mysoc->phone;
	}
	if (!empty($mysoc->email)) {
		$companyLines[] = $mysoc->email;
	}
	if (!empty($mysoc->url)) {
		$companyLines[] = $mysoc->url;
	}
	$companyText = implode("\n", array_filter($companyLines));
	if ($companyText !== '') {
		$pdf->SetXY($textX, $pdf->GetY());
		$pdf->MultiCell($textWidth, 4, $langs->convToOutputCharset($companyText), 0, 'L');
	}

	$pdf->SetFillColor(32, 55, 100);
	$pdf->Rect($infoPosX + 1, $headerTop + 2, $infoWidth - 2, $headerHeight - 4, 'F');

	$pdf->SetTextColor(255, 255, 255);
	$pdf->SetFont('', 'B', $defaultFontSize + 2);
	$pdf->SetXY($infoPosX + 5, $headerTop + 6);
	$title = $context['title'] ?? $langs->trans('TimesheetWeekSummaryTitle');
	$pdf->MultiCell($infoWidth - 10, 6, $langs->convToOutputCharset($title), 0, 'R');

	$pdf->SetFont('', '', $defaultFontSize - 1);
	$infoLines = array();
	$generatedOn = $context['generated_on'] ?? '';
	$generatedBy = $context['generated_by'] ?? '';
	if ($generatedOn !== '' && $generatedBy !== '') {
		$infoLines[] = $langs->trans('TimesheetWeekSummaryGeneratedOnBy', $generatedOn, $generatedBy);
	} elseif ($generatedOn !== '') {
		$infoLines[] = $langs->trans('TimesheetWeekSummaryGeneratedOn', $generatedOn);
	} elseif ($generatedBy !== '') {
		$infoLines[] = $langs->trans('TimesheetWeekSummaryGeneratedBy', $generatedBy);
	}
	if (!empty($context['selection_label'])) {
		$infoLines[] = $context['selection_label'];
	}
	if (!empty($infoLines)) {
		$pdf->SetXY($infoPosX + 5, $headerTop + 14);
		$pdf->MultiCell($infoWidth - 10, 4, $langs->convToOutputCharset(implode("\n", $infoLines)), 0, 'R');
	}
	$pdf->SetTextColor(0, 0, 0);

	return $headerTop + $headerHeight;
}

/**
 * EN: Draw a structured footer including contact and document details.
 * FR: Dessine un pied de page structuré incluant les coordonnées et les détails du document.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $rightMargin
 * @param float $bottomMargin
 * @param array $context
 * @return void
 */
function tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, array $context = array())
{
	global $mysoc;

	// EN: Load the necessary translations for footer sections.
	// FR: Charge les traductions nécessaires pour les sections du pied de page.
	$langs->loadLangs(array('main', 'companies', 'bills', 'timesheetweek@timesheetweek'));

	$pageWidth = $pdf->getPageWidth();
	$pageHeight = $pdf->getPageHeight();
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$availableWidth = $pageWidth - $leftMargin - $rightMargin;
	$footerHeight = 26;
	$footTop = $pageHeight - $bottomMargin - $footerHeight;
	if ($footTop < 0) {
		$footTop = 0;
	}

	$pdf->SetFillColor(240, 240, 240);
	$pdf->Rect($leftMargin, $footTop, $availableWidth, $footerHeight, 'F');

	$columnWidth = $availableWidth / 3;

	// EN: Contact information column.
	// FR: Colonne des informations de contact.
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize - 1);
	$pdf->SetXY($leftMargin + 2, $footTop + 2);
	$pdf->MultiCell($columnWidth - 4, 4, $langs->transnoentities('TimesheetWeekPdfContact'), 0, 'L');
	$pdf->SetFont('', '', $defaultFontSize - 2);
	$pdf->SetTextColor(0, 0, 0);
	$contactLines = array();
	$contactLines[] = !empty($mysoc->name) ? $mysoc->name : 'Dolibarr ERP & CRM';
	if (!empty($mysoc->address)) {
		$contactLines[] = $mysoc->address;
	}
	$cityLine = trim(($mysoc->zip ? $mysoc->zip.' ' : '').$mysoc->town);
	if ($cityLine !== '') {
		$contactLines[] = $cityLine;
	}
	if (!empty($mysoc->phone)) {
		$contactLines[] = $langs->transnoentities('Phone').': '.$mysoc->phone;
	}
	if (!empty($mysoc->email)) {
		$contactLines[] = $mysoc->email;
	}
	if (!empty($mysoc->url)) {
		$contactLines[] = $mysoc->url;
	}
	$contactText = implode("\n", array_filter($contactLines));
	if ($contactText !== '') {
		$pdf->SetXY($leftMargin + 2, $pdf->GetY());
		$pdf->MultiCell($columnWidth - 4, 4, $langs->convToOutputCharset($contactText), 0, 'L');
	}

	// EN: Legal identifiers column.
	// FR: Colonne des identifiants légaux.
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize - 1);
	$pdf->SetXY($leftMargin + $columnWidth + 2, $footTop + 2);
	$pdf->MultiCell($columnWidth - 4, 4, $langs->transnoentities('TimesheetWeekPdfCompanyId'), 0, 'L');
	$pdf->SetFont('', '', $defaultFontSize - 2);
	$pdf->SetTextColor(0, 0, 0);
	$legalLines = array();
	if (!empty($mysoc->idprof1)) {
		$legalLines[] = $langs->transnoentities('ProfId1').': '.$mysoc->idprof1;
	}
	if (!empty($mysoc->idprof2)) {
		$legalLines[] = $langs->transnoentities('ProfId2').': '.$mysoc->idprof2;
	}
	if (!empty($mysoc->idprof3)) {
		$legalLines[] = $langs->transnoentities('ProfId3').': '.$mysoc->idprof3;
	}
	if (!empty($mysoc->capital)) {
		$legalLines[] = $langs->transnoentities('Capital').': '.$mysoc->capital;
	}
	if (!empty($mysoc->tva_intra)) {
		$legalLines[] = $langs->transnoentities('VATIntra').': '.$mysoc->tva_intra;
	}
	$legalText = implode("\n", array_filter($legalLines));
	if ($legalText !== '') {
		$pdf->SetXY($leftMargin + $columnWidth + 2, $pdf->GetY());
		$pdf->MultiCell($columnWidth - 4, 4, $langs->convToOutputCharset($legalText), 0, 'L');
	}

	// EN: Document summary column.
	// FR: Colonne de synthèse du document.
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize - 1);
	$pdf->SetXY($leftMargin + ($columnWidth * 2) + 2, $footTop + 2);
	$pdf->MultiCell($columnWidth - 4, 4, $langs->transnoentities('TimesheetWeekPdfDocument'), 0, 'R');
	$pdf->SetFont('', '', $defaultFontSize - 2);
	$pdf->SetTextColor(0, 0, 0);
	$docLines = array();
	$title = $context['title'] ?? $langs->trans('TimesheetWeekSummaryTitle');
	$docLines[] = $langs->convToOutputCharset($title);
	if (!empty($context['generated_on'])) {
		$docLines[] = $langs->trans('TimesheetWeekSummaryGeneratedOn', $context['generated_on']);
	}
	if (!empty($context['generated_by'])) {
		$docLines[] = $langs->trans('TimesheetWeekSummaryGeneratedBy', $context['generated_by']);
	}
	$docLines[] = sprintf($langs->trans('TimesheetWeekPdfPage'), $pdf->getPage(), $pdf->getAliasNbPages());
	$pdf->SetXY($leftMargin + ($columnWidth * 2) + 2, $pdf->GetY());
	$pdf->MultiCell($columnWidth - 4, 4, $langs->convToOutputCharset(implode("\n", $docLines)), 0, 'R');

	// EN: Optional footer note sourced from configuration.
	// FR: Note optionnelle de pied de page issue de la configuration.
	$footerNote = '';
	if (!empty($context['allow_free_text'])) {
		$footerNote = trim(getDolGlobalString('TIMESHEETWEEK_PDF_FREETEXT'));
		if ($footerNote === '') {
			$footerNote = trim(getDolGlobalString('MAIN_PDF_FOOTER_TEXT'));
		}
	}
	if ($footerNote !== '') {
		$pdf->SetXY($leftMargin + 2, $footTop + $footerHeight - 6);
		$pdf->SetFont('', '', $defaultFontSize - 2);
		$pdf->MultiCell($availableWidth - 4, 4, $langs->convToOutputCharset($footerNote), 0, 'L');
	}

	$pdf->SetTextColor(0, 0, 0);
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
 * @param array $context
 * @param array $headerState
 * @return float
 */
function tw_pdf_add_landscape_page($pdf, $langs, $conf, $leftMargin, $topMargin, $rightMargin, $bottomMargin, array $context = array(), &$headerState = null)
{
	$pdf->AddPage('L');
	// EN: Detect whether TCPDF already rendered the header via callback.
	// FR: Détecte si TCPDF a déjà rendu l'entête via le callback.
	$hasAutomaticHeader = is_array($headerState) && !empty($headerState['automatic']) && array_key_exists('value', $headerState);
	if ($hasAutomaticHeader) {
		$headerBottom = (float) $headerState['value'];
	} else {
		$headerBottom = tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $rightMargin, $context);
		tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, $context);
		if (is_array($headerState)) {
			// EN: Store the manual header height for subsequent calls.
			// FR: Stocke la hauteur de l'entête manuel pour les appels suivants.
			$headerState['value'] = $headerBottom;
			$headerState['automatic'] = false;
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
		global $conf, $langs;

		// EN: Load the translations required to build summary PDF labels.
		// FR: Charge les traductions nécessaires pour construire les libellés du PDF de synthèse.
		$langs->loadLangs(array('main', 'bills', 'companies', 'timesheetweek@timesheetweek'));

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
 * EN: Generate the PDF file summarising selected weekly timesheets with structured header/footer context.
 * FR: Génère le fichier PDF résumant les feuilles de temps hebdomadaires sélectionnées avec un contexte d'entête/pied structuré.
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

	$uploaddir = !empty($conf->timesheetweek->multidir_output[$conf->entity] ?? null)
		? $conf->timesheetweek->multidir_output[$conf->entity]
		: (!empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek');

	$targetDir = rtrim($uploaddir, '/').'/'.TIMESHEETWEEK_PDF_SUMMARY_SUBDIR;
	if (dol_mkdir($targetDir) < 0) {
		return array('success' => false, 'errors' => array($langs->trans('ErrorCanNotCreateDir', $targetDir)));
	}

	$timestamp = dol_now();
	$filename = 'timesheetweek-summary-'.dol_print_date($timestamp, 'dayhourlog').'.pdf';
	$filepath = $targetDir.'/'.$filename;
	$generatedOnLabel = dol_print_date($timestamp, 'dayhour', false, $langs, true);
	$generatedByLabel = $user->getFullName($langs);
	// EN: Prepare the metadata shared between header and footer renderers.
	// FR: Prépare les métadonnées partagées entre les rendus d'entête et de pied de page.
	$renderContext = array(
		'title' => $langs->trans('TimesheetWeekSummaryTitle'),
		'generated_on' => $generatedOnLabel,
		'generated_by' => $generatedByLabel,
		'allow_free_text' => true
	);

	$format = pdf_getFormat();
	$pdfFormat = array($format['width'], $format['height']);
	$margeGauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
	$margeDroite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$margeHaute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
	$margeBasse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
	$footerReserve = 12;

	$pdf = pdf_getInstance($pdfFormat);
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$pdf->SetPageOrientation('L');
	$pdf->SetAutoPageBreak(true, $margeBasse + $footerReserve);
	$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
	$headerState = array('value' => 0.0, 'automatic' => false);
	if (method_exists($pdf, 'setHeaderCallback') && method_exists($pdf, 'setFooterCallback')) {
		// EN: Delegate header rendering to TCPDF so every page created by the engine receives it automatically.
		// FR: Confie le rendu de l'entête à TCPDF afin que chaque page créée par le moteur le reçoive automatiquement.
		$pdf->setPrintHeader(true);
		$pdf->setHeaderCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeHaute, $margeDroite, $renderContext, &$headerState) {
			$headerState['value'] = tw_pdf_draw_header($pdfInstance, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $renderContext);
			$headerState['automatic'] = true;
		});
		// EN: Delegate footer drawing to TCPDF to guarantee presence on automatic page breaks.
		// FR: Confie le dessin du pied de page à TCPDF pour garantir sa présence lors des sauts automatiques.
		$pdf->setPrintFooter(true);
		$pdf->setFooterCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeDroite, $margeBasse, $renderContext) {
			tw_pdf_draw_footer($pdfInstance, $langs, $conf, $margeGauche, $margeDroite, $margeBasse, $renderContext);
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
	$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $renderContext, $headerState);
	$pageHeight = $pdf->getPageHeight();

	$pdf->SetXY($margeGauche, $contentTop);
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize + 3);
	$pdf->MultiCell(0, 6, tw_pdf_format_cell_html($langs->trans('TimesheetWeekSummaryTitle')), 0, 'L', 0, 1, '', '', true, 0, true);

	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->Ln(2);

	$pdf->MultiCell(0, 5, tw_pdf_format_cell_html($langs->trans('TimesheetWeekSummaryGeneratedOnBy', dol_print_date($timestamp, 'dayhour'), $user->getFullName($langs))), 0, 'L', 0, 1, '', '', true, 0, true);
	$pdf->Ln(2);

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

			if ($isFirstUser) {
				// EN: Skip the initial spacer so the first table begins on the opening page.
				// FR: Ignore l'espacement initial pour que le premier tableau démarre sur la page d'ouverture.
				$isFirstUser = false;
			} else {
				$pdf->Ln(4);
			}
			// EN: Insert a new page when the banner and header would overflow.
			// FR: Ajoute une nouvelle page si la bannière et l'entête débordent.
			if ($pdf->GetY() + 40 > ($pageHeight - ($margeBasse + $footerReserve))) {
				$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $renderContext, $headerState);
				$pageHeight = $pdf->getPageHeight();
			}

			tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);

			// EN: Pre-calculate the header height to avoid unexpected page breaks.
			// FR: Pré-calcule la hauteur de l'entête pour éviter les sauts de page imprévus.
			$headerRowHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $columnLabels, $lineHeight);
			if ($pdf->GetY() + 2 + $headerRowHeight > ($pageHeight - ($margeBasse + $footerReserve))) {
				$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $renderContext, $headerState);
				$pageHeight = $pdf->getPageHeight();
				tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);
			}
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
			foreach ($records as $record) {
				$rowData = array(
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

				$dataRowHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $rowData, $lineHeight);
				// EN: Trigger a new page and redraw the header when the upcoming row would overflow.
				// FR: Déclenche une nouvelle page et redessine l'entête si la prochaine ligne dépasse la marge.
				if ($pdf->GetY() + $dataRowHeight > ($pageHeight - ($margeBasse + $footerReserve))) {
					$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $renderContext, $headerState);
					$pageHeight = $pdf->getPageHeight();
					tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);
					$pdf->Ln(2);
					$pdf->SetFillColor(230, 230, 230);
					$pdf->SetDrawColor(128, 128, 128);
					$pdf->SetLineWidth(0.2);
					$pdf->SetFont('', 'B', $defaultFontSize - 1);
					$pdf->SetX($margeGauche);
					// EN: Reprint the header to preserve column context after the page break.
					// FR: Réimprime l'entête pour conserver le contexte des colonnes après le saut de page.
					tw_pdf_render_row($pdf, $columnWidths, $columnLabels, $lineHeight, array(
							'fill' => true,
							'alignments' => array_fill(0, count($columnLabels), 'C')
					));
					$pdf->SetFont('', '', $defaultFontSize - 1);
				}
				$pdf->SetX($margeGauche);
				// EN: Output the data row with harmonised heights and numeric alignment.
				// FR: Affiche la ligne de données avec des hauteurs harmonisées et des alignements numériques.
				tw_pdf_render_row($pdf, $columnWidths, $rowData, $lineHeight, array(
							'alignments' => $alignments
				));
			}

			// EN: Build the totals row to summarise the selected weeks per employee.
			// FR: Construit la ligne de totaux pour résumer les semaines sélectionnées par salarié.
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
			$totalsRowHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $totalsRow, $lineHeight);
			// EN: Manage page breaks for totals to keep the layout consistent.
			// FR: Gère les sauts de page pour la ligne de totaux afin de garder une mise en page cohérente.
			if ($pdf->GetY() + $totalsRowHeight > ($pageHeight - ($margeBasse + $footerReserve))) {
				$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $renderContext, $headerState);
				$pageHeight = $pdf->getPageHeight();
				tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);
				$pdf->Ln(2);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->SetDrawColor(128, 128, 128);
				$pdf->SetLineWidth(0.2);
				$pdf->SetFont('', 'B', $defaultFontSize - 1);
				$pdf->SetX($margeGauche);
				// EN: Redisplay the header to accompany totals on a fresh page.
				// FR: Réaffiche l'entête pour accompagner les totaux sur une nouvelle page.
				tw_pdf_render_row($pdf, $columnWidths, $columnLabels, $lineHeight, array(
							'fill' => true,
							'alignments' => array_fill(0, count($columnLabels), 'C')
				));
				$pdf->SetFont('', '', $defaultFontSize - 1);
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
