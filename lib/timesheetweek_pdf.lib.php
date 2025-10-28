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
	$sql = "SELECT t.rowid, t.entity, t.year, t.week, t.total_hours, t.overtime_hours, t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count, t.fk_user, u.lastname, u.firstname, u.weeklyhours";
	$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
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
			'zone5_count' => (int) $row->zone5_count
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

	$format = pdf_getFormat();
	$pdfFormat = array($format['width'], $format['height']);
	$pageHeight = $format['height'];
	$margeGauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
	$margeDroite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$margeHaute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
	$margeBasse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

	$pdf = pdf_getInstance($pdfFormat);
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$pdf->SetAutoPageBreak(true, $margeBasse);
	$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetFont(pdf_getPDFFont($langs), '', $defaultFontSize);
	$pdf->Open();
	$pdf->AddPage();

	$pdf->SetXY($margeGauche, $margeHaute);
	$pdf->SetTextColor(0, 0, 60);
	$pdf->SetFont('', 'B', $defaultFontSize + 3);
	$pdf->MultiCell(0, 6, $langs->trans('TimesheetWeekSummaryTitle'), 0, 'L');

	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->Ln(2);

	$pdf->MultiCell(0, 5, $langs->trans('TimesheetWeekSummaryGeneratedOn', dol_print_date($timestamp, 'dayhour')), 0, 'L');
	$pdf->Ln(2);

	$columnWidths = array(14, 20, 20, 16, 18, 18, 14, 11, 11, 11, 11, 11);
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
		$langs->trans('TimesheetWeekSummaryColumnZone5')
	);

	$lineHeight = 6;

	foreach ($sortedUsers as $userSummary) {
		$userObject = $userSummary['user'];
		$records = $userSummary['records'];
		$totals = $userSummary['totals'];

		$pdf->Ln(4);
		if ($pdf->GetY() + 40 > ($pageHeight - $margeBasse)) {
			$pdf->AddPage();
		}

		$pdf->SetFont('', 'B', $defaultFontSize + 1);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(0, 6, $langs->trans('TimesheetWeekSummaryUserTitle', $userObject->getFullName($langs)), 0, 'L');
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);

		$headerY = $pdf->GetY() + 2;
		if ($headerY + ($lineHeight * (count($records) + 2)) > ($pageHeight - $margeBasse)) {
			$pdf->AddPage();
			$headerY = $pdf->GetY();
		}
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetLineWidth(0.2);
		$pdf->SetFont('', 'B', $defaultFontSize - 1);

		$x = $margeGauche;
		$pdf->SetXY($x, $headerY);
		foreach ($columnLabels as $index => $label) {
			$width = $columnWidths[$index];
			$pdf->MultiCell($width, $lineHeight, $label, 1, 'C', 1, 0);
			$x += $width;
		}
		$pdf->Ln();

		$pdf->SetFont('', '', $defaultFontSize - 1);
		foreach ($records as $record) {
			if ($pdf->GetY() + ($lineHeight * 2) > ($pageHeight - $margeBasse)) {
				$pdf->AddPage();
				$pdf->SetFillColor(230, 230, 230);
				$pdf->SetDrawColor(128, 128, 128);
				$pdf->SetFont('', 'B', $defaultFontSize - 1);
				$x = $margeGauche;
				$pdf->SetXY($x, $pdf->GetY());
				foreach ($columnLabels as $index => $label) {
					$width = $columnWidths[$index];
					$pdf->MultiCell($width, $lineHeight, $label, 1, 'C', 1, 0);
					$x += $width;
				}
				$pdf->Ln();
				$pdf->SetFont('', '', $defaultFontSize - 1);
			}

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
				(string) $record['zone5_count']
			);

			$x = $margeGauche;
			$pdf->SetXY($x, $pdf->GetY());
			foreach ($rowData as $index => $value) {
				$width = $columnWidths[$index];
				$align = ($index >= 3) ? 'R' : 'C';
				$pdf->MultiCell($width, $lineHeight, $value, 1, $align, 0, 0);
				$x += $width;
			}
			$pdf->Ln();
		}

		if ($pdf->GetY() + $lineHeight > ($pageHeight - $margeBasse)) {
			$pdf->AddPage();
		}
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$x = $margeGauche;
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
			(string) $totals['zone5_count']
		);
		foreach ($totalsRow as $index => $value) {
			$width = $columnWidths[$index];
			$align = ($index >= 3) ? 'R' : 'C';
			$pdf->MultiCell($width, $lineHeight, $value, 1, $align, 0, 0);
			$x += $width;
		}
		$pdf->Ln();
		$pdf->SetFont('', '', $defaultFontSize);
	}

	$pdf->Output($filepath, 'F');

	return array(
		'success' => true,
		'file' => $filepath,
		'relative' => TIMESHEETWEEK_PDF_SUMMARY_SUBDIR.'/'.$filename,
		'warnings' => $warnings
	);
}
