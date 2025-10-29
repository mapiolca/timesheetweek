<?php
/* Copyright (C) 2025	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <https://www.gnu.org/licenses/>.
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

	// EN: Entity identifier bound to the line.
	// FR: Identifiant d'entité lié à la ligne.
	public $entity;
	public $fk_timesheet_week;
	public $fk_task;
	public $day_date;
	public $hours;
	// EN: Stores the selected daily rate value linked to forfait-jour entries.
	// FR: Stocke la valeur de forfait-jour sélectionnée pour les saisies concernées.
	public $daily_rate;
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
		$sql = "SELECT rowid, entity, fk_timesheet_week, fk_task, day_date, hours, daily_rate, zone, meal";
		$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE rowid=".(int) $id;
		// EN: Respect module entity permissions during line fetch.
		// FR: Respecte les permissions d'entité du module lors du chargement de la ligne.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";

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
		$this->entity = (int) $obj->entity;
		$this->fk_timesheet_week = (int) $obj->fk_timesheet_week;
		$this->fk_task = (int) $obj->fk_task;
		$this->day_date = $obj->day_date;
		$this->hours = (float) $obj->hours;
		$this->daily_rate = (int) $obj->daily_rate;
		$this->zone = (int) $obj->zone;
		$this->meal = (int) $obj->meal;

		return 1;
	}

/**
* Saves the line or updates it when already stored.
* Crée ou met à jour la ligne si elle existe déjà.
*/
	public function save($user)
	{
		// EN: Resolve the entity from the parent sheet when not provided.
		// FR: Récupère l'entité depuis la feuille parente lorsqu'elle n'est pas fournie.
		if (empty($this->entity) && !empty($this->fk_timesheet_week)) {
			$sqlEntity = "SELECT entity FROM ".MAIN_DB_PREFIX."timesheet_week WHERE rowid=".(int) $this->fk_timesheet_week;
			$sqlEntity .= " AND entity IN (".getEntity('timesheetweek').")";
			$resEntity = $this->db->query($sqlEntity);
			if ($resEntity) {
				$objEntity = $this->db->fetch_object($resEntity);
				if ($objEntity) {
					$this->entity = (int) $objEntity->entity;
				}
			}
		}
		if (empty($this->entity)) {
			global $conf;
			// EN: Fall back to the current context entity as a last resort.
			// FR: Revient en dernier recours à l'entité du contexte courant.
			$this->entity = isset($conf->entity) ? (int) $conf->entity : 1;
		}

		// EN: Normalize the stored daily rate to avoid null values reaching SQL.
		// FR: Normalise la valeur de forfait-jour pour éviter d'envoyer des NULL en SQL.
		if ($this->daily_rate === null) {
			$this->daily_rate = 0;
		}

// EN: Check whether a line already exists for this task and day.
// FR: Vérifie si une ligne existe déjà pour cette tâche et ce jour.
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE fk_timesheet_week = ".((int)$this->fk_timesheet_week);
		$sql .= " AND fk_task = ".((int)$this->fk_task);
		$sql .= " AND day_date = '".$this->db->escape($this->day_date)."'";
		// EN: Keep lookups limited to lines inside allowed entities.
		// FR: Limite les recherches aux lignes situées dans les entités autorisées.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			// ---- UPDATE ----
			$obj = $this->db->fetch_object($resql);
			$sqlu = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET ";
			$sqlu .= " hours = ".((float)$this->hours).",";
			$sqlu .= " daily_rate = ".((int)$this->daily_rate).",";
			$sqlu .= " zone = ".((int)$this->zone).",";
			$sqlu .= " meal = ".((int)$this->meal);
			$sqlu .= " WHERE rowid = ".((int)$obj->rowid);
			// EN: Ensure updates stay within the permitted entity scope.
			// FR: Assure que les mises à jour restent dans le périmètre d'entité autorisé.
			$sqlu .= " AND entity IN (".getEntity('timesheetweek').")";
			return $this->db->query($sqlu) ? 1 : -1;
		}
		else {
			// ---- INSERT ----
			// EN: Persist the entity alongside the usual line fields for consistency.
			// FR: Enregistre l'entité avec les champs habituels de la ligne pour rester cohérent.
			$sqli = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(";
			$sqli .= " entity, fk_timesheet_week, fk_task, day_date, hours, daily_rate, zone, meal)";
			$sqli .= " VALUES(";
			$sqli .= (int)$this->entity.",";
			$sqli .= (int)$this->fk_timesheet_week.",";
			$sqli .= (int)$this->fk_task.",";
			$sqli .= "'".$this->db->escape($this->day_date)."',";
			$sqli .= (float)$this->hours.",";
			$sqli .= (int)$this->daily_rate.",";
			$sqli .= (int)$this->zone.",";
			$sqli .= (int)$this->meal.")";
			return $this->db->query($sqli) ? 1 : -1;
		}
	}
}
