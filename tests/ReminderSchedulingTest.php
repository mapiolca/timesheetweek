<?php
// CLI regression harness: mail delivery and persistence are simulated, no emails are sent.
if (PHP_SAPI !== 'cli') {
	exit(1);
}
define('DOL_VERSION', '24.0.0');
define('MAIN_DB_PREFIX', 'test_');
class CommonObject {}
class User {
	public $id = 7;
	public $firstname = 'Test';
	public $lastname = 'User';
	public function __construct($db) {}
	public function fetch($id) { return 1; }
}
class Cronjob {
	public $id = 12;
	public $entity = 3;
	public $datestart = 0;
	public $datenextrun = 0;
}
class CEmailTemplate {
	public $topic = 'Reminder';
	public $content = 'Please complete your timesheet';
	public $email_from = 'test@example.invalid';
	public function __construct($db) {}
	public function fetch($id) { return 1; }
}
class CMailFile {
	public static $sent = 0;
	public function __construct(...$args) {}
	public function sendfile() { self::$sent++; return true; }
}
class ReminderResult {
	public $rows;
	public function __construct($rows) { $this->rows = $rows; }
}
class DoliDB {
	public $failUpdate = false;
	public $missingJob = false;
	public $constants = array();
	public $nextRun = 0;
	public function query($sql) {
		if (strpos($sql, 'SELECT rowid FROM test_cronjob') === 0) {
			return new ReminderResult($this->missingJob ? array() : array((object) array('rowid' => 12)));
		}
		if (strpos($sql, 'UPDATE test_cronjob') === 0) {
			if ($this->failUpdate) { return false; }
			preg_match("/datenextrun = '([0-9]+)'/", $sql, $matches);
			$this->nextRun = (int) $matches[1];
			return true;
		}
		if (strpos($sql, 'GET_LOCK') !== false) {
			return new ReminderResult(array((object) array('lockstatus' => 1)));
		}
		if (strpos($sql, 'RELEASE_LOCK') !== false) { return true; }
		if (strpos($sql, 'SELECT DISTINCT') === 0) {
			return new ReminderResult(array((object) array('rowid' => 7, 'email' => 'test@example.invalid')));
		}
		if (strpos($sql, 'SELECT value') === 0) {
			preg_match("/name = '([^']+)'/", $sql, $matches);
			$key = '0:'.$matches[1];
			return new ReminderResult(isset($this->constants[$key]) ? array((object) array('value' => $this->constants[$key])) : array());
		}
		throw new RuntimeException('Unexpected query: '.$sql);
	}
	public function fetch_object($result) { return array_shift($result->rows); }
	public function free($result) {}
	public function escape($value) { return $value; }
	public function idate($value) { return (string) $value; }
	public function plimit($limit) { return ' LIMIT '.$limit; }
}
function isModEnabled($module) { return true; }
function GETPOST($key, $type) { return $_POST[$key] ?? ''; }
function dol_now() { return $GLOBALS['now']; }
function dol_time_plus_duree($time, $value, $unit) { return $time + $value * 604800; }
function dol_syslog($message, $level = 0) {}
function getDolGlobalInt($key, $default = 0) { return (int) getDolGlobalString($key, $default); }
function getDolGlobalString($key, $default = '') {
	global $db, $conf;
	return $db->constants[$conf->entity.':'.$key] ?? $default;
}
function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity) {
	if ($name === ($GLOBALS['failConst'] ?? '')) { return -1; }
	$db->constants[$entity.':'.$name] = $value;
	return 1;
}
function getCommonSubstitutionArray(...$args) { return array(); }
function complete_substitutions_array(&$substitutions, ...$args) {}
function make_substitutions($text, $substitutions) { return $text; }
function dol_string_nohtmltag($text) { return strip_tags($text); }
function dolGetFirstLastname($first, $last) { return $first.' '.$last; }
function tw_sql_timesheet_reminder_eligible_user($alias, $entity) { return 'u.entity = '.$entity; }

// Load the actual class without the Dolibarr bootstrap (dependencies above are test doubles).
$source = file_get_contents($argv[1] ?? __DIR__.'/../class/timesheetweek_reminder.class.php');
$source = substr($source, strpos($source, 'class TimesheetweekReminder extends'));
$source = preg_replace("/require_once DOL_DOCUMENT_ROOT\\.[^;]+;/", '', $source);
eval($source);
$langs = new class {
	public function loadLangs($catalogues) {}
	public function trans($key, ...$args) { return $key; }
};
$conf = (object) array('entity' => 3);
$user = new User(null);
$now = 1800000000;
function check($condition, $message) {
	if (!$condition) { throw new RuntimeException($message); }
}
function resetReminder($start) {
	global $db, $cronjob, $object, $conf, $failConst;
	$db = new DoliDB();
	$cronjob = new Cronjob();
	$object = null;
	$conf->entity = 3;
	$failConst = '';
	CMailFile::$sent = 0;
	foreach (array('ENABLED' => 1, 'STARTTIME' => $start, 'EMAIL_TEMPLATE' => 1) as $key => $value) {
		$db->constants['3:TIMESHEETWEEK_REMINDER_'.$key] = $value;
	}
	return new TimesheetweekReminder($db);
}

// Future date: do not send; repair a stale scheduler date. Request parameters cannot force delivery.
$reminder = resetReminder($now + 3600);
$_POST = array('forcerun' => 1, 'action' => 'confirm_execute', 'confirm' => 'yes');
check($reminder->run() === 0 && CMailFile::$sent === 0, 'Future reminder was sent');
check($cronjob->datenextrun === $now + 3600, 'Future date not repaired');

// A due run persists the next date and synchronizes the object later saved by native run_jobs().
$reminder = resetReminder($now);
check($reminder->run() === 0 && CMailFile::$sent === 1, 'Due reminder not sent once');
$next = $now + 604800;
check($db->nextRun === $next && $cronjob->datenextrun === $next && $cronjob->datestart === $next, 'CLI/URL loaded job remained stale');
$db->nextRun = $cronjob->datenextrun; // Simulate native completion save and reprogramming.
$now += 300;
check($reminder->run() === 0 && CMailFile::$sent === 1, 'Five minute retry sent again');

// Manual native runner uses $object; unrelated jobs/entities must remain untouched.
$reminder = resetReminder($now);
$object = new Cronjob();
$cronjob->entity = 2;
check($reminder->run() === 0 && $object->datenextrun > $now, 'Manual job date not synchronized');
check($cronjob->datenextrun === 0, 'Other entity modified');
$object->id = 99;
TimesheetweekReminder::updateCronStartTime($db, $now + 100);
check($object->datenextrun !== $now + 100, 'Unrelated job modified');

// Overdue schedule sends one reminder, skips historical occurrences and remains in the future.
$reminder = resetReminder($now - 4 * 604800);
check($reminder->run() === 0 && CMailFile::$sent === 1 && $cronjob->datenextrun > $now, 'Overdue schedule did not advance to future');

// A successful delivery followed by persistence failure must return an error without resending.
foreach (array('TIMESHEETWEEK_REMINDER_STARTTIME', 'TIMESHEETWEEK_REMINDER_LAST_SENT_STARTTIME', 'cron') as $failure) {
	$reminder = resetReminder($now);
	if ($failure === 'cron') { $db->failUpdate = true; } else { $failConst = $failure; }
	check($reminder->run() < 0 && CMailFile::$sent === 1, 'Persistence failure hidden');
	$failConst = '';
	$db->failUpdate = false;
	check($reminder->run() === 0 && CMailFile::$sent === 1 && $cronjob->datenextrun > $now, 'Persistence retry resent email or failed to repair schedule');
}
$reminder = resetReminder($now + 3600);
$db->missingJob = true;
check($reminder->run() < 0 && CMailFile::$sent === 0, 'Missing cron job hidden');

// Explicit administrator test remains available before the due date and leaves the schedule alone.
$reminder = resetReminder($now + 3600);
check($reminder->sendTest($user) === 0 && CMailFile::$sent === 1 && $cronjob->datenextrun === 0, 'Administrator test changed schedule');
echo "Reminder scheduling regression tests passed (simulated mail, database and scheduler).\n";
