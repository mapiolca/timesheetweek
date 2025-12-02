<?php
/* Copyright (C) 2025           Pierre Ardoin           <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.php';
dol_include_once('/core/class/emailtemplates.class.php');

dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Cron helper used to send weekly reminders.
 */
class TimesheetweekReminder
{
	/**
	 * Run cron job to send weekly reminder emails.
	 *
	 * @param DoliDB $db        Database handler
	 * @param int    $limit     Optional limit for recipients
	 * @param int    $forcerun  Force execution (1) or use normal scheduling (0)
	 * @return int              <0 if KO, >=0 if OK (number of emails sent)
	 */
	public static function run($db, $limit = 0, $forcerun = 0)
	{
		global $conf, $langs;

		$langs->loadLangs(array('timesheetweek@timesheetweek'));

		dol_syslog(__METHOD__, LOG_DEBUG);

		$reminderEnabled = getDolGlobalInt('TIMESHEETWEEK_REMINDER_ENABLED', 0);
		if (empty($reminderEnabled) && empty($forcerun)) {
			dol_syslog('TimesheetweekReminder: reminder disabled', LOG_INFO);
			return 0;
		}

		$reminderWeekday = getDolGlobalInt('TIMESHEETWEEK_REMINDER_WEEKDAY', 1);
		if ($reminderWeekday < 1 || $reminderWeekday > 7) {
			dol_syslog($langs->trans('TimesheetWeekReminderWeekdayInvalid'), LOG_ERR);
			return -1;
		}

		$reminderHour = getDolGlobalString('TIMESHEETWEEK_REMINDER_HOUR', '18:00');
		if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $reminderHour)) {
			dol_syslog($langs->trans('TimesheetWeekReminderHourInvalid'), LOG_ERR);
			return -1;
		}

		$templateId = getDolGlobalInt('TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', 0);
		if (empty($templateId)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_WARNING);
			return -1;
		}

		$timezoneCode = !empty($conf->timezone) ? $conf->timezone : 'UTC';
		try {
			$timezone = new DateTimeZone($timezoneCode);
		} catch (Exception $e) {
			dol_syslog($e->getMessage(), LOG_WARNING);
			$timezone = new DateTimeZone('UTC');
		}

		$now = dol_now();
		$currentDate = new DateTime('@'.$now);
		$currentDate->setTimezone($timezone);

		$currentWeekday = (int) $currentDate->format('N');
		$currentMinutes = ((int) $currentDate->format('H') * 60) + (int) $currentDate->format('i');

		list($targetHour, $targetMinute) = explode(':', $reminderHour);
		$targetMinutes = ((int) $targetHour * 60) + (int) $targetMinute;

		if (empty($forcerun)) {
			if ($currentWeekday !== $reminderWeekday) {
				dol_syslog('TimesheetweekReminder: not the configured day, skipping execution', LOG_DEBUG);
				return 0;
			}
			if ($currentMinutes < $targetMinutes) {
				dol_syslog('TimesheetweekReminder: configured hour not reached, skipping execution', LOG_DEBUG);
				return 0;
			}
		}

		$emailTemplateClass = '';
		if (class_exists('CEmailTemplates')) {
			$emailTemplateClass = 'CEmailTemplates';
		} elseif (class_exists('EmailTemplates')) {
			$emailTemplateClass = 'EmailTemplates';
		}

		if (empty($emailTemplateClass)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_ERR);
			return -1;
		}

		$emailTemplate = new $emailTemplateClass($db);
		$templateFetch = $emailTemplate->fetch($templateId);
		if ($templateFetch <= 0) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_ERR);
			return -1;
		}

		$subject = !empty($emailTemplate->topic) ? $emailTemplate->topic : $emailTemplate->label;
		$body = !empty($emailTemplate->content) ? $emailTemplate->content : '';

		if (empty($subject) || empty($body)) {
			dol_syslog($langs->trans('TimesheetWeekReminderTemplateMissing'), LOG_ERR);
			return -1;
		}

		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}

		$substitutions = getCommonSubstitutionArray($langs, 0, null, null, null);

		$sql = 'SELECT rowid, lastname, firstname, email';
		$sql .= ' FROM '.MAIN_DB_PREFIX."user";
		$sql .= " WHERE statut = 1 AND email IS NOT NULL AND email <> ''";
		$sql .= ' AND entity IN (0, '.((int) $conf->entity).')';
		$sql .= ' ORDER BY rowid ASC';
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

			$userSubstitutions = $substitutions;
			$userSubstitutions['__USER_FIRSTNAME__'] = $obj->firstname;
			$userSubstitutions['__USER_LASTNAME__'] = $obj->lastname;
			$userSubstitutions['__USER_FULLNAME__'] = dolGetFirstLastname($obj->firstname, $obj->lastname);

			$preparedSubject = make_substitutions($subject, $userSubstitutions);
			$preparedBody = make_substitutions($body, $userSubstitutions);

			$mail = new CMailFile($preparedSubject, $recipient, $from, $preparedBody, array(), array(), array(), '', '', 0, 0, '', '', '', 'utf-8');
			$resultSend = $mail->sendfile();
			if ($resultSend) {
				$emailsSent++;
			} else {
				dol_syslog($langs->trans('TimesheetWeekReminderSendFailed', $recipient), LOG_ERR);
				$errors--;
			}
		}

		$db->free($resql);

		if ($errors < 0) {
			return $errors;
		}

		return $emailsSent;
	}
}
