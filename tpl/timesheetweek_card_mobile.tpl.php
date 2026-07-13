<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file tpl/timesheetweek_card_mobile.tpl.php
 * \brief Native-looking phone layout for a weekly timesheet.
 */

if (!defined('DOL_VERSION')) {
	exit;
}

$editable = ($object->status == tw_status('draft') && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user));
$contractedHours = $contractedHoursDisp;
$canOverrideHolidayLock = tw_can_override_holiday_lock($user);
$holidayMarkerByDay = tw_get_holiday_markers_by_day($db, $object->fk_user, $weekdates, $langs, !empty($object->entity) ? (int) $object->entity : (int) $conf->entity);
$dailyRateHoursMap = tw_get_daily_rate_hours_map($useQuarterDayDailyContract);
$dailyRateOptions = array();
if ($isDailyRateEmployee) {
	if ($useQuarterDayDailyContract) {
		$dailyRateOptions = array(
			4 => $langs->trans('TimesheetWeekDailyRateQuarterDay'),
			2 => $langs->trans('TimesheetWeekDailyRateHalfDay'),
			1 => $langs->trans('TimesheetWeekDailyRateOneDay'),
		);
		if ($hasLegacyHalfDayDailyRate) {
			$dailyRateOptions[3] = $langs->trans('TimesheetWeekDailyRateHalfDay');
		}
	} else {
		$dailyRateOptions = array(
			1 => $langs->trans('TimesheetWeekDailyRateFullDay'),
			2 => $langs->trans('TimesheetWeekDailyRateMorning'),
			3 => $langs->trans('TimesheetWeekDailyRateAfternoon'),
		);
	}
}

$byproject = array();
foreach ($tasks as $task) {
	$projectId = (int) $task['project_id'];
	if (!isset($byproject[$projectId])) {
		$byproject[$projectId] = array(
			'ref' => (string) $task['project_ref'],
			'title' => (string) $task['project_title'],
			'tasks' => array(),
		);
	}
	$byproject[$projectId]['tasks'][] = $task;
}

$autosaveConfig = array(
	'endpoint' => dol_buildpath('/timesheetweek/ajax/timesheetweek_autosave.php', 1),
	'token' => newToken(),
	'id' => (int) $object->id,
	'revision' => (int) $object->tms,
	'editable' => $editable ? 1 : 0,
	'dailyRate' => $isDailyRateEmployee ? 1 : 0,
	'dailyRateHours' => $dailyRateHoursMap,
	'contract' => (float) $contractedHours,
	'storageKey' => 'timesheetweek:draft:'.((int) $object->entity).':'.((int) $object->id).':'.((int) $user->id),
	'messages' => array(
		'saved' => $langs->trans('TimesheetWeekAutosaveSaved'),
		'saving' => $langs->trans('TimesheetWeekAutosaveSaving'),
		'pending' => $langs->trans('TimesheetWeekAutosavePending'),
		'offline' => $langs->trans('TimesheetWeekAutosaveOffline'),
		'error' => $langs->trans('TimesheetWeekAutosaveError'),
		'restore' => $langs->trans('TimesheetWeekAutosaveRestore'),
	),
);

print '<div id="timesheetweek-mobile-card" class="timesheetweek-mobile-card" data-config="'.dol_escape_htmltag((string) json_encode($autosaveConfig)).'">';
print '<div id="tw-autosave-status" class="tw-autosave-status opacitymedium" role="status" aria-live="polite">'.dol_escape_htmltag($langs->trans('TimesheetWeekAutosaveReady')).'</div>';
print '<div id="tw-restore-message" class="warning tw-restore-message" hidden>';
print '<span>'.$langs->trans('TimesheetWeekAutosaveRestore').'</span> ';
print '<button type="button" class="button small" data-tw-restore="yes">'.$langs->trans('TimesheetWeekAutosaveRestoreAction').'</button> ';
print '<button type="button" class="button small" data-tw-restore="no">'.$langs->trans('TimesheetWeekAutosaveDiscardAction').'</button>';
print '</div>';

if (empty($tasks)) {
	print '<div class="opacitymedium">'.$langs->trans('NoTasksAssigned').'</div>';
	print '</div>';
	return;
}

print '<form id="timesheetweek-mobile-form" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<nav class="tw-day-navigation" aria-label="'.$langs->trans('TimesheetWeekMobileDayNavigation').'">';
foreach ($days as $index => $day) {
	$dayLabelKey = isset($dayLabelKeys[$day]) ? $dayLabelKeys[$day] : $day;
	$dateLabel = !empty($weekdates[$day]) ? dol_print_date(strtotime($weekdates[$day]), 'day') : '';
	print '<button type="button" class="button tw-day-button'.($index === 0 ? ' tw-day-active' : '').'" data-tw-day="'.dol_escape_htmltag($day).'">';
	print '<span>'.dol_escape_htmltag($langs->trans($dayLabelKey)).'</span>';
	if ($dateLabel !== '') {
		print '<small>'.dol_escape_htmltag($dateLabel).'</small>';
	}
	print '</button>';
}
print '</nav>';

if (!$isDailyRateEmployee) {
	foreach ($days as $index => $day) {
		$lockedLeave = !empty($holidayMarkerByDay[$day]['has_leave']) && empty($holidayMarkerByDay[$day]['is_public_holiday']);
		$disabled = (!$editable || ($lockedLeave && !$canOverrideHolidayLock)) ? ' disabled' : '';
		print '<section class="tw-day-options'.($index === 0 ? '' : ' tw-day-hidden').'" data-tw-panel="'.dol_escape_htmltag($day).'">';
		print '<table class="noborder centpercent"><tbody><tr>';
		print '<td><label for="tw-zone-'.$day.'">'.$langs->trans('Zone').'</label></td><td class="right">';
		print '<select id="tw-zone-'.$day.'" name="zone_'.$day.'" class="flat tw-zone-select"'.$disabled.'><option value=""></option>';
		for ($zone = 1; $zone <= 5; $zone++) {
			print '<option value="'.$zone.'"'.((int) $dayZone[$day] === $zone ? ' selected' : '').'>'.$zone.'</option>';
		}
		print '</select></td></tr><tr>';
		print '<td><label for="tw-meal-'.$day.'">'.$langs->trans('Meal').'</label></td><td class="right">';
		print '<label class="switch"><input id="tw-meal-'.$day.'" type="checkbox" name="meal_'.$day.'" value="1" class="mealbox"'.(!empty($dayMeal[$day]) ? ' checked' : '').$disabled.'><span class="slider round"></span></label>';
		print '</td></tr></tbody></table></section>';
	}
}

print '<div class="tw-mobile-summary">';
print '<span>'.$langs->trans($isDailyRateEmployee ? 'TimesheetWeekTotalDays' : 'Total').'</span>';
print '<strong id="tw-mobile-grand-total">'.($isDailyRateEmployee ? tw_format_days(((float) $object->total_hours) / 8.0, $langs) : formatHours((float) $object->total_hours)).'</strong>';
print '</div>';

foreach ($byproject as $projectId => $projectData) {
	$project = new Project($db);
	if ($project->fetch((int) $projectId) <= 0) {
		$project->id = (int) $projectId;
		$project->ref = $projectData['ref'];
		$project->title = $projectData['title'];
	}
	print '<section class="tw-project-section">';
	print '<div class="liste_titre tw-project-title">'.tw_get_project_nomurl($project, 1).'</div>';
	print '<table class="noborder centpercent tw-mobile-task-table"><tbody>';
	foreach ($projectData['tasks'] as $task) {
		$taskId = (int) $task['task_id'];
		$taskObject = new Task($db);
		if ($taskObject->fetch($taskId) <= 0) {
			$taskObject->id = $taskId;
			$taskObject->ref = isset($task['task_ref']) ? (string) $task['task_ref'] : '';
			$taskObject->label = (string) $task['task_label'];
		}
		print '<tr class="oddeven tw-task-row"><td class="tw-task-label">'.tw_get_task_nomurl($taskObject, 1).'</td><td class="right tw-task-entry">';
		foreach ($days as $index => $day) {
			$dateKey = $weekdates[$day];
			$hoursValue = isset($hoursBy[$taskId][$dateKey]) ? formatHours((float) $hoursBy[$taskId][$dateKey]) : '';
			$dailyValue = isset($dailyRateBy[$taskId][$dateKey]) ? (int) $dailyRateBy[$taskId][$dateKey] : 0;
			$holidayLabel = !empty($holidayMarkerByDay[$day]['label']) ? (string) $holidayMarkerByDay[$day]['label'] : '';
			$isPublicHoliday = !empty($holidayMarkerByDay[$day]['is_public_holiday']);
			$isLocked = ($editable && $holidayLabel !== '' && !$isPublicHoliday && !$canOverrideHolidayLock);
			$fieldDisabled = (!$editable || $isLocked) ? ' disabled' : '';
			print '<div class="tw-task-day-field'.($index === 0 ? '' : ' tw-day-hidden').'" data-tw-panel="'.dol_escape_htmltag($day).'">';
			if ($isDailyRateEmployee) {
				print '<select name="daily_'.$taskId.'_'.$day.'" class="flat daily-rate-select"'.$fieldDisabled.'><option value="">'.dol_escape_htmltag($holidayLabel).'</option>';
				foreach ($dailyRateOptions as $code => $label) {
					print '<option value="'.((int) $code).'"'.($dailyValue === (int) $code ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
				}
				print '</select>';
			} else {
				print '<input type="text" inputmode="decimal" autocomplete="off" name="hours_'.$taskId.'_'.$day.'" value="'.dol_escape_htmltag($hoursValue).'" placeholder="'.dol_escape_htmltag($holidayLabel !== '' ? $holidayLabel : '00:00').'" class="flat hourinput"'.$fieldDisabled.'>';
			}
			print '</div>';
		}
		print '</td></tr>';
	}
	print '</tbody></table></section>';
}

if ($editable) {
	print '<div class="center tw-mobile-save"><button type="submit" class="button button-save">'.$langs->trans('Save').'</button></div>';
} else {
	print '<div class="opacitymedium center">'.$langs->trans('TimesheetIsNotEditable').'</div>';
}
print '</form></div>';
