<?php
require_once __DIR__.'/../lib/timesheetweek.lib.php';

$errors = array();

$fixedRules = array(
	array('year' => 0, 'month' => 5, 'day' => 1, 'dayrule' => '')
);
if (!tw_is_public_holiday_from_rules('2026-05-01', $fixedRules)) {
	$errors[] = 'fixed public holiday rule was not detected';
}

$easterRules = array(
	array('year' => 0, 'month' => 0, 'day' => 0, 'dayrule' => 'eastermonday')
);
if (!tw_is_public_holiday_from_rules('2026-04-06', $easterRules)) {
	$errors[] = 'Easter Monday 2026 rule was not detected';
}

$leaveDays = 0.0;
$rttDays = 0.0;
$publicHolidayDays = 0.0;
$period = new DatePeriod(new DateTime('2026-04-06'), new DateInterval('P1D'), new DateTime('2026-04-11'));
foreach ($period as $date) {
	$isoDate = $date->format('Y-m-d');
	$split = tw_split_leave_day_value(1.0, false, tw_is_public_holiday_from_rules($isoDate, $easterRules));
	$leaveDays += (float) $split['leave_days'];
	$rttDays += (float) $split['rtt_days'];
	$publicHolidayDays += (float) $split['public_holiday_days'];
}

if ($leaveDays !== 4.0) {
	$errors[] = sprintf('expected 4 leave days, got %.2f', $leaveDays);
}
if ($rttDays !== 0.0) {
	$errors[] = sprintf('expected 0 RTT days, got %.2f', $rttDays);
}
if ($publicHolidayDays !== 1.0) {
	$errors[] = sprintf('expected 1 public holiday day, got %.2f', $publicHolidayDays);
}

if ($errors) {
	fwrite(STDERR, "public holiday overlap tests failed:\n".implode("\n", $errors)."\n");
	exit(1);
}

echo "All public holiday overlap tests passed.\n";
