<?php
/* Copyright (C) 2017       Laurent Destailleur
 * Copyright (C) 2023-2024  Frédéric France
 * Copyright (C) 2025       Pierre ARDOIN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License...
 */

/**
 * \file        class/timesheetweek.class.php
 * \ingroup     timesheetweek
 * \brief       CRUD class for TimesheetWeek
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for TimesheetWeek
 */
class TimesheetWeek extends CommonObject
{
	/** @var string ID of module */
	public $module = 'timesheetweek';

	/** @var string Object element */
	public $element = 'timesheetweek';

	/** @var string Main table name without prefix */
	public $table_element = 'timesheet_week';

	/** @var string Icon */
	public $picto = 'time';

	/** @var int Does object support extrafields ? 0=No, 1=Yes */
	public $isextrafieldmanaged = 0;

	/** @var int|string|null Multicompany support */
	public $ismultientitymanaged = 1; // local field 'entity'

	// -------------------- Status model (unifié) --------------------
	const STATUS_DRAFT      = 0;
	const STATUS_INPROGRESS = 1;
	const STATUS_SUBMITTED  = 2;
	const STATUS_APPROVED   = 3;
	const STATUS_REFUSED    = 4;

	/** @var array<int,string> Labels */
	public static $status_labels = array(
		self::STATUS_DRAFT      => 'Draft',
		self::STATUS_INPROGRESS => 'InProgress',
		self::STATUS_SUBMITTED  => 'Submitted',
		self::STATUS_APPROVED   => 'Approved',
		self::STATUS_REFUSED    => 'Refused',
	);

	// -------------------- Fields definition (for CommonObject) --------------------
	public $fields = array(
		'rowid'           => array('type'=>'int',             'label'=>'TechnicalID',     'enabled'=>'1', 'position'=>10,  'notnull'=>1, 'visible'=>'0'),
		'entity'          => array('type'=>'integer',         'label'=>'Entity',          'enabled'=>'1', 'position'=>12,  'notnull'=>1, 'visible'=>'0', 'default'=>'1', 'index'=>1),
		'ref'             => array('type'=>'varchar(50)',     'label'=>'Ref',             'enabled'=>'1', 'position'=>15,  'notnull'=>1, 'visible'=>'1', 'csslist'=>'tdoverflowmax150', 'showoncombobox'=>'1'),
		'fk_user'         => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'Fkuser', 'picto'=>'user', 'enabled'=>'1', 'position'=>20, 'notnull'=>1, 'visible'=>'-1', 'css'=>'maxwidth500 widthcentpercentminusxx', 'csslist'=>'tdoverflowmax150'),
		'year'            => array('type'=>'smallint',        'label'=>'Year',            'enabled'=>'1', 'position'=>25,  'notnull'=>1, 'visible'=>'-1'),
		'week'            => array('type'=>'smallint',        'label'=>'Week',            'enabled'=>'1', 'position'=>30,  'notnull'=>1, 'visible'=>'-1'),
		'status'          => array('type'=>'smallint',        'label'=>'Status',          'enabled'=>'1', 'position'=>500, 'notnull'=>1, 'visible'=>'-1', 'default'=>'0'),
		'note'            => array('type'=>'text',            'label'=>'Note',            'enabled'=>'1', 'position'=>45,  'notnull'=>0, 'visible'=>'-1'),
		'date_creation'   => array('type'=>'datetime',        'label'=>'DateCreation',    'enabled'=>'1', 'position'=>50,  'notnull'=>0, 'visible'=>'-1'),
		'date_validation' => array('type'=>'datetime',        'label'=>'Datevalidation',  'enabled'=>'1', 'position'=>55,  'notnull'=>0, 'visible'=>'-1'),
		'fk_user_valid'   => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserValidation', 'picto'=>'user', 'enabled'=>'1', 'position'=>60, 'notnull'=>0, 'visible'=>'-1', 'css'=>'maxwidth500 widthcentpercentminusxx', 'csslist'=>'tdoverflowmax150'),
		'total_hours'     => array('type'=>'double(24,8)',    'label'=>'TotalHours',      'enabled'=>'1', 'position'=>62,  'notnull'=>0, 'visible'=>'-1', 'default'=>'0'),
		'overtime_hours'  => array('type'=>'double(24,8)',    'label'=>'Overtime',        'enabled'=>'1', 'position'=>63,  'notnull'=>0, 'visible'=>'-1', 'default'=>'0'),
		'tms'             => array('type'=>'timestamp',       'label'=>'DateModification','enabled'=>'1', 'position'=>65,  'notnull'=>0, 'visible'=>'-1'),
	);

	// Public properties (mirror of fields)
	public $rowid;
	public $entity;
	public $ref;
	public $fk_user;
	public $year;
	public $week;
	public $status;
	public $note;
	public $date_creation;
	public $date_validation;
	public $fk_user_valid;
	public $total_hours;
	public $overtime_hours;
	public $tms;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		// Hide rowid if needed
		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}

		// Remove disabled fields
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate arrayofkeyval if needed
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database (custom INSERT to control ref/entity)
	 *
	 * @param	User $user
	 * @param	int $notrigger
	 * @return	int  <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $langs, $conf;

		$error = 0;
		$now = dol_now();

		// Safety defaults
		if (empty($this->entity)) $this->entity = (int) $conf->entity;
		if (empty($this->status) && $this->status !== 0) $this->status = self::STATUS_DRAFT;
		if (!isset($this->note)) $this->note = '';
		if (!isset($this->total_hours)) $this->total_hours = 0;
		if (!isset($this->overtime_hours)) $this->overtime_hours = 0;

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week(";
		$sql .= "entity, ref, fk_user, year, week, status, note, date_creation, fk_user_valid, total_hours, overtime_hours";
		$sql .= ") VALUES (";
		$sql .= (int) $this->entity.",";
		$sql .= "'".$this->db->escape($this->ref ? $this->ref : '(PROV)')."',";
		$sql .= (int) $this->fk_user.",";
		$sql .= (int) $this->year.",";
		$sql .= (int) $this->week.",";
		$sql .= (int) $this->status.",";
		$sql .= "'".$this->db->escape($this->note)."',";
		$sql .= "'".$this->db->idate($now)."',";
		$sql .= (!empty($this->fk_user_valid) ? (int) $this->fk_user_valid : "NULL").",";
		$sql .= (float) $this->total_hours.",";
		$sql .= (float) $this->overtime_hours;
		$sql .= ")";

		dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."timesheet_week");
		$this->rowid = $this->id;

		// Auto-generate reference as (PRO{id}) if was (PROV) or empty
		if (empty($this->ref) || preg_match('/^\(PROV\)$/i', (string) $this->ref)) {
			$newref = '(PRO'.$this->id.')';
			$upd = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET ref='".$this->db->escape($newref)."' WHERE rowid=".(int) $this->id;
			if ($this->db->query($upd)) {
				$this->ref = $newref;
			}
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Fetch object
	 *
	 * @param int $id
	 * @param string|null $ref
	 * @param int $noextrafields
	 * @param int $nolines
	 * @return int
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		// No automatic fetch lines here. Use getLines() if needed.
		return $result;
	}

	/**
	 * Update object into database (common)
	 *
	 * @param User $user
	 * @param int $notrigger
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param	User $user
	 * @param	int $notrigger
	 * @return	int
	 */
	public function delete($user, $notrigger = 0)
	{
		// Optionally delete lines here if you don't have ON DELETE CASCADE on fk
		return $this->deleteCommon($user, $notrigger);
	}

	// -------------------- Status helpers --------------------

	/**
	 * Get label of status with/without picto
	 *
	 * @param int $mode 0=long, 1=short, 2=Picto + short, 3=Picto, 4=Picto + long, 5=Short + Picto, 6=Long + Picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return the label of a given status
	 *
	 * @param int $status
	 * @param int $mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		if (is_null($status)) return '';

		global $langs;

		$labels = array(
			self::STATUS_DRAFT      => array('short'=>$langs->trans('Draft'),     'long'=>$langs->trans('Draft'),     'type'=>'status0'),
			self::STATUS_INPROGRESS => array('short'=>$langs->trans('InProgress'),'long'=>$langs->trans('InProgress'),'type'=>'status3'),
			self::STATUS_SUBMITTED  => array('short'=>$langs->trans('Submitted'), 'long'=>$langs->trans('Submitted'), 'type'=>'status1'),
			self::STATUS_APPROVED   => array('short'=>$langs->trans('Approved'),  'long'=>$langs->trans('Approved'),  'type'=>'status4'),
			self::STATUS_REFUSED    => array('short'=>$langs->trans('Refused'),   'long'=>$langs->trans('Refused'),   'type'=>'status6'),
		);

		$info = isset($labels[$status]) ? $labels[$status] : array('short'=>$status, 'long'=>$status, 'type'=>'status0');
		return dolGetStatus($info['long'], $info['short'], '', $info['type'], $mode);
	}

	// -------------------- URL / Tooltip --------------------

	/**
	 * Return a link to the object card
	 *
	 * @param int $withpicto 0=No, 1=Include picto, 2=Only picto
	 * @param string $option
	 * @param int $notooltip
	 * @param string $morecss
	 * @param int $save_lastsearch_value
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		$url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.$this->id;

		$label = $langs->trans("TimesheetWeek").' : '.$this->ref;
		$linkstart = '<a href="'.$url.'"'.($notooltip ? '' : ' title="'.dolPrintHTMLForAttribute($label).'"').($morecss ? ' class="'.$morecss.'"' : '').'>';
		$linkend = '</a>';

		$result = $linkstart;
		if ($withpicto) $result .= img_object($label, ($this->picto ? $this->picto : 'generic'), ($withpicto != 2 ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
		if ($withpicto != 2) $result .= $this->ref;
		$result .= $linkend;

		return $result;
	}

	// -------------------- Business helpers --------------------

	/**
	 * Get tasks assigned to a user (grouped by project in your view)
	 * Uses element_contact on project_task, compatible with your DB usage
	 *
	 * @param int $userid
	 * @return array<int,array{task_id:int,task_label:string,project_id:int,project_ref:string,project_title:string}>
	 */
	public function getAssignedTasks($userid)
	{
		$records = array();
		if (empty($userid)) return $records;

		$sql = "SELECT t.rowid as task_id, t.label as task_label,";
		$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_contact ec ON ec.element_id = t.rowid";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ec.fk_socpeople";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND ctc.element = 'project_task'";
		// L'utilisateur peut être stocké dans ec.fk_socpeople (directement avec rowid user)
		$sql .= " AND (u.rowid = ".((int) $userid)." OR ec.fk_socpeople = ".((int) $userid).")";
		$sql .= " ORDER BY p.ref, t.label";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$records[] = array(
					'task_id'       => (int) $obj->task_id,
					'task_label'    => (string) $obj->task_label,
					'project_id'    => (int) $obj->project_id,
					'project_ref'   => (string) $obj->project_ref,
					'project_title' => (string) $obj->project_title,
				);
			}
			$this->db->free($resql);
		} else {
			dol_syslog(__METHOD__.' sql error: '.$this->db->lasterror(), LOG_ERR);
		}

		return $records;
	}

	/**
	 * Return all saved lines for this timesheet (mapped by task/date)
	 * @return array<string,array{rowid:int,task_id:int,day_date:string,hours:float,zone:int,meal:int}>
	 */
	public function getLines()
	{
		$out = array();
		if (empty($this->id)) return $out;

		$sql = "SELECT l.rowid, l.fk_task as task_id, l.day_date, l.hours, l.zone, l.meal";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line l";
		$sql .= " WHERE l.fk_timesheet_week = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$key = (int) $obj->task_id.'|'.(string) $obj->day_date;
				$out[$key] = array(
					'rowid'     => (int) $obj->rowid,
					'task_id'   => (int) $obj->task_id,
					'day_date'  => (string) $obj->day_date,
					'hours'     => (float) $obj->hours,
					'zone'      => (int) $obj->zone,
					'meal'      => (int) $obj->meal,
				);
			}
			$this->db->free($resql);
		}
		return $out;
	}

	/**
	 * Fetch one line for this timesheet
	 *
	 * @param int $taskId
	 * @param string $dayYmd YYYY-MM-DD
	 * @return array|null
	 */
	public function getLine($taskId, $dayYmd)
	{
		if (empty($this->id) || empty($taskId) || empty($dayYmd)) return null;

		$sql = "SELECT l.rowid, l.fk_task as task_id, l.day_date, l.hours, l.zone, l.meal";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line l";
		$sql .= " WHERE l.fk_timesheet_week = ".((int) $this->id);
		$sql .= " AND l.fk_task = ".((int) $taskId);
		$sql .= " AND l.day_date = '".$this->db->escape($dayYmd)."'";

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$this->db->free($resql);
			return array(
				'rowid'     => (int) $obj->rowid,
				'task_id'   => (int) $obj->task_id,
				'day_date'  => (string) $obj->day_date,
				'hours'     => (float) $obj->hours,
				'zone'      => (int) $obj->zone,
				'meal'      => (int) $obj->meal,
			);
		}
		return null;
	}

	/**
	 * Insert or update one line (for current timesheet)
	 *
	 * @param int $taskId
	 * @param string $dayYmd
	 * @param float $hours
	 * @param int $zone
	 * @param int $meal
	 * @return int >0 OK (rowid) , <0 KO
	 */
	public function upsertLine($taskId, $dayYmd, $hours, $zone = 0, $meal = 0)
	{
		global $conf;

		if (empty($this->id) || empty($taskId) || empty($dayYmd)) {
			$this->error = 'MissingParameter';
			return -1;
		}

		$exists = $this->getLine($taskId, $dayYmd);
		if ($exists) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET";
			$sql .= " hours = ".((float) $hours).",";
			$sql .= " zone = ".((int) $zone).",";
			$sql .= " meal = ".((int) $meal);
			$sql .= " WHERE rowid = ".((int) $exists['rowid']);

			if ($this->db->query($sql)) return (int) $exists['rowid'];

			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(";
		$sql .= "entity, fk_timesheet_week, fk_task, day_date, hours, zone, meal";
		$sql .= ") VALUES (";
		$sql .= (int) $this->entity.",";
		$sql .= (int) $this->id.",";
		$sql .= (int) $taskId.",";
		$sql .= "'".$this->db->escape($dayYmd)."',";
		$sql .= (float) $hours.",";
		$sql .= (int) $zone.",";
		$sql .= (int) $meal;
		$sql .= ")";

		if ($this->db->query($sql)) {
			return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'timesheet_week_line');
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Recompute and update totals (total_hours & overtime_hours)
	 *
	 * @param float $weeklyContractHours
	 * @return int 1 if OK, <0 if KO
	 */
	public function updateTotals($weeklyContractHours = 35.0)
	{
		$total = 0.0;

		$sql = "SELECT SUM(hours) as th";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE fk_timesheet_week = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$total = (float) ($obj ? $obj->th : 0);
			$this->db->free($resql);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$overtime = $total - (float) $weeklyContractHours;
		if ($overtime < 0) $overtime = 0.0;

		$upd = "UPDATE ".MAIN_DB_PREFIX."timesheet_week";
		$upd .= " SET total_hours = ".((float) $total).", overtime_hours = ".((float) $overtime);
		$upd .= " WHERE rowid = ".((int) $this->id);

		if ($this->db->query($upd)) {
			$this->total_hours = $total;
			$this->overtime_hours = $overtime;
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Get Monday/Sunday timestamps of ISO week
	 *
	 * @param int $year
	 * @param int $week
	 * @return array{start:int,end:int}
	 */
	public static function getIsoWeekRange($year, $week)
	{
		$dto = new DateTime();
		$dto->setISODate((int) $year, (int) $week);
		$start = $dto->getTimestamp();
		$dto->modify('+6 day');
		$end = $dto->getTimestamp();
		return array('start'=>$start, 'end'=>$end);
	}
}
// --- In class TimesheetWeek ---

/**
 * Approve the timesheet (status = APPROVED), set validator and date_validation
 *
 * @param User $user        Current user (for audit)
 * @param int|null $validatorId If provided, force fk_user_valid to this user
 * @return int              >0 if OK, <0 if KO
 */
public function approve(User $user, $validatorId = null)
{
	$this->db->begin();

	// If a validator is provided (i.e. mass approval by someone else), update it
	if (!empty($validatorId)) {
		$this->fk_user_valid = (int) $validatorId;
	}
	$this->date_validation = dol_now();

	// STATUS_APPROVED shim with STATUS_VALIDATED fallback
	if (defined('self::STATUS_APPROVED')) {
		$this->status = self::STATUS_APPROVED;
	} elseif (defined('self::STATUS_VALIDATED')) {
		$this->status = self::STATUS_VALIDATED;
	} else {
		$this->status = 2; // fallback
	}

	$res = $this->update($user);

	if ($res > 0) {
		$this->db->commit();
		return $res;
	} else {
		$this->db->rollback();
		return -1;
	}
}

/**
 * Refuse the timesheet (status = REFUSED), set validator and date_validation
 *
 * @param User $user        Current user (for audit)
 * @param int|null $validatorId If provided, force fk_user_valid to this user
 * @return int              >0 if OK, <0 if KO
 */
public function refuse(User $user, $validatorId = null)
{
	$this->db->begin();

	if (!empty($validatorId)) {
		$this->fk_user_valid = (int) $validatorId;
	}
	$this->date_validation = dol_now();
	$this->status = defined('self::STATUS_REFUSED') ? self::STATUS_REFUSED : 3;

	$res = $this->update($user);

	if ($res > 0) {
		$this->db->commit();
		return $res;
	} else {
		$this->db->rollback();
		return -1;
	}
}
