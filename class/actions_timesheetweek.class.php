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

    public const NATIVE_PICTO = 'fa-calendar-check';

    public const NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE = 'timesheetweek@timesheetweek';

    public const NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE = 'timesheetweek_send';

    public const NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL = 'Notification TimesheetWeek';

    public const NATIVE_NOTIFICATION_ROUTER_TEMPLATE_BODY = '<div>__TIMESHEETWEEK_NOTIFICATION_BODY__</div>';

    /** @var array<string,bool> */
    protected static $nativeNotificationSetupSynced = array();

    /** @var DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array */
    public $errors = array();

    /** @var array */
    public $warnings = array();

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
     * Return business events exposed by TimesheetWeek, following the Diffusion module pattern.
     *
     * @return array<string,array<string,int|string>>
     */
    public static function getBusinessEventsDefinition()
    {
        $timesheetweek = array(
            'contexts' => 'agenda:notification',
            'notification_elementtype' => 'timesheetweek@timesheetweek',
            'agenda_elementtype' => 'timesheetweek@timesheetweek',
        );

        return array(
            'TIMESHEETWEEK_CREATE' => array_merge(array('label' => 'Create weekly timesheet', 'description' => 'Executed when a weekly timesheet is created.', 'rang' => 45000301), $timesheetweek),
            'TIMESHEETWEEK_SUBMIT' => array_merge(array('label' => 'Submit weekly timesheet', 'description' => 'Executed when a weekly timesheet is submitted for approval.', 'rang' => 45000302), $timesheetweek),
            'TIMESHEETWEEK_APPROVE' => array_merge(array('label' => 'Approve weekly timesheet', 'description' => 'Executed when a weekly timesheet is approved.', 'rang' => 45000303), $timesheetweek),
            'TIMESHEETWEEK_REFUSE' => array_merge(array('label' => 'Refuse weekly timesheet', 'description' => 'Executed when a weekly timesheet is refused.', 'rang' => 45000304), $timesheetweek),
            'TIMESHEETWEEK_SETDRAFT' => array_merge(array('label' => 'Revert weekly timesheet to draft', 'description' => 'Executed when a weekly timesheet is reverted to draft.', 'rang' => 45000305), $timesheetweek),
            'TIMESHEETWEEK_SEAL' => array_merge(array('label' => 'Seal weekly timesheet', 'description' => 'Executed when a weekly timesheet is sealed.', 'rang' => 45000306), $timesheetweek),
            'TIMESHEETWEEK_UNSEAL' => array_merge(array('label' => 'Unseal weekly timesheet', 'description' => 'Executed when a weekly timesheet is unsealed.', 'rang' => 45000307), $timesheetweek),
            'TIMESHEETWEEK_DELETE' => array_merge(array('label' => 'Delete weekly timesheet', 'description' => 'Executed when a weekly timesheet is deleted.', 'rang' => 45000308), $timesheetweek),
            'TIMESHEETWEEK_MODIFY' => array_merge($timesheetweek, array('label' => 'Modify weekly timesheet', 'description' => 'Executed when a weekly timesheet is modified without a dedicated workflow transition.', 'rang' => 45000309, 'contexts' => 'agenda')),
        );
    }

    /**
     * Return one business event definition.
     *
     * @param string $code Business trigger code
     * @return array<string,int|string>
     */
    public static function getBusinessEventDefinition($code)
    {
        $events = self::getBusinessEventsDefinition();
        return !empty($events[$code]) ? $events[$code] : array();
    }

    /**
     * Return business event codes supported by native notifications.
     *
     * @return array<int,string>
     */
    public static function getNotificationEventCodes()
    {
        return array_values(array_diff(array_keys(self::getBusinessEventsDefinition()), self::getExcludedNotificationEventCodes()));
    }

    /**
     * Return business event codes intentionally hidden from native notifications.
     *
     * @return array<int,string>
     */
    public static function getExcludedNotificationEventCodes()
    {
        return array(
            'TIMESHEETWEEK_MODIFY',
        );
    }

    /**
     * Return native triggers exposed to Dolibarr Agenda and Notifications.
     *
     * @return array<int,string>
     */
    public static function getNativeNotificationTriggerCodes()
    {
        return array_keys(self::getBusinessEventsDefinition());
    }

    /**
     * Return native CREATE/MODIFY/DELETE triggers.
     *
     * @return array<int,string>
     */
    public static function getNativeNotificationCrudTriggerCodes()
    {
        return array(
            'TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_MODIFY',
            'TIMESHEETWEEK_DELETE',
        );
    }

    /**
     * Return native workflow triggers exposed to Dolibarr Notifications.
     *
     * @return array<int,string>
     */
    public static function getNativeNotificationWorkflowTriggerCodes()
    {
        return array(
            'TIMESHEETWEEK_SUBMIT',
            'TIMESHEETWEEK_APPROVE',
            'TIMESHEETWEEK_REFUSE',
            'TIMESHEETWEEK_SETDRAFT',
            'TIMESHEETWEEK_SEAL',
            'TIMESHEETWEEK_UNSEAL',
        );
    }

    /**
     * Return the native picto used by generic Dolibarr screens.
     *
     * @return string
     */
    public static function getNativePicto()
    {
        return self::NATIVE_PICTO;
    }

    /**
     * Return native c_action_trigger rows required by Agenda and Notifications.
     *
     * @return array<int,array{elementtype:string,code:string,contexts:string,label:string,description:string,rang:int}>
     */
    public static function getNativeNotificationTriggerRows()
    {
        $rows = array();
        foreach (self::getBusinessEventsDefinition() as $code => $event) {
            $rows[] = array(
                'elementtype' => (string) $event['notification_elementtype'],
                'code' => (string) $code,
                'contexts' => (string) $event['contexts'],
                'label' => (string) $event['label'],
                'description' => (string) $event['description'],
                'rang' => (int) $event['rang'],
            );
        }

        return $rows;
    }

    /**
     * Return obsolete historical trigger codes no longer exposed to native features.
     *
     * @return array<int,string>
     */
    public static function getLegacyNotificationTriggerCodes()
    {
        return array(
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
     * Return the recent CRUD trigger codes that must be migrated to business codes.
     *
     * @return array<string,string>
     */
    public static function getRecentCrudTriggerCodeMap()
    {
        return array(
            'TIMESHEETWEEK_TIMESHEETWEEK_CREATE' => 'TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE' => 'TIMESHEETWEEK_MODIFY',
            'TIMESHEETWEEK_TIMESHEETWEEK_DELETE' => 'TIMESHEETWEEK_DELETE',
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
                'label' => self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL,
                'position' => 200,
                'topic' => '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__',
                'content' => self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_BODY,
            ),
            array(
                'lang' => 'en_US',
                'label' => self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL,
                'position' => 200,
                'topic' => '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__',
                'content' => self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_BODY,
            ),
        );
    }

    /**
     * Return additional visible native email templates for TimesheetWeek business notifications.
     *
     * The single "Notification TimesheetWeek" router template injects the
     * step-specific subject and body through substitutions, so the module must
     * not seed one visible template per workflow event.
     *
     * @return array<int,array{lang:string,label:string,position:int,topic:string,content:string}>
     */
    public static function getNativeNotificationEmailTemplates()
    {
        return array();
    }

    /**
     * Return native Notification template constants and their expected email-template type.
     *
     * @return array<string,array{type:string}>
     */
    public static function getNativeNotificationTemplateConstantDefinitions()
    {
        return array(
            'TIMESHEETWEEK_CREATE_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_MODIFY_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_DELETE_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_SUBMIT_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_APPROVE_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_REFUSE_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_SETDRAFT_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_SEAL_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
            ),
            'TIMESHEETWEEK_UNSEAL_TEMPLATE' => array(
                'type' => 'emailtemplate:'.self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE,
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

        if (self::migrateRecentCrudNotificationSetup($db, $entity) < 0) {
            return -1;
        }

        $legacyCodes = self::getLegacyNotificationTriggerCodes();
        $legacySql = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
        $legacySql .= " WHERE code IN (".self::buildSqlStringList($db, $legacyCodes).")";
        $legacySql .= " AND elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek')";
        if (!$db->query($legacySql)) {
            return -1;
        }

        $nativeCodes = self::getNativeNotificationTriggerCodes();
        $shortElementSql = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
        $shortElementSql .= " WHERE code IN (".self::buildSqlStringList($db, $nativeCodes).")";
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

        foreach (self::getNativeNotificationEmailTemplates() as $template) {
            $result = self::upsertNativeNotificationEmailTemplate($db, $template);
            if ($result < 0) {
                return -1;
            }
        }

        if (self::ensureNativeNotificationTemplateConstants($db, $entity) < 0) {
            return -1;
        }

        if (self::cleanupObsoleteNotificationMirrors($db) < 0) {
            return -1;
        }

        if (self::copyNativeNotificationTemplatesToObjectType($db) < 0) {
            return -1;
        }

        self::$nativeNotificationSetupSynced[$cacheKey] = true;

        return 1;
    }

    /**
     * Migrate recent CRUD notification metadata to the business-event names used by this module.
     *
     * @param DoliDB $db     Database handler
     * @param int    $entity Current entity
     * @return int 1 on success, -1 on error
     */
    protected static function migrateRecentCrudNotificationSetup($db, $entity)
    {
        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }

        foreach (self::getRecentCrudTriggerCodeMap() as $oldCode => $newCode) {
            $sqlTarget = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_action_trigger";
            $sqlTarget .= " WHERE code = '".$db->escape($newCode)."'";
            $sqlTarget .= " AND elementtype = 'timesheetweek@timesheetweek'";
            $sqlTarget .= " LIMIT 1";
            $resqlTarget = $db->query($sqlTarget);
            if (!$resqlTarget) {
                return -1;
            }
            $targetExists = (bool) $db->num_rows($resqlTarget);
            $db->free($resqlTarget);

            if ($targetExists) {
                $sqlDeleteOld = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
                $sqlDeleteOld .= " WHERE code = '".$db->escape($oldCode)."'";
                $sqlDeleteOld .= " AND elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek')";
                if (!$db->query($sqlDeleteOld)) {
                    return -1;
                }
            } else {
                $sqlUpdateExternal = "UPDATE ".MAIN_DB_PREFIX."c_action_trigger";
                $sqlUpdateExternal .= " SET code = '".$db->escape($newCode)."'";
                $sqlUpdateExternal .= " WHERE code = '".$db->escape($oldCode)."'";
                $sqlUpdateExternal .= " AND elementtype = 'timesheetweek@timesheetweek'";
                if (!$db->query($sqlUpdateExternal)) {
                    return -1;
                }

                $sqlDeleteShort = "DELETE FROM ".MAIN_DB_PREFIX."c_action_trigger";
                $sqlDeleteShort .= " WHERE code = '".$db->escape($oldCode)."'";
                $sqlDeleteShort .= " AND elementtype = 'timesheetweek'";
                if (!$db->query($sqlDeleteShort)) {
                    return -1;
                }
            }
        }

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
        $newType = self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE;

        $sqlExistingNew = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_email_templates";
        $sqlExistingNew .= " WHERE entity = 0";
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
            $sqlInsert .= "'isModEnabled(\\\"timesheetweek\\\") && isModEnabled(\\\"notification\\\")',";
            $sqlInsert .= "0,";
            $sqlInsert .= "'".$db->escape($template['topic'])."',";
            $sqlInsert .= "'".$db->escape($template['content'])."'";
            $sqlInsert .= ")";
            if (!$db->query($sqlInsert)) {
                return -1;
            }
        }

        if ($newRowid > 0) {
            $sqlNormalize = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
            $sqlNormalize .= " SET module = 'timesheetweek',";
            $sqlNormalize .= " type_template = '".$db->escape($newType)."',";
            $sqlNormalize .= " position = ".((int) $template['position']).",";
            $sqlNormalize .= " active = 1,";
            $sqlNormalize .= " enabled = 'isModEnabled(\\\"timesheetweek\\\") && isModEnabled(\\\"notification\\\")',";
            $sqlNormalize .= " joinfiles = 0";
            $sqlNormalize .= " WHERE rowid = ".$newRowid;
            if (!$db->query($sqlNormalize)) {
                return -1;
            }

            $sqlUpdateOldDefaultBody = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
            $sqlUpdateOldDefaultBody .= " SET content = '".$db->escape(self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_BODY)."'";
            $sqlUpdateOldDefaultBody .= " WHERE rowid = ".$newRowid;
            $sqlUpdateOldDefaultBody .= " AND content = '__TIMESHEETWEEK_NOTIFICATION_BODY__'";
            if (!$db->query($sqlUpdateOldDefaultBody)) {
                return -1;
            }
        }

        return 1;
    }

    /**
     * Insert or complete one visible TimesheetWeek notification email template.
     *
     * @param DoliDB $db Database handler
     * @param array{lang:string,label:string,position:int,topic:string,content:string} $template Template data
     * @return int 1 on success, -1 on error
     */
    protected static function upsertNativeNotificationEmailTemplate($db, array $template)
    {
        $type = self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE;
        $enabled = 'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")';

        $where = "entity = 0";
        $where .= " AND lang = '".$db->escape($template['lang'])."'";
        $where .= " AND label = '".$db->escape($template['label'])."'";

        $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."c_email_templates";
        $sqlInsert .= " (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)";
        $sqlInsert .= " SELECT 0,'timesheetweek','".$db->escape($type)."','".$db->escape($template['lang'])."',0,NULL,NOW(),";
        $sqlInsert .= "'".$db->escape($template['label'])."',".((int) $template['position']).",1,'".$db->escape($enabled)."',0,";
        $sqlInsert .= "'".$db->escape($template['topic'])."','".$db->escape($template['content'])."'";
        $sqlInsert .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."c_email_templates WHERE ".$where.")";
        if (!$db->query($sqlInsert)) {
            return -1;
        }

        $sqlNormalize = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
        $sqlNormalize .= " SET module = 'timesheetweek',";
        $sqlNormalize .= " type_template = '".$db->escape($type)."',";
        $sqlNormalize .= " position = ".((int) $template['position']).",";
        $sqlNormalize .= " active = 1,";
        $sqlNormalize .= " enabled = '".$db->escape($enabled)."',";
        $sqlNormalize .= " joinfiles = 0";
        $sqlNormalize .= " WHERE ".$where;
        if (!$db->query($sqlNormalize)) {
            return -1;
        }

        return 1;
    }

    /**
     * Ensure native Notification selector constants exist without selecting a template.
     *
     * @param DoliDB $db     Database handler
     * @param int    $entity Current entity
     * @return int 1 on success, -1 on error
     */
    public static function ensureNativeNotificationTemplateConstants($db, $entity)
    {
		dol_include_once('/core/lib/admin.lib.php');
		if (!function_exists('dolibarr_set_const')) {
			return -1;
		}

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }

        $templateConstants = self::getNativeNotificationTemplateConstantDefinitions();
        if (empty($templateConstants)) {
            return 1;
        }

        foreach ($templateConstants as $templateConstant => $definition) {
            $sqlSelect = "SELECT rowid FROM ".MAIN_DB_PREFIX."const";
            $sqlSelect .= " WHERE name = '".$db->escape($templateConstant)."'";
            $sqlSelect .= " AND entity = ".$entity;
            $sqlSelect .= " LIMIT 1";
            $resqlSelect = $db->query($sqlSelect);
            if (!$resqlSelect) {
                return -1;
            }

            $exists = (bool) $db->num_rows($resqlSelect);
            $db->free($resqlSelect);

            if (!$exists) {
                $result = dolibarr_set_const($db, $templateConstant, '', $definition['type'], 0, '', $entity);
                if ($result < 0) {
                    return -1;
                }
            }
        }

        $sqlUpdateTypes = "UPDATE ".MAIN_DB_PREFIX."const";
        $sqlUpdateTypes .= " SET type = 'emailtemplate:".$db->escape(self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE)."'";
        $sqlUpdateTypes .= " WHERE name IN (".self::buildSqlStringList($db, array_keys($templateConstants)).")";
        $sqlUpdateTypes .= " AND entity = ".$entity;
        if (!$db->query($sqlUpdateTypes)) {
            return -1;
        }

        return 1;
    }

    /**
     * Sync the selected visible Notification template into the object type consumed by Notify::send().
     *
     * @param DoliDB $db Database handler
     * @param string $notifcode Notification trigger code
     * @return int<-1,1>
     */
    public static function syncSelectedNotificationEmailTemplateMirror($db, $notifcode)
    {
        if (empty($notifcode) || !in_array($notifcode, self::getNativeNotificationTriggerCodes(), true)) {
            return 1;
        }
        if (!function_exists('getDolGlobalString')) {
            return 1;
        }

        $label = getDolGlobalString($notifcode.'_TEMPLATE');
        if ($label === '') {
            return 1;
        }

        $visibleLabel = self::getVisibleNotificationEmailTemplateLabel($label);
        $result = self::syncNotificationEmailTemplateMirror($db, $visibleLabel);
        if ($result < 0) {
            return $result;
        }
        if ($result === 0) {
            return 1;
        }

        global $conf;
        if (is_object($conf) && !empty($conf->global) && is_object($conf->global)) {
            $conf->global->{$notifcode.'_TEMPLATE'} = self::getNotificationEmailTemplateMirrorLabel($visibleLabel);
        }

        return 1;
    }

    /**
     * Kept for upgrade compatibility; create the hidden mirror required by Notify::send().
     *
     * @param DoliDB $db Database handler
     * @return int 1 on success, -1 on error
     */
    protected static function copyNativeNotificationTemplatesToObjectType($db)
    {
        return self::syncNotificationEmailTemplateMirror($db, self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL) < 0 ? -1 : 1;
    }

    /**
     * Remove hidden mirrors generated for obsolete per-event templates.
     *
     * @param DoliDB $db Database handler
     * @return int 1 on success, -1 on error
     */
    protected static function cleanupObsoleteNotificationMirrors($db)
    {
        $mirrorLabel = self::getNotificationEmailTemplateMirrorLabel(self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL);

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."c_email_templates";
        $sql .= " WHERE module = 'timesheetweek'";
        $sql .= " AND type_template = '".$db->escape(self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE)."'";
        $sql .= " AND (label IS NULL OR label <> '".$db->escape($mirrorLabel)."' OR COALESCE(lang, '') NOT IN ('fr_FR', 'en_US'))";

        if (!$db->query($sql)) {
            return -1;
        }

        $sqlDuplicates = "DELETE duplicate_template FROM ".MAIN_DB_PREFIX."c_email_templates AS duplicate_template";
        $sqlDuplicates .= " INNER JOIN ".MAIN_DB_PREFIX."c_email_templates AS kept_template";
        $sqlDuplicates .= " ON kept_template.module = 'timesheetweek'";
        $sqlDuplicates .= " AND kept_template.type_template = '".$db->escape(self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE)."'";
        $sqlDuplicates .= " AND kept_template.label = '".$db->escape($mirrorLabel)."'";
        $sqlDuplicates .= " AND kept_template.entity = duplicate_template.entity";
        $sqlDuplicates .= " AND kept_template.lang = duplicate_template.lang";
        $sqlDuplicates .= " AND kept_template.rowid < duplicate_template.rowid";
        $sqlDuplicates .= " WHERE duplicate_template.module = 'timesheetweek'";
        $sqlDuplicates .= " AND duplicate_template.type_template = '".$db->escape(self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE)."'";
        $sqlDuplicates .= " AND duplicate_template.label = '".$db->escape($mirrorLabel)."'";
        $sqlDuplicates .= " AND duplicate_template.lang IN ('fr_FR', 'en_US')";

        return $db->query($sqlDuplicates) ? 1 : -1;
    }

    /**
     * Copy visible TimesheetWeek notification templates into the native object email type.
     *
     * @param DoliDB $db Database handler
     * @param string $label Optional visible template label to sync
     * @return int<-1,1> 1 if synced, 0 if no source template found, -1 on error
     */
    protected static function syncNotificationEmailTemplateMirror($db, $label = '')
    {
        if ($label === '') {
            $label = self::NATIVE_NOTIFICATION_ROUTER_TEMPLATE_LABEL;
        }

        $sql = "SELECT rowid, entity, module, type_template, lang, private, fk_user, label, position, defaultfortype, enabled, active,";
        $sql .= " email_from, email_to, email_tocc, email_tobcc, topic, joinfiles, content, content_lines";
        $sql .= " FROM ".MAIN_DB_PREFIX."c_email_templates";
        $sql .= " WHERE module = 'timesheetweek'";
        $sql .= " AND type_template = '".$db->escape(self::NATIVE_NOTIFICATION_VISIBLE_TEMPLATE_TYPE)."'";
        $sql .= " AND label = '".$db->escape($label)."'";
        $sql .= " AND active = 1";
        $sql .= " ORDER BY entity, lang, position, rowid";

        $resql = $db->query($sql);
        if (!$resql) {
            return -1;
        }

        $nbsource = 0;
        while ($obj = $db->fetch_object($resql)) {
            $nbsource++;
            $mirrorLabel = self::getNotificationEmailTemplateMirrorLabel((string) $obj->label);

            $result = self::syncNotificationEmailTemplateMirrorRow($db, $obj, $mirrorLabel, $obj->lang);
            if ($result < 0) {
                $db->free($resql);
                return -1;
            }
        }

        $db->free($resql);

        return $nbsource > 0 ? 1 : 0;
    }

    /**
     * Copy or update one visible template row into the hidden object email type.
     *
     * @param DoliDB   $db          Database handler
     * @param stdClass $obj         Source template row
     * @param string   $mirrorLabel Hidden mirror label
     * @param mixed    $mirrorLang  Hidden mirror language
     * @return int<-1,1>
     */
    protected static function syncNotificationEmailTemplateMirrorRow($db, $obj, $mirrorLabel, $mirrorLang)
    {
        $uniqueWhere = self::getEmailTemplateUniqueWhere($db, (int) $obj->entity, $mirrorLabel, $mirrorLang);
        $mirrorWhere = "module = 'timesheetweek'";
        $mirrorWhere .= " AND type_template = '".$db->escape(self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE)."'";
        $mirrorWhere .= " AND entity = ".((int) $obj->entity);
        $mirrorWhere .= " AND label ".self::sqlNullableCondition($db, $mirrorLabel);
        $mirrorWhere .= " AND lang ".self::sqlNullableCondition($db, $mirrorLang);

        $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."c_email_templates";
        $sqlInsert .= " (entity, module, type_template, lang, private, fk_user, datec, label, position, defaultfortype, enabled, active,";
        $sqlInsert .= " email_from, email_to, email_tocc, email_tobcc, topic, joinfiles, content, content_lines)";
        $sqlInsert .= " SELECT ".((int) $obj->entity).", 'timesheetweek', '".$db->escape(self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE)."',";
        $sqlInsert .= " ".self::sqlNullableString($db, $mirrorLang).", ".((int) $obj->private).", ".self::sqlNullableInteger($obj->fk_user).", NOW(),";
        $sqlInsert .= " ".self::sqlNullableString($db, $mirrorLabel).", ".self::sqlNullableInteger($obj->position).", ".((int) $obj->defaultfortype).",";
        $sqlInsert .= " ".self::sqlNullableString($db, $obj->enabled).", ".((int) $obj->active).",";
        $sqlInsert .= " ".self::sqlNullableString($db, $obj->email_from).", ".self::sqlNullableString($db, $obj->email_to).",";
        $sqlInsert .= " ".self::sqlNullableString($db, $obj->email_tocc).", ".self::sqlNullableString($db, $obj->email_tobcc).",";
        $sqlInsert .= " ".self::sqlNullableString($db, $obj->topic).", ".self::sqlNullableString($db, $obj->joinfiles).",";
        $sqlInsert .= " ".self::sqlNullableString($db, $obj->content).", ".self::sqlNullableString($db, $obj->content_lines);
        $sqlInsert .= " FROM DUAL";
        $sqlInsert .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."c_email_templates WHERE ".$uniqueWhere.")";
        if (!$db->query($sqlInsert)) {
            return -1;
        }

        $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."c_email_templates";
        $sqlUpdate .= " SET private = ".((int) $obj->private);
        $sqlUpdate .= ", fk_user = ".self::sqlNullableInteger($obj->fk_user);
        $sqlUpdate .= ", position = ".self::sqlNullableInteger($obj->position);
        $sqlUpdate .= ", defaultfortype = ".((int) $obj->defaultfortype);
        $sqlUpdate .= ", enabled = ".self::sqlNullableString($db, $obj->enabled);
        $sqlUpdate .= ", active = ".((int) $obj->active);
        $sqlUpdate .= ", email_from = ".self::sqlNullableString($db, $obj->email_from);
        $sqlUpdate .= ", email_to = ".self::sqlNullableString($db, $obj->email_to);
        $sqlUpdate .= ", email_tocc = ".self::sqlNullableString($db, $obj->email_tocc);
        $sqlUpdate .= ", email_tobcc = ".self::sqlNullableString($db, $obj->email_tobcc);
        $sqlUpdate .= ", topic = ".self::sqlNullableString($db, $obj->topic);
        $sqlUpdate .= ", joinfiles = ".self::sqlNullableString($db, $obj->joinfiles);
        $sqlUpdate .= ", content = ".self::sqlNullableString($db, $obj->content);
        $sqlUpdate .= ", content_lines = ".self::sqlNullableString($db, $obj->content_lines);
        $sqlUpdate .= " WHERE ".$mirrorWhere;

        return $db->query($sqlUpdate) ? 1 : -1;
    }

    /**
     * Return the hidden label used for the object-type mirror.
     *
     * @param string $label Visible template label
     * @return string
     */
    protected static function getNotificationEmailTemplateMirrorLabel($label)
    {
        $suffix = ' ['.self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE.']';
        if (substr($label, -strlen($suffix)) === $suffix) {
            return $label;
        }

        $maxLabelSize = 180 - strlen($suffix);
        if ($maxLabelSize < 1) {
            $maxLabelSize = 1;
        }

        return substr((string) $label, 0, $maxLabelSize).$suffix;
    }

    /**
     * Return the visible label when a runtime value already points to a mirror.
     *
     * @param string $label Current template label
     * @return string
     */
    protected static function getVisibleNotificationEmailTemplateLabel($label)
    {
        $suffix = ' ['.self::NATIVE_NOTIFICATION_MIRROR_TEMPLATE_TYPE.']';
        if (substr($label, -strlen($suffix)) === $suffix) {
            return substr($label, 0, -strlen($suffix));
        }

        return $label;
    }

    /**
     * Build the native unique key condition for c_email_templates.
     *
     * @param DoliDB $db Database handler
     * @param int    $entity Entity id
     * @param mixed  $label Template label
     * @param mixed  $lang Template language
     * @return string SQL WHERE fragment
     */
    protected static function getEmailTemplateUniqueWhere($db, $entity, $label, $lang)
    {
        $where = "entity = ".((int) $entity);
        $where .= " AND label ".self::sqlNullableCondition($db, $label);
        $where .= " AND lang ".self::sqlNullableCondition($db, $lang);

        return $where;
    }

    /**
     * Return a SQL nullable string value.
     *
     * @param DoliDB $db Database handler
     * @param mixed  $value Value
     * @return string
     */
    protected static function sqlNullableString($db, $value)
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".$db->escape((string) $value)."'";
    }

    /**
     * Return a SQL nullable integer value.
     *
     * @param mixed $value Value
     * @return string
     */
    protected static function sqlNullableInteger($value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        return (string) ((int) $value);
    }

    /**
     * Return a SQL nullable comparison.
     *
     * @param DoliDB $db Database handler
     * @param mixed  $value Value
     * @return string
     */
    protected static function sqlNullableCondition($db, $value)
    {
        if ($value === null) {
            return 'IS NULL';
        }

        return "= '".$db->escape((string) $value)."'";
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
            'picto' => self::getNativePicto(),
            // EN: Activate the quick add entry only when the module is enabled and rights allow it.
            // FR: Activer l'entrée de création rapide uniquement lorsque le module est actif et que les droits le permettent.
            'activation' => isModEnabled('timesheetweek') && ($hasWriteRight),
            'position' => 100,
        );

        return 0;
    }

    /**
     * Print last TimesheetWeek rows on the native user bank card.
     *
     * @param array<string,mixed> $parameters Hook parameters
     * @param object             $object Current user object displayed by user/bank.php
     * @param string             $action Current action
     * @param HookManager        $hookmanager Hook manager
     * @return int
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $this->results = array();
        $this->resprints = '';

        if (!isModEnabled('timesheetweek')) {
            return 0;
        }

        if (!$this->isUserBankPage()) {
            return 0;
        }

        $targetUserId = 0;
        if (is_object($object) && !empty($object->id)) {
            $targetUserId = (int) $object->id;
        } elseif (!empty($parameters['userid'])) {
            $targetUserId = (int) $parameters['userid'];
        }

        if ($targetUserId <= 0 || !$this->canReadTimesheetWeekForUser($targetUserId)) {
            return 0;
        }

        $langs->loadLangs(array('timesheetweek@timesheetweek'));
        print $this->buildUserBankLastTimesheetWeeksBlock($targetUserId);

        return 0;
    }

    /**
     * Check whether the current hook call comes from the user bank card.
     *
     * @return bool
     */
    protected function isUserBankPage()
    {
        $candidates = array();
        foreach (array('PHP_SELF', 'SCRIPT_NAME') as $key) {
            if (!empty($_SERVER[$key])) {
                $candidates[] = str_replace('\\', '/', (string) $_SERVER[$key]);
            }
        }

        foreach ($candidates as $candidate) {
            if (preg_match('~/user/bank\.php$~', $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether current user may see timesheets for the target employee.
     *
     * @param int $targetUserId Target employee identifier
     * @return bool
     */
    protected function canReadTimesheetWeekForUser($targetUserId)
    {
        global $user;

        if (!is_object($user)) {
            return false;
        }

        if (!empty($user->admin)) {
            return true;
        }

        dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

        if (function_exists('tw_can_act_on_user')) {
            return tw_can_act_on_user(
                (int) $targetUserId,
                $user->hasRight('timesheetweek', 'read'),
                $user->hasRight('timesheetweek', 'readChild'),
                $user->hasRight('timesheetweek', 'readAll'),
                $user
            );
        }

        if ($user->hasRight('timesheetweek', 'readAll')) {
            return true;
        }

        if ($user->hasRight('timesheetweek', 'read') && (int) $targetUserId === (int) $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Build native table for the last TimesheetWeek rows of a user.
     *
     * @param int $targetUserId Target employee identifier
     * @return string HTML output
     */
    protected function buildUserBankLastTimesheetWeeksBlock($targetUserId)
    {
        global $conf, $langs;

        dol_include_once('/timesheetweek/class/timesheetweek.class.php');

        $max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);
        if ($max <= 0) {
            $max = 5;
        }

        $entityList = getEntity('timesheetweek');
        if ($entityList === '') {
            $entityList = (string) ((int) $conf->entity);
        }

        $sqlWhere = " WHERE t.fk_user = ".((int) $targetUserId);
        $sqlWhere .= " AND t.entity IN (".$this->db->sanitize($entityList).")";

        $totalTimesheetWeeks = 0;
        $sqlCount = "SELECT COUNT(t.rowid) AS total";
        $sqlCount .= " FROM ".MAIN_DB_PREFIX."timesheet_week AS t";
        $sqlCount .= $sqlWhere;
        $resqlCount = $this->db->query($sqlCount);
        if ($resqlCount) {
            $objCount = $this->db->fetch_object($resqlCount);
            if (is_object($objCount)) {
                $totalTimesheetWeeks = (int) $objCount->total;
            }
            $this->db->free($resqlCount);
        } else {
            dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_ERR);
        }

        $sql = "SELECT t.rowid, t.ref, t.entity, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours, t.meal_count, t.tms";
        $sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week AS t";
        $sql .= $sqlWhere;
        $sql .= " ORDER BY t.year DESC, t.week DESC, t.tms DESC";
        $sql .= $this->db->plimit($max);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__.': '.$this->error, LOG_ERR);
            return '';
        }

        $allTimesheetsUrl = dol_buildpath('/timesheetweek/timesheetweek_list.php', 1).'?search_user='.((int) $targetUserId);

        $out = '<div id="timesheetweek-userbank-last-sheets-block">';
        $out .= '<div class="div-table-responsive-no-min">';
        $out .= '<table class="noborder centpercent">';
        $out .= '<tr class="liste_titre">';
        $out .= '<td>'.$langs->trans('TimesheetWeekLastSheetsTitle').'</td>';
        $out .= '<td class="center">'.$langs->trans('Week').'</td>';
        $out .= '<td class="right">'.$langs->trans('TotalHours').'</td>';
        $out .= '<td class="right">'.$langs->trans('Overtime').'</td>';
        $out .= '<td class="right">'.$langs->trans('MealCount').'</td>';
        $out .= '<td class="right"><a class="notasortlink" href="'.dol_escape_htmltag($allTimesheetsUrl).'">'.$langs->trans('TimesheetWeekAllSheets').'<span class="badge marginleftonlyshort">'.$totalTimesheetWeeks.'</span></a></td>';
        $out .= '</tr>';

        $num = $this->db->num_rows($resql);
        if ($num <= 0) {
            $out .= '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
        } else {
            while (is_object($obj = $this->db->fetch_object($resql))) {
                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall">'.$this->getTimesheetWeekNomUrlFromRow($obj).'</td>';
                $out .= '<td class="center nowraponall">'.sprintf('%02d / %d', (int) $obj->week, (int) $obj->year).'</td>';
                $out .= '<td class="right nowraponall">'.$this->formatTimesheetWeekHours((float) $obj->total_hours).'</td>';
                $out .= '<td class="right nowraponall">'.$this->formatTimesheetWeekHours((float) $obj->overtime_hours).'</td>';
                $out .= '<td class="right nowraponall">'.((int) $obj->meal_count).'</td>';
                $out .= '<td class="right nowraponall">'.$this->getTimesheetWeekStatusLabel((int) $obj->status).'</td>';
                $out .= '</tr>';
            }
        }
        $this->db->free($resql);

        $out .= '</table>';
        $out .= '</div>';
        $out .= '</div>';

        return $out;
    }

    /**
     * Return a TimesheetWeek native object link from a database row.
     *
     * @param object $row Database row
     * @return string HTML link
     */
    protected function getTimesheetWeekNomUrlFromRow($row)
    {
        if (class_exists('TimesheetWeek')) {
            $timesheet = new TimesheetWeek($this->db);
            $timesheet->id = (int) $row->rowid;
            $timesheet->ref = (string) $row->ref;
            $timesheet->entity = (int) $row->entity;
            $timesheet->fk_user = (int) $row->fk_user;
            $timesheet->year = (int) $row->year;
            $timesheet->week = (int) $row->week;
            $timesheet->status = (int) $row->status;
            $timesheet->total_hours = (float) $row->total_hours;
            $timesheet->overtime_hours = (float) $row->overtime_hours;
            $timesheet->meal_count = (int) $row->meal_count;

            return $timesheet->getNomUrl(1);
        }

        $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.(int) $row->rowid;
        return '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag((string) $row->ref).'</a>';
    }

    /**
     * Format decimal hours into HH:MM like the TimesheetWeek list.
     *
     * @param float $hours Decimal hours
     * @return string
     */
    protected function formatTimesheetWeekHours($hours)
    {
        $hours = max(0.0, (float) $hours);
        $hh = (int) floor($hours);
        $mm = (int) round(($hours - $hh) * 60);
        if ($mm === 60) {
            $hh++;
            $mm = 0;
        }

        return str_pad((string) $hh, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $mm, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Return native TimesheetWeek status badge.
     *
     * @param int $status TimesheetWeek status
     * @return string
     */
    protected function getTimesheetWeekStatusLabel($status)
    {
        if (class_exists('TimesheetWeek')) {
            return TimesheetWeek::LibStatut((int) $status, 5);
        }

        return dol_escape_htmltag((string) $status);
    }

    /**
     * Expose TimesheetWeek triggers to the native Notification module.
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

        $moduleEnabled = 0;
        if (function_exists('isModEnabled')) {
            $moduleEnabled = isModEnabled('timesheetweek') ? 1 : 0;
        } elseif (!empty($conf->timesheetweek->enabled)) {
            $moduleEnabled = 1;
        }

        $notificationElementAliases = array('timesheetweek', 'timesheetweek@timesheetweek');
        foreach ($notificationElementAliases as $alias) {
            if (empty($conf->{$alias}) || !is_object($conf->{$alias})) {
                $conf->{$alias} = new stdClass();
            }
            $conf->{$alias}->enabled = $moduleEnabled;
            $conf->{$alias}->name = 'TimesheetWeek';
            $conf->{$alias}->picto = self::getNativePicto();
        }

        if (!empty($parameters['notifcode'])) {
            $result = self::syncSelectedNotificationEmailTemplateMirror($this->db, (string) $parameters['notifcode']);
            if ($result < 0) {
                $this->error = $this->db->lasterror();
                $this->errors[] = $this->error;
                return -1;
            }
        }

        $events = self::getNotificationEventCodes();
        if (!empty($hookmanager->resArray['arrayofnotifsupported']) && is_array($hookmanager->resArray['arrayofnotifsupported'])) {
            $events = array_merge($hookmanager->resArray['arrayofnotifsupported'], $events);
        }

        $events = array_values(array_diff(array_unique($events), self::getExcludedNotificationEventCodes()));
        $this->results = array('arrayofnotifsupported' => $events);

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

		$elementType = '';
		foreach (array('elementType', 'elementtype', 'element_type', 'element') as $parameterKey) {
			if (!empty($parameters[$parameterKey])) {
				$elementType = (string) $parameters[$parameterKey];
				break;
			}
		}

		$elementType = strtolower(trim($elementType));
		if ($elementType === '') {
			return 0;
		}

		if (!in_array($elementType, array('timesheetweek', 'timesheetweek@timesheetweek'), true)) {
			return 0;
		}

		$dirOutput = '';
		$dirTemp = '';
		$entity = (!empty($conf) && is_object($conf) && !empty($conf->entity)) ? (int) $conf->entity : 1;
		if (!empty($conf) && is_object($conf) && !empty($conf->timesheetweek->multidir_output[$entity])) {
			$dirOutput = $conf->timesheetweek->multidir_output[$entity];
		} elseif (!empty($conf) && is_object($conf) && !empty($conf->timesheetweek->dir_output)) {
			$dirOutput = $conf->timesheetweek->dir_output;
		}
		if (!empty($conf) && is_object($conf) && !empty($conf->timesheetweek->multidir_temp[$entity])) {
			$dirTemp = $conf->timesheetweek->multidir_temp[$entity];
		} elseif (!empty($conf) && is_object($conf) && !empty($conf->timesheetweek->dir_temp)) {
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
            'picto' => self::getNativePicto(),
            'dir_output' => $dirOutput,
            'dir_temp' => $dirTemp,
            'parent_element' => '',
        ));

        return 0;
    }

    /**
     * Add TimesheetWeek entries to the native email templates element list.
     *
     * @param array        $parameters  Hook parameters
     * @param object       $object      Current object
     * @param string       $action      Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function emailElementlist($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $langs->load('timesheetweek@timesheetweek');

        $this->results = array(
            'timesheetweek' => img_picto('', self::getNativePicto(), 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans('TimesheetWeekNativeEmailTemplates')),
            'timesheetweek@timesheetweek' => img_picto('', self::getNativePicto(), 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans('TimesheetWeekNativeEmailTemplates')),
        );

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
