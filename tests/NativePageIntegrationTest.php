<?php

$cardSource = file_get_contents(__DIR__.'/../timesheetweek_card.php');
if ($cardSource === false) {
	fwrite(STDERR, "Unable to read the TimesheetWeek card source.\n");
	exit(1);
}

if (strpos($cardSource, "'timesheetweekcard','globalcard'") === false) {
	fwrite(STDERR, "The TimesheetWeek card must expose the globalcard hook context.\n");
	exit(1);
}

if (strpos($cardSource, "executeHooks('doActions'") === false) {
	fwrite(STDERR, "The TimesheetWeek card must execute doActions for cross-module integrations.\n");
	exit(1);
}

$javascriptSource = file_get_contents(__DIR__.'/../js/timesheetweek.js.php');
if ($javascriptSource === false) {
	fwrite(STDERR, "Unable to read the TimesheetWeek JavaScript source.\n");
	exit(1);
}

if (strpos($javascriptSource, 'select[name^="constvalue_TIMESHEETWEEK_"][name$="_TEMPLATE"]') === false) {
	fwrite(STDERR, "The Dolibarr v23 notification template selector is not handled.\n");
	exit(1);
}

echo "Native page integration test passed.\n";
