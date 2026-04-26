<?php
/**
 * Substitution variables for TimesheetWeek module.
 *
 * Dolibarr loads this file automatically when module_parts['substitutions'] = 1.
 * Variables are available in email templates (admin/mails_templates.php) and
 * resolved by complete_substitutions_array() / make_substitutions().
 */

if (!defined('DOL_VERSION')) {
	exit('No direct access');
}

/**
 * Fill substitution array with __TIMESHEETWEEK_*__ placeholders.
 *
 * When called from the email template admin UI, $object is null → placeholder
 * labels are used so the variable list renders correctly.
 * When called at send time with a real TimesheetWeek object, actual values are used.
 *
 * @param  array<string,string> $substitutionarray  Substitution array to complete (passed by reference)
 * @param  Translate             $outputlangs        Output language object
 * @param  CommonObject|null     $object             Business object (TimesheetWeek or null)
 * @param  array<string,mixed>   $parameters         Extra parameters passed by the caller
 * @return int                                       Always 1
 */
function timesheetweek_completesubstitutionarray(&$substitutionarray, $outputlangs, $object, $parameters)
{
	global $db;

	if (is_object($outputlangs)) {
		$outputlangs->loadLangs(array('timesheetweek@timesheetweek', 'users'));
	}

	// Default placeholder labels shown in the admin template editor.
	$substitutionarray['__TIMESHEETWEEK_REF__']                = $outputlangs->trans('TimesheetWeekRef');
	$substitutionarray['__TIMESHEETWEEK_WEEK__']               = $outputlangs->trans('Week');
	$substitutionarray['__TIMESHEETWEEK_YEAR__']               = $outputlangs->trans('Year');
	$substitutionarray['__TIMESHEETWEEK_URL__']                = $outputlangs->trans('TimesheetWeekUrl');
	$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__']  = $outputlangs->trans('Employee');
	$substitutionarray['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $outputlangs->trans('Validator');

	// When a real TimesheetWeek object is available, replace with actual values.
	if (!is_object($object) || empty($object->element) || $object->element !== 'timesheetweek') {
		return 1;
	}

	dol_include_once('/timesheetweek/class/timesheetweek.class.php');
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

	$substitutionarray['__TIMESHEETWEEK_REF__']  = (string) $object->ref;
	$substitutionarray['__TIMESHEETWEEK_WEEK__'] = (string) $object->week;
	$substitutionarray['__TIMESHEETWEEK_YEAR__'] = (string) $object->year;
	$substitutionarray['__TIMESHEETWEEK_URL__']  = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $object->id;

	if (!empty($object->fk_user)) {
		$employee = new User($db);
		if ($employee->fetch((int) $object->fk_user) > 0) {
			$substitutionarray['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__'] = $employee->getFullName($outputlangs);
		}
	}

	if (!empty($object->fk_user_valid)) {
		$validator = new User($db);
		if ($validator->fetch((int) $object->fk_user_valid) > 0) {
			$substitutionarray['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $validator->getFullName($outputlangs);
		}
	}

	return 1;
}
