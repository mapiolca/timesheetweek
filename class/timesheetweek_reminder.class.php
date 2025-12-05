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
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

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
	 * @param DoliDB $db	     Database handler
	 * @param int	 $limit	     Optional limit for recipients
	 * @param int	 $forcerun   Force execution (1) or use normal scheduling (0)
	 * @param array	 $targetUserIds Limit execution to specific user ids when provided
	 * @return int		     <0 if KO, >=0 if OK (number of emails sent)
	 */
	public function run($dbInstance = null, $limit = 0, $forcerun = 0, array $targetUserIds = array())
	{
		global $db, $conf, $user, $langs;
		/*
		$db = $dbInstance;
		if (empty($db) && !empty($GLOBALS['db'])) {
			$db = $GLOBALS['db'];
		}
		*/
		if (empty($db)) {
			dol_syslog($langs->transnoentitiesnoconv('ErrorNoDatabase'), LOG_ERR);
			return -1;
		}
		
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

		$emailTemplateClassFile = '';
		if (is_readable(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplate.class.php')) {
			$emailTemplateClassFile = '/core/class/cemailtemplate.class.php';
		} elseif (is_readable(DOL_DOCUMENT_ROOT.'/core/class/emailtemplate.class.php')) {
			$emailTemplateClassFile = '/core/class/emailtemplate.class.php';
		}

		if (!empty($emailTemplateClassFile)) {
			dol_include_once($emailTemplateClassFile);
		}

		if (!class_exists('CEmailTemplate') && !class_exists('EmailTemplate')) {
			dol_syslog($langs->trans('ErrorFailedToLoadEmailTemplateClass'), LOG_ERR);
			return -1;
		}

		dol_syslog(__METHOD__, LOG_DEBUG);

		$reminderEnabled = getDolGlobalInt('TIMESHEETWEEK_REMINDER_ENABLED', 0, $conf->entity);
		if (empty($reminderEnabled) && empty($forceExecution)) {
			dol_syslog('TimesheetweekReminder: reminder disabled', LOG_INFO);
			return 0;
		}

		$reminderWeekday = getDolGlobalInt('TIMESHEETWEEK_REMINDER_WEEKDAY', 1, $conf->entity);
		if ($reminderWeekday < 1 || $reminderWeekday > 7) {
			dol_syslog($langs->trans('TimesheetWeekReminderWeekdayInvalid'), LOG_ERR);
			return -1;
		}

		$reminderHour = getDolGlobalString('TIMESHEETWEEK_REMINDER_HOUR', '18:00', $conf->entity);
		if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $reminderHour)) {
			dol_syslog($langs->trans('TimesheetWeekReminderHourInvalid'), LOG_ERR);
			return -1;
		}

		$templateId = getDolGlobalInt('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 0, $conf->entity);
		if (empty($templateId)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_WARNING);
			return 0;
		}

		$timezoneCode = !empty($conf->timezone) ? $conf->timezone : 'UTC';
		$now = dol_now();
		$nowArray = dol_getdate($now, true, $timezoneCode);
		$currentWeekday = (int) $nowArray['wday'];
		$currentWeekdayIso = ($currentWeekday === 0) ? 7 : $currentWeekday;
		$currentMinutes = ((int) $nowArray['hours'] * 60) + (int) $nowArray['minutes'];

		list($targetHour, $targetMinute) = explode(':', $reminderHour);
		$targetMinutes = ((int) $targetHour * 60) + (int) $targetMinute;
		$windowMinutes = 5;
		$lowerBound = max(0, $targetMinutes - $windowMinutes);
		$upperBound = min(120, $targetMinutes + $windowMinutes);

		if (empty($forceExecution)) {
			if ($currentWeekdayIso !== $reminderWeekday) {
				dol_syslog('TimesheetweekReminder: not the configured day, skipping execution', LOG_DEBUG);
				return 0;
			}
			if ($currentMinutes < $lowerBound || $currentMinutes > $upperBound) {
				dol_syslog('TimesheetweekReminder: outside configured time window, skipping execution', LOG_DEBUG);
				return 0;
			}
		}

		$emailTemplate = null;
		$templateFetch = 0;
		if (class_exists('CEmailTemplate')) {
			$emailTemplate = new CEmailTemplate($db);
			if (method_exists($emailTemplate, 'apifetch')) {
				$templateFetch = $emailTemplate->apifetch($templateId);
			} else {
				$templateFetch = $emailTemplate->fetch($templateId);
			}
		} elseif (class_exists('EmailTemplate')) {
			$emailTemplate = new EmailTemplate($db);
			$templateFetch = $emailTemplate->fetch($templateId);
		}

		if (empty($emailTemplate)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_ERR);
			return -1;
		}

		if ($templateFetch <= 0) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_WARNING);
			return 0;
		}

		$subject = !empty($emailTemplate->topic) ? $emailTemplate->topic : $emailTemplate->label;
		$body = !empty($emailTemplate->content) ? $emailTemplate->content : '';

		if (empty($subject) || empty($body)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_WARNING);
			return 0;
		}

		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}

		$substitutions = getCommonSubstitutionArray($langs, 0, null, null, null);
		complete_substitutions_array($substitutions, $langs, null);

		$eligibleRights = array(
			45000301, // read own
			45000302, // read child
			45000303, // read all
			45000304, // write own
			45000305, // write child
			45000306, // write all
			45000310, // validate generic
			45000311, // validate own
			45000312, // validate child
			45000313, // validate all
			45000314, // seal
			45000315, // unseal
		);

		$entityFilter = getEntity('user');
		$sql = 'SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.email';
		$sql .= ' FROM '.MAIN_DB_PREFIX."user AS u";
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX."user_rights AS ur ON ur.fk_user = u.rowid AND ur.entity IN (".$entityFilter.')';
		$sql .= " WHERE u.statut = 1 AND u.email IS NOT NULL AND u.email <> ''";
		$sql .= ' AND u.entity IN ('.$entityFilter.')';
		$sql .= ' AND ur.fk_id IN ('.implode(',', array_map('intval', $eligibleRights)).')';
		if (!empty($targetUserIds)) {
			$sql .= ' AND u.rowid IN ('.implode(',', array_map('intval', $targetUserIds)).')';
		}
		$sql .= ' ORDER BY u.rowid ASC';
		if ($limit > 0) {
			$sql .= $db->plimit((int) $limit);
		}

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog($db->lasterror(), LOG_ERR);
			return -1;
		}

		$emailsSent = 0;
		$errors = 0;

		while ($obj = $db->fetch_object($resql)) {
			$recipient = trim($obj->email);
			if (empty($recipient)) {
				continue;
			}

			$user = new User($db);
			$fetchUser = $user->fetch($obj->rowid);
			if ($fetchUser < 0) {
				dol_syslog($user->error, LOG_ERR);
				$errors++;
				continue;
			}

			$userSubstitutions = $substitutions;
			$userSubstitutions['__USER_FIRSTNAME__'] = $user->firstname;
			$userSubstitutions['__USER_LASTNAME__'] = $user->lastname;
			$userSubstitutions['__USER_FULLNAME__'] = dolGetFirstLastname($user->firstname, $user->lastname);
			$userSubstitutions['__USER_EMAIL__'] = $recipient;
			complete_substitutions_array($userSubstitutions, $langs, null, $user);

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

/*		if ($errors > 0) {
			return -$errors;
		}*/

//		return $emailsSent;
		
		if ($errors) {
			//$result = 1;
            $this->error = $langs->trans('TimesheetWeekReminderSendFailed').' '.$errors;
            dol_syslog(__METHOD__." end - ".$this->error, LOG_ERR);
            return 1;
        }else{
			//$result = 0;
            $this->output = $langs->trans('TimesheetWeekReminderSendSuccess')." ".$emailsSent.".";
            dol_syslog(__METHOD__." end - ".$this->output, LOG_INFO);
            return 0;
        }
	}

	/**
	 * Send a reminder test email to the current user using the configured template.
	 *
	 * @param DoliDB $db	Database handler
	 * @param User	 $user	Current user
	 * @return int		<0 if KO, >=0 if OK (number of emails sent)
	 */
	public function sendTest($db, User $user)
	{
		return  $this->run($db, 1, 1, array((int) $user->id));
	}
}
