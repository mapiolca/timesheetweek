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

if ($action === 'setmodule') {
        $value = GETPOST('value', 'alpha');
        $result = dolibarr_set_const($db, 'TIMESHEETWEEK_MYOBJECT_ADDON', $value, 'chaine', 0, '', $conf->entity);
        if ($result > 0) {
                setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
        } else {
                setEventMessages($langs->trans('Error'), null, 'errors');
        }
}

$selected = getDolGlobalString('TIMESHEETWEEK_MYOBJECT_ADDON', 'mod_timesheetweek_standard');
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
print '<input type="hidden" name="token" value="'.newToken().'">';
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

print dol_get_fiche_end();

llxFooter();
$db->close();
