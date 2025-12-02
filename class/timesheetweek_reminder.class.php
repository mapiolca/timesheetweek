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

		$reminderEnabledConst = dolibarr_get_const($db, 'TIMESHEETWEEK_REMINDER_ENABLED', $conf->entity);
		$reminderEnabled = !empty($reminderEnabledConst) ? (int) $reminderEnabledConst : 0;
		if (empty($reminderEnabled) && empty($forcerun)) {
			dol_syslog('TimesheetweekReminder: reminder disabled', LOG_INFO);
			return 0;
		}

		$reminderWeekdayConst = dolibarr_get_const($db, 'TIMESHEETWEEK_REMINDER_WEEKDAY', $conf->entity);
		$reminderWeekday = !empty($reminderWeekdayConst) ? (int) $reminderWeekdayConst : 1;
		if ($reminderWeekday < 1 || $reminderWeekday > 7) {
			dol_syslog($langs->trans('TimesheetWeekReminderWeekdayInvalid'), LOG_ERR);
			return -1;
		}

		$reminderHourConst = dolibarr_get_const($db, 'TIMESHEETWEEK_REMINDER_HOUR', $conf->entity);
		$reminderHour = !empty($reminderHourConst) ? $reminderHourConst : '18:00';
		if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $reminderHour)) {
			dol_syslog($langs->trans('TimesheetWeekReminderHourInvalid'), LOG_ERR);
			return -1;
		}

		$templateIdConst = dolibarr_get_const($db, 'TIMESHEETWEEK_REMINDER_EMAIL_TEMPLATE', $conf->entity);
		$templateId = !empty($templateIdConst) ? (int) $templateIdConst : 0;
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
		$windowMinutes = 60;
		$lowerBound = max(0, $targetMinutes - $windowMinutes);
		$upperBound = min(1440, $targetMinutes + $windowMinutes);
		
		if (empty($forcerun)) {
			if ($currentWeekdayIso !== $reminderWeekday) {
			dol_syslog('TimesheetweekReminder: not the configured day, skipping execution', LOG_DEBUG);
			return 0;
			}
			if ($currentMinutes < $lowerBound || $currentMinutes > $upperBound) {
			dol_syslog('TimesheetweekReminder: outside configured time window, skipping execution', LOG_DEBUG);
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
				$errors++;
			}
		}

		$db->free($resql);

		if ($errors > 0) {
			return -$errors;
		}

		return $emailsSent;
	}
}
