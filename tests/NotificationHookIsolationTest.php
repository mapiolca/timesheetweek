<?php

require_once __DIR__.'/../class/actions_timesheetweek.class.php';

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_WARNING')) {
	define('LOG_WARNING', 4);
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
		return true;
	}
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		return isset($GLOBALS['timesheetweek_test_template_label']) ? (string) $GLOBALS['timesheetweek_test_template_label'] : $default;
	}
}
if (!function_exists('dol_strlen')) {
	function dol_strlen($value)
	{
		return strlen((string) $value);
	}
}
if (!function_exists('dol_substr')) {
	function dol_substr($value, $start, $length = null)
	{
		return $length === null
			? substr((string) $value, (int) $start)
			: substr((string) $value, (int) $start, (int) $length);
	}
}

$conf = new stdClass();
$conf->entity = 3;
$conf->timesheetweek = new stdClass();
$conf->timesheetweek->enabled = 1;
$user = new stdClass();
$user->id = 7;

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

$routerDb = new class() {
	public $queries = array();

	public function escape($value)
	{
		return str_replace("'", "''", (string) $value);
	}

	public function query($sql)
	{
		$this->queries[] = $sql;
		if (strpos($sql, 'SELECT rowid, entity, module, type_template') !== 0) {
			return true;
		}

		$result = new stdClass();
		$result->index = 0;
		$result->rows = array();
		$sourceLabel = '';
		if (strpos($sql, "label = 'Notification TimesheetWeek'") !== false) {
			$sourceLabel = ActionsTimesheetweek::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL;
		} elseif (strpos($sql, "label = 'Valid custom template'") !== false) {
			$sourceLabel = 'Valid custom template';
		} elseif (strpos($sql, "label = 'Legacy raw template'") !== false) {
			$sourceLabel = 'Legacy raw template';
		} elseif (strpos($sql, "label = 'Legacy href template'") !== false) {
			$sourceLabel = 'Legacy href template';
		} elseif (strpos($sql, "label = 'Legacy unquoted href template'") !== false) {
			$sourceLabel = 'Legacy unquoted href template';
		} elseif (strpos($sql, "label = 'Notification body wrapper'") !== false) {
			$sourceLabel = 'Notification body wrapper';
		} elseif (strpos($sql, "label = 'Entity override template'") !== false) {
			$sourceLabel = 'Entity override template';
		} elseif (strpos($sql, "label = 'Private custom template'") !== false) {
			$sourceLabel = 'Private custom template';
		} elseif (strpos($sql, "label = 'Null module custom template'") !== false) {
			$sourceLabel = 'Null module custom template';
		} elseif (strpos($sql, "label = 'Changing languages template'") !== false) {
			$sourceLabel = 'Changing languages template';
		} elseif (!empty($GLOBALS['timesheetweek_test_long_label']) && strpos($sql, "label = '".$this->escape($GLOBALS['timesheetweek_test_long_label'])."'") !== false) {
			$sourceLabel = (string) $GLOBALS['timesheetweek_test_long_label'];
		}
		if ($sourceLabel !== '') {
			$sourceEntities = $sourceLabel === 'Entity override template' ? array(0, 3) : array(0);
			foreach ($sourceEntities as $sourceEntity) {
				$row = new stdClass();
				$row->rowid = $sourceEntity + 1;
				$row->entity = $sourceEntity;
				$row->module = $sourceLabel === 'Null module custom template' ? null : 'timesheetweek';
				$row->type_template = ActionsTimesheetweek::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE;
				$row->lang = 'en_US';
				$row->private = $sourceLabel === 'Private custom template' ? 1 : 0;
				$row->fk_user = $sourceLabel === 'Private custom template' ? 99 : null;
				$row->label = $sourceLabel;
				$row->position = 10;
				$row->defaultfortype = 0;
				$row->enabled = '';
				$row->active = 1;
				$row->email_from = '';
				$row->email_to = '';
				$row->email_tocc = '';
				$row->email_tobcc = '';
				$row->topic = '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__';
				$row->joinfiles = 0;
				if ($sourceLabel === 'Legacy raw template') {
					$row->content = 'Accès direct : __TIMESHEETWEEK_URL_RAW__';
				} elseif ($sourceLabel === 'Legacy href template') {
					$row->content = '<a href="__TIMESHEETWEEK_URL_RAW__">View</a>';
				} elseif ($sourceLabel === 'Legacy unquoted href template') {
					$row->content = '<a href=__TIMESHEETWEEK_URL_RAW__>View</a>';
				} elseif ($sourceLabel === 'Notification body wrapper') {
					$row->content = '<section>__TIMESHEETWEEK_NOTIFICATION_BODY__</section>';
				} elseif ($sourceLabel === 'Entity override template') {
					$row->content = $sourceEntity === 3 ? 'Entity override content' : 'Global override content';
				} else {
					$row->content = ActionsTimesheetweek::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_BODY;
				}
				$row->content_lines = null;
				$result->rows[] = $row;
				if ($sourceLabel === ActionsTimesheetweek::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL) {
					$neutralRow = clone $row;
					$neutralRow->rowid = 2;
					$neutralRow->lang = '';
					$result->rows[] = $neutralRow;
				}
				if ($sourceLabel === 'Changing languages template' && !empty($GLOBALS['timesheetweek_test_include_french'])) {
					$frenchRow = clone $row;
					$frenchRow->rowid = 3;
					$frenchRow->lang = 'fr_FR';
					$frenchRow->content = 'French language content';
					$result->rows[] = $frenchRow;
				}
			}
		}

		return $result;
	}

	public function fetch_object($result)
	{
		if (!is_object($result) || !isset($result->rows, $result->index) || $result->index >= count($result->rows)) {
			return false;
		}

		return $result->rows[$result->index++];
	}

	public function free($result)
	{
		return true;
	}
};

$conf->global = new stdClass();
$timesheetweek_test_template_label = '';
$cleanupMethod = new ReflectionMethod(ActionsTimesheetweek::class, 'cleanupObsoleteNotificationMirrors');
$cleanupMethod->setAccessible(true);
$routerDb->queries = array();
$cleanupResult = $cleanupMethod->invoke(null, $routerDb);
$cleanupQueries = implode("\n", $routerDb->queries);
if ($cleanupResult !== 1 || strpos($cleanupQueries, 'AND (entity <> 0 OR label IS NULL') === false) {
	fwrite(STDERR, "Legacy unscoped router mirrors must be removed from local entities.\n");
	exit(1);
}

$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$routerInitializationQueries = implode("\n", $routerDb->queries);
$expectedRouterMirror = 'Notification TimesheetWeek [timesheetweek_send]';
if (
	$routerResult !== 1
	|| !isset($conf->global->TIMESHEETWEEK_SEAL_TEMPLATE)
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== $expectedRouterMirror
	|| strpos($routerInitializationQueries, "lang = ''") === false
) {
	fwrite(STDERR, "An empty native template selection must use the bundled router in memory.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Missing custom template';
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== $expectedRouterMirror) {
	fwrite(STDERR, "An invalid native template selection must fall back to the bundled router in memory.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Valid custom template';
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Valid custom template [timesheetweek_send:entity3]') {
	fwrite(STDERR, "A valid native template selection must remain selected through its in-memory mirror.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Valid custom template';
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_APPROVE', 2);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_APPROVE_TEMPLATE !== $expectedRouterMirror) {
	fwrite(STDERR, "A shared timesheet must first route through the bundled global template.\n");
	exit(1);
}
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_APPROVE', 3);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_APPROVE_TEMPLATE !== 'Valid custom template [timesheetweek_send:entity3]') {
	fwrite(STDERR, "A local timesheet processed after a shared one must recover the configured visible template.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Entity override template';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$entityOverrideQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Entity override template [timesheetweek_send:entity3]'
	|| strpos($entityOverrideQueries, 'Entity override content') === false
	|| strpos($entityOverrideQueries, 'Global override content') !== false
) {
	fwrite(STDERR, "An entity template must override a homonymous global template for the same language.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Valid custom template';
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 2);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== $expectedRouterMirror) {
	fwrite(STDERR, "A shared timesheet must use the global bundled router in the execution entity.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Legacy raw template';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$mirrorQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Legacy raw template [timesheetweek_send:entity3]'
	|| strpos($mirrorQueries, 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'c_email_templates') === false
	|| strpos($mirrorQueries, '__TIMESHEETWEEK_ACCESS__') === false
	|| strpos($mirrorQueries, 'Accès direct : __TIMESHEETWEEK_URL_RAW__') !== false
) {
	fwrite(STDERR, "A legacy raw-URL template must be adapted only in its hidden mirror.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Private custom template';
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
if ($routerResult !== 1 || $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== $expectedRouterMirror) {
	fwrite(STDERR, "A private template owned by another user must fall back to the bundled router.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Null module custom template';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$nullModuleQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Null module custom template [timesheetweek_send:entity3]'
	|| strpos($nullModuleQueries, "(module IS NULL OR module = '' OR module = 'timesheetweek')") === false
) {
	fwrite(STDERR, "A native custom template without a module field must remain selectable.\n");
	exit(1);
}

if (strpos($mirrorQueries, 'AND entity IN (0, 3)') === false) {
	fwrite(STDERR, "A selected template lookup must exclude homonymous templates from other entities.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Legacy href template';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$mirrorQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| strpos($mirrorQueries, '<a href="__TIMESHEETWEEK_URL_RAW__">View</a>') === false
	|| strpos($mirrorQueries, 'href="__TIMESHEETWEEK_ACCESS__"') !== false
) {
	fwrite(STDERR, "A valid legacy href must keep the raw URL substitution in the hidden mirror.\n");
	exit(1);
}

$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3, true);
$mirrorQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Legacy href template [timesheetweek_send:entity3:no_url]'
	|| strpos($mirrorQueries, '__TIMESHEETWEEK_ACCESS__') === false
	|| strpos($mirrorQueries, '<a href="__TIMESHEETWEEK_URL_RAW__">View</a>') !== false
) {
	fwrite(STDERR, "An invalid legacy href must use the dedicated no-URL mirror and account instruction.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Legacy unquoted href template';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3, true);
$mirrorQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| strpos($mirrorQueries, '__TIMESHEETWEEK_ACCESS__') === false
	|| strpos($mirrorQueries, '<a href=__TIMESHEETWEEK_URL_RAW__>View</a>') !== false
) {
	fwrite(STDERR, "An unquoted historical raw-URL href must become the account instruction.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Notification body wrapper';
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3, true);
$mirrorQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE !== 'Notification body wrapper [timesheetweek_send:entity3:no_url]'
	|| strpos($mirrorQueries, '<section>__TIMESHEETWEEK_NOTIFICATION_BODY__</section>') === false
	|| strpos($mirrorQueries, '__TIMESHEETWEEK_ACCESS__') !== false
) {
	fwrite(STDERR, "A wrapper around the native notification body must not duplicate the account instruction.\n");
	exit(1);
}

$timesheetweek_test_template_label = 'Changing languages template';
$timesheetweek_test_include_french = true;
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
if ($routerResult !== 1 || strpos(implode("\n", $routerDb->queries), 'French language content') === false) {
	fwrite(STDERR, "The first synchronization must create every effective language mirror.\n");
	exit(1);
}
$timesheetweek_test_include_french = false;
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$languageCleanupQueries = implode("\n", $routerDb->queries);
if (
	$routerResult !== 1
	|| strpos($languageCleanupQueries, "AND (lang IS NULL OR lang NOT IN ('en_US'))") === false
	|| strpos($languageCleanupQueries, 'French language content') !== false
) {
	fwrite(STDERR, "A stale language mirror must be deleted when its source is no longer effective.\n");
	exit(1);
}

$timesheetweek_test_long_label = str_repeat('É', 180);
$timesheetweek_test_template_label = $timesheetweek_test_long_label;
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$longMirrorLabel = $conf->global->TIMESHEETWEEK_SEAL_TEMPLATE;
if (
	$routerResult !== 1
	|| strlen($longMirrorLabel) > 180
	|| preg_match('//u', $longMirrorLabel) !== 1
	|| preg_match('~:h[0-9a-f]{12}\]$~', $longMirrorLabel) !== 1
) {
	fwrite(STDERR, "A long UTF-8 template label must produce a valid mirror with byte-based Dolibarr fallbacks.\n");
	exit(1);
}
$mirrorLabelMethod = new ReflectionMethod(ActionsTimesheetweek::class, 'getNotificationEmailTemplateMirrorLabel');
$mirrorLabelMethod->setAccessible(true);
$collisionPrefix = str_repeat('É', 180);
$firstCollisionLabel = $mirrorLabelMethod->invoke(null, $collisionPrefix.'A', false, 3);
$secondCollisionLabel = $mirrorLabelMethod->invoke(null, $collisionPrefix.'B', false, 3);
if ($firstCollisionLabel === $secondCollisionLabel) {
	fwrite(STDERR, "Distinct long template labels must not share a hidden mirror label.\n");
	exit(1);
}
$routerDb->queries = array();
$routerResult = ActionsTimesheetweek::syncSelectedNotificationEmailTemplateMirror($routerDb, 'TIMESHEETWEEK_SEAL', 3);
$longLabelSecondPassQueries = implode("\n", $routerDb->queries);
if ($routerResult !== 1 || strpos($longLabelSecondPassQueries, "label = '".$timesheetweek_test_long_label."'") === false) {
	fwrite(STDERR, "A repeated notification must reuse the complete visible template label after mirror truncation.\n");
	exit(1);
}

echo "Notification hook isolation test passed.\n";
