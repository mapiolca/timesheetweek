<?php

$moduleRoot = dirname(__DIR__);
$sources = array(
	'helper' => file_get_contents($moduleRoot.'/lib/timesheetweek.lib.php'),
	'card' => file_get_contents($moduleRoot.'/timesheetweek_card.php'),
	'mobile' => file_get_contents($moduleRoot.'/tpl/timesheetweek_card_mobile.tpl.php'),
	'desktop' => file_get_contents($moduleRoot.'/tpl/timesheetweek_card_desktop.tpl.php'),
	'css' => file_get_contents($moduleRoot.'/css/timesheetweek_card_mobile.css'),
	'mobilejs' => file_get_contents($moduleRoot.'/js/timesheetweek_card_mobile.js'),
	'setup' => file_get_contents($moduleRoot.'/admin/setup.php'),
);

foreach ($sources as $name => $source) {
	if ($source === false) {
		fwrite(STDERR, "Unable to read the ".$name." source.\n");
		exit(1);
	}
}

$expectedFragments = array(
	'helper' => array(
		'function tw_get_task_nomurl(Task $task, $withpicto = 0, $withproject = false, $withref = true)',
		'elseif ($text === \'\')',
	),
	'card' => array(
		'1 => 8.0',
		'2 => 4.0',
		'3 => 4.0',
		'$map[4] = 2.0',
	),
	'mobile' => array(
		"getDolGlobalString('TIMESHEETWEEK_MOBILE_TASK_LABEL_MODE', 'single')",
		"array('single', 'double', 'full')",
		'foreach ($byproject as $projectId => $projectData)',
		'<colgroup><col class="tw-task-label-column"><col class="tw-task-entry-column"></colgroup>',
		'tw_get_task_nomurl($taskObject, 1, false, false)',
		'tw-task-label-content',
		'4 => $langs->trans(\'TimesheetWeekDailyRateQuarterDay\')',
		'2 => $langs->trans(\'TimesheetWeekDailyRateHalfDay\')',
		'1 => $langs->trans(\'TimesheetWeekDailyRateOneDay\')',
		'$dailyRateOptions[3] = $langs->trans(\'TimesheetWeekDailyRateHalfDay\')',
		'class="flat daily-rate-select"',
		'class="flat hourinput"',
	),
	'desktop' => array(
		'tw_get_task_nomurl($tsk, 1)',
	),
	'css' => array(
		'table-layout: fixed',
		'--tw-task-entry-width: max(7rem, 40%)',
		'.tw-task-label { width: 60%; max-width: 60%; min-width: 0; }',
		'.tw-task-entry-column,',
		'.tw-task-entry { width: var(--tw-task-entry-width); }',
		'.tw-project-title a { white-space: normal; overflow-wrap: anywhere; }',
		'.tw-task-label-mode-single',
		'.tw-task-label-mode-double',
		'.tw-task-label-mode-full',
		'.tw-task-day-field .hourinput',
		'.tw-task-day-field .daily-rate-select { width: 100%; min-width: 0; max-width: 100%; min-height: 44px',
	),
	'mobilejs' => array(
		"return form.querySelectorAll('input.hourinput, select.daily-rate-select, select.tw-zone-select, input.mealbox')",
		"form.querySelectorAll('input.hourinput, select.daily-rate-select')",
		"(config.dailyRateHours || {})[field.value]",
	),
	'setup' => array(
		'$action === \'savemobiledisplay\'',
		'dolibarr_set_const($db, \'TIMESHEETWEEK_MOBILE_TASK_LABEL_MODE\', $mobileTaskLabelModeValue, \'chaine\', 0, \'\', $conf->entity)',
		'ajax_combobox(\'TIMESHEETWEEK_MOBILE_TASK_LABEL_MODE\')',
	),
);

foreach ($expectedFragments as $sourceName => $fragments) {
	foreach ($fragments as $fragment) {
		if (strpos($sources[$sourceName], $fragment) === false) {
			fwrite(STDERR, "Missing mobile task-label regression marker in ".$sourceName.": ".$fragment."\n");
			exit(1);
		}
	}
}

$projectLoopPosition = strpos($sources['mobile'], 'foreach ($byproject as $projectId => $projectData)');
$columnGroupPosition = strpos($sources['mobile'], '<colgroup><col class="tw-task-label-column"><col class="tw-task-entry-column"></colgroup>');
$taskLoopPosition = strpos($sources['mobile'], "foreach (\$projectData['tasks'] as \$task)");
if ($projectLoopPosition === false || $columnGroupPosition === false || $taskLoopPosition === false || $columnGroupPosition <= $projectLoopPosition || $columnGroupPosition >= $taskLoopPosition) {
	fwrite(STDERR, "The shared mobile colgroup must be rendered inside every project block before its tasks.\n");
	exit(1);
}

echo "Mobile task-label display test passed.\n";
