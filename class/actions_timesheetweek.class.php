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

    /** @var array<string,bool> */
    protected static $nativeNotificationSetupSynced = array();

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
     * Return native CRUD triggers exposed to Dolibarr Notifications.
     *
     * @return array<int,string>
     */
    public static function getNativeNotificationTriggerCodes()
    {
        return array(
            'TIMESHEETWEEK_TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE',
            'TIMESHEETWEEK_TIMESHEETWEEK_DELETE',
        );
    }

    /**
     * Return native c_action_trigger rows required by Agenda and Notifications.
     *
     * @return array<int,array{elementtype:string,code:string,contexts:string,label:string,description:string,rang:int}>
     */
    public static function getNativeNotificationTriggerRows()
    {
        return array(
            array(
                'elementtype' => 'timesheetweek@timesheetweek',
                'code' => 'TIMESHEETWEEK_TIMESHEETWEEK_CREATE',
                'contexts' => 'agenda:notification',
                'label' => 'Create weekly timesheet',
                'description' => 'Executed when a weekly timesheet is created; the precise business context is carried by the object context',
                'rang' => 45000301,
            ),
            array(
                'elementtype' => 'timesheetweek@timesheetweek',
                'code' => 'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE',
                'contexts' => 'agenda:notification',
                'label' => 'Update weekly timesheet',
                'description' => 'Executed when a weekly timesheet is updated; status, seal and refusal details are carried by the object context',
                'rang' => 45000302,
            ),
            array(
                'elementtype' => 'timesheetweek@timesheetweek',
                'code' => 'TIMESHEETWEEK_TIMESHEETWEEK_DELETE',
                'contexts' => 'agenda:notification',
                'label' => 'Delete weekly timesheet',
                'description' => 'Executed when a weekly timesheet is deleted; the object context identifies the deleted sheet',
                'rang' => 45000303,
            ),
        );
    }

    /**
     * Return obsolete non-CRUD trigger codes no longer exposed to native features.
     *
     * @return array<int,string>
     */
    public static function getLegacyNotificationTriggerCodes()
    {
        return array(
            'TIMESHEETWEEK_SUBMIT',
            'TIMESHEETWEEK_APPROVE',
            'TIMESHEETWEEK_REFUSE',
            'TIMESHEETWEEK_SUBMITTED',
            'TIMESHEETWEEK_APPROVED',
            'TIMESHEETWEEK_REFUSED',
            'TSWK_CREATE',
            'TSWK_SUBMIT',
            'TSWK_REOPEN',
            'TSWK_APPROVE',
            'TSWK_SEAL',
            'TSWK_UNSEAL',
            'TSWK_REFUSE',
            'TSWK_DELETE',
        );
    }

    /**
     * Return router templates used by the native Notifications module.
     *
     * @return array<int,array{lang:string,label:string,position:int,topic:string,content:string}>
     */
    public static function getNativeNotificationRouterTemplates()
    {
        return array(
            array(
                'lang' => 'fr_FR',
                'label' => 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER',
                'position' => 200,
                'topic' => '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__',
                'content' => '__TIMESHEETWEEK_NOTIFICATION_BODY__',
            ),
            array(
                'lang' => 'en_US',
                'label' => 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER',
                'position' => 200,
                'topic' => '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__',
                'content' => '__TIMESHEETWEEK_NOTIFICATION_BODY__',
            ),
        );
    }

    /**
     * Ensure native Notification metadata exists before Dolibarr reads c_action_trigger.
     *
     * @param DoliDB $db     Database handler
     * @param int    $entity Current entity
     * @return int 1 on success, -1 on error
     */
    public static function ensureNativeNotificationSetup($db, $entity)
    {
        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }

        $cacheKey = (string) $entity;
        if (!empty(self::$nativeNotificationSetupSynced[$cacheKey])) {
            return 1;
        }

        $legacyCodes = self::getLegacyNotificationTriggerCodes();
        $legacySql = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
        $legacySql .= " WHERE code IN (".self::buildSqlStringList($db, $legacyCodes).")";
        $legacySql .= " AND elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek')";
        if (!$db->query($legacySql)) {
            return -1;
        }

        $crudCodes = self::getNativeNotificationTriggerCodes();
        $shortElementSql = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
        $shortElementSql .= " WHERE code IN (".self::buildSqlStringList($db, $crudCodes).")";
        $shortElementSql .= " AND elementtype = 'timesheetweek'";
        if (!$db->query($shortElementSql)) {
            return -1;
        }

        foreach (self::getNativeNotificationTriggerRows() as $row) {
            $result = self::upsertNativeNotificationTriggerRow($db, $row);
            if ($result < 0) {
                return -1;
            }
        }

        foreach (self::getNativeNotificationRouterTemplates() as $template) {
            $result = self::upsertNativeNotificationRouterTemplate($db, $template);
            if ($result < 0) {
                return -1;
            }
        }

        if (function_exists('getDolGlobalString') && function_exists('dolibarr_set_const') && getDolGlobalString('TIMESHEETWEEK_TIMESHEETWEEK_UPDATE_TEMPLATE', '') === '') {
            $result = dolibarr_set_const($db, 'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE_TEMPLATE', 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER', 'chaine', 0, '', $entity);
            if ($result < 0) {
                return -1;
            }
        }

        self::$nativeNotificationSetupSynced[$cacheKey] = true;

        return 1;
    }

    /**
     * Build a SQL string list from trusted values.
     *
     * @param DoliDB            $db     Database handler
     * @param array<int,string> $values Values to quote
     * @return string
     */
    protected static function buildSqlStringList($db, array $values)
    {
        $quoted = array();
        foreach ($values as $value) {
            $quoted[] = "'".$db->escape($value)."'";
        }

        return implode(', ', $quoted);
    }

    /**
     * Insert or update one native c_action_trigger row.
     *
     * @param DoliDB $db  Database handler
     * @param array{elementtype:string,code:string,contexts:string,label:string,description:string,rang:int} $row Trigger row
     * @return int 1 on success, -1 on error
     */
    protected static function upsertNativeNotificationTriggerRow($db, array $row)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_action_trigger";
        $sql .= " WHERE elementtype = '".$db->escape($row['elementtype'])."'";
        $sql .= " AND code = '".$db->escape($row['code'])."'";
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) {
            return -1;
        }

        $rowid = 0;
        $obj = $db->fetch_object($resql);
        if (is_object($obj)) {
            $rowid = (int) $obj->rowid;
        }
        $db->free($resql);

        if ($rowid > 0) {
            $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."c_action_trigger";
            $sqlUpdate .= " SET contexts = '".$db->escape($row['contexts'])."',";
            $sqlUpdate .= " label = '".$db->escape($row['label'])."',";
            $sqlUpdate .= " description = '".$db->escape($row['description'])."',";
            $sqlUpdate .= " rang = ".((int) $row['rang']);
            $sqlUpdate .= " WHERE rowid = ".$rowid;

            return $db->query($sqlUpdate) ? 1 : -1;
        }

        $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."c_action_trigger";
        $sqlInsert .= " (elementtype, code, contexts, label, description, rang)";
        $sqlInsert .= " VALUES (";
        $sqlInsert .= "'".$db->escape($row['elementtype'])."',";
        $sqlInsert .= "'".$db->escape($row['code'])."',";
        $sqlInsert .= "'".$db->escape($row['contexts'])."',";
        $sqlInsert .= "'".$db->escape($row['label'])."',";
        $sqlInsert .= "'".$db->escape($row['description'])."',";
        $sqlInsert .= ((int) $row['rang']);
        $sqlInsert .= ")";

        return $db->query($sqlInsert) ? 1 : -1;
    }

    /**
     * Insert or complete the native router email template.
     *
     * @param DoliDB $db       Database handler
     * @param array{lang:string,label:string,position:int,topic:string,content:string} $template Template data
     * @return int 1 on success, -1 on error
     */
    protected static function upsertNativeNotificationRouterTemplate($db, array $template)
    {
        $newType = 'timesheetweek@timesheetweek';
        $legacyType = 'timesheetweek';

        $sqlExistingNew = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_email_templates";
        $sqlExistingNew .= " WHERE module = 'timesheetweek'";
        $sqlExistingNew .= " AND type_template = '".$db->escape($newType)."'";
        $sqlExistingNew .= " AND lang = '".$db->escape($template['lang'])."'";
        $sqlExistingNew .= " AND label = '".$db->escape($template['label'])."'";
        $sqlExistingNew .= " LIMIT 1";
        $resqlExistingNew = $db->query($sqlExistingNew);
        if (!$resqlExistingNew) {
            return -1;
        }

        $newRowid = 0;
        $existingNew = $db->fetch_object($resqlExistingNew);
        if (is_object($existingNew)) {
            $newRowid = (int) $existingNew->rowid;
        }
        $db->free($resqlExistingNew);

        if ($newRowid <= 0) {
            $sqlExistingLegacy = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_email_templates";
            $sqlExistingLegacy .= " WHERE module = 'timesheetweek'";
            $sqlExistingLegacy .= " AND type_template = '".$db->escape($legacyType)."'";
            $sqlExistingLegacy .= " AND lang = '".$db->escape($template['lang'])."'";
            $sqlExistingLegacy .= " AND label = '".$db->escape($template['label'])."'";
            $sqlExistingLegacy .= " LIMIT 1";
            $resqlExistingLegacy = $db->query($sqlExistingLegacy);
            if (!$resqlExistingLegacy) {
                return -1;
            }

            $legacyRowid = 0;
            $existingLegacy = $db->fetch_object($resqlExistingLegacy);
            if (is_object($existingLegacy)) {
                $legacyRowid = (int) $existingLegacy->rowid;
            }
            $db->free($resqlExistingLegacy);

            if ($legacyRowid > 0) {
                $sqlMigrate = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
                $sqlMigrate .= " SET type_template = '".$db->escape($newType)."'";
                $sqlMigrate .= " WHERE rowid = ".$legacyRowid;
                if (!$db->query($sqlMigrate)) {
                    return -1;
                }
                $newRowid = $legacyRowid;
            }
        }

        if ($newRowid <= 0) {
            $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."c_email_templates";
            $sqlInsert .= " (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)";
            $sqlInsert .= " VALUES (";
            $sqlInsert .= "0,";
            $sqlInsert .= "'timesheetweek',";
            $sqlInsert .= "'".$db->escape($newType)."',";
            $sqlInsert .= "'".$db->escape($template['lang'])."',";
            $sqlInsert .= "0,";
            $sqlInsert .= "NULL,";
            $sqlInsert .= "NOW(),";
            $sqlInsert .= "'".$db->escape($template['label'])."',";
            $sqlInsert .= ((int) $template['position']).",";
            $sqlInsert .= "1,";
            $sqlInsert .= "'isModEnabled(\\\"timesheetweek\\\")',";
            $sqlInsert .= "0,";
            $sqlInsert .= "'".$db->escape($template['topic'])."',";
            $sqlInsert .= "'".$db->escape($template['content'])."'";
            $sqlInsert .= ")";
            if (!$db->query($sqlInsert)) {
                return -1;
            }
        }

        $sqlUpdateTopic = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
        $sqlUpdateTopic .= " SET topic = '".$db->escape($template['topic'])."'";
        $sqlUpdateTopic .= " WHERE module = 'timesheetweek'";
        $sqlUpdateTopic .= " AND type_template = '".$db->escape($newType)."'";
        $sqlUpdateTopic .= " AND lang = '".$db->escape($template['lang'])."'";
        $sqlUpdateTopic .= " AND label = '".$db->escape($template['label'])."'";
        $sqlUpdateTopic .= " AND (topic IS NULL OR topic = '')";
        if (!$db->query($sqlUpdateTopic)) {
            return -1;
        }

        $sqlUpdateContent = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
        $sqlUpdateContent .= " SET content = '".$db->escape($template['content'])."'";
        $sqlUpdateContent .= " WHERE module = 'timesheetweek'";
        $sqlUpdateContent .= " AND type_template = '".$db->escape($newType)."'";
        $sqlUpdateContent .= " AND lang = '".$db->escape($template['lang'])."'";
        $sqlUpdateContent .= " AND label = '".$db->escape($template['label'])."'";
        $sqlUpdateContent .= " AND (content IS NULL OR content = '')";
        if (!$db->query($sqlUpdateContent)) {
            return -1;
        }

        return 1;
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
        global $conf;

        $entity = (!empty($conf->entity) ? (int) $conf->entity : 1);
        if (self::ensureNativeNotificationSetup($this->db, $entity) < 0) {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
        }

        if (!is_array($this->results)) {
            $this->results = array();
        }

        $current = array();
        if (!empty($this->results['arrayofnotifsupported']) && is_array($this->results['arrayofnotifsupported'])) {
            $current = $this->results['arrayofnotifsupported'];
        }

        $this->results['arrayofnotifsupported'] = array_values(array_unique(array_merge($current, self::getNativeNotificationTriggerCodes())));

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
