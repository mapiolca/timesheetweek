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
// FR: Charge l'environnement Dolibarr en testant les chemins possibles.
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
// EN: Load document helper functions required for model toggles.
// FR: Charge les fonctions d'aide aux documents nécessaires aux commutateurs de modèles.
require_once DOL_DOCUMENT_ROOT.'/core/lib/doc.lib.php';
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

// EN: Load translation files required for the configuration page.
// FR: Charge les fichiers de traduction nécessaires à la page de configuration.
$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

// EN: Only administrators can access the setup.
// FR: Seuls les administrateurs peuvent accéder à la configuration.
if (empty($user->admin)) {
		accessforbidden();
}

// EN: Read HTTP parameters once so we can re-use them further down.
// FR: Lit les paramètres HTTP une seule fois pour les réutiliser ensuite.
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alphanohtml');
$token = GETPOST('token', 'alphanohtml');
// EN: Capture additional parameters used to reproduce Dolibarr's document model toggles.
// FR: Capture les paramètres additionnels utilisés par les bascules de modèles de document Dolibarr.
$docLabel = GETPOST('label', 'alphanohtml');
$scanDir = GETPOST('scan_dir', 'alpha');
$reminderAction = GETPOST('reminder_action', 'aZ09');

if (is_readable(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplates.class.php')) {
        dol_include_once('/core/class/cemailtemplates.class.php');
} elseif (is_readable(DOL_DOCUMENT_ROOT.'/core/class/emailtemplates.class.php')) {
        dol_include_once('/core/class/emailtemplates.class.php');
}

// EN: Helper to enable a PDF model in the database.
// FR: Aide pour activer un modèle PDF dans la base.
function timesheetweekEnableDocumentModel($model, $label = '', $scandir = '')
{
	global $db, $conf;

	if (empty($model)) {
		return 0;
	}

	// EN: Check if the model already exists for the current entity to avoid duplicates.
	// FR: Vérifie si le modèle existe déjà pour l'entité courante afin d'éviter les doublons.
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
		// FR: Met à jour le libellé fourni pour garder l'interface cohérente.
		if ($label !== '') {
			$fields[] = "libelle='".$db->escape($label)."'";
		}

		// EN: Refresh directory hint when provided to ease future scans.
		// FR: Met à jour le chemin fourni pour faciliter les scans ultérieurs.
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
// FR: Aide pour désactiver un modèle PDF dans la base.
function timesheetweekDisableDocumentModel($model)
{
	if (empty($model)) {
		return 0;
	}

	$result = delDocumentModel($model, 'timesheetweek');
	return ($result > 0) ? 1 : ($result === 0 ? 0 : -1);
}

// EN: Build the list of numbering modules available for the module.
// FR: Construit la liste des modules de numérotation disponibles pour le module.
function timesheetweekListNumberingModules(array $directories, Translate $langs, TimesheetWeek $sample, $selected)
{
		global $db;

		$modules = array();

		foreach ($directories as $reldir) {
			// EN: Resolve the directory that holds the numbering module classes.
			// FR: Résout le répertoire qui contient les classes de numérotation.
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
// FR: Construit la liste des modèles de documents disponibles pour le module.
function timesheetweekListDocumentModels(array $directories, Translate $langs, array $enabled, $default)
{
		global $db;

		$models = array();

		foreach ($directories as $reldir) {
			// EN: Resolve the directory that stores the document model definitions.
			// FR: Résout le répertoire qui contient les définitions de modèles de document.
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
// FR: Vérifie le jeton CSRF lorsque la requête modifie la configuration.
if (in_array($action, array('setmodule', 'setdoc', 'setdocmodel', 'delmodel', 'setquarterday'), true)) {
        if (function_exists('dol_verify_token')) {
                if (empty($token) || dol_verify_token($token) <= 0) {
                        accessforbidden();
                }
        }
}

if ($reminderAction === 'savereminder') {
	if (function_exists('dol_verify_token')) {
		if (empty($token) || dol_verify_token($token) <= 0) {
			accessforbidden();
		}
	}
}

// EN: Persist the chosen numbering module.
// FR: Enregistre le module de numérotation choisi.
if ($action === 'setmodule' && !empty($value)) {
		$result = dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON', $value, 'chaine', 0, '', $conf->entity);
		if ($result > 0) {
				setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
				setEventMessages($langs->trans('Error'), null, 'errors');
		}
}

// EN: Set the default PDF model while ensuring the model is enabled.
// FR: Définit le modèle PDF par défaut tout en s'assurant qu'il est activé.
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
// FR: Active un modèle PDF sans le définir comme défaut.
if ($action === 'setdocmodel' && !empty($value)) {
$res = timesheetweekEnableDocumentModel($value, $docLabel, $scanDir);
		if ($res > 0) {
				setEventMessages($langs->trans('ModelEnabled', $value), null, 'mesgs');
		} else {
				setEventMessages($langs->trans('Error'), null, 'errors');
		}
}

// EN: Disable a PDF model and remove the default flag if needed.
// FR: Désactive un modèle PDF et supprime le statut par défaut si nécessaire.
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
// FR: Active ou désactive le sélecteur quart de jour pour les contrats au forfait jour.
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

if ($reminderAction === 'savereminder') {
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

// EN: Read the selected options so we can highlight them in the UI.
// FR: Lit les options sélectionnées pour les mettre en avant dans l'interface.
$selectedNumbering = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_standard');
$defaultPdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
$useQuarterDaySelector = getDolGlobalInt('TIMESHEETWEEK_QUARTERDAYFORDAILYCONTRACT', 0);
$reminderEnabled = getDolGlobalInt('TIMESHEETWEEK_REMINDER_ENABLED', 0);
$reminderWeekday = getDolGlobalInt('TIMESHEETWEEK_REMINDER_WEEKDAY', 1);
$reminderHour = getDolGlobalString('TIMESHEETWEEK_REMINDER_HOUR', '18:00');
$reminderTemplateId = getDolGlobalInt('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 0);
$directories = array_merge(array('/'), (array) $conf->modules_parts['models']);

// EN: Prepare a lightweight object to test numbering module activation.
// FR: Prépare un objet léger pour tester l'activation des modules de numérotation.
$sampleTimesheet = new TimesheetWeek($db);

// EN: Fetch the enabled document models from the database.
// FR: Récupère les modèles de documents activés depuis la base.
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
// FR: Construit les tableaux de métadonnées utilisés par l'affichage HTML ci-dessous.
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
// FR: Affiche les modules de numérotation avec des commutateurs plutôt qu'avec des radios.
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
		// FR: Affiche le commutateur d’activation qui sélectionne le modèle de numérotation avec protection CSRF.
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
// FR: Affiche les commutateurs dédiés aux workflows des contrats au forfait jour.
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
// FR: Affiche le commutateur dédié à l'aide de déclaration des quarts de jour.
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
print '<input type="hidden" name="reminder_action" value="savereminder">';

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

$templateOptions = array(0 => $langs->trans('None'));
foreach ($emailTemplates as $templateItem) {
	$templateId = 0;
	if (!empty($templateItem->id)) {
		$templateId = (int) $templateItem->id;
	} elseif (!empty($templateItem->rowid)) {
		$templateId = (int) $templateItem->rowid;
	}

	if (empty($templateId)) {
		continue;
	}

	$templateLabel = '';
	if (!empty($templateItem->label)) {
		$templateLabel = $templateItem->label;
	} elseif (!empty($templateItem->ref)) {
		$templateLabel = $templateItem->ref;
	} elseif (!empty($templateItem->topic)) {
		$templateLabel = $templateItem->topic;
	} else {
		$templateLabel = '#'.$templateId;
	}

	$templateOptions[$templateId] = $templateLabel;
}

print '<tr class="oddeven">';
print '<td class="nowraponall">'.$langs->trans('TimesheetWeekReminderEmailTemplate').'</td>';
print '<td class="small">'.$langs->trans('TimesheetWeekReminderEmailTemplateHelp').'</td>';
print '<td class="center">'.$form->selectarray('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', $templateOptions, $reminderTemplateId, 0, 0, 0, '', 0, 0, 0, '', '', 1).'</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div>';
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
