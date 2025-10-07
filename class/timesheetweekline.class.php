<?php
/* Copyright (C) 2025 Pierre ARDOIN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License.
 */

/**
 * \file    timesheetweek/class/timesheetweekline.class.php
 * \ingroup timesheetweek
 * \brief   TimesheetWeekLine class file
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

class TimesheetWeekLine extends CommonObjectLine
{
    public $element = 'timesheetweekline';
    public $table_element = 'timesheet_week_line';
    public $fk_element = 'fk_timesheet_week';
    public $parent_element = 'timesheetweek';

    public $fk_timesheet_week;
    public $fk_task;
    public $day_date;
    public $hours;
    public $zone;
    public $meal;

    /**
     * Fetches a timesheet line by its rowid.
     * Récupère une ligne de feuille de temps via son rowid.
     *
     * @param int $id Row identifier / Identifiant de ligne
     * @return int                             >0 success, <=0 error / >0 succès, <=0 erreur
     */
    public function fetch($id)
    {
        if (empty($id)) {
            return 0;
        }

        // Build the query for the line / Construit la requête pour la ligne
        $sql = "SELECT rowid, fk_timesheet_week, fk_task, day_date, hours, zone, meal";
        $sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line";
        $sql .= " WHERE rowid=".(int) $id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);
        if (!$obj) {
            return 0;
        }

        // Map database values to object / Mappe les valeurs de la base vers l'objet
        $this->id = (int) $obj->rowid;
        $this->rowid = (int) $obj->rowid;
        $this->fk_timesheet_week = (int) $obj->fk_timesheet_week;
        $this->fk_task = (int) $obj->fk_task;
        $this->day_date = $obj->day_date;
        $this->hours = (float) $obj->hours;
        $this->zone = (int) $obj->zone;
        $this->meal = (int) $obj->meal;

        return 1;
    }

    /**
     * Crée ou met à jour la ligne si elle existe déjà
     */
    public function save($user)
    {
        // Vérifie si une ligne existe déjà pour cette tâche et ce jour
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line";
        $sql .= " WHERE fk_timesheet_week = ".((int)$this->fk_timesheet_week);
        $sql .= " AND fk_task = ".((int)$this->fk_task);
        $sql .= " AND day_date = '".$this->db->escape($this->day_date)."'";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            // ---- UPDATE ----
            $obj = $this->db->fetch_object($resql);
            $sqlu = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET ";
            $sqlu .= " hours = ".((float)$this->hours).",";
            $sqlu .= " zone = ".((int)$this->zone).",";
            $sqlu .= " meal = ".((int)$this->meal);
            $sqlu .= " WHERE rowid = ".((int)$obj->rowid);
            return $this->db->query($sqlu) ? 1 : -1;
        }
        else {
            // ---- INSERT ----
            $sqli = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(";
            $sqli .= " fk_timesheet_week, fk_task, day_date, hours, zone, meal)";
            $sqli .= " VALUES(";
            $sqli .= (int)$this->fk_timesheet_week.",";
            $sqli .= (int)$this->fk_task.",";
            $sqli .= "'".$this->db->escape($this->day_date)."',";
            $sqli .= (float)$this->hours.",";
            $sqli .= (int)$this->zone.",";
            $sqli .= (int)$this->meal.")";
            return $this->db->query($sqli) ? 1 : -1;
        }
    }
}