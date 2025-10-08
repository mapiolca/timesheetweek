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
        global $langs, $user;

        // EN: Reset hook containers before populating the dropdown.
        // FR: Réinitialiser les conteneurs du hook avant de remplir le menu déroulant.
        $this->results = array();
        $this->resprints = '';

        // EN: Load module translations to expose localized labels.
        // FR: Charger les traductions du module pour exposer les libellés localisés.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        // EN: Evaluate user permissions to control quick creation visibility.
        // FR: Évaluer les droits de l'utilisateur pour contrôler la visibilité de la création rapide.
        $hasWriteRight = !empty($user->rights->timesheetweek->write) || !empty($user->rights->timesheetweek->writeChild) || !empty($user->rights->timesheetweek->writeAll);
        if (!$hasWriteRight && method_exists($user, 'hasRight')) {
            // EN: Consolidate Dolibarr helper checks when available.
            // FR: Consolider les vérifications via les helpers Dolibarr lorsqu'ils sont disponibles.
            $hasWriteRight = $user->hasRight('timesheetweek', 'write') || $user->hasRight('timesheetweek', 'writeChild') || $user->hasRight('timesheetweek', 'writeAll');
        }

        // EN: Inject the quick creation entry with translated metadata.
        // FR: Injecter l'entrée de création rapide avec des métadonnées traduites.
        $this->results[0] = array(
            // EN: Link directly to the custom module path exposed by Dolibarr.
            // FR: Lien direct vers le chemin custom du module exposé par Dolibarr.
            'url' => '/custom/timesheetweek/timesheetweek_card.php?action=create',
            'title' => 'QuickCreateTimesheetWeek@timesheetweek',
            'name' => 'TimesheetWeek@timesheetweek',
            'picto' => 'bookcal',
            // EN: Activate the quick add entry only when modules and rights allow it.
            // FR: Activer l'entrée de création rapide uniquement lorsque les modules et droits le permettent.
            'activation' => isModEnabled('product') && $hasWriteRight,
            'position' => 100,
        );

        return 0;
    }
}
