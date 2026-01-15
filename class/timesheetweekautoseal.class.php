<?php
/* Copyright (C) 2026		Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/cron/class/cronjob.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');

/**
 * Cron helper used to automatically seal approved timesheets.
 * FR: Assistant CRON pour sceller automatiquement les feuilles approuvées.
 */
class TimesheetweekAutoSeal extends CommonObject
{
	public $db;
	public $error;
	public $errors = array();
	public $output;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Run cron job to automatically seal approved timesheets.
	 * FR: Lance la tâche CRON pour sceller automatiquement les feuilles approuvées.
	 *
	 * @param DoliDB|null $dbInstance Optional database handler override
	 * @return int                   <0 if KO, >=0 if OK (number of sealed sheets)
	 */
	public function run($dbInstance = null)
	{
		global $db, $conf, $langs;

		if ($dbInstance instanceof DoliDB) {
			$this->db = $dbInstance;
		} elseif (!empty($db) && $db instanceof DoliDB) {
			$this->db = $db;
		}

		if (empty($this->db)) {
			$this->error = $langs->trans('ErrorNoDatabase');
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return 0;
		}

		$langs->loadLangs(array('timesheetweek@timesheetweek'));

		// EN: Load auto-seal configuration values from module settings.
		// FR: Charge les valeurs de configuration du scellement automatique.
		$enabled = getDolGlobalInt('TIMESHEETWEEK_AUTOSEAL_ENABLE', 0, $conf->entity);
		$delayDays = getDolGlobalInt('TIMESHEETWEEK_AUTOSEAL_DELAY_DAYS', 7, $conf->entity);
		$userId = getDolGlobalInt('TIMESHEETWEEK_AUTOSEAL_USERID', 0, $conf->entity);

		if (empty($enabled)) {
			$this->output = $langs->trans('TimesheetWeekAutoSealDisabled');
			dol_syslog($this->output, LOG_INFO);
			return 0;
		}

		if ($delayDays <= 0 || $userId <= 0) {
			$this->output = $langs->trans('TimesheetWeekAutoSealMissingConfig');
			dol_syslog($this->output, LOG_WARNING);
			return 0;
		}

		// EN: Fetch the configured user to attribute the seal operation.
		// FR: Récupère l'utilisateur configuré pour attribuer le scellement.
		$userAuto = new User($this->db);
		$userFetch = $userAuto->fetch((int) $userId);
		if ($userFetch <= 0) {
			$this->error = $langs->trans('TimesheetWeekAutoSealUserMissing', (int) $userId);
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return -1;
		}

		// EN: Compute the approval threshold date to select eligible sheets.
		// FR: Calcule la date seuil d'approbation pour sélectionner les feuilles éligibles.
		$threshold = dol_now() - ((int) $delayDays * 86400);
		$timesheet = new TimesheetWeek($this->db);

		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$timesheet->table_element;
		$sql .= ' WHERE status='.(int) TimesheetWeek::STATUS_APPROVED;
		$sql .= " AND datev IS NOT NULL";
		$sql .= " AND datev <= '".$this->db->idate($threshold)."'";
		$sql .= ' AND entity IN ('.getEntity('timesheetweek').')';

		// EN: Retrieve approved sheets that are old enough and not yet sealed.
		// FR: Récupère les feuilles approuvées assez anciennes et non encore scellées.
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->output = $this->error;
			dol_syslog($this->error, LOG_ERR);
			return -1;
		}

		$sealedCount = 0;
		$skippedCount = 0;
		$errorCount = 0;

		while ($obj = $this->db->fetch_object($resql)) {
			$timesheetLine = new TimesheetWeek($this->db);
			$fetchResult = $timesheetLine->fetch((int) $obj->rowid);
			if ($fetchResult <= 0) {
				$skippedCount++;
				continue;
			}

			$resultSeal = $timesheetLine->seal($userAuto, 'auto');
			if ($resultSeal > 0) {
				$sealedCount++;
			} else {
				$errorCount++;
				if (!empty($timesheetLine->error)) {
					$this->errors[] = $timesheetLine->error;
				}
			}
		}

		$this->output = $langs->trans('TimesheetWeekAutoSealSummary', $sealedCount, $skippedCount, $errorCount);
		if ($errorCount > 0) {
			dol_syslog($this->output, LOG_WARNING);
		}

		return 0;
	}
}
