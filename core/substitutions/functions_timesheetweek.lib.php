<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/class/timesheetweeknotification.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Tell if Dolibarr is collecting available email substitutions for the help window.
 *
 * @param mixed $parameters Additional parameters passed by Dolibarr
 * @return bool
 */
function timesheetweek_is_email_template_substitution_help_context($parameters)
{
	if (!is_array($parameters)) {
		return false;
	}

	$mode = isset($parameters['mode']) ? (string) $parameters['mode'] : '';

	return in_array($mode, array('formemail', 'formemailwithlines', 'formemailforlines', 'formwithlines', 'formforlines', 'emailing'), true);
}

/**
 * Return TimesheetWeek substitutions shown in native email-template help.
 *
 * @param Translate|null $outputlangs Output language
 * @return array<string,string>
 */
function timesheetweek_get_email_template_substitution_catalog($outputlangs = null)
{
	global $langs;

	$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
	if ($trans instanceof Translate) {
		$trans->loadLangs(array('timesheetweek@timesheetweek', 'users'));
	}

	$labels = array(
		'__TIMESHEETWEEK_ID__' => 'TimesheetWeekSubstitutionId',
		'__TIMESHEETWEEK_REF__' => 'TimesheetWeekSubstitutionRef',
		'__TIMESHEETWEEK_WEEK__' => 'TimesheetWeekSubstitutionWeek',
		'__TIMESHEETWEEK_YEAR__' => 'TimesheetWeekSubstitutionYear',
		'__TIMESHEETWEEK_STATUS__' => 'TimesheetWeekSubstitutionStatus',
		'__TIMESHEETWEEK_OLD_STATUS__' => 'TimesheetWeekSubstitutionOldStatus',
		'__TIMESHEETWEEK_NEW_STATUS__' => 'TimesheetWeekSubstitutionNewStatus',
		'__TIMESHEETWEEK_TRIGGER_REASON__' => 'TimesheetWeekSubstitutionTriggerReason',
		'__TIMESHEETWEEK_TRIGGER_REASON_LABEL__' => 'TimesheetWeekSubstitutionTriggerReasonLabel',
		'__TIMESHEETWEEK_URL__' => 'TimesheetWeekSubstitutionUrlHtml',
		'__TIMESHEETWEEK_URL_RAW__' => 'TimesheetWeekSubstitutionUrlRaw',
		'__TIMESHEETWEEK_EMPLOYEE_FULLNAME__' => 'TimesheetWeekSubstitutionEmployeeFullname',
		'__TIMESHEETWEEK_EMPLOYEE_EMAIL__' => 'TimesheetWeekSubstitutionEmployeeEmail',
		'__TIMESHEETWEEK_VALIDATOR_FULLNAME__' => 'TimesheetWeekSubstitutionValidatorFullname',
		'__TIMESHEETWEEK_VALIDATOR_EMAIL__' => 'TimesheetWeekSubstitutionValidatorEmail',
		'__TIMESHEETWEEK_MOTIF__' => 'TimesheetWeekSubstitutionMotif',
		'__ACTION_USER_FULLNAME__' => 'TimesheetWeekSubstitutionActionUserFullname',
		'__ACTION_USER_EMAIL__' => 'TimesheetWeekSubstitutionActionUserEmail',
		'__RECIPIENT_FULLNAME__' => 'TimesheetWeekSubstitutionRecipientFullname',
		'__RECIPIENT_EMAIL__' => 'TimesheetWeekSubstitutionRecipientEmail',
		'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__' => 'TimesheetWeekSubstitutionNotificationSubject',
		'__TIMESHEETWEEK_NOTIFICATION_BODY__' => 'TimesheetWeekSubstitutionNotificationBody',
		'__TIMESHEETWEEK_NOTIFICATION_REASON__' => 'TimesheetWeekSubstitutionNotificationReason',
		'__TIMESHEETWEEK_NOTIFICATION_REASON_LABEL__' => 'TimesheetWeekSubstitutionNotificationReasonLabel',
		'__TIMESHEETWEEK_NOTIFICATION_TEMPLATE_ID__' => 'TimesheetWeekSubstitutionNotificationTemplateId',
	);

	$catalog = array();
	foreach ($labels as $variable => $translationKey) {
		$label = $translationKey;
		if ($trans instanceof Translate) {
			$label = $trans->transnoentities($translationKey);
		}

		$catalog[$variable] = $label;
	}

	return $catalog;
}

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

	$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
	if ($trans instanceof Translate) {
		$trans->loadLangs(array('timesheetweek@timesheetweek', 'users'));
	}

	if (!($object instanceof TimesheetWeek)) {
		if (timesheetweek_is_email_template_substitution_help_context($parameters)) {
			$substitutionarray = array_replace($substitutionarray, timesheetweek_get_email_template_substitution_catalog($trans));
		}

		return;
	}

	$employeeName = '';
	$employeeEmail = '';
	if (!empty($object->fk_user)) {
		$employee = new User($db);
		if ($employee->fetch((int) $object->fk_user) > 0) {
			$employeeName = $employee->getFullName($trans);
			$employeeEmail = (string) $employee->email;
		}
	}

	$validatorName = '';
	$validatorEmail = '';
	if (!empty($object->fk_user_valid)) {
		$validator = new User($db);
		if ($validator->fetch((int) $object->fk_user_valid) > 0) {
			$validatorName = $validator->getFullName($trans);
			$validatorEmail = (string) $validator->email;
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

	$substitutionarray['__ID__'] = (string) $object->id;
	$substitutionarray['__REF__'] = (string) $object->ref;
	$substitutionarray['__LABEL__'] = (string) $object->ref;
	$substitutionarray['__TIMESHEETWEEK_ID__'] = (string) $object->id;
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
	$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_EMAIL__'] = $employeeEmail;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_NAME__'] = $validatorName;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $validatorName;
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_EMAIL__'] = $validatorEmail;
	$substitutionarray['__TIMESHEETWEEK_MOTIF__'] = !empty($object->motif) ? (string) $object->motif : ((!empty($object->context) && is_array($object->context) && !empty($object->context['timesheetweek_motif'])) ? (string) $object->context['timesheetweek_motif'] : '');

	static $buildingNativeNotificationSubstitutions = false;
	if (!$buildingNativeNotificationSubstitutions && class_exists('TimesheetWeekNotification')) {
		$buildingNativeNotificationSubstitutions = true;
		$actionUser = isset($GLOBALS['user']) && $GLOBALS['user'] instanceof User ? $GLOBALS['user'] : null;
		$nativeNotificationSubstitutions = TimesheetWeekNotification::getNativeNotificationSubstitutions($object, $trans, $actionUser);
		$substitutionarray = array_replace($substitutionarray, $nativeNotificationSubstitutions);
		$buildingNativeNotificationSubstitutions = false;
	}
}
