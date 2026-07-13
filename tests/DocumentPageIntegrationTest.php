<?php

$documentPageSource = file_get_contents(__DIR__.'/../timesheetweek_document.php');
if ($documentPageSource === false) {
	fwrite(STDERR, "Unable to read the TimesheetWeek document page.\n");
	exit(1);
}

if (strpos($documentPageSource, "'globalcard'") === false) {
	fwrite(STDERR, "The document page must expose the globalcard context.\n");
	exit(1);
}

if (strpos($documentPageSource, "executeHooks('doActions'") === false) {
	fwrite(STDERR, "The document page must execute doActions for UserNavHistory.\n");
	exit(1);
}

$descriptorSource = file_get_contents(__DIR__.'/../core/modules/modTimesheetWeek.class.php');
if ($descriptorSource === false) {
	fwrite(STDERR, "Unable to read the TimesheetWeek descriptor.\n");
	exit(1);
}

if (strpos($descriptorSource, "'/timesheetweek/js/timesheetweek.js.php'") === false) {
	fwrite(STDERR, "The TimesheetWeek JavaScript must be registered by the module descriptor.\n");
	exit(1);
}

if (strpos($descriptorSource, "'js' => (!empty(\$_SERVER['PHP_SELF'])") !== false) {
	fwrite(STDERR, "Module JavaScript registration must not depend on the activation request path.\n");
	exit(1);
}

echo "Document page integration test passed.\n";
