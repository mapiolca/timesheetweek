<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Trigger class for TimesheetWeek native integrations.
 */
class InterfaceTimesheetWeekTriggers extends DolibarrTriggers
{
	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'timesheetweektriggers';
		$this->family = 'timesheetweek';
		$this->description = 'TimesheetWeek native events';
		$this->version = 'dolibarr';
		$this->picto = 'fa-calendar-check';
	}

	/**
	 * Execute trigger.
	 *
	 * @param string       $action Trigger code
	 * @param CommonObject $object Current object
	 * @param User         $user Current user
	 * @param Translate    $langs Translation handler
	 * @param Conf         $conf Dolibarr configuration
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (!isModEnabled('timesheetweek')) {
			return 0;
		}

		if (!($object instanceof TimesheetWeek)) {
			return 0;
		}

		$supportedTriggers = array(
			TimesheetWeek::TRIGGER_CREATE,
			TimesheetWeek::TRIGGER_UPDATE,
			TimesheetWeek::TRIGGER_DELETE,
			TimesheetWeek::TRIGGER_SUBMIT,
			TimesheetWeek::TRIGGER_APPROVE,
			TimesheetWeek::TRIGGER_REFUSE,
			TimesheetWeek::TRIGGER_SETDRAFT,
			TimesheetWeek::TRIGGER_SEAL,
			TimesheetWeek::TRIGGER_UNSEAL,
		);
		if (!in_array($action, $supportedTriggers, true)) {
			$legacyTriggers = array(
				'TIMESHEETWEEK_SUBMITTED',
				'TIMESHEETWEEK_APPROVED',
				'TIMESHEETWEEK_REFUSED',
				'TSWK_CREATE',
				'TSWK_SUBMIT',
				'TSWK_REOPEN',
				'TSWK_APPROVE',
				'TSWK_SEAL',
				'TSWK_UNSEAL',
				'TSWK_REFUSE',
				'TSWK_DELETE',
			);
			if (in_array($action, $legacyTriggers, true)) {
				dol_syslog(__METHOD__.': deprecated TimesheetWeek trigger ignored: '.$action, LOG_WARNING);
			}
			return 0;
		}

		if (!is_array($object->context)) {
			$object->context = array();
		}

		$object->module = 'timesheetweek';
		$object->element = 'timesheetweek';
		$object->context['elementtype'] = 'timesheetweek@timesheetweek';

		if (empty($object->actiontypecode)) {
			$object->actiontypecode = 'AC_OTH_AUTO';
		}
		if (empty($object->actionmsg2) && !empty($object->context['actionmsg2'])) {
			$object->actionmsg2 = $object->context['actionmsg2'];
		}
		if (empty($object->actionmsg) && !empty($object->context['actionmsg'])) {
			$object->actionmsg = $object->context['actionmsg'];
		}

		$result = $this->createAgendaEvent($action, $object, $user, $langs, $conf);
		if ($result < 0) {
			return -1;
		}

		return 0;
	}

	/**
	 * Create a native Agenda event when the matching automatic action is enabled.
	 *
	 * @param string       $action Trigger code
	 * @param TimesheetWeek $object Current object
	 * @param User         $user Current user
	 * @param Translate    $langs Translation handler
	 * @param Conf         $conf Dolibarr configuration
	 * @return int<-1,1>
	 */
	private function createAgendaEvent($action, $object, $user, $langs, $conf)
	{
		if (!isModEnabled('agenda') || !getDolGlobalInt('MAIN_AGENDA_ACTIONAUTO_'.$action)) {
			return 0;
		}
		if (empty($object->id)) {
			return 0;
		}
		if (!class_exists('ActionComm')) {
			dol_include_once('/comm/action/class/actioncomm.class.php');
		}
		if (!class_exists('ActionComm')) {
			return 0;
		}

		$elementtype = 'timesheetweek@timesheetweek';
		$objectid = (int) $object->id;
		$duplicateWindowStart = dol_now() - 60;

		$sql = "SELECT id FROM ".MAIN_DB_PREFIX."actioncomm";
		$sql .= " WHERE elementtype = '".$this->db->escape($elementtype)."'";
		$sql .= " AND fk_element = ".$objectid;
		$sql .= " AND code = '".$this->db->escape($action)."'";
		$sql .= " AND datep >= '".$this->db->idate($duplicateWindowStart)."'";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			$this->db->free($resql);
			return 0;
		}
		$this->db->free($resql);

		$langs->loadLangs(array('timesheetweek@timesheetweek', 'agenda'));

		$label = !empty($object->actionmsg2) ? (string) $object->actionmsg2 : $langs->trans('Notify_'.$action);
		if ($label === 'Notify_'.$action) {
			$label = $langs->trans('TimesheetWeekTriggerGeneric', $object->ref);
		}
		$note = !empty($object->actionmsg) ? (string) $object->actionmsg : $label;

		$agenda = new ActionComm($this->db);
		$agenda->type_code = 'AC_OTH_AUTO';
		$agenda->code = $action;
		$agenda->label = $label;
		$agenda->note_private = $note;
		$agenda->datep = dol_now();
		$agenda->datef = $agenda->datep;
		$agenda->percentage = -1;
		$agenda->elementtype = $elementtype;
		$agenda->fk_element = $objectid;
		$agenda->entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$agenda->userownerid = !empty($user->id) ? (int) $user->id : (!empty($object->fk_user) ? (int) $object->fk_user : 0);

		if (!empty($object->fk_user)) {
			$agenda->userassigned = array(
				(int) $object->fk_user => array('id' => (int) $object->fk_user),
			);
		}

		$result = $agenda->create($user);
		if ($result < 0) {
			$this->error = $agenda->error;
			$this->errors = $agenda->errors;
			return -1;
		}

		return 1;
	}
}
