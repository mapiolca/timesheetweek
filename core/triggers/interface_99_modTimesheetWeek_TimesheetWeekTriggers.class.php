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

/**
 * Trigger class for TimesheetWeek events.
 *
 * FR : Les e-mails de TIMESHEETWEEK_SUBMIT / TIMESHEETWEEK_APPROVE /
 *      TIMESHEETWEEK_REFUSE sont envoyés exclusivement par le module
 *      "Notifications" de Dolibarr (notify_def + NOTIFICATION_FIXEDEMAIL_*).
 * EN : Emails for TIMESHEETWEEK_SUBMIT / TIMESHEETWEEK_APPROVE /
 *      TIMESHEETWEEK_REFUSE are dispatched exclusively by the Dolibarr
 *      "Notifications" module (notify_def + NOTIFICATION_FIXEDEMAIL_*).
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
		return 0;
	}
}
