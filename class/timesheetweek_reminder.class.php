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

		$sql = 'SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.email';
		$sql .= ' FROM '.MAIN_DB_PREFIX."user AS u";
		if (isModEnabled('multicompany') && getDolGlobalString('MULTICOMPANY_TRANSVERSE_MODE')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."usergroup_user as ug";
			$sql .= " ON ((ug.fk_user = u.rowid";
			$sql .= " AND ug.entity IN (".getEntity('usergroup')."))";
			$sql .= " OR u.entity = 0)";
		}
		$sql .= " WHERE u.statut = 1 AND u.email IS NOT NULL AND u.email <> ''";
		if (isModEnabled('multicompany') && !getDolGlobalString('MULTICOMPANY_TRANSVERSE_MODE')) {
			$sql .= ' AND u.entity IN ('.getEntity("user").')';
		}
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
			return 0;
		}

		$emailsSent = 0;
		$errors = 0;

		while ($obj = $db->fetch_object($resql)) {
			$recipient = trim($obj->email);
			if (empty($recipient)) {
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
			return -$errors;
		}

		$this->output = $langs->trans('TimesheetWeekReminderSendSuccess', $emailsSent);
		dol_syslog(__METHOD__.' sent='.$emailsSent, LOG_DEBUG);

		return $emailsSent;
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
			global $user;
			
			$cronJob = new Cronjob($db);
			$fetchResult = $cronJob->fetch(0, 'TimesheetweekReminder', 'run');
			if ($fetchResult <= 0 || empty($cronJob->id)) {
				return 0;
			}
			
			$cronJob->datestart = $startTimestamp;
			$cronJob->datenextrun = $startTimestamp;
			
			$userForUpdate = $currentUser;
			if (empty($userForUpdate) && !empty($user) && $user instanceof User) {
				$userForUpdate = $user;
			}
			
			return $cronJob->update($userForUpdate);
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
	}
