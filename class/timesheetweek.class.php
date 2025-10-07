<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License...
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
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
        public $zone1_count = 0;         // zone 1 days count / nombre de jours en zone 1
        public $zone2_count = 0;         // zone 2 days count / nombre de jours en zone 2
        public $zone3_count = 0;         // zone 3 days count / nombre de jours en zone 3
        public $zone4_count = 0;         // zone 4 days count / nombre de jours en zone 4
        public $zone5_count = 0;         // zone 5 days count / nombre de jours en zone 5
        public $meal_count = 0;          // meal days count / nombre de jours avec panier

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
                $sql .= "ref, entity, fk_user, year, week, status, note, date_creation, fk_user_valid, total_hours, overtime_hours, zone1_count, zone2_count, zone3_count, zone4_count, zone5_count, meal_count";
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
                $sql .= " ".((float) ($this->overtime_hours ?: 0)).",";
                $sql .= " ".((int) ($this->zone1_count ?: 0)).",";
                $sql .= " ".((int) ($this->zone2_count ?: 0)).",";
                $sql .= " ".((int) ($this->zone3_count ?: 0)).",";
                $sql .= " ".((int) ($this->zone4_count ?: 0)).",";
                $sql .= " ".((int) ($this->zone5_count ?: 0)).",";
                $sql .= " ".((int) ($this->meal_count ?: 0));
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
                $sql .= " t.total_hours, t.overtime_hours, t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count";
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
                $this->zone1_count = (int) $obj->zone1_count;
                $this->zone2_count = (int) $obj->zone2_count;
                $this->zone3_count = (int) $obj->zone3_count;
                $this->zone4_count = (int) $obj->zone4_count;
                $this->zone5_count = (int) $obj->zone5_count;
                $this->meal_count = (int) $obj->meal_count;

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
                $sets[] = "zone1_count=".(int) ($this->zone1_count ?: 0);
                $sets[] = "zone2_count=".(int) ($this->zone2_count ?: 0);
                $sets[] = "zone3_count=".(int) ($this->zone3_count ?: 0);
                $sets[] = "zone4_count=".(int) ($this->zone4_count ?: 0);
                $sets[] = "zone5_count=".(int) ($this->zone5_count ?: 0);
                $sets[] = "meal_count=".(int) ($this->meal_count ?: 0);
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
                $zoneBuckets = array(1=>0, 2=>0, 3=>0, 4=>0, 5=>0);
                $mealDays = 0;
                $dayAggregates = array();
                if (!is_array($this->lines) || !count($this->lines)) {
                        $this->fetchLines();
                }
                foreach ($this->lines as $l) {
                        $total += (float) $l->hours;
                        // EN: Aggregate data per day to prevent counting duplicated task entries.
                        // FR: Agrège les données par jour pour éviter de compter plusieurs fois les mêmes tâches.
                        $dayKey = !empty($l->day_date) ? $l->day_date : null;
                        if ($dayKey === null) {
                                continue;
                        }
                        if (!isset($dayAggregates[$dayKey])) {
                                $dayAggregates[$dayKey] = array(
                                        'zone' => (int) $l->zone,
                                        'meal' => ((int) $l->meal > 0 ? 1 : 0)
                                );
                        } else {
                                if ((int) $l->zone > 0) {
                                        $dayAggregates[$dayKey]['zone'] = (int) $l->zone;
                                }
                                if ((int) $l->meal > 0) {
                                        $dayAggregates[$dayKey]['meal'] = 1;
                                }
                        }
                }
                foreach ($dayAggregates as $info) {
                        $zoneVal = (int) ($info['zone'] ?? 0);
                        if ($zoneVal >= 1 && $zoneVal <= 5) {
                                $zoneBuckets[$zoneVal]++;
                        }
                        if (!empty($info['meal'])) {
                                $mealDays++;
                        }
                }
                $this->total_hours = $total;
                // EN: Persist the aggregated metrics onto the main object for later storage.
                // FR: Enregistre les indicateurs agrégés sur l'objet principal pour une sauvegarde ultérieure.
                $this->zone1_count = $zoneBuckets[1];
                $this->zone2_count = $zoneBuckets[2];
                $this->zone3_count = $zoneBuckets[3];
                $this->zone4_count = $zoneBuckets[4];
                $this->zone5_count = $zoneBuckets[5];
                $this->meal_count = $mealDays;

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
                $sql .= ", zone1_count=".(int) $this->zone1_count.", zone2_count=".(int) $this->zone2_count;
                $sql .= ", zone3_count=".(int) $this->zone3_count.", zone4_count=".(int) $this->zone4_count;
                $sql .= ", zone5_count=".(int) $this->zone5_count.", meal_count=".(int) $this->meal_count;
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

                // Remove synced time entries to keep the ERP aligned (EN)
                // Supprime les temps synchronisés pour garder l'ERP aligné (FR)
                if ($this->deleteElementTimeRecords() < 0) {
                        $this->db->rollback();
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

                // Synchronize time consumption with Dolibarr core table (EN)
                // Synchronise la consommation de temps avec la table cœur de Dolibarr (FR)
                if ($this->syncElementTimeRecords() < 0) {
                        $this->db->rollback();
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
                return 1;
        }

        /**
         * Synchronize element_time rows with the validated timesheet lines.
         * EN: Inserts dedicated rows into llx_element_time when approving a sheet, removing stale ones first.
         * FR: Insère les lignes dans llx_element_time lors de l'approbation, après avoir supprimé les enregistrements obsolètes.
         * @return int
         */
        protected function syncElementTimeRecords()
        {
                if (empty($this->id)) {
                        return 0;
                }

                // Clean previous entries tied to this sheet to avoid duplicates (EN)
                // Nettoie les entrées existantes liées à cette fiche pour éviter les doublons (FR)
                if ($this->deleteElementTimeRecords() < 0) {
                        return -1;
                }

                $lines = $this->getLines();
                if (empty($lines)) {
                        return 1;
                }

                // Determine employee hourly rate for THM column (EN)
                // Détermine le taux horaire salarié pour la colonne THM (FR)
                $employeeThm = $this->resolveEmployeeThm();

                // Prepare multilingual helper for note composition (EN)
                // Prépare l'assistant multilingue pour composer la note (FR)
                global $langs;
                if (is_object($langs)) {
                        $langs->load('timesheetweek@timesheetweek');
                }

                foreach ($lines as $line) {
                        $durationSeconds = (int) round(((float) $line->hours) * 3600);
                        if ($durationSeconds <= 0) {
                                continue;
                        }

                        $taskTimestamp = $this->normalizeLineDate($line->day_date);
                        $noteDateLabel = $taskTimestamp ? dol_print_date($taskTimestamp, '%d/%m/%Y') : dol_print_date(dol_now(), '%d/%m/%Y');
                        // Try Dolibarr helper then fallback to module formatter (EN)
                        // Tente l'assistant Dolibarr puis bascule sur le formateur du module (FR)
                        if (function_exists('dol_print_duration')) {
                                $noteDurationLabel = dol_print_duration($durationSeconds, 'allhourmin');
                        } else {
                                $noteDurationLabel = $this->formatDurationLabel($durationSeconds);
                        }
                        $noteDurationLabel = trim(str_replace('&nbsp;', ' ', $noteDurationLabel));
                        if ($noteDurationLabel === '') {
                                $noteDurationValue = price2num($line->hours, 'MT');
                                $noteDurationLabel = ($noteDurationValue !== '' ? number_format((float) $noteDurationValue, 2, '.', ' ') : '0.00').' h';
                        }

                        // Build the readable note with translations and fallback (EN)
                        // Construit la note lisible avec traductions et repli (FR)
                        if (is_object($langs)) {
                                $noteMessage = $langs->trans('TimesheetWeekElementTimeNote', (string) $this->ref, $noteDateLabel, $noteDurationLabel);
                        } else {
                                $noteMessage = 'Feuille de temps '.(string) $this->ref.' - '.$noteDateLabel.' - '.$noteDurationLabel;
                        }

                        // Store a readable note for auditors and managers (EN)
                        // Enregistre une note lisible pour les contrôleurs et managers (FR)
                        $sql = "INSERT INTO ".MAIN_DB_PREFIX."element_time(";
                        $sql .= " fk_user, fk_element, elementtype, element_duration, element_date, thm, note, import_key";
                        $sql .= ") VALUES (";
                        $sql .= " ".(!empty($this->fk_user) ? (int) $this->fk_user : "NULL").",";
                        $sql .= " ".(!empty($line->fk_task) ? (int) $line->fk_task : "NULL").",";
                        $sql .= " 'task',";
                        $sql .= " ".$durationSeconds.",";
                        $sql .= $taskTimestamp ? " '".$this->db->idate($taskTimestamp)."'," : " NULL,";
                        $sql .= ($employeeThm !== null ? " ".$employeeThm : " NULL").",";
                        $sql .= " '".$this->db->escape($noteMessage)."',";
                        $sql .= " '".$this->db->escape($this->buildElementTimeImportKey($line))."'";
                        $sql .= ")";

                        if (!$this->db->query($sql)) {
                                $this->error = $this->db->lasterror();
                                return -1;
                        }
                }

                return 1;
        }

        /**
         * Delete synced rows from llx_element_time for this sheet.
         * EN: Uses the custom import_key prefix to target only our module entries.
         * FR: Utilise le préfixe import_key personnalisé pour ne viser que les entrées du module.
         * @return int
         */
        protected function deleteElementTimeRecords()
        {
                if (empty($this->id)) {
                        return 0;
                }

                $prefix = $this->getElementTimeImportKeyPrefix();
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."element_time";
                $sql .= " WHERE elementtype='task'";
                if ($prefix !== '') {
                        $sql .= " AND import_key LIKE '".$this->db->escape($prefix)."%'";
                }

                $resql = $this->db->query($sql);
                if (!$resql) {
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                return $this->db->affected_rows($resql);
        }

        /**
         * Normalize date value coming from a line.
         * EN: Returns a unix timestamp or 0 if the date is missing.
         * FR: Retourne un timestamp unix ou 0 si la date est absente.
         * @param mixed $value
         * @return int
         */
        protected function normalizeLineDate($value)
        {
                if (empty($value)) {
                        return 0;
                }

                if (is_numeric($value)) {
                        return (int) $value;
                }

                $timestamp = dol_stringtotime($value, 0, 1);
                if ($timestamp > 0) {
                        return $timestamp;
                }

                $timestamp = strtotime($value);
                return $timestamp ? (int) $timestamp : 0;
        }

        /**
         * Build a deterministic import_key for a line.
         * EN: Generates a short hash starting with a module prefix for deletion filtering.
         * FR: Génère un hash court préfixé pour faciliter le filtrage à la suppression.
         * @param TimesheetWeekLine $line
         * @return string
         */
        protected function buildElementTimeImportKey($line)
        {
                $lineId = !empty($line->id) ? (int) $line->id : (!empty($line->rowid) ? (int) $line->rowid : 0);
                $base = (string) $this->id.'-'.$lineId;
                return $this->getElementTimeImportKeyPrefix().substr(md5($base), 0, 8);
        }

        /**
         * Provide the common prefix used for import_key values.
         * EN: Ensures every record for this sheet starts with a predictable marker.
         * FR: Garantit que chaque enregistrement de la fiche débute par un marqueur prévisible.
         * @return string
         */
        protected function getElementTimeImportKeyPrefix()
        {
                if (empty($this->id)) {
                        return '';
                }

                return 'TW'.substr(md5((string) $this->id), 0, 4);
        }

        /**
         * Resolve employee THM (average hourly rate) for element_time insert.
         * EN: Tries the standard user fields to find the hourly cost before inserting into llx_element_time.
         * FR: Explore les champs standards de l'utilisateur pour retrouver le coût horaire avant l'insertion dans llx_element_time.
         * @return string|null
         */
        protected function resolveEmployeeThm()
        {
                $employee = $this->loadUserFromCache($this->fk_user);
                if (!$employee) {
                        return null;
                }

                $candidates = array();
                if (property_exists($employee, 'thm')) {
                        $candidates[] = $employee->thm;
                }
                if (!empty($employee->array_options) && array_key_exists('options_thm', $employee->array_options)) {
                        $candidates[] = $employee->array_options['options_thm'];
                }

                foreach ($candidates as $candidate) {
                        if ($candidate === '' || $candidate === null) {
                                continue;
                        }

                        $value = price2num($candidate, 'MT');
                        if ($value !== '') {
                                return (string) $value;
                        }
                }

                return null;
        }

        /**
         * Format a readable duration when Dolibarr helper is unavailable.
         * EN: Converts a duration in seconds to an "Hh Mmin" label for notes.
         * FR: Convertit une durée en secondes en libellé « Hh Mmin » pour les notes.
         * @param int $seconds
         * @return string
         */
        protected function formatDurationLabel($seconds)
        {
                $seconds = max(0, (int) $seconds);
                $hours = (int) floor($seconds / 3600);
                $minutes = (int) floor(($seconds % 3600) / 60);
                $remainingSeconds = $seconds % 60;

                $parts = array();
                if ($hours > 0) {
                        $parts[] = $hours.'h';
                }
                if ($minutes > 0) {
                        $parts[] = $minutes.'min';
                }
                if ($remainingSeconds > 0 && $hours === 0) {
                        $parts[] = $remainingSeconds.'s';
                }

                if (empty($parts)) {
                        return '0 min';
                }

                return implode(' ', $parts);
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
               // EN: Fetch extra task metadata so the card can filter items (status, progress, dates).
               // FR: Récupère des métadonnées supplémentaires pour que la carte puisse filtrer (statut, avancement, dates).
               $sql = "SELECT t.rowid as task_id, t.label as task_label, t.ref as task_ref,";
               $sql .= " t.progress as task_progress, t.fk_statut as task_status, t.dateo as task_date_start, t.datee as task_date_end,";
                $sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."element_contact ec ON ec.element_id = t.rowid";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ec.fk_socpeople"; // legacy mapping
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND ctc.element = 'project_task'";
		$sql .= " AND (ec.fk_socpeople = ".$userid." OR u.rowid = ".$userid.")";
               // EN: Group by the extra fields to keep SQL strict mode satisfied once new columns are selected.
               // FR: Ajoute les nouveaux champs dans le GROUP BY pour respecter le mode strict SQL après sélection.
               $sql .= " GROUP BY t.rowid, t.label, t.ref, t.progress, t.fk_statut, t.dateo, t.datee, p.rowid, p.ref, p.title";
		$sql .= " ORDER BY p.ref, t.label";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$out = array();
		while ($obj = $this->db->fetch_object($res)) {
                       // EN: Return the enriched task information so the caller can apply advanced filters.
                       // FR: Retourne les informations enrichies de la tâche pour permettre des filtres avancés côté appelant.
                       $out[] = array(
                                'task_id' => (int) $obj->task_id,
                                'task_label' => (string) $obj->task_label,
                                'task_ref' => (string) $obj->task_ref,
                                'task_progress' => ($obj->task_progress !== null ? (float) $obj->task_progress : null),
                                'task_status' => ($obj->task_status !== null ? (int) $obj->task_status : null),
                                'task_date_start' => ($obj->task_date_start !== null ? (string) $obj->task_date_start : null),
                                'task_date_end' => ($obj->task_date_end !== null ? (string) $obj->task_date_end : null),
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
