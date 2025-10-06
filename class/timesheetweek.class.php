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
                $this->sendAutomaticNotification('TIMESHEETWEEK_SUBMITTED', $user);
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

                if (!$this->createAgendaEvent($user, 'TSWK_APPROVE', 'TimesheetWeekAgendaApproved', array($this->ref))) {
                        $this->db->rollback();
                        return -1;
                }

                $this->db->commit();
                $this->sendAutomaticNotification('TIMESHEETWEEK_APPROVED', $user);
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
                $this->sendAutomaticNotification('TIMESHEETWEEK_REFUSED', $user);
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
                global $conf, $langs;

                $langs->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));

                $recipients = array();
                $subjectKey = '';
                $bodyKey = '';
                $missingKey = '';

                if ($triggerCode === 'TIMESHEETWEEK_SUBMITTED') {
                        $subjectKey = 'TimesheetWeekNotificationSubmitSubject';
                        $bodyKey = 'TimesheetWeekNotificationSubmitBody';
                        $missingKey = 'TimesheetWeekNotificationValidatorFallback';
                        $target = $this->loadUserFromCache($this->fk_user_valid);
                        if ($target) {
                                $recipients[] = $target;
                        }
                } elseif ($triggerCode === 'TIMESHEETWEEK_APPROVED') {
                        $subjectKey = 'TimesheetWeekNotificationApproveSubject';
                        $bodyKey = 'TimesheetWeekNotificationApproveBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $target = $this->loadUserFromCache($this->fk_user);
                        if ($target) {
                                $recipients[] = $target;
                        }
                } elseif ($triggerCode === 'TIMESHEETWEEK_REFUSED') {
                        $subjectKey = 'TimesheetWeekNotificationRefuseSubject';
                        $bodyKey = 'TimesheetWeekNotificationRefuseBody';
                        $missingKey = 'TimesheetWeekNotificationEmployeeFallback';
                        $target = $this->loadUserFromCache($this->fk_user);
                        if ($target) {
                                $recipients[] = $target;
                        }
                } else {
                        return true;
                }

                if (empty($recipients)) {
                        $this->errors[] = $langs->trans('TimesheetWeekNotificationMissingRecipient', $langs->trans($missingKey));
                        dol_syslog(__METHOD__.': '.$this->errors[count($this->errors) - 1], LOG_WARNING);
                        return false;
                }

                dol_include_once('/core/lib/functions2.lib.php');
                if (is_readable(DOL_DOCUMENT_ROOT.'/core/class/cemailtemplates.class.php')) {
                        dol_include_once('/core/class/cemailtemplates.class.php');
                } else {
                        dol_include_once('/core/class/emailtemplates.class.php');
                }
                dol_include_once('/core/class/CMailFile.class.php');

                $templateClass = '';
                if (class_exists('CEmailTemplates')) {
                        $templateClass = 'CEmailTemplates';
                } elseif (class_exists('EmailTemplates')) {
                        $templateClass = 'EmailTemplates';
                }

                if (empty($templateClass)) {
                        $this->errors[] = $langs->trans('ErrorFailedToLoadEmailTemplateClass');
                        dol_syslog(__METHOD__.': '.$this->errors[count($this->errors) - 1], LOG_ERR);
                        return false;
                }

                $employee = $this->loadUserFromCache($this->fk_user);
                $validator = $this->loadUserFromCache($this->fk_user_valid);
                $employeeName = $employee ? $employee->getFullName($langs) : '';
                $validatorName = $validator ? $validator->getFullName($langs) : '';
                $actionUserName = $actionUser->getFullName($langs);
                $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $this->id;

                $overallResult = true;

                foreach ($recipients as $recipient) {
                        if (empty($recipient->email)) {
                                $this->errors[] = $langs->trans('TimesheetWeekNotificationNoEmail', $recipient->getFullName($langs));
                                dol_syslog(__METHOD__.': '.$this->errors[count($this->errors) - 1], LOG_WARNING);
                                $overallResult = false;
                                continue;
                        }

                        $substitutions = array(
                                '__TIMESHEETWEEK_REF__' => $this->ref,
                                '__TIMESHEETWEEK_WEEK__' => $this->week,
                                '__TIMESHEETWEEK_YEAR__' => $this->year,
                                '__TIMESHEETWEEK_URL__' => $url,
                                '__TIMESHEETWEEK_EMPLOYEE_FULLNAME__' => $employeeName,
                                '__TIMESHEETWEEK_VALIDATOR_FULLNAME__' => $validatorName,
                                '__ACTION_USER_FULLNAME__' => $actionUserName,
                                '__RECIPIENT_FULLNAME__' => $recipient->getFullName($langs),
                        );

                        $template = new $templateClass($this->db);
                        $tplResult = $template->fetchByTrigger($triggerCode, $actionUser, $conf->entity);

                        $subject = '';
                        $message = '';
                        $ccList = array();
                        $bccList = array();
                        $sendtoList = array($recipient->email);
                        $emailFrom = $actionUser->email;
                        if (empty($emailFrom) && !empty($conf->global->MAIN_MAIL_EMAIL_FROM)) {
                                $emailFrom = $conf->global->MAIN_MAIL_EMAIL_FROM;
                        }
                        if (empty($emailFrom) && !empty($conf->global->MAIN_INFO_SOCIETE_MAIL)) {
                                $emailFrom = $conf->global->MAIN_INFO_SOCIETE_MAIL;
                        }

                        if ($tplResult > 0) {
                                $subjectTemplate = !empty($template->subject) ? $template->subject : $template->topic;
                                $bodyTemplate = $template->content;

                                $subject = make_substitutions($subjectTemplate, $substitutions);
                                $message = make_substitutions($bodyTemplate, $substitutions);

                                if (!empty($template->email_from)) {
                                        $emailFrom = make_substitutions($template->email_from, $substitutions);
                                }
                                $templateTo = '';
                                if (!empty($template->email_to)) {
                                        $templateTo = $template->email_to;
                                } elseif (!empty($template->email_to_list)) {
                                        $templateTo = $template->email_to_list;
                                }
                                if (!empty($templateTo)) {
                                        $templateTo = make_substitutions($templateTo, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateTo) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $sendtoList, true)) {
                                                        $sendtoList[] = $addr;
                                                }
                                        }
                                }

                                $templateCc = '';
                                if (!empty($template->email_cc)) {
                                        $templateCc = $template->email_cc;
                                } elseif (!empty($template->email_to_cc)) {
                                        $templateCc = $template->email_to_cc;
                                }
                                if (!empty($templateCc)) {
                                        $templateCc = make_substitutions($templateCc, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateCc) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $ccList, true)) {
                                                        $ccList[] = $addr;
                                                }
                                        }
                                }

                                $templateBcc = '';
                                if (!empty($template->email_bcc)) {
                                        $templateBcc = $template->email_bcc;
                                } elseif (!empty($template->email_to_bcc)) {
                                        $templateBcc = $template->email_to_bcc;
                                }
                                if (!empty($templateBcc)) {
                                        $templateBcc = make_substitutions($templateBcc, $substitutions);
                                        foreach (preg_split('/[,;]+/', $templateBcc) as $addr) {
                                                $addr = trim($addr);
                                                if ($addr && !in_array($addr, $bccList, true)) {
                                                        $bccList[] = $addr;
                                                }
                                        }
                                }
                        } else {
                                $recipientName = $recipient->getFullName($langs);
                                if ($triggerCode === 'TIMESHEETWEEK_SUBMITTED') {
                                        $subject = $langs->trans($subjectKey, $this->ref);
                                        $message = $langs->trans(
                                                $bodyKey,
                                                $recipientName,
                                                $employeeName,
                                                $this->ref,
                                                $this->week,
                                                $this->year,
                                                $url,
                                                $actionUserName
                                        );
                                } else {
                                        $subject = $langs->trans($subjectKey, $this->ref);
                                        $message = $langs->trans(
                                                $bodyKey,
                                                $recipientName,
                                                $this->ref,
                                                $this->week,
                                                $this->year,
                                                $actionUserName,
                                                $url,
                                                $actionUserName
                                        );
                                }
                        }

                        if (empty($subject) || empty($message)) {
                                $this->errors[] = $langs->trans('TimesheetWeekNotificationMailError', 'Empty template');
                                dol_syslog(__METHOD__.': '.$this->errors[count($this->errors) - 1], LOG_WARNING);
                                $overallResult = false;
                                continue;
                        }

                        $sendto = implode(',', array_unique(array_filter($sendtoList)));
                        $cc = implode(',', array_unique(array_filter($ccList)));
                        $bcc = implode(',', array_unique(array_filter($bccList)));

                        $mail = new CMailFile($subject, $sendto, $emailFrom, $message, array(), array(), array(), $cc, $bcc, 0, 0);
                        if (!$mail->sendfile()) {
                                $errmsg = $mail->error ? $mail->error : 'Unknown error';
                                $this->errors[] = $langs->trans('TimesheetWeekNotificationMailError', $errmsg);
                                dol_syslog(__METHOD__.': '.$errmsg, LOG_WARNING);
                                $overallResult = false;
                        }
                }

                return $overallResult;
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
