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
 * \brief       CRUD class for TimesheetWeek header (weekly timesheet)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class TimesheetWeek extends CommonObject
{
	/** @var string */
	public $module = 'timesheetweek';
	/** @var string */
	public $element = 'timesheetweek';
	/** @var string */
	public $table_element = 'timesheet_week'; // << llx_timesheet_week
	/** @var string */
	public $picto = 'time';

	/** Extrafields support */
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0; // no entity field in table by default

	// ----- Custom statuses for this module -----
	const STATUS_DRAFT      = 0; // Brouillon
	const STATUS_INPROGRESS = 1; // En cours
	const STATUS_SUBMITTED  = 2; // Soumise
	const STATUS_APPROVED   = 3; // Approuvée
	const STATUS_REFUSED    = 4; // Refusée

	public static $status_labels = array(
		self::STATUS_DRAFT      => 'Draft',
		self::STATUS_INPROGRESS => 'InProgress',
		self::STATUS_SUBMITTED  => 'Submitted',
		self::STATUS_APPROVED   => 'Approved',
		self::STATUS_REFUSED    => 'Refused'
	);

	// ----- DB mapped fields -----
	public $rowid;
	public $ref;
	public $fk_user;
	public $year;
	public $week;
	public $status;
	public $note;
	public $date_creation;
	public $date_validation;
	public $fk_user_valid;
	public $tms;

	/**
	 * Fields definition for CommonObject::fetchCommon / updateCommon
	 * Visible/listing/misc kept minimal; adapt if you need list screens to show columns.
	 */
	public $fields = array(
		'rowid'          => array('type'=>'int',        'label'=>'TechnicalID', 'visible'=>0, 'notnull'=>1, 'position'=>10),
		'ref'            => array('type'=>'varchar(50)','label'=>'Ref',         'visible'=>1, 'notnull'=>1, 'position'=>20, 'index'=>1, 'showoncombobox'=>1),
		'fk_user'        => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'Employee', 'visible'=>-1, 'notnull'=>1, 'position'=>30, 'picto'=>'user'),
		'year'           => array('type'=>'smallint',   'label'=>'Year',        'visible'=>-1, 'notnull'=>1, 'position'=>40),
		'week'           => array('type'=>'smallint',   'label'=>'Week',        'visible'=>-1, 'notnull'=>1, 'position'=>50),
		'status'         => array('type'=>'smallint',   'label'=>'Status',      'visible'=>-1, 'notnull'=>1, 'position'=>60, 'default'=>'0'),
		'note'           => array('type'=>'text',       'label'=>'Note',        'visible'=>-1, 'notnull'=>0, 'position'=>70),
		'date_creation'  => array('type'=>'datetime',   'label'=>'DateCreation','visible'=>-1, 'notnull'=>0, 'position'=>80),
		'date_validation'=> array('type'=>'datetime',   'label'=>'DateValidation','visible'=>-1, 'notnull'=>0,'position'=>90),
		'fk_user_valid'  => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserValidation','visible'=>-1,'notnull'=>0,'position'=>100, 'picto'=>'user'),
		'tms'            => array('type'=>'timestamp',  'label'=>'DateModification','visible'=>-1, 'notnull'=>0, 'position'=>110),
	);

	/**
	 * Constructor
	 * @param DoliDB $db
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;
		$this->db = $db;

		// Clean disabled fields dynamically if any (template pattern)
		foreach ($this->fields as $k => $def) {
			if (isset($def['enabled']) && empty($def['enabled'])) unset($this->fields[$k]);
		}

		// Translate arrayofkeyval if any
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $k => $v) {
						$this->fields[$key]['arrayofkeyval'][$k] = $langs->trans($v);
					}
				}
			}
		}
	}

	/**
	 * Create object into database (explicit INSERT)
	 *
	 * @param	User	$user
	 * @param	int		$notrigger
	 * @return	int		<0 if KO, id if OK
	 */
	public function create($user, $notrigger = 0)
	{
		$error = 0;
		$now = dol_now();

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week(";
		$sql .= "ref, fk_user, year, week, status, note, date_creation, fk_user_valid";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."',";
		$sql .= ((int) $this->fk_user).",";
		$sql .= ((int) $this->year).",";
		$sql .= ((int) $this->week).",";
		$sql .= ((int) $this->status).",";
		$sql .= "'".$this->db->escape((string) $this->note)."',";
		$sql .= "'".$this->db->idate($now)."',";
		$sql .= (!empty($this->fk_user_valid) ? (int) $this->fk_user_valid : "NULL");
		$sql .= ")";

		dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."timesheet_week");
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
		return $this->fetchCommon($id, $ref, '', $noextrafields);
	}

	/**
	 * Update object
	 * @param User $user
	 * @param int $notrigger
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object
	 * @param User $user
	 * @param int $notrigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Infos (create/modify/validate users/dates)
	 * @param int $id
	 * @return void
	 */
	public function info($id)
	{
		$sql = "SELECT rowid, date_creation as datec, tms as datem, date_validation as datev";
		$sql .= ", fk_user_valid";
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.rowid = ".((int) $id);

		$res = $this->db->query($sql);
		if ($res) {
			if ($this->db->num_rows($res)) {
				$obj = $this->db->fetch_object($res);

				$this->id = $obj->rowid;
				$this->user_validation_id = $obj->fk_user_valid;
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
				$this->date_validation = $this->db->jdate($obj->datev);
			}
			$this->db->free($res);
		}
	}

	/**
	 * Label of status
	 * @param int $mode 0=long, 1=short, 2=picto+short, 3=picto, 4=picto+long, 5=short+picto, 6=long+picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Translate a given status to label
	 * @param int $status
	 * @param int $mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(
			self::STATUS_DRAFT      => $langs->transnoentitiesnoconv('Draft'),
			self::STATUS_INPROGRESS => $langs->transnoentitiesnoconv('InProgress'),
			self::STATUS_SUBMITTED  => $langs->transnoentitiesnoconv('Submitted'),
			self::STATUS_APPROVED   => $langs->transnoentitiesnoconv('Approved'),
			self::STATUS_REFUSED    => $langs->transnoentitiesnoconv('Refused')
		);

		$short = $labels;
		$statustype = 'status'.$status; // map to dolGetStatus
		return dolGetStatus($labels[$status], $short[$status], '', $statustype, $mode);
	}

	/**
	 * Return lines (hours, zone, meal) for this week.
	 * Structure:
	 *   $lines[task_id][YYYY-mm-dd] = TimesheetWeekLine|stdClass
	 * Note: daily zone/meal lines are stored with fk_task = 0
	 *
	 * @return array
	 */
	public function getLines()
	{
		$lines = array();

		$sql = "SELECT rowid, fk_task, day_date, hours, zone, meal";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE fk_timesheet_week = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$useClass = class_exists('TimesheetWeekLine');
			while ($obj = $this->db->fetch_object($resql)) {
				if ($useClass) {
					$line = new TimesheetWeekLine($this->db);
					$line->id       = (int) $obj->rowid;
					$line->fk_task  = (int) $obj->fk_task;
					$line->day_date = $obj->day_date;
					$line->hours    = (float) $obj->hours;
					$line->zone     = isset($obj->zone) ? (int) $obj->zone : 0;
					$line->meal     = isset($obj->meal) ? (int) $obj->meal : 0;
				} else {
					$line = new stdClass();
					$line->id       = (int) $obj->rowid;
					$line->fk_task  = (int) $obj->fk_task;
					$line->day_date = $obj->day_date;
					$line->hours    = (float) $obj->hours;
					$line->zone     = isset($obj->zone) ? (int) $obj->zone : 0;
					$line->meal     = isset($obj->meal) ? (int) $obj->meal : 0;
				}

				if (!isset($lines[(int) $obj->fk_task])) $lines[(int) $obj->fk_task] = array();
				$lines[(int) $obj->fk_task][$obj->day_date] = $line;
			}
			$this->db->free($resql);
		}

		return $lines;
	}

	/**
	 * Return assigned tasks to a user (via element_contact on project_task)
	 * Compatible with ec.fk_element OR ec.element_id schema.
	 *
	 * Return:
	 * [
	 *   ['project_id'=>..,'project_ref'=>..,'project_title'=>..,'task_id'=>..,'task_label'=>..],
	 *   ...
	 * ]
	 *
	 * @param int $userid
	 * @return array
	 */
	public function getAssignedTasks($userid)
	{
		$userid = (int) $userid;
		$tasks = array();
		if ($userid <= 0) return $tasks;

		// Try with fk_element
		$sql = "SELECT t.rowid as task_id, t.label as task_label,";
		$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_contact ec ON ec.fk_element = t.rowid";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact";
		$sql .= " WHERE ctc.element = 'project_task'";
		$sql .= " AND ec.fk_socpeople = ".$userid; // In your setup, fk_socpeople points to llx_user.rowid
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= " GROUP BY t.rowid, p.rowid, p.ref, p.title";
		$sql .= " ORDER BY p.ref, t.label";

		dol_syslog(__METHOD__." try fk_element", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$err = $this->db->lasterror();
			// Fallback if fk_element unknown: use element_id
			if (strpos($err, 'fk_element') !== false) {
				$sql = "SELECT t.rowid as task_id, t.label as task_label,";
				$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title";
				$sql .= " FROM ".MAIN_DB_PREFIX."projet_task t";
				$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
				$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_contact ec ON ec.element_id = t.rowid";
				$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact";
				$sql .= " WHERE ctc.element = 'project_task'";
				$sql .= " AND ec.fk_socpeople = ".$userid;
				$sql .= " AND p.entity IN (".getEntity('project').")";
				$sql .= " GROUP BY t.rowid, p.rowid, p.ref, p.title";
				$sql .= " ORDER BY p.ref, t.label";

				dol_syslog(__METHOD__." fallback element_id", LOG_DEBUG);
				$resql = $this->db->query($sql);
			}
		}

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$tasks[] = array(
					'project_id'    => (int) $obj->project_id,
					'project_ref'   => (string) $obj->project_ref,
					'project_title' => (string) $obj->project_title,
					'task_id'       => (int) $obj->task_id,
					'task_label'    => (string) $obj->task_label
				);
			}
			$this->db->free($resql);
		} else {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_WARNING);
		}

		return $tasks;
	}

	/**
	 * Group tasks array by project
	 *
	 * @param array $tasks
	 * @return array project_id => ['ref'=>.., 'title'=>.., 'tasks'=>[]]
	 */
	public function groupTasksByProject($tasks)
	{
		$byproject = array();
		if (empty($tasks)) return $byproject;

		foreach ($tasks as $t) {
			$pid = (int) $t['project_id'];
			if (empty($byproject[$pid])) {
				$byproject[$pid] = array(
					'ref'   => $t['project_ref'],
					'title' => $t['project_title'],
					'tasks' => array()
				);
			}
			$byproject[$pid]['tasks'][] = array(
				'project_id'    => (int) $t['project_id'],
				'project_ref'   => (string) $t['project_ref'],
				'project_title' => (string) $t['project_title'],
				'task_id'       => (int) $t['task_id'],
				'task_label'    => (string) $t['task_label'],
			);
		}

		return $byproject;
	}

	/**
	 * getTooltipContentArray (for hover)
	 * @param array $params
	 * @return array
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;
		$datas = array();

		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("TimesheetWeek").'</u>';
		if (isset($this->status)) $datas['picto'] .= ' '.$this->getLibStatut(5);
		if (!empty($this->ref)) $datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;

		return $datas;
	}

	/**
	 * getNomUrl
	 * @param int $withpicto
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

		$label = implode($this->getTooltipContentArray(array()));
		$linkstart = '<a href="'.$url.'"'.($notooltip ? '' : ' title="'.dol_escape_htmltag($label).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"').'>';
		$linkend = '</a>';

		$picto = ($withpicto ? img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), 'class="paddingright"') : '');
		$out = $linkstart.$picto.($withpicto != 2 ? dol_escape_htmltag($this->ref) : '').$linkend;

		$hookmanager->initHooks(array($this->element.'dao'));
		$parameters = array('id'=>$this->id, 'getnomurl'=>&$out);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this);
		if ($reshook > 0) $out = $hookmanager->resPrint; else $out .= $hookmanager->resPrint;

		return $out;
	}
}
