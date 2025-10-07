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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/timesheetweek/class/timesheetweekline.class.php');

class TimesheetWeek extends CommonObject
{
	// Dolibarr meta
	public $element = 'timesheetweek';
	public $table_element = 'timesheet_week';
	public $picto = 'bookcal';
	public $ismultientitymanaged = 1;	// There is an entity field
	public $modulepart = 'timesheetweek';
	public $hasFiles = 1;
	public $hasDocModel = 1;
	public $dir_output = 'timesheetweek';

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

        /**
         * Cache des colonnes de llx_element_time pour limiter les requêtes répétées.
         * Cache for llx_element_time columns to avoid repeated metadata queries.
         *
         * @var array|null
         */
        protected $elementTimeColumnCache = null;

        /**
         * Indique si la contrainte d'unicité sur fk_elementdet a déjà été contrôlée/posée.
         * Flag telling whether the fk_elementdet unique constraint has already been checked/applied.
         *
         * @var bool
         */
        protected $elementTimeUniqueChecked = false;

	public $errors = array();
	public $error = '';

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->status = self::STATUS_DRAFT;

		if (!empty($conf->timesheetweek->dir_output)) {
			$this->dir_output = $conf->timesheetweek->dir_output;
		} elseif (defined('DOL_DATA_ROOT')) {
			$this->dir_output = DOL_DATA_ROOT.'/timesheetweek';
		}
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

                if (!$this->createAgendaEvent($user, 'TSWK_CREATE', 'TimesheetWeekAgendaCreated', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
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

                $sql = "SELECT rowid, fk_task, day_date, hours, zone, meal";
                $sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line";
                $sql .= " WHERE fk_timesheet_week=".(int) $this->id;
                $sql .= " ORDER BY day_date ASC, rowid ASC";

                $res = $this->db->query($sql);
                if (!$res) {
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                while ($obj = $this->db->fetch_object($res)) {
                        $l = new TimesheetWeekLine($this->db);

                        // FR: Injecte toutes les informations nécessaires pour la réplication sans dépendre d'un fetch séparé.
                        // EN: Inject all needed details for replication without relying on a separate fetch.
                        $l->id = (int) $obj->rowid;
                        $l->rowid = (int) $obj->rowid;
                        $l->fk_timesheet_week = (int) $this->id;
                        $l->fk_task = (int) $obj->fk_task;
                        $l->day_date = $obj->day_date;
                        $l->hours = (float) $obj->hours;
                        $l->zone = isset($obj->zone) ? (int) $obj->zone : null;
                        $l->meal = isset($obj->meal) ? (int) $obj->meal : null;

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

                if (!is_array($this->lines) || !count($this->lines)) {
                        $resLines = $this->fetchLines();
                        if ($resLines < 0) {
                                $this->db->rollback();
                                return -1;
                        }
                }

                if ($this->removeTaskTimeSpent($user) < 0) {
                        $this->db->rollback();
                        return -1;
                }

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

                if (!$this->createAgendaEvent($user, 'TSWK_DELETE', 'TimesheetWeekAgendaDeleted', array($this->ref), false)) {
                        $this->db->rollback();
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
                $now = dol_now();

                if (!in_array((int) $this->status, array(self::STATUS_DRAFT, self::STATUS_REFUSED), true)) {
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

                $up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=".(int) self::STATUS_SUBMITTED.", tms='".$this->db->idate($now)."', date_validation=NULL WHERE rowid=".(int) $this->id;
                if (!$this->db->query($up)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $this->status = self::STATUS_SUBMITTED;
                $this->tms = $now;
                $this->date_validation = null;

                if (!$this->createAgendaEvent($user, 'TSWK_SUBMIT', 'TimesheetWeekAgendaSubmitted', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
                }

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
                $now = dol_now();

                if ((int) $this->status === self::STATUS_DRAFT) {
                        $this->error = 'AlreadyDraft';
                        return 0;
                }

                $this->db->begin();

                $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element.
                        " SET status=".(int) self::STATUS_DRAFT.", tms='".$this->db->idate($now)."', date_validation=NULL WHERE rowid=".(int) $this->id;
                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $this->status = self::STATUS_DRAFT;
                $this->tms = $now;
                $this->date_validation = null;

                if ($this->removeTaskTimeSpent($user) < 0) {
                        $this->db->rollback();
                        return -1;
                }

                if (!$this->createAgendaEvent($user, 'TSWK_REOPEN', 'TimesheetWeekAgendaReopened', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
                }

                $this->db->commit();

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

                if ((int) $this->status !== self::STATUS_SUBMITTED) {
                        $this->error = 'BadStatusForApprove';
                        return -1;
                }

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
                $sql .= ", tms='".$this->db->idate($now)."'";
                $sql .= $setvalid;
                $sql .= " WHERE rowid=".(int) $this->id;

                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $this->status = self::STATUS_APPROVED;
                $this->date_validation = $now;
                $this->tms = $now;

                $skipInlineReplication = ((int) getDolGlobalInt('TIMESHEETWEEK_TASKTIME_REPLICATE', 0) === 1);
                if (!$skipInlineReplication) {
                        // FR: Réplique immédiatement les temps vers les tâches lorsque l'option n'est pas active côté carte.
                        // EN: Mirror time entries immediately when the card-level option is not active.
                        if ($this->applyTaskTimeSpent($user) < 0) {
                                $this->db->rollback();
                                return -1;
                        }
                }

                if (!$this->createAgendaEvent($user, 'TSWK_APPROVE', 'TimesheetWeekAgendaApproved', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
                }

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

                if ((int) $this->status !== self::STATUS_SUBMITTED) {
                        $this->error = 'BadStatusForRefuse';
                        return -1;
                }

                $this->db->begin();

                $setvalid = '';
                if (empty($this->fk_user_valid) || (int) $this->fk_user_valid !== (int) $user->id) {
                        $setvalid = ", fk_user_valid=".(int) $user->id;
                        $this->fk_user_valid = (int) $user->id;
                }

                $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
                $sql .= " status=".(int) self::STATUS_REFUSED;
                $sql .= ", date_validation='".$this->db->idate($now)."'";
                $sql .= ", tms='".$this->db->idate($now)."'";
                $sql .= $setvalid;
                $sql .= " WHERE rowid=".(int) $this->id;

                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $this->status = self::STATUS_REFUSED;
                $this->date_validation = $now;
                $this->tms = $now;

                if (!$this->createAgendaEvent($user, 'TSWK_REFUSE', 'TimesheetWeekAgendaRefused', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
                }

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
                global $langs;

                $label = dol_escape_htmltag($this->ref);
                $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.(int) $this->id;

                $linkstart = '';
                $linkend = '';
                $labeltooltip = $langs->trans('ShowTimesheetWeek', $this->ref);

                if ($option !== 'nolink') {
                        $linkstart = '<a href="'.$url.'"';
                        if ($morecss) {
                                $linkstart .= ' class="'.$morecss.'"';
                        }
                        if (empty($notooltip)) {
                                $linkstart .= ' title="'.dol_escape_htmltag($labeltooltip).'"';
                        }
                        $linkstart .= '>';
                        $linkend = '</a>';
                } elseif ($morecss) {
                        $linkstart = '<span class="'.$morecss.'">';
                        $linkend = '</span>';
                }

                $result = '';
                if ($withpicto) {
                        $tooltip = empty($notooltip) ? $labeltooltip : '';
                        $picto = img_object($tooltip, $this->picto);
                        $result .= $linkstart.$picto.$linkend;
                        if ($withpicto != 1) {
                                $result .= ' ';
                        }
                }

                if ($withpicto != 1 || $option === 'ref' || !$withpicto) {
                        $result .= $linkstart.$label.$linkend;
                }

                return $result;
        }

        /**
         * Load user from cache
         *
         * @param int $userid
         * @return User|null
         */
        protected function loadUserFromCache($userid)
        {
                $userid = (int) $userid;
                if ($userid <= 0) {
                        return null;
                }

                static $cache = array();
                if (!array_key_exists($userid, $cache)) {
                        $cache[$userid] = null;
                        $tmp = new User($this->db);
                        if ($tmp->fetch($userid) > 0) {
                                $cache[$userid] = $tmp;
                        }
                }

                return $cache[$userid];
        }

        /**
         * Send automatic notification based on trigger code
         *
         * @param string $triggerCode
         * @param User   $actionUser
         * @return bool
         */
       protected function sendAutomaticNotification($triggerCode, User $actionUser)
       {
               global $langs;

               $langs->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));

               $employee = $this->loadUserFromCache($this->fk_user);
               $validator = $this->loadUserFromCache($this->fk_user_valid);

               // FR: Génère l'URL directe vers la fiche pour l'insérer dans le modèle d'e-mail.
               // EN: Build the direct link to the card so it can be injected inside the e-mail template.
               $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $this->id;

               $employeeName = $employee ? $employee->getFullName($langs) : '';
               $validatorName = $validator ? $validator->getFullName($langs) : '';
               $actionUserName = $actionUser->getFullName($langs);

               // FR: Base des substitutions partagées entre notifications automatiques et métier.
               // EN: Base substitution array shared between automatic and business notifications.
               $baseSubstitutions = array(
                       '__TIMESHEETWEEK_REF__' => $this->ref,
                       '__TIMESHEETWEEK_WEEK__' => $this->week,
                       '__TIMESHEETWEEK_YEAR__' => $this->year,
                       '__TIMESHEETWEEK_URL__' => $url,
                       '__TIMESHEETWEEK_EMPLOYEE_FULLNAME__' => $employeeName,
                       '__TIMESHEETWEEK_VALIDATOR_FULLNAME__' => $validatorName,
                       '__ACTION_USER_FULLNAME__' => $actionUserName,
                       '__RECIPIENT_FULLNAME__' => '',
               );

               if (!is_array($this->context)) {
                       $this->context = array();
               }

               $this->context['timesheetweek_notification'] = array(
                       'trigger_code' => $triggerCode,
                       'url' => $url,
                       'employee_id' => $employee ? (int) $employee->id : 0,
                       'validator_id' => $validator ? (int) $validator->id : 0,
                       'action_user_id' => (int) $actionUser->id,
                       'employee_fullname' => $employeeName,
                       'validator_fullname' => $validatorName,
                       'action_user_fullname' => $actionUserName,
                       'base_substitutions' => $baseSubstitutions,
               );

               // FR: Conserve les substitutions pour les triggers Notification natifs.
               // EN: Keep substitutions handy for native Notification triggers.
               $this->context['mail_substitutions'] = $baseSubstitutions;

               // FR: Permet aux implémentations natives de récupérer toutes les informations utiles.
               // EN: Allow native helpers to access every useful metadata while sending e-mails.
               $this->context['timesheetweek_notification']['native_options'] = array(
                       'employee' => $employee,
                       'validator' => $validator,
                       'base_substitutions' => $baseSubstitutions,
               );

               // FR: Les triggers accèdent aux informations via le contexte, inutile de dupliquer les paramètres.
               // EN: Triggers read every detail from the context, no need to duplicate the payload locally.
               $result = $this->fireNotificationTrigger($triggerCode, $actionUser);
               if ($result < 0) {
                       $this->errors[] = $langs->trans('TimesheetWeekNotificationTriggerError', $triggerCode);
                       dol_syslog(__METHOD__.': unable to execute trigger '.$triggerCode, LOG_ERR);
                       return false;
               }

               return true;
       }

       /**
        * Execute Dolibarr triggers when notifications must be sent.
        *
        * @param string $triggerCode
        * @param User   $actionUser
        * @param array  $parameters
        * @return int
        */
       protected function fireNotificationTrigger($triggerCode, User $actionUser)
       {
               global $langs, $conf, $hookmanager;

               $payload = $this->buildTriggerParameters($triggerCode, $actionUser);

               // FR: Priorité à call_trigger lorsqu'il est disponible sur l'objet.
               // EN: Give priority to call_trigger whenever it exists on the object.
               if (method_exists($this, 'call_trigger')) {
                       try {
                               $method = new \ReflectionMethod($this, 'call_trigger');
                               $arguments = $this->mapTriggerArguments($method->getParameters(), $payload);

                               return $method->invokeArgs($this, $arguments);
                       } catch (\Throwable $error) {
                               dol_syslog(__METHOD__.': '.$error->getMessage(), LOG_WARNING);
                       }
               }

               // FR: Compatibilité avec les anciennes versions qui exposent runTrigger directement sur l'objet.
               // EN: Backward compatibility for legacy releases exposing runTrigger on the object.
               if (method_exists($this, 'runTrigger')) {
                       try {
                               $method = new \ReflectionMethod($this, 'runTrigger');
                               $arguments = $this->mapTriggerArguments($method->getParameters(), $payload, true);

                               return $method->invokeArgs($this, $arguments);
                       } catch (\Throwable $error) {
                               dol_syslog(__METHOD__.': '.$error->getMessage(), LOG_WARNING);
                       }
               }

               // FR: Fallback ultime sur la fonction globale runTrigger si elle est disponible.
               // EN: Ultimate fallback using the global runTrigger helper when available.
               if (!function_exists('runTrigger')) {
                       dol_include_once('/core/triggers/functions_triggers.inc.php');
               }

               if (function_exists('runTrigger')) {
                       try {
                               $function = new \ReflectionFunction('runTrigger');
                               $arguments = $this->mapTriggerArguments($function->getParameters(), $payload, true);

                               return $function->invokeArgs($arguments);
                       } catch (\Throwable $error) {
                               dol_syslog(__METHOD__.': '.$error->getMessage(), LOG_WARNING);
                       }
               }

               return 0;
       }

       /**
        * Prépare le jeu de paramètres partagé entre toutes les signatures de triggers.
        * Prepare the shared payload used by every trigger signature.
        *
        * @param string $triggerCode
        * @param User   $actionUser
        * @return array
        */
       protected function buildTriggerParameters($triggerCode, User $actionUser)
       {
               global $langs, $conf, $hookmanager;

               return array(
                       'action' => $triggerCode,
                       'trigger' => $triggerCode,
                       'event' => $triggerCode,
                       'actioncode' => $triggerCode,
                       'user' => $actionUser,
                       'actor' => $actionUser,
                       'currentuser' => $actionUser,
                       'langs' => $langs,
                       'language' => $langs,
                       'conf' => $conf,
                       'config' => $conf,
                       'hookmanager' => isset($hookmanager) ? $hookmanager : null,
                       'hook' => isset($hookmanager) ? $hookmanager : null,
                       'object' => $this,
                       'obj' => $this,
                       'objectsrc' => $this,
                       'context' => $this->context,
                       'parameters' => array('context' => $this->context, 'timesheetweek' => $this),
                       'params' => array('context' => $this->context, 'timesheetweek' => $this),
                       'moreparam' => array('context' => $this->context, 'timesheetweek' => $this),
                       'extrafields' => property_exists($this, 'extrafields') ? $this->extrafields : null,
                       'extrafield' => property_exists($this, 'extrafields') ? $this->extrafields : null,
                       'extraparams' => property_exists($this, 'extrafields') ? $this->extrafields : null,
                       'parametersarray' => array('context' => $this->context, 'timesheetweek' => $this),
                       'actiontype' => $triggerCode,
               );
       }

       /**
        * Mappe la liste des paramètres attendus par une signature de trigger vers les valeurs connues.
        * Map the expected parameter list of a trigger signature to the known values.
        *
        * @param \ReflectionParameter[] $signature
        * @param array                  $payload
        * @param bool                   $injectObjectWhenMissing
        * @return array
        */
       protected function mapTriggerArguments(array $signature, array $payload, $injectObjectWhenMissing = false)
       {
               $arguments = array();

               foreach ($signature as $parameter) {
                       $name = $parameter->getName();

                       if (isset($payload[$name])) {
                               $arguments[] = $payload[$name];
                               continue;
                       }

                       // FR: Quelques alias fréquents ne respectent pas la casse ou ajoutent un suffixe.
                       // EN: Handle common aliases that differ by casing or suffixes.
                       $lower = strtolower($name);
                       if (isset($payload[$lower])) {
                               $arguments[] = $payload[$lower];
                               continue;
                       }

                       if ($lower === 'object' && $injectObjectWhenMissing) {
                               $arguments[] = $this;
                               continue;
                       }

                       if (strpos($lower, 'context') !== false) {
                               $arguments[] = $payload['context'];
                               continue;
                       }

                       // FR: Valeur neutre par défaut pour ne pas interrompre le déclenchement.
                       // EN: Default neutral value so the trigger keeps running.
                       $arguments[] = null;
               }

               return $arguments;
       }

       /**
        * Try to send an e-mail notification relying on Dolibarr native helpers.
        *
        * FR: Tente d'envoyer un e-mail de notification en s'appuyant sur les outils natifs de Dolibarr.
        * EN: Try sending the notification e-mail using Dolibarr's native helpers.
        *
        * @param string $triggerCode
        * @param User   $actionUser
        * @param User   $recipient
        * @param mixed  $langs
        * @param mixed  $conf
        * @param array  $substitutions
        * @param array  $options
        * @return int                    >0 success, 0 unsupported, <0 failure
        */
       public function sendNativeMailNotification($triggerCode, User $actionUser, $recipient, $langs, $conf, array $substitutions, array $options = array())
       {
               $sendto = isset($options['sendto']) ? trim((string) $options['sendto']) : '';
               if ($sendto === '') {
                       return 0;
               }

               $methods = array('sendEmailsFromTemplate', 'sendEmailsCommon', 'sendEmailsFromModel', 'sendEmails', 'sendMails');

               $payload = array(
                       'trigger' => $triggerCode,
                       'action' => $triggerCode,
                       'code' => $triggerCode,
                       'event' => $triggerCode,
                       'user' => $actionUser,
                       'actionuser' => $actionUser,
                       'actor' => $actionUser,
                       'currentuser' => $actionUser,
                       'langs' => $langs,
                       'language' => $langs,
                       'conf' => $conf,
                       'subject' => isset($options['subject']) ? (string) $options['subject'] : '',
                       'message' => isset($options['message']) ? (string) $options['message'] : '',
                       'content' => isset($options['message']) ? (string) $options['message'] : '',
                       'body' => isset($options['message']) ? (string) $options['message'] : '',
                       'sendto' => $sendto,
                       'emailto' => $sendto,
                       'email_to' => $sendto,
                       'sendtolist' => $sendto,
                       'sendtocc' => isset($options['cc']) ? (string) $options['cc'] : '',
                       'emailcc' => isset($options['cc']) ? (string) $options['cc'] : '',
                       'sendtobcc' => isset($options['bcc']) ? (string) $options['bcc'] : '',
                       'emailbcc' => isset($options['bcc']) ? (string) $options['bcc'] : '',
                       'replyto' => isset($options['replyto']) ? (string) $options['replyto'] : '',
                       'emailreplyto' => isset($options['replyto']) ? (string) $options['replyto'] : '',
                       'deliveryreceipt' => !empty($options['deliveryreceipt']) ? 1 : 0,
                       'trackid' => !empty($options['trackid']) ? (string) $options['trackid'] : 'timesheetweek-'.$this->id.'-'.$triggerCode,
                       'substitutions' => $substitutions,
                       'substitutionarray' => $substitutions,
                       'mail_substitutions' => $substitutions,
                       'array_substitutions' => $substitutions,
                       'files' => isset($options['files']) && is_array($options['files']) ? $options['files'] : array(),
                       'filearray' => isset($options['files']) && is_array($options['files']) ? $options['files'] : array(),
                       'filename' => isset($options['filenames']) && is_array($options['filenames']) ? $options['filenames'] : array(),
                       'filenameList' => isset($options['filenames']) && is_array($options['filenames']) ? $options['filenames'] : array(),
                       'mimetype' => isset($options['mimetypes']) && is_array($options['mimetypes']) ? $options['mimetypes'] : array(),
                       'mimetypeList' => isset($options['mimetypes']) && is_array($options['mimetypes']) ? $options['mimetypes'] : array(),
                       'joinfiles' => isset($options['files']) && is_array($options['files']) ? $options['files'] : array(),
                       'mode' => 'email',
                       'recipient' => $recipient,
                       'email' => $sendto,
                       'context' => $this->context,
                       'moreinval' => array('context' => $this->context, 'timesheetweek' => $this),
                       'params' => isset($options['params']) && is_array($options['params']) ? $options['params'] : array(),
                       'options' => $options,
               );

               foreach ($methods as $methodName) {
                       if (!method_exists($this, $methodName)) {
                               continue;
                       }

                       try {
                               $method = new \ReflectionMethod($this, $methodName);
                               $arguments = $this->mapMailMethodArguments($method->getParameters(), $payload);
                               $result = $method->invokeArgs($this, $arguments);

                               if ($result === false) {
                                       continue;
                               }

                               if (is_numeric($result)) {
                                       if ((int) $result > 0) {
                                               return (int) $result;
                                       }

                                       continue;
                               }

                               return 1;
                       } catch (\Throwable $error) {
                               dol_syslog(__METHOD__.': '.$error->getMessage(), LOG_WARNING);
                       }
               }

               return 0;
       }

       /**
        * Map native mail helper signature to known payload values.
        *
        * FR: Associe la signature d'une méthode d'envoi d'e-mail aux valeurs connues.
        * EN: Map the parameter list of a mail helper to the known payload values.
        *
        * @param \ReflectionParameter[] $signature
        * @param array                  $payload
        * @return array
        */
       protected function mapMailMethodArguments(array $signature, array $payload)
       {
               $arguments = array();

               foreach ($signature as $parameter) {
                       $value = null;
                       $name = $parameter->getName();
                       $lower = strtolower($name);

                       if (isset($payload[$name])) {
                               $value = $payload[$name];
                       } elseif (isset($payload[$lower])) {
                               $value = $payload[$lower];
                       } else {
                               if ($parameter->hasType()) {
                                       $type = $parameter->getType();
                                       if ($type && !$type->isBuiltin()) {
                                               $typeName = ltrim($type->getName(), '\\');
                                               if ($typeName === 'User') {
                                                       $value = isset($payload['user']) ? $payload['user'] : null;
                                               } elseif ($typeName === 'Translate') {
                                                       $value = isset($payload['langs']) ? $payload['langs'] : null;
                                               } elseif ($typeName === 'Conf') {
                                                       $value = isset($payload['conf']) ? $payload['conf'] : null;
                                               } elseif ($typeName === 'TimesheetWeek' || is_a($this, $typeName)) {
                                                       $value = $this;
                                               }
                                       }
                               }

                               if ($value === null) {
                                       if (strpos($lower, 'substit') !== false && isset($payload['substitutions'])) {
                                               $value = $payload['substitutions'];
                                       } elseif (strpos($lower, 'sendto') !== false && isset($payload['sendto'])) {
                                               $value = $payload['sendto'];
                                       } elseif (strpos($lower, 'subject') !== false && isset($payload['subject'])) {
                                               $value = $payload['subject'];
                                       } elseif ((strpos($lower, 'message') !== false || strpos($lower, 'content') !== false || strpos($lower, 'body') !== false) && isset($payload['message'])) {
                                               $value = $payload['message'];
                                       } elseif (strpos($lower, 'reply') !== false && isset($payload['replyto'])) {
                                               $value = $payload['replyto'];
                                       } elseif (strpos($lower, 'cc') !== false && isset($payload['sendtocc'])) {
                                               $value = $payload['sendtocc'];
                                       } elseif (strpos($lower, 'bcc') !== false && isset($payload['sendtobcc'])) {
                                               $value = $payload['sendtobcc'];
                                       } elseif (strpos($lower, 'user') !== false && isset($payload['user'])) {
                                               $value = $payload['user'];
                                       } elseif (strpos($lower, 'lang') !== false && isset($payload['langs'])) {
                                               $value = $payload['langs'];
                                       } elseif (strpos($lower, 'conf') !== false && isset($payload['conf'])) {
                                               $value = $payload['conf'];
                                       } elseif (strpos($lower, 'context') !== false && isset($payload['context'])) {
                                               $value = $payload['context'];
                                       } elseif (strpos($lower, 'track') !== false && isset($payload['trackid'])) {
                                               $value = $payload['trackid'];
                                       } elseif (strpos($lower, 'recipient') !== false && isset($payload['recipient'])) {
                                               $value = $payload['recipient'];
                                       } elseif (strpos($lower, 'files') !== false && isset($payload['files'])) {
                                               $value = $payload['files'];
                                       } elseif (strpos($lower, 'filename') !== false && isset($payload['filename'])) {
                                               $value = $payload['filename'];
                                       } elseif (strpos($lower, 'mimetype') !== false && isset($payload['mimetype'])) {
                                               $value = $payload['mimetype'];
                                       } elseif (strpos($lower, 'moreinval') !== false && isset($payload['moreinval'])) {
                                               $value = $payload['moreinval'];
                                       } elseif (strpos($lower, 'params') !== false && isset($payload['params'])) {
                                               $value = $payload['params'];
                                       }
                               }
                       }

                       if ($value === null && $parameter->isDefaultValueAvailable()) {
                               $value = $parameter->getDefaultValue();
                       }

                       $arguments[] = $value;
               }

               return $arguments;
       }

       /**
        * Fire Dolibarr business notifications (module Notification) for status changes.
        *
        * FR: Déclenche les notifications métiers standards de Dolibarr (module Notification) lors des changements d'état.
        * EN: Fire standard Dolibarr business notifications (Notification module) when the status changes.
        *
        * @param string $triggerCode
        * @param User   $actionUser
        * @return int
        */
       protected function triggerBusinessNotification($triggerCode, User $actionUser)
       {
               if (!is_array($this->context)) {
                       $this->context = array();
               }

               // FR: Marqueur spécifique pour les triggers du module Notification.
               // EN: Specific flag for the Notification module triggers.
               $this->context['timesheetweek_business_notification'] = 1;

               if (!empty($this->context['timesheetweek_notification']['base_substitutions'])) {
                       // FR: Garantit que les notifications métier récupèrent les mêmes substitutions que les e-mails automatiques.
                       // EN: Ensure business notifications reuse the same substitutions as automatic e-mails.
                       $this->context['mail_substitutions'] = $this->context['timesheetweek_notification']['base_substitutions'];
               }

               return $this->fireNotificationTrigger($triggerCode, $actionUser);
       }

        /**
         * Réplique les temps consommés vers les tâches associées à la feuille.
         * Mirror the timesheet consumption onto the related project tasks.
         *
         * @param User $actionUser
         * @return int
         */
        public function replicateTaskTimeSpent(User $actionUser)
        {
                // FR: Délègue au moteur interne de synchronisation pour conserver la logique existante.
                // EN: Delegate to the internal synchronisation engine to keep the existing logic.
                return $this->applyTaskTimeSpent($actionUser);
        }

        /**
         * Add time spent entries on linked tasks for every line of the timesheet.
         *
         * @param User $actionUser
         * @return int
         */
        protected function applyTaskTimeSpent(User $actionUser)
        {
                global $langs;

                // FR: Journalise le démarrage de la synchronisation des temps vers les tâches.
                // EN: Log the start of the time synchronisation onto tasks.
                dol_syslog(__METHOD__.': Begin mirroring timesheet '.$this->id.' for user '.$this->fk_user, LOG_DEBUG);

                if ((int) $this->fk_user <= 0) {
                        // FR: Informe que l'utilisateur assigné est manquant et annule l'opération.
                        // EN: Report missing assigned user and abort the operation.
                        dol_syslog(__METHOD__.': Missing fk_user, aborting', LOG_WARNING);
                        return 1;
                }

                $lines = $this->getLines();
                if (!is_array($lines) || !count($lines)) {
                        // FR: Mentionne l'absence de lignes à traiter.
                        // EN: Mention that there are no lines to process.
                        dol_syslog(__METHOD__.': No lines to mirror', LOG_DEBUG);
                        return 1;
                }

                foreach ($lines as $line) {
                        $lineId = $this->getLineIdentifier($line);
                        $taskId = !empty($line->fk_task) ? (int) $line->fk_task : 0;
                        if ($lineId <= 0 || $taskId <= 0) {
                                // FR: Ignore les lignes dépourvues d'identifiant ou de tâche.
                                // EN: Skip lines without an identifier or task.
                                dol_syslog(__METHOD__.': Skip line without id or task (line='.$lineId.', task='.$taskId.')', LOG_DEBUG);
                                continue;
                        }

                        $duration = $this->convertHoursToSeconds($line->hours);
                        if ($duration <= 0) {
                                // FR: Ignore les lignes sans durée positive.
                                // EN: Skip lines without a positive duration.
                                dol_syslog(__METHOD__.': Skip line '.$lineId.' because duration is not positive', LOG_DEBUG);
                                continue;
                        }

                        $importKey = $this->buildTimeSpentImportKey($lineId);
                        if (empty($importKey)) {
                                // FR: Enregistre l'impossibilité de calculer la clé d'import.
                                // EN: Record that the import key could not be generated.
                                dol_syslog(__METHOD__.': Failed to compute import key for line '.$lineId, LOG_WARNING);
                                continue;
                        }

                        $existing = $this->fetchElementTimeRowsByImportKey($importKey);
                        if ($existing === false) {
                                // FR: Avertit l'utilisateur d'un échec lors de la recherche des miroirs element_time.
                                // EN: Warn the user about a failure while fetching element_time mirror rows.
                                dol_syslog(__METHOD__.': Unable to fetch existing element_time rows for key '.$importKey, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }
                        if (!empty($existing)) {
                                // FR: Indique qu'un miroir element_time existe déjà et évite un doublon.
                                // EN: Note that an element_time mirror already exists to avoid duplicates.
                                dol_syslog(__METHOD__.': element_time already exists for key '.$importKey, LOG_DEBUG);
                                continue;
                        }

                        $task = new Task($this->db);
                        if ($task->fetch($taskId) <= 0) {
                                $this->error = $task->error ?: 'FailedToFetchTask';
                                if (!empty($task->errors) && is_array($task->errors)) {
                                        $this->errors = array_merge($this->errors, $task->errors);
                                }
                                // FR: Signale l'échec du chargement de la tâche cible.
                                // EN: Report the failure to load the target task.
                                dol_syslog(__METHOD__.': Failed to fetch task '.$taskId.' for line '.$lineId.' - error='.$this->error, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }

                        $timestamp = $this->resolveLineDate($line->day_date);

                        $task->timespent_date = $timestamp;
                        $task->timespent_datehour = $timestamp;
                        $task->timespent_withhour = 0;
                        $task->timespent_duration = $duration;
                        $task->timespent_fk_user = (int) $this->fk_user;
                        $task->timespent_note = $this->buildTimeSpentNote($line, $timestamp);
                        $task->timespent_import_key = $importKey;

                        if (property_exists($task, 'fk_project') && !empty($task->fk_project)) {
                                $task->timespent_fk_project = (int) $task->fk_project;
                        } elseif (property_exists($task, 'fk_projet') && !empty($task->fk_projet)) {
                                $task->timespent_fk_project = (int) $task->fk_projet;
                        }

                        $resAdd = $task->addTimeSpent($actionUser);
                        if ($resAdd <= 0) {
                                $this->error = $task->error ?: 'FailedToAddTimeSpent';
                                if (!empty($task->errors) && is_array($task->errors)) {
                                        $this->errors = array_merge($this->errors, $task->errors);
                                }
                                // FR: Informe de l'échec de la création du temps consommé côté tâche.
                                // EN: Report the failure to create the time spent entry on the task side.
                                dol_syslog(__METHOD__.': addTimeSpent failed for task '.$taskId.' (import='.$importKey.') - error='.$this->error, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }

                        if ($this->ensureElementTimeEntry($task, $timestamp, $duration, $importKey, $actionUser, (int) $resAdd) < 0) {
                                if (method_exists($task, 'delTimeSpent')) {
                                        $task->delTimeSpent((int) $resAdd, $actionUser);
                                }
                                $this->deleteElementTimeByImportKey($importKey);
                                // FR: Préviens de l'échec de la création du miroir dans llx_element_time et de l'annulation.
                                // EN: Warn about the failure to create the mirror in llx_element_time and the rollback.
                                dol_syslog(__METHOD__.': ensureElementTimeEntry failed, rollback import '.$importKey, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }

                        // FR: Confirme la synchronisation réussie de la ligne courante.
                        // EN: Confirm the successful synchronisation of the current line.
                        dol_syslog(__METHOD__.': Mirrored line '.$lineId.' into task '.$taskId.' (import='.$importKey.')', LOG_DEBUG);
                }

                // FR: Signale la fin de la synchronisation sans erreur.
                // EN: Signal the end of the synchronisation without errors.
                dol_syslog(__METHOD__.': Completed mirroring for timesheet '.$this->id, LOG_DEBUG);

                return 1;
        }

        /**
         * Remove time spent entries previously generated for this timesheet.
         *
         * @param User $actionUser
         * @return int
         */
        protected function removeTaskTimeSpent(User $actionUser)
        {
                $timesheetId = (int) $this->id;
                if ($timesheetId <= 0) {
                        return 1;
                }

                $records = $this->fetchElementTimeRowsByTimesheet(true);
                if ($records === false) {
                        return -1;
                }
                if (empty($records)) {
                        // FR: Avertit qu'aucune ligne miroir n'a été trouvée pour cette feuille.
                        // EN: Warn that no mirror rows were found for this sheet.
                        dol_syslog(__METHOD__.': No mirrored rows found for timesheet '.$timesheetId, LOG_DEBUG);
                        return 1;
                }

                foreach ($records as $record) {
                        $importKey = !empty($record['import_key']) ? $record['import_key'] : '';
                        $taskTimeId = !empty($record['fk_elementdet']) ? (int) $record['fk_elementdet'] : 0;
                        $taskId = !empty($record['fk_element']) ? (int) $record['fk_element'] : 0;

                        if ($taskId > 0 && $taskTimeId > 0) {
                                $task = new Task($this->db);
                                $taskFetched = $task->fetch($taskId);

                                if ($taskFetched > 0 && method_exists($task, 'delTimeSpent')) {
                                        $resDel = $task->delTimeSpent($taskTimeId, $actionUser);
                                        if ($resDel <= 0) {
                                                $this->error = $task->error ?: 'FailedToDeleteTimeSpent';
                                                if (!empty($task->errors) && is_array($task->errors)) {
                                                        $this->errors = array_merge($this->errors, $task->errors);
                                                }
                                                // FR: Journalise l'échec de la suppression via l'API Dolibarr.
                                                // EN: Log the failure to delete through Dolibarr's API.
                                                dol_syslog(__METHOD__.': delTimeSpent failed for import '.$importKey.' (task='.$taskId.', timespent='.$taskTimeId.') - error='.$this->error, LOG_ERR);
                                                return -1;
                                        }
                                } else {
                                        // FR: Consigne l'impossibilité de supprimer côté tâche tout en poursuivant le nettoyage.
                                        // EN: Log the inability to delete on the task side while continuing the cleanup.
                                        dol_syslog(__METHOD__.': Skip task time deletion for import '.$importKey.' (task='.$taskId.', timespent='.$taskTimeId.')', LOG_DEBUG);
                                }
                        }

                        if ($this->deleteElementTimeByImportKey($importKey) < 0) {
                                return -1;
                        }
                }

                return 1;
        }

        /**
         * Build a unique import key for the time spent entry of a line.
         *
         * @param int $lineId
         * @return string
         */
        protected function buildTimeSpentImportKey($lineId)
        {
                if ((int) $lineId <= 0 || (int) $this->id <= 0) {
                        return '';
                }

                return 'tswk:'.$this->id.':'.$lineId;
        }

        /**
         * Ensure a matching entry exists in llx_element_time for the generated time spent record.
         *
         * @param Task $task
         * @param int  $timestamp
         * @param int  $duration
         * @param string $importKey
         * @param User $actionUser
         * @param int $taskTimeId Identifiant de la ligne temps créée (table Dolibarr dédiée). / Identifier of the created time entry line (Dolibarr task time table).
         * @return int
         */
        protected function ensureElementTimeEntry(Task $task, $timestamp, $duration, $importKey, User $actionUser, $taskTimeId)
        {
                // FR: Trace l'intention de créer ou vérifier une ligne llx_element_time.
                // EN: Trace the intent to create or verify an llx_element_time row.
                dol_syslog(__METHOD__.': Ensure element_time for task '.$task->id.' import '.$importKey, LOG_DEBUG);

                if ((int) $duration <= 0) {
                        // FR: Empêche toute insertion lorsque la durée est nulle ou négative.
                        // EN: Prevent any insertion when the duration is null or negative.
                        dol_syslog(__METHOD__.': Skip element_time insert/update because duration is not positive (key='.$importKey.')', LOG_DEBUG);
                        return 1;
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        // FR: Avertit de l'impossibilité de récupérer les colonnes de la table.
                        // EN: Warn that the table columns could not be retrieved.
                        dol_syslog(__METHOD__.': Unable to load element_time column metadata', LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                if (isset($columns['fk_elementdet']) && (int) $taskTimeId <= 0) {
                        // FR: fk_elementdet doit toujours pointer vers une ligne valide pour respecter la contrainte.
                        // EN: fk_elementdet must always target a valid row to respect the constraint.
                        dol_syslog(__METHOD__.': Missing task time id for key '.$importKey.', aborting insert/update', LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                $existing = $this->fetchElementTimeRowsByImportKey($importKey, true);
                if ($existing === false) {
                        // FR: Enregistre l'impossibilité de lire la table miroir.
                        // EN: Record the inability to read the mirror table.
                        dol_syslog(__METHOD__.': Could not inspect element_time for key '.$importKey, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }
                if (!empty($existing) && count($existing) > 1) {
                        $existing = $this->pruneElementTimeDuplicates($importKey, $existing);
                        if ($existing === false) {
                                return -1;
                        }
                }

                $taskId = !empty($task->id) ? (int) $task->id : 0;
                if ($taskId <= 0) {
                        return 1;
                }

                if (!empty($existing)) {
                        $existingRow = reset($existing);
                        $existingRowId = !empty($existingRow['rowid']) ? (int) $existingRow['rowid'] : (!empty($existingRow['id']) ? (int) $existingRow['id'] : 0);
                        if ($existingRowId <= 0) {
                                // FR: Sans identifiant, impossible de mettre à jour la ligne existante.
                                // EN: Without an identifier, the existing row cannot be updated.
                                dol_syslog(__METHOD__.': Existing element_time row has no id for key '.$importKey, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }

                        $updateData = $this->buildElementTimeData($task, $timestamp, $duration, $importKey, $actionUser, $taskTimeId, $columns, false);
                        if (empty($updateData)) {
                                return 1;
                        }

                        return $this->updateElementTimeRow($existingRowId, $updateData, $importKey, $taskId, $taskTimeId);
                }

                $existingByTaskTime = array();
                if ($taskTimeId > 0) {
                        $existingByTaskTime = $this->fetchElementTimeRowByTaskTimeId($taskTimeId);
                        if ($existingByTaskTime === false) {
                                // FR: Impossible de lire la ligne associée au temps Dolibarr.
                                // EN: Unable to read the row linked to the Dolibarr task time.
                                dol_syslog(__METHOD__.': Failed to load element_time row for tasktime '.$taskTimeId.' (import='.$importKey.')', LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return -1;
                        }
                }

                if (!empty($existingByTaskTime)) {
                        $updateData = $this->buildElementTimeData($task, $timestamp, $duration, $importKey, $actionUser, $taskTimeId, $columns, false);
                        if (empty($updateData)) {
                                return 1;
                        }

                        return $this->updateElementTimeRow((int) $existingByTaskTime['rowid'], $updateData, $importKey, $taskId, $taskTimeId);
                }

                if (isset($columns['fk_elementdet']) && !$this->ensureElementTimeUniqueConstraint($importKey)) {
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                $data = $this->buildElementTimeData($task, $timestamp, $duration, $importKey, $actionUser, $taskTimeId, $columns, true);
                if (empty($data)) {
                        return 1;
                }

                $fieldList = array();
                $valueList = array();
                foreach ($data as $field => $value) {
                        $fieldList[] = $field;
                        $valueList[] = is_numeric($value) || $value === 'NULL' ? (string) $value : $value;
                }

                $sql = "INSERT INTO ".MAIN_DB_PREFIX."element_time(".implode(', ', $fieldList).") VALUES (".implode(', ', $valueList).")";

                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        // FR: Journalise l'échec de l'insertion dans llx_element_time.
                        // EN: Log the failure to insert into llx_element_time.
                        dol_syslog(__METHOD__.': Insert failed for key '.$importKey.' - error='.$this->error, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                // FR: Confirme la création d'une nouvelle ligne miroir.
                // EN: Confirm the creation of a new mirror row.
                dol_syslog(__METHOD__.': Inserted element_time row for key '.$importKey.' (task='.$taskId.', tasktime='.$taskTimeId.')', LOG_DEBUG);

                if ($taskTimeId > 0 && isset($columns['fk_elementdet'])) {
                        $postInsertRows = $this->fetchElementTimeRowsByImportKey($importKey, true);
                        if ($postInsertRows !== false && !empty($postInsertRows)) {
                                $insertedRow = reset($postInsertRows);
                                if (empty($insertedRow['fk_elementdet'])) {
                                        $updateData = $this->buildElementTimeData($task, $timestamp, $duration, $importKey, $actionUser, $taskTimeId, $columns, false);
                                        if (!empty($updateData)) {
                                                if ($this->updateElementTimeRow((int) $insertedRow['rowid'], $updateData, $importKey, $taskId, $taskTimeId) < 0) {
                                                        return -1;
                                                }
                                        }
                                }
                        }
                }

                return 1;
        }

        /**
         * Prépare les données à insérer ou mettre à jour dans llx_element_time.
         * Prepare the dataset to insert or update inside llx_element_time.
         *
         * @param Task  $task
         * @param int   $timestamp
         * @param int   $duration
         * @param string $importKey
         * @param User  $actionUser
         * @param int   $taskTimeId
         * @param array $columns
         * @param bool  $forInsert
         * @return array
         */
        protected function buildElementTimeData(Task $task, $timestamp, $duration, $importKey, User $actionUser, $taskTimeId, array $columns, $forInsert = true)
        {
                global $conf;

                $now = dol_now();
                $data = array();

                $entity = property_exists($task, 'entity') ? (int) $task->entity : 0;
                if ($entity <= 0) {
                        $entity = (int) $this->entity;
                }
                if ($entity <= 0 && !empty($conf->entity)) {
                        $entity = (int) $conf->entity;
                }

                $taskId = !empty($task->id) ? (int) $task->id : 0;
                if ($taskId <= 0) {
                        return array();
                }

                $projectId = 0;
                if (property_exists($task, 'fk_project') && !empty($task->fk_project)) {
                        $projectId = (int) $task->fk_project;
                } elseif (property_exists($task, 'fk_projet') && !empty($task->fk_projet)) {
                        $projectId = (int) $task->fk_projet;
                }

                $withHour = property_exists($task, 'timespent_withhour') ? (int) $task->timespent_withhour : 0;
                $dateDay = $timestamp ? dol_print_date($timestamp, '%Y-%m-%d') : '';
                $dateHour = $timestamp ? $this->db->idate($timestamp) : '';

                $note = property_exists($task, 'timespent_note') ? $task->timespent_note : '';
                $timeUserId = property_exists($task, 'timespent_fk_user') ? (int) $task->timespent_fk_user : (int) $this->fk_user;

                if ($forInsert && isset($columns['datec'])) {
                        $data['datec'] = "'".$this->db->escape($this->db->idate($now))."'";
                }
                if (isset($columns['tms'])) {
                        $data['tms'] = "'".$this->db->escape($this->db->idate($now))."'";
                }
                if (isset($columns['entity'])) {
                        $data['entity'] = ($entity > 0 ? (int) $entity : 1);
                }
                if (isset($columns['elementtype'])) {
                        $data['elementtype'] = "'task'";
                }
                if (isset($columns['fk_element'])) {
                        $data['fk_element'] = $taskId;
                }
                if ($taskTimeId > 0 && isset($columns['fk_elementdet'])) {
                        $data['fk_elementdet'] = (int) $taskTimeId;
                }
                if (isset($columns['element_date'])) {
                        $data['element_date'] = ($dateDay !== '' ? "'".$this->db->escape($dateDay)."'" : 'NULL');
                }
                if (isset($columns['element_datehour'])) {
                        $data['element_datehour'] = ($dateHour !== '' ? "'".$this->db->escape($dateHour)."'" : 'NULL');
                }
                if (isset($columns['element_withhour'])) {
                        $data['element_withhour'] = (int) $withHour;
                }
                if (isset($columns['duration'])) {
                        $data['duration'] = (int) $duration;
                }
                if (isset($columns['fk_user'])) {
                        $data['fk_user'] = $timeUserId;
                }
                if (isset($columns['fk_user_author'])) {
                        $data['fk_user_author'] = $timeUserId;
                }
                if ($forInsert && isset($columns['fk_user_create'])) {
                        $data['fk_user_create'] = !empty($actionUser->id) ? (int) $actionUser->id : 'NULL';
                }
                if (isset($columns['fk_user_modif'])) {
                        $data['fk_user_modif'] = !empty($actionUser->id) ? (int) $actionUser->id : 'NULL';
                }
                if (isset($columns['note'])) {
                        $data['note'] = ($note !== '' ? "'".$this->db->escape($note)."'" : 'NULL');
                }
                if (isset($columns['import_key'])) {
                        $data['import_key'] = "'".$this->db->escape($importKey)."'";
                }
                if (isset($columns['fk_project'])) {
                        $data['fk_project'] = ($projectId > 0 ? $projectId : 'NULL');
                }

                return $data;
        }

        /**
         * Met à jour une ligne existante de llx_element_time avec les données préparées.
         * Update an existing llx_element_time row with the prepared dataset.
         *
         * @param int    $rowId
         * @param array  $updateData
         * @param string $importKey
         * @param int    $taskId
         * @param int    $taskTimeId
         * @return int
         */
        protected function updateElementTimeRow($rowId, array $updateData, $importKey, $taskId, $taskTimeId)
        {
                $rowId = (int) $rowId;
                if ($rowId <= 0 || empty($updateData)) {
                        return 1;
                }

                $setParts = array();
                foreach ($updateData as $field => $value) {
                        $setParts[] = $field.'='.(is_numeric($value) || $value === 'NULL' ? (string) $value : $value);
                }

                $sql = 'UPDATE '.MAIN_DB_PREFIX."element_time SET ".implode(', ', $setParts).' WHERE rowid='.$rowId;
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        // FR: Journalise l'échec de la mise à jour et alerte l'utilisateur.
                        // EN: Log the update failure and warn the user.
                        dol_syslog(__METHOD__.': Update failed for key '.$importKey.' - error='.$this->error, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                // FR: Confirme la mise à jour de la ligne miroir ciblée.
                // EN: Confirm the update of the targeted mirror row.
                dol_syslog(__METHOD__.': Updated element_time row '.$rowId.' for key '.$importKey.' (task='.$taskId.', tasktime='.$taskTimeId.')', LOG_DEBUG);

                return 1;
        }

        /**
         * Supprime les doublons llx_element_time pour une clé d'import donnée et conserve la meilleure ligne.
         * Remove llx_element_time duplicates for the given import key while preserving the best row.
         *
         * @param string $importKey
         * @param array  $rows
         * @return array|false
         */
        protected function pruneElementTimeDuplicates($importKey, array $rows)
        {
                if (count($rows) <= 1) {
                        return array_values($rows);
                }

                $bestRow = null;
                $bestRowId = 0;
                $duplicates = array();

                foreach ($rows as $row) {
                        $rowId = !empty($row['rowid']) ? (int) $row['rowid'] : (!empty($row['id']) ? (int) $row['id'] : 0);
                        if ($rowId <= 0) {
                                continue;
                        }

                        $hasElementDet = !empty($row['fk_elementdet']);
                        $bestHasElementDet = !empty($bestRow) && !empty($bestRow['fk_elementdet']);

                        if ($bestRow === null) {
                                $bestRow = $row;
                                $bestRowId = $rowId;
                                continue;
                        }

                        $shouldReplace = false;
                        if ($hasElementDet && !$bestHasElementDet) {
                                $shouldReplace = true;
                        } elseif ($hasElementDet === $bestHasElementDet && $rowId < $bestRowId) {
                                $shouldReplace = true;
                        }

                        if ($shouldReplace) {
                                $duplicates[] = $bestRow;
                                $bestRow = $row;
                                $bestRowId = $rowId;
                        } else {
                                $duplicates[] = $row;
                        }
                }

                foreach ($duplicates as $duplicate) {
                        $dupId = !empty($duplicate['rowid']) ? (int) $duplicate['rowid'] : (!empty($duplicate['id']) ? (int) $duplicate['id'] : 0);
                        if ($dupId <= 0) {
                                continue;
                        }

                        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'element_time WHERE rowid='.(int) $dupId;
                        if (!$this->db->query($sql)) {
                                $this->error = $this->db->lasterror();
                                // FR: Journalise l'échec de la purge des doublons et avertit l'utilisateur.
                                // EN: Log the failure to purge duplicates and warn the user.
                                dol_syslog(__METHOD__.': Failed to delete duplicate row '.$dupId.' for key '.$importKey.' - error='.$this->error, LOG_ERR);
                                $this->notifyElementTimeError('['.$importKey.']');
                                return false;
                        }
                }

                if (!empty($duplicates)) {
                        // FR: Signale combien de doublons ont été supprimés pour cette clé.
                        // EN: Report how many duplicates were removed for this key.
                        dol_syslog(__METHOD__.': Pruned '.count($duplicates).' duplicate element_time row(s) for key '.$importKey, LOG_DEBUG);
                }

                return $bestRow !== null ? array($bestRow) : array();
        }

        /**
         * S'assure que la colonne fk_elementdet est protégée par une contrainte d'unicité.
         * Ensure the fk_elementdet column is protected by a unique constraint.
         *
         * @param string $importKey
         * @return bool
         */
        protected function ensureElementTimeUniqueConstraint($importKey = '')
        {
                if ($this->elementTimeUniqueChecked) {
                        return true;
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        return false;
                }

                if (!isset($columns['fk_elementdet'])) {
                        // FR: La colonne n'existe pas, rien à imposer.
                        // EN: Column is missing, nothing to enforce.
                        $this->elementTimeUniqueChecked = true;
                        return true;
                }

                $sql = 'SHOW INDEX FROM '.MAIN_DB_PREFIX."element_time WHERE Column_name='fk_elementdet'";
                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        // FR: Impossible de vérifier les index existants.
                        // EN: Unable to inspect existing indexes.
                        dol_syslog(__METHOD__.': Failed to inspect indexes for fk_elementdet - error='.$this->error, LOG_ERR);
                        $this->elementTimeUniqueChecked = true;
                        return false;
                }

                $hasUnique = false;
                while ($obj = $this->db->fetch_object($resql)) {
                        if (isset($obj->Non_unique) && (int) $obj->Non_unique === 0) {
                                $hasUnique = true;
                                break;
                        }
                }
                $this->db->free($resql);

                if ($hasUnique) {
                        $this->elementTimeUniqueChecked = true;
                        return true;
                }

                $sql = 'ALTER TABLE '.MAIN_DB_PREFIX."element_time ADD UNIQUE KEY uk_timesheetweek_fk_elementdet (fk_elementdet)";
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        // FR: L'ajout de la contrainte a échoué, journalise l'erreur pour analyse.
                        // EN: Adding the constraint failed, log the error for troubleshooting.
                        dol_syslog(__METHOD__.': Unable to add unique constraint on fk_elementdet'.(!empty($importKey) ? ' (key '.$importKey.')' : '').' - error='.$this->error, LOG_ERR);
                        $this->elementTimeUniqueChecked = true;
                        return false;
                }

                $this->elementTimeUniqueChecked = true;

                // FR: Confirme la création de l'index unique sur fk_elementdet.
                // EN: Confirm the creation of the unique index on fk_elementdet.
                dol_syslog(__METHOD__.': Added unique constraint on fk_elementdet', LOG_DEBUG);

                return true;
        }

        /**
         * Récupère la ligne llx_element_time associée à un temps Dolibarr.
         * Fetch the llx_element_time row linked to a Dolibarr task time entry.
         *
         * @param int $taskTimeId
         * @return array|false
         */
        protected function fetchElementTimeRowByTaskTimeId($taskTimeId)
        {
                $taskTimeId = (int) $taskTimeId;
                if ($taskTimeId <= 0) {
                        return array();
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        return false;
                }
                if (!isset($columns['fk_elementdet'])) {
                        // FR: La table ne référence pas directement les lignes de temps Dolibarr.
                        // EN: The table does not directly reference Dolibarr task time rows.
                        dol_syslog(__METHOD__.': fk_elementdet column missing, skip lookup for tasktime '.$taskTimeId, LOG_DEBUG);
                        return array();
                }

                $selectFields = array('rowid');
                if (isset($columns['import_key'])) {
                        $selectFields[] = 'import_key';
                }
                if (isset($columns['duration'])) {
                        $selectFields[] = 'duration';
                }

                $sql = 'SELECT '.implode(', ', $selectFields).' FROM '.MAIN_DB_PREFIX."element_time WHERE fk_elementdet=".$taskTimeId.' ORDER BY rowid DESC LIMIT 1';

                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        dol_syslog(__METHOD__.': Query failed for tasktime '.$taskTimeId.' - error='.$this->error, LOG_ERR);
                        return false;
                }

                $row = array();
                if ($obj = $this->db->fetch_object($resql)) {
                        $row['rowid'] = (int) $obj->rowid;
                        if (isset($obj->import_key)) {
                                $row['import_key'] = $obj->import_key;
                        }
                        if (isset($obj->duration)) {
                                $row['duration'] = (int) $obj->duration;
                        }
                }
                $this->db->free($resql);

                // FR: Trace le résultat de la recherche ciblée.
                // EN: Trace the outcome of the targeted lookup.
                dol_syslog(__METHOD__.': Lookup for tasktime '.$taskTimeId.' returned '.(!empty($row) ? '1' : '0').' row(s)', LOG_DEBUG);

                return $row;
        }

        /**
         * Récupère et mémorise les colonnes de llx_element_time.
         * Retrieve and cache llx_element_time columns metadata.
         *
         * @return array|false
         */
        protected function getElementTimeColumns()
        {
                if ($this->elementTimeColumnCache !== null) {
                        // FR: Utilise le cache si disponible et consigne l'opération.
                        // EN: Use the cache when available and log the operation.
                        dol_syslog(__METHOD__.': Using cached element_time metadata', LOG_DEBUG);
                        return $this->elementTimeColumnCache;
                }

                // FR: Journalise le chargement des métadonnées depuis la base.
                // EN: Log the metadata load from the database.
                dol_syslog(__METHOD__.': Loading element_time metadata from database', LOG_DEBUG);

                $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."element_time";
                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        return false;
                }

                $columns = array();
                while ($obj = $this->db->fetch_object($resql)) {
                        if (!empty($obj->Field)) {
                                $columns[$obj->Field] = $obj;
                        }
                }
                $this->db->free($resql);

                $this->elementTimeColumnCache = $columns;

                // FR: Journalise le nombre de colonnes détectées.
                // EN: Log the number of detected columns.
                dol_syslog(__METHOD__.': Cached '.count($columns).' element_time columns', LOG_DEBUG);

                return $this->elementTimeColumnCache;
        }

        /**
         * Déclenche un message d'erreur utilisateur lié au miroir element_time.
         * Emit a user-facing error message related to the element_time mirror.
         *
         * @param string $context Informations additionnelles à afficher / Additional context to display.
         * @return void
         */
        protected function notifyElementTimeError($context = '')
        {
                global $langs;

                if (!is_object($langs)) {
                        return;
                }

                // FR: Construit un message combinant la traduction et le contexte éventuel.
                // EN: Build a message mixing the translation and the optional context.
                $message = $langs->trans('TimesheetWeekElementTimeMirrorError');
                if ($context !== '') {
                        $message .= ' '.$context;
                }
                if (!empty($this->error)) {
                        $message .= ' ('.$this->error.')';
                }

                setEventMessages($message, null, 'errors');
        }

        /**
         * Remove any llx_element_time rows linked to the provided import key.
         *
         * @param string $importKey
         * @return int
         */
        protected function deleteElementTimeByImportKey($importKey)
        {
                if (empty($importKey)) {
                        // FR: Aucun import key fourni, rien à supprimer.
                        // EN: No import key provided, nothing to delete.
                        dol_syslog(__METHOD__.': No import key provided for deletion', LOG_DEBUG);
                        return 1;
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        // FR: Impossible de lire la structure de la table lors de la suppression.
                        // EN: Unable to read the table structure during deletion.
                        dol_syslog(__METHOD__.': Could not load element_time columns while deleting '.$importKey, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }
                if (!isset($columns['import_key'])) {
                        // FR: La colonne import_key n'existe pas, aucune suppression nécessaire.
                        // EN: The import_key column is missing, nothing to delete.
                        dol_syslog(__METHOD__.': import_key column missing, skip deletion for '.$importKey, LOG_DEBUG);
                        return 1;
                }

                $sql = "DELETE FROM ".MAIN_DB_PREFIX."element_time WHERE import_key='".$this->db->escape($importKey)."'";
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        // FR: La suppression a échoué, journalise l'erreur et avertit l'utilisateur.
                        // EN: Deletion failed, log the error and warn the user.
                        dol_syslog(__METHOD__.': Failed to delete rows for '.$importKey.' - error='.$this->error, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return -1;
                }

                // FR: Suppression effectuée avec succès.
                // EN: Deletion completed successfully.
                dol_syslog(__METHOD__.': Deleted element_time rows for '.$importKey, LOG_DEBUG);

                return 1;
        }

        /**
         * Fetch llx_element_time rows linked to an import key.
         *
         * @param string $importKey
         * @param bool   $withDetails FR: Inclure fk_element/fk_elementdet si disponibles. / EN: Include fk_element/fk_elementdet when available.
         * @return array|false
         */
        protected function fetchElementTimeRowsByImportKey($importKey, $withDetails = false)
        {
                if (empty($importKey)) {
                        // FR: Sans clé d'import, aucune lecture n'est nécessaire.
                        // EN: Without an import key, no lookup is required.
                        dol_syslog(__METHOD__.': No import key provided for fetch', LOG_DEBUG);
                        return array();
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        // FR: Impossible d'obtenir la structure de la table pendant la lecture.
                        // EN: Unable to obtain the table structure during lookup.
                        dol_syslog(__METHOD__.': Could not load element_time columns while fetching '.$importKey, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return false;
                }
                if (!isset($columns['import_key'])) {
                        // FR: Si la colonne n'existe pas, aucune ligne ne peut être liée.
                        // EN: If the column is missing, no linked rows can exist.
                        dol_syslog(__METHOD__.': import_key column missing, returning empty for '.$importKey, LOG_DEBUG);
                        return array();
                }

                $selectFields = array('rowid');
                if ($withDetails) {
                        // FR: Ajoute les colonnes détaillées uniquement si elles existent.
                        // EN: Append detailed columns only when they exist.
                        if (isset($columns['fk_element'])) {
                                $selectFields[] = 'fk_element';
                        }
                        if (isset($columns['fk_elementdet'])) {
                                $selectFields[] = 'fk_elementdet';
                        }
                }

                $sql = 'SELECT '.implode(', ', $selectFields).' FROM '.MAIN_DB_PREFIX."element_time WHERE import_key='".$this->db->escape($importKey)."'";

                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        // FR: Journalise l'échec de la requête et avertit l'utilisateur.
                        // EN: Log the query failure and notify the user.
                        dol_syslog(__METHOD__.': Query failed for '.$importKey.' - error='.$this->error, LOG_ERR);
                        $this->notifyElementTimeError('['.$importKey.']');
                        return false;
                }

                $rows = array();
                while ($obj = $this->db->fetch_object($resql)) {
                        $row = array(
                                'rowid' => (int) $obj->rowid,
                                'id' => (int) $obj->rowid,
                        );
                        if ($withDetails) {
                                if (isset($obj->fk_element)) {
                                        $row['fk_element'] = (int) $obj->fk_element;
                                }
                                if (isset($obj->fk_elementdet)) {
                                        $row['fk_elementdet'] = (int) $obj->fk_elementdet;
                                }
                        }
                        $rows[] = $row;
                }
                $this->db->free($resql);

                // FR: Indique combien de lignes ont été trouvées pour la clé demandée.
                // EN: Indicate how many rows were found for the requested key.
                dol_syslog(__METHOD__.': Fetched '.count($rows).' rows for '.$importKey, LOG_DEBUG);

                return $rows;
        }

        /**
         * Récupère toutes les lignes element_time liées à cette feuille via sa clé d'import.
         * Fetch all element_time rows linked to this sheet through its import key prefix.
         *
         * @param bool $withDetails FR: Inclure les colonnes de liaison si disponibles. / EN: Include linking columns when available.
         * @return array|false
         */
        protected function fetchElementTimeRowsByTimesheet($withDetails = false)
        {
                $timesheetId = (int) $this->id;
                if ($timesheetId <= 0) {
                        return array();
                }

                $columns = $this->getElementTimeColumns();
                if ($columns === false) {
                        // FR: Impossible de récupérer la structure de la table pour le chargement global.
                        // EN: Unable to retrieve the table structure for the bulk load.
                        dol_syslog(__METHOD__.': Could not load element_time columns for timesheet '.$timesheetId, LOG_ERR);
                        $this->notifyElementTimeError('[tswk:'.$timesheetId.']');
                        return false;
                }
                if (!isset($columns['import_key'])) {
                        // FR: Sans colonne import_key, il n'existe aucune ligne à purger.
                        // EN: Without an import_key column, there are no rows to purge.
                        dol_syslog(__METHOD__.': import_key column missing, returning empty for timesheet '.$timesheetId, LOG_DEBUG);
                        return array();
                }

                $selectFields = array('rowid', 'import_key');
                if ($withDetails) {
                        if (isset($columns['fk_element'])) {
                                $selectFields[] = 'fk_element';
                        }
                        if (isset($columns['fk_elementdet'])) {
                                $selectFields[] = 'fk_elementdet';
                        }
                }

                $prefix = $this->db->escape('tswk:'.$timesheetId.':');
                $sql = 'SELECT '.implode(', ', $selectFields).' FROM '.MAIN_DB_PREFIX."element_time WHERE import_key LIKE '".$prefix."%'";

                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        // FR: Journalise l'échec de la requête et avertit l'utilisateur.
                        // EN: Log the query failure and warn the user.
                        dol_syslog(__METHOD__.': Query failed for timesheet '.$timesheetId.' - error='.$this->error, LOG_ERR);
                        $this->notifyElementTimeError('[tswk:'.$timesheetId.']');
                        return false;
                }

                $rows = array();
                while ($obj = $this->db->fetch_object($resql)) {
                        $row = array(
                                'id' => (int) $obj->rowid,
                                'import_key' => (string) $obj->import_key,
                        );
                        if ($withDetails) {
                                if (isset($obj->fk_element)) {
                                        $row['fk_element'] = (int) $obj->fk_element;
                                }
                                if (isset($obj->fk_elementdet)) {
                                        $row['fk_elementdet'] = (int) $obj->fk_elementdet;
                                }
                        }
                        $rows[] = $row;
                }
                $this->db->free($resql);

                // FR: Trace le nombre total de lignes récupérées pour cette feuille.
                // EN: Trace the total number of rows retrieved for this sheet.
                dol_syslog(__METHOD__.': Fetched '.count($rows).' rows for timesheet '.$timesheetId, LOG_DEBUG);

                return $rows;
        }

        /**
         * Convert an hours value to seconds for task time tracking.
         *
         * @param float|int|string $hours
         * @return int
         */
        protected function convertHoursToSeconds($hours)
        {
                return (int) round(((float) $hours) * 3600);
        }

        /**
         * Resolve the identifier of a line object.
         *
         * @param TimesheetWeekLine|object $line
         * @return int
         */
        protected function getLineIdentifier($line)
        {
                if (!is_object($line)) {
                        return 0;
                }

                if (!empty($line->id)) {
                        return (int) $line->id;
                }

                if (!empty($line->rowid)) {
                        return (int) $line->rowid;
                }

                return 0;
        }

        /**
         * Resolve the timestamp to use for a line date value.
         *
         * @param mixed $value
         * @return int
         */
        protected function resolveLineDate($value)
        {
                if (empty($value)) {
                        return dol_now();
                }

                if (is_numeric($value)) {
                        $timestamp = (int) $value;
                        if ($timestamp > 0) {
                                return $timestamp;
                        }
                }

                if ($value instanceof \DateTimeInterface) {
                        return $value->getTimestamp();
                }

                $timestamp = strtotime((string) $value);
                if ($timestamp > 0) {
                        return $timestamp;
                }

                $timestamp = dol_stringtotime((string) $value, '%Y-%m-%d');
                if ($timestamp > 0) {
                        return $timestamp;
                }

                return dol_now();
        }

        /**
         * Build the note stored on the generated time spent entry.
         *
         * @param TimesheetWeekLine|object $line
         * @param int                      $timestamp
         * @return string
         */
        protected function buildTimeSpentNote($line, $timestamp)
        {
                $label = 'Timesheet '.$this->ref;
                if ($timestamp > 0) {
                        $label .= ' - '.dol_print_date($timestamp, 'day');
                }

                if (!empty($line->hours)) {
                        $label .= ' ('.round((float) $line->hours, 2).'h)';
                }

                return $label;
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

                $statusInfo = array(
                        self::STATUS_DRAFT => array(
                                'label' => $langs->trans('TimesheetWeekStatusDraft'),
                                'picto' => 'statut0',
                                'class' => 'badge badge-status badge-status0',
                        ),
                        self::STATUS_SUBMITTED => array(
                                'label' => $langs->trans('TimesheetWeekStatusSubmitted'),
                                'picto' => 'statut1',
                                'class' => 'badge badge-status badge-status1',
                        ),
                        self::STATUS_APPROVED => array(
                                'label' => $langs->trans('TimesheetWeekStatusApproved'),
                                'picto' => 'statut4',
                                'class' => 'badge badge-status badge-status4',
                        ),
                        self::STATUS_REFUSED => array(
                                'label' => $langs->trans('TimesheetWeekStatusRefused'),
                                'picto' => 'statut6',
                                'class' => 'badge badge-status badge-status6',
                        ),
                );

                $info = $statusInfo[$status] ?? array(
                        'label' => $langs->trans('Unknown'),
                        'picto' => 'statut0',
                        'class' => 'badge badge-status badge-status0',
                );

                if ((int) $mode === 5) {
                        return '<span class="'.$info['class'].'" title="'.dol_escape_htmltag($info['label']).'">'
                                .dol_escape_htmltag($info['label']).'</span>';
                }

                $picto = img_picto($info['label'], $info['picto']);
		if ((int) $mode === 1) return $picto;
                if ((int) $mode === 2) return $picto.' '.$info['label'];
                if ((int) $mode === 3) return $info['label'].' '.$picto;
                return $info['label'];
        }

        /**
         * Create an agenda event for this timesheet action
         *
         * @param User   $user
         * @param string $code       Internal agenda code (ex: TSWK_SUBMIT)
         * @param string $labelKey   Translation key for event label
         * @param array  $labelParams Parameters passed to translation
         * @param bool   $linkToObject Whether to create an object link
         *
         * @return bool
         */
        protected function createAgendaEvent($user, $code, $labelKey, array $labelParams = array(), $linkToObject = true)
        {
                global $conf, $langs;

                if (!function_exists('isModEnabled') || !isModEnabled('agenda')) {
                        return true;
                }

                $langs->loadLangs(array('timesheetweek@timesheetweek', 'agenda'));

                dol_include_once('/comm/action/class/actioncomm.class.php');

                $args = array_merge(array($labelKey), $labelParams);
                $label = call_user_func_array(array($langs, 'trans'), $args);
                if ($label === $labelKey) {
                        $label = $langs->trans('TimesheetWeekAgendaDefaultLabel', $this->ref);
                }

                $now = dol_now();

                $event = new ActionComm($this->db);
                $event->type_code = 'AC_OTH_AUTO';
                $event->code = $code;
                $event->label = $label;
                $event->note_private = $label;
                $event->fk_user_author = (int) $user->id;
                $event->fk_user_mod = (int) $user->id;
                $ownerId = (int) (!empty($user->id) ? $user->id : ($this->fk_user ?: 0));
                $event->userownerid = $ownerId;
                if (property_exists($event, 'fk_user_action')) {
                        $event->fk_user_action = $ownerId;
                }
                $event->datep = $now;
                $event->datef = $now;
                $event->percentage = -1;
                $event->priority = 0;
                $event->fulldayevent = 0;
                $event->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;

                if (!empty($this->fk_user)) {
                        $event->userassigned = array(
                                (int) $this->fk_user => array('id' => (int) $this->fk_user),
                        );
                }

                if ($linkToObject) {
                        $event->elementtype = $this->element;
                        $event->fk_element = (int) $this->id;
                }

                $res = $event->create($user);
                if ($res <= 0) {
                        $this->error = !empty($event->error) ? $event->error : 'AgendaEventCreationFailed';
                        if (!empty($event->errors)) {
                                $this->errors = array_merge($this->errors, $event->errors);
                        }
                        return false;
                }

                if ($linkToObject && method_exists($event, 'add_object_linked')) {
                        $event->add_object_linked($this->element, (int) $this->id);
                }

                return true;
        }
}
