<?php

$objectSource = file_get_contents(__DIR__.'/../class/timesheetweek.class.php');
$notificationSource = file_get_contents(__DIR__.'/../class/timesheetweeknotification.class.php');
$substitutionSource = file_get_contents(__DIR__.'/../core/substitutions/functions_timesheetweek.lib.php');

if ($objectSource === false || $notificationSource === false || $substitutionSource === false) {
	fwrite(STDERR, "Unable to read TimesheetWeek notification sources.\n");
	exit(1);
}

if (strpos($objectSource, "\$this->context['action_user_id'] = (int) \$user->id;") === false) {
	fwrite(STDERR, "TimesheetWeek triggers must carry the business action user identifier.\n");
	exit(1);
}

if (strpos($notificationSource, '$actionUser = $this->resolveActionUser($object, $actionUser);') === false) {
	fwrite(STDERR, "Native notifications must resolve the business action user from trigger context.\n");
	exit(1);
}

if (strpos($objectSource, "\$this->context['timesheetweek_seal_origin'] = (\$origin === 'auto' ? 'auto' : 'manual');") === false) {
	fwrite(STDERR, "TimesheetWeek sealing triggers must carry the sealing origin.\n");
	exit(1);
}

if (
	strpos($notificationSource, "\$reason === 'seal'") === false
	|| strpos($notificationSource, "\$object->context['timesheetweek_seal_origin'] === 'auto'") === false
	|| strpos($notificationSource, "\$substitutions['__SENDEREMAIL_SIGNATURE__'] = '';") === false
) {
	fwrite(STDERR, "Automatic sealing notifications must suppress the sender user signature.\n");
	exit(1);
}

$emailSources = array(
	'class/timesheetweek.class.php' => $objectSource,
	'class/timesheetweeknotification.class.php' => $notificationSource,
	'core/substitutions/functions_timesheetweek.lib.php' => $substitutionSource,
);

foreach ($emailSources as $path => $source) {
	$expected = "dol_buildpath('/timesheetweek/timesheetweek_card.php', 2)";
	if (strpos($source, $expected) === false) {
		fwrite(STDERR, $path." must build the TimesheetWeek email URL from the host resolved by Dolibarr.\n");
		exit(1);
	}

	$forbidden = "dol_buildpath('/timesheetweek/timesheetweek_card.php', 3)";
	if (strpos($source, $forbidden) !== false) {
		fwrite(STDERR, $path." must not use the configured external host for the TimesheetWeek email URL.\n");
		exit(1);
	}
}

echo "Sealing notification test passed.\n";
