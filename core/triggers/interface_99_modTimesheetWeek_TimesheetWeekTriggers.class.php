<?php
/*
 * Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Trigger class for TimesheetWeek notifications.
 */
class InterfaceTimesheetWeekTriggers extends DolibarrTriggers
{
        /**
         * Constructor
         *
         * @param DoliDB $db
         */
        public function __construct($db)
        {
                $this->db = $db;
                $this->name = 'timesheetweektriggers';
                $this->family = 'timesheetweek';
                $this->description = 'TimesheetWeek events';
                $this->version = 'dolibarr';
                $this->picto = 'bookcal@timesheetweek';
        }

       /**
        * Execute trigger
        *
        * @param string        $action
        * @param CommonObject  $object
        * @param User          $user
        * @param Translate     $langs
        * @param Conf          $conf
        *
        * @return int
        */
       public function runTrigger($action, $object, $user, $langs, $conf)
       {
               if (empty($conf->timesheetweek->enabled)) {
                       return 0;
               }

		if (!($object instanceof TimesheetWeek)) {
			return 0;
		}
		/*
		if ($action === 'TIMESHEETWEEK_SUBMIT' || $action === 'TIMESHEETWEEK_APPROVE' || $action === 'TIMESHEETWEEK_REFUSE') {
			// FR : on conserve notre envoi custom : le template <TRIGGER>_TEMPLATE est chargé via
			//      FormMail::getEMailTemplate() et les destinataires viennent du module Notification
			//      (subscriptions notify_def + adresses fixes NOTIFICATION_FIXEDEMAIL_*, avec résolution
			//      des tokens __SUPERVISOREMAIL__ et __AUTHOREMAIL__).
			// EN : keep our custom dispatch: <TRIGGER>_TEMPLATE is loaded through FormMail::getEMailTemplate()
			//      and recipients come from the Notification module (notify_def subscriptions + fixed
			//      NOTIFICATION_FIXEDEMAIL_* addresses, resolving __SUPERVISOREMAIL__ / __AUTHOREMAIL__).
			return $this->sendNotification($action, $object, $user, $langs, $conf);
		}
		*/
               return 0;
       }

       /**
        * Send notification email for TimesheetWeek events
        *
        * @param string        $action
        * @param TimesheetWeek $timesheet
        * @param User          $actionUser
        * @param Translate     $langs
        * @param Conf          $conf
        *
        * @return int
        */
        protected function sendNotification($action, TimesheetWeek $timesheet, User $actionUser, $langs, $conf)
        {
                $langs->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));

                $subjectKey = '';
                $bodyKey = '';
                $missingKey = '';
                $templateKeys = array();
                $recipients = array();

                $meta = $this->buildNotificationData($timesheet, $actionUser, $langs, $conf);
                $employee = $meta['employee'];
                $validator = $meta['validator'];
                $employeeName = $meta['employee_name'];
                $validatorName = $meta['validator_name'];
                $actionUserName = $meta['action_user_name'];
                $mailSignature = $meta['mail_signature'];
                $baseSubstitutions = $meta['base_substitutions'];
                $url = $meta['url'];
				
                if ($action === 'TIMESHEETWEEK_SUBMIT') {
                        $subjectKey = 'TimesheetWeekNotificationSubmitSubject';
                        $bodyKey = 'TimesheetWeekNotificationSubmitBody';
                        $missingKey = 'TimesheetWeekNotificationValidatorFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateSubmitLabel',
                                'subject' => 'TimesheetWeekTemplateSubmitSubject',
                                'body' => 'TimesheetWeekTemplateSubmitBody',
                        );
                } elseif ($action === 'TIMESHEETWEEK_APPROVE') {
                        $subjectKey = 'TimesheetWeekNotificationApproveSubject';
                        $bodyKey = 'TimesheetWeekNotificationApproveBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateApproveLabel',
                                'subject' => 'TimesheetWeekTemplateApproveSubject',
                                'body' => 'TimesheetWeekTemplateApproveBody',
                        );
                } elseif ($action === 'TIMESHEETWEEK_REFUSE') {
                        $subjectKey = 'TimesheetWeekNotificationRefuseSubject';
                        $bodyKey = 'TimesheetWeekNotificationRefuseBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateRefuseLabel',
                                'subject' => 'TimesheetWeekTemplateRefuseSubject',
                                'body' => 'TimesheetWeekTemplateRefuseBody',
                        );
                }
				
                $recipients = $this->resolveNotificationRecipients($action, $timesheet, $actionUser, $langs);

                if (empty($recipients)) {
                        dol_syslog(__METHOD__.': no recipient configured in Notification module for trigger '.$action, LOG_WARNING);
                        return 0;
                }
				
			
			//dol_include_once('/core/lib/functions2.lib.php');
			//if (floatval(DOL_VERSION) < 23) {
			//		dol_include_once('/timesheetweek/core/class/cemailtemplate.class.php');
			//} else {
			//	require_once DOL_DOCUMENT_ROOT.'/core/class/cemailtemplate.class.php';
			//}
			
			dol_include_once('/core/class/CMailFile.class.php');

                $templateClass = '';
                if (class_exists('CEmailTemplates')) {
                        $templateClass = 'CEmailTemplates';
                } elseif (class_exists('EmailTemplates')) {
                        $templateClass = 'EmailTemplates';
                }

                $sent = 0;

                foreach ($recipients as $recipient) {
                        if (empty($recipient->email)) {
                                dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationNoEmail', $recipient->getFullName($langs)), LOG_WARNING);
                                continue;
                        }

                        $substitutions = $baseSubstitutions;
                        $substitutions['__RECIPIENT_FULLNAME__'] = $recipient->getFullName($langs);
                        $substitutions['__TIMESHEETWEEK_MAIL_SIGNATURE__'] = $mailSignature;

                        $template = null;
                        $tplResult = 0;
                        $template = $this->fetchTemplateForTrigger($action, $actionUser, $langs);
                        if ($template) {
                                $tplResult = 1;
                        } elseif (!empty($templateClass) && !empty($templateKeys)) {
                                $template = $this->createDefaultTemplate($templateClass, $templateKeys, $action, $actionUser, $langs, $conf);
                                if ($template) {
                                        $tplResult = 1;
                                }
                        }

                        $subject = '';
                        $message = '';
                        $ccList = array();
                        $bccList = array();
                        $sendtoList = array($recipient->email);
                        $emailFrom = $actionUser->email;
                        if (empty($emailFrom) && !empty($conf->global->MAIN_MAIL_EMAIL_FROM)) {
                                $emailFrom = $conf->global->MAIN_MAIL_EMAIL_FROM;
                        }
                        if (empty($emailFrom) && !empty($conf->global->MAIN_INFO_SOCIETE_MAIL)) {
                                $emailFrom = $conf->global->MAIN_INFO_SOCIETE_MAIL;
                        }

					if ($tplResult > 0 && $template) {
						$subjectTemplate = !empty($template->subject) ? $template->subject : $template->topic;
						$bodyTemplate = $template->content;

						$subject = make_substitutions($subjectTemplate, $substitutions);
						$subject = dol_html_entity_decode($subject, ENT_QUOTES);
						$message = make_substitutions($bodyTemplate, $substitutions);

						if (!empty($template->email_from)) {
							$emailFrom = make_substitutions($template->email_from, $substitutions);
						}

                                $templateTo = '';
                                if (!empty($template->email_to)) {
                                        $templateTo = $template->email_to;
                                } elseif (!empty($template->email_to_list)) {
                                        $templateTo = $template->email_to_list;
                                }
                                if (!empty($templateTo)) {
                                        $templateTo = make_substitutions($templateTo, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateTo) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $sendtoList, true)) {
                                                        $sendtoList[] = $addr;
                                                }
                                        }
                                }

                                $templateCc = '';
                                if (!empty($template->email_cc)) {
                                        $templateCc = $template->email_cc;
                                } elseif (!empty($template->email_to_cc)) {
                                        $templateCc = $template->email_to_cc;
                                }
                                if (!empty($templateCc)) {
                                        $templateCc = make_substitutions($templateCc, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateCc) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $ccList, true)) {
                                                        $ccList[] = $addr;
                                                }
                                        }
                                }

                                $templateBcc = '';
                                if (!empty($template->email_bcc)) {
                                        $templateBcc = $template->email_bcc;
                                } elseif (!empty($template->email_to_bcc)) {
                                        $templateBcc = $template->email_to_bcc;
                                }
                                if (!empty($templateBcc)) {
                                        $templateBcc = make_substitutions($templateBcc, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateBcc) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $bccList, true)) {
                                                        $bccList[] = $addr;
                                                }
                                        }
                                }
					} else {
						$recipientName = $recipient->getFullName($langs);
						$subject = $langs->trans($subjectKey, $timesheet->ref);
						$subject = dol_html_entity_decode($subject, ENT_QUOTES);

                                if ($action === 'TIMESHEETWEEK_SUBMIT') {
                                        $messageArgs = array(
                                                $recipientName,
                                                $employeeName,
                                                $timesheet->ref,
                                                $timesheet->week,
                                                $timesheet->year,
                                                $url,
                                                $mailSignature,
                                        );
                                } else {
                                        $messageArgs = array(
                                                $recipientName,
                                                $timesheet->ref,
                                                $timesheet->week,
                                                $timesheet->year,
                                                $actionUserName,
                                                $url,
                                                $mailSignature,
                                        );
                                }

                                list($messageTemplate, $placeholderCount) = $this->resolveNotificationTemplate($langs, $bodyKey, count($messageArgs));

                                if ($placeholderCount > count($messageArgs)) {
                                        dol_syslog(
                                                __METHOD__.': '.$langs->trans(
                                                        'TimesheetWeekNotificationMailError',
                                                        'Not enough values provided for mail template placeholders'
                                                ),
                                                LOG_WARNING
                                        );
                                        $messageArgs = array_pad($messageArgs, $placeholderCount, '');
                                }

                                $message = @vsprintf($messageTemplate, $messageArgs);
                                if ($message === false) {
                                        dol_syslog(
                                                __METHOD__.': '.$langs->trans(
                                                        'TimesheetWeekNotificationMailError',
                                                        'Invalid mail template placeholders'
                                                ),
                                                LOG_WARNING
                                        );
                                        $message = $messageTemplate;
                                }
                        }

			if (empty($subject) || empty($message)) {
				dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationMailError', 'Empty template'), LOG_WARNING);
				continue;
			}

			$sendto = implode(',', array_unique(array_filter($sendtoList)));
			$cc = implode(',', array_unique(array_filter($ccList)));
			$bcc = implode(',', array_unique(array_filter($bccList)));
			// Normalize escaped newlines coming from lang/templates ("\\n" -> "\n")
			$normalizeNewlines = function ($str) {
				if ($str === null) return $str;

				// Handle double-escaped sequences first
				$str = str_replace(array('\\\\r\\\\n', '\\\\n', '\\\\r'), array("\r\n", "\n", "\r"), $str);
				$str = preg_replace('/\\\\+r\\\\+n/', "\r\n", $str);
				$str = preg_replace('/\\\\+n/', "\n", $str);
				$str = preg_replace('/\\\\+r/', "\r", $str);

				// Then handle standard escaped sequences
				return str_replace(array('\\r\\n', '\\n', '\\r'), array("\r\n", "\n", "\r"), $str);
			};

			$messageText = $normalizeNewlines($message);
			$messageText = str_replace(array("\\r\\n", "\\n", "\\r"), array("\r\n", "\n", "\r"), $messageText);

			// Build HTML part from message (keep existing HTML if message already contains tags)
			if (function_exists('dol_textishtml') && dol_textishtml($messageText)) {
				$messageHtml = $messageText;
			} else {
				$messageHtml = dol_nl2br(dol_escape_htmltag($messageText));
			}

			// Make URL clickable in HTML part using Dolibarr helper (no regex linkify).
			if (!empty($url) && function_exists('dol_print_url') && strpos($messageHtml, '<a ') === false) {
				$escapedUrl = dol_escape_htmltag($url);
				$clickableUrl = dol_print_url($url, '_blank', 255, 0, '');
				if (!empty($clickableUrl)) {
					$messageHtml = str_replace($escapedUrl, $clickableUrl, $messageHtml);
				}
			}
			$messageHtml = str_replace(array("\\r\\n", "\\n", "\\r"), array('<br>', '<br>', ''), $messageHtml);

			dol_syslog(
				__METHOD__.
				': prepare mail action='.$action.
				' msgishtml=1 textlen='.(function_exists('dol_strlen') ? dol_strlen($messageText) : strlen($messageText)).
				' htmllen='.(function_exists('dol_strlen') ? dol_strlen($messageHtml) : strlen($messageHtml)).
				' haslink='.((strpos($messageHtml, '<a ') !== false) ? 'yes' : 'no'),
				LOG_DEBUG
			);
			$trackId = 'timesheetweek-'.$timesheet->id.'-'.$action.'-'.($recipient ? (int) $recipient->id : 0);
			$isHtml = 1;

			$nativeResult = $timesheet->sendNativeMailNotification(
				$action,
				$actionUser,
				$recipient,
				$langs,
				$conf,
				$substitutions,
				array(
					'subject' => $subject,
					'message' => $messageText,
					'message_html' => $messageHtml,
					'sendto' => $sendto,
					'cc' => $cc,
					'bcc' => $bcc,
					'replyto' => $emailFrom,
					'trackid' => $trackId,
					'ishtml' => $isHtml,
				)
			);

			if ($nativeResult > 0) {
				$sent += $nativeResult;
				continue;
			}

			$mail = new CMailFile($subject, $sendto, $emailFrom, $messageHtml, array(), array(), array(), $cc, $bcc, 0, $isHtml, '', '', $trackId);
			if ($mail->sendfile()) {
				$sent++;
			} else {
				$errmsg = $mail->error ? $mail->error : 'Unknown error';
				dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationMailError', $errmsg), LOG_WARNING);
			}
		}

		return $sent;
	}
	
        /**
         * Resolve the recipient list from the Notification module configuration.
         *
         * FR : Reproduit la résolution des destinataires de Notify::send() : abonnements de notify_def
         *      (utilisateurs liés au trigger) + adresses libres NOTIFICATION_FIXEDEMAIL_<TRIGGER>_THRESHOLD_HIGHER_*
         *      avec remplacement des tokens __SUPERVISOREMAIL__ et __AUTHOREMAIL__.
         * EN : Mirror Notify::send() recipient resolution: notify_def subscriptions for the trigger plus
         *      NOTIFICATION_FIXEDEMAIL_<TRIGGER>_THRESHOLD_HIGHER_* free addresses, with
         *      __SUPERVISOREMAIL__ / __AUTHOREMAIL__ token substitution.
         *
         * @param string        $action
         * @param TimesheetWeek $timesheet
         * @param User          $actionUser
         * @param Translate     $langs
         *
         * @return array<int,object>
         */
        protected function resolveNotificationRecipients($action, TimesheetWeek $timesheet, User $actionUser, $langs)
        {
                global $conf;

                $recipients = array();
                $seenEmails = array();

                if (!isModEnabled('notification')) {
                        return $recipients;
                }

                $sql = "SELECT n.fk_user, u.email, u.firstname, u.lastname, u.lang as default_lang";
                $sql .= " FROM ".MAIN_DB_PREFIX."notify_def AS n";
                $sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_action_trigger AS a ON a.rowid = n.fk_action";
                $sql .= " INNER JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = n.fk_user";
                $sql .= " WHERE a.code = '".$this->db->escape($action)."'";
                $sql .= " AND u.statut = 1";
                $sql .= " AND u.email IS NOT NULL AND u.email <> ''";
                $sql .= " AND n.entity IN (".getEntity('notify_def').")";

                $resql = $this->db->query($sql);
                if ($resql) {
                        while ($obj = $this->db->fetch_object($resql)) {
                                $emailKey = strtolower(trim($obj->email));
                                if (isset($seenEmails[$emailKey])) {
                                        continue;
                                }
                                $seenEmails[$emailKey] = true;

                                $u = new User($this->db);
                                $u->id = (int) $obj->fk_user;
                                $u->email = $obj->email;
                                $u->firstname = $obj->firstname;
                                $u->lastname = $obj->lastname;
                                $u->lang = $obj->default_lang;
                                $recipients[] = $u;
                        }
                        $this->db->free($resql);
                } else {
                        dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_WARNING);
                }

                $prefix = 'NOTIFICATION_FIXEDEMAIL_'.$action.'_THRESHOLD_HIGHER_';
                if (!empty($conf->global) && (is_array($conf->global) || is_object($conf->global))) {
                        foreach ((array) $conf->global as $constKey => $constValue) {
                                if (strpos($constKey, $prefix) !== 0 || empty($constValue)) {
                                        continue;
                                }

                                $resolved = $this->resolveSpecialRecipientTokens((string) $constValue, $timesheet, $actionUser);
                                foreach (preg_split('/[,;]+/', $resolved) as $addr) {
                                        $addr = trim($addr);
                                        if ($addr === '') {
                                                continue;
                                        }

                                        $emailOnly = $addr;
                                        if (preg_match('/<([^>]+)>/', $addr, $matches)) {
                                                $emailOnly = trim($matches[1]);
                                        }
                                        $emailKey = strtolower($emailOnly);
                                        if (isset($seenEmails[$emailKey])) {
                                                continue;
                                        }
                                        $seenEmails[$emailKey] = true;

                                        $u = new User($this->db);
                                        $u->id = 0;
                                        $u->email = $emailOnly;
                                        $u->firstname = '';
                                        $u->lastname = '';
                                        $recipients[] = $u;
                                }
                        }
                }

                return $recipients;
        }

        /**
         * Replace __SUPERVISOREMAIL__ / __AUTHOREMAIL__ tokens inside a recipient string.
         *
         * FR : __SUPERVISOREMAIL__ = supérieur de l'utilisateur qui a déclenché l'action,
         *      __AUTHOREMAIL__ = auteur (fk_user) de la feuille de temps.
         * EN : __SUPERVISOREMAIL__ = supervisor of the user who triggered the action,
         *      __AUTHOREMAIL__ = author (fk_user) of the timesheet.
         *
         * @param string        $sendto
         * @param TimesheetWeek $timesheet
         * @param User          $actionUser
         *
         * @return string
         */
        protected function resolveSpecialRecipientTokens($sendto, TimesheetWeek $timesheet, User $actionUser)
        {
                $supervisorEmail = '';
                if (!empty($actionUser->fk_user)) {
                        $supervisor = $this->fetchUser((int) $actionUser->fk_user);
                        if ($supervisor && !empty($supervisor->email)) {
                                $supervisorEmail = $supervisor->email;
                        }
                }

                $authorEmail = '';
                if (!empty($timesheet->fk_user)) {
                        $author = $this->fetchUser((int) $timesheet->fk_user);
                        if ($author && !empty($author->email)) {
                                $authorEmail = $author->email;
                        }
                }

                $sendto = str_replace(array('__SUPERVISOREMAIL__', '__AUTHOREMAIL__'), array($supervisorEmail, $authorEmail), $sendto);

                $sendto = preg_replace('/,\s*,/', ',', $sendto);
                $sendto = preg_replace('/^[\s,]+/', '', $sendto);
                $sendto = preg_replace('/[\s,]+$/', '', $sendto);

                return $sendto;
        }

        /**
         * Resolve a translation template without triggering sprintf errors.
         *
         * @param Translate $langs
         * @param string    $key
         * @param int       $expectedPlaceholders
         *
         * @return array{0:string,1:int}
         */
        protected function resolveNotificationTemplate($langs, $key, $expectedPlaceholders)
        {
                $template = $this->extractTranslationTemplate($langs, $key);
                if ($template === '') {
                        return array($key, (int) $expectedPlaceholders);
                }

                $placeholderCount = 0;
                if (preg_match_all('/(?<!%)%(?:\d+\$)?[bcdeEfFgGosuxX]/', $template, $matches)) {
                        $placeholderCount = count($matches[0]);
                }

                return array($template, max($placeholderCount, (int) $expectedPlaceholders));
        }

        /**
         * Safely extract a translation value while preserving escaped characters.
         *
         * @param Translate $langs
         * @param string    $key
         *
         * @return string
         */
        protected function extractTranslationTemplate($langs, $key)
        {
                if (!is_object($langs) || empty($key)) {
                        return '';
                }

                $value = '';
                if (property_exists($langs, 'tab_translate') && is_array($langs->tab_translate)) {
                        $lookupKeys = array($key, strtoupper($key));
                        foreach ($lookupKeys as $lookupKey) {
                                if (array_key_exists($lookupKey, $langs->tab_translate)) {
                                        $candidate = $langs->tab_translate[$lookupKey];
                                        if (is_string($candidate) && $candidate !== '') {
                                                $value = $candidate;
                                                break;
                                        }
                                }
                        }
                }

                if ($value === '') {
                        return '';
                }

                $value = strtr(
                        $value,
                        array(
                                '\\n' => "\n",
                                '\\r' => "\r",
                                '\\t' => "\t",
                        )
                );

                return str_replace('\\\\', '\\', $value);
        }

        /**
         * Fetch user helper
         *
         * @param int $userId
         * @return User|null
         */
        protected function fetchUser($userId)
        {
                $userId = (int) $userId;
                if ($userId <= 0) {
                        return null;
                }

                static $cache = array();
                if (!array_key_exists($userId, $cache)) {
                        $user = new User($this->db);
                        if ($user->fetch($userId) > 0) {
                                $cache[$userId] = $user;
                        } else {
                                $cache[$userId] = null;
                        }
                }

                return $cache[$userId];
        }

        /**
         * Load the email template configured for a given trigger, mirroring Dolibarr's Notify class.
         *
         * FR : Reproduit le mécanisme de Notify::send() : on lit la constante <TRIGGER>_TEMPLATE
         *      (renseignée via la page de paramétrage du module Notification) puis on charge le
         *      modèle correspondant via FormMail::getEMailTemplate().
         * EN : Mirror Notify::send() behaviour: read the <TRIGGER>_TEMPLATE constant set from the
         *      Notification module setup page, then load it through FormMail::getEMailTemplate().
         *
         * @param string    $action
         * @param User      $actionUser
         * @param Translate $langs
         *
         * @return object|null
         */
        protected function fetchTemplateForTrigger($action, User $actionUser, $langs)
        {
                if (empty($action)) {
                        return null;
                }

                $label = getDolGlobalString($action.'_TEMPLATE');
                if (empty($label)) {
                        return null;
                }

                require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
                $formmail = new FormMail($this->db);

                try {
                        $template = $formmail->getEMailTemplate($this->db, 'timesheetweek', $actionUser, $langs, 0, 1, $label);
                } catch (\Throwable $error) {
                        dol_syslog(__METHOD__.': getEMailTemplate failed for label '.$label.' - '.$error->getMessage(), LOG_WARNING);
                        return null;
                }

                if (is_object($template) && !empty($template->id)) {
                        dol_syslog(__METHOD__.': loaded template "'.$label.'" for trigger '.$action, LOG_DEBUG);
                        return $template;
                }

                return null;
        }

        /**
         * Create a default email template when none exists
         *
         * @param string    $templateClass
         * @param array     $templateKeys
         * @param string    $action
         * @param User      $actionUser
         * @param Translate $langs
         * @param Conf      $conf
         *
         * @return object|null
         */
        protected function createDefaultTemplate($templateClass, array $templateKeys, $action, User $actionUser, $langs, $conf)
        {
                if (empty($templateClass) || empty($templateKeys['subject']) || empty($templateKeys['body'])) {
                        return null;
                }

                $template = new $templateClass($this->db);
                if (property_exists($template, 'entity')) {
                        $template->entity = (int) $conf->entity;
                }
                if (property_exists($template, 'module')) {
                        $template->module = 'timesheetweek';
                }
                if (property_exists($template, 'type_template') && empty($template->type_template)) {
                        $template->type_template = 'timesheetweek';
                }
                if (property_exists($template, 'code')) {
                        $template->code = $action;
                }
                $template->label = $langs->transnoentities($templateKeys['label']);
                if (property_exists($template, 'topic')) {
                        $template->topic = $langs->transnoentities($templateKeys['subject']);
                }
                if (property_exists($template, 'subject')) {
                        $template->subject = $langs->transnoentities($templateKeys['subject']);
                }
                if (property_exists($template, 'content')) {
                        $template->content = $langs->transnoentities($templateKeys['body']);
                }
                if (property_exists($template, 'lang')) {
                        $template->lang = '';
                }
                if (property_exists($template, 'private')) {
                        $template->private = 0;
                }
                if (property_exists($template, 'fk_user')) {
                        $template->fk_user = (int) $actionUser->id;
                }
                if (property_exists($template, 'active')) {
                        $template->active = 1;
                }
                if (property_exists($template, 'enabled')) {
                        $template->enabled = 1;
                }
                if (property_exists($template, 'email_from')) {
                        $template->email_from = '';
                }
                if (property_exists($template, 'email_to')) {
                        $template->email_to = '';
                }
                if (property_exists($template, 'email_cc')) {
                        $template->email_cc = '';
                }
                if (property_exists($template, 'email_bcc')) {
                        $template->email_bcc = '';
                }
                if (property_exists($template, 'joinfiles')) {
                        $template->joinfiles = 0;
                }
                if (property_exists($template, 'position')) {
                        $template->position = 0;
                }

                if (method_exists($template, 'create')) {
                        $res = $template->create($actionUser);
                        if ($res > 0) {
                                return $template;
                        }

                        dol_syslog(__METHOD__.': failed to create default template for '.$action.' - '.(!empty($template->error) ? $template->error : 'unknown error'), LOG_WARNING);
                }

		return null;
		}

		/**
		 * Build notification signature depending on Dolibarr configuration.
		 *
		 * @param Translate $langs
		 * @param Conf      $conf
		 *
		 * @return string
		 */
		protected function buildMailSignature($langs, $conf)
		{
		$signature = '';
		if (!empty($conf->global->MAIN_APPLICATION_TITLE)) {
		$signature = $langs->transnoentities('TimesheetWeekMailSignatureWithAppTitle', $conf->global->MAIN_APPLICATION_TITLE);
		} else {
		$companyName = !empty($conf->global->MAIN_INFO_SOCIETE_NOM) ? $conf->global->MAIN_INFO_SOCIETE_NOM : '';
		$signature = $langs->transnoentities('TimesheetWeekMailSignatureWithoutAppTitle', $companyName);
		}

		return $signature;
		}

		/**
		 * Build common notification metadata reused across automatic e-mails and business notifications.
		 *
		 * FR : Construit les métadonnées partagées par les e-mails automatiques et les notifications métiers.
		 * EN : Build the metadata shared by automatic e-mails and business notifications.
		 *
		 * @param TimesheetWeek $timesheet
		 * @param User          $actionUser
		 * @param Translate     $langs
		 * @param Conf          $conf
		 * @return array
		 */
		protected function buildNotificationData(TimesheetWeek $timesheet, User $actionUser, $langs, $conf)
		{
		$employee = $this->fetchUser($timesheet->fk_user);
		$validator = $this->fetchUser($timesheet->fk_user_valid);

		$employeeName = $employee ? $employee->getFullName($langs) : '';
		$validatorName = $validator ? $validator->getFullName($langs) : '';
		$actionUserName = $actionUser->getFullName($langs);
		$url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $timesheet->id;
		$mailSignature = $this->buildMailSignature($langs, $conf);

		$baseSubstitutions = array(
		'__TIMESHEETWEEK_REF__' => $timesheet->ref,
		'__TIMESHEETWEEK_WEEK__' => $timesheet->week,
		'__TIMESHEETWEEK_YEAR__' => $timesheet->year,
		'__TIMESHEETWEEK_URL__' => $url,
		'__TIMESHEETWEEK_EMPLOYEE_FULLNAME__' => $employeeName,
		'__TIMESHEETWEEK_VALIDATOR_FULLNAME__' => $validatorName,
		'__ACTION_USER_FULLNAME__' => $actionUserName,
		'__TIMESHEETWEEK_MAIL_SIGNATURE__' => $mailSignature,
		'__RECIPIENT_FULLNAME__' => '',
		);

		return array(
		'employee' => $employee,
		'validator' => $validator,
		'employee_name' => $employeeName,
		'validator_name' => $validatorName,
		'action_user_name' => $actionUserName,
		'mail_signature' => $mailSignature,
		'url' => $url,
		'base_substitutions' => $baseSubstitutions,
		);
		}
		}
