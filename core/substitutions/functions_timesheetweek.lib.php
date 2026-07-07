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
	$urlHtml = '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($url).'</a>';
	$triggerReason = (!empty($object->context) && is_array($object->context) && !empty($object->context['trigger_reason'])) ? (string) $object->context['trigger_reason'] : '';
	$triggerReasonLabel = '';
	if ($triggerReason !== '') {
		$triggerReasonKey = 'TimesheetWeekNotificationReason'.ucfirst($triggerReason);
		$triggerReasonLabel = $trans instanceof Translate ? $trans->trans($triggerReasonKey) : $triggerReason;
		if ($triggerReasonLabel === $triggerReasonKey) {
			$triggerReasonLabel = $triggerReason;
		}
	}
	$oldStatus = (!empty($object->context) && is_array($object->context) && array_key_exists('old_status', $object->context)) ? $object->context['old_status'] : null;
	$newStatus = (!empty($object->context) && is_array($object->context) && array_key_exists('new_status', $object->context)) ? $object->context['new_status'] : null;
	$oldStatusLabel = '';
	if ($oldStatus !== null && method_exists('TimesheetWeek', 'getStatusBadgeDefinition')) {
		$oldStatusDefinition = TimesheetWeek::getStatusBadgeDefinition((int) $oldStatus, $trans);
		$oldStatusLabel = !empty($oldStatusDefinition['label']) ? (string) $oldStatusDefinition['label'] : (string) $oldStatus;
	}
	$newStatusLabel = '';
	if ($newStatus !== null && method_exists('TimesheetWeek', 'getStatusBadgeDefinition')) {
		$newStatusDefinition = TimesheetWeek::getStatusBadgeDefinition((int) $newStatus, $trans);
		$newStatusLabel = !empty($newStatusDefinition['label']) ? (string) $newStatusDefinition['label'] : (string) $newStatus;
	}

	$substitutionarray['__TIMESHEETWEEK_REF__'] = (string) $object->ref;
	$substitutionarray['__TIMESHEETWEEK_WEEK__'] = (string) $object->week;
	$substitutionarray['__TIMESHEETWEEK_YEAR__'] = (string) $object->year;
	$substitutionarray['__TIMESHEETWEEK_STATUS__'] = $status;
	$substitutionarray['__TIMESHEETWEEK_OLD_STATUS__'] = $oldStatusLabel;
	$substitutionarray['__TIMESHEETWEEK_NEW_STATUS__'] = $newStatusLabel;
	$substitutionarray['__TIMESHEETWEEK_TRIGGER_REASON__'] = $triggerReason;
	$substitutionarray['__TIMESHEETWEEK_TRIGGER_REASON_LABEL__'] = $triggerReasonLabel;
	$substitutionarray['__TIMESHEETWEEK_URL__'] = $urlHtml;
	$substitutionarray['__TIMESHEETWEEK_URL_RAW__'] = $url;
	$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_NAME__'] = $employeeName;
	$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__'] = $employeeName;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_NAME__'] = $validatorName;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $validatorName;
	$substitutionarray['__TIMESHEETWEEK_MOTIF__'] = !empty($object->motif) ? (string) $object->motif : ((!empty($object->context) && is_array($object->context) && !empty($object->context['timesheetweek_motif'])) ? (string) $object->context['timesheetweek_motif'] : '');
}
