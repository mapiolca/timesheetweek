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
		$autoSealUserId = 0;
		$currentEntity = isset($conf->entity) ? (int) $conf->entity : 1;
		if ((int) $object->entity === $currentEntity) {
			$autoSealUserId = getDolGlobalInt('TIMESHEETWEEK_AUTOSEAL_USERID', 0);
		} elseif (method_exists($conf, 'setEntityValues')) {
			$entityConf = clone $conf;
			if ($entityConf->setEntityValues($db, (int) $object->entity) >= 0 && isset($entityConf->global) && is_object($entityConf->global) && isset($entityConf->global->TIMESHEETWEEK_AUTOSEAL_USERID)) {
				$autoSealUserId = (int) $entityConf->global->TIMESHEETWEEK_AUTOSEAL_USERID;
			}
		}
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
			'timesheetweek_notification_url' => timesheetweekBuildNotificationUrl($db, (int) $object->id, (int) $object->entity, 3),
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
$autoSealSource = file_get_contents(__DIR__.'/../class/timesheetweekautoseal.class.php');
$actionsSource = file_get_contents(__DIR__.'/../class/actions_timesheetweek.class.php');
$reminderSource = file_get_contents(__DIR__.'/../class/timesheetweek_reminder.class.php');
$substitutionSource = file_get_contents(__DIR__.'/../core/substitutions/functions_timesheetweek.lib.php');
$librarySource = file_get_contents(__DIR__.'/../lib/timesheetweek.lib.php');
$setupSource = file_get_contents(__DIR__.'/../admin/setup.php');
$descriptorSource = file_get_contents(__DIR__.'/../core/modules/modTimesheetWeek.class.php');
$frLangSource = file_get_contents(__DIR__.'/../langs/fr_FR/timesheetweek.lang');
$enLangSource = file_get_contents(__DIR__.'/../langs/en_US/timesheetweek.lang');

if ($objectSource === false || $notificationSource === false || $autoSealSource === false || $actionsSource === false || $reminderSource === false || $substitutionSource === false || $librarySource === false || $setupSource === false || $descriptorSource === false || $frLangSource === false || $enLangSource === false) {
	fwrite(STDERR, "Unable to read TimesheetWeek notification sources.\n");
	exit(1);
}

if (!defined('DOL_URL_ROOT')) {
	define('DOL_URL_ROOT', '/dolibarr');
}
if (!function_exists('dol_buildpath')) {
	function dol_buildpath($path, $type = 0)
	{
		if ($type === 3 && !empty($GLOBALS['timesheetweek_test_malformed_cli_url'])) {
			return 'https:/custom/'.ltrim((string) $path, '/');
		}
		$builtPath = DOL_URL_ROOT.'/custom/'.ltrim((string) $path, '/');
		return !empty($GLOBALS['timesheetweek_test_absolute_buildpath']) ? 'https://legacy-alt.example.com'.$builtPath : $builtPath;
	}
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		return $key === 'TIMESHEETWEEK_PUBLIC_URL_ROOT' ? 'https://erp.example.com/dolibarr' : $default;
	}
}
if (!defined('LOG_WARNING')) {
	define('LOG_WARNING', 4);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
		return true;
	}
}

require_once __DIR__.'/../lib/timesheetweek.lib.php';
if (
	timesheetweekNormalizePublicUrlRoot('https:/custom') !== ''
	|| timesheetweekNormalizePublicUrlRoot('/dolibarr/custom') !== ''
	|| timesheetweekNormalizePublicUrlRoot('ftp://erp.example.com') !== ''
	|| timesheetweekNormalizePublicUrlRoot("https://erp.example.com/\r\nBcc: hidden@example.com") !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://user:secret@erp.example.com') !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/') !== 'https://erp.example.com'
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/dolibarr/') !== 'https://erp.example.com/dolibarr'
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/custom') !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/?token=secret') !== ''
	|| timesheetweekNormalizePublicUrlRoot('https://erp.example.com/#fragment') !== ''
	|| timesheetweekBuildUrlFromPublicRoot('https://erp.example.com/dolibarr', '/timesheetweek/timesheetweek_card.php') !== 'https://erp.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php'
	|| timesheetweekBuildNotificationUrl(null, 471, 2, 3) !== 'https://erp.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php?id=471&entity=2'
	|| timesheetweekBuildNotificationUrl(null, 471, 2, 3, 'https://erp.example.com') !== 'https://erp.example.com/custom/timesheetweek/timesheetweek_card.php?id=471&entity=2'
	|| timesheetweekBuildNotificationUrl(null, 471, 2, 3, 'https://erp.example.com/subdir') !== 'https://erp.example.com/subdir/custom/timesheetweek/timesheetweek_card.php?id=471&entity=2'
	|| timesheetweekBuildNotificationUrl(null, 471, 2, 3, '') !== ''
) {
	fwrite(STDERR, "Notification public URL validation must reject incomplete or unsafe roots.\n");
	exit(1);
}
$timesheetweek_test_absolute_buildpath = true;
$urlFromAbsoluteBuildPath = timesheetweekBuildUrlFromPublicRoot('https://erp.example.com/dolibarr', '/timesheetweek/timesheetweek_card.php');
$timesheetweek_test_absolute_buildpath = false;
if ($urlFromAbsoluteBuildPath !== 'https://erp.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php') {
	fwrite(STDERR, "An absolute legacy dol_buildpath result must be reduced to its path before joining the configured root.\n");
	exit(1);
}

$conf = new class() {
	public $entity = 1;
	public $global;

	public function __construct()
	{
		$this->global = new stdClass();
		$this->global->TIMESHEETWEEK_PUBLIC_URL_ROOT = 'https://current.example.com/dolibarr';
	}

	public function setEntityValues($db, $entity)
	{
		$this->entity = (int) $entity;
		$this->global = new stdClass();
		$this->global->TIMESHEETWEEK_PUBLIC_URL_ROOT = (int) $entity === 2 ? 'https://owner.example.com/dolibarr' : '';
		return 1;
	}
};
$ownerEntityUrl = timesheetweekBuildNotificationUrl(new stdClass(), 471, 2, 3);
if ($ownerEntityUrl !== 'https://owner.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php?id=471&entity=2') {
	fwrite(STDERR, "Notification URLs for shared timesheets must use the owner entity configuration.\n");
	exit(1);
}

// CLI alternative roots may be relative: the native instance root must supply the host.
$dolibarr_main_url_root = 'https://instance.example.com/dolibarr';
$timesheetweek_test_malformed_cli_url = true;
$instanceUrl = timesheetweekBuildNotificationUrl(null, 483, 1, 3, '');
if ($instanceUrl !== 'https://instance.example.com/dolibarr/custom/timesheetweek/timesheetweek_card.php?id=483&entity=1') {
	fwrite(STDERR, "The configured native instance root must qualify relative CLI module paths.\n");
	exit(1);
}
if (timesheetweekBuildNotificationUrl(new stdClass(), 471, 2, 3) !== $ownerEntityUrl) {
	fwrite(STDERR, "The native instance root must not override the owner entity root.\n");
	exit(1);
}
foreach (array('https:/custom', '/custom', 'https://user:secret@instance.example.com', 'https://instance.example.com?x=1') as $invalidRoot) {
	$dolibarr_main_url_root = $invalidRoot;
	if (timesheetweekBuildNotificationUrl(null, 483, 1, 3, '') !== '') {
		fwrite(STDERR, "An invalid native instance root must not become an email link.\n");
		exit(1);
	}
}
unset($dolibarr_main_url_root);
unset($timesheetweek_test_malformed_cli_url);

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

if (
	strpos($autoSealSource, "\$timesheetLine->context['timesheetweek_notification_url'] = \$notificationUrl;") === false
	|| strpos($autoSealSource, "TimesheetWeekAutoSealPublicUrlWarning") === false
	|| strpos($autoSealSource, "TimesheetWeekAutoSealWarningSummary") === false
) {
	fwrite(STDERR, "Automatic sealing must keep an empty URL as a non-blocking cron warning and continue sealing.\n");
	exit(1);
}
$sealCallPosition = strpos($autoSealSource, "\$resultSeal = \$timesheetLine->seal(\$userAuto, 'auto');");
$urlWarningPosition = strpos($autoSealSource, "if (\$notificationUrl === '')", $sealCallPosition !== false ? $sealCallPosition : 0);
if ($sealCallPosition === false || $urlWarningPosition === false || $urlWarningPosition < $sealCallPosition) {
	fwrite(STDERR, "The missing-URL warning must only be counted after a successful seal.\n");
	exit(1);
}

if (
	strpos($notificationSource, "array_key_exists('timesheetweek_notification_url', \$object->context)") === false
	|| strpos($substitutionSource, "array_key_exists('timesheetweek_notification_url', \$object->context)") === false
	|| strpos($notificationSource, "\$substitutions['__TIMESHEETWEEK_ACCESS__'] = \$accessBlock;") === false
	|| strpos($substitutionSource, "'__TIMESHEETWEEK_ACCESS__' => 'TimesheetWeekSubstitutionAccess'") === false
	|| strpos($substitutionSource, "\$substitutionarray['__TIMESHEETWEEK_ACCESS__'] = \$accessBlock;") === false
) {
	fwrite(STDERR, "Notification substitutions must trust the validated cron context and expose the complete access block.\n");
	exit(1);
}

if (
	strpos($notificationSource, "'Accès direct : __TIMESHEETWEEK_URL_RAW__'") === false
	|| strpos($notificationSource, "'Direct access: __TIMESHEETWEEK_URL_RAW__'") === false
	|| substr_count($notificationSource, '$normalizedBody = preg_replace(') < 2
	|| strpos($notificationSource, "strpos(\$body, \$accessBlock) === false") === false
	|| strpos($actionsSource, "str_replace(\$legacyAccessPatterns, '__TIMESHEETWEEK_ACCESS__'") === false
) {
	fwrite(STDERR, "Historical URL-only templates must receive the account instruction without an orphan access label.\n");
	exit(1);
}

$legacyHrefBodies = array(
	'<p>Sealed</p><a class="button" href="__TIMESHEETWEEK_URL_RAW__">View</a>',
	'<p>Sealed</p><a class="button" href=__TIMESHEETWEEK_URL_RAW__>View</a>',
);
foreach ($legacyHrefBodies as $legacyHrefBody) {
	$legacyHrefBody = preg_replace(
		'~<a\b[^>]*\bhref\s*=\s*(?:"__TIMESHEETWEEK_URL_RAW__"|\'__TIMESHEETWEEK_URL_RAW__\'|__TIMESHEETWEEK_URL_RAW__)(?=[\s>])[^>]*>.*?</a>~is',
		'__TIMESHEETWEEK_ACCESS__',
		$legacyHrefBody
	);
	$legacyHrefBody = is_string($legacyHrefBody)
		? str_replace('__TIMESHEETWEEK_ACCESS__', 'Please view it directly from your Dolibarr user account.', $legacyHrefBody)
		: '';
	if (strpos($legacyHrefBody, 'href=') !== false || strpos($legacyHrefBody, '__TIMESHEETWEEK_URL_RAW__') !== false || substr_count($legacyHrefBody, 'Please view it directly from your Dolibarr user account.') !== 1) {
		fwrite(STDERR, "A historical HTML link with an empty raw URL must become exactly one account instruction.\n");
		exit(1);
	}
}

if (
	strpos($notificationSource, 'transnoentities($accessTranslationKey, $urlHtml)') === false
	|| strpos($substitutionSource, 'transnoentities($accessTranslationKey, $urlHtml)') === false
) {
	fwrite(STDERR, "A valid complete access block must contain the escaped clickable absolute URL.\n");
	exit(1);
}

if (
	strpos($frLangSource, 'TimesheetWeekNotificationAccountAccess = À consulter directement depuis votre compte utilisateur Dolibarr.') === false
	|| strpos($enLangSource, 'TimesheetWeekNotificationAccountAccess = Please view it directly from your Dolibarr user account.') === false
	|| strpos($frLangSource, 'TimesheetWeekTemplateSealBody = ') === false
	|| strpos($frLangSource, '__TIMESHEETWEEK_ACCESS__') === false
	|| strpos($enLangSource, '__TIMESHEETWEEK_ACCESS__') === false
) {
	fwrite(STDERR, "French and English templates must provide the expected access instruction.\n");
	exit(1);
}

if (
	strpos($actionsSource, '$useBundledRouter = $entity !== $currentEntity || $label === \'\';') === false
	|| strpos($actionsSource, 'self::syncNotificationEmailTemplateMirror($db, $visibleLabel, $sourceEntity, $mirrorAccessFallback)') === false
	|| strpos($actionsSource, '" AND entity IN (0, ".$entity.")"') === false
	|| strpos($actionsSource, "\$conf->global->{\$notifcode.'_TEMPLATE'} = self::getNotificationEmailTemplateMirrorLabel(\$visibleLabel, \$mirrorAccessFallback, \$sourceEntity);") === false
) {
	fwrite(STDERR, "Native template routing must isolate entities and use the bundled router in memory when needed.\n");
	exit(1);
}

$nativeHelperSources = $notificationSource.$autoSealSource.$reminderSource;
if (preg_match('/getDolGlobal(?:Int|String)\([^()\r\n]*,[^,()\r\n]*,[^,()\r\n]*\)/', $nativeHelperSources)) {
	fwrite(STDERR, "Dolibarr global helpers must not receive an unsupported entity argument.\n");
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
	|| strpos($setupSource, "setEventMessages(\$langs->trans('TimesheetWeekPublicUrlRootMissing'), null, 'errors');") !== false
	|| strpos($descriptorSource, 'timesheetweekInitializeNotificationPublicUrlRoot(') === false
) {
	fwrite(STDERR, "Automatic sealing must expose and validate the per-entity public URL without requiring one to seal.\n");
	exit(1);
}

echo "Sealing notification test passed.\n";
