<?php
/*
 * Copyright (C) 2025
 * Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU GPL v3 or later.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

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

		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;

		$year = (int) $object->year;
		$week = (int) $object->week;

		// EN: Fallback to current year and week when missing. FR: Repli sur l'année et la semaine courantes si absentes.
		if (empty($year) || empty($week)) {
			$ts = dol_now();
			$year = (int) dol_print_date($ts, '%Y');
			$week = (int) dol_print_date($ts, '%W'); // ISO week number
		}

		$numberingEntities = array($entity);
		$sharedEntities = getEntity('timesheetweeknumbering', 1, $object);
		foreach (explode(',', (string) $sharedEntities) as $sharedEntity) {
			$sharedEntity = (int) trim($sharedEntity);
			if ($sharedEntity > 0) {
				$numberingEntities[] = $sharedEntity;
			}
		}
		$numberingEntities = array_values(array_unique($numberingEntities));
		sort($numberingEntities, SORT_NUMERIC);

		$key = 'TIMESHEETWEEK_FHWEEKLY_COUNTER_'.$entity.'_'.$year;
		$current = 0;
		$sql = "SELECT value FROM ".MAIN_DB_PREFIX."const";
		$sql .= " WHERE name='".$db->escape($key)."' AND entity=".$entity;
		$sql .= " ".$db->plimit(1);
		$resql = $db->query($sql);
		if (!$resql) {
			return '';
		}
		$constRow = $db->fetch_object($resql);
		$db->free($resql);
		if (is_object($constRow)) {
			$current = (int) $constRow->value;
		}

		$sql = "SELECT MAX(CAST(SUBSTRING_INDEX(ref, '-', -1) AS UNSIGNED)) AS max_sequence";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week";
		$sql .= " WHERE entity IN (".implode(',', $numberingEntities).")";
		$sql .= " AND ref REGEXP '^FH".$year."[0-9]{2}-[0-9]{3,}$'";
		$resql = $db->query($sql);
		if (!$resql) {
			return '';
		}
		$sequenceRow = $db->fetch_object($resql);
		$db->free($resql);
		$maxExisting = is_object($sequenceRow) ? (int) $sequenceRow->max_sequence : 0;
		$next = max($current, $maxExisting) + 1;

		// Keep the caller transaction open while persisting the owner-entity counter.
		$res = dolibarr_set_const($db, $key, $next, 'integer', 0, '', $entity);
		if ($res <= 0) {
			return '';
		}

		return sprintf('FH%04d%02d-%03d', $year, $week, $next);
	}
}
