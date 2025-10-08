<?php
/**
 * Hook class for TimesheetWeek module.
 * Classe de hook pour le module TimesheetWeek.
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

        // EN: Stop early when the module is disabled to avoid useless processing.
        // FR: Stopper immédiatement lorsque le module est désactivé pour éviter un traitement inutile.
        if (empty($conf->timesheetweek->enabled)) {
            return 0;
        }

        // EN: Check write permissions to decide if the quick add entry must be shown.
        // FR: Vérifier les permissions d'écriture pour décider si l'entrée de création rapide doit être affichée.
        $hasWriteRight = !empty($user->rights->timesheetweek->write)
            || !empty($user->rights->timesheetweek->writeChild)
            || !empty($user->rights->timesheetweek->writeAll);
        if (!$hasWriteRight) {
            return 0;
        }

        // EN: Load module translations to expose localized labels.
        // FR: Charger les traductions du module pour exposer les libellés localisés.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        // EN: Provide the quick add entry with a Dolibarr-safe URL and translated metadata.
        // FR: Fournir l'entrée de création rapide avec une URL Dolibarr sécurisée et des métadonnées traduites.
        $this->results[] = array(
            'url' => dol_buildpath('/timesheetweek/timesheetweek_card.php', 1) . '?action=create',
            'title' => 'QuickCreateTimesheetWeek@timesheetweek',
            'name' => 'TimesheetWeek@timesheetweek',
            'picto' => 'bookcal',
            'activation' => 1,
            'position' => 100,
        );

        return 0;
    }
}
