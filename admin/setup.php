<?php
/* Copyright (C)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU GPL v3 or later.
 */

// Load Dolibarr env (ALWAYS use require_once pattern)
$res = 0;
if (!$res && file_exists(__DIR__.'/../main.inc.php'))	$res = require_once __DIR__.'/../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../main.inc.php'))	$res = require_once __DIR__.'/../../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = require_once __DIR__.'/../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

// Langs
$langs->loadLangs(array('admin', 'timesheetweek@timesheetweek'));

// Security: admin only for setup pages
if (empty($user->admin)) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');

// Actions: set constants
if ($action == 'set') {
	$error = 0;

	$addon = GETPOST('TIMESHEETWEEK_ADDON', 'alpha');
	if ($addon !== '') {
		if (dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON', $addon, 'chaine', 0, '', $conf->entity) <= 0) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

// Build list of available numbering modules
$moddircore   = DOL_DOCUMENT_ROOT.'/timesheetweek/core/modules/timesheetweek';     // if module was installed into htdocs (rare)
$moddircustom = DOL_DOCUMENT_ROOT.'/custom/timesheetweek/core/modules/timesheetweek';

$files = array();
if (is_dir($moddircustom)) {
	$tmp = dol_dir_list($moddircustom, 'files', 0, 'mod_.*\.php', '', 'name', SORT_ASC, 0, 1);
	if (is_array($tmp)) $files = array_merge($files, $tmp);
}
if (is_dir($moddircore) && $moddircore != $moddircustom) {
	$tmp = dol_dir_list($moddircore, 'files', 0, 'mod_.*\.php', '', 'name', SORT_ASC, 0, 1);
	if (is_array($tmp)) $files = array_merge($files, $tmp);
}

$current_addon = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_fhweekly');

$title = $langs->trans("ModuleSetup", $langs->transnoentitiesnoconv("TimesheetWeek"));
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'bookcal');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '<td class="right">'.$langs->trans("Action").'</td>';
print '</tr>';

// Numbering module choice
print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans("NumberingModule").'</td>';
print '<td>';

if (empty($files)) {
	// fallback — au minimum notre module FH weekly
	print '<input type="text" class="flat minwidth200" name="TIMESHEETWEEK_ADDON" value="'.dol_escape_htmltag($current_addon).'">';
	print '<div class="opacitymedium">'.$langs->trans("NoNumberingModuleFound").'</div>';
} else {
	print '<select name="TIMESHEETWEEK_ADDON" class="flat minwidth200">';
	foreach ($files as $fileinfo) {
		$file = $fileinfo['fullname'];
		$classname = preg_replace('/\.php$/', '', $fileinfo['name']); // ex: mod_timesheetweek_fhweekly
		print '<option value="'.$classname.'"'.($classname == $current_addon ? ' selected' : '').'>'.$classname.'</option>';
	}
	print '</select>';
}
print '</td>';
print '<td class="right"><input type="submit" class="button small" value="'.$langs->trans("Modify").'"></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
