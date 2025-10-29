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
                        //'display' => '!empty($conf->global->MULTICOMPANY_TIMESHEETWEEK_SHARING_ENABLED)',
                        'input' => array(
                            'global' => array(
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
