<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

if (is_readable(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplates.class.php')) {
	dol_include_once('/core/class/cemailtemplates.class.php');
} elseif (is_readable(DOL_DOCUMENT_ROOT.'/core/class/emailtemplates.class.php')) {
	dol_include_once('/core/class/emailtemplates.class.php');
}

$templateClass = '';
if (class_exists('CEmailTemplates')) {
	$templateClass = 'CEmailTemplates';
} elseif (class_exists('EmailTemplates')) {
	$templateClass = 'EmailTemplates';
}

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
$notification = GETPOST('notification', 'alphanohtml');
$templateId = GETPOSTINT('template_id');

// EN: Helper to enable a PDF model in the database.
// FR: Aide pour activer un modèle PDF dans la base.
function timesheetweekEnableDocumentModel($model)
{
        global $db, $conf;

        if (empty($model)) {
                return 0;
        }

        $sql = 'INSERT INTO '.MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES ('".$db->escape($model)."', 'timesheetweek', ".((int) $conf->entity).')';
        $resql = $db->query($sql);
        if ($resql) {
                return 1;
        }

        // EN: Ignore duplicate entries silently because the model is already stored.
        // FR: Ignore les doublons car le modèle est déjà enregistré.
        if ($db->lasterrno() && strpos($db->lasterror(), 'Duplicate') !== false) {
                return 1;
        }

        return -1;
}

// EN: Helper to disable a PDF model from the database.
// FR: Aide pour désactiver un modèle PDF dans la base.
function timesheetweekDisableDocumentModel($model)
{
        global $db, $conf;

        if (empty($model)) {
                return 0;
        }

        $sql = 'DELETE FROM '.MAIN_DB_PREFIX."document_model WHERE nom='".$db->escape($model)."' AND type='timesheetweek' AND entity IN (0, ".((int) $conf->entity).')';
        $resql = $db->query($sql);
        if ($resql) {
                return ($db->affected_rows($resql) >= 0) ? 1 : 0;
        }

        return -1;
}

// EN: Build the list of numbering modules available for the module.
// FR: Construit la liste des modules de numérotation disponibles pour le module.
function timesheetweekListNumberingModules(array $directories, Translate $langs, TimesheetWeek $sample, $selected)
{
        global $db;

        $modules = array();

        foreach ($directories as $reldir) {
                $dir = dol_buildpath($reldir.'core/modules/timesheetweek/');
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

// EN: Build the list of available PDF models for the module.
// FR: Construit la liste des modèles PDF disponibles pour le module.
function timesheetweekListDocumentModels(array $directories, Translate $langs, array $enabled, $default)
{
        global $db;

        $models = array();

        foreach ($directories as $reldir) {
                $dir = dol_buildpath($reldir.'core/modules/timesheetweek/doc/');
                if (!is_dir($dir)) {
                        continue;
                }

                $files = dol_dir_list($dir, 'files', 0, '^[a-z0-9_]+\.php$');
                foreach ($files as $fileinfo) {
                        $file = $fileinfo['name'];
                        $classname = preg_replace('/\.php$/', '', $file);

                        require_once $dir.$file;
                        if (!class_exists($classname)) {
                                continue;
                        }

                        $module = new $classname($db);
                        if (empty($module->type) || $module->type !== 'pdf') {
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
                        );
                }
        }

        usort($models, function ($a, $b) {
                return strcasecmp($a['label'], $b['label']);
        });

        return $models;
}

// EN: Map notification action codes to configuration constants.
// FR: Lie les codes de notification aux constantes de configuration.
function timesheetweekNotificationTemplateConst($code)
{
	$map = array(
		'TIMESHEETWEEK_SUBMITTED' => 'TIMESHEETWEEK_TEMPLATE_SUBMITTED',
		'TIMESHEETWEEK_APPROVED' => 'TIMESHEETWEEK_TEMPLATE_APPROVED',
		'TIMESHEETWEEK_REFUSED' => 'TIMESHEETWEEK_TEMPLATE_REFUSED',
	);

	return isset($map[$code]) ? $map[$code] : '';
}

// EN: Ensure the default email template exists for a notification trigger.
// FR: Garantit l'existence du modèle de courriel par défaut pour un déclencheur donné.
function timesheetweekEnsureDefaultNotificationTemplate($templateClass, array $templateKeys, $code, User $user, Translate $langs, Conf $conf)
{
	global $db;

	if (empty($templateClass) || empty($templateKeys['subject']) || empty($templateKeys['body'])) {
		return 0;
	}

	$template = new $templateClass($db);
	if (method_exists($template, 'fetchByTrigger')) {
		$res = $template->fetchByTrigger($code, $user, $conf->entity);
		if ($res > 0) {
			return property_exists($template, 'id') ? (int) $template->id : (property_exists($template, 'rowid') ? (int) $template->rowid : 0);
		}
	}

	$template = new $templateClass($db);
	if (property_exists($template, 'entity')) {
		$template->entity = (int) $conf->entity;
	}
	if (property_exists($template, 'module')) {
		$template->module = 'timesheetweek';
	}
	if (property_exists($template, 'type_template') && empty($template->type_template)) {
		$template->type_template = 'timesheetweek';
	}
	if (property_exists($template, 'code')) {
		$template->code = $code;
	}
	$template->label = $langs->transnoentities($templateKeys['label']);
	if (property_exists($template, 'topic')) {
		$template->topic = $langs->transnoentities($templateKeys['subject']);
	}
	if (property_exists($template, 'subject')) {
		$template->subject = $langs->transnoentities($templateKeys['subject']);
	}
	if (property_exists($template, 'content')) {
		$template->content = $langs->transnoentities($templateKeys['body']);
	}
	if (property_exists($template, 'lang')) {
		$template->lang = '';
	}
	if (property_exists($template, 'private')) {
		$template->private = 0;
	}
	if (property_exists($template, 'fk_user')) {
		$template->fk_user = (int) $user->id;
	}
	if (property_exists($template, 'active')) {
		$template->active = 1;
	}
	if (property_exists($template, 'enabled')) {
		$template->enabled = 1;
	}
	if (property_exists($template, 'email_from')) {
		$template->email_from = '';
	}
	if (property_exists($template, 'email_to')) {
		$template->email_to = '';
	}
	if (property_exists($template, 'email_cc')) {
		$template->email_cc = '';
	}
	if (property_exists($template, 'email_bcc')) {
		$template->email_bcc = '';
	}
	if (property_exists($template, 'joinfiles')) {
		$template->joinfiles = 0;
	}
	if (property_exists($template, 'position')) {
		$template->position = 0;
	}

	if (method_exists($template, 'create')) {
		$res = $template->create($user);
		if ($res > 0) {
			return property_exists($template, 'id') ? (int) $template->id : (property_exists($template, 'rowid') ? (int) $template->rowid : 0);
		}
	}

	return 0;
}

// EN: Retrieve the list of available email templates for the selector.
// FR: Récupère la liste des modèles de courriel disponibles pour le sélecteur.
function timesheetweekListEmailTemplates(Conf $conf)
{
	global $db;

	$tables = array('c_email_templates', 'email_templates');
	$tableName = '';
	foreach ($tables as $candidate) {
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$candidate.' LIMIT 1';
		$resql = $db->query($sql);
		if ($resql) {
			$db->free($resql);
			$tableName = MAIN_DB_PREFIX.$candidate;
			break;
		}
	}

	if (empty($tableName)) {
		return array();
	}

	$sql = 'SELECT rowid, label, lang, code FROM '.$tableName.' WHERE (entity IN (0, '.((int) $conf->entity).') OR entity IS NULL)';
	$sql .= ' ORDER BY label ASC';
	$resql = $db->query($sql);
	$list = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$list[] = array(
				'id' => (int) $obj->rowid,
				'label' => $obj->label,
				'lang' => $obj->lang,
				'code' => $obj->code,
			);
		}
		$db->free($resql);
	}

	return $list;
}

// EN: Verify CSRF token when the request changes the configuration.
// FR: Vérifie le jeton CSRF lorsque la requête modifie la configuration.
if (in_array($action, array('setmodule', 'setdoc', 'setdocmodel', 'delmodel', 'setemailtemplate'), true)) {
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
        $res = timesheetweekEnableDocumentModel($value);
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
        $res = timesheetweekEnableDocumentModel($value);
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

// EN: Store the email template selection for a notification type.
// FR: Enregistre le modèle de courriel choisi pour un type de notification.
if ($action === 'setemailtemplate' && !empty($notification)) {
	$constName = timesheetweekNotificationTemplateConst($notification);
	if (empty($constName)) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		if ($templateId > 0) {
			$result = dolibarr_set_const($db, $constName, $templateId, 'chaine', 0, '', $conf->entity);
		} else {
			$result = dolibarr_del_const($db, $constName, $conf->entity);
			$result = ($result < 0) ? -1 : 1;
		}

		if ($result > 0) {
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

// EN: Read the selected options so we can highlight them in the UI.
// FR: Lit les options sélectionnées pour les mettre en avant dans l'interface.
$selectedNumbering = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_standard');
$defaultPdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard');
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
// EN: Describe each notification to expose its label, constant and template metadata.
// FR: Décrit chaque notification pour exposer son libellé, sa constante et ses métadonnées de modèle.
$notificationDefinitions = array(
	'TIMESHEETWEEK_SUBMITTED' => array(
		'const' => 'TIMESHEETWEEK_TEMPLATE_SUBMITTED',
		'label' => 'TimesheetWeekNotificationSubmittedLabel',
		'default_label' => 'TimesheetWeekTemplateSubmitLabel',
		'template_keys' => array(
			'label' => 'TimesheetWeekTemplateSubmitLabel',
			'subject' => 'TimesheetWeekTemplateSubmitSubject',
			'body' => 'TimesheetWeekTemplateSubmitBody',
		),
	),
	'TIMESHEETWEEK_APPROVED' => array(
		'const' => 'TIMESHEETWEEK_TEMPLATE_APPROVED',
		'label' => 'TimesheetWeekNotificationApprovedLabel',
		'default_label' => 'TimesheetWeekTemplateApproveLabel',
		'template_keys' => array(
			'label' => 'TimesheetWeekTemplateApproveLabel',
			'subject' => 'TimesheetWeekTemplateApproveSubject',
			'body' => 'TimesheetWeekTemplateApproveBody',
		),
	),
	'TIMESHEETWEEK_REFUSED' => array(
		'const' => 'TIMESHEETWEEK_TEMPLATE_REFUSED',
		'label' => 'TimesheetWeekNotificationRefusedLabel',
		'default_label' => 'TimesheetWeekTemplateRefuseLabel',
		'template_keys' => array(
			'label' => 'TimesheetWeekTemplateRefuseLabel',
			'subject' => 'TimesheetWeekTemplateRefuseSubject',
			'body' => 'TimesheetWeekTemplateRefuseBody',
		),
	),
);

// EN: Create default email templates when they do not already exist.
// FR: Crée les modèles de courriel par défaut lorsqu'ils n'existent pas encore.
foreach ($notificationDefinitions as $code => &$definition) {
	$definition['default_id'] = timesheetweekEnsureDefaultNotificationTemplate($templateClass, $definition['template_keys'], $code, $user, $langs, $conf);
}
unset($definition);

$numberingModules = timesheetweekListNumberingModules($directories, $langs, $sampleTimesheet, $selectedNumbering);
$documentModels = timesheetweekListDocumentModels($directories, $langs, $enabledModels, $defaultPdf);
$emailTemplates = timesheetweekListEmailTemplates($conf);
$pageToken = function_exists('newToken') ? newToken() : '';

$form = new Form($db);

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
                $url = $_SERVER['PHP_SELF'].'?action=setdocmodel&value='.urlencode($modelInfo['name']).'&token='.$pageToken;
                print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Activate'), 'switch_off').'</a>';
        }
        print '</td>';

        print '<td class="center">';
        if ($modelInfo['is_default']) {
                print img_picto($langs->trans('Enabled'), 'on');
        } elseif ($modelInfo['is_enabled']) {
                $url = $_SERVER['PHP_SELF'].'?action=setdoc&value='.urlencode($modelInfo['name']).'&token='.$pageToken;
                print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('SetDefault'), 'switch_on').'</a>';
        } else {
                print '&nbsp;';
        }
        print '</td>';
        print '</tr>';
}

print '</table>';
print '</div>';

// EN: Render the email template selectors for each automatic notification.
// FR: Affiche les sélecteurs de modèles de courriel pour chaque notification automatique.
print '<br>';
print load_fiche_titre($langs->trans('TimesheetWeekNotificationTemplates'), '', 'email');
print '<div class="underbanner opacitymedium">'.$langs->trans('TimesheetWeekNotificationTemplatesHelp').'</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Type').'</th>';
print '<th>'.$langs->trans('MailTemplate').'</th>';
print '</tr>';

if (count($notificationDefinitions) === 0) {
	print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans('TimesheetWeekNotificationTemplatesEmpty').'</td></tr>';
} else {
	foreach ($notificationDefinitions as $code => $definition) {
		$selectedTemplate = 0;
		if (function_exists('getDolGlobalInt')) {
			$selectedTemplate = (int) getDolGlobalInt($definition['const'], 0);
		} elseif (!empty($conf->global->{$definition['const']})) {
			$selectedTemplate = (int) $conf->global->{$definition['const']};
		}

		$options = array();
		$options[0] = $langs->trans('TimesheetWeekNotificationTemplateDefaultOption', $langs->trans($definition['default_label']));
		foreach ($emailTemplates as $tpl) {
			$label = $tpl['label'];
			if (!empty($tpl['lang'])) {
				$label .= ' ('.$tpl['lang'].')';
			}
			if (!empty($tpl['code'])) {
				$label .= ' ['.$tpl['code'].']';
			}
			$options[$tpl['id']] = $label;
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag($langs->trans($definition['label'])).'</td>';
		print '<td>';
		print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block">';
		print '<input type="hidden" name="token" value="'.dol_escape_htmltag($pageToken).'">';
		print '<input type="hidden" name="action" value="setemailtemplate">';
		print '<input type="hidden" name="notification" value="'.dol_escape_htmltag($code).'">';
		print $form->selectarray('template_id', $options, $selectedTemplate, 0);
		print ' <button type="submit" class="button small">'.$langs->trans('Save').'</button>';
		print '</form>';
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';


print dol_get_fiche_end();

llxFooter();
$db->close();
