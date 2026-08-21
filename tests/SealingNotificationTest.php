<?php

if (PHP_SAPI !== 'cli') {
	require_once __DIR__.'/../../../main.inc.php';
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

	dol_include_once('/timesheetweek/class/timesheetweek.class.php');
	dol_include_once('/timesheetweek/class/timesheetweeknotification.class.php');

	$langs->loadLangs(array('timesheetweek@timesheetweek', 'mails', 'users'));

	if (!isModEnabled('timesheetweek') || empty($user->admin)) {
		accessforbidden();
	}

	$timesheetId = GETPOSTINT('id');
	if ($timesheetId <= 0) {
		$timesheetId = 1;
	}

	$object = new TimesheetWeek($db);
	$fetchResult = $object->fetch($timesheetId);
	$content = array(
		'subject' => '',
		'body' => '',
		'reason' => '',
		'reason_label' => '',
		'template_id' => 0,
		'substitutions' => array(),
	);
	$actionUser = $user;
	$templateLabel = '';

	if ($fetchResult > 0) {
		$autoSealUserId = getDolGlobalInt('TIMESHEETWEEK_AUTOSEAL_USERID', 0, (int) $object->entity);
		if ($autoSealUserId > 0) {
			$autoSealUser = new User($db);
			if ($autoSealUser->fetch($autoSealUserId) > 0) {
				$actionUser = $autoSealUser;
			} else {
				setEventMessages($langs->trans('TimesheetWeekSealingPreviewFallbackUser', $autoSealUserId), null, 'warnings');
			}
		} else {
			setEventMessages($langs->trans('TimesheetWeekSealingPreviewNoConfiguredUser'), null, 'warnings');
		}

		$object->status = TimesheetWeek::STATUS_SEALED;
		$object->context = array(
			'trigger_reason' => 'seal',
			'timesheetweek_seal_origin' => 'auto',
			'action_user_id' => (int) $actionUser->id,
			'old_status' => TimesheetWeek::STATUS_APPROVED,
			'new_status' => TimesheetWeek::STATUS_SEALED,
		);

		$notification = new TimesheetWeekNotification($db);
		$content = $notification->getNativeNotificationContent($object, $langs, $actionUser, 3);
		$templateId = !empty($content['template_id']) ? (int) $content['template_id'] : 0;
		if ($templateId > 0) {
			$templateOptions = TimesheetWeekNotification::getEmailTemplateOptions($db, (int) $object->entity);
			$templateLabel = isset($templateOptions[$templateId]) ? (string) $templateOptions[$templateId] : '#'.$templateId;
		}
	} else {
		setEventMessages($langs->trans('TimesheetWeekSealingPreviewNotFound', $timesheetId), null, 'errors');
	}

	$title = $langs->trans('TimesheetWeekSealingNotificationPreview');
	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-timesheetweek page-test');

	print load_fiche_titre($title, '', 'email');
	print '<p class="opacitymedium">'.$langs->trans('TimesheetWeekSealingNotificationPreviewHelp').'</p>';

	print '<form method="GET" action="'.dol_escape_htmltag(dol_buildpath('/timesheetweek/tests/SealingNotificationTest.php', 1)).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('TimesheetWeekSealingPreviewParameters').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TimesheetWeekPreviewTimesheetId').'</td>';
	print '<td><input type="number" min="1" name="id" value="'.((int) $timesheetId).'">';
	print ' <input type="submit" class="button" value="'.$langs->trans('TimesheetWeekPreviewDisplay').'">';
	print '</td></tr>';
	print '</table>';
	print '</form>';

	if ($fetchResult > 0) {
		$templateDisplay = $templateLabel !== ''
			? dol_escape_htmltag($templateLabel).' <span class="opacitymedium">(#'.((int) $content['template_id']).')</span>'
			: $langs->trans('TimesheetWeekPreviewDefaultTemplate');

		print '<br>';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('TimesheetWeekSealingPreviewResult').'</td></tr>';
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TimesheetWeek').'</td><td>'.$object->getNomUrl(1).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekPreviewResolvedTemplate').'</td><td>'.$templateDisplay.'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekPreviewReason').'</td><td>'.dol_escape_htmltag((string) $content['reason_label']).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekPreviewActionUser').'</td><td>'.$actionUser->getNomUrl(-1).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('TimesheetWeekPreviewSubject').'</td><td>'.dol_escape_htmltag((string) $content['subject']).'</td></tr>';
		print '</table>';

		$previewDocument = '<!doctype html><html><head><meta charset="UTF-8"><base target="_blank">'
			.'<style>body{font-family:Arial,sans-serif;margin:24px;color:#222;line-height:1.45}img{max-width:100%;height:auto}</style>'
			.'</head><body>'.(string) $content['body'].'</body></html>';

		print '<br>';
		print '<div class="liste_titre">'.$langs->trans('TimesheetWeekPreviewBody').'</div>';
		print '<iframe class="centpercent" height="640" sandbox="allow-popups allow-popups-to-escape-sandbox" title="'.dol_escape_htmltag($langs->trans('TimesheetWeekPreviewBody')).'" srcdoc="'.dol_escape_htmltag($previewDocument).'"></iframe>';
	}

	llxFooter();
	$db->close();
	exit;
}

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
	$modeSelection = "PHP_SAPI === 'cli' ? 3 : 2";
	$urlBuilder = "dol_buildpath('/timesheetweek/timesheetweek_card.php', \$urlMode)";
	if (strpos($source, $modeSelection) === false || strpos($source, $urlBuilder) === false) {
		fwrite(STDERR, $path." must use Dolibarr's configured public URL for CLI notifications and the current host for web notifications.\n");
		exit(1);
	}
}

echo "Sealing notification test passed.\n";
