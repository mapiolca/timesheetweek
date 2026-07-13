<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file ajax/timesheetweek_autosave.php
 * \brief Secure draft-cell autosave endpoint for the phone layout.
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', 1);
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', 1);

$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = include '../../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('timesheetweek@timesheetweek'));
top_httphead('application/json; charset=UTF-8');

/**
 * Emit a JSON response and stop execution.
 *
 * @param bool   $success Success flag
 * @param string $message Translated message
 * @param int    $httpCode HTTP status
 * @param array<string,mixed> $extra Extra response values
 * @return never
 */
function timesheetweekAutosaveResponse($success, $message, $httpCode = 200, array $extra = array())
{
	global $db;
	http_response_code($httpCode);
	print json_encode(array_merge(array('success' => (bool) $success, 'message' => (string) $message), $extra));
	$db->close();
	exit;
}

if (!isModEnabled('timesheetweek')) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ModuleDisabled'), 403);
}
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 405);
}

$id = GETPOSTINT('id', 2);
$action = GETPOST('action', 'aZ09', 2);
$revision = GETPOSTINT('revision', 2);
$rawChange = GETPOST('change', 'none', 2);
if ($action !== 'autosave' || $id <= 0 || $rawChange === '') {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
}

$change = json_decode($rawChange, true);
if (!is_array($change)) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
}

$object = new TimesheetWeek($db);
$fetchResult = $object->fetch($id);
if ($fetchResult <= 0) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorRecordNotFound'), 404);
}

$permWrite = $user->hasRight('timesheetweek', 'write');
$permWriteChild = $user->hasRight('timesheetweek', 'writeChild');
$permWriteAll = !empty($user->admin) || $user->hasRight('timesheetweek', 'writeAll');
$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAjaxForbidden'), 403);
}
if ((int) $object->status !== (int) TimesheetWeek::STATUS_DRAFT) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetIsNotEditable'), 409);
}
if ($revision > 0 && (int) $object->tms !== $revision) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAutosaveConflict'), 409, array('revision' => (int) $object->tms));
}

$allowedDays = array('Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6);
$day = isset($change['day']) && is_string($change['day']) ? $change['day'] : '';
if (!array_key_exists($day, $allowedDays)) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
}

$zone = isset($change['zone']) && $change['zone'] !== '' ? (int) $change['zone'] : 0;
$meal = !empty($change['meal']) ? 1 : 0;
if ($zone < 0 || $zone > 5) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
}

$date = new DateTime();
$date->setISODate((int) $object->year, (int) $object->week);
$date->modify('+'.$allowedDays[$day].' day');
$dateSql = $date->format('Y-m-d');
$settingsOnly = !empty($change['settingsOnly']);
$taskId = isset($change['taskId']) ? (int) $change['taskId'] : 0;
$hours = 0.0;
$dailyRate = 0;
$employee = new User($db);
$employeeDailyRate = false;
if ($employee->fetch((int) $object->fk_user) > 0) {
	$employee->fetch_optionals($employee->id, $employee->table_element);
	$employeeDailyRate = !empty($employee->array_options['options_lmdb_daily_rate']);
}
if ($settingsOnly && $employeeDailyRate) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
}

if (!$settingsOnly) {
	$allowedTaskIds = array();
	foreach ($object->getAssignedTasks($object->fk_user) as $task) {
		$allowedTaskIds[(int) $task['task_id']] = true;
	}
	foreach ($object->getLines() as $line) {
		$allowedTaskIds[(int) $line->fk_task] = true;
	}
	if ($taskId <= 0 || empty($allowedTaskIds[$taskId])) {
		timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAutosaveInvalidTask'), 403);
	}

	$isDailyRate = !empty($change['dailyRate']);
	if ($isDailyRate !== $employeeDailyRate) {
		timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
	}

	$rawValue = isset($change['value']) && is_scalar($change['value']) ? trim((string) $change['value']) : '';
	if ($isDailyRate) {
		$dailyRate = $rawValue === '' ? 0 : (int) $rawValue;
		$dailyRateHours = array(1 => 8.0, 2 => 4.0, 3 => 4.0);
		if (getDolGlobalInt('TIMESHEETWEEK_QUARTERDAYFORDAILYCONTRACT', 0)) $dailyRateHours[4] = 2.0;
		if ($dailyRate > 0 && !isset($dailyRateHours[$dailyRate])) {
			timesheetweekAutosaveResponse(false, $langs->transnoentities('ErrorBadParameters'), 400);
		}
		$hours = isset($dailyRateHours[$dailyRate]) ? $dailyRateHours[$dailyRate] : 0.0;
	} elseif ($rawValue !== '') {
		$normalized = str_replace(',', '.', $rawValue);
		if (preg_match('/^([0-9]{1,2}):([0-5][0-9])$/', $normalized, $matches)) {
			$hours = (int) $matches[1] + ((int) $matches[2] / 60);
		} elseif (preg_match('/^[0-9]{1,2}(?:\.[0-9]{1,2})?$/', $normalized)) {
			$hours = (float) $normalized;
		} else {
			timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAutosaveInvalidHours'), 400);
		}
		if ($hours > 24) {
			timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAutosaveInvalidHours'), 400);
		}
	}
}

$saveResult = $object->saveDraftEntry($user, array(
	'settings_only' => $settingsOnly,
	'task_id' => $taskId,
	'date' => $dateSql,
	'hours' => $hours,
	'daily_rate' => $dailyRate,
	'zone' => $zone,
	'meal' => $meal,
	'expected_revision' => $revision,
));
if ($saveResult === -2) {
	$messageKey = $object->error === 'TimesheetWeekAutosaveConflict' ? 'TimesheetWeekAutosaveConflict' : 'TimesheetIsNotEditable';
	timesheetweekAutosaveResponse(false, $langs->transnoentities($messageKey), 409, array('revision' => (int) $object->tms));
}
if ($saveResult < 0) {
	timesheetweekAutosaveResponse(false, $langs->transnoentities('TimesheetWeekAutosaveError'), 500);
}

dol_syslog('TimesheetWeek mobile draft autosaved for sheet '.$object->id, LOG_DEBUG);
timesheetweekAutosaveResponse(true, $langs->transnoentities('TimesheetWeekAutosaveSaved'), 200, array(
	'revision' => (int) $object->tms,
	'totalHours' => (float) $object->total_hours,
	'overtimeHours' => (float) $object->overtime_hours,
));
