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
$librarySource = file_get_contents(__DIR__.'/../lib/timesheetweek.lib.php');
$setupSource = file_get_contents(__DIR__.'/../admin/setup.php');
$descriptorSource = file_get_contents(__DIR__.'/../core/modules/modTimesheetWeek.class.php');

if ($objectSource === false || $notificationSource === false || $substitutionSource === false || $librarySource === false || $setupSource === false || $descriptorSource === false) {
	fwrite(STDERR, "Unable to read TimesheetWeek notification sources.\n");
	exit(1);
}

if (!defined('DOL_URL_ROOT')) {
	define('DOL_URL_ROOT', '/dolibarr');
}
if (!function_exists('dol_buildpath')) {
	function dol_buildpath($path, $type = 0)
	{
		return DOL_URL_ROOT.'/custom/'.ltrim((string) $path, '/');
	}
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		return $key === 'TIMESHEETWEEK_PUBLIC_URL_ROOT' ? 'https://erp.example.com/dolibarr' : $default;
	}
}

require_once __DIR__.'/../lib/timesheetweek.lib.php';
if (
	timesheetweekNormalizePublicUrlRoot('https:/custom') !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/') !== 'https://erp.example.com'
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/dolibarr/') !== 'https://erp.example.com/dolibarr'
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/custom') !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/?token=secret') !== ''
	|| timesheetweekBuildUrlFromPublicRoot('https://erp.example.com/dolibarr', '/timesheetweek/timesheetweek_card.php') !== 'https://erp.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php'
	|| timesheetweekBuildNotificationUrl(null, 471, 2, 3) !== 'https://erp.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php?id=471&entity=2'
) {
	fwrite(STDERR, "Notification public URL validation must reject incomplete or unsafe roots.\n");
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
	|| strpos($notificationSource, "\$substitutions['__USER_SIGNATURE__'] = '';") === false
	|| strpos($notificationSource, "\$substitutions['__MYCOMPANY_NAME__'] = '';") === false
	|| strpos($notificationSource, "\$signature = '';") === false
) {
	fwrite(STDERR, "Automatic sealing notifications must suppress user and trailing template signatures.\n");
	exit(1);
}

$emailSources = array(
	'class/timesheetweek.class.php' => $objectSource,
	'class/timesheetweeknotification.class.php' => $notificationSource,
	'core/substitutions/functions_timesheetweek.lib.php' => $substitutionSource,
);

foreach ($emailSources as $path => $source) {
	if (strpos($source, 'timesheetweekBuildNotificationUrl(') === false) {
		fwrite(STDERR, $path." must use the centralized validated notification URL builder.\n");
		exit(1);
	}
}

if (
	strpos($librarySource, "getDolGlobalString('TIMESHEETWEEK_PUBLIC_URL_ROOT', '')") === false
	|| strpos($librarySource, "timesheetweekGetMulticompanyPublicUrlRoot(\$db, \$entity)") === false
	|| strpos($librarySource, "timesheetweekIsAbsoluteHttpUrl(\$nativeUrl)") === false
	|| strpos($setupSource, "name=\"TIMESHEETWEEK_PUBLIC_URL_ROOT\"") === false
	|| strpos($descriptorSource, 'timesheetweekInitializeNotificationPublicUrlRoot(') === false
) {
	fwrite(STDERR, "Automatic sealing must expose, initialize and validate a per-entity public URL.\n");
	exit(1);
}

echo "Sealing notification test passed.\n";
