<?php
/* Copyright (C) 2025
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU GPL v3 or later.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Numbering module for TimesheetWeek
 * Pattern: FHyyyyss-XXX (year + ISO week + 3-digits counter reset every year)
 */
class mod_timesheetweek_fhweekly
{
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $prefix = 'FH';

	/**
	 * @return string
	 */
	public function info()
	{
		return 'FHyyyyss-XXX (compteur réinitialisé chaque année)';
	}

	/**
	 * @return int 1 if enabled
	 */
	public function canBeActivated()
	{
		return 1;
	}

	/**
	 * @return string
	 */
	public function getExample()
	{
		return 'FH202540-001';
	}

	/**
	 * Get next value
	 * Counter is stored in constants per entity and year: TIMESHEETWEEK_FHWEEKLY_COUNTER_{entity}_{year}
	 *
	 * @param  TimesheetWeek $object
	 * @return string                  Next ref or empty string on error
	 */
	public function getNextValue($object)
	{
		global $db, $conf;

		$entity = (int) $conf->entity;

		$year = (int) $object->year;
		$week = (int) $object->week;

		// fallback si non renseigné
		if (empty($year) || empty($week)) {
            $ts   = dol_now();
            $year = (int) dol_print_date($ts, '%Y');
            $week = (int) dol_print_date($ts, '%W'); // ISO week number
		}

		$key = 'TIMESHEETWEEK_FHWEEKLY_COUNTER_'.$entity.'_'.$year;

		$db->begin();

		$current = (int) getDolGlobalInt($key, 0);
		$next = $current + 1;

		$res = dolibarr_set_const($db, $key, $next, 'integer', 0, '', $entity);
		if ($res <= 0) {
			$db->rollback();
			return '';
		}

		$db->commit();

		return sprintf('FH%04d%02d-%03d', $year, $week, $next);
	}
}
