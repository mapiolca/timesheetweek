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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       custom/timesheetweek/admin/setup.php
 *  \ingroup    timesheetweek
 *  \brief      Setup page for TimesheetWeek module (numbering, options...)
 */

// EN: Load Dolibarr environment with fallback paths.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
// EN: Load email template class with backward compatibility for older Dolibarr versions.

if (version_compare(DOL_VERSION, '23.0.0', '<')) {
		dol_inclunde_once('/timesheetweek/core/class/cemailtemplate.class.php');
} else {
	require_once DOL_DOCUMENT_ROOT.'/core/class/cemailtemplate.class.php';
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/doc.lib.php';
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/class/timesheetweek_reminder.class.php');

// EN: Load translation files required for the configuration page.
$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

// EN: Only administrators can access the setup.
if (empty($user->admin)) {
		accessforbidden();
}

// EN: Read HTTP parameters once so we can re-use them further down.
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alphanohtml');
$token = GETPOST('token', 'alphanohtml');
// EN: Capture additional parameters used to reproduce Dolibarr's document model toggles.
$docLabel = GETPOST('label', 'alphanohtml');
$scanDir = GETPOST('scan_dir', 'alpha');

$form = new Form($db);

$sql = "SELECT rowid, label ";
$sql.= "FROM ".MAIN_DB_PREFIX."c_email_templates ";
$sql.= "WHERE active='1' ";
//$sql.= "AND enabled='1' ";
$sql.= "AND type_template = 'actioncomm_send' ";
//$sql.= "AND entity='".getEntity('timesheetweek')."' ";
//$sql.= "GROUP BY label";
$result = $db->query($sql);
$templateOptions = array();
if ($result) {
	while ($obj = $db->fetch_object($result)) {
		$templateOptions[(int) $obj->rowid] = $obj->label;
	}
}

// EN: Helper to enable a PDF model in the database.
function timesheetweekEnableDocumentModel($model, $label = '', $scandir = '')
{
	global $db, $conf;

	if (empty($model)) {
		return 0;
	}

	// EN: Check if the model already exists for the current entity to avoid duplicates.
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."document_model WHERE nom='".$db->escape($model)."' AND type='timesheetweek' AND entity=".((int) $conf->entity);
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}

	$existing = $db->fetch_object($resql);
	$db->free($resql);

	if ($existing) {
		$fields = array();

		// EN: Refresh label when provided to keep UI messages in sync.
		if ($label !== '') {
			$fields[] = "libelle='".$db->escape($label)."'";
		}

		// EN: Refresh directory hint when provided to ease future scans.
		if ($scandir !== '') {
			$fields[] = "description='".$db->escape($scandir)."'";
		}

		if (!empty($fields)) {
			$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX."document_model SET ".implode(', ', $fields).' WHERE rowid='.((int) $existing->rowid);
			if (!$db->query($sqlUpdate)) {
				return -1;
			}
		}

		return 1;
	}

	$result = addDocumentModel($model, 'timesheetweek', $label, $scandir);
	if ($result > 0) {
		return 1;
	}

	return ($result === 0) ? 1 : -1;
}


// EN: Helper to disable a PDF model from the database.
function timesheetweekDisableDocumentModel($model)
{
	if (empty($model)) {
		return 0;
	}

	$result = delDocumentModel($model, 'timesheetweek');
	return ($result > 0) ? 1 : ($result === 0 ? 0 : -1);
}

// EN: Build the list of numbering modules available for the module.
function timesheetweekListNumberingModules(array $directories, Translate $langs, TimesheetWeek $sample, $selected)
{
		global $db;

		$modules = array();

		foreach ($directories as $reldir) {
			// EN: Resolve the directory that holds the numbering module classes.
			$dir = dol_buildpath(rtrim($reldir, '/').'/timesheetweek/core/modules/timesheetweek/');
				if (!is_dir($dir)) {
						continue;
				}

				$files = dol_dir_list($dir, 'files', 0, '^mod_.*\.php$');
				foreach ($files as $fileinfo) {
						$file = $fileinfo['name'];
						$classname = preg_replace('/\.php$/', '', $file);

						require_once $dir.$file;
						if (!class_exists($classname)) {
								continue;
						}

						$module = new $classname($db);

						$label = !empty($module->name) ? $module->name : $classname;
						if ($label && $langs->transnoentitiesnoconv($label) !== $label) {
								$label = $langs->trans($label);
						}

						$description = '';
						if (method_exists($module, 'info')) {
								$description = $module->info($langs);
						} elseif (!empty($module->description)) {
								$description = $module->description;
						}
						if ($description && $langs->transnoentitiesnoconv($description) !== $description) {
								$description = $langs->trans($description);
						}

						$example = '';
						if (method_exists($module, 'getExample')) {
								$example = $module->getExample($sample);
						}

						$canBeActivated = true;
						$activationError = '';
						if (method_exists($module, 'canBeActivated')) {
								$canBeActivated = (bool) $module->canBeActivated($sample);
								if (!$canBeActivated && !empty($module->error)) {
										$activationError = $module->error;
								}
						}

						$modules[] = array(
								'classname' => $classname,
								'label' => $label,
								'description' => $description,
								'example' => $example,
								'active' => ($selected === $classname),
								'can_be_activated' => $canBeActivated,
								'activation_error' => $activationError,
						);
				}
		}

		usort($modules, function ($a, $b) {
				return strcasecmp($a['label'], $b['label']);
		});

		return $modules;
}

// EN: Build the list of available document models for the module.
function timesheetweekListDocumentModels(array $directories, Translate $langs, array $enabled, $default)
{
		global $db;

		$models = array();

		foreach ($directories as $reldir) {
			// EN: Resolve the directory that stores the document model definitions.
			$dir = dol_buildpath(rtrim($reldir, '/').'/timesheetweek/core/modules/timesheetweek/doc/');
				if (!is_dir($dir)) {
						continue;
				}

				$files = dol_dir_list($dir, 'files', 0, '^[A-Za-z0-9_]+\.modules\.php$');
				foreach ($files as $fileinfo) {
						$file = $fileinfo['name'];
						$classname = preg_replace('/\.modules\.php$/i', '', $file);

						require_once $dir.$file;
						if (!class_exists($classname)) {
								continue;
						}

						$module = new $classname($db);
						if (empty($module->type)) {
								continue;
						}

						$name = !empty($module->name) ? $module->name : $classname;
						$label = $name;
						if (!empty($module->name) && $langs->transnoentitiesnoconv($module->name) !== $module->name) {
								$label = $langs->trans($module->name);
						}

						$description = '';
						if (!empty($module->description)) {
								$description = $module->description;
						}
						if ($description && $langs->transnoentitiesnoconv($description) !== $description) {
								$description = $langs->trans($description);
						}

			$models[] = array(
				'name' => $name,
				'classname' => $classname,
				'label' => $label,
				'description' => $description,
				'is_enabled' => !empty($enabled[$name]),
				'is_default' => ($default === $name),
				'type' => $module->type,
				'scandir' => property_exists($module, 'scandir') ? $module->scandir : '',
			);
				}
		}

		usort($models, function ($a, $b) {
				return strcasecmp($a['label'], $b['label']);
		});

		return $models;
}

// EN: Verify CSRF token when the request changes the configuration.
if (in_array($action, array('setmodule', 'setdoc', 'setdocmodel', 'delmodel', 'setquarterday', 'savereminder', 'testreminder'), true)) {
        if (function_exists('dol_verify_token')) {
                if (empty($token) || dol_verify_token($token) <= 0) {
                        accessforbidden();
                }
        }
}

// EN: Persist the chosen numbering module.
if ($action === 'setmodule' && !empty($value)) {
		$result = dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON', $value, 'chaine', 0, '', $conf->entity);
		if ($result > 0) {
				setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
				setEventMessages($langs->trans('Error'), null, 'errors');
		}
}

// EN: Set the default PDF model while ensuring the model is enabled.
if ($action === 'setdoc' && !empty($value)) {
$res = timesheetweekEnableDocumentModel($value, $docLabel, $scanDir);
		if ($res > 0) {
				$res = dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON_PDF', $value, 'chaine', 0, '', $conf->entity);
		}
		if ($res > 0) {
				setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
				setEventMessages($langs->trans('Error'), null, 'errors');
		}
}

// EN: Activate a PDF model without making it the default.
if ($action === 'setdocmodel' && !empty($value)) {
$res = timesheetweekEnableDocumentModel($value, $docLabel, $scanDir);
		if ($res > 0) {
				setEventMessages($langs->trans('ModelEnabled', $value), null, 'mesgs');
		} else {
				setEventMessages($langs->trans('Error'), null, 'errors');
		}
}

// EN: Disable a PDF model and remove the default flag if needed.
if ($action === 'delmodel' && !empty($value)) {
	$res = timesheetweekDisableDocumentModel($value);
	if ($res > 0) {
		if ($value === getDolGlobalString('TIMESHEETWEEK_ADDON_PDF')) {
			dolibarr_del_const($db, 'TIMESHEETWEEK_ADDON_PDF', $conf->entity);
		}
		setEventMessages($langs->trans('ModelDisabled', $value), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

// EN: Enable or disable the quarter-day selector for daily rate contracts.
if ($action === 'setquarterday') {
        $targetValue = (int) GETPOST('value', 'int');
        if ($targetValue !== 0) {
                $targetValue = 1;
        }
	$res = dolibarr_set_const($db, 'TIMESHEETWEEK_QUARTERDAYFORDAILYCONTRACT', $targetValue, 'chaine', 0, '', $conf->entity);
	if ($res > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

if ($action === 'savereminder') {
$reminderEnabledValue = (int) GETPOST('TIMESHEETWEEK_REMINDER_ENABLED', 'int');
$reminderWeekdayValue = (int) GETPOST('TIMESHEETWEEK_REMINDER_WEEKDAY', 'int');
$reminderHourValue = trim(GETPOST('TIMESHEETWEEK_REMINDER_HOUR', 'alphanohtml'));
$reminderTemplateValue = (int) GETPOST('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 'int');

	$error = 0;

	if ($reminderWeekdayValue < 1 || $reminderWeekdayValue > 7) {
		setEventMessages($langs->trans('TimesheetWeekReminderWeekdayInvalid'), null, 'errors');
		$error++;
	}

	if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $reminderHourValue)) {
		setEventMessages($langs->trans('TimesheetWeekReminderHourInvalid'), null, 'errors');
		$error++;
	}

	if (!$error) {
		$results = array();
		$results[] = dolibarr_set_const($db, 'TIMESHEETWEEK_REMINDER_ENABLED', ($reminderEnabledValue ? 1 : 0), 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'TIMESHEETWEEK_REMINDER_WEEKDAY', $reminderWeekdayValue, 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'TIMESHEETWEEK_REMINDER_HOUR', $reminderHourValue, 'chaine', 0, '', $conf->entity);
		$results[] = dolibarr_set_const($db, 'TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', $reminderTemplateValue, 'chaine', 0, '', $conf->entity);

		$hasError = false;
		foreach ($results as $resultValue) {
			if ($resultValue <= 0) {
				$hasError = true;
				break;
			}
		}

		if ($hasError) {
			setEventMessages($langs->trans('Error'), null, 'errors');
		} else {
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		}
	}
}

	if ($action === 'testreminder') {
		$reminder = new TimesheetweekReminder($db);
		$resultTest = $reminder->sendTest($db, $user); //$resultTest = TimesheetweekReminder::sendTest($db, $user);
		//var_dump($resultTest);
		if ($resultTest == 0) {
			setEventMessages($langs->trans('TimesheetWeekReminderTestSuccess'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('TimesheetWeekReminderTestError'), null, 'errors');
		}
}

// EN: Read the selected options so we can highlight them in the UI.
$selectedNumbering = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_standard');
$defaultPdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
$useQuarterDaySelector = getDolGlobalInt('TIMESHEETWEEK_QUARTERDAYFORDAILYCONTRACT', 0);
$reminderEnabled = getDolGlobalInt('TIMESHEETWEEK_REMINDER_ENABLED', 0);
$reminderWeekday = getDolGlobalInt('TIMESHEETWEEK_REMINDER_WEEKDAY', 1);
$reminderHour = getDolGlobalString('TIMESHEETWEEK_REMINDER_HOUR', '18:00');
$reminderTemplateId = getDolGlobalInt('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 0);
$directories = array_merge(array('/'), (array) $conf->modules_parts['models']);

// EN: Prepare a lightweight object to test numbering module activation.
$sampleTimesheet = new TimesheetWeek($db);

// EN: Fetch the enabled document models from the database.
$enabledModels = array();
$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX."document_model WHERE type='timesheetweek' AND entity IN (0, ".((int) $conf->entity).')';
$resql = $db->query($sql);
if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
				$enabledModels[$obj->nom] = 1;
		}
		$db->free($resql);
}

// EN: Build the metadata arrays used by the HTML rendering below.
$numberingModules = timesheetweekListNumberingModules($directories, $langs, $sampleTimesheet, $selectedNumbering);
$documentModels = timesheetweekListDocumentModels($directories, $langs, $enabledModels, $defaultPdf);
$pageToken = function_exists('newToken') ? newToken() : '';
$form = new Form($db);

$emailTemplates = array();
$emailTemplateClass = '';
if (class_exists('CEmailTemplates')) {
        $emailTemplateClass = 'CEmailTemplates';
} elseif (class_exists('EmailTemplates')) {
        $emailTemplateClass = 'EmailTemplates';
}

if (!empty($emailTemplateClass)) {
        $emailTemplateObject = new $emailTemplateClass($db);
        if (method_exists($emailTemplateObject, 'fetchAll')) {
                $filters = array('entity' => $conf->entity);
                $templatesResult = $emailTemplateObject->fetchAll('', '', 0, 0, $filters);
                if (is_array($templatesResult)) {
                        $emailTemplates = $templatesResult;
                }
        }
}

$title = $langs->trans('ModuleSetup', 'Timesheetweek');
$helpurl = '';

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-timesheetweek page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = timesheetweekAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'timesheetweek@timesheetweek');

print '<div class="opacitymedium">'.$langs->trans('TimesheetWeekSetupPage').'</div>';
print '<br>';

print '<div class="fichecenter">';

// EN: Display the numbering modules with switch-based activation instead of radios.
print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekNumberingHelp').'</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th>'.$langs->trans('Example').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '</tr>';

if (count($numberingModules) === 0) {
		print '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('TimesheetWeekNumberingEmpty').'</td></tr>';
}

foreach ($numberingModules as $moduleInfo) {
		$desc = $moduleInfo['description'];
		$descIsPlainText = ($desc === strip_tags($desc));
		$descHtml = $descIsPlainText ? dol_escape_htmltag($desc) : $desc;

		print '<tr class="oddeven">';
		print '<td class="nowraponall">';
		print dol_escape_htmltag($moduleInfo['label']);
		if ($moduleInfo['classname'] !== $moduleInfo['label']) {
				print ' <span class="opacitymedium">('.dol_escape_htmltag($moduleInfo['classname']).')</span>';
		}
		if (!$moduleInfo['can_be_activated'] && !empty($moduleInfo['activation_error'])) {
				print '<br><span class="error">'.dol_escape_htmltag($moduleInfo['activation_error']).'</span>';
		}
		print '</td>';

		print '<td class="small">'.(!empty($descHtml) ? $descHtml : '&nbsp;').'</td>';
		print '<td class="small">'.(!empty($moduleInfo['example']) ? dol_escape_htmltag($moduleInfo['example']) : '&nbsp;').'</td>';

		// EN: Render the activation toggle that selects the numbering model with CSRF protection.
		print '<td class="center">';
		if ($moduleInfo['active']) {
				print img_picto($langs->trans('Enabled'), 'switch_on');
		} elseif ($moduleInfo['can_be_activated']) {
				$url = $_SERVER['PHP_SELF'].'?action=setmodule&value='.urlencode($moduleInfo['classname']).'&token='.$pageToken;
				print '<a class="reposition" href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('TimesheetWeekNumberingActivate'), 'switch_off').'</a>';
		} else {
				print img_picto($langs->trans('Disabled'), 'switch_off');
		}
		print '</td>';
		print '</tr>';
}

print '</table>';
print '</div>';

print '</div>';

print '<br>';

// EN: Display the helper switches dedicated to the daily-rate contract workflows.
print load_fiche_titre($langs->trans('TimesheetWeekDailyRateOptions'), '', 'setup');
print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekDailyRateOptionsHelp').'</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '</tr>';

// EN: Render the switch dedicated to the quarter-day declaration helper.
print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekQuarterDayForDailyContract').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekQuarterDayForDailyContractHelp').'</td>';
print '<td class="center">';
	if (!empty($useQuarterDaySelector)) {
	$url = $_SERVER['PHP_SELF'].'?action=setquarterday&value=0&token='.$pageToken;
	print '<a class="reposition" href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disable'), 'switch_on').'</a>';
	} else {
		$url = $_SERVER['PHP_SELF'].'?action=setquarterday&value=1&token='.$pageToken;
	print '<a class="reposition" href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Activate'), 'switch_off').'</a>';
	}
	print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<br>';

print load_fiche_titre($langs->trans('TimesheetWeekReminderSectionTitle'), '', 'email');
print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekReminderSectionHelp').'</div>';

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$pageToken.'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Value').'</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekReminderEnabled').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekReminderEnabledHelp').'</td>';
print '<td class="center">';
print '<input type="checkbox" name="TIMESHEETWEEK_REMINDER_ENABLED" value="1"'.(!empty($reminderEnabled) ? ' checked' : '').'>';
print '</td>';
print '</tr>';

$weekdayOptions = array(
1 => $langs->trans('Monday'),
2 => $langs->trans('Tuesday'),
3 => $langs->trans('Wednesday'),
4 => $langs->trans('Thursday'),
5 => $langs->trans('Friday'),
6 => $langs->trans('Saturday'),
7 => $langs->trans('Sunday'),
);

print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekReminderWeekday').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekReminderWeekdayHelp').'</td>';
print '<td class="center">'.$form->selectarray('TIMESHEETWEEK_REMINDER_WEEKDAY', $weekdayOptions, $reminderWeekday, 0, 0, 0, '', 0, 0, 0, '', '', 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekReminderHour').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekReminderHourHelp').'</td>';
print '<td class="center"><input type="text" name="TIMESHEETWEEK_REMINDER_HOUR" value="'.dol_escape_htmltag($reminderHour).'" size="6" maxlength="5"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekReminderEmailTemplate').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekReminderEmailTemplateHelp').'</td>';
print '<td class="center">'.$form->selectarray('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', $templateOptions, $reminderTemplateId, 0, 0, 0, '', 0, 0, 0, '', '', 1).'</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<button type="submit" class="butAction" name="action" value="savereminder">'.($langs->trans("Save")!='Save'?$langs->trans("Save"):'Enregistrer').'</button>';
print '&nbsp;';
print '<button type="submit" class="butAction" name="action" value="testreminder">'.($langs->trans("TimesheetWeekReminderSendTest")!='Send a test e-mail'?$langs->trans("TimesheetWeekReminderSendTest"):'Envoyer un mail de test').'</button>';
print '</div>';
print '</form>';

print '<br>';

print load_fiche_titre($langs->trans('TimesheetWeekPDFModels'), '', 'pdf');
print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekPDFModelsHelp').'</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Type').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th class="center">'.$langs->trans('Default').'</th>';
print '</tr>';

if (count($documentModels) === 0) {
		print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('TimesheetWeekPDFModelsEmpty').'</td></tr>';
}

foreach ($documentModels as $modelInfo) {
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag($modelInfo['label']).'</td>';
		print '<td class="small">'.(!empty($modelInfo['description']) ? dol_escape_htmltag($modelInfo['description']) : '&nbsp;').'</td>';
		print '<td class="center">'.dol_escape_htmltag($modelInfo['type']).'</td>';

		print '<td class="center">';
		if ($modelInfo['is_enabled']) {
				$url = $_SERVER['PHP_SELF'].'?action=delmodel&value='.urlencode($modelInfo['name']).'&token='.$pageToken;
				print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disable'), 'switch_on').'</a>';
		} else {
				$url = $_SERVER['PHP_SELF'].'?action=setdocmodel&value='.urlencode($modelInfo['name']).'&token='.$pageToken.'&scan_dir='.urlencode($modelInfo['scandir']).'&label='.urlencode($modelInfo['label']);
				print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Activate'), 'switch_off').'</a>';
		}
		print '</td>';

		print '<td class="center">';
		if ($modelInfo['is_default']) {
				print img_picto($langs->trans('Enabled'), 'on');
		} elseif ($modelInfo['is_enabled']) {
				$url = $_SERVER['PHP_SELF'].'?action=setdoc&value='.urlencode($modelInfo['name']).'&token='.$pageToken.'&scan_dir='.urlencode($modelInfo['scandir']).'&label='.urlencode($modelInfo['label']);
				print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('SetDefault'), 'switch_on').'</a>';
		} else {
				print '&nbsp;';
		}
		print '</td>';
		print '</tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
