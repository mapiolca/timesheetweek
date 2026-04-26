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
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Trigger class for TimesheetWeek.
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
	 * Execute trigger.
	 *
	 * @param string       $action Action code
	 * @param CommonObject $object Trigger object
	 * @param User         $user Trigger user
	 * @param Translate    $langs Translation handler
	 * @param Conf         $conf Configuration handler
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

		return 0;
	}
}
