<?php
/* Copyright (C) 2025		Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
/*
if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', 1);
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', 1);
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', 1);
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', 1);
}
if (!defined('NOREQUIRESUBPERMS')) {
	define('NOREQUIRESUBPERMS', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
*/
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/cron/class/cronjob.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');


/**
 * Cron helper used to send weekly reminders.
 */
class TimesheetweekReminder extends CommonObject
{
	public $db;
	public $error;
	public $errors = array();
	public $output;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}
	/**
	* Run cron job to send weekly reminder emails.
	*
	* @param DoliDB|null $dbInstance    Optional database handler override
	* @param int         $limit         Optional limit for recipients
	* @param int         $forcerun      Force execution (1) or use normal scheduling (0)
	* @param array       $targetUserIds Limit execution to specific user ids when provided
	* @return int                        <0 if KO, >=0 if OK (number of emails sent)
	*/
	public function run($dbInstance = null, $limit = 0, $forcerun = 0, array $targetUserIds = array())
	{
		global $db, $conf, $user, $langs;

		if ($dbInstance instanceof DoliDB) {
			$this->db = $dbInstance;
		} elseif (!empty($db) && $db instanceof DoliDB) {
			$this->db = $db;
		}

		if (empty($this->db)) {
			$this->error = $langs->trans('ErrorNoDatabase');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return 0;
		}

		$db = $this->db;

		$langs->loadLangs(array('timesheetweek@timesheetweek'));

		$forceExecution = !empty($forcerun);
		if (!$forceExecution) {
			$forceExecution = ((int) GETPOST('forcerun', 'int') > 0);
		}
		if (!$forceExecution) {
			$action = GETPOST('action', 'aZ09');
			$confirm = GETPOST('confirm', 'alpha');
			if ($action === 'confirm_execute' && $confirm === 'yes') {
				$forceExecution = true;
			}
		}

		$useCoreTemplateClass = (version_compare(DOL_VERSION, '23.0.0', '>=') || file_exists(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplate.class.php')) ? true : false;
		if ($useCoreTemplateClass) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/cemailtemplate.class.php';
			dol_syslog(__METHOD__.' use core CEmailTemplate', LOG_DEBUG);
		} else {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
			dol_syslog(__METHOD__.' use ModelMail', LOG_DEBUG);
		}

		$reminderEnabled = getDolGlobalInt('TIMESHEETWEEK_REMINDER_ENABLED', 0, $conf->entity);
		if (empty($reminderEnabled) && empty($forceExecution)) {
			dol_syslog('TimesheetweekReminder: reminder disabled', LOG_INFO);
			$this->output = $langs->trans('TimesheetWeekReminderDisabled');
			return 0;
		}

		$reminderStartValue = getDolGlobalString('TIMESHEETWEEK_REMINDER_STARTTIME', '', $conf->entity);
		$reminderStartTimestamp = 0;
		if ($reminderStartValue !== '') {
			if (is_numeric($reminderStartValue)) {
				$reminderStartTimestamp = (int) $reminderStartValue;
			} else {
				$reminderStartTimestamp = dol_stringtotime($reminderStartValue, 0);
			}
		}
		if (empty($reminderStartTimestamp)) {
			$this->error = $langs->trans('TimesheetWeekReminderStartTimeInvalid');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return 0;
		}

		if (empty($forceExecution) && $this->isReminderStartAlreadySent($reminderStartTimestamp)) {
			$this->output = $langs->trans('TimesheetWeekReminderAlreadySent');
			dol_syslog(__METHOD__.' '.$this->output.' entity='.(int) $conf->entity.' start='.(int) $reminderStartTimestamp, LOG_INFO);
			return 0;
		}

		$templateId = getDolGlobalInt('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 0, $conf->entity);
		if (empty($templateId)) {
			$this->error = $langs->trans('TimesheetWeekReminderTemplateMissing');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_WARNING);
			return 0;
		}

		$emailTemplate = null;
		$templateFetch = 0;
		if ($useCoreTemplateClass && class_exists('CEmailTemplate')) {
			$emailTemplate = new CEmailTemplate($db);
			if (method_exists($emailTemplate, 'apifetch')) {
				$templateFetch = $emailTemplate->apifetch($templateId);
			} else {
				$templateFetch = $emailTemplate->fetch($templateId);
			}
			if ($templateFetch > 0) {
				dol_syslog(__METHOD__.' template fetched via CEmailTemplate id='.$templateId, LOG_DEBUG);
			}
		} elseif (!$useCoreTemplateClass && class_exists('FormMail')) {
			$formMail = new FormMail($db);
			$emailTemplate = $formMail->getEMailTemplate($db, 'actioncomm_send', $user, $langs, $templateId, 1);
			if ($emailTemplate instanceof ModelMail && $emailTemplate->id > 0) {
				$templateFetch = 1;
				dol_syslog(__METHOD__.' template fetched via ModelMail id='.$templateId, LOG_DEBUG);
			}
		}

		if (empty($emailTemplate) || $templateFetch <= 0) {
			$this->error = $langs->trans('TimesheetWeekReminderTemplateMissing');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return 0;
		}

		$subject = !empty($emailTemplate->topic) ? $emailTemplate->topic : $emailTemplate->label;
		$body = !empty($emailTemplate->content) ? $emailTemplate->content : '';

		if (empty($subject) || empty($body)) {
			$this->error = $langs->trans('TimesheetWeekReminderTemplateMissing');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_WARNING);
			return 0;
		}

		$from = (!empty($emailTemplate->email_from) ? $emailTemplate->email_from : '');
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		}
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}

		$substitutions = getCommonSubstitutionArray($langs, 0, null, null, null);
		complete_substitutions_array($substitutions, $langs, null);

		$excludedUsersString = getDolGlobalString('TIMESHEETWEEK_REMINDER_EXCLUDED_USERS', '', $conf->entity);
		$excludedUsers = array();
		if ($excludedUsersString !== '') {
			$excludedUsers = array_filter(array_map('intval', explode(',', $excludedUsersString)));
		}

		$reminderPeriodKey = $this->getReminderPeriodKey($reminderStartTimestamp);
		$lockName = '';
		$lockAcquired = false;
		if (empty($forceExecution)) {
			$lockName = $this->getReminderLockName($reminderPeriodKey);
			$lockAcquired = $this->acquireMysqlLock($lockName);
			if (!$lockAcquired) {
				$this->output = $langs->trans('TimesheetWeekReminderAlreadyRunning');
				dol_syslog(__METHOD__.' '.$this->output.' lock='.$lockName, LOG_INFO);
				return 0;
			}

			if ($this->isReminderStartAlreadySent($reminderStartTimestamp)) {
				$this->releaseMysqlLock($lockName);
				$this->output = $langs->trans('TimesheetWeekReminderAlreadySent');
				dol_syslog(__METHOD__.' '.$this->output.' entity='.(int) $conf->entity.' start='.(int) $reminderStartTimestamp, LOG_INFO);
				return 0;
			}
		}

		$sql = 'SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.email';
		$sql .= ' FROM '.MAIN_DB_PREFIX."user AS u";
		$sql .= " WHERE ".tw_sql_timesheet_reminder_eligible_user('u', (int) $conf->entity);
		if (!empty($excludedUsers)) {
			$sql .= ' AND u.rowid NOT IN ('.implode(',', $excludedUsers).')';
		}
		if (!empty($targetUserIds)) {
			$sql .= ' AND u.rowid IN ('.implode(',', array_map('intval', $targetUserIds)).')';
		}
		$sql .= ' ORDER BY u.rowid ASC';
		if ($limit > 0) {
			$sql .= $db->plimit((int) $limit);
		}

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			$this->output = $langs->trans('TimesheetWeekReminderSendFailed', $this->error);
			dol_syslog($this->error, LOG_ERR);
			if ($lockAcquired) {
				$this->releaseMysqlLock($lockName);
			}
			return 0;
		}

		$emailsSent = 0;
		$errors = 0;
		$recipientKeys = array();

		while ($obj = $db->fetch_object($resql)) {
			$recipient = trim($obj->email);
			if (empty($recipient)) {
				continue;
			}

			$recipientKey = $this->normalizeRecipientEmail($recipient);
			if (isset($recipientKeys[$recipientKey])) {
				dol_syslog(__METHOD__.' skip duplicate recipient in current run recipient_hash='.$this->getRecipientLogHash($recipientKey), LOG_INFO);
				continue;
			}
			$recipientKeys[$recipientKey] = true;

			if (empty($forceExecution) && $this->wasReminderRecipientSent($recipientKey, $reminderPeriodKey)) {
				dol_syslog($langs->trans('TimesheetWeekReminderRecipientAlreadySent').' recipient_hash='.$this->getRecipientLogHash($recipientKey), LOG_INFO);
				continue;
			}

			$recipientUser = new User($db);
			$fetchUser = $recipientUser->fetch($obj->rowid);
			if ($fetchUser < 0) {
				dol_syslog($recipientUser->error, LOG_ERR);
				$errors++;
				continue;
			}

			$userSubstitutions = $substitutions;
			$userSubstitutions['__USER_FIRSTNAME__'] = $recipientUser->firstname;
			$userSubstitutions['__USER_LASTNAME__'] = $recipientUser->lastname;
			$userSubstitutions['__USER_FULLNAME__'] = dolGetFirstLastname($recipientUser->firstname, $recipientUser->lastname);
			$userSubstitutions['__USER_EMAIL__'] = $recipient;
			complete_substitutions_array($userSubstitutions, $langs, null, $recipientUser);

			$preparedSubject = make_substitutions($subject, $userSubstitutions);
			$preparedBody = make_substitutions($body, $userSubstitutions);

			$preparedSubject = dol_string_nohtmltag(html_entity_decode($preparedSubject, ENT_QUOTES, 'UTF-8'));
			$preparedBodyHtml = html_entity_decode($preparedBody, ENT_QUOTES, 'UTF-8');
			$isHtmlBody = (!empty($preparedBodyHtml) && preg_match('/<[^>]+>/', $preparedBodyHtml)) ? 1 : 0;
			$preparedBodyFinal = $isHtmlBody ? $preparedBodyHtml : dol_string_nohtmltag($preparedBodyHtml);

			$mail = new CMailFile($preparedSubject, $recipient, $from, $preparedBodyFinal, array(), array(), array(), '', '', 0, $isHtmlBody, '', '', '', 'utf-8');
			$resultSend = $mail->sendfile();
			if ($resultSend) {
				$emailsSent++;
				dol_syslog($langs->trans('TimesheetWeekReminderSendSuccess', $recipient), LOG_INFO);
				if (empty($forceExecution) && !$this->markReminderRecipientSent($recipientKey, $reminderPeriodKey)) {
					dol_syslog(__METHOD__.' failed to mark reminder recipient recipient_hash='.$this->getRecipientLogHash($recipientKey).' period='.$reminderPeriodKey, LOG_WARNING);
				}
			} else {
				dol_syslog($langs->trans('TimesheetWeekReminderSendFailed', $recipient), LOG_ERR);
				$errors++;
			}
		}

		$db->free($resql);

		if ($errors > 0) {
			$this->error = $langs->trans('TimesheetWeekReminderSendFailed', $errors);
			$this->output = $this->error;
			dol_syslog(__METHOD__.' errors='.$errors, LOG_ERR);
			if ($lockAcquired) {
				$this->releaseMysqlLock($lockName);
			}
			return -$errors;
		}

		if (empty($forceExecution)) {
			$this->markReminderStartSent($reminderStartTimestamp);
			$this->scheduleNextStart($reminderStartTimestamp);
		}

		$this->output = $langs->trans('TimesheetWeekReminderSendSuccess', $emailsSent);
		dol_syslog(__METHOD__.' sent='.$emailsSent, LOG_DEBUG);

		if ($lockAcquired) {
			$this->releaseMysqlLock($lockName);
		}

		return 0;
	}
		/**
		* Send a reminder test email to the current user using the configured template.
		*
		* @param User $user Current user
		* @return int       <0 if KO, >=0 if OK (number of emails sent)
		*/
		public function sendTest(User $user)
		{
			return $this->run($this->db, 1, 1, array((int) $user->id));
		}
		
		/**
		* Update the cron job start date using the provided timestamp.
		*
		* @param DoliDB     $db              Database handler
		* @param int        $startTimestamp  Next launch timestamp
		* @param User|null  $currentUser     User performing the update
		* @return int                        >0 if updated, 0 if cron missing, <0 on error
		*/
		public static function updateCronStartTime(DoliDB $db, int $startTimestamp, $currentUser = null)
		{
			global $conf, $user;
			
			$sql = 'SELECT COUNT(rowid) as nb';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'cronjob';
			$sql .= " WHERE jobtype = 'method'";
			$sql .= " AND classesname = '/timesheetweek/class/timesheetweek_reminder.class.php'";
			$sql .= " AND objectname = 'TimesheetweekReminder'";
			$sql .= " AND methodename = 'run'";
			$sql .= ' AND entity = '.((int) $conf->entity);
			$resql = $db->query($sql);
			if (!$resql) {
				return -1;
			}
			$obj = $db->fetch_object($resql);
			$db->free($resql);
			if (empty($obj) || (int) $obj->nb <= 0) {
				return 0;
			}
			
			$userForUpdate = $currentUser;
			if (empty($userForUpdate) && !empty($user) && $user instanceof User) {
				$userForUpdate = $user;
			}
			
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'cronjob SET';
			$sql .= " datestart = '".$db->idate($startTimestamp)."'";
			$sql .= ", datenextrun = '".$db->idate($startTimestamp)."'";
			if (!empty($userForUpdate) && $userForUpdate instanceof User) {
				$sql .= ', fk_user_mod = '.((int) $userForUpdate->id);
			}
			$sql .= " WHERE jobtype = 'method'";
			$sql .= " AND classesname = '/timesheetweek/class/timesheetweek_reminder.class.php'";
			$sql .= " AND objectname = 'TimesheetweekReminder'";
			$sql .= " AND methodename = 'run'";
			$sql .= ' AND entity = '.((int) $conf->entity);

			return $db->query($sql) ? 1 : -1;
		}
		
		/**
		* Store the next reminder start date based on the provided timestamp.
		*
		* @param int $currentStartTimestamp Current start timestamp
		* @return int                       Next start timestamp
		*/
		private function scheduleNextStart($currentStartTimestamp)
		{
			global $db, $conf;
			
			$nextStartTimestamp = dol_time_plus_duree($currentStartTimestamp, 1, 'w');
			dolibarr_set_const($db, 'TIMESHEETWEEK_REMINDER_STARTTIME', (string) $nextStartTimestamp, 'chaine', 0, '', $conf->entity);
			self::updateCronStartTime($db, $nextStartTimestamp);
			
			return $nextStartTimestamp;
		}

		/**
		 * Build a weekly reminder period key shared by all entities.
		 *
		 * @param int $startTimestamp Current reminder start timestamp
		 * @return string             Period key
		 */
		private function getReminderPeriodKey($startTimestamp)
		{
			return date('o-\WW', (int) $startTimestamp);
		}

		/**
		 * Build the MySQL advisory lock name for the reminder period.
		 *
		 * @param string $periodKey Reminder period key
		 * @return string           Lock name
		 */
		private function getReminderLockName($periodKey)
		{
			return 'timesheetweek_reminder_'.$periodKey;
		}

		/**
		 * Acquire a MySQL advisory lock without waiting.
		 *
		 * @param string $lockName Lock name
		 * @return bool            True when acquired
		 */
		private function acquireMysqlLock($lockName)
		{
			$sql = "SELECT GET_LOCK('".$this->db->escape($lockName)."', 0) as lockstatus";
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_WARNING);
				return false;
			}
			$obj = $this->db->fetch_object($resql);
			$this->db->free($resql);

			return (!empty($obj) && (int) $obj->lockstatus === 1);
		}

		/**
		 * Release a MySQL advisory lock.
		 *
		 * @param string $lockName Lock name
		 * @return void
		 */
		private function releaseMysqlLock($lockName)
		{
			if ($lockName === '') {
				return;
			}

			$sql = "SELECT RELEASE_LOCK('".$this->db->escape($lockName)."')";
			$this->db->query($sql);
		}

		/**
		 * Normalize recipient email for deduplication.
		 *
		 * @param string $email Email address
		 * @return string       Normalized email
		 */
		private function normalizeRecipientEmail($email)
		{
			return strtolower(trim((string) $email));
		}

		/**
		 * Build the global marker constant for a recipient.
		 *
		 * @param string $recipientKey Normalized recipient email
		 * @return string              Constant name
		 */
		private function getRecipientMarkerConstName($recipientKey)
		{
			return 'TIMESHEETWEEK_REMINDER_SENT_'.strtoupper(hash('sha256', $recipientKey));
		}

		/**
		 * Build a short non-reversible recipient hash for logs.
		 *
		 * @param string $recipientKey Normalized recipient email
		 * @return string              Short hash
		 */
		private function getRecipientLogHash($recipientKey)
		{
			return substr(hash('sha256', $recipientKey), 0, 12);
		}

		/**
		 * Read a global module constant stored on entity 0.
		 *
		 * @param string $name Constant name
		 * @return string      Constant value
		 */
		private function getGlobalConstValue($name)
		{
			$sql = 'SELECT value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'const';
			$sql .= " WHERE name = '".$this->db->escape($name)."'";
			$sql .= ' AND entity = 0';
			$sql .= ' ORDER BY rowid DESC';
			$sql .= $this->db->plimit(1);
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_WARNING);
				return '';
			}

			$value = '';
			if ($obj = $this->db->fetch_object($resql)) {
				$value = (string) $obj->value;
			}
			$this->db->free($resql);

			return $value;
		}

		/**
		 * Check if the current entity already completed this reminder start.
		 *
		 * @param int $startTimestamp Reminder start timestamp
		 * @return bool               True when already completed
		 */
		private function isReminderStartAlreadySent($startTimestamp)
		{
			return ((int) getDolGlobalString('TIMESHEETWEEK_REMINDER_LAST_SENT_STARTTIME', '') === (int) $startTimestamp);
		}

		/**
		 * Mark the current entity reminder start as completed.
		 *
		 * @param int $startTimestamp Reminder start timestamp
		 * @return bool               True when stored
		 */
		private function markReminderStartSent($startTimestamp)
		{
			global $conf;

			return dolibarr_set_const($this->db, 'TIMESHEETWEEK_REMINDER_LAST_SENT_STARTTIME', (string) $startTimestamp, 'chaine', 0, '', $conf->entity) > 0;
		}

		/**
		 * Check if a recipient already received the weekly reminder in any entity.
		 *
		 * @param string $recipientKey Normalized recipient email
		 * @param string $periodKey    Weekly period key
		 * @return bool                True when already sent
		 */
		private function wasReminderRecipientSent($recipientKey, $periodKey)
		{
			return ($this->getGlobalConstValue($this->getRecipientMarkerConstName($recipientKey)) === $periodKey);
		}

		/**
		 * Mark a recipient as having received the weekly reminder globally.
		 *
		 * @param string $recipientKey Normalized recipient email
		 * @param string $periodKey    Weekly period key
		 * @return bool                True when stored
		 */
		private function markReminderRecipientSent($recipientKey, $periodKey)
		{
			return dolibarr_set_const($this->db, $this->getRecipientMarkerConstName($recipientKey), $periodKey, 'chaine', 0, '', 0) > 0;
		}
	}
