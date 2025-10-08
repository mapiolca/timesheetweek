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
     * Add TimesheetWeek entry into the quick add dropdown of the top right menu.
     * Ajouter une entrée TimesheetWeek dans le menu rapide du bandeau supérieur droit.
     *
     * @param array          $parameters Hook parameters / Paramètres du hook
     * @param CommonObject   $object     Current object / Objet courant
     * @param string         $action     Current action / Action courante
     * @param HookManager    $hookmanager Hook manager instance / Gestionnaire de hooks
     *
     * @return int Status / Statut
     */
    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        // EN: Only add the entry when the quick add dropdown is being rendered.
        // FR: Ajouter l'entrée uniquement lorsque le menu déroulant de création rapide est rendu.
        if (empty($parameters['currentcontext']) || $parameters['currentcontext'] !== 'quickadddropdown') {
            return 0;
        }

        // EN: Load module translations to display the quick add label.
        // FR: Charger les traductions du module pour afficher le libellé de création rapide.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        // EN: Stop if the user has no write permission on weekly timesheets.
        // FR: Stopper si l'utilisateur n'a aucun droit d'écriture sur les feuilles hebdomadaires.
        if (empty($user->rights->timesheetweek->write) && empty($user->rights->timesheetweek->writeChild) && empty($user->rights->timesheetweek->writeAll)) {
            return 0;
        }

        // EN: Build the URL leading to the creation form.
        // FR: Construire l'URL menant au formulaire de création.
        $url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 1) . '?action=create';

        // EN: Prepare the translated label for the quick action.
        // FR: Préparer le libellé traduit pour l'action rapide.
        $label = $langs->trans('QuickCreateTimesheetWeek');

        // EN: Reuse the module pictogram to keep the look consistent.
        // FR: Réutiliser le pictogramme du module pour conserver la cohérence visuelle.
        $icon = img_picto('', 'bookcal@timesheetweek', 'class="pictofixedwidth"');

        // EN: Print the quick add entry with escaped content.
        // FR: Afficher l'entrée de création rapide avec un contenu échappé.
        $this->resprints .= '<a class="dropdown-item" href="' . dol_escape_htmltag($url) . '">' . $icon . dol_escape_htmltag($label) . '</a>';

        // EN: Allow other modules to continue injecting their entries.
        // FR: Laisser les autres modules poursuivre l'injection de leurs entrées.
        return 0;
    }
}
