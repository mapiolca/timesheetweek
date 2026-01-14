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
	const STATUS_SEALED    = 8; // EN: "sealed" status / FR : statut "scellée"
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
	public $contract = null;        // stored contract hours snapshot
	public $zone1_count = 0;         // zone 1 days count / nombre de jours en zone 1
	public $zone2_count = 0;         // zone 2 days count / nombre de jours en zone 2
	public $zone3_count = 0;         // zone 3 days count / nombre de jours en zone 3
	public $zone4_count = 0;         // zone 4 days count / nombre de jours en zone 4
	public $zone5_count = 0;         // zone 5 days count / nombre de jours en zone 5
	public $meal_count = 0;          // meal days count / nombre de jours avec panier
	public $model_pdf = '';          // EN: Selected PDF model / FR: Modèle PDF sélectionné

	/**
	* EN: Cache flag telling if the table includes the model_pdf column.
	* FR: Drapeau mis en cache indiquant si la table inclut la colonne model_pdf.
	*
	* @var bool|null
	*/
	protected $hasModelPdfColumn = null;

	/** @var TimesheetWeekLine[] */
	public $lines = array();

	public $errors = array();
	public $warnings = array();
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
	* EN: Load object info for dol_print_object_info (author/validator/date).
	* FR: Charge les informations d'objet pour dol_print_object_info (auteur/validateur/date).
	*
	* @param int $id
	* @return int <0 if KO, >0 if OK
	*/
	public function info($id)
	{
		$sql = 'SELECT rowid, fk_user, fk_user_valid, date_creation, date_validation, tms';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid='.(int) $id;
		// EN: Restrict info fetch to authorized entities.
		// FR: Restreint la lecture aux entités autorisées.
		$sql .= ' AND entity IN ('.getEntity($this->element).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($obj = $this->db->fetch_object($resql)) {
			// EN: Map custom fields to CommonObject info keys.
			// FR: Mappe les champs personnalisés aux clés info CommonObject.
			$this->info = array(
				'datec' => $this->db->jdate($obj->date_creation),
				'datev' => $this->db->jdate($obj->date_validation),
				'datem' => $this->db->jdate($obj->tms),
				'fk_user_author' => (int) $obj->fk_user,
				'fk_user_valid' => (int) $obj->fk_user_valid,
			);
		}

		$this->db->free($resql);
		return 1;
	}
	/**
	* Initialise un objet specimen (prévisualisation / exemple de numérotation).
	*
	* @return int 1 si OK, <0 si KO
	*/
	public function initAsSpecimen()
	{
		$ret = 1;

		// CommonObject (Dolibarr) fournit généralement initAsSpecimenCommon()
		if (method_exists($this, 'initAsSpecimenCommon')) {
			$ret = $this->initAsSpecimenCommon();
			if ($ret < 0) return $ret;
		}

		$now = dol_now();

		$this->id = 0;
		$this->ref = 'TSW-SPECIMEN';
		$this->status = self::STATUS_DRAFT;

		// Utilisé par le modèle de numérotation (get_next_value) via $object->date_creation
		$this->date_creation = $now;

		// Valeurs cohérentes si le masque exploite l'année / semaine
		$this->year = (int) dol_print_date($now, '%Y');
		$this->week = (int) dol_print_date($now, '%V');

		return 1;
	}

	/**
	* EN: Detect lazily if the database schema already stores the PDF model.
	* FR: Détecte paresseusement si le schéma de base stocke déjà le modèle PDF.
	*
	* @return bool
	*/
	protected function checkModelPdfColumnAvailability()
	{
		if ($this->hasModelPdfColumn !== null) {
			return $this->hasModelPdfColumn;
		}

		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$this->table_element." LIKE 'model_pdf'";
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->hasModelPdfColumn = ($this->db->num_rows($resql) > 0);
			$this->db->free($resql);
		} else {
			$this->hasModelPdfColumn = false;
		}

		return $this->hasModelPdfColumn;
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

		$fields = array(
		'ref',
		'entity',
		'fk_user',
		'year',
		'week',
		'status',
		'note',
		'date_creation',
		'fk_user_valid',
		'total_hours',
		'overtime_hours',
		'contract',
		'zone1_count',
		'zone2_count',
		'zone3_count',
		'zone4_count',
		'zone5_count',
		'meal_count'
		);
		$values = array(
		"'".$this->db->escape($this->ref ? $this->ref : '(PROV)')."'",
		(int) $this->entity,
		(int) $this->fk_user,
		(int) $this->year,
		(int) $this->week,
		(int) $this->status,
		($this->note !== null ? "'".$this->db->escape($this->note)."'" : 'NULL'),
		"'".$this->db->idate($now)."'",
		(!empty($this->fk_user_valid) ? (int) $this->fk_user_valid : 'NULL'),
		(float) ($this->total_hours ?: 0),
		(float) ($this->overtime_hours ?: 0),
		($this->contract !== null ? (float) $this->contract : 'NULL'),
		(int) ($this->zone1_count ?: 0),
		(int) ($this->zone2_count ?: 0),
		(int) ($this->zone3_count ?: 0),
		(int) ($this->zone4_count ?: 0),
		(int) ($this->zone5_count ?: 0),
		(int) ($this->meal_count ?: 0)
		);

		if ($this->checkModelPdfColumnAvailability()) {
			// EN: Persist the PDF model selection when the schema supports it.
			// FR: Persiste la sélection du modèle PDF lorsque le schéma le supporte.
			$selectedModel = $this->model_pdf !== '' ? $this->model_pdf : getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
			if ($selectedModel !== '') {
				$this->model_pdf = $selectedModel;
				$values[] = "'".$this->db->escape($selectedModel)."'";
			} else {
				$values[] = 'NULL';
			}
			$fields[] = 'model_pdf';
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (".implode(', ', $fields).") VALUES (".implode(', ', $values).")";

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
			// EN: Prevent provisional reference updates outside the allowed entities.
			// FR: Empêche la mise à jour de référence provisoire hors des entités autorisées.
			$up .= " AND entity IN (".getEntity('timesheetweek').")";
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
	* EN: Fetch the existing timesheet for a user/week within an entity.
	* FR: Récupère la feuille existante pour un utilisateur/semaine au sein d'une entité.
	*
	* @param int      $userId  Target user id
	* @param int      $year    Target ISO year
	* @param int      $week    Target ISO week number
	* @param int|null $entity  Optional entity identifier
	* @return int               >0 if found (id), 0 if not found, <0 on error
	*/
	public function fetchByUserWeek($userId, $year, $week, $entity = null)
	{
		global $conf;

		$this->error = '';
		$this->errors = array();

		// EN: Determine the entity used for the lookup.
		// FR: Détermine l'entité utilisée pour la recherche.
		$entityId = ($entity !== null) ? (int) $entity : (int) $conf->entity;

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity=".(int) $entityId;
		// EN: Double check the entity against the module permissions matrix.
		// FR: Vérifie en plus que l'entité figure dans la matrice des permissions du module.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
		$sql .= " AND fk_user=".(int) $userId;
		$sql .= " AND year=".(int) $year;
		$sql .= " AND week=".(int) $week;
		$sql .= " LIMIT 1";

		// EN: Execute the lookup to detect an existing record.
		// FR: Exécute la recherche pour détecter un enregistrement existant.
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		return $this->fetch((int) $obj->rowid);
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

		$includeModelPdf = $this->checkModelPdfColumnAvailability();

		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_user, t.year, t.week, t.status, t.note, t.date_creation, t.tms, t.date_validation, t.fk_user_valid,";
$sql .= " t.total_hours, t.overtime_hours, t.contract, t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count";
		if ($includeModelPdf) {
			$sql .= ", t.model_pdf";
		}
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE 1=1";
		// EN: Restrict fetch operations to the entities enabled for this module.
		// FR: Restreint les opérations de récupération aux entités autorisées pour ce module.
		$sql .= " AND t.entity IN (".getEntity('timesheetweek').")";
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
$this->contract = ($obj->contract !== null ? (float) $obj->contract : null);
$this->zone1_count = (int) $obj->zone1_count;
		$this->zone2_count = (int) $obj->zone2_count;
		$this->zone3_count = (int) $obj->zone3_count;
		$this->zone4_count = (int) $obj->zone4_count;
		$this->zone5_count = (int) $obj->zone5_count;
		$this->meal_count = (int) $obj->meal_count;
		if ($includeModelPdf && isset($obj->model_pdf)) {
			// EN: Load the stored PDF model when the column is available.
			// FR: Charge le modèle PDF stocké lorsque la colonne est disponible.
			$this->model_pdf = ($obj->model_pdf !== null) ? (string) $obj->model_pdf : '';
		} else {
			$this->model_pdf = '';
		}

		if ($this->model_pdf === '') {
			// EN: Default back to the configured PDF model to keep generation functional.
			// FR: Revient au modèle PDF configuré pour conserver une génération fonctionnelle.
			$this->model_pdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
		}

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
		// EN: Protect the fetch to ensure lines belong to an allowed entity scope.
		// FR: Protège le chargement pour s'assurer que les lignes appartiennent à une entité autorisée.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
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
$sets[] = "contract=".($this->contract !== null ? (float) $this->contract : 'NULL');
$sets[] = "zone1_count=".(int) ($this->zone1_count ?: 0);
		$sets[] = "zone2_count=".(int) ($this->zone2_count ?: 0);
		$sets[] = "zone3_count=".(int) ($this->zone3_count ?: 0);
		$sets[] = "zone4_count=".(int) ($this->zone4_count ?: 0);
		$sets[] = "zone5_count=".(int) ($this->zone5_count ?: 0);
		$sets[] = "meal_count=".(int) ($this->meal_count ?: 0);
		if ($this->checkModelPdfColumnAvailability()) {
			// EN: Synchronise the stored PDF model when the schema exposes the column.
			// FR: Synchronise le modèle PDF stocké lorsque le schéma expose la colonne.
			$sets[] = "model_pdf=".($this->model_pdf !== '' ? "'".$this->db->escape($this->model_pdf)."'" : 'NULL');
		}
		$sets[] = "tms='".$this->db->idate($now)."'";

		$sql .= " ".implode(', ', $sets);
		$sql .= " WHERE rowid=".(int) $this->id;
		// EN: Ensure updates only target rows within authorized entities.
		// FR: Garantit que les mises à jour ne visent que les lignes des entités autorisées.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";

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

	/**
	* EN: Override to skip SQL updates when the schema lacks the model_pdf column.
	* FR: Surcharge pour ignorer les mises à jour SQL lorsque le schéma n'a pas la colonne model_pdf.
	*
	* @param User  $user   Current user triggering the change
	* @param string $model PDF model identifier
	* @param string $type  Optional type (kept for compatibility)
	* @return int
	*/
	public function setDocModel($user, $model, $type = '')
	{
		$this->model_pdf = $model;

		if ($this->checkModelPdfColumnAvailability()) {
			return parent::setDocModel($user, $model, $type);
		}

		return 1;
	}
	/**
	 * EN: Generate the PDF document using the selected model.
	 * FR: Génère le document PDF en utilisant le modèle sélectionné.
	 *
	 * @param string $model  PDF model identifier / Identifiant du modèle PDF
	 * @param Translate|null $outputlangs  Language handler / Gestionnaire de langues
	 * @param int $hidedetails  Hide detail lines flag / Indicateur de masquage des détails
	 * @param int $hidedesc  Hide descriptions flag / Indicateur de masquage des descriptions
	 * @param int $hideref  Hide references flag / Indicateur de masquage des références
	 * @param array $moreparams  Additional parameters / Paramètres additionnels
	 * @return int 1 on success, <=0 otherwise / 1 si succès, <=0 sinon
	 */
	public function generateDocument($model = '', $outputlangs = null, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = array())
	{
		global $conf, $langs;

		// EN: Reset error containers before launching the generator.
		// FR: Réinitialise les conteneurs d'erreurs avant de lancer le générateur.
		$this->error = '';
		$this->errors = array();

		// EN: Remember the requested model or fall back to the object and global configuration.
		// FR: Retient le modèle demandé ou retombe sur la configuration de l'objet puis du global.
		if (empty($model)) {
			if (!empty($this->model_pdf)) {
				$model = $this->model_pdf;
			} else {
				$model = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
			}
		}

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		// EN: Ensure translations are available for error and filename messages.
		// FR: S'assure que les traductions sont disponibles pour les messages d'erreur et de fichier.
		if ($outputlangs instanceof Translate) {
			if (method_exists($outputlangs, 'loadLangs')) {
				$outputlangs->loadLangs(array('main', 'timesheetweek@timesheetweek'));
			} else {
				$outputlangs->load('main');
				$outputlangs->load('timesheetweek@timesheetweek');
			}
		}

		if (empty($model)) {
			$this->error = $outputlangs instanceof Translate ? $outputlangs->trans('ErrorNoPDFForDoc') : 'No PDF model available';
			return -1;
		}

		// EN: Make the TimesheetWeek document models available.
		// FR: Rend disponibles les modèles de documents TimesheetWeek.
		dol_include_once('/timesheetweek/core/modules/timesheetweek/modules_timesheetweek.php');

		$modulePath = dol_buildpath('/timesheetweek/core/modules/timesheetweek/doc/pdf_'.$model.'.modules.php', 0);

		// EN: Abort if the PDF module file cannot be accessed.
		// FR: Abandonne si le fichier du module PDF est inaccessible.
		if (!is_readable($modulePath)) {
			$this->error = $outputlangs instanceof Translate ? $outputlangs->trans('ErrorFailedToLoadFile', basename($modulePath)) : 'Unable to load PDF module';
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		require_once $modulePath;

		$classname = 'pdf_'.$model;

		// EN: Validate the presence of the generator class before instantiation.
		// FR: Valide la présence de la classe génératrice avant instanciation.
		if (!class_exists($classname)) {
			$this->error = $outputlangs instanceof Translate ? $outputlangs->trans('ErrorFailedToLoadFile', $classname) : 'PDF class not found';
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		$generator = new $classname($this->db);

		// EN: Execute the PDF generation with the selected options.
		// FR: Exécute la génération PDF avec les options sélectionnées.
		$result = $generator->write_file($this, $outputlangs, '', $hidedetails, $hidedesc, $hideref);

		if ($result <= 0) {
			// EN: Propagate detailed errors from the generator for troubleshooting.
			// FR: Propage les erreurs détaillées du générateur pour faciliter le diagnostic.
			if (!empty($generator->errors)) {
				$this->errors = (array) $generator->errors;
				$this->error = implode(', ', $this->errors);
			} elseif (!empty($generator->error)) {
				$this->error = $generator->error;
				$this->errors = array($generator->error);
			} else {
				$this->error = $outputlangs instanceof Translate ? $outputlangs->trans('ErrorFailToCreateFile') : 'PDF generation failed';
				$this->errors = array($this->error);
			}
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Keep track of the last generated document path for Dolibarr widgets.
		// FR: Conserve le chemin du dernier document généré pour les widgets Dolibarr.
		if (!empty($generator->result['relativepath'])) {
			$this->last_main_doc = $generator->result['relativepath'];
		} elseif (!empty($generator->result['fullpath'])) {
			$this->last_main_doc = basename($generator->result['fullpath']);
		}

		// EN: Store potential warnings from the generator for later display.
		// FR: Stocke les avertissements potentiels du générateur pour un affichage ultérieur.
		if (!empty($generator->result['warnings'])) {
			$this->warnings = $generator->result['warnings'];
		}

		// EN: Memorise the model used so that the next generation reuses it.
		// FR: Mémorise le modèle utilisé pour que la prochaine génération le réutilise.
		if (!empty($model)) {
			$this->model_pdf = $model;
		}

		return 1;
	}

	public function delete($user)
	{
		$this->db->begin();

		$dl = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $this->id;
		// EN: Secure the cascade deletion to the permitted entities only.
		// FR: Sécurise la suppression en cascade aux seules entités autorisées.
		$dl .= " AND entity IN (".getEntity('timesheetweek').")";
		if (!$this->db->query($dl)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid=".(int) $this->id;
		// EN: Limit the deletion of the header to the entities allowed for the module.
		// FR: Limite la suppression de l'en-tête aux entités autorisées pour le module.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
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

		// Weekly contracted hours snapshot
		$weekly = ($this->contract !== null ? (float) $this->contract : 35.0);
		if (!empty($this->fk_user)) {
			$u = new User($this->db);
			if ($u->fetch($this->fk_user) > 0) {
				if (!empty($u->weeklyhours)) {
					$weekly = ($this->contract !== null ? (float) $this->contract : (float) $u->weeklyhours);
					if ($this->contract === null) {
						$this->contract = (float) $u->weeklyhours;
					}
				}
			}
		}
		if ($this->contract === null) {
			$this->contract = $weekly;
		}
		$ot = $total - $weekly;
		$this->overtime_hours = ($ot > 0) ? $ot : 0.0;
	}

	/**
	 * EN: Save totals into DB.
	 * FR: Enregistre les totaux en base.
	 *
	 * @return int
	 */
	public function updateTotalsInDB()
	{
		$this->computeTotals();
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET total_hours=".(float) $this->total_hours.", overtime_hours=".(float) $this->overtime_hours;
		$sql .= ", contract=".($this->contract !== null ? (float) $this->contract : 'NULL');
		$sql .= ", zone1_count=".(int) $this->zone1_count.", zone2_count=".(int) $this->zone2_count;
		$sql .= ", zone3_count=".(int) $this->zone3_count.", zone4_count=".(int) $this->zone4_count;
		$sql .= ", zone5_count=".(int) $this->zone5_count.", meal_count=".(int) $this->meal_count;
		$sql .= " WHERE rowid=".(int) $this->id;
		// EN: Constrain totals updates to the allowed entities for safety.
		// FR: Contraint les mises à jour des totaux aux entités autorisées pour plus de sécurité.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
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
		// EN: Count only lines belonging to the permitted entities for submission checks.
		// FR: Compte uniquement les lignes des entités autorisées pour les vérifications de soumission.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
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

		$contractHours = 35.0;
		$employeeContract = new User($this->db);
		if ($employeeContract->fetch($this->fk_user) > 0 && !empty($employeeContract->weeklyhours)) {
			$contractHours = (float) $employeeContract->weeklyhours;
		}
		$this->contract = $contractHours;

		if ($this->updateTotalsInDB() < 0) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

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
			// EN: Secure definitive reference assignment to authorized entities.
			// FR: Sécurise l'attribution de la référence définitive aux entités autorisées.
			$upref .= " AND entity IN (".getEntity('timesheetweek').")";
			if (!$this->db->query($upref)) {
				$this->db->rollback();
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=".(int) self::STATUS_SUBMITTED.", tms='".$this->db->idate($now)."', date_validation=NULL WHERE rowid=".(int) $this->id;
		// EN: Apply the status change strictly within the accessible entities.
		// FR: Applique le changement de statut strictement au sein des entités accessibles.
		$up .= " AND entity IN (".getEntity('timesheetweek').")";
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
		$previousStatus = (int) $this->status;

		if ((int) $this->status === self::STATUS_DRAFT) {
			$this->error = 'AlreadyDraft';
			return 0;
		}

		$taskIds = array();
		$lines = $this->getLines();
		if (!empty($lines)) {
			foreach ($lines as $line) {
				if (empty($line->fk_task)) {
					continue;
				}

				// EN: Track tasks with recorded hours to refresh effective durations after rollback.
				// FR: Suit les tâches ayant du temps saisi pour rafraîchir les durées effectives après retour.
				$taskIds[(int) $line->fk_task] = (int) $line->fk_task;
			}
		}

		$this->db->begin();

		$up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$up .= " SET status=".(int) self::STATUS_DRAFT.", tms='".$this->db->idate($now)."', date_validation=NULL";
		// EN: Protect draft rollback with entity scoping for multi-company safety.
		// FR: Protège le retour en brouillon en restreignant l'entité pour la sécurité multi-entreprise.
		$up .= " WHERE rowid=".(int) $this->id;
		$up .= " AND entity IN (".getEntity('timesheetweek').")";
		if (!$this->db->query($up)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($previousStatus === self::STATUS_APPROVED || $previousStatus === self::STATUS_SEALED) {
			$this->overtime_hours = 0;
			$this->updateTotalsInDB();
		}

		$taskIds = array_filter($taskIds);
		if (!empty($taskIds) && ($previousStatus === self::STATUS_APPROVED || $previousStatus === self::STATUS_SEALED)) {
			// EN: Recompute effective duration on related tasks after removing approved times.
			// FR: Recalcule la durée effective des tâches concernées après suppression des temps approuvés.
			if ($this->updateTasksDurationEffective($taskIds) < 0) {
				$this->db->rollback();
				return -1;
			}
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
		// EN: Restrict the approval to timesheets inside permitted entities.
		// FR: Restreint l'approbation aux feuilles situées dans les entités autorisées.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";

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
	* EN: Seal the approved timesheet to prevent further changes.
	* FR : Scelle la feuille approuvée pour empêcher de nouvelles modifications.
	*
	* @param User $user
	* @return int
	*/
	public function seal($user, $origin = 'manual')
	{
		global $langs;

		$now = dol_now();
		$noteUpdate = null;

		if ((int) $this->status !== self::STATUS_APPROVED) {
			$this->error = 'BadStatusForSeal';
			return -1;
		}

		if ($origin === 'auto') {
			// EN: Build the automatic sealing trace for the note field.
			// FR: Construit la trace de scellement automatique pour le champ note.
			if ($langs instanceof Translate) {
				$langs->loadLangs(array('timesheetweek@timesheetweek'));
			}
			$noteDateLabel = dol_print_date($now, '%d/%m/%Y');
			$userFullName = method_exists($user, 'getFullName') ? $user->getFullName($langs) : trim($user->firstname.' '.$user->lastname);
			$noteMessage = $langs->trans('TimesheetWeekAutoSealTrace', $noteDateLabel, $userFullName);
			if (!empty($noteMessage)) {
				if (!empty($this->note)) {
					$noteUpdate = $this->note."\n".$noteMessage;
				} else {
					$noteUpdate = $noteMessage;
				}
			}
		}

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " status=".(int) self::STATUS_SEALED;
		$sql .= ", tms='".$this->db->idate($now)."'";
		if ($noteUpdate !== null) {
			$sql .= ", note='".$this->db->escape($noteUpdate)."'";
		}
		$sql .= " WHERE rowid=".(int) $this->id;

		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		// EN: Keep approval metadata intact while locking the sheet.
		// FR : Conserve les métadonnées d'approbation tout en verrouillant la feuille.
		$this->status = self::STATUS_SEALED;
		$this->tms = $now;
		if ($noteUpdate !== null) {
			$this->note = $noteUpdate;
		}

		if (!$this->createAgendaEvent($user, 'TSWK_SEAL', 'TimesheetWeekAgendaSealed', array($this->ref))) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	* EN: Revert a sealed timesheet back to the approved state.
	* FR : Rouvre une feuille scellée en la repassant au statut approuvé.
	*
	* @param User $user
	* @return int
	*/
	public function unseal($user)
	{
		$now = dol_now();

		if ((int) $this->status !== self::STATUS_SEALED) {
			$this->error = 'BadStatusForUnseal';
			return -1;
		}

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " status=".(int) self::STATUS_APPROVED;
		$sql .= ", tms='".$this->db->idate($now)."'";
		$sql .= " WHERE rowid=".(int) $this->id;

		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		// EN: Keep the original validation date while allowing edits again.
		// FR : Préserve la date d'approbation d'origine tout en rouvrant l'édition.
		$this->status = self::STATUS_APPROVED;
		$this->tms = $now;

		if (!$this->createAgendaEvent($user, 'TSWK_UNSEAL', 'TimesheetWeekAgendaUnsealed', array($this->ref))) {
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

			$taskIds = array();

			foreach ($lines as $line) {
				$durationSeconds = (int) round(((float) $line->hours) * 3600);
				if ($durationSeconds <= 0) {
					continue;
				}

				if (!empty($line->fk_task)) {
					$taskIds[] = (int) $line->fk_task;
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

			if (!empty($taskIds)) {
				$taskIds = array_unique($taskIds);
				if ($this->updateTasksDurationEffective($taskIds) < 0) {
					return -1;
				}
			}

			return 1;
		}

		/**
		 * EN: Update project task effective durations based on aggregated element_time entries.
		 * FR: Met à jour les durées effectives des tâches projet à partir des enregistrements element_time agrégés.
		 *
		 * @param array $taskIds
		 * @return int
		 */
		protected function updateTasksDurationEffective($taskIds)
		{
			if (empty($taskIds)) {
				return 1;
			}

			// EN: Prepare unique task identifiers list for querying.
			// FR: Prépare la liste unique des identifiants de tâches pour la requête.
			$cleanTaskIds = array_unique(array_map('intval', $taskIds));
			if (empty($cleanTaskIds)) {
				return 1;
			}

			$idList = implode(',', $cleanTaskIds);

			$sql = "SELECT fk_element, SUM(element_duration) AS total_duration";
			$sql .= " FROM ".MAIN_DB_PREFIX."element_time";
			$sql .= " WHERE elementtype='task'";
			$sql .= " AND fk_element IN (".$idList.")";
			$sql .= " GROUP BY fk_element";

			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}

			$taskDurations = array();
			foreach ($cleanTaskIds as $taskId) {
				$taskDurations[$taskId] = 0;
			}

			while ($obj = $this->db->fetch_object($resql)) {
				$taskDurations[(int) $obj->fk_element] = (int) $obj->total_duration;
			}
			$this->db->free($resql);

			foreach ($taskDurations as $taskId => $durationSeconds) {
				// EN: Propagate consolidated duration into the project task record.
				// FR: Propage la durée consolidée dans l'enregistrement de la tâche projet.
				$sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."projet_task SET";
				$sqlUpdate .= " duration_effective=".(int) $durationSeconds;
				$sqlUpdate .= " WHERE rowid=".(int) $taskId;
				$sqlUpdate .= " AND entity IN (".getEntity('project').")";

				if (!$this->db->query($sqlUpdate)) {
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
		// EN: Ensure the fallback generation still respects allowed entities.
		// FR: Garantit que la génération de secours respecte les entités autorisées.
		$sql .= " AND entity IN (".getEntity('timesheetweek').")";
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
			//'task_progress' => ($obj->task_progress !== null ? (float) $obj->task_progress : null), // EN: Progress at 100% are not considered to not hide closed tasks // FR: Le sprogresdsion à 100% ne sont pas prises en compte pour ne pas masquer les tâches clôturées. 
			//'task_status' => ($obj->task_status !== null ? (int) $obj->task_status : null), // EN: All status are considered to not hide closed tasks // FR: Tous les Statuts sont pris en compte pour ne pas masquer les tâches clôturées.
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

		// FR: Conserve aussi une version HTML cliquable du lien.
		// EN: Keep a clickable HTML version of the link as well.
		$urlRaw = $url;
		$urlHtml = '<a href="'.dol_escape_htmltag($urlRaw).'">'.dol_escape_htmltag($urlRaw).'</a>';

		$employeeName = $employee ? $employee->getFullName($langs) : '';
		$validatorName = $validator ? $validator->getFullName($langs) : '';
		$actionUserName = $actionUser->getFullName($langs);

		// FR: Base des substitutions partagées entre notifications automatiques et métier.
		// EN: Base substitution array shared between automatic and business notifications.
		$baseSubstitutions = array(
		'__TIMESHEETWEEK_REF__' => $this->ref,
		'__TIMESHEETWEEK_WEEK__' => $this->week,
		'__TIMESHEETWEEK_YEAR__' => $this->year,
		'__TIMESHEETWEEK_URL__' => $urlHtml,
		'__TIMESHEETWEEK_URL_RAW__' => $urlRaw,
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
		$message = isset($options['message']) ? (string) $options['message'] : '';
		$htmlMessage = isset($options['message_html']) ? (string) $options['message_html'] : $message;
		$isHtml = !empty($options['ishtml']) ? 1 : 0;

		if (empty($options['message_html'])) {
			$htmlMessage = dol_nl2br(dol_escape_htmltag($message));
		} else {
			$htmlMessage = (string) $options['message_html'];
		}

		if (!empty($conf->global->MAIN_MAIL_USE_MULTI_PART) || $isHtml) {
			$isHtml = 1;
		}

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
			'message' => $message,
			'content' => $htmlMessage,
			'body' => $htmlMessage,
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
			'message_html' => $htmlMessage,
			'html' => $htmlMessage,
			'ishtml' => $isHtml,
			'msgishtml' => $isHtml,
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
					} elseif (strpos($lower, 'html') !== false) {
						if (strpos($lower, 'message') !== false && isset($payload['message_html'])) {
							$value = $payload['message_html'];
						} elseif (isset($payload['ishtml'])) {
							$value = $payload['ishtml'];
						}
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

	/**
	* EN: Provide the Dolibarr badge definition for a status (class, label, colors).
	* FR: Fournit la définition du badge Dolibarr pour un statut (classe, libellé, couleurs).
	*
	* @param int $status Timesheet status identifier / Identifiant du statut de la feuille
	* @param Translate|null $translator Optional translator to reuse / Traducteur optionnel à réutiliser
	* @return array<string,string> Badge definition data / Données de définition du badge
	*/
	public static function getStatusBadgeDefinition($status, $translator = null)
	{
		global $langs;

		// EN: Allow PDF generation to reuse its own Translate instance when provided.
		// FR: Permet à la génération PDF de réutiliser sa propre instance Translate lorsque fournie.
		$activeTranslator = $translator instanceof Translate ? $translator : $langs;

		if ($activeTranslator instanceof Translate) {
			if (method_exists($activeTranslator, 'loadLangs')) {
				$activeTranslator->loadLangs(array('timesheetweek@timesheetweek', 'other'));
			} else {
				$activeTranslator->load('timesheetweek@timesheetweek');
				$activeTranslator->load('other');
			}
		}

		$statusInfo = array(
			self::STATUS_DRAFT => array(
				'label' => $activeTranslator instanceof Translate ? $activeTranslator->trans('TimesheetWeekStatusDraft') : 'Draft',
				'picto' => 'statut0',
				'class' => 'badge badge-status badge-status0',
				'type' => 'status0',
				// EN: Provide RGB-friendly colors for PDF rendering.
				// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
				'background_color' => '#adb5bd',
				'text_color' => '#212529',
			),
			self::STATUS_SUBMITTED => array(
				'label' => $activeTranslator instanceof Translate ? $activeTranslator->trans('TimesheetWeekStatusSubmitted') : 'Submitted',
				'picto' => 'statut1',
				'class' => 'badge badge-status badge-status1',
				'type' => 'status1',
				// EN: Provide RGB-friendly colors for PDF rendering.
				// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
				'background_color' => '#0d6efd',
				'text_color' => '#ffffff',
			),
			self::STATUS_APPROVED => array(
				'label' => $activeTranslator instanceof Translate ? $activeTranslator->trans('TimesheetWeekStatusApproved') : 'Approved',
				'picto' => 'statut4',
				'class' => 'badge badge-status badge-status4',
				'type' => 'status4',
				// EN: Provide RGB-friendly colors for PDF rendering.
				// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
				'background_color' => '#198754',
				'text_color' => '#ffffff',
			),
			self::STATUS_SEALED => array(
				// EN: Align sealed badge with Dolibarr default visual identity.
				// FR: Aligne le badge du statut scellé sur l'identité visuelle Dolibarr par défaut.
				'label' => $activeTranslator instanceof Translate ? $activeTranslator->trans('TimesheetWeekStatusSealed') : 'Sealed',
				'picto' => 'statut6',
				'class' => 'badge badge-status badge-status6',
				'type' => 'status6',
				// EN: Provide RGB-friendly colors for PDF rendering.
				// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
				'background_color' => '#6f42c1',
				'text_color' => '#ffffff',
			),
			self::STATUS_REFUSED => array(
				// EN: Keep the refused badge matching Dolibarr styling guidelines.
				// FR: Maintient le badge du statut refusé conforme aux directives Dolibarr.
				'label' => $activeTranslator instanceof Translate ? $activeTranslator->trans('TimesheetWeekStatusRefused') : 'Refused',
				'picto' => 'statut8',
				'class' => 'badge badge-status badge-status8',
				'type' => 'status8',
				// EN: Provide RGB-friendly colors for PDF rendering.
				// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
				'background_color' => '#dc3545',
				'text_color' => '#ffffff',
			),
		);

		$defaultLabel = $activeTranslator instanceof Translate ? $activeTranslator->trans('Unknown') : 'Unknown';

		return $statusInfo[$status] ?? array(
			'label' => $defaultLabel,
			'picto' => 'statut0',
			'class' => 'badge badge-status badge-status0',
			'type' => 'status0',
			// EN: Provide RGB-friendly colors for PDF rendering.
			// FR: Fournit des couleurs compatibles RVB pour le rendu PDF.
			'background_color' => '#adb5bd',
			'text_color' => '#212529',
		);
	}

	public static function LibStatut($status, $mode = 0)
	{
		$info = self::getStatusBadgeDefinition($status);
		$label = dol_escape_htmltag($info['label']);

		if ((int) $mode === 5) {
			// EN: Build Dolibarr badge output to mirror native status rendering.
			// FR: Construit le badge Dolibarr pour reproduire le rendu natif des statuts.
			$badgeParams = array(
				'badgeParams' => array(
					'attr' => array(
						'aria-label' => $label,
					),
				),
			);
			return dolGetStatus(
				$info['label'],
				$info['label'],
				'',
				!empty($info['type']) ? $info['type'] : 'status0',
				5,
				'',
				$badgeParams
			);
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
