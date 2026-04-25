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

    /**
     * Expose TimesheetWeek translations and trigger metadata on Notification pages.
     * Exposer les traductions TimesheetWeek et les métadonnées de triggers dans les pages Notifications.
     *
     * @param array       $parameters Hook parameters / Paramètres du hook
     * @param CommonObject $object Current object / Objet courant
     * @param string      $action Current action / Action courante
     * @param HookManager $hookmanager Hook manager / Gestionnaire de hooks
     *
     * @return int
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $context = '';
        if (!empty($parameters['currentcontext'])) {
            $context = (string) $parameters['currentcontext'];
        }

        $contexts = array_filter(explode(':', $context));
        $notificationContexts = array(
            'notification',
            'emailtemplates',
            'notificationcard',
            'notificationtemplatescard',
            'emailtemplatescard',
        );

        if (empty(array_intersect($contexts, $notificationContexts))) {
            return 0;
        }

        // EN: Ensure module labels are available when configuring notification events/templates.
        // FR: Assurer la disponibilité des libellés du module lors de la configuration des événements/modèles.
        $langs->loadLangs(array('timesheetweek@timesheetweek'));

        if (!is_array($this->results)) {
            $this->results = array();
        }

        // EN: Provide TimesheetWeek business trigger list to notification hooks when they consume hook results.
        // FR: Fournir la liste des triggers métier TimesheetWeek aux hooks notifications qui lisent $this->results.
        $this->results['timesheetweek_notification_events'] = array(
            'TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_SAVE',
            'TIMESHEETWEEK_SUBMIT',
            'TIMESHEETWEEK_APPROVE',
            'TIMESHEETWEEK_REFUSE',
            'TIMESHEETWEEK_SENTBYMAIL',
            'TIMESHEETWEEK_SEAL',
            'TIMESHEETWEEK_BACKTODRAFT',
            'TIMESHEETWEEK_DELETE',
        );

        return 0;
    }

    /**
     * Add TimesheetWeek events to notification managed events list.
     * Ajouter les événements TimesheetWeek à la liste des événements gérés par Notifications.
     *
     * @param array<string,mixed> $parameters Hook parameters / Paramètres du hook
     * @param CommonObject        $object Current object / Objet courant
     * @param string              $action Current action / Action courante
     * @param HookManager         $hookmanager Hook manager / Gestionnaire de hooks
     *
     * @return int
     */
    public function notifsupported($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        $notificationElementAliases = array('timesheetweek', 'timesheetweek@timesheetweek');
        foreach ($notificationElementAliases as $alias) {
            if (empty($conf->{$alias}) || !is_object($conf->{$alias})) {
                $conf->{$alias} = new stdClass();
            }
            $conf->{$alias}->enabled = !empty($conf->timesheetweek->enabled) ? 1 : 0;
        }

        $events = array(
            'TIMESHEETWEEK_CREATE',
            'TIMESHEETWEEK_SAVE',
            'TIMESHEETWEEK_SUBMIT',
            'TIMESHEETWEEK_APPROVE',
            'TIMESHEETWEEK_REFUSE',
            'TIMESHEETWEEK_SENTBYMAIL',
            'TIMESHEETWEEK_SEAL',
            'TIMESHEETWEEK_BACKTODRAFT',
            'TIMESHEETWEEK_DELETE',
        );

        if (!empty($hookmanager->resArray['arrayofnotifsupported']) && is_array($hookmanager->resArray['arrayofnotifsupported'])) {
            $events = array_merge($hookmanager->resArray['arrayofnotifsupported'], $events);
        }

		$templateByTrigger = array();
		if (is_object($user) && is_object($langs)) {
			$langs->loadLangs(array('mails', 'timesheetweek@timesheetweek', 'users'));
			foreach ($events as $eventCode) {
				if (!is_string($eventCode) || strpos($eventCode, 'TIMESHEETWEEK_') !== 0) {
					continue;
				}

				$template = $this->fetchTemplateForTrigger($eventCode, $user, $langs);
				if (is_object($template) && !empty($template->id)) {
					$templateByTrigger[$eventCode] = (int) $template->id;
				}
			}
		}

		$this->results = array(
			'arrayofnotifsupported' => array_values(array_unique($events)),
			'timesheetweek_templates_by_trigger' => $templateByTrigger,
		);

        return 0;
    }

	/**
	 * Load the email template configured for a trigger.
	 *
	 * @param string    $action Trigger code
	 * @param User      $actionUser Current user
	 * @param Translate $langs Translations handler
	 *
	 * @return object|null
	 */
	protected function fetchTemplateForTrigger($action, $actionUser, $langs)
	{
		global $conf;

		if (empty($action)) {
			return null;
		}

		$actionCode = strtoupper(trim((string) $action));
		$constantName = $actionCode.'_TEMPLATE';
		$label = getDolGlobalString($constantName);
		if (empty($label) && !empty($conf->global->{$constantName})) {
			$label = (string) $conf->global->{$constantName};
		}

		if (empty($label)) {
			dol_syslog(__METHOD__.': no template constant found for '.$constantName, LOG_DEBUG);
			return null;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		$formmail = new FormMail($this->db);

		try {
			$template = $formmail->getEMailTemplate($this->db, 'timesheetweek', $actionUser, $langs, 0, 1, $label);
		} catch (\Throwable $error) {
			dol_syslog(__METHOD__.': getEMailTemplate failed for label '.$label.' - '.$error->getMessage(), LOG_WARNING);
			return null;
		}

		if (is_object($template) && !empty($template->id)) {
			dol_syslog(__METHOD__.': loaded template "'.$label.'" from '.$constantName.' for trigger '.$actionCode, LOG_DEBUG);
			return $template;
		}

		dol_syslog(__METHOD__.': template "'.$label.'" configured in '.$constantName.' was not found', LOG_DEBUG);

		return null;
	}

	/**
	 * Add TimesheetWeek entry into email templates element list.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject        $object Current object
	 * @param string              $action Current action
	 * @param HookManager         $hookmanager Hook manager propagated
	 *
	 * @return int
	 */
	public function emailElementlist($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$langs->loadLangs(array('timesheetweek@timesheetweek'));

		$this->results = array(
			'timesheetweek' => img_picto('', 'bookcal', 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans('MailToSendTimesheetWeek')),
		);

		return 0;
	}

	/**
	 * Inject TimesheetWeek-specific placeholders into the global substitution array.
	 *
	 * FR : Ajoute les variables __TIMESHEETWEEK_*__ au tableau de substitution global afin qu'elles
	 *      soient résolues dans les templates de mail rendus par Notify::send() ou par tout autre
	 *      mécanisme natif Dolibarr appelant complete_substitutions_array().
	 * EN : Push the __TIMESHEETWEEK_*__ placeholders into the global substitution array so they get
	 *      resolved inside email templates rendered by Notify::send() or any other native Dolibarr
	 *      caller of complete_substitutions_array().
	 *
	 * @param array<string,mixed> $parameters
	 * @param CommonObject|null   $object
	 * @param string              $action
	 * @param HookManager         $hookmanager
	 *
	 * @return int
	 */
	public function completesubstitutionarray($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!is_object($object) || !($object instanceof TimesheetWeek)) {
			return 0;
		}

		if (!isset($parameters['substitutionarray']) || !is_array($parameters['substitutionarray'])) {
			return 0;
		}

		dol_include_once('/timesheetweek/class/timesheetweek.class.php');
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

		if (is_object($langs)) {
			$langs->loadLangs(array('timesheetweek@timesheetweek', 'users'));
		}

		$employee = null;
		if (!empty($object->fk_user)) {
			$employee = new User($this->db);
			if ($employee->fetch((int) $object->fk_user) <= 0) {
				$employee = null;
			}
		}

		$validator = null;
		if (!empty($object->fk_user_valid)) {
			$validator = new User($this->db);
			if ($validator->fetch((int) $object->fk_user_valid) <= 0) {
				$validator = null;
			}
		}

		$url = dol_buildpath('/timesheetweek/timesheetweek_card.php', 2).'?id='.(int) $object->id;

		$parameters['substitutionarray']['__TIMESHEETWEEK_REF__']                 = (string) $object->ref;
		$parameters['substitutionarray']['__TIMESHEETWEEK_WEEK__']                = (string) $object->week;
		$parameters['substitutionarray']['__TIMESHEETWEEK_YEAR__']                = (string) $object->year;
		$parameters['substitutionarray']['__TIMESHEETWEEK_URL__']                 = $url;
		$parameters['substitutionarray']['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__']   = $employee ? $employee->getFullName($langs) : '';
		$parameters['substitutionarray']['__TIMESHEETWEEK_VALIDATOR_FULLNAME__']  = $validator ? $validator->getFullName($langs) : '';

		return 0;
	}
}
