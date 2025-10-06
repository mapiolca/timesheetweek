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

                if (in_array($action, array('TIMESHEETWEEK_SUBMITTED', 'TIMESHEETWEEK_APPROVED', 'TIMESHEETWEEK_REFUSED'), true)) {
                        return $this->sendNotification($action, $object, $user, $langs, $conf);
                }

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

                if ($action === 'TIMESHEETWEEK_SUBMITTED') {
                        $subjectKey = 'TimesheetWeekNotificationSubmitSubject';
                        $bodyKey = 'TimesheetWeekNotificationSubmitBody';
                        $missingKey = 'TimesheetWeekNotificationValidatorFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateSubmitLabel',
                                'subject' => 'TimesheetWeekTemplateSubmitSubject',
                                'body' => 'TimesheetWeekTemplateSubmitBody',
                        );
                        $target = $this->fetchUser($timesheet->fk_user_valid);
                        if ($target) {
                                $recipients[] = $target;
                        }
                } elseif ($action === 'TIMESHEETWEEK_APPROVED') {
                        $subjectKey = 'TimesheetWeekNotificationApproveSubject';
                        $bodyKey = 'TimesheetWeekNotificationApproveBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateApproveLabel',
                                'subject' => 'TimesheetWeekTemplateApproveSubject',
                                'body' => 'TimesheetWeekTemplateApproveBody',
                        );
                        $target = $this->fetchUser($timesheet->fk_user);
                        if ($target) {
                                $recipients[] = $target;
                        }
                } elseif ($action === 'TIMESHEETWEEK_REFUSED') {
                        $subjectKey = 'TimesheetWeekNotificationRefuseSubject';
                        $bodyKey = 'TimesheetWeekNotificationRefuseBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $templateKeys = array(
                                'label' => 'TimesheetWeekTemplateRefuseLabel',
                                'subject' => 'TimesheetWeekTemplateRefuseSubject',
                                'body' => 'TimesheetWeekTemplateRefuseBody',
                        );
                        $target = $this->fetchUser($timesheet->fk_user);
                        if ($target) {
                                $recipients[] = $target;
                        }
                }

                if (empty($recipients)) {
                        dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationMissingRecipient', $langs->trans($missingKey)), LOG_WARNING);
                        return 0;
                }

                dol_include_once('/core/lib/functions2.lib.php');
                if (is_readable(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplates.class.php')) {
                        dol_include_once('/core/class/cemailtemplates.class.php');
                } else {
                        dol_include_once('/core/class/emailtemplates.class.php');
                }
                dol_include_once('/core/class/CMailFile.class.php');

                $templateClass = '';
                if (class_exists('CEmailTemplates')) {
                        $templateClass = 'CEmailTemplates';
                } elseif (class_exists('EmailTemplates')) {
                        $templateClass = 'EmailTemplates';
                }

                $employee = $this->fetchUser($timesheet->fk_user);
                $validator = $this->fetchUser($timesheet->fk_user_valid);
                $employeeName = $employee ? $employee->getFullName($langs) : '';
                $validatorName = $validator ? $validator->getFullName($langs) : '';
                $actionUserName = $actionUser->getFullName($langs);
                $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $timesheet->id;

                $sent = 0;

                foreach ($recipients as $recipient) {
                        if (empty($recipient->email)) {
                                dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationNoEmail', $recipient->getFullName($langs)), LOG_WARNING);
                                continue;
                        }

                        $substitutions = array(
                                '__TIMESHEETWEEK_REF__' => $timesheet->ref,
                                '__TIMESHEETWEEK_WEEK__' => $timesheet->week,
                                '__TIMESHEETWEEK_YEAR__' => $timesheet->year,
                                '__TIMESHEETWEEK_URL__' => $url,
                                '__TIMESHEETWEEK_EMPLOYEE_FULLNAME__' => $employeeName,
                                '__TIMESHEETWEEK_VALIDATOR_FULLNAME__' => $validatorName,
                                '__ACTION_USER_FULLNAME__' => $actionUserName,
                                '__RECIPIENT_FULLNAME__' => $recipient->getFullName($langs),
                        );

                        $template = null;
                        $tplResult = 0;
                        if (!empty($templateClass)) {
                                $template = new $templateClass($this->db);
                                $tplResult = $template->fetchByTrigger($action, $actionUser, $conf->entity);
                                if ($tplResult <= 0 && !empty($templateKeys)) {
                                        $template = $this->createDefaultTemplate($templateClass, $templateKeys, $action, $actionUser, $langs, $conf);
                                        if ($template) {
                                                $tplResult = 1;
                                        }
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
                                if ($action === 'TIMESHEETWEEK_SUBMITTED') {
                                        $subject = $langs->trans($subjectKey, $timesheet->ref);
                                        $message = $langs->trans(
                                                $bodyKey,
                                                $recipientName,
                                                $employeeName,
                                                $timesheet->ref,
                                                $timesheet->week,
                                                $timesheet->year,
                                                $url,
                                                $actionUserName
                                        );
                                } else {
                                        $subject = $langs->trans($subjectKey, $timesheet->ref);
                                        $message = $langs->trans(
                                                $bodyKey,
                                                $recipientName,
                                                $timesheet->ref,
                                                $timesheet->week,
                                                $timesheet->year,
                                                $actionUserName,
                                                $url,
                                                $actionUserName
                                        );
                                }
                        }

                        if (empty($subject) || empty($message)) {
                                dol_syslog(__METHOD__.': '.$langs->trans('TimesheetWeekNotificationMailError', 'Empty template'), LOG_WARNING);
                                continue;
                        }

                        $sendto = implode(',', array_unique(array_filter($sendtoList)));
                        $cc = implode(',', array_unique(array_filter($ccList)));
                        $bcc = implode(',', array_unique(array_filter($bccList)));

                        $mail = new CMailFile($subject, $sendto, $emailFrom, $message, array(), array(), array(), $cc, $bcc, 0, 0);
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
}
