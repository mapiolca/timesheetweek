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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

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
	 * EN: Generate the PDF file using the shared summary renderer.
	 * FR: Génère le fichier PDF en utilisant le moteur de synthèse mutualisé.
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
			$outputlangs->loadLangs(array('main', 'timesheetweek@timesheetweek'));
		} else {
			$outputlangs->load('main');
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
		$objectRef = dol_sanitizeFileName($object->ref);
		$relativePath = $object->element.'/'.$objectRef;
		$targetDir = rtrim($baseOutput, '/').'/'.$relativePath;
		if (dol_mkdir($targetDir) < 0) {
			$this->error = $outputlangs->trans('ErrorCanNotCreateDir', $targetDir);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Check the current user's permissions for the summary generator.
		// FR: Vérifie les permissions de l'utilisateur courant pour le générateur de synthèse.
		$permReadOwn = $user->hasRight('timesheetweek', 'read');
		$permReadChild = $user->hasRight('timesheetweek', 'readChild');
		$permReadAll = $user->hasRight('timesheetweek', 'readAll');

		// EN: Run the shared summary generator with the single timesheet identifier.
		// FR: Lance le générateur de synthèse partagé avec l'identifiant unique de la feuille.
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
			// EN: Relay generator errors to the caller for troubleshooting.
			// FR: Relaye les erreurs du générateur à l'appelant pour faciliter le diagnostic.
			$messages = !empty($result['errors']) ? (array) $result['errors'] : array($outputlangs->trans('ErrorFailToCreateFile'));
			$this->errors = $messages;
			$this->error = implode(', ', $messages);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		$sourceFile = $result['file'];
		$filename = basename($sourceFile);
		$destinationFile = $targetDir.'/'.$filename;

		// EN: Copy the generated PDF into the Dolibarr-managed directory.
		// FR: Copie le PDF généré dans le répertoire géré par Dolibarr.
		if (dol_copy($sourceFile, $destinationFile, 0, 1) <= 0) {
			$this->error = $outputlangs->trans('ErrorFailToCopyFile', $sourceFile, $destinationFile);
			dol_syslog(__METHOD__.' failed: '.$this->error, LOG_ERR);
			return -1;
		}

		// EN: Expose the resulting document metadata to Dolibarr callers.
		// FR: Expose les métadonnées du document résultant aux appelants Dolibarr.
		$this->result = array(
			'fullpath' => $destinationFile,
			'filename' => $filename,
			'relativepath' => $relativePath.'/'.$filename,
			'warnings' => !empty($result['warnings']) ? $result['warnings'] : array()
		);

		return 1;
	}
}
