<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Complete substitution array for TimesheetWeek objects.
 *
 * @param array      $substitutionarray Substitution array passed by Dolibarr
 * @param Translate  $outputlangs       Output language
 * @param object|null $object           Current object
 * @param mixed      $parameters        Additional parameters
 * @return void
 */
function timesheetweek_completesubstitutionarray(&$substitutionarray, $outputlangs, $object = null, $parameters = null)
{
	global $db, $langs;

	if (!($object instanceof TimesheetWeek)) {
		return;
	}

	$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
	if ($trans instanceof Translate) {
		$trans->loadLangs(array('timesheetweek@timesheetweek', 'users'));
	}

	$employeeName = '';
	if (!empty($object->fk_user)) {
		$employee = new User($db);
		if ($employee->fetch((int) $object->fk_user) > 0) {
			$employeeName = $employee->getFullName($trans);
		}
	}

	$validatorName = '';
	if (!empty($object->fk_user_valid)) {
		$validator = new User($db);
		if ($validator->fetch((int) $object->fk_user_valid) > 0) {
			$validatorName = $validator->getFullName($trans);
		}
	}

	$status = method_exists($object, 'getLibStatut') ? $object->getLibStatut(0) : (string) $object->status;
	$url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $object->id;

	$substitutionarray['__TIMESHEETWEEK_REF__'] = (string) $object->ref;
	$substitutionarray['__TIMESHEETWEEK_WEEK__'] = (string) $object->week;
	$substitutionarray['__TIMESHEETWEEK_YEAR__'] = (string) $object->year;
	$substitutionarray['__TIMESHEETWEEK_STATUS__'] = $status;
	$substitutionarray['__TIMESHEETWEEK_URL__'] = $url;
	$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_NAME__'] = $employeeName;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_NAME__'] = $validatorName;
	$substitutionarray['__TIMESHEETWEEK_MOTIF__'] = !empty($object->motif) ? (string) $object->motif : '';
}
