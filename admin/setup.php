<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License...
 */

/**
 *  \file       custom/timesheetweek/admin/setup.php
 *  \ingroup    timesheetweek
 *  \brief      Setup page for TimesheetWeek module (numbering, options...)
 */

// ============================
// Bootstrap Dolibarr
// ============================
$res = 0;
if (!$res && file_exists(__DIR__.'/../main.inc.php'))			$res = require_once __DIR__.'/../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../main.inc.php'))		$res = require_once __DIR__.'/../../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../../main.inc.php'))	$res = require_once __DIR__.'/../../../main.inc.php';
if (!$res) die('Include of main fails');

// ============================
// Includes
// ============================
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// ============================
// Load langs
// ============================
$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

// ============================
// Security
// ============================
if (empty($user->admin)) accessforbidden();

// ============================
// Parameters
// ============================
$action = GETPOST('action', 'aZ09');

// ============================
// Actions
// ============================
if ($action == 'setmodule' && $user->admin) {
	$value = GETPOST('value', 'alpha');
	$result = dolibarr_set_const($db, 'TIMESHEETWEEK_MYOBJECT_ADDON', $value, 'chaine', 0, '', $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

// ============================
// View
// ============================
$form = new Form($db);

$title   = $langs->trans("ModuleSetup", "TimesheetWeek");
$helpurl = '';

llxHeader('', $title, $helpurl);

print load_fiche_titre($langs->trans("TimesheetWeekSetup"), '', 'bookcal@timesheetweek');
print '<br>';

// ----------------------------
// Numbering modules list
// ----------------------------
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setmodule">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';

// Title
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Name").'</th>';
print '<th>'.$langs->trans("Description").'</th>';
print '<th class="center">'.$langs->trans("Status").'</th>';
print '</tr>';

// Scan all models directories for numbering modules
$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
$selected  = getDolGlobalString('TIMESHEETWEEK_MYOBJECT_ADDON', 'mod_timesheetweek_standard');

$found = 0;
foreach ($dirmodels as $reldir) {
	$dir = dol_buildpath($reldir."core/modules/timesheetweek/");
	if (!is_dir($dir)) continue;

	$filelist = dol_dir_list($dir, 'files', 0, '^mod_.*\.php$');
	foreach ($filelist as $fileinfo) {
		$found++;
		$file = $fileinfo['name'];
		$classname = preg_replace('/\.php$/', '', $file);

		// Include module file
		@include_once $dir.$file;
		if (!class_exists($classname)) continue;

		// Instantiate
		$gen = null;
		try {
			$gen = new $classname();
		} catch (Throwable $e) {
			// Some generators require $db in constructor, try with it
			try {
				$gen = new $classname($db);
			} catch (Throwable $e2) {
				continue;
			}
		}

		// Human label
		$label = (property_exists($gen, 'name') && $gen->name) ? $gen->name : $classname;

		// Robust description retrieval:
		$desc = '';
		if (method_exists($gen, 'info')) {
			try {
				$rm = new ReflectionMethod($gen, 'info');
				$nb = $rm->getNumberOfParameters();
				if ($nb == 0) {
					$desc = $gen->info();
				} else {
					// Try with $langs first (most common), fallback to $db
					try {
						$desc = $gen->info($langs);
					} catch (Throwable $e) {
						try {
							$desc = $gen->info($db);
						} catch (Throwable $e2) {
							$desc = '';
						}
					}
				}
			} catch (Throwable $e) {
				// ignore
			}
		} elseif (property_exists($gen, 'desc')) {
			$desc = $gen->desc;
		}

		print '<tr class="oddeven">';
		print '<td>';

		$checked = ($selected == $classname) ? ' checked' : '';
		print '<label>';
		print '<input type="radio" class="flat" name="value" value="'.dol_escape_htmltag($classname).'"'.$checked.'> ';
		print dol_escape_htmltag($label).' <span class="opacitymedium">('.dol_escape_htmltag($classname).')</span>';
		print '</label>';

		print '</td>';
		print '<td class="small">'.($desc ? dol_escape_htmltag($desc) : '&nbsp;').'</td>';

		print '<td class="center">';
		if ($checked) {
			print img_picto($langs->trans("Enabled"), 'status1');
		} else {
			print img_picto($langs->trans("Disabled"), 'status0');
		}
		print '</td>';

		print '</tr>';
	}
}

if (!$found) {
	print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}

print '</table>';
print '</div>';

print '<div class="center margintoponly">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Footer
llxFooter();
$db->close();
