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
		$this->description = 'TimesheetWeek CRUD events';
		$this->version = 'dolibarr';
		$this->picto = 'bookcal@timesheetweek';
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

		$crudTriggers = array(
			TimesheetWeek::TRIGGER_CREATE,
			TimesheetWeek::TRIGGER_UPDATE,
			TimesheetWeek::TRIGGER_DELETE,
		);
		if (!in_array($action, $crudTriggers, true)) {
			$legacyTriggers = array(
				'TIMESHEETWEEK_SUBMIT',
				'TIMESHEETWEEK_APPROVE',
				'TIMESHEETWEEK_REFUSE',
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

		return 0;
	}
}
