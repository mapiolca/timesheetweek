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
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

if (empty($user->admin)) {
        accessforbidden();
}

$action = GETPOST('action', 'aZ09');
// FR: On accepte les caractères natifs des classes Dolibarr (underscore, chiffres...).
// EN: Allow native Dolibarr class names with underscores and digits.
$value = GETPOST('value', 'alphanohtml');
$receivedToken = GETPOST('token', 'alphanohtml');

// EN: Secure the state-changing actions with the Dolibarr CSRF token when available.
// FR: Sécurise les actions modifiant l'état avec le jeton CSRF de Dolibarr lorsque disponible.
if (in_array($action, array('setmodule', 'setdoc', 'setdocmodel', 'delmodel'), true)) {
        if (function_exists('dol_verify_token')) {
                if (empty($receivedToken) || dol_verify_token($receivedToken) <= 0) {
                        accessforbidden();
                }
        }
}

// EN: Helper to collect metadata for numbering modules so we can render a native-looking table.
// FR: Collecte des métadonnées sur les modules de numérotation pour afficher un tableau au format natif.
function timesheetweek_collect_numbering_modules(array $directories, Translate $langs, TimesheetWeek $sampleObject, $selected)
{
        global $db;

        $modules = array();

        foreach ($directories as $reldir) {
                $dir = dol_buildpath($reldir.'core/modules/timesheetweek/');
                if (!is_dir($dir)) {
                        continue;
                }

                $filelist = dol_dir_list($dir, 'files', 0, '^mod_.*\.php$');
                foreach ($filelist as $fileinfo) {
                        $file = $fileinfo['name'];
                        $classname = preg_replace('/\.php$/', '', $file);

                        require_once $dir.$file;
                        if (!class_exists($classname)) {
                                continue;
                        }

                        try {
                                $module = new $classname($db);
                        } catch (Throwable $exception) {
                                continue;
                        }

                        $label = !empty($module->name) ? $module->name : $classname;
                        if ($label && $langs->transnoentitiesnoconv($label) !== $label) {
                                $label = $langs->trans($label);
                        }

                        $description = '';
                        if (method_exists($module, 'info')) {
                                try {
                                        $description = $module->info($langs);
                                } catch (Throwable $exception) {
                                        $description = '';
                                }
                        } elseif (!empty($module->description)) {
                                $description = $module->description;
                        } elseif (!empty($module->desc)) {
                                $description = $module->desc;
                        }

                        $example = '';
                        if (method_exists($module, 'getExample')) {
                                try {
                                        $example = $module->getExample();
                                } catch (Throwable $exception) {
                                        $example = '';
                                }
                        }

                        $canBeActivated = true;
                        $activationError = '';
                        if (method_exists($module, 'canBeActivated')) {
                                try {
                                        $canBeActivated = (bool) $module->canBeActivated($sampleObject);
                                        if (!$canBeActivated && !empty($module->error)) {
                                                $activationError = $module->error;
                                        }
                                } catch (Throwable $exception) {
                                        $canBeActivated = false;
                                        $activationError = $exception->getMessage();
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

// EN: Helper to collect PDF model metadata while respecting Dolibarr document conventions.
// FR: Collecte les métadonnées des modèles PDF en respectant les conventions Dolibarr.
function timesheetweek_collect_document_models(array $directories, Translate $langs, array $enabledModels, $defaultModel)
{
        global $db;

        $models = array();

        foreach ($directories as $reldir) {
                $dir = dol_buildpath($reldir.'core/modules/timesheetweek/doc/');
                if (!is_dir($dir)) {
                        continue;
                }

                $filelist = dol_dir_list($dir, 'files', 0, '^[a-z0-9_]+\.php$');
                foreach ($filelist as $fileinfo) {
                        $file = $fileinfo['name'];
                        $classname = preg_replace('/\.php$/', '', $file);

                        require_once $dir.$file;
                        if (!class_exists($classname)) {
                                continue;
                        }

                        try {
                                $module = new $classname($db);
                        } catch (Throwable $exception) {
                                continue;
                        }

                        if (!property_exists($module, 'type') || $module->type !== 'pdf') {
                                continue;
                        }

                        $name = !empty($module->name) ? $module->name : $classname;
                        $description = '';
                        if (!empty($module->description)) {
                                $description = $module->description;
                        } elseif (!empty($module->desc)) {
                                $description = $module->desc;
                        }
                        if ($description && $langs->transnoentitiesnoconv($description) !== $description) {
                                $description = $langs->trans($description);
                        }

                        $models[] = array(
                                'name' => $name,
                                'classname' => $classname,
                                'label' => !empty($module->name) && $langs->transnoentitiesnoconv($module->name) !== $module->name ? $langs->trans($module->name) : $name,
                                'description' => $description,
                                'type' => $module->type,
                                'is_enabled' => !empty($enabledModels[$name]),
                                'is_default' => ($defaultModel === $name),
                        );
                }
        }

        usort($models, function ($a, $b) {
                return strcasecmp($a['label'], $b['label']);
        });

        return $models;
}

if (!function_exists('timesheetweek_enable_document_model')) {
        /**
         * Enable a document model for TimesheetWeek.
         *
         * @param string $model
         * @return int
         */
        function timesheetweek_enable_document_model($model)
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

                // Ignore duplicate errors silently (model already enabled)
                if ($db->lasterrno() && strpos($db->lasterror(), 'Duplicate') !== false) {
                        return 1;
                }

                return -1;
        }
}

if (!function_exists('timesheetweek_disable_document_model')) {
        /**
         * Disable a document model for TimesheetWeek.
         *
         * @param string $model
         * @return int
         */
        function timesheetweek_disable_document_model($model)
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
}

if ($action === 'setmodule' && !empty($value)) {
        $result = dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON', $value, 'chaine', 0, '', $conf->entity);
        if ($result > 0) {
                setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
        } else {
                setEventMessages($langs->trans('Error'), null, 'errors');
        }
}

if ($action === 'setdoc' && !empty($value)) {
        $res = timesheetweek_enable_document_model($value);
        if ($res > 0) {
                $res = dolibarr_set_const($db, 'TIMESHEETWEEK_ADDON_PDF', $value, 'chaine', 0, '', $conf->entity);
        }
        if ($res > 0) {
                setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
        } else {
                setEventMessages($langs->trans('Error'), null, 'errors');
        }
}

if ($action === 'setdocmodel' && !empty($value)) {
        $res = timesheetweek_enable_document_model($value);
        if ($res > 0) {
                setEventMessages($langs->trans('ModelEnabled', $value), null, 'mesgs');
        } else {
                setEventMessages($langs->trans('Error'), null, 'errors');
        }
}

if ($action === 'delmodel' && !empty($value)) {
        $res = timesheetweek_disable_document_model($value);
        if ($res > 0) {
                if ($value === getDolGlobalString('TIMESHEETWEEK_ADDON_PDF')) {
                        dolibarr_del_const($db, 'TIMESHEETWEEK_ADDON_PDF', $conf->entity);
                }
                setEventMessages($langs->trans('ModelDisabled', $value), null, 'mesgs');
        } else {
                setEventMessages($langs->trans('Error'), null, 'errors');
        }
}

$selected = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_standard');
$defaultpdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard');
$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

// EN: Instantiate a lightweight object to validate numbering modules.
// FR: Instancie un objet léger pour valider les modules de numérotation.
$sampleTimesheet = new TimesheetWeek($db);

// EN: Retrieve active document models from the database.
// FR: Récupère les modèles de documents actifs depuis la base de données.
$enabledModels = array();
$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX."document_model WHERE type='timesheetweek' AND entity IN (0, ".((int) $conf->entity).')';
$resql = $db->query($sql);
if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
                $enabledModels[$obj->nom] = 1;
        }
        $db->free($resql);
}

// EN: Prepare metadata arrays for rendering.
// FR: Prépare les tableaux de métadonnées pour l'affichage.
$numberingModules = timesheetweek_collect_numbering_modules($dirmodels, $langs, $sampleTimesheet, $selected);
$documentModels = timesheetweek_collect_document_models($dirmodels, $langs, $enabledModels, $defaultpdf);
$pageToken = function_exists('newToken') ? newToken() : ''; // Defensive check for legacy.

$title = $langs->trans('ModuleSetup', 'TimesheetWeek');
$helpurl = '';

llxHeader('', $title, $helpurl);

$head = timesheetweekAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'timesheetweek@timesheetweek');

print load_fiche_titre($langs->trans('TimesheetWeekSetup'), '', 'bookcal@timesheetweek');
print '<div class="opacitymedium">'.$langs->trans('TimesheetWeekSetupPage').'</div>';
print '<br>';

print '<div class="fichecenter">';

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
print '<input type="hidden" name="token" value="'.$pageToken.'">';
print '<input type="hidden" name="action" value="setmodule">';

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
        print '<label class="cursorpointer" for="model_'.$moduleInfo['classname'].'">';
        print '<input type="radio" id="model_'.$moduleInfo['classname'].'" class="flat" name="value" value="'.dol_escape_htmltag($moduleInfo['classname']).'"'.($moduleInfo['active'] ? ' checked' : '').($moduleInfo['can_be_activated'] ? '' : ' disabled').'> ';
        print dol_escape_htmltag($moduleInfo['label']);
        if ($moduleInfo['classname'] !== $moduleInfo['label']) {
                print ' <span class="opacitymedium">('.dol_escape_htmltag($moduleInfo['classname']).')</span>';
        }
        print '</label>';
        if (!$moduleInfo['can_be_activated'] && !empty($moduleInfo['activation_error'])) {
                print '<br><span class="error">'.dol_escape_htmltag($moduleInfo['activation_error']).'</span>';
        }
        print '</td>';

        print '<td class="small">'.(!empty($descHtml) ? $descHtml : '&nbsp;').'</td>';
        print '<td class="small">'.(!empty($moduleInfo['example']) ? dol_escape_htmltag($moduleInfo['example']) : '&nbsp;').'</td>';

        print '<td class="center">'.img_picto($moduleInfo['active'] ? $langs->trans('Enabled') : $langs->trans('Disabled'), $moduleInfo['active'] ? 'status1' : 'status0').'</td>';
        print '</tr>';
}

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

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

print dol_get_fiche_end();

llxFooter();
$db->close();
