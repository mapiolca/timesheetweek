<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Workflow email notification content for TimesheetWeek status steps.
 *
 * The native Notifications module sends the configured workflow event. This
 * class resolves the step-specific content from trigger_reason.
 */
class TimesheetWeekNotification
{
	public const TEMPLATE_TYPE = 'timesheetweek@timesheetweek';
	public const NATIVE_TEMPLATE_TYPE = 'timesheetweek_send';
	public const NATIVE_ROUTER_TEMPLATE_LABEL = 'Notification TimesheetWeek';
	public const NATIVE_ROUTER_TEMPLATE_LEGACY_LABEL = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER';
	public const NATIVE_UPDATE_TEMPLATE_CONSTANT = 'TIMESHEETWEEK_MODIFY_TEMPLATE';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return workflow reasons that can send a dedicated email.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function getWorkflowReasons()
	{
		return array(
			'submit' => array(
				'suffix' => 'SUBMIT',
				'label' => 'TimesheetWeekNotificationReasonSubmit',
				'description' => 'TimesheetWeekNotificationReasonSubmitHelp',
				'recipient' => 'validator',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_SUBMIT',
				'subject_key' => 'TimesheetWeekTemplateSubmitSubject',
				'body_key' => 'TimesheetWeekTemplateSubmitBody',
			),
			'approve' => array(
				'suffix' => 'APPROVE',
				'label' => 'TimesheetWeekNotificationReasonApprove',
				'description' => 'TimesheetWeekNotificationReasonApproveHelp',
				'recipient' => 'employee',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_APPROVE',
				'subject_key' => 'TimesheetWeekTemplateApproveSubject',
				'body_key' => 'TimesheetWeekTemplateApproveBody',
			),
			'refuse' => array(
				'suffix' => 'REFUSE',
				'label' => 'TimesheetWeekNotificationReasonRefuse',
				'description' => 'TimesheetWeekNotificationReasonRefuseHelp',
				'recipient' => 'employee',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_REFUSE',
				'subject_key' => 'TimesheetWeekTemplateRefuseSubject',
				'body_key' => 'TimesheetWeekTemplateRefuseBody',
			),
			'setdraft' => array(
				'suffix' => 'SETDRAFT',
				'label' => 'TimesheetWeekNotificationReasonSetdraft',
				'description' => 'TimesheetWeekNotificationReasonSetdraftHelp',
				'recipient' => 'employee',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_SETDRAFT',
				'subject_key' => 'TimesheetWeekTemplateSetdraftSubject',
				'body_key' => 'TimesheetWeekTemplateSetdraftBody',
			),
			'seal' => array(
				'suffix' => 'SEAL',
				'label' => 'TimesheetWeekNotificationReasonSeal',
				'description' => 'TimesheetWeekNotificationReasonSealHelp',
				'recipient' => 'employee',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_SEAL',
				'subject_key' => 'TimesheetWeekTemplateSealSubject',
				'body_key' => 'TimesheetWeekTemplateSealBody',
			),
			'unseal' => array(
				'suffix' => 'UNSEAL',
				'label' => 'TimesheetWeekNotificationReasonUnseal',
				'description' => 'TimesheetWeekNotificationReasonUnsealHelp',
				'recipient' => 'employee',
				'template_label' => 'TIMESHEETWEEK_NOTIFY_UNSEAL',
				'subject_key' => 'TimesheetWeekTemplateUnsealSubject',
				'body_key' => 'TimesheetWeekTemplateUnsealBody',
			),
		);
	}

	/**
	 * Get the enable constant name for a workflow reason.
	 *
	 * @param string $reason Workflow reason
	 * @return string
	 */
	public static function getEnableConstant($reason)
	{
		$definition = self::getReasonDefinition($reason);
		return empty($definition) ? '' : 'TIMESHEETWEEK_NOTIFICATION_'.$definition['suffix'].'_ENABLED';
	}

	/**
	 * Get the template constant name for a workflow reason.
	 *
	 * @param string $reason Workflow reason
	 * @return string
	 */
	public static function getTemplateConstant($reason)
	{
		$definition = self::getReasonDefinition($reason);
		return empty($definition) ? '' : 'TIMESHEETWEEK_NOTIFICATION_'.$definition['suffix'].'_EMAIL_TEMPLATE';
	}

	/**
	 * Return the native Notification template constant used by the generic UPDATE event.
	 *
	 * @return string
	 */
	public static function getNativeUpdateTemplateConstant()
	{
		return self::NATIVE_UPDATE_TEMPLATE_CONSTANT;
	}

	/**
	 * Return the router template label selected by the native Notification event.
	 *
	 * @return string
	 */
	public static function getNativeRouterTemplateLabel()
	{
		return self::NATIVE_ROUTER_TEMPLATE_LABEL;
	}

	/**
	 * Return the former technical router template label.
	 *
	 * @return string
	 */
	public static function getNativeRouterTemplateLegacyLabel()
	{
		return self::NATIVE_ROUTER_TEMPLATE_LEGACY_LABEL;
	}

	/**
	 * Return the native object template type used by Notify::send().
	 *
	 * @return string
	 */
	public static function getNativeRouterTemplateType()
	{
		return self::NATIVE_TEMPLATE_TYPE;
	}

	/**
	 * Check whether native Notifications can be used in the current instance.
	 *
	 * @return bool
	 */
	public static function isNativeNotificationAvailable()
	{
		return (!defined('DOL_VERSION') || version_compare(DOL_VERSION, '20.0.0', '>=')) && function_exists('isModEnabled') && isModEnabled('notification');
	}

	/**
	 * Return one reason definition.
	 *
	 * @param string $reason Workflow reason
	 * @return array<string,string>
	 */
	public static function getReasonDefinition($reason)
	{
		$reasons = self::getWorkflowReasons();
		return isset($reasons[$reason]) ? $reasons[$reason] : array();
	}

	/**
	 * List workflow email templates available for the current entity.
	 *
	 * @param DoliDB $db Database handler
	 * @param int    $entity Entity identifier
	 * @return array<int,string>
	 */
	public static function getEmailTemplateOptions($db, $entity)
	{
		$entity = (int) $entity;
		if ($entity <= 0) {
			$entity = 1;
		}

		$sql = 'SELECT rowid, label, lang, entity';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
		$sql .= " WHERE active = 1";
		$sql .= " AND type_template = '".$db->escape(self::TEMPLATE_TYPE)."'";
		$sql .= " AND entity IN (0, ".$entity.")";
		$sql .= ' ORDER BY label ASC, entity DESC';

		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}

		$options = array();
		while ($obj = $db->fetch_object($resql)) {
			$templateLabel = (string) $obj->label;
			if (!empty($obj->lang)) {
				$templateLabel .= ' ('.(string) $obj->lang.')';
			}
			$options[(int) $obj->rowid] = $templateLabel;
		}
		$db->free($resql);

		return $options;
	}

	/**
	 * Build substitutions consumed by the native workflow router template.
	 *
	 * @param TimesheetWeek  $object Current timesheet
	 * @param Translate|null $outputlangs Output language
	 * @param User|null      $actionUser User who triggered the action
	 * @return array<string,string>
	 */
	public static function getNativeNotificationSubstitutions(TimesheetWeek $object, $outputlangs = null, $actionUser = null)
	{
		$service = new self($object->db);
		$content = $service->getNativeNotificationContent($object, $outputlangs, $actionUser);
		$substitutions = !empty($content['substitutions']) && is_array($content['substitutions']) ? $content['substitutions'] : array();

		$substitutions['__TIMESHEETWEEK_NOTIFICATION_SUBJECT__'] = !empty($content['subject']) ? (string) $content['subject'] : '';
		$substitutions['__TIMESHEETWEEK_NOTIFICATION_BODY__'] = !empty($content['body']) ? (string) $content['body'] : '';
		$substitutions['__TIMESHEETWEEK_NOTIFICATION_REASON__'] = !empty($content['reason']) ? (string) $content['reason'] : '';
		$substitutions['__TIMESHEETWEEK_NOTIFICATION_REASON_LABEL__'] = !empty($content['reason_label']) ? (string) $content['reason_label'] : '';
		$substitutions['__TIMESHEETWEEK_NOTIFICATION_TEMPLATE_ID__'] = !empty($content['template_id']) ? (string) $content['template_id'] : '';

		return $substitutions;
	}

	/**
	 * Resolve the subject and body inserted into the native Notification router template.
	 *
	 * @param TimesheetWeek  $object Current timesheet
	 * @param Translate|null $outputlangs Output language
	 * @param User|null      $actionUser User who triggered the action
	 * @return array{subject:string,body:string,reason:string,reason_label:string,template_id:int,substitutions:array<string,string>}
	 */
	public function getNativeNotificationContent(TimesheetWeek $object, $outputlangs = null, $actionUser = null)
	{
		global $langs, $user, $conf;

		$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
		if ($trans instanceof Translate) {
			$trans->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));
		}

		if (!($actionUser instanceof User) && isset($user) && $user instanceof User) {
			$actionUser = $user;
		}

		$reason = is_array($object->context) && !empty($object->context['trigger_reason']) ? (string) $object->context['trigger_reason'] : '';
		$definition = self::getReasonDefinition($reason);
		if (empty($definition)) {
			$definition = array(
				'label' => 'Notify_TIMESHEETWEEK_MODIFY',
				'description' => 'TimesheetWeekCompatibilityNotificationsDesc',
				'recipient' => 'employee',
				'template_label' => '',
				'subject_key' => 'Notify_TIMESHEETWEEK_MODIFY',
				'body_key' => 'TimesheetWeekNativeNotificationDefaultBody',
			);
		}

		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$templateConstant = self::getTemplateConstant($reason);
		$templateId = ($templateConstant !== '') ? getDolGlobalInt($templateConstant, 0, $entity) : 0;
		$template = $templateId > 0 ? $this->fetchEmailTemplate($templateId, $entity) : array();
		$templateLabel = !empty($template['label']) ? (string) $template['label'] : '';
		$routerMirrorLabel = self::NATIVE_ROUTER_TEMPLATE_LABEL.' ['.self::NATIVE_TEMPLATE_TYPE.']';
		$legacyRouterMirrorLabel = self::NATIVE_ROUTER_TEMPLATE_LEGACY_LABEL.' ['.self::NATIVE_TEMPLATE_TYPE.']';
		if (in_array($templateLabel, array(self::NATIVE_ROUTER_TEMPLATE_LABEL, self::NATIVE_ROUTER_TEMPLATE_LEGACY_LABEL, $routerMirrorLabel, $legacyRouterMirrorLabel), true)) {
			$template = array();
		}

		$defaultRecipients = $this->resolveDefaultRecipients($object, !empty($definition['recipient']) ? $definition['recipient'] : 'employee');
		$recipientUser = !empty($defaultRecipients) ? reset($defaultRecipients) : null;
		$substitutions = $this->buildSubstitutions($object, $actionUser, $recipientUser instanceof User ? $recipientUser : null, $reason, $definition, $trans, false);

		$subject = !empty($template['topic']) ? $template['topic'] : $this->getDefaultTemplateText($definition['subject_key'], $object, $actionUser, $trans);
		$body = !empty($template['content']) ? $template['content'] : $this->getDefaultTemplateText($definition['body_key'], $object, $actionUser, $trans);

		$subject = make_substitutions($subject, $substitutions);
		$body = make_substitutions(str_replace('\\n', "\n", $body), $substitutions);

		$subject = dol_string_nohtmltag(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$body = $this->formatNotificationBodyAsHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));

		$reasonLabel = !empty($substitutions['__TIMESHEETWEEK_TRIGGER_REASON_LABEL__']) ? $substitutions['__TIMESHEETWEEK_TRIGGER_REASON_LABEL__'] : $reason;
		if ($subject === '') {
			$subject = $trans instanceof Translate ? $trans->transnoentities('Notify_TIMESHEETWEEK_MODIFY') : 'Timesheet updated';
		}
		if ($body === '') {
			$body = $trans instanceof Translate ? $trans->transnoentities('TimesheetWeekNativeNotificationDefaultBody') : 'The timesheet __TIMESHEETWEEK_REF__ was updated.';
			$body = make_substitutions(str_replace('\\n', "\n", $body), $substitutions);
			$body = $this->formatNotificationBodyAsHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
		}

		return array(
			'subject' => $subject,
			'body' => $body,
			'reason' => $reason,
			'reason_label' => $reasonLabel,
			'template_id' => $templateId,
			'substitutions' => $substitutions,
		);
	}

	/**
	 * Send a workflow notification from a TimesheetWeek trigger context.
	 *
	 * @param TimesheetWeek $object Current timesheet
	 * @param User          $actionUser User who triggered the action
	 * @return int 1 if sent, 0 if skipped or logged as non-blocking failure
	 */
	public static function sendForTriggerContext(TimesheetWeek $object, User $actionUser)
	{
		if (!getDolGlobalInt('TIMESHEETWEEK_ENABLE_LEGACY_NOTIFICATION_HELPERS', 0)) {
			return 0;
		}

		if (!is_array($object->context)) {
			return 0;
		}

		$reason = !empty($object->context['trigger_reason']) ? (string) $object->context['trigger_reason'] : '';
		if ($reason === '' || !isset(self::getWorkflowReasons()[$reason])) {
			return 0;
		}

		$service = new self($object->db);
		return $service->sendWorkflowNotification($object, $actionUser, $reason);
	}

	/**
	 * Send one configured workflow notification.
	 *
	 * @param TimesheetWeek $object Current timesheet
	 * @param User          $actionUser User who triggered the action
	 * @param string        $reason Workflow reason
	 * @return int 1 if sent, 0 otherwise
	 */
	public function sendWorkflowNotification(TimesheetWeek $object, User $actionUser, $reason)
	{
		global $conf, $langs;

		if (!getDolGlobalInt('TIMESHEETWEEK_ENABLE_LEGACY_NOTIFICATION_HELPERS', 0)) {
			return 0;
		}

		$definition = self::getReasonDefinition($reason);
		if (empty($definition)) {
			return 0;
		}

		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$enableConstant = self::getEnableConstant($reason);
		if ($enableConstant === '' || !getDolGlobalInt($enableConstant, 0, $entity)) {
			return 0;
		}

		$templateConstant = self::getTemplateConstant($reason);
		$templateId = ($templateConstant !== '') ? getDolGlobalInt($templateConstant, 0, $entity) : 0;
		if ($templateId <= 0) {
			dol_syslog(__METHOD__.': missing email template for '.$reason.' on timesheet '.$object->id, LOG_WARNING);
			return 0;
		}

		$template = $this->fetchEmailTemplate($templateId, $entity);
		if (empty($template)) {
			dol_syslog(__METHOD__.': unable to fetch email template id='.$templateId.' for '.$reason, LOG_WARNING);
			return 0;
		}

		if ($langs instanceof Translate) {
			$langs->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));
		}

		$defaultRecipients = $this->resolveDefaultRecipients($object, $definition['recipient']);
		$recipientUser = !empty($defaultRecipients) ? reset($defaultRecipients) : null;
		$substitutions = $this->buildSubstitutions($object, $actionUser, $recipientUser instanceof User ? $recipientUser : null, $reason, $definition, $langs, true);

		$sendTo = !empty($template['email_to']) ? make_substitutions($template['email_to'], $substitutions) : '';
		if ($sendTo === '') {
			$sendTo = $this->getRecipientEmailList($defaultRecipients);
		}
		if ($sendTo === '') {
			$label = $langs instanceof Translate ? $langs->trans($definition['label']) : $reason;
			dol_syslog(__METHOD__.': '.$label.' - missing recipient for timesheet '.$object->id, LOG_WARNING);
			return 0;
		}

		$subject = !empty($template['topic']) ? $template['topic'] : $this->getDefaultTemplateText($definition['subject_key'], $object, $actionUser);
		$body = !empty($template['content']) ? $template['content'] : $this->getDefaultTemplateText($definition['body_key'], $object, $actionUser);
		$subject = make_substitutions($subject, $substitutions);
		$body = make_substitutions(str_replace('\\n', "\n", $body), $substitutions);

		$subject = dol_string_nohtmltag(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
		$isHtmlBody = (!empty($body) && preg_match('/<[^>]+>/', $body)) ? 1 : 0;
		$preparedBody = $isHtmlBody ? $body : dol_string_nohtmltag($body);

		$from = !empty($template['email_from']) ? make_substitutions($template['email_from'], $substitutions) : '';
		if ($from === '') {
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		}
		if ($from === '') {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}

		$cc = !empty($template['email_tocc']) ? make_substitutions($template['email_tocc'], $substitutions) : '';
		$bcc = !empty($template['email_tobcc']) ? make_substitutions($template['email_tobcc'], $substitutions) : '';

		$mail = new CMailFile($subject, $sendTo, $from, $preparedBody, array(), array(), array(), $cc, $bcc, 0, $isHtmlBody, '', '', '', 'utf-8');
		$result = $mail->sendfile();
		if (!$result) {
			$error = !empty($mail->error) ? $mail->error : 'unknown error';
			dol_syslog(__METHOD__.': unable to send workflow notification '.$reason.' for timesheet '.$object->id.': '.$error, LOG_ERR);
			return 0;
		}

		dol_syslog(__METHOD__.': workflow notification '.$reason.' sent for timesheet '.$object->id, LOG_INFO);
		return 1;
	}

	/**
	 * Fetch one email template while tolerating schema differences between Dolibarr versions.
	 *
	 * @param int $templateId Template row id
	 * @param int $entity Entity identifier
	 * @return array<string,string>
	 */
	protected function fetchEmailTemplate($templateId, $entity)
	{
		$templateId = (int) $templateId;
		$entity = (int) $entity;
		if ($templateId <= 0) {
			return array();
		}

		$columns = array('rowid', 'module', 'type_template', 'label', 'topic', 'content', 'lang', 'entity', 'active');
		foreach (array('email_from', 'email_to', 'email_tocc', 'email_tobcc', 'joinfiles') as $candidateColumn) {
			if ($this->emailTemplateColumnExists($candidateColumn)) {
				$columns[] = $candidateColumn;
			}
		}

		$sql = 'SELECT '.implode(', ', $columns);
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
		$sql .= ' WHERE rowid = '.$templateId;
		$sql .= ' AND active = 1';
		$sql .= " AND (module = 'timesheetweek' OR type_template = '".$this->db->escape(self::TEMPLATE_TYPE)."')";
		$sql .= ' AND entity IN (0, '.$entity.')';
		$sql .= ' ORDER BY entity DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$template = array();
		$obj = $this->db->fetch_object($resql);
		if ($obj) {
			foreach ($columns as $column) {
				$template[$column] = isset($obj->{$column}) ? (string) $obj->{$column} : '';
			}
		}
		$this->db->free($resql);

		return $template;
	}

	/**
	 * Tell if a column exists on c_email_templates.
	 *
	 * @param string $column Column name
	 * @return bool
	 */
	protected function emailTemplateColumnExists($column)
	{
		static $cache = array();

		$column = preg_replace('/[^a-z0-9_]/i', '', (string) $column);
		if ($column === '') {
			return false;
		}
		if (array_key_exists($column, $cache)) {
			return $cache[$column];
		}

		$sql = 'SHOW COLUMNS FROM '.MAIN_DB_PREFIX."c_email_templates LIKE '".$this->db->escape($column)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$cache[$column] = false;
			return false;
		}

		$cache[$column] = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $cache[$column];
	}

	/**
	 * Resolve default recipients for a workflow reason.
	 *
	 * @param TimesheetWeek $object Current timesheet
	 * @param string        $recipientMode Recipient mode
	 * @return array<int,User>
	 */
	protected function resolveDefaultRecipients(TimesheetWeek $object, $recipientMode)
	{
		$userIds = array();
		if ($recipientMode === 'validator' && !empty($object->fk_user_valid)) {
			$userIds[] = (int) $object->fk_user_valid;
		}
		if ($recipientMode === 'employee' && !empty($object->fk_user)) {
			$userIds[] = (int) $object->fk_user;
		}

		$recipients = array();
		foreach (array_values(array_unique($userIds)) as $userId) {
			$recipient = new User($this->db);
			if ($recipient->fetch((int) $userId) > 0) {
				$recipients[(int) $recipient->id] = $recipient;
			}
		}

		return $recipients;
	}

	/**
	 * Build a comma-separated recipient list.
	 *
	 * @param array<int,User> $recipients Recipient users
	 * @return string
	 */
	protected function getRecipientEmailList(array $recipients)
	{
		$emails = array();
		foreach ($recipients as $recipient) {
			$email = is_object($recipient) && !empty($recipient->email) ? trim((string) $recipient->email) : '';
			if ($email !== '' && !in_array($email, $emails, true)) {
				$emails[] = $email;
			}
		}

		return implode(',', $emails);
	}

	/**
	 * Build substitutions available to workflow email templates.
	 *
	 * @param TimesheetWeek        $object Current timesheet
	 * @param User|null            $actionUser User who triggered the action
	 * @param User|null            $recipientUser Default recipient user
	 * @param string               $reason Workflow reason
	 * @param array<string,string> $definition Reason definition
	 * @param Translate|null       $outputlangs Output language
	 * @param bool                 $includeCommonSubstitutions Include module substitutions
	 * @return array<string,string>
	 */
	protected function buildSubstitutions(TimesheetWeek $object, $actionUser, $recipientUser, $reason, array $definition, $outputlangs = null, $includeCommonSubstitutions = true)
	{
		global $langs;

		$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
		$employee = $this->fetchUser((int) $object->fk_user);
		$validator = $this->fetchUser((int) $object->fk_user_valid);
		$urlRaw = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $object->id;
		$urlHtml = '<a href="'.dol_escape_htmltag($urlRaw).'">'.dol_escape_htmltag($urlRaw).'</a>';

		$oldStatus = is_array($object->context) && array_key_exists('old_status', $object->context) ? $object->context['old_status'] : null;
		$newStatus = is_array($object->context) && array_key_exists('new_status', $object->context) ? $object->context['new_status'] : (int) $object->status;
		$motif = is_array($object->context) && !empty($object->context['timesheetweek_motif']) ? (string) $object->context['timesheetweek_motif'] : (string) $object->motif;
		$signature = getDolGlobalString('MAIN_APPLICATION_TITLE', '');
		if ($signature === '') {
			$signature = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', '');
		}

		$substitutions = array();
		if ($trans instanceof Translate && function_exists('getCommonSubstitutionArray')) {
			$substitutions = getCommonSubstitutionArray($trans, 0, null, $object);
		}
		if ($includeCommonSubstitutions && $trans instanceof Translate && function_exists('complete_substitutions_array')) {
			complete_substitutions_array($substitutions, $trans, $object);
		}

		$substitutions['__ID__'] = (string) $object->id;
		$substitutions['__REF__'] = (string) $object->ref;
		$substitutions['__LABEL__'] = (string) $object->ref;
		$substitutions['__TIMESHEETWEEK_ID__'] = (string) $object->id;
		$substitutions['__TIMESHEETWEEK_REF__'] = (string) $object->ref;
		$substitutions['__TIMESHEETWEEK_WEEK__'] = (string) $object->week;
		$substitutions['__TIMESHEETWEEK_YEAR__'] = (string) $object->year;
		$substitutions['__TIMESHEETWEEK_STATUS__'] = $this->getStatusLabel((int) $object->status);
		$substitutions['__TIMESHEETWEEK_OLD_STATUS__'] = ($oldStatus !== null ? $this->getStatusLabel((int) $oldStatus) : '');
		$substitutions['__TIMESHEETWEEK_NEW_STATUS__'] = ($newStatus !== null ? $this->getStatusLabel((int) $newStatus) : '');
		$substitutions['__TIMESHEETWEEK_TRIGGER_REASON__'] = $reason;
		$substitutions['__TIMESHEETWEEK_TRIGGER_REASON_LABEL__'] = $trans instanceof Translate ? $trans->trans($definition['label']) : $reason;
		$substitutions['__TIMESHEETWEEK_URL__'] = $urlHtml;
		$substitutions['__TIMESHEETWEEK_URL_RAW__'] = $urlRaw;
		$substitutions['__TIMESHEETWEEK_MOTIF__'] = $motif;
		$substitutions['__TIMESHEETWEEK_MAIL_SIGNATURE__'] = $signature;

		$substitutions['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__'] = $employee instanceof User ? $employee->getFullName($trans) : '';
		$substitutions['__TIMESHEETWEEK_EMPLOYEE_NAME__'] = $substitutions['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__'];
		$substitutions['__TIMESHEETWEEK_EMPLOYEE_EMAIL__'] = $employee instanceof User ? (string) $employee->email : '';
		$substitutions['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $validator instanceof User ? $validator->getFullName($trans) : '';
		$substitutions['__TIMESHEETWEEK_VALIDATOR_NAME__'] = $substitutions['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'];
		$substitutions['__TIMESHEETWEEK_VALIDATOR_EMAIL__'] = $validator instanceof User ? (string) $validator->email : '';
		$substitutions['__ACTION_USER_FULLNAME__'] = $actionUser instanceof User ? $actionUser->getFullName($trans) : '';
		$substitutions['__ACTION_USER_EMAIL__'] = $actionUser instanceof User ? (string) $actionUser->email : '';
		$substitutions['__RECIPIENT_FULLNAME__'] = $recipientUser instanceof User ? $recipientUser->getFullName($trans) : '';
		$substitutions['__RECIPIENT_EMAIL__'] = $recipientUser instanceof User ? (string) $recipientUser->email : '';

		return $substitutions;
	}

	/**
	 * Fetch one user.
	 *
	 * @param int $userId User id
	 * @return User|null
	 */
	protected function fetchUser($userId)
	{
		$userId = (int) $userId;
		if ($userId <= 0) {
			return null;
		}

		$user = new User($this->db);
		if ($user->fetch($userId) <= 0) {
			return null;
		}

		return $user;
	}

	/**
	 * Return a plain status label.
	 *
	 * @param int $status Status code
	 * @return string
	 */
	protected function getStatusLabel($status)
	{
		global $langs;

		if (method_exists('TimesheetWeek', 'getStatusBadgeDefinition')) {
			$definition = TimesheetWeek::getStatusBadgeDefinition((int) $status, $langs);
			if (!empty($definition['label'])) {
				return (string) $definition['label'];
			}
		}

		return (string) $status;
	}

	/**
	 * Fallback text when a template has no subject or content.
	 *
	 * @param string        $translationKey Translation key
	 * @param TimesheetWeek $object Current timesheet
	 * @param User|null     $actionUser Action user
	 * @param Translate|null $outputlangs Output language
	 * @return string
	 */
	protected function getDefaultTemplateText($translationKey, TimesheetWeek $object, $actionUser = null, $outputlangs = null)
	{
		global $langs;

		$trans = ($outputlangs instanceof Translate) ? $outputlangs : $langs;
		if (!($trans instanceof Translate)) {
			return '';
		}

		$translated = $trans->transnoentities($translationKey);
		return ($translated === $translationKey) ? '' : $translated;
	}

	/**
	 * Convert a plain notification body into HTML while keeping custom HTML untouched.
	 *
	 * @param string $body Notification body after substitutions
	 * @return string
	 */
	protected function formatNotificationBodyAsHtml($body)
	{
		$body = $this->normalizeNotificationLineBreaks($body);
		if ($body === '') {
			return '';
		}

		if (preg_match('/<\s*(a|br|div|p|span|table|tbody|thead|tr|td|th|ul|ol|li|strong|em|b|i)\b/i', $body)) {
			return '<div style="white-space:pre-wrap">'.$this->convertNotificationLineBreaksToHtml($body).'</div>';
		}

		return '<div style="white-space:pre-wrap">'.$this->convertNotificationLineBreaksToHtml($this->escapeTextWithLinks($body)).'</div>';
	}

	/**
	 * Normalize real and escaped line breaks from templates.
	 *
	 * @param string $body Notification body
	 * @return string
	 */
	protected function normalizeNotificationLineBreaks($body)
	{
		$body = str_replace(array("\r\n", "\r"), "\n", (string) $body);
		$body = str_replace(array('\\\\r\\\\n', '\\\\n', '\\\\r', '\\r\\n', '\\n', '\\r'), "\n", $body);

		return $body;
	}

	/**
	 * Convert normalized line breaks into HTML breaks for mail clients.
	 *
	 * @param string $html HTML fragment
	 * @return string
	 */
	protected function convertNotificationLineBreaksToHtml($html)
	{
		$html = $this->normalizeNotificationLineBreaks($html);

		return str_replace("\n", '<br>', $html);
	}

	/**
	 * Escape text and turn raw URLs into clickable links.
	 *
	 * @param string $text Plain text
	 * @return string
	 */
	protected function escapeTextWithLinks($text)
	{
		$html = '';
		$offset = 0;
		$matches = array();
		preg_match_all('~https?://[^\s<>"\']+~u', $text, $matches, PREG_OFFSET_CAPTURE);

		foreach ($matches[0] as $match) {
			$url = (string) $match[0];
			$position = (int) $match[1];
			if ($position < $offset) {
				continue;
			}

			$html .= dol_escape_htmltag(substr($text, $offset, $position - $offset));

			$cleanUrl = rtrim($url, '.,;:)');
			$trailing = substr($url, strlen($cleanUrl));
			if ($cleanUrl !== '') {
				$html .= '<a href="'.dol_escape_htmltag($cleanUrl).'">'.dol_escape_htmltag($cleanUrl).'</a>';
			}
			$html .= dol_escape_htmltag($trailing);

			$offset = $position + strlen($url);
		}

		$html .= dol_escape_htmltag(substr($text, $offset));

		return $html;
	}
}
