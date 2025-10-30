<?php
/*
* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
* \file        core/modules/timesheetweek/doc/pdf_standard_timesheetweek.modules.php
* \ingroup     timesheetweek
* \brief       PDF model that delegates rendering to the TimesheetWeek summary generator
*/

// EN: Load the abstract parent and the PDF helper to reuse the modern summary renderer.
// FR: Charge la classe parente et l'assistant PDF pour réutiliser le moteur de synthèse moderne.
dol_include_once('/timesheetweek/core/modules/timesheetweek/modules_timesheetweek.php');
dol_include_once('/timesheetweek/lib/timesheetweek_pdf.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
* EN: Standard PDF model for weekly timesheets relying on the shared summary generator.
* FR: Modèle PDF standard des feuilles hebdomadaires s'appuyant sur le générateur de synthèse mutualisé.
*/
class pdf_standard_timesheetweek extends ModelePDFTimesheetWeek
{
	/**
	* @var DoliDB Database handler / Gestionnaire de base de données
	*/
	public $db;

	/**
	* @var string Model identifier / Identifiant du modèle
	*/
	public $name = 'standard';

	/**
	* @var string Model description / Description du modèle
	*/
	public $description;

	/**
	* @var string Document type / Type de document
	*/
	public $type = 'pdf';

	/**
	* @var array<int,int> Minimum PHP version / Version minimale de PHP
	*/
	public $phpmin = array(7, 0);

	/**
	* @var string Dolibarr compatibility flag / Indicateur de compatibilité Dolibarr
	*/
	public $version = 'dolibarr';

	/**
	* @var int Automatically mark generated file as main doc / Marquer automatiquement le fichier généré comme document principal
	*/
	public $update_main_doc_field = 1;

	/**
	* @var Societe Issuer company / Société émettrice
	*/
	public $emetteur;

	/**
	* Constructor.
	* Constructeur.
	*
	* @param DoliDB $db Database handler / Gestionnaire de base de données
	*/
	public function __construct($db)
	{
		global $langs, $mysoc;

		// EN: Keep the database handler available for later operations.
		// FR: Conserve le gestionnaire de base pour les opérations ultérieures.
		$this->db = $db;

		// EN: Load the common dictionaries so the description stays translated.
		// FR: Charge les dictionnaires communs afin que la description reste traduite.
		if (method_exists($langs, 'loadLangs')) {
			$langs->loadLangs(array('main', 'companies', 'timesheetweek@timesheetweek'));
		} else {
			$langs->load('main');
			$langs->load('companies');
			$langs->load('timesheetweek@timesheetweek');
		}

		// EN: Expose the Dolibarr label used in the model selector.
		// FR: Expose le libellé Dolibarr utilisé dans le sélecteur de modèles.
		$this->description = $langs->trans('DocumentModelStandardPDF');

		// EN: Keep a reference on the issuer to let Dolibarr reuse company defaults.
		// FR: Conserve une référence vers l'émetteur pour que Dolibarr réutilise les paramètres société.
		$this->emetteur = $mysoc;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	* EN: Generate the PDF on disk using the shared summary renderer.
	* FR: Génère le PDF sur disque en utilisant le moteur de synthèse mutualisé.
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

		// EN: Use provided translations or fallback to the global handler.
		// FR: Utilise les traductions fournies ou retombe sur le gestionnaire global.
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		$this->error = '';
		$this->errors = array();

		// EN: Abort generation when the timesheet is not loaded properly.
		// FR: Abandonne la génération lorsque la feuille n'est pas correctement chargée.
		if (empty($object) || empty($object->id)) {
			$this->error = $outputlangs->trans('ErrorRecordNotFound');
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Detect the entity-specific output directory with Multicompany support.
		// FR: Détecte le répertoire de sortie spécifique à l'entité avec gestion Multicompany.
		$entityId = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$baseOutput = '';
		if (!empty($conf->timesheetweek->multidir_output[$entityId] ?? null)) {
			$baseOutput = $conf->timesheetweek->multidir_output[$entityId];
		} elseif (!empty($conf->timesheetweek->dir_output)) {
			$baseOutput = $conf->timesheetweek->dir_output;
		} else {
			$baseOutput = DOL_DATA_ROOT.'/timesheetweek';
		}

		// EN: Build the object-centric directory to follow Dolibarr storage conventions.
		// FR: Construit le répertoire centré objet pour suivre les conventions de stockage Dolibarr.
		$objectRef = dol_sanitizeFileName($object->ref);
		$relativePath = $object->element.'/'.$objectRef;
		$targetDir = rtrim($baseOutput, '/').'/'.$relativePath;
		if (dol_mkdir($targetDir) < 0) {
			$this->error = $outputlangs->trans('ErrorCanNotCreateDir', $targetDir);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Compute permissions for the summary generator to honour visibility rules.
		// FR: Calcule les permissions pour que le générateur respecte les règles de visibilité.
		$permReadOwn = $user->hasRight('timesheetweek', 'read');
		$permReadChild = $user->hasRight('timesheetweek', 'readChild');
		$permReadAll = $user->hasRight('timesheetweek', 'readAll');

		// EN: Request the summary generation with the current timesheet identifier only.
		// FR: Demande la génération de synthèse avec uniquement l'identifiant de la feuille courante.
		$result = tw_generate_summary_pdf(
			$this->db,
			$conf,
			$outputlangs,
			$user,
			array($object->id),
			$permReadOwn,
			$permReadChild,
			$permReadAll
		);

		if (empty($result['success'])) {
			// EN: Propagate any error message returned by the generator to the caller.
			// FR: Propage tout message d'erreur renvoyé par le générateur à l'appelant.
			$messages = !empty($result['errors']) ? (array) $result['errors'] : array($outputlangs->trans('ErrorCanNotCreateDir', $targetDir));
			$this->errors = $messages;
			$this->error = implode(', ', $messages);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		$sourceFile = $result['file'];
		$filename = basename($sourceFile);
		$destinationFile = $targetDir.'/'.$filename;

		// EN: Copy the freshly generated PDF into the Dolibarr-managed directory.
		// FR: Copie le PDF fraîchement généré dans le répertoire géré par Dolibarr.
		if (dol_copy($sourceFile, $destinationFile, 0, 1) <= 0) {
			$this->error = $outputlangs->trans('ErrorFailToCopyFile', $sourceFile, $destinationFile);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Remember the resulting document path so Dolibarr can link it to the card.
		// FR: Mémorise le chemin du document résultant pour que Dolibarr puisse le lier à la fiche.
		$this->result = array(
			'fullpath' => $destinationFile,
			'filename' => $filename,
			'relativepath' => $relativePath.'/'.$filename,
			'warnings' => !empty($result['warnings']) ? $result['warnings'] : array()
		);

		return 1;
	}
}
