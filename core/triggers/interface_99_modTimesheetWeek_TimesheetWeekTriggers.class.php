<?php
/* Copyright (C) 2025-2026	Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class for TimesheetWeek events.
 */
class InterfaceTimesheetWeekTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler.
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
	 * Execute trigger.
	 *
	 * EN: TimesheetWeek must not send notifications directly from this trigger.
	 * EN: Dolibarr native Notification trigger (interface_50_modNotification_Notification)
	 * EN: receives business events and calls Notify::send($action, $object).
	 *
	 * @param string       $action Trigger code.
	 * @param CommonObject $object Object linked to trigger.
	 * @param User         $user   User who fired trigger.
	 * @param Translate    $langs  Translate instance.
	 * @param Conf         $conf   Global config.
	 *
	 * @return int 0 when everything is OK.
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (!isModEnabled('timesheetweek')) {
			return 0;
		}

		return 0;
	}
}
