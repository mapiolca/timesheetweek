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

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

// EN: Check permissions before loading any additional resources to abort early.
// FR: Vérifie les permissions avant de charger d'autres ressources pour interrompre immédiatement.
$permRead = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll = $user->hasRight('timesheetweek','timesheetweek','readAll');
$permWrite = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll = $user->hasRight('timesheetweek','timesheetweek','writeAll');
$permDelete = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll = $user->hasRight('timesheetweek','timesheetweek','deleteAll');
$permValidate = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateOwn = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll = $user->hasRight('timesheetweek','timesheetweek','validateAll');
$canSeeAllEmployees = (!empty($user->admin) || $permReadAll || $permWriteAll || $permDeleteAll || $permValidateAll);
$permViewAny = ($permRead || $permReadChild || $permReadAll || $permWrite || $permWriteChild || $permWriteAll || $permDelete ||
$permDeleteChild || $permDeleteAll || $permValidate || $permValidateOwn || $permValidateChild || $permValidateAll || !empty($user->admin));
if (!$permViewAny) {
	accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','other','users'));

// EN: Collect the identifiers of employees the user is authorised to manage.
// FR: Rassemble les identifiants des salariés que l'utilisateur est autorisé à gérer.
$allowedUserIds = array();
if (!$canSeeAllEmployees) {
	if ($permRead || $permWrite || $permDelete || $permValidate || $permValidateOwn) {
		$allowedUserIds[] = (int) $user->id;
	}
	if ($permReadChild || $permWriteChild || $permDeleteChild || $permValidateChild) {
		$allowedUserIds = array_merge($allowedUserIds, tw_get_user_child_ids($user));
	}
	$allowedUserIds = array_values(array_unique(array_filter($allowedUserIds, function ($candidateId) {
		return (int) $candidateId > 0;
	})));
}
// EN: Detect if the Multicompany module is enabled to expose entity-specific data.
// FR: Détecte si le module Multicompany est activé pour exposer les données spécifiques d'entité.
$multicompanyEnabled = !empty($conf->multicompany->enabled);

/**
 * Params
 */
$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$show_files   = GETPOSTINT('show_files');
$confirm      = GETPOST('confirm', 'alpha');
$cancel       = GETPOST('cancel', 'alpha');
$toselect     = GETPOST('toselect', 'array');
$contextpage  = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

$sortfield    = GETPOST('sortfield', 'aZ09comma');
$sortorder    = GETPOST('sortorder', 'aZ09comma');
$page         = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) $page = 0;
$limit        = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset       = $limit * $page;

$search_ref   = trim(GETPOST('search_ref','alphanohtml'));
$search_user  = GETPOSTINT('search_user');
$search_year  = GETPOSTINT('search_year');
$search_week  = GETPOSTINT('search_week');
// EN: Retrieve the ISO year-week values for the multi-select filter and validate them.
// FR: Récupère les valeurs ISO année-semaine pour le filtre multi-sélection et les valide.
$rawWeekyearFilter = GETPOST('search_weekyear', 'array', 2);
if (!is_array($rawWeekyearFilter)) {
        $legacyWeekyear = trim(GETPOST('search_weekyear', 'alphanohtml'));
        $rawWeekyearFilter = $legacyWeekyear !== '' ? array($legacyWeekyear) : array();
}

$search_weekyears = array();
$searchWeekTuples = array();
foreach ($rawWeekyearFilter as $candidateWeekyear) {
        $candidateWeekyear = trim((string) $candidateWeekyear);
        if ($candidateWeekyear === '') {
                continue;
        }
        if (preg_match('/^(\d{4})-W(\d{2})$/', $_
