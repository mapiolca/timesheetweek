<?php
/*
 * EN: Actions class to expose TimesheetWeek hooks.
 * FR: Classe d'actions pour exposer les hooks de TimesheetWeek.
 */

// EN: Protect direct access.
// FR: Protéger contre les accès directs.
if (!defined('DOL_DOCUMENT_ROOT')) {
    die('Dolibarr environment not found');
}

/**
 * EN: Hook class for TimesheetWeek.
 * FR: Classe de hook pour TimesheetWeek.
 */
class ActionsTimesheetweek
{
    /** @var DoliDB */
    public $db;

    /**
     * EN: Constructor storing the database handler for later use.
     * FR: Constructeur stockant le gestionnaire de base de données pour un usage ultérieur.
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * EN: Provide Multicompany sharing options via hook.
     * FR: Fournir les options de partage Multisociété via le hook.
     *
     * @param array      $parameters Hook parameters
     * @param CommonObject|null $object  Related object
     * @param string     $action     Current action string
     * @param HookManager $hookmanager Hook manager instance
     * @return int
     */
    public function multicompanysharing($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        // EN: Abort when the module is disabled to avoid useless processing.
        // FR: Abandonner lorsque le module est désactivé pour éviter un traitement inutile.
        if (empty($conf->timesheetweek->enabled)) {
            return 0;
        }

        if (!function_exists('timesheetweek_build_multicompany_sharing_config')) {
            dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
        }

        $sharing = timesheetweek_build_multicompany_sharing_config();
        if (empty($sharing)) {
            return 0;
        }

        if (empty($hookmanager->resArray) || !is_array($hookmanager->resArray)) {
            // EN: Ensure the hook manager stores an array before merging definitions.
            // FR: S'assurer que le gestionnaire de hooks conserve un tableau avant de fusionner les définitions.
            $hookmanager->resArray = array();
        }

        // EN: Merge TimesheetWeek sharing options with existing ones.
        // FR: Fusionner les options de partage TimesheetWeek avec les options existantes.
        $hookmanager->resArray = array_merge($hookmanager->resArray, $sharing);

        return 1;
    }
}
