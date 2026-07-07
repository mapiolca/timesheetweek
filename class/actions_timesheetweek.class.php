<?php
/**
 * Hook class for TimesheetWeek module.
 * Classe de hook pour le module TimesheetWeek.
 */
class ActionsTimesheetweek
{
    // EN: Identifier used by the Multicompany sharing payload.
    // FR: Identifiant utilisé par la charge utile de partage Multicompany.
    public const MULTICOMPANY_SHARING_ROOT_KEY = 'timesheetweek';

    /** @var DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array */
    public $errors = array();

    /** @var string */
    public $resprints = '';

    /**
     * Hook results container.
     * Tableau des résultats du hook.
     *
     * @var array
     */
    public $results = array();

    /**
     * Constructor.
     * Constructeur.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        // EN: Store the database handler for potential future hooks.
        // FR: Conserver le gestionnaire de base de données pour de futurs hooks.
        $this->db = $db;
    }

    /**
     * Inject TimesheetWeek entry into the quick add dropdown menu.
     * Injecter une entrée TimesheetWeek dans le menu déroulant de création rapide.
     *
     * @param array          $parameters Hook parameters / Paramètres du hook
     * @param CommonObject   $object     Current object / Objet courant
     * @param string         $action     Current action / Action courante
     * @param HookManager    $hookmanager Hook manager instance / Gestionnaire de hooks
     *
     * @return int Status / Statut
     */
    public function menuDropdownQuickaddItems($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        // EN: Reset hook containers before populating the dropdown.
        // FR: Réinitialiser les conteneurs du hook avant de remplir le menu déroulant.
        $this->results = array();
        $this->resprints = '';

        // EN: Load module translations to expose localized labels.
        // FR: Charger les traductions du module pour exposer les libellés localisés.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        // EN: Evaluate user permissions to control quick creation visibility.
        // FR: Évaluer les droits de l'utilisateur pour contrôler la visibilité de la création rapide.

        $hasWriteRight = $user->hasRight('timesheetweek', 'write') || $user->hasRight('timesheetweek', 'writeChild') || $user->hasRight('timesheetweek', 'writeAll');

        // EN: Inject the quick creation entry with translated metadata.
        // FR: Injecter l'entrée de création rapide avec des métadonnées traduites.
        $this->results[0] = array(
            // EN: Link directly to the custom module path exposed by Dolibarr.
            // FR: Lien direct vers le chemin custom du module exposé par Dolibarr.
            'url' => '/custom/timesheetweek/timesheetweek_card.php?action=create',
            'title' => 'QuickCreateTimesheetWeek@timesheetweek',
            'name' => 'Timesheet@timesheetweek',
            'picto' => 'bookcal',
            // EN: Activate the quick add entry only when the module is enabled and rights allow it.
            // FR: Activer l'entrée de création rapide uniquement lorsque le module est actif et que les droits le permettent.
            'activation' => isModEnabled('timesheetweek') && ($hasWriteRight),
            'position' => 100,
        );

        return 0;
    }

    /**
     * Expose TimesheetWeek CRUD triggers to the native Notification module.
     *
     * @param array        $parameters  Hook parameters
     * @param object       $object      Current object
     * @param string       $action      Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function notifsupported($parameters, &$object, &$action, $hookmanager)
    {
        if (!is_array($this->results)) {
            $this->results = array();
        }

        $current = array();
        if (!empty($this->results['arrayofnotifsupported']) && is_array($this->results['arrayofnotifsupported'])) {
            $current = $this->results['arrayofnotifsupported'];
        }

        $this->results['arrayofnotifsupported'] = array_values(array_unique(array_merge($current, array(
            'TIMESHEETWEEK_TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE',
            'TIMESHEETWEEK_TIMESHEETWEEK_DELETE',
        ))));

        return 0;
    }

    /**
     * Make the custom object resolvable by Dolibarr generic object helpers.
     *
     * @param array        $parameters  Hook parameters
     * @param object       $object      Current object
     * @param string       $action      Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function getElementProperties($parameters, &$object = null, &$action = '', $hookmanager = null)
    {
        global $conf;

        $elementType = isset($parameters['elementType']) ? (string) $parameters['elementType'] : '';
        if (!in_array($elementType, array('timesheetweek', 'timesheetweek@timesheetweek'), true)) {
            return 0;
        }

        $dirOutput = '';
        $dirTemp = '';
        if (!empty($conf->timesheetweek->multidir_output[$conf->entity])) {
            $dirOutput = $conf->timesheetweek->multidir_output[$conf->entity];
        } elseif (!empty($conf->timesheetweek->dir_output)) {
            $dirOutput = $conf->timesheetweek->dir_output;
        }
        if (!empty($conf->timesheetweek->multidir_temp[$conf->entity])) {
            $dirTemp = $conf->timesheetweek->multidir_temp[$conf->entity];
        } elseif (!empty($conf->timesheetweek->dir_temp)) {
            $dirTemp = $conf->timesheetweek->dir_temp;
        }

        $this->results = array_replace(is_array($this->results) ? $this->results : array(), array(
            'module' => 'timesheetweek',
            'element' => 'timesheetweek',
            'table_element' => 'timesheet_week',
            'subelement' => 'timesheetweek',
            'classpath' => 'timesheetweek/class',
            'classfile' => 'timesheetweek',
            'classname' => 'TimesheetWeek',
            'dir_output' => $dirOutput,
            'dir_temp' => $dirTemp,
            'parent_element' => '',
        ));

        return 0;
    }

    /**
     * Replace the generic object picto in the Dolibarr banner with the generated PDF preview.
     *
     * @param array        $parameters  Hook parameters
     * @param object       $object      Current object
     * @param string       $action      Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function formDolBanner($parameters, &$object, &$action, $hookmanager)
    {
        if (!is_object($object) || empty($object->id) || empty($object->element) || (string) $object->element !== 'timesheetweek') {
            return 0;
        }

        $previewHtml = $this->buildTimesheetWeekBannerDocumentPreview($object);
        if ($previewHtml === '') {
            return 0;
        }

        if (array_key_exists('morehtmlleft', $parameters)) {
            $parameters['morehtmlleft'] = $previewHtml;
        }

        return 0;
    }

    /**
     * Adjust native document access for TimesheetWeek granular read rights.
     *
     * @param array        $parameters  Hook parameters
     * @param object       $object      Current object
     * @param string       $action      Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function checkSecureAccess($parameters, &$object, &$action, $hookmanager)
    {
        $modulepart = isset($parameters['modulepart']) ? (string) $parameters['modulepart'] : '';
        if ($modulepart !== 'timesheetweek') {
            return 0;
        }
        if (!$this->loadTimesheetWeekDocumentHelpers()) {
            return 0;
        }

        $fuser = (!empty($parameters['fuser']) && is_object($parameters['fuser'])) ? $parameters['fuser'] : null;
        $originalFile = isset($parameters['original_file']) ? str_replace('\\', '/', (string) $parameters['original_file']) : '';
        $entity = isset($parameters['entity']) ? (int) $parameters['entity'] : 0;
        if (!is_object($fuser) || !method_exists($fuser, 'hasRight') || $originalFile === '') {
            return 0;
        }

        $ref = $this->extractTimesheetWeekRefFromDocumentPath($originalFile, $entity);
        if ($ref === '') {
            return 0;
        }

        $timesheet = new TimesheetWeek($this->db);
        if ($timesheet->fetch(null, $ref) <= 0) {
            return 0;
        }

        $expectedDir = rtrim(timesheetweekGetDocumentDir($timesheet), '/').'/';
        if (strpos($originalFile, $expectedDir) !== 0) {
            return 0;
        }

        $canRead = !empty($fuser->admin) || tw_can_act_on_user(
            (int) $timesheet->fk_user,
            $fuser->hasRight('timesheetweek', 'read'),
            $fuser->hasRight('timesheetweek', 'readChild'),
            $fuser->hasRight('timesheetweek', 'readAll'),
            $fuser
        );

        if (!$canRead) {
            return 0;
        }

        $this->results = array(
            'accessallowed' => 1,
            'original_file' => $originalFile,
            'sqlprotectagainstexternals' => '',
        );

        return 1;
    }

    /**
     * Load helpers required by document preview and access hooks.
     *
     * @return bool
     */
    private function loadTimesheetWeekDocumentHelpers()
    {
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
        dol_include_once('/timesheetweek/class/timesheetweek.class.php');
        dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

        return class_exists('TimesheetWeek')
            && function_exists('timesheetweekGetDocumentBaseDir')
            && function_exists('timesheetweekGetDocumentDir')
            && function_exists('timesheetweekGetDocumentModulePart')
            && function_exists('timesheetweekGetDocumentRelativeDir')
            && function_exists('tw_can_act_on_user');
    }

    /**
     * Build the native banner PDF thumbnail HTML for a TimesheetWeek object.
     *
     * @param object $object TimesheetWeek object
     * @return string
     */
    private function buildTimesheetWeekBannerDocumentPreview($object)
    {
        global $conf;

        if (!$this->loadTimesheetWeekDocumentHelpers() || empty($object->ref)) {
            return '';
        }

        $relativeFile = $this->resolveTimesheetWeekMainDocumentRelativePath($object);
        if ($relativeFile === '') {
            return '';
        }

        $baseDir = rtrim(timesheetweekGetDocumentBaseDir($object), '/');
        $pdfFile = $baseDir.'/'.$relativeFile;
        if (!dol_is_file($pdfFile)) {
            return '';
        }

        $previewFile = $pdfFile.'_preview.png';
        if (
            (!dol_is_file($previewFile) || dol_filemtime($previewFile) < dol_filemtime($pdfFile))
            && class_exists('Imagick')
            && !getDolGlobalString('MAIN_DISABLE_PDF_THUMBS')
        ) {
            $result = dol_convert_file($pdfFile, 'png', $previewFile, '0');
            if ($result < 0) {
                return '';
            }
        }

        if (!dol_is_file($previewFile)) {
            return '';
        }

        $entity = !empty($object->entity) ? (int) $object->entity : (!empty($conf->entity) ? (int) $conf->entity : 1);
        $height = !empty($conf->dol_optimize_smallscreen) ? 60 : 80;
        $previewRelativeFile = $relativeFile.'_preview.png';
        $previewUrl = DOL_URL_ROOT.'/document.php?modulepart='.urlencode(timesheetweekGetDocumentModulePart()).'&attachment=0&entity='.$entity.'&file='.urlencode($previewRelativeFile).'&cache='.urlencode((string) dol_filemtime($previewFile));

        $html = '<div class="floatleft inline-block valignmiddle divphotoref">';
        $html .= '<div class="photoref">';
        $html .= '<img height="'.((int) $height).'" class="photo photowithborder" src="'.dol_escape_htmltag($previewUrl).'" alt="">';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Resolve the relative path of the main TimesheetWeek PDF.
     *
     * @param object $object TimesheetWeek object
     * @return string
     */
    private function resolveTimesheetWeekMainDocumentRelativePath($object)
    {
        $relativeFile = '';
        if (!empty($object->last_main_doc)) {
            $candidate = str_replace('\\', '/', (string) $object->last_main_doc);
            $candidate = ltrim($candidate, '/');
            if (strpos($candidate, 'timesheetweek/') === 0) {
                $relativeFile = $candidate;
            } elseif (basename($candidate) !== '') {
                $relativeFile = timesheetweekGetDocumentRelativeDir($object).'/'.basename($candidate);
            }
        }

        if ($relativeFile === '') {
            $ref = dol_sanitizeFileName((string) $object->ref);
            if ($ref !== '') {
                $relativeFile = timesheetweekGetDocumentRelativeDir($object).'/'.$ref.'.pdf';
            }
        }

        return $relativeFile;
    }

    /**
     * Extract the TimesheetWeek reference from a resolved document path.
     *
     * @param string $absoluteFile Absolute file path
     * @param int    $entity       Entity id
     * @return string
     */
    private function extractTimesheetWeekRefFromDocumentPath($absoluteFile, $entity)
    {
        global $conf;

        $baseDir = '';
        if ($entity > 0 && !empty($conf->timesheetweek->multidir_output[$entity])) {
            $baseDir = $conf->timesheetweek->multidir_output[$entity];
        } elseif (!empty($conf->timesheetweek->dir_output)) {
            $baseDir = $conf->timesheetweek->dir_output;
        }

        $baseDir = str_replace('\\', '/', rtrim((string) $baseDir, '/'));
        if ($baseDir === '' || strpos($absoluteFile, $baseDir.'/') !== 0) {
            return '';
        }

        $relativeFile = ltrim(substr($absoluteFile, strlen($baseDir)), '/');
        if (!preg_match('#^timesheetweek/([^/]+)/#', $relativeFile, $matches)) {
            return '';
        }

        return (string) $matches[1];
    }

    /**
     * Build the Multicompany sharing payload for the module.
     * Construire la charge utile de partage Multicompany pour le module.
     *
     * @return array
     */
    public static function getMulticompanySharingDefinition()
    {
        global $conf;

        // EN: Prepare the payload describing both the element and numbering sharing options.
        // FR: Préparer la charge utile décrivant les options de partage des éléments et de la numérotation.
        return array(
            self::MULTICOMPANY_SHARING_ROOT_KEY => array(
                'sharingelements' => array(
                    'timesheetweek' => array(
                        'type' => 'element',
                        'icon' => 'calendar',
                        'lang' => 'timesheetweek@timesheetweek',
                        'tooltip' => 'ShareTimesheetWeekTooltip',
                        'enable' => '!empty($conf->timesheetweek->enabled)',
                        'input' => array(
                            'global' => array(
                                'showhide' => true,
                                'hide' => true,
                                'del' => true,
                            ),
                        ),
                    ),
                // EN: Expose the numbering share inside the dedicated document numbering section.
                // FR: Exposer le partage de numérotation dans la section dédiée aux numérotations de documents.
                    'timesheetweeknumbering' => array(
                        'type' => 'objectnumber',
                        'icon' => 'cogs',
                        'lang' => 'timesheetweek@timesheetweek',
                        'tooltip' => 'ShareTimesheetWeekNumberingTooltip',
                        //'mandatory' => 'timesheetweek',
                        'enable' => '!empty($conf->timesheetweek->enabled)',
                        'input' => array(
                            'global' => array(
                                'showhide' => true,
                                'hide' => true,
                                'del' => true,
                            ),
                            /*'timesheetweek' => array(
                                'showhide' => true,
                                'hide' => true,
                                'del' => true,
                            ),*/
                        ),
                    ),
                ),
                'sharingmodulename' => array(
                    'timesheetweek' => 'timesheetweek',
                    'timesheetweeknumbering' => 'timesheetweek',
                ),
            ),
        );
    }

    /**
     * Prepare and store multicompany sharing configuration.
     * Préparer et stocker la configuration de partage multicompany.
     *
     * @return void
     */
    private function registerMulticompanySharingDefinition()
    {
        global $langs;

        // EN: Safeguard the results container to merge data without overwriting existing hooks.
        // FR: Sécuriser le conteneur de résultats pour fusionner les données sans écraser les hooks existants.
        if (!is_array($this->results)) {
            $this->results = array();
        }

        // EN: Ensure translations are available for the multicompany labels.
        // FR: S'assurer que les traductions sont disponibles pour les libellés multicompany.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        // EN: Merge the static definition with any pre-existing sharing data.
        // FR: Fusionner la définition statique avec les données de partage déjà présentes.
        $this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
    }

    /**
     * Provide multicompany sharing options through the dedicated hook.
     * Fournir les options de partage multicompany via le hook dédié.
     */
    public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
    {
        // EN: Register the sharing definition for the multicompany extension.
        // FR: Enregistrer la définition de partage pour l'extension multicompany.
        $this->registerMulticompanySharingDefinition();

        return 0;
    }

    /**
     * Alias hook to support alternate multicompany triggers.
     * Hook alias pour supporter des déclencheurs multicompany alternatifs.
     */
    public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
    {
        // EN: Delegate to the primary multicompany sharing registration.
        // FR: Déléguer à l'enregistrement principal du partage multicompany.
        $this->registerMulticompanySharingDefinition();

        return 0;
    }

    /**
     * Additional alias covering broader multicompany sharing requests.
     * Alias supplémentaire couvrant les requêtes de partage multicompany plus larges.
     */
    public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
    {
        // EN: Reuse the shared definition to keep behaviour consistent across hooks.
        // FR: Réutiliser la définition partagée pour conserver un comportement cohérent entre les hooks.
        $this->registerMulticompanySharingDefinition();

        return 0;
    }
}
