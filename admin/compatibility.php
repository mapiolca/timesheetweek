<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment with fallback paths.
$res = 0;
if (!$res && file_exists(__DIR__.'/../main.inc.php')) {
	$res = require_once __DIR__.'/../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = require_once __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = require_once __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweekcompatibility.class.php');

$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

if (empty($user->admin)) {
	accessforbidden();
}

$title = $langs->trans('TimesheetWeekCompatibility');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('timesheetweek').'">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title, '');

print load_fiche_titre($title, $linkback, 'title_setup');

$head = timesheetweekAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $title, -1, 'fa-calendar-check');

print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekCompatibilityPageHelp').'</div>';
print '<br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('TimesheetWeekCompatibilityEnvironment').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TimesheetWeekCompatibilityDetectedDolibarr').'</td><td>'.dol_escape_htmltag(defined('DOL_VERSION') ? DOL_VERSION : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekCompatibilityDetectedPhp').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekCompatibilityMinDolibarr').'</td><td>'.dol_escape_htmltag(TimesheetWeekCompatibility::MIN_DOLIBARR).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekCompatibilityMinPhp').'</td><td>'.dol_escape_htmltag(TimesheetWeekCompatibility::MIN_PHP).'</td></tr>';
print '</table>';
print '</div>';

print '<br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Code').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th>'.$langs->trans('TimesheetWeekCompatibilityAvailableFrom').'</th>';
print '<th>'.$langs->trans('Status').'</th>';
print '<th>'.$langs->trans('Reason').'</th>';
print '</tr>';

foreach (TimesheetWeekCompatibility::getCompatibilityFeatures() as $code => $feature) {
	$status = !empty($feature['available']) ? $langs->trans('Available') : $langs->trans('Unavailable');
	$threshold = !empty($feature['module_available_from']) ? $feature['module_available_from'] : '';
	if (!empty($feature['min_dolibarr'])) {
		$threshold .= ($threshold !== '' ? ' - ' : '').$langs->trans('Dolibarr').' '.$feature['min_dolibarr'];
	}

	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['label'])).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['description'])).'</td>';
	print '<td>'.dol_escape_htmltag($threshold).'</td>';
	print '<td>'.dol_escape_htmltag($status).'</td>';
	print '<td>'.dol_escape_htmltag(!empty($feature['available']) ? $langs->trans('OK') : $langs->trans($feature['reason'])).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print '<br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('TimesheetWeekAgendaDiagnostics').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="right">'.$langs->trans('Number').'</th>';
print '<th>'.$langs->trans('Status').'</th>';
print '</tr>';

foreach (TimesheetWeekCompatibility::getAgendaDiagnostics($db) as $diagnostic) {
	$count = (int) $diagnostic['count'];
	$status = $count < 0 ? $langs->trans('Error') : ($count > 0 ? $langs->trans('TimesheetWeekCompatibilityToCheck') : $langs->trans('OK'));

	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($langs->trans($diagnostic['label'])).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans($diagnostic['description'])).'</td>';
	print '<td class="right">'.($count >= 0 ? (int) $count : dol_escape_htmltag($langs->trans('Error'))).'</td>';
	print '<td>'.dol_escape_htmltag($status).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
