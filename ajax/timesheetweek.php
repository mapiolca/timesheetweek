<?php
/* Copyright (C) 2022       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025       Pierre Ardoin           <developpeur@lesmetiersdubatiment.fr>
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

/**
 *       \file       htdocs/timesheetweek/ajax/timesheetweek.php
 *       \brief      File to return Ajax response on timesheetweek list request
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$mode = GETPOST('mode', 'aZ09');
$objectId = GETPOSTINT('objectId');
$field = GETPOST('field', 'aZ09');
$value = GETPOST('value', 'aZ09');

// @phan-suppress-next-line PhanUndeclaredClass
$object = new TimesheetWeek($db);

// EN: Evaluate write permissions, including subordinate scope, before processing the request.
// FR: Évalue les permissions d'écriture, y compris sur les subordonnés, avant de traiter la requête.
$permWrite = $user->hasRight('timesheetweek', 'write');
$permWriteChild = $user->hasRight('timesheetweek', 'writeChild');
$permWriteAll = $user->hasRight('timesheetweek', 'writeAll');
$canWriteAll = (!empty($user->admin) || $permWriteAll);
if (!($permWrite || $permWriteChild || $canWriteAll)) {
	// EN: Return a JSON 403 response when the user cannot edit any timesheet.
	// FR: Retourne une réponse JSON 403 lorsque l'utilisateur ne peut éditer aucune feuille.
	top_httphead('application/json; charset=UTF-8');
	http_response_code(403);
	print json_encode(array(
		'status' => 'error',
		'message' => $langs->transnoentities('TimesheetWeekAjaxForbidden')
	));
	$db->close();
	exit;
}

/*
 * View
 */

dol_syslog("Call ajax timesheetweek/ajax/timesheetweek.php");

// EN: Prepare the JSON response headers for the AJAX consumer.
// FR: Prépare les en-têtes JSON de la réponse pour le client AJAX.
top_httphead('application/json; charset=UTF-8');

// Update the object field with the new value
if ($objectId && $field && isset($value)) {
	$fetchResult = $object->fetch($objectId);
	if ($fetchResult <= 0 || empty($object->id)) {
		// EN: Return a JSON error when the target timesheet cannot be retrieved.
		// FR: Retourne une erreur JSON lorsque la feuille de temps cible ne peut pas être récupérée.
		print json_encode(['status' => 'error', 'message' => $langs->transnoentities('ErrorRecordNotFound')]);
		$db->close();
		exit;
	}
		if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $canWriteAll, $user)) {
			// EN: Deny updates outside the manager scope with a structured JSON reply.
			// FR: Refuse les mises à jour hors périmètre manager via une réponse JSON structurée.
			http_response_code(403);
			print json_encode(array(
				'status' => 'error',
				'message' => $langs->transnoentities('TimesheetWeekAjaxForbidden')
			));
			$db->close();
			exit;
		}
	$object->$field = $value;
	$result = $object->update($user);
	if ($result < 0) {
		// EN: Return a translated error when the update fails.
		// FR: Retourne une erreur traduite lorsque la mise à jour échoue.
		print json_encode(array(
			'status' => 'error',
			'message' => $langs->transnoentities('TimesheetWeekAjaxUpdateError', $field)
		));
	} else {
		// EN: Confirm the update with a translated success message.
		// FR: Confirme la mise à jour avec un message de succès traduit.
		print json_encode(array(
			'status' => 'success',
			'message' => $langs->transnoentities('TimesheetWeekAjaxUpdateSuccess', $field)
		));
	}
}

$db->close();
