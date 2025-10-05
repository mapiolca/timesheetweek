<?php
/* Copyright (C) 2025
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU GPL v3 or later.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweekline.class.php');

class TimesheetWeek extends CommonObject
{
	/** @var string Dolibarr element type */
	public $element = 'timesheetweek';
	/** @var string DB table without prefix */
	public $table_element = 'timesheet_week';

	// --- Statuts
	const STATUS_DRAFT     = 0;
	const STATUS_SUBMITTED = 1;
	// On préfère APPROVED, mais on garde compat avec VALIDATED (si déjà utilisé)
	const STATUS_APPROVED  = 2;
	const STATUS_REFUSED   = 3;

	// --- Champs
	public $id;
	public $entity;
	public $ref;
	public $fk_user;
	public $year;
	public $week;
	public $status;
	public $note;

	public $date_creation;
	public $date_validation;
	public $tms;

	public $fk_user_valid;

	public $total_hours = 0.0;
	public $overtime_hours = 0.0;

	/** @var TimesheetWeekLine[] */
	public $lines = array();

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->isextrafieldmanaged = 1;
	}

	/**
	 * Create
	 *
	 * @param User $user
	 * @return int >0 if OK
	 */
	public function create($user)
	{
		global $conf;

		$this->entity = (int) $conf->entity;
		$this->date_creation = dol_now();
		if (empty($this->ref)) $this->ref = '(PROV)';

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
		$sql.= "entity, ref, fk_user, year, week, status, note, date_creation, fk_user_valid, total_hours, overtime_hours";
		$sql.= ") VALUES (";
		$sql.= (int)$this->entity.", ";
		$sql.= "'".$this->db->escape($this->ref)."', ";
		$sql.= (int)$this->fk_user.", ";
		$sql.= (int)$this->year.", ";
		$sql.= (int)$this->week.", ";
		$sql.= (int)$this->status.", ";
		$sql.= (isset($this->note)?"'".$this->db->escape($this->note)."'":"NULL").", ";
		$sql.= "'".$this->db->idate($this->date_creation)."', ";
		$sql.= (!empty($this->fk_user_valid)?(int)$this->fk_user_valid:"NULL").", ";
		$sql.= (float)$this->total_hours.", ";
		$sql.= (float)$this->overtime_hours;
		$sql.= ")";

		$this->db->begin();
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->db->commit();

		return $this->id;
	}

	/**
	 * Fetch
	 *
	 * @param int $id
	 * @return int
	 */
	public function fetch($id)
	{
		$sql = "SELECT t.rowid, t.entity, t.ref, t.fk_user, t.year, t.week, t.status, t.note,";
		$sql.= " t.date_creation, t.date_validation, t.tms, t.fk_user_valid, t.total_hours, t.overtime_hours";
		$sql.= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql.= " WHERE t.rowid=".(int)$id;

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($res) == 0) return 0;

		$obj = $this->db->fetch_object($res);

		$this->id = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->ref = $obj->ref;
		$this->fk_user = (int) $obj->fk_user;
		$this->year = (int) $obj->year;
		$this->week = (int) $obj->week;
		$this->status = (int) $obj->status;
		$this->note = $obj->note;

		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->tms = $this->db->jdate($obj->tms);

		$this->fk_user_valid = (int) $obj->fk_user_valid;

		$this->total_hours = (float) $obj->total_hours;
		$this->overtime_hours = (float) $obj->overtime_hours;

		$this->db->free($res);

		// Load lines
		$this->loadLines();

		return 1;
	}

	/**
	 * Load lines into $this->lines
	 * @return void
	 */
	public function loadLines()
	{
		$this->lines = array();

		$sql = "SELECT l.rowid, l.fk_timesheet_week, l.fk_task, l.day_date, l.hours, l.zone, l.meal";
		$sql.= " FROM ".MAIN_DB_PREFIX."timesheet_week_line as l";
		$sql.= " WHERE l.fk_timesheet_week=".(int)$this->id;
		$sql.= " ORDER BY l.day_date ASC, l.fk_task ASC";

		$res = $this->db->query($sql);
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$line = new TimesheetWeekLine($this->db);
				$line->id = (int) $obj->rowid;
				$line->fk_timesheet_week = (int) $obj->fk_timesheet_week;
				$line->fk_task = (int) $obj->fk_task;
				$line->day_date = $obj->day_date; // string Y-m-d
				$line->hours = (float) $obj->hours;
				$line->zone = (int) $obj->zone;
				$line->meal = (int) $obj->meal;
				$this->lines[] = $line;
			}
			$this->db->free($res);
		}
	}

	/**
	 * Update
	 *
	 * @param User $user
	 * @return int
	 */
	public function update($user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql.= "ref = ".(isset($this->ref)?"'".$this->db->escape($this->ref)."'":"NULL");
		$sql.= ", fk_user = ".(int)$this->fk_user;
		$sql.= ", year = ".(int)$this->year;
		$sql.= ", week = ".(int)$this->week;
		$sql.= ", status = ".(int)$this->status;
		$sql.= ", note = ".(isset($this->note)?"'".$this->db->escape($this->note)."'":"NULL");
		$sql.= ", date_validation = ".(!empty($this->date_validation)?"'".$this->db->idate($this->date_validation)."'":"NULL");
		$sql.= ", fk_user_valid = ".(!empty($this->fk_user_valid)?(int)$this->fk_user_valid:"NULL");
		$sql.= ", total_hours = ".((float)$this->total_hours);
		$sql.= ", overtime_hours = ".((float)$this->overtime_hours);
		$sql.= " WHERE rowid=".(int)$this->id;

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Delete
	 * @param User $user
	 * @return int
	 */
	public function delete($user)
	{
		$this->db->begin();

		// delete lines
		$this->db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$this->id);

		$res = $this->db->query("DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid=".(int)$this->id);
		if (!$res) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Generate next reference using Dolibarr numbering module
	 *
	 * @return string Next ref or empty on error
	 */
	public function getNextRef()
	{
		global $conf;

		$modname = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_fhweekly');

		// Try custom dir first then core dir (if module was installed in core)
		$rel = '/timesheetweek/core/modules/timesheetweek/'.$modname.'.php';

		$paths = array(
			DOL_DOCUMENT_ROOT.'/custom'.$rel,
			DOL_DOCUMENT_ROOT.$rel
		);

		$found = false;
		foreach ($paths as $p) {
			if (file_exists($p)) {
				require_once $p;
				$found = true;
				break;
			}
		}
		if (!$found) return '';

		if (!class_exists($modname)) return '';

		$mod = new $modname($this->db);
		if (!method_exists($mod, 'getNextValue')) return '';

		return $mod->getNextValue($this);
	}

	/**
	 * Compute totals (total_hours and overtime_hours)
	 * @param float $weeklyContract Default 35 if not provided
	 * @return void
	 */
	public function computeTotals($weeklyContract = 35.0)
	{
		$total = 0.0;
		if (empty($this->lines)) $this->loadLines();
		foreach ($this->lines as $l) $total += (float) $l->hours;

		$this->total_hours = $total;
		$this->overtime_hours = max(0.0, $total - (float) $weeklyContract);
	}

	/**
	 * Submit the timesheet (set status to submitted and generate final reference if needed)
	 *
	 * @param User $user
	 * @param float $weeklyContract
	 * @return int
	 */
	public function submit($user, $weeklyContract = 35.0)
	{
		$this->db->begin();

		// Recompute totals before submission
		$this->computeTotals($weeklyContract);

		// Generate final ref if in provisional mode
		if (empty($this->ref) || $this->ref == '(PROV)' || strpos($this->ref, '(PROV)') === 0) {
			$nextref = $this->getNextRef();
			if (empty($nextref)) {
				$this->error = 'FailedToGenerateRef';
				$this->db->rollback();
				return -1;
			}
			$this->ref = $nextref;
		}

		$this->status = self::STATUS_SUBMITTED;

		$res = $this->update($user);
		if ($res <= 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Back to draft
	 * @param User $user
	 * @return int
	 */
	public function setDraft($user)
	{
		$this->status = self::STATUS_DRAFT;
		return $this->update($user);
	}

	/**
	 * Approve (a.k.a. validate/approve)
	 * @param User $user
	 * @param int|null $validatorId
	 * @return int
	 */
	public function approve($user, $validatorId = null)
	{
		$this->db->begin();

		if (!empty($validatorId)) $this->fk_user_valid = (int) $validatorId;
		$this->date_validation = dol_now();
		$this->status = self::STATUS_APPROVED; // 2

		$res = $this->update($user);
		if ($res <= 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Refuse
	 * @param User $user
	 * @param int|null $validatorId
	 * @return int
	 */
	public function refuse($user, $validatorId = null)
	{
		$this->db->begin();

		if (!empty($validatorId)) $this->fk_user_valid = (int) $validatorId;
		$this->date_validation = dol_now();
		$this->status = self::STATUS_REFUSED; // 3

		$res = $this->update($user);
		if ($res <= 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Get status label/picto
	 * @param int $mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		global $langs;

		$label = '';
		$picto = 'statut0';

		switch ((int)$this->status) {
			case self::STATUS_DRAFT:
				$label = $langs->trans('Draft');
				$picto = 'statut0';
				break;
			case self::STATUS_SUBMITTED:
				$label = $langs->trans('Submitted');
				$picto = 'statut3';
				break;
			case self::STATUS_APPROVED:
				$label = ($langs->trans('Approved')!='Approved'?$langs->trans('Approved'):'Approuvée');
				$picto = 'statut4';
				break;
			case self::STATUS_REFUSED:
				$label = $langs->trans('Refused');
				$picto = 'statut8';
				break;
		}

		return dolGetStatus($label, $picto, '', $mode);
	}

	/**
	 * Get URL
	 * @param int $withpicto
	 * @return string
	 */
	public function getNomUrl($withpicto = 0)
	{
		$link = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.$this->id;

		$label = $this->ref;
		$picto = img_object('', 'bookcal');

		$out = '<a href="'.$link.'">'.($withpicto ? $picto.' ' : '').dol_escape_htmltag($label).'</a>';

		return $out;
	}

	/**
	 * Return tasks assigned to a user (grouped elsewhere)
	 * @param int $userid
	 * @return array
	 */
	public function getAssignedTasks($userid)
	{
		$userid = (int) $userid;
		$out = array();

		$sql = "SELECT t.rowid as task_id, t.label as task_label";
		$sql.= ", p.rowid as project_id, p.ref as project_ref, p.title as project_title";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task as t";
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_projet";
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."element_contact as ec ON ec.element_id = t.rowid";
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact as ctc ON ctc.rowid = ec.fk_c_type_contact";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = ec.fk_socpeople";
		$sql.= " WHERE p.entity IN (".getEntity('project').")";
		$sql.= " AND ctc.element = 'project_task'";
		// Tous rôles possibles sur la tâche
		$sql.= " AND ec.fk_socpeople = ".$userid;
		$sql.= " ORDER BY p.ref, t.label";

		$res = $this->db->query($sql);
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$out[] = array(
					'task_id' => (int) $obj->task_id,
					'task_label' => $obj->task_label,
					'project_id' => (int) $obj->project_id,
					'project_ref' => $obj->project_ref,
					'project_title' => $obj->project_title
				);
			}
			$this->db->free($res);
		}

		return $out;
	}
}
