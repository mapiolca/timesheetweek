<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 * \file      core/modules/timesheetweek/doc/pdf_standard_timesheetweek.modules.php
 * \ingroup   timesheetweek
 * \brief     Standard PDF model for weekly timesheets.
 * EN: Standard PDF model for weekly timesheets.
 * FR: Modèle PDF standard pour les feuilles hebdomadaires.
 */

dol_include_once('/timesheetweek/core/modules/timesheetweek/modules_timesheetweek.php');
dol_include_once('/timesheetweek/lib/timesheetweek_pdf.lib.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
// EN: Load the TimesheetWeek class to reuse status constants and helpers.
// FR: Charge la classe TimesheetWeek pour réutiliser les constantes et helpers de statut.
dol_include_once('/timesheetweek/class/timesheetweek.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

class pdf_standard_timesheetweek extends ModelePDFTimesheetWeek
{
	/**
	 * EN: Database handler reference.
	 * FR: Référence vers le gestionnaire de base de données.
	 *
	 * @var DoliDB
	 */
	public $db;

	/**
	 * EN: Internal model name used by Dolibarr.
	 * FR: Nom interne du modèle utilisé par Dolibarr.
	 *
	 * @var string
	 */
	public $name = 'standard_timesheetweek';

	/**
	 * EN: Localized description displayed in selectors.
	 * FR: Description localisée affichée dans les sélecteurs.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * EN: Document type handled by the generator.
	 * FR: Type de document géré par le générateur.
	 *
	 * @var string
	 */
	public $type = 'pdf';

	/**
	 * EN: Compatibility flag for the Dolibarr version.
	 * FR: Indicateur de compatibilité avec la version de Dolibarr.
	 *
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * EN: Ensure the generated file becomes the main document.
	 * FR: Assure que le fichier généré devient le document principal.
	 *
	 * @var int
	 */
	public $update_main_doc_field = 1;

	/**
	 * EN: Page width in millimeters.
	 * FR: Largeur de page en millimètres.
	 *
	 * @var float
	 */
	public $page_largeur;

	/**
	 * EN: Page height in millimeters.
	 * FR: Hauteur de page en millimètres.
	 *
	 * @var float
	 */
	public $page_hauteur;

	/**
	 * EN: Format array used by TCPDF.
	 * FR: Tableau de format utilisé par TCPDF.
	 *
	 * @var array<int,float>
	 */
	public $format = array();

	/**
	 * EN: Left margin in millimeters.
	 * FR: Marge gauche en millimètres.
	 *
	 * @var int
	 */
	public $marge_gauche;

	/**
	 * EN: Right margin in millimeters.
	 * FR: Marge droite en millimètres.
	 *
	 * @var int
	 */
	public $marge_droite;

	/**
	 * EN: Top margin in millimeters.
	 * FR: Marge haute en millimètres.
	 *
	 * @var int
	 */
	public $marge_haute;

	/**
	 * EN: Bottom margin in millimeters.
	 * FR: Marge basse en millimètres.
	 *
	 * @var int
	 */
	public $marge_basse;

	/**
	 * EN: Corner radius for frames.
	 * FR: Rayon des coins pour les cadres.
	 *
	 * @var int
	 */
	public $corner_radius;

	/**
	 * EN: Issuer company reference.
	 * FR: Référence de la société émettrice.
	 *
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * EN: Constructor.
	 * FR: Constructeur.
	 *
	 * @param DoliDB $db Database handler / Gestionnaire de base de données
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		// EN: Store the database handler for later use.
		// FR: Conserve le gestionnaire de base de données pour les usages ultérieurs.
		$this->db = $db;

		// EN: Load shared translations required by the selector and generator.
		// FR: Charge les traductions partagées nécessaires au sélecteur et au générateur.
		if (method_exists($langs, 'loadLangs')) {
			$langs->loadLangs(array('main', 'companies', 'timesheetweek@timesheetweek'));
		} else {
			$langs->load('main');
			$langs->load('companies');
			$langs->load('timesheetweek@timesheetweek');
		}

		// EN: Identify the template for Dolibarr interfaces and automations.
		// FR: Identifie le modèle pour les interfaces et automatisations Dolibarr.
		$this->description = $langs->trans('PDFStandardTimesheetWeekDescription');

		// EN: Request the default PDF geometry from the Dolibarr helper.
		// FR: Récupère la géométrie PDF par défaut depuis l'assistant Dolibarr.
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);

		// EN: Apply configured margins and frame radius for consistency.
		// FR: Applique les marges configurées et le rayon de cadre pour conserver la cohérence.
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);

		// EN: Keep a reference to the issuer company for header helpers.
		// FR: Conserve une référence vers la société émettrice pour les assistants d'en-tête.
		$this->emetteur = $mysoc;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * EN: Generate the PDF by mirroring the interactive grid displayed on the card page.
	 * FR: Génère le PDF en reproduisant la grille interactive affichée sur la fiche.
	 *
	 * @param TimesheetWeek $object Timesheet source / Feuille de temps source
	 * @param Translate $outputlangs Output language / Gestionnaire de langue de sortie
	 * @param string $srctemplatepath Optional template path / Chemin optionnel du gabarit
	 * @param int $hidedetails Hide details flag / Indicateur de masquage des détails
	 * @param int $hidedesc Hide descriptions flag / Indicateur de masquage des descriptions
	 * @param int $hideref Hide references flag / Indicateur de masquage des références
	 * @return int 1 on success, <=0 otherwise / 1 si succès, <=0 sinon
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $user;
		
		// EN: Fallback to global translations if none were provided.
		// FR: Retombe sur les traductions globales si aucune n'a été fournie.
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		
		// EN: Load module translations to localize messages and filenames.
		// FR: Charge les traductions du module pour localiser messages et noms de fichiers.
		if (method_exists($outputlangs, 'loadLangs')) {
			$outputlangs->loadLangs(array('main', 'companies', 'timesheetweek@timesheetweek'));
		} else {
			$outputlangs->load('main');
			$outputlangs->load('companies');
			$outputlangs->load('timesheetweek@timesheetweek');
		}
		
		$this->error = '';
		$this->errors = array();
		
		// EN: Abort if the source timesheet is not properly initialized.
		// FR: Abandonne si la feuille de temps source n'est pas correctement initialisée.
		if (empty($object) || empty($object->id)) {
			$this->error = $outputlangs->trans('ErrorRecordNotFound');
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}
		
		// EN: Resolve the destination directory while respecting Multicompany rules.
		// FR: Résout le répertoire de destination en respectant les règles Multicompany.
		$entityId = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$baseOutput = '';
		if (!empty($conf->timesheetweek->multidir_output[$entityId] ?? null)) {
			$baseOutput = $conf->timesheetweek->multidir_output[$entityId];
		} elseif (!empty($conf->timesheetweek->dir_output)) {
			$baseOutput = $conf->timesheetweek->dir_output;
		} else {
			$baseOutput = DOL_DATA_ROOT.'/timesheetweek';
		}
		
		// EN: Build the sanitized document directory for the current timesheet.
		// FR: Construit le répertoire de documents assaini pour la feuille courante.
		$cleanRef = dol_sanitizeFileName($object->ref);
		if ($cleanRef === '') {
			$cleanRef = dol_sanitizeFileName('timesheetweek-'.$object->id);
		}
		$relativePath = $object->element.'/'.$cleanRef;
		$targetDir = rtrim($baseOutput, '/').'/'.$relativePath;
		if (dol_mkdir($targetDir) < 0) {
			$this->error = $outputlangs->trans('ErrorCanNotCreateDir', $targetDir);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}
		
		// EN: Collect employee details to reproduce the on-screen grid behaviour.
		// FR: Récupère les informations salarié pour reproduire le comportement de la grille à l'écran.
		$timesheetEmployee = null;
		$isDailyRateEmployee = false;
		$contractedHours = 35.0;
		if (!empty($object->fk_user)) {
			$employee = new User($this->db);
			if ($employee->fetch($object->fk_user) > 0) {
				$employee->fetch_optionals($employee->id, $employee->table_element);
				$timesheetEmployee = $employee;
				$isDailyRateEmployee = !empty($employee->array_options['options_lmdb_daily_rate']);
				if (!empty($employee->weeklyhours)) {
					$contractedHours = (float) $employee->weeklyhours;
				}
			}
		}
		
		// EN: Rebuild the week date map to display the same weekday headers as the HTML card.
		// FR: Reconstruit la carte des dates de la semaine pour afficher les mêmes entêtes que la carte HTML.
		$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		$dayLabelKeys = array(
		'Monday' => 'TimesheetWeekDayMonday',
		'Tuesday' => 'TimesheetWeekDayTuesday',
		'Wednesday' => 'TimesheetWeekDayWednesday',
		'Thursday' => 'TimesheetWeekDayThursday',
		'Friday' => 'TimesheetWeekDayFriday',
		'Saturday' => 'TimesheetWeekDaySaturday',
		'Sunday' => 'TimesheetWeekDaySunday'
		);
		$weekdates = array();
		$weekStartDate = null;
		$weekEndDate = null;
		if (!empty($object->year) && !empty($object->week)) {
			$dto = new DateTime();
			$dto->setISODate((int) $object->year, (int) $object->week);
			foreach ($days as $dayName) {
				$weekdates[$dayName] = $dto->format('Y-m-d');
				$dto->modify('+1 day');
			}
			$weekStartDate = isset($weekdates['Monday']) ? $weekdates['Monday'] : null;
			$weekEndDate = isset($weekdates['Sunday']) ? $weekdates['Sunday'] : null;
		} else {
			foreach ($days as $dayName) {
				$weekdates[$dayName] = null;
			}
		}
		
		// EN: Preload the day-level options to replicate the card layout (zone + meal).
		// FR: Précharge les options quotidiennes pour reproduire la disposition de la carte (zone + repas).
		$dayMeal = array('Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0);
		$dayZone = array('Monday' => null, 'Tuesday' => null, 'Wednesday' => null, 'Thursday' => null, 'Friday' => null, 'Saturday' => null, 'Sunday' => null);
		$hoursBy = array();
		$dailyRateBy = array();
		$taskIdsFromLines = array();
		
		$sqlLines = 'SELECT fk_task, day_date, hours, daily_rate, zone, meal';
		$sqlLines .= ' FROM '.MAIN_DB_PREFIX."timesheet_week_line";
		$sqlLines .= ' WHERE fk_timesheet_week='.(int) $object->id;
		$sqlLines .= ' AND entity IN ('.getEntity('timesheetweek').')';
		$resLines = $this->db->query($sqlLines);
		if ($resLines) {
			while ($lineObj = $this->db->fetch_object($resLines)) {
				$taskId = (int) $lineObj->fk_task;
				$dayDate = (string) $lineObj->day_date;
				$lineHours = (float) $lineObj->hours;
				$lineDailyRate = isset($lineObj->daily_rate) ? (int) $lineObj->daily_rate : 0;
				$lineZone = isset($lineObj->zone) ? (int) $lineObj->zone : null;
				$lineMeal = (int) $lineObj->meal;
				
				if (!isset($hoursBy[$taskId])) {
					$hoursBy[$taskId] = array();
				}
				$hoursBy[$taskId][$dayDate] = $lineHours;
				if (!isset($dailyRateBy[$taskId])) {
					$dailyRateBy[$taskId] = array();
				}
				$dailyRateBy[$taskId][$dayDate] = $lineDailyRate;
				
				$weekdayNumber = (int) date('N', strtotime($dayDate));
				$weekdayName = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
				if (!empty($weekdayName[$weekdayNumber])) {
					$weekdayKey = $weekdayName[$weekdayNumber];
					if ($lineMeal) {
						$dayMeal[$weekdayKey] = 1;
					}
					if ($lineZone !== null) {
						$dayZone[$weekdayKey] = $lineZone;
					}
				}
				
				$taskIdsFromLines[$taskId] = 1;
			}
		}
		
		// EN: Retrieve assigned tasks then merge unassigned ones found in the stored lines.
		// FR: Récupère les tâches assignées puis fusionne celles non assignées présentes dans les lignes stockées.
		$tasks = $object->getAssignedTasks($object->fk_user);
		$tasksById = array();
		if (!empty($tasks)) {
			foreach ($tasks as $taskRow) {
				$tasksById[(int) $taskRow['task_id']] = $taskRow;
			}
		}
		if (!empty($taskIdsFromLines)) {
			$missingTaskIds = array();
			foreach (array_keys($taskIdsFromLines) as $missingId) {
				if (!isset($tasksById[$missingId])) {
					$missingTaskIds[] = (int) $missingId;
				}
			}
			if (!empty($missingTaskIds)) {
				$sqlMissing = 'SELECT t.rowid as task_id, t.label as task_label, t.ref as task_ref, t.progress as task_progress,';
				$sqlMissing .= ' t.fk_statut as task_status, t.dateo as task_date_start, t.datee as task_date_end,';
				$sqlMissing .= ' p.rowid as project_id, p.ref as project_ref, p.title as project_title';
				$sqlMissing .= ' FROM '.MAIN_DB_PREFIX."projet_task t";
				$sqlMissing .= ' INNER JOIN '.MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet";
				$sqlMissing .= ' WHERE t.rowid IN ('.implode(',', array_map('intval', $missingTaskIds)).')';
				$resMissing = $this->db->query($sqlMissing);
				if ($resMissing) {
					while ($missingObj = $this->db->fetch_object($resMissing)) {
						$tasks[] = array(
						'task_id' => (int) $missingObj->task_id,
						'task_label' => $missingObj->task_label,
						'task_ref' => $missingObj->task_ref,
						'task_progress' => ($missingObj->task_progress !== null ? (float) $missingObj->task_progress : null),
						'task_status' => ($missingObj->task_status !== null ? (int) $missingObj->task_status : null),
						'task_date_start' => ($missingObj->task_date_start !== null ? (string) $missingObj->task_date_start : null),
						'task_date_end' => ($missingObj->task_date_end !== null ? (string) $missingObj->task_date_end : null),
						'project_id' => (int) $missingObj->project_id,
						'project_ref' => $missingObj->project_ref,
						'project_title' => $missingObj->project_title
						);
					}
				}
			}
		}
		
		// EN: Filter out closed or out-of-range tasks exactly like the web interface.
		// FR: Exclut les tâches clôturées ou hors plage exactement comme l'interface web.
		$closedStatuses = array();
		if (defined('Task::STATUS_DONE')) {
			$closedStatuses[] = Task::STATUS_DONE;
		}
		if (defined('Task::STATUS_CLOSED')) {
			$closedStatuses[] = Task::STATUS_CLOSED;
		}
		if (defined('Task::STATUS_FINISHED')) {
			$closedStatuses[] = Task::STATUS_FINISHED;
		}
		if (defined('Task::STATUS_CANCELLED')) {
			$closedStatuses[] = Task::STATUS_CANCELLED;
		}
		if (defined('Task::STATUS_CANCELED')) {
			$closedStatuses[] = Task::STATUS_CANCELED;
		}
		$closedStatuses = array_unique(array_map('intval', $closedStatuses));
		
		$weekStartTs = ($weekStartDate ? strtotime($weekStartDate.' 00:00:00') : null);
		$weekEndTs = ($weekEndDate ? strtotime($weekEndDate.' 23:59:59') : null);
		$filteredTasks = array();
		if (!empty($tasks)) {
			foreach ($tasks as $taskRow) {
				$taskId = isset($taskRow['task_id']) ? (int) $taskRow['task_id'] : 0;
				$hasRecordedEffort = false;
				if ($taskId > 0) {
					$hasRecordedEffort = (!empty($hoursBy[$taskId]) || !empty($dailyRateBy[$taskId]));
				}
				if (!$hasRecordedEffort) {
					// EN: Skip tasks without recorded effort to hide empty rows.
					// FR: Ignore les tâches sans temps saisi pour masquer les lignes vides.
					continue;
				}
				$progress = isset($taskRow['task_progress']) ? $taskRow['task_progress'] : null;
				if ($progress !== null && (float) $progress >= 100) {
					continue;
				}
				$statusValue = isset($taskRow['task_status']) ? $taskRow['task_status'] : null;
				if ($statusValue !== null && !empty($closedStatuses) && in_array((int) $statusValue, $closedStatuses, true)) {
					continue;
				}
				$taskStart = isset($taskRow['task_date_start']) ? $taskRow['task_date_start'] : null;
				$taskEnd = isset($taskRow['task_date_end']) ? $taskRow['task_date_end'] : null;
				$taskStartTs = null;
				$taskEndTs = null;
				if (!empty($taskStart)) {
					$taskStartTs = is_numeric($taskStart) ? (int) $taskStart : strtotime($taskStart);
					if ($taskStartTs === false) {
						$taskStartTs = null;
					}
				}
				if (!empty($taskEnd)) {
					$taskEndTs = is_numeric($taskEnd) ? (int) $taskEnd : strtotime($taskEnd);
					if ($taskEndTs === false) {
						$taskEndTs = null;
					}
				}
				if ($weekStartTs !== null && $taskEndTs !== null && $taskEndTs < $weekStartTs) {
					continue;
				}
				if ($weekEndTs !== null && $taskStartTs !== null && $taskStartTs > $weekEndTs) {
					continue;
				}
				$filteredTasks[] = $taskRow;
			}
		}
		$tasks = array_values($filteredTasks);
		
		// EN: Group tasks per project to mimic the nested HTML structure.
		// FR: Regroupe les tâches par projet pour imiter la structure HTML imbriquée.
		$tasksByProject = array();
		foreach ($tasks as $taskRow) {
			$projectId = (int) $taskRow['project_id'];
			if (!isset($tasksByProject[$projectId])) {
				$tasksByProject[$projectId] = array(
				'project_ref' => isset($taskRow['project_ref']) ? $taskRow['project_ref'] : '',
				'project_title' => isset($taskRow['project_title']) ? $taskRow['project_title'] : '',
				'tasks' => array()
				);
			}
			$tasksByProject[$projectId]['tasks'][] = $taskRow;
		}
		
		// EN: Prepare computation helpers shared by the row rendering logic.
		// FR: Prépare les aides de calcul partagées par la logique de rendu des lignes.
		$dailyRateOptions = array();
		if ($isDailyRateEmployee) {
			$dailyRateOptions = array(
			1 => $outputlangs->trans('TimesheetWeekDailyRateFullDay'),
			2 => $outputlangs->trans('TimesheetWeekDailyRateMorning'),
			3 => $outputlangs->trans('TimesheetWeekDailyRateAfternoon')
			);
		}
		$dailyRateHoursMap = $this->getDailyRateHoursMap();
		$dayTotalsHours = array();
		foreach ($days as $dayName) {
			$dayTotalsHours[$dayName] = 0.0;
		}
		$grandHours = 0.0;
		
		// EN: Build the HTML table mirroring the editable grid layout.
		// FR: Construit le tableau HTML reflétant la grille éditable.
		$htmlGrid = '';
		if (empty($tasksByProject)) {
			$htmlGrid .= '<p>'.tw_pdf_format_cell_html($outputlangs->trans('NoTasksAssigned')).'</p>';
		} else {
			$htmlGrid .= '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="border-collapse:collapse;">';
			$htmlGrid .= '<colgroup>';
			$htmlGrid .= '<col style="width:28%">';
			$dayColumnCount = count($days);
			if ($dayColumnCount > 0) {
				$dayWidth = 9;
				for ($c = 0; $c < $dayColumnCount; $c++) {
					$htmlGrid .= '<col style="width:'.$dayWidth.'%">';
				}
			}
			$htmlGrid .= '<col style="width:9%">';
			$htmlGrid .= '</colgroup>';
			$htmlGrid .= '<thead>';
			$htmlGrid .= '<tr style="background-color:#eeeeee;">';
			$htmlGrid .= '<th>'.tw_pdf_format_cell_html($outputlangs->trans('ProjectTaskColumn')).'</th>';
			foreach ($days as $dayName) {
				$labelKey = isset($dayLabelKeys[$dayName]) ? $dayLabelKeys[$dayName] : $dayName;
				$dayLabel = $outputlangs->trans($labelKey);
				$displayDate = '';
				if (!empty($weekdates[$dayName])) {
					$dayTs = strtotime($weekdates[$dayName]);
					if ($dayTs !== false) {
						$displayDate = dol_print_date($dayTs, 'day');
					}
				}
				$cellContent = tw_pdf_format_cell_html($dayLabel);
				if ($displayDate !== '') {
					$cellContent .= '<br><span style="font-size:9px;color:#666666;">'.tw_pdf_format_cell_html($displayDate).'</span>';
				}
				$htmlGrid .= '<th align="center">'.$cellContent.'</th>';
			}
			$htmlGrid .= '<th align="center">'.tw_pdf_format_cell_html($outputlangs->trans('Total')).'</th>';
			$htmlGrid .= '</tr>';
			$htmlGrid .= '</thead>';
			$htmlGrid .= '<tbody>';
			if (!$isDailyRateEmployee) {
				$htmlGrid .= '<tr style="background-color:#f7f7f7;">';
				$htmlGrid .= '<td></td>';
				foreach ($days as $dayName) {
					$zoneValue = isset($dayZone[$dayName]) && $dayZone[$dayName] !== null ? $dayZone[$dayName] : '-';
					$mealValue = !empty($dayMeal[$dayName]) ? $outputlangs->trans('Yes') : $outputlangs->trans('No');
					$zoneLabel = tw_pdf_format_cell_html($outputlangs->trans('Zone').' '.$zoneValue);
					$mealLabel = tw_pdf_format_cell_html($outputlangs->trans('Meal').': '.$mealValue);
					$htmlGrid .= '<td align="center">'.$zoneLabel.'<br>'.$mealLabel.'</td>';
				}
				$htmlGrid .= '<td></td>';
				$htmlGrid .= '</tr>';
			}
			$colspan = count($days) + 2;
			foreach ($tasksByProject as $projectData) {
				$projectPieces = array();
				if (!empty($projectData['project_ref'])) {
					$projectPieces[] = '['.$projectData['project_ref'].']';
				}
				if (!empty($projectData['project_title'])) {
					$projectPieces[] = $projectData['project_title'];
				}
				$projectLabel = trim(implode(' ', $projectPieces));
				if ($projectLabel === '') {
					$projectLabel = $outputlangs->trans('Project');
				}
				$htmlGrid .= '<tr style="background-color:#f2f2f2;">';
				$htmlGrid .= '<td colspan="'.$colspan.'"><strong>'.tw_pdf_format_cell_html($projectLabel).'</strong></td>';
				$htmlGrid .= '</tr>';
				if (!empty($projectData['tasks'])) {
					foreach ($projectData['tasks'] as $taskRow) {
						$taskLabelPieces = array();
						if (!empty($taskRow['task_ref'])) {
							$taskLabelPieces[] = '['.$taskRow['task_ref'].']';
						}
						if (!empty($taskRow['task_label'])) {
							$taskLabelPieces[] = $taskRow['task_label'];
						}
						$taskLabel = trim(implode(' ', $taskLabelPieces));
						if ($taskLabel === '') {
							$taskLabel = $outputlangs->trans('Task');
						}
						$htmlGrid .= '<tr>';
						$htmlGrid .= '<td>'.tw_pdf_format_cell_html($taskLabel).'</td>';
						$rowTotalHours = 0.0;
						foreach ($days as $dayName) {
							$dayDate = isset($weekdates[$dayName]) ? $weekdates[$dayName] : null;
							$cellHours = 0.0;
							$cellDisplay = '';
							if ($dayDate !== null) {
								if ($isDailyRateEmployee) {
									$rateCode = isset($dailyRateBy[(int) $taskRow['task_id']][$dayDate]) ? (int) $dailyRateBy[(int) $taskRow['task_id']][$dayDate] : 0;
									if ($rateCode > 0 && isset($dailyRateHoursMap[$rateCode])) {
										$cellHours = (float) $dailyRateHoursMap[$rateCode];
										$cellDisplay = isset($dailyRateOptions[$rateCode]) ? $dailyRateOptions[$rateCode] : '';
									}
								} else {
									if (isset($hoursBy[(int) $taskRow['task_id']][$dayDate])) {
										$cellHours = (float) $hoursBy[(int) $taskRow['task_id']][$dayDate];
										if ($cellHours > 0) {
											$cellDisplay = formatHours($cellHours);
										}
									}
								}
							}
							$dayTotalsHours[$dayName] += $cellHours;
							$rowTotalHours += $cellHours;
							$htmlGrid .= '<td align="center">'.($cellDisplay !== '' ? tw_pdf_format_cell_html($cellDisplay) : '&nbsp;').'</td>';
						}
						$grandHours += $rowTotalHours;
						if ($isDailyRateEmployee) {
							$displayTotal = $this->formatDays(($rowTotalHours > 0 ? ($rowTotalHours / 8.0) : 0.0), $outputlangs);
						} else {
							$displayTotal = ($rowTotalHours > 0 ? formatHours($rowTotalHours) : '');
						}
						$htmlGrid .= '<td align="center">'.($displayTotal !== '' ? tw_pdf_format_cell_html($displayTotal) : '&nbsp;').'</td>';
						$htmlGrid .= '</tr>';
					}
				}
			}
			if ($isDailyRateEmployee) {
				$htmlGrid .= '<tr style="background-color:#f7f7f7;">';
				$htmlGrid .= '<td>'.tw_pdf_format_cell_html($outputlangs->trans('TimesheetWeekTotalDays')).'</td>';
				foreach ($days as $dayName) {
					$dayValue = ($dayTotalsHours[$dayName] > 0 ? ($dayTotalsHours[$dayName] / 8.0) : 0.0);
					$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html($this->formatDays($dayValue, $outputlangs)).'</td>';
				}
				$grandDays = ($grandHours > 0 ? ($grandHours / 8.0) : 0.0);
				$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html($this->formatDays($grandDays, $outputlangs)).'</td>';
				$htmlGrid .= '</tr>';
			} else {
				$htmlGrid .= '<tr style="background-color:#f7f7f7;">';
				$htmlGrid .= '<td>'.tw_pdf_format_cell_html($outputlangs->trans('Total')).'</td>';
				foreach ($days as $dayName) {
					$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html(formatHours($dayTotalsHours[$dayName])).'</td>';
				}
				$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html(formatHours($grandHours)).'</td>';
				$htmlGrid .= '</tr>';
				$mealCount = array_sum($dayMeal);
				$htmlGrid .= '<tr style="background-color:#f7f7f7;">';
				$htmlGrid .= '<td>'.tw_pdf_format_cell_html($outputlangs->trans('Meals')).'</td>';
				$htmlGrid .= '<td colspan="'.count($days).'"></td>';
				$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html($mealCount).'</td>';
				$htmlGrid .= '</tr>';
				$overtimeHours = !empty($object->overtime_hours) ? (float) $object->overtime_hours : max(0.0, $grandHours - $contractedHours);
				$htmlGrid .= '<tr style="background-color:#f7f7f7;">';
				$htmlGrid .= '<td>'.tw_pdf_format_cell_html($outputlangs->trans('Overtime').' ('.formatHours($contractedHours).')').'</td>';
				$htmlGrid .= '<td colspan="'.count($days).'"></td>';
				$htmlGrid .= '<td align="center">'.tw_pdf_format_cell_html(formatHours($overtimeHours)).'</td>';
				$htmlGrid .= '</tr>';
			}
			$htmlGrid .= '</tbody>';
			$htmlGrid .= '</table>';
		}
		
		// EN: Prepare PDF header metadata with week range and reference subtitle.
		// FR: Prépare les métadonnées d'entête du PDF avec la plage de semaine et la référence en sous-titre.
		$weekLabel = (!empty($object->week) ? sprintf('%02d', (int) $object->week) : '00');
		$yearLabel = (!empty($object->year) ? sprintf('%04d', (int) $object->year) : (dol_strlen($weekStartDate) === 10 ? date('Y', strtotime($weekStartDate)) : date('Y')));
		$headerTitle = $outputlangs->trans('TimesheetWeek');
		$headerStatus = '';
		if (isset($object->status)) {
			// EN: Align PDF badge generation on Dolibarr helpers for consistent styling.
			// FR: Aligne la génération des badges PDF sur les helpers Dolibarr pour garder un style cohérent.
			$statusLabelKeys = array(
				TimesheetWeek::STATUS_DRAFT => 'TimesheetWeekStatusDraft',
				TimesheetWeek::STATUS_SUBMITTED => 'TimesheetWeekStatusSubmitted',
				TimesheetWeek::STATUS_APPROVED => 'TimesheetWeekStatusApproved',
				TimesheetWeek::STATUS_SEALED => 'TimesheetWeekStatusSealed',
				TimesheetWeek::STATUS_REFUSED => 'TimesheetWeekStatusRefused'
			);
			// EN: Map each business status to its Dolibarr badge identifier.
			// FR: Mappe chaque statut métier vers son identifiant de badge Dolibarr.
			$statusTypes = array(
				TimesheetWeek::STATUS_DRAFT => 'status0',
				TimesheetWeek::STATUS_SUBMITTED => 'status1',
				TimesheetWeek::STATUS_APPROVED => 'status4',
				TimesheetWeek::STATUS_SEALED => 'status6',
				TimesheetWeek::STATUS_REFUSED => 'status8'
			);
			// EN: Reuse Dolibarr badge palette to render inline styles in the PDF context.
			// FR: Réutilise la palette de badges Dolibarr pour produire un style inline dans le contexte PDF.
			$statusStyles = array(
				'status0' => array('bg' => '#adb5bd', 'fg' => '#212529'),
				'status1' => array('bg' => '#0d6efd', 'fg' => '#ffffff'),
				'status4' => array('bg' => '#198754', 'fg' => '#ffffff'),
				'status6' => array('bg' => '#6f42c1', 'fg' => '#ffffff'),
				'status8' => array('bg' => '#dc3545', 'fg' => '#ffffff')
			);
			$statusValue = (int) $object->status;
			if (!empty($statusLabelKeys[$statusValue]) && !empty($statusTypes[$statusValue])) {
				$statusType = $statusTypes[$statusValue];
				$statusLabel = $outputlangs->trans($statusLabelKeys[$statusValue]);
				$statusLabel = dol_escape_htmltag($statusLabel);
				$badgeStyle = '';
				if (!empty($statusStyles[$statusType])) {
					$badgeStyle = 'color:'.$statusStyles[$statusType]['fg'].';background-color:'.$statusStyles[$statusType]['bg'].';border-radius:3px;padding:1px 6px;font-weight:bold;display:inline-block;';
				}
				$badgeParams = array(
					'badgeParams' => array(
						'attr' => array(
							'classOverride' => 'badge badge-status '.$statusType,
							'aria-label' => $statusLabel,
						),
					)
				);
				if (!empty($badgeStyle)) {
					$badgeParams['badgeParams']['attr']['style'] = $badgeStyle;
				}
				$headerStatus = dolGetStatus(
					$statusLabel,
					$statusLabel,
					$statusLabel,
					$statusType,
					5,
					'',
					$badgeParams
				);
			} else {
				$headerStatus = dol_escape_htmltag($outputlangs->trans('Unknown'));
			}
		}
		$headerWeekRange = '';
		if (!empty($object->week) && !empty($object->year)) {
			// EN: Retrieve the translated week range template before injecting ISO week and year values.
			// FR: Récupère le gabarit traduit de la plage de semaine avant d'injecter les valeurs ISO de semaine et d'année.
			$headerWeekRangeLabel = $outputlangs->trans('TimesheetWeekSummaryHeaderWeekRange');
			// EN: Compose the final week label with the ISO week number and year to match the translated template.
			// FR: Compose le libellé final avec le numéro de semaine ISO et l'année pour correspondre au gabarit traduit.
			$headerWeekRange = sprintf($headerWeekRangeLabel, $weekLabel, $yearLabel);
		}
		$headerSubtitle = $outputlangs->trans('TimesheetWeekPdfReferenceLabel', $object->ref);
		if ($timesheetEmployee instanceof User) {
			$employeeSubtitle = $outputlangs->trans('Employee').': '.$timesheetEmployee->getFullName($outputlangs);
			$headerSubtitle .= "\n".$employeeSubtitle;
		}
		if ((int) $object->status === TimesheetWeek::STATUS_APPROVED) {
			// EN: Prepare the approval trace with validation date and validator name for the PDF header.
			// FR: Prépare la trace d'approbation avec la date de validation et le validateur pour l'entête PDF.
			$approvalDateLabel = '';
			if (!empty($object->date_validation)) {
				$approvalDateLabel = dol_print_date($object->date_validation, '%d/%m/%Y');
			}
			$validatorName = '';
			if (!empty($object->fk_user_valid)) {
				$validatorUser = new User($this->db);
				if ($validatorUser->fetch($object->fk_user_valid) > 0) {
					$validatorName = $validatorUser->getFullName($outputlangs);
				}
			}
			if ($approvalDateLabel !== '' && $validatorName !== '') {
				// EN: Append the approval information under the employee line to mirror the card layout.
				// FR: Ajoute les informations d'approbation sous la ligne salarié pour refléter la carte Dolibarr.
				$headerSubtitle .= "\n".$outputlangs->trans('TimesheetWeekPdfApprovedDetails', $approvalDateLabel, $validatorName);
			}
		}
		
		$format = pdf_getFormat();
		$pdfFormat = array($format['width'], $format['height']);
		$margeGauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$margeDroite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$margeHaute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$margeBasse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$footerReserve = 12;
		$autoPageBreakMargin = $margeBasse + $footerReserve;
		
		$pdf = pdf_getInstance($pdfFormat);
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$pdf->SetPageOrientation('L');
		$pdf->SetAutoPageBreak(true, $autoPageBreakMargin);
		$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
		$headerState = array('value' => 0.0, 'automatic' => false);
		if (method_exists($pdf, 'setHeaderCallback') && method_exists($pdf, 'setFooterCallback')) {
			$pdf->setPrintHeader(true);
			$pdf->setHeaderCallback(function ($pdfInstance) use ($outputlangs, $conf, $margeGauche, $margeHaute, &$headerState, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle) {
				$headerState['value'] = tw_pdf_draw_header($pdfInstance, $outputlangs, $conf, $margeGauche, $margeHaute, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
				$headerState['automatic'] = true;
			});
			$pdf->setPrintFooter(true);
			$pdf->setFooterCallback(function ($pdfInstance) use ($outputlangs, $conf, $margeGauche, $margeDroite, $margeBasse, $autoPageBreakMargin, $object) {
				tw_pdf_draw_footer($pdfInstance, $outputlangs, $conf, $margeGauche, $margeDroite, $margeBasse, $object, 0, $autoPageBreakMargin);
			});
		} else {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		} elseif (method_exists($pdf, 'setAliasNbPages')) {
			$pdf->setAliasNbPages();
		}
		
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($user->getFullName($outputlangs));
		$pdf->SetTitle(tw_pdf_normalize_string($headerTitle));
		$pdf->SetSubject(tw_pdf_normalize_string($headerTitle));
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $defaultFontSize);
		$pdf->Open();
		
		$contentTop = tw_pdf_add_landscape_page($pdf, $outputlangs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $headerState, $autoPageBreakMargin, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
		$pdf->SetXY($margeGauche, $contentTop + 2.0);
		$pdf->writeHTMLCell(0, 0, '', '', $htmlGrid, 0, 1, 0, true, '', true);
		$pdf->lastPage();
		
		$filename = $cleanRef.'.pdf';
		$destinationFile = $targetDir.'/'.$filename;
		$resultOutput = $pdf->Output($destinationFile, 'F');
		if ($resultOutput === false) {
			// EN: Abort when TCPDF signals a failure while writing the document to disk.
			// FR: Abandonne lorsque TCPDF signale un échec lors de l'écriture du document sur le disque.
			$this->error = $outputlangs->trans('ErrorFailToCreateFile');
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}
		
		$this->result = array(
			'fullpath' => $destinationFile,
			'filename' => $filename,
			'relativepath' => $relativePath.'/'.$filename,
			'warnings' => array()
		);
	
		return 1;
	}
	
	/**
	 * EN: Format a day quantity using Dolibarr price helpers to mimic the card view.
	 * FR: Formate une quantité de jours via les helpers de prix Dolibarr pour imiter la vue carte.
	 *
	 * @param float $value Day quantity / Quantité de jours
	 * @param Translate $langs Language handler / Gestionnaire de langues
	 * @return string
	 */
	protected function formatDays($value, $langs)
	{
		$value = price2num($value, '2');
		return price($value, '', $langs, null, 1, 2);
	}
	
	/**
	 * EN: Provide the mapping between daily-rate codes and their hour equivalents.
	 * FR: Fournit la correspondance entre les codes forfait jour et leur équivalent horaire.
	 *
	 * @return array<int,float>
	 */
	protected function getDailyRateHoursMap()
	{
		return array(
			1 => 8.0,
			2 => 4.0,
			3 => 4.0
		);
	}
	}
