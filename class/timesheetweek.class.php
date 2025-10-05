<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License...
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

dol_include_once('/timesheetweek/class/timesheetweekline.class.php');

class TimesheetWeek extends CommonObject
{
	// Dolibarr meta
	public $element = 'timesheetweek';
	public $table_element = 'timesheet_week';
	public $picto = 'bookcal';
	public $ismultientitymanaged = 1;	// There is an entity field

	// Status
	const STATUS_DRAFT     = 0;
	const STATUS_SUBMITTED = 1;
	const STATUS_APPROVED  = 4; // "approuvée"
	const STATUS_REFUSED   = 6;

	// Properties
	public $id;
	public $ref;
	public $entity;
	public $fk_user;          // employee
	public $year;
	public $week;
	public $status;
	public $note;
	public $fk_user_valid;    // validator (user id)
	public $date_creation;
	public $tms;
	public $date_validation;

	public $total_hours = 0.0;      // total week hours
	public $overtime_hours = 0.0;   // overtime based on user weeklyhours

	/** @var TimesheetWeekLine[] */
	public $lines = array();

	public $errors = array();
	public $error = '';

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = self::STATUS_DRAFT;
	}

	/**
	 * Create
	 * @param User $user
	 * @return int    >0 new id, <0 error
	 */
	public function create($user)
	{
		global $conf;

		$this->error = '';
		$this->errors = array();

		$this->entity = (int) $conf->entity;
		$now = dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
		$sql .= "ref, entity, fk_user, year, week, status, note, date_creation, fk_user_valid, total_hours, overtime_hours";
		$sql .= ") VALUES (";
		$sql .= " '".$this->db->escape($this->ref ? $this->ref : '(PROV)')."',";
		$sql .= " ".((int) $this->entity).",";
		$sql .= " ".((int) $this->fk_user).",";
		$sql .= " ".((int) $this->year).",";
		$sql .= " ".((int) $this->week).",";
		$sql .= " ".((int) $this->status).",";
		$sql .= " ".($this->note !== null ? "'".$this->db->escape($this->note)."'" : "NULL").",";
		$sql .= " '".$this->db->idate($now)."',";
		$sql .= " ".(!empty($this->fk_user_valid) ? (int) $this->fk_user_valid : "NULL").",";
		$sql .= " ".((float) ($this->total_hours ?: 0)).",";
		$sql .= " ".((float) ($this->overtime_hours ?: 0));
		$sql .= ")";

		$this->db->begin();

		$res = $this->db->query($sql);
		if (!$res) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->date_creation = $now;

		// Replace provisional ref with (PROV<ID>)
		if (empty($this->ref) || strpos($this->ref, '(PROV') === 0) {
			$this->ref = '(PROV'.$this->id.')';
			$up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ref='".$this->db->escape($this->ref)."' WHERE rowid=".(int) $this->id;
			if (!$this->db->query($up)) {
				$this->db->rollback();
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Fetch by id or ref
	 * @param int|null $id
	 * @param string|null $ref
	 * @return int
	 */
	public function fetch($id = null, $ref = null)
	{
		$this->error = '';
		$this->errors = array();

		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_user, t.year, t.week, t.status, t.note, t.date_creation, t.tms, t.date_validation, t.fk_user_valid,";
		$sql .= " t.total_hours, t.overtime_hours";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE 1=1";
		if ($id) {
			$sql .= " AND t.rowid=".(int) $id;
		} elseif ($ref) {
			$sql .= " AND t.ref='".$this->db->escape($ref)."'";
		} else {
			$this->error = 'Missing parameter for fetch';
			return -1;
		}

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($res);
		$this->db->free($res);
		if (!$obj) return 0;

		$this->id = (int) $obj->rowid;
		$this->ref = $obj->ref;
		$this->entity = (int) $obj->entity;
		$this->fk_user = (int) $obj->fk_user;
		$this->year = (int) $obj->year;
		$this->week = (int) $obj->week;
		$this->status = (int) $obj->status;
		$this->note = $obj->note;
		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->tms = $this->db->jdate($obj->tms);
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->fk_user_valid = (int) $obj->fk_user_valid;
		$this->total_hours = (float) $obj->total_hours;
		$this->overtime_hours = (float) $obj->overtime_hours;

		$this->fetchLines();

		return 1;
	}

	/**
	 * Load lines into $this->lines
	 * @return int
	 */
	public function fetchLines()
	{
		$this->lines = array();

		if (empty($this->id)) return 0;

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE fk_timesheet_week=".(int) $this->id;
		$sql .= " ORDER BY day_date ASC, rowid ASC";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($res)) {
			$l = new TimesheetWeekLine($this->db);
			$l->fetch((int) $obj->rowid);
			$this->lines[] = $l;
		}
		$this->db->free($res);

		return count($this->lines);
	}

	/**
	 * @return TimesheetWeekLine[]
	 */
	public function getLines()
	{
		if ($this->lines === null || !is_array($this->lines) || !count($this->lines)) {
			$this->fetchLines();
		}
		return $this->lines;
	}

	/**
	 * Update core fields (not lines)
	 * @param User $user
	 * @return int
	 */
	public function update($user)
	{
		$now = dol_now();

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sets = array();
		if ($this->ref) $sets[] = "ref='".$this->db->escape($this->ref)."'";
		if ($this->fk_user) $sets[] = "fk_user=".(int) $this->fk_user;
		if ($this->year) $sets[] = "year=".(int) $this->year;
		if ($this->week) $sets[] = "week=".(int) $this->week;
		if ($this->status !== null) $sets[] = "status=".(int) $this->status;
		$sets[] = "note=".($this->note !== null ? "'".$this->db->escape($this->note)."'" : "NULL");
		$sets[] = "fk_user_valid=".(!empty($this->fk_user_valid) ? (int) $this->fk_user_valid : "NULL");
		$sets[] = "total_hours=".(float) ($this->total_hours ?: 0);
		$sets[] = "overtime_hours=".(float) ($this->overtime_hours ?: 0);
		$sets[] = "tms='".$this->db->idate($now)."'";

		$sql .= " ".implode(', ', $sets);
		$sql .= " WHERE rowid=".(int) $this->id;

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Delete (and its lines)
	 * @param User $user
	 * @return int
	 */
	public function delete($user)
	{
		$this->db->begin();

		$dl = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $this->id;
		if (!$this->db->query($dl)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid=".(int) $this->id;
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Compute totals and save into object (not DB)
	 * @return void
	 */
	public function computeTotals()
	{
		$total = 0.0;
		if (!is_array($this->lines) || !count($this->lines)) {
			$this->fetchLines();
		}
		foreach ($this->lines as $l) {
			$total += (float) $l->hours;
		}
		$this->total_hours = $total;

		// Weekly contracted hours from user
		$weekly = 35.0;
		if (!empty($this->fk_user)) {
			$u = new User($this->db);
			if ($u->fetch($this->fk_user) > 0) {
				if (!empty($u->weeklyhours)) $weekly = (float) $u->weeklyhours;
			}
		}
		$ot = $total - $weekly;
		$this->overtime_hours = ($ot > 0) ? $ot : 0.0;
	}

	/**
	 * Save totals into DB
	 * @return int
	 */
	public function updateTotalsInDB()
	{
		$this->computeTotals();
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET total_hours=".(float) $this->total_hours.", overtime_hours=".(float) $this->overtime_hours;
		$sql .= " WHERE rowid=".(int) $this->id;
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Has at least one line
	 * @return bool
	 */
	public function hasAtLeastOneLine()
	{
		if (is_array($this->lines) && count($this->lines)) return true;
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $this->id;
		$res = $this->db->query($sql);
		if (!$res) return false;
		$obj = $this->db->fetch_object($res);
		$this->db->free($res);
		return (!empty($obj->nb) && (int) $obj->nb > 0);
	}

	/**
	 * Submit -> set status to SUBMITTED and set definitive ref if needed
	 * @param User $user
	 * @return int
	 */
	public function submit($user)
	{
		if ($this->status != self::STATUS_DRAFT && $this->status != self::STATUS_REFUSED) {
			$this->error = 'BadStatusForSubmit';
			return -1;
		}
		if (!$this->hasAtLeastOneLine()) {
			$this->error = 'NoLineToSubmit';
			return -2;
		}

		$this->db->begin();

		// Set definitive ref if provisional
		if (empty($this->ref) || strpos($this->ref, '(PROV') === 0) {
			$newref = $this->generateDefinitiveRef();
			if (empty($newref)) {
				$this->db->rollback();
				$this->error = 'RefGenerationFailed';
				return -1;
			}
			$this->ref = $newref;
			$upref = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ref='".$this->db->escape($this->ref)."' WHERE rowid=".(int) $this->id;
			if (!$this->db->query($upref)) {
				$this->db->rollback();
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=".(int) self::STATUS_SUBMITTED." WHERE rowid=".(int) $this->id;
		if (!$this->db->query($up)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_SUBMITTED;

		$this->db->commit();
		return 1;
	}

	/**
	 * Revert to draft
	 * @param User $user
	 * @return int
	 */
	public function revertToDraft($user)
	{
		$up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=".(int) self::STATUS_DRAFT." WHERE rowid=".(int) $this->id;
		if (!$this->db->query($up)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->status = self::STATUS_DRAFT;
		return 1;
	}

	/**
	 * Approve
	 * @param User $user
	 * @return int
	 */
	public function approve($user)
	{
		$now = dol_now();

		$this->db->begin();

		// Set validator if different
		$setvalid = '';
		if (empty($this->fk_user_valid) || (int) $this->fk_user_valid !== (int) $user->id) {
			$setvalid = ", fk_user_valid=".(int) $user->id;
			$this->fk_user_valid = (int) $user->id;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " status=".(int) self::STATUS_APPROVED;
		$sql .= ", date_validation='".$this->db->idate($now)."'";
		$sql .= $setvalid;
		$sql .= " WHERE rowid=".(int) $this->id;

		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_APPROVED;
		$this->date_validation = $now;

		$this->db->commit();
		return 1;
	}

	/**
	 * Refuse
	 * @param User $user
	 * @return int
	 */
	public function refuse($user)
	{
		$now = dol_now();

		$this->db->begin();

		$setvalid = '';
		if (empty($this->fk_user_valid) || (int) $this->fk_user_valid !== (int) $user->id) {
			$setvalid = ", fk_user_valid=".(int) $user->id;
			$this->fk_user_valid = (int) $user->id;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " status=".(int) self::STATUS_REFUSED;
		$sql .= ", date_validation='".$this->db->idate($now)."'";
		$sql .= $setvalid;
		$sql .= " WHERE rowid=".(int) $this->id;

		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_REFUSED;
		$this->date_validation = $now;

		$this->db->commit();
		return 1;
	}

	/**
	 * Generate definitive reference using addon in conf TIMESHEETWEEK_ADDON
	 * fallback: FHyyyyss-XXX
	 * @return string|false
	 */
	public function generateDefinitiveRef()
	{
		global $conf, $langs;

		$langs->load("other");

		$module = getDolGlobalString('TIMESHEETWEEK_ADDON', 'mod_timesheetweek_advanced');
		$file = '/timesheetweek/core/modules/timesheetweek/'.$module.'.php';

		$classname = $module;
		dol_include_once($file);

		if (class_exists($classname)) {
			$mod = new $classname($this->db);
			if (method_exists($mod, 'getNextValue')) {
				$ref = $mod->getNextValue($this);
				if ($ref) return $ref;
			}
		}

		// Fallback
		$yyyy = (int) $this->year;
		$ww = str_pad((int) $this->week, 2, '0', STR_PAD_LEFT);

		$sql = "SELECT ref FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity=".(int) $conf->entity;
		$sql .= " AND year=".(int) $this->year." AND week=".(int) $this->week;
		$sql .= " AND ref LIKE 'FH".$yyyy.$ww."-%'";
		$sql .= " ORDER BY ref DESC";
		$sql .= " ".$this->db->plimit(1);

		$res = $this->db->query($sql);
		$seq = 0;
		if ($res) {
			$obj = $this->db->fetch_object($res);
			if ($obj && preg_match('/^FH'.$yyyy.$ww.'-(\d{3})$/', $obj->ref, $m)) {
				$seq = (int) $m[1];
			}
			$this->db->free($res);
		}
		$seq++;
		return 'FH'.$yyyy.$ww.'-'.str_pad($seq, 3, '0', STR_PAD_LEFT);
	}

	/**
	 * Return linked tasks assigned to user (project_task)
	 * @param int $userid
	 * @return array<int,array>   Each: task_id, task_label, project_id, project_ref, project_title
	 */
	public function getAssignedTasks($userid)
	{
		global $conf;

		$userid = (int) $userid;
		if ($userid <= 0) return array();

		// Several ways tasks can be assigned -> llx_element_contact with element='project_task'
		// Note: Some installs store user id into fk_socpeople (legacy)
		$sql = "SELECT t.rowid as task_id, t.label as task_label,";
		$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_contact ec ON ec.element_id = t.rowid";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ec.fk_socpeople"; // legacy mapping
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND ctc.element = 'project_task'";
		$sql .= " AND (ec.fk_socpeople = ".$userid." OR u.rowid = ".$userid.")";
		$sql .= " GROUP BY t.rowid, t.label, p.rowid, p.ref, p.title";
		$sql .= " ORDER BY p.ref, t.label";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$out = array();
		while ($obj = $this->db->fetch_object($res)) {
			$out[] = array(
				'task_id' => (int) $obj->task_id,
				'task_label' => (string) $obj->task_label,
				'project_id' => (int) $obj->project_id,
				'project_ref' => (string) $obj->project_ref,
				'project_title' => (string) $obj->project_title,
			);
		}
		$this->db->free($res);
		return $out;
	}

	/**
	 * URL to card
	 * @param int $withpicto
	 * @param string $option
	 * @param int $notooltip
	 * @param string $morecss
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$label = $this->ref;
		$url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.(int) $this->id;
		$link = '<a href="'.$url.'"'.($morecss ? ' class="'.$morecss.'"' : '').'>';
		$linkend = '</a>';

		$p = '';
		if ($withpicto) $p = img_object('', $this->picto);

		if ($withpicto == 2) return $link.$p.$linkend.' '.$link.dol_escape_htmltag($label).$linkend;
		if ($withpicto == 1) return $link.$p.$linkend;
		return $link.dol_escape_htmltag($label).$linkend;
	}

	/**
	 * Badge / labels
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode);
	}

	public static function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->loadLangs(array('timesheetweek@timesheetweek', 'other'));

		$label = '';
		$clsnum = 0;

		switch ((int) $status) {
			case self::STATUS_DRAFT:
				$label = $langs->trans("Draft");
				$clsnum = 0;
				break;
			case self::STATUS_SUBMITTED:
				$label = $langs->trans("Submitted");
				$clsnum = 1;
				break;
			case self::STATUS_APPROVED:
				$label = $langs->trans("Approved"); // "Approuvée"
				$clsnum = 4;
				break;
			case self::STATUS_REFUSED:
				$label = $langs->trans("Refused");
				$clsnum = 6;
				break;
			default:
				$label = $langs->trans("Unknown");
				$clsnum = 0;
		}

		if ((int) $mode === 5) {
			return '<span class="badge badge-status'.$clsnum.' badge-status" title="'.dol_escape_htmltag($label).'">'
				.dol_escape_htmltag($label).'</span>';
		}

		$picto = img_picto($label, 'statut'.$clsnum);
		if ((int) $mode === 1) return $picto;
		if ((int) $mode === 2) return $picto.' '.$label;
		if ((int) $mode === 3) return $label.' '.$picto;
		return $label;
	}
}
