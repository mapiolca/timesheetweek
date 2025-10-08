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

        $hasWriteRight = $user->hasRight('timesheetweek', 'timesheetweek', 'write') || $user->hasRight('timesheetweek', 'timesheetweek', 'writeChild') || $user->hasRight('timesheetweek', 'timesheetweek', 'writeAll');

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
}
