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

$langs->loadLangs(array('admin', 'other', 'timesheetweek@timesheetweek'));

if (empty($user->admin)) {
        accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');

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

$title = $langs->trans('ModuleSetup', 'TimesheetWeek');
$helpurl = '';

llxHeader('', $title, $helpurl);

$head = timesheetweekAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'timesheetweek@timesheetweek');

print load_fiche_titre($langs->trans('TimesheetWeekSetup'), '', 'bookcal@timesheetweek');
print '<div class="opacitymedium">'.$langs->trans('TimesheetWeekSetupPage').'</div>';
print '<br>';

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
$formToken = newToken();
print '<input type="hidden" name="token" value="'.$formToken.'">';
print '<input type="hidden" name="action" value="setmodule">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '</tr>';

$found = 0;
foreach ($dirmodels as $reldir) {
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
                } catch (Throwable $e) {
                        continue;
                }

                $found++;
                $isActive = ($selected === $classname);

                $label = !empty($module->name) ? $module->name : $classname;
                if ($label && $langs->transnoentitiesnoconv($label) !== $label) {
                        $label = $langs->trans($label);
                }

                $desc = '';
                if (method_exists($module, 'info')) {
                        try {
                                $desc = $module->info($langs);
                        } catch (Throwable $e) {
                                $desc = '';
                        }
                } elseif (!empty($module->description)) {
                        $desc = $module->description;
                } elseif (!empty($module->desc)) {
                        $desc = $module->desc;
                }

                print '<tr class="oddeven">';
                print '<td class="nowraponall">';
                print '<label class="cursorpointer" for="model_'.$classname.'">';
                print '<input type="radio" id="model_'.$classname.'" class="flat" name="value" value="'.dol_escape_htmltag($classname).'"'.($isActive ? ' checked' : '').'> ';
                print dol_escape_htmltag($label);
                if ($classname !== $label) {
                        print ' <span class="opacitymedium">('.dol_escape_htmltag($classname).')</span>';
                }
                print '</label>';
                print '</td>';

                if (!empty($desc)) {
                        $descIsPlainText = ($desc === strip_tags($desc));
                        $descHtml = $descIsPlainText ? dol_escape_htmltag($desc) : $desc;
                } else {
                        $descHtml = '&nbsp;';
                }

                print '<td class="small">'.$descHtml.'</td>';

                print '<td class="center">';
                print img_picto($isActive ? $langs->trans('Enabled') : $langs->trans('Disabled'), $isActive ? 'status1' : 'status0');
                print '</td>';
                print '</tr>';
        }
}

if (!$found) {
        print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print '<br>';

print load_fiche_titre($langs->trans('TimesheetWeekPDFModels'), '', 'pdf');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Name').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Type').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th class="center">'.$langs->trans('Default').'</th>';
print '</tr>';

$enabledModels = array();
$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX."document_model WHERE type='timesheetweek' AND entity IN (0, ".((int) $conf->entity).')';
$resql = $db->query($sql);
if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
                $enabledModels[$obj->nom] = 1;
        }
        $db->free($resql);
}

$found = 0;
$docToken = newToken();
foreach ($dirmodels as $reldir) {
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
                } catch (Throwable $e) {
                        continue;
                }

                if (!property_exists($module, 'type') || $module->type !== 'pdf') {
                        continue;
                }

                $found++;
                $name = $module->name ?: $classname;
                $desc = !empty($module->description) ? $module->description : '';
                if ($desc && $langs->transnoentitiesnoconv($desc) !== $desc) {
                        $desc = $langs->trans($desc);
                }
                $isEnabled = !empty($enabledModels[$name]);
                $isDefault = ($defaultpdf === $name);

                print '<tr class="oddeven">';
                print '<td class="nowraponall">'.dol_escape_htmltag($name).'</td>';
                print '<td class="small">'.(!empty($desc) ? dol_escape_htmltag($desc) : '&nbsp;').'</td>';
                print '<td class="center">'.dol_escape_htmltag($module->type).'</td>';
                print '<td class="center">';
                if ($isEnabled) {
                        $url = $_SERVER['PHP_SELF'].'?action=delmodel&value='.urlencode($name).'&token='.$docToken;
                        print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disable'), 'switch_on').'</a>';
                } else {
                        $url = $_SERVER['PHP_SELF'].'?action=setdocmodel&value='.urlencode($name).'&token='.$docToken;
                        print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Activate'), 'switch_off').'</a>';
                }
                print '</td>';

                print '<td class="center">';
                if ($isDefault) {
                        print img_picto($langs->trans('Enabled'), 'on');
                } elseif ($isEnabled) {
                        $url = $_SERVER['PHP_SELF'].'?action=setdoc&value='.urlencode($name).'&token='.$docToken;
                        print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('SetDefault'), 'switch_on').'</a>';
                } else {
                        print '&nbsp;';
                }
                print '</td>';
                print '</tr>';
        }
}

if (!$found) {
        print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
