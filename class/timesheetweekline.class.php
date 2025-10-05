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

class TimesheetWeekLine extends CommonObject
{
    public $element = 'timesheetweekline';
    public $table_element = 'timesheet_week_line';

    public $fk_timesheet_week;
    public $fk_task;
    public $day_date;
    public $hours;
    public $zone;
    public $meal;

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