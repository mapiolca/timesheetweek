<?php

require_once __DIR__.'/../class/actions_timesheetweek.class.php';

$conf = new stdClass();
$conf->entity = 3;
$conf->timesheetweek = new stdClass();
$conf->timesheetweek->enabled = 1;

$db = new stdClass();
$hookmanager = new stdClass();
$hookmanager->resArray = array();
$object = new stdClass();
$action = 'USERNAVHISTORY_UPDATE';

$hooks = new ActionsTimesheetweek($db);
$result = $hooks->notifsupported(array(), $object, $action, $hookmanager);

if ($result !== 0) {
	fwrite(STDERR, "notifsupported must return 0.\n");
	exit(1);
}

if (empty($hooks->results['arrayofnotifsupported']) || !is_array($hooks->results['arrayofnotifsupported'])) {
	fwrite(STDERR, "notifsupported did not expose the TimesheetWeek notification events.\n");
	exit(1);
}

echo "Notification hook isolation test passed.\n";
