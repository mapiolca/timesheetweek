<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

// EN: Check permissions before loading any additional resources to abort early.
// FR: Vérifie les permissions avant de charger d'autres ressources pour interrompre immédiatement.
$permRead = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll = $user->hasRight('timesheetweek','timesheetweek','readAll');
$permWrite = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll = $user->hasRight('timesheetweek','timesheetweek','writeAll');
$permDelete = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll = $user->hasRight('timesheetweek','timesheetweek','deleteAll');
$permValidate = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateOwn = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll = $user->hasRight('timesheetweek','timesheetweek','validateAll');
$permSeal = $user->hasRight('timesheetweek','timesheetweek','seal');
$canSeeAllEmployees = (!empty($user->admin) || $permReadAll || $permWriteAll || $permDeleteAll || $permValidateAll);
$permViewAny = ($permRead || $permReadChild || $permReadAll || $permWrite || $permWriteChild || $permWriteAll || $permDelete ||
$permDeleteChild || $permDeleteAll || $permValidate || $permValidateOwn || $permValidateChild || $permValidateAll || !empty($user->admin));
if (!$permViewAny) {
	accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','other','users'));

// EN: Provide the validation helper when not already loaded by another context.
// FR: Fournit l'assistant de validation lorsqu'il n'est pas déjà chargé par un autre contexte.
if (!function_exists('tw_can_validate_timesheet')) {
	function tw_can_validate_timesheet(
		TimesheetWeek $o,
		User $user,
		$permValidate,
		$permValidateOwn,
		$permValidateChild,
		$permValidateAll,
		$permWrite = false,
		$permWriteChild = false,
		$permWriteAll = false
	) {
		// EN: Mirror the permission fallback from the card to keep both screens aligned.
		// FR: Reproduit le repli de permissions de la fiche pour garder les deux écrans cohérents.
		$hasExplicitValidation = ($permValidate || $permValidateOwn || $permValidateChild || $permValidateAll);

		if (!empty($user->admin)) {
			$permValidateAll = true;
			$hasExplicitValidation = true;
		}

		if (!$hasExplicitValidation) {
			// EN: Reuse write permissions when historical validation rights are absent.
			// FR: Réutilise les droits d'écriture lorsque les droits de validation historiques sont absents.
			if ($permWriteAll) {
				$permValidateAll = true;
			}
			if ($permWriteChild) {
				$permValidateChild = true;
			}

			if ($permWrite || $permWriteChild || $permWriteAll) {
				// EN: Allow validation when the user is set as validator on the sheet.
				// FR: Autorise la validation lorsque l'utilisateur est désigné validateur sur la feuille.
				if ((int) $o->fk_user_valid === (int) $user->id) {
					$permValidate = true;
				}

				// EN: Restore legacy manager behaviour relying on writeChild rights.
				// FR: Restaure l'ancien comportement des managers basé sur les droits writeChild.
				if (!$permValidateChild && $permWriteChild) {
					$permValidateChild = true;
				}
			}
		}

		if ($permValidateAll) return true;
		if ($permValidateChild && tw_is_manager_of($o->fk_user, $user)) return true;
		if ($permValidateOwn && ((int) $user->id === (int) $o->fk_user)) return true;
		if ($permValidate && ((int) $user->id === (int) $o->fk_user_valid)) return true;

		return false;
	}
}

// EN: Translate low-level errors when mass actions fail.
// FR: Traduit les erreurs bas niveau lors des échecs d'actions de masse.
if (!function_exists('tw_list_translate_error')) {
	function tw_list_translate_error($errorKey, $langs)
	{
		if (empty($errorKey)) {
			return $langs->trans('Error');
		}
		$translated = $langs->trans($errorKey);
		if ($translated === $errorKey) {
			return $langs->trans('Error').' ('.dol_escape_htmltag($errorKey).')';
		}
		return $translated;
	}
}

// EN: Collect the identifiers of employees the user is authorised to manage.
// FR: Rassemble les identifiants des salariés que l'utilisateur est autorisé à gérer.
$allowedUserIds = array();
if (!$canSeeAllEmployees) {
	if ($permRead || $permWrite || $permDelete || $permValidate || $permValidateOwn) {
		$allowedUserIds[] = (int) $user->id;
	}
	if ($permReadChild || $permWriteChild || $permDeleteChild || $permValidateChild) {
		$allowedUserIds = array_merge($allowedUserIds, tw_get_user_child_ids($user));
	}
	$allowedUserIds = array_values(array_unique(array_filter($allowedUserIds, function ($candidateId) {
		return (int) $candidateId > 0;
	})));
}
// EN: Detect if the Multicompany module is enabled to expose entity-specific data.
// FR: Détecte si le module Multicompany est activé pour exposer les données spécifiques d'entité.
$multicompanyEnabled = !empty($conf->multicompany->enabled);


/**
 * Params
 */
$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$show_files   = GETPOSTINT('show_files');
$confirm      = GETPOST('confirm', 'alpha');
$cancel       = GETPOST('cancel', 'alpha');
$toselect     = GETPOST('toselect', 'array');
$contextpage  = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

$sortfield    = GETPOST('sortfield', 'aZ09comma');
$sortorder    = GETPOST('sortorder', 'aZ09comma');
$page         = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) $page = 0;
$limit        = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset       = $limit * $page;

$search_ref   = trim(GETPOST('search_ref','alphanohtml'));
$search_user  = GETPOSTINT('search_user');
$search_year  = GETPOSTINT('search_year');
$search_week  = GETPOSTINT('search_week');
// EN: Retrieve the ISO year-week values for the multi-select filter and validate them.
// FR: Récupère les valeurs ISO année-semaine pour le filtre multi-sélection et les valide.
$rawWeekyearFilter = GETPOST('search_weekyear', 'array', 2);
if (!is_array($rawWeekyearFilter)) {
        $legacyWeekyear = trim(GETPOST('search_weekyear', 'alphanohtml'));
        $rawWeekyearFilter = $legacyWeekyear !== '' ? array($legacyWeekyear) : array();
}

$search_weekyears = array();
$searchWeekTuples = array();
foreach ($rawWeekyearFilter as $candidateWeekyear) {
        $candidateWeekyear = trim((string) $candidateWeekyear);
        if ($candidateWeekyear === '') {
                continue;
        }
        if (preg_match('/^(\d{4})-W(\d{2})$/', $candidateWeekyear, $matches)) {
                $isoWeekyear = $matches[1].'-W'.$matches[2];
                if (!in_array($isoWeekyear, $search_weekyears, true)) {
                        // EN: Preserve each valid ISO entry for rendering and SQL filtering.
                        // FR: Conserve chaque entrée ISO valide pour le rendu et le filtrage SQL.
                        $search_weekyears[] = $isoWeekyear;
                        $searchWeekTuples[] = array('year' => (int) $matches[1], 'week' => (int) $matches[2]);
                }
        }
}
// EN: Capture the entity filters when Multicompany support is available, ensuring backward compatibility with legacy single values.
// FR: Capture les filtres d'entité lorsque la compatibilité Multicompany est disponible, en assurant la rétrocompatibilité avec les valeurs uniques historiques.
$search_entities = array();
if ($multicompanyEnabled) {
        $rawEntityFilter = GETPOST('search_entity', 'array', 2);
        if (!is_array($rawEntityFilter)) {
                $legacyEntity = GETPOSTINT('search_entity');
                $rawEntityFilter = $legacyEntity > 0 ? array($legacyEntity) : array();
        }
        foreach ($rawEntityFilter as $entityCandidate) {
                $entityId = (int) $entityCandidate;
                if ($entityId >= 0 && !in_array($entityId, $search_entities, true)) {
                        // EN: Keep each allowed entity identifier only once to mirror the native Dolibarr multiselect behaviour.
                        // FR: Conserve chaque identifiant d'entité une seule fois afin de refléter le comportement natif du multiselect Dolibarr.
                        $search_entities[] = $entityId;
                }
        }
}
$search_status = GETPOST('search_status', 'array', 2);
$search_status = is_array($search_status) ? $search_status : array();
$hasStatusRequest = function_exists('GETPOSTISSET') ? GETPOSTISSET('search_status') : (isset($_GET['search_status']) || isset($_POST['search_status']));
if (!$hasStatusRequest) {
    $rawStatus = GETPOST('search_status', 'alpha');
    if (!empty($rawStatus)) {
        $search_status = array_map('trim', explode(',', $rawStatus));
    }
}
$search_status = array_values(array_unique(array_filter($search_status, function ($value) {
    return $value !== '' && $value !== '-1';
})));
$search_status = array_map('strval', $search_status);

/**
 * Objects
 */
$form = new Form($db);
$tswstatic = new TimesheetWeek($db);
$usertmp = new User($db);

// EN: Preload the list of entities allowed for the module to feed the multiselect filter.
// FR: Précharge la liste des entités autorisées pour le module afin d'alimenter le filtre multisélection.
$entityFilterOptions = array();
if ($multicompanyEnabled) {
        $allowedEntityIds = array();
        $allowedEntityString = getEntity('timesheetweek');
        if (!empty($allowedEntityString)) {
                foreach (explode(',', $allowedEntityString) as $candidate) {
                        $candidate = trim($candidate);
                        if ($candidate === '') {
                                continue;
                        }
                        $candidateId = (int) $candidate;
                        if ($candidateId > 0 && !in_array($candidateId, $allowedEntityIds, true)) {
                                // EN: Keep only positive entity identifiers to match entries available in the entity dictionary.
                                // FR: Conserve uniquement les identifiants d'entité positifs afin de correspondre aux entrées disponibles dans le dictionnaire des entités.
                                $allowedEntityIds[] = $candidateId;
                        }
                }
        }

        $sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX."entity";
        if (!empty($allowedEntityIds)) {
                $sqlEntity .= ' WHERE rowid IN ('.implode(',', $allowedEntityIds).')';
        }
        $sqlEntity .= ' ORDER BY label ASC';

        $resEntity = $db->query($sqlEntity);
        if ($resEntity) {
                while ($entityRow = $db->fetch_object($resEntity)) {
                        $label = trim((string) $entityRow->label);
                        if ($label === '') {
                                // EN: Fall back to a generic label when the entity dictionary does not provide a name.
                                // FR: Revient à un libellé générique lorsque le dictionnaire d'entités ne fournit pas de nom.
                                $label = $langs->trans('Entity').' #'.(int) $entityRow->rowid;
                        }
                        $entityFilterOptions[(int) $entityRow->rowid] = $label;
                }
                $db->free($resEntity);
        }
}
/**
 * Arrayfields (select columns)
 */
$arrayfields = array(
        't.ref'          => array('label' => $langs->trans("Ref"),          'checked' => 1),
        'user'           => array('label' => $langs->trans("Employee"),     'checked' => 1),
);

if ($multicompanyEnabled) {
        // EN: Display the entity column immediately after the employee to avoid it drifting to the far right.
        // FR: Affiche la colonne entité juste après l'employé pour éviter qu'elle ne parte à l'extrême droite.
        $arrayfields['t.entity'] = array('label' => $langs->trans('Entity'), 'checked' => 1);
}

$arrayfields += array(
        't.year'         => array('label' => $langs->trans("Year"),         'checked' => 1),
        't.week'         => array('label' => $langs->trans("Week"),         'checked' => 1),
        't.total_hours'  => array('label' => $langs->trans("TotalHours"),   'checked' => 1),
        't.overtime_hours'=>array('label' => $langs->trans("Overtime"),     'checked' => 0),
        // EN: Zone counters columns for list display.
        // FR: Colonnes des compteurs de zones pour l'affichage de la liste.
        't.zone1_count'  => array('label' => $langs->trans("Zone1Count"),   'checked' => 0),
        't.zone2_count'  => array('label' => $langs->trans("Zone2Count"),   'checked' => 0),
        't.zone3_count'  => array('label' => $langs->trans("Zone3Count"),   'checked' => 0),
        't.zone4_count'  => array('label' => $langs->trans("Zone4Count"),   'checked' => 0),
        't.zone5_count'  => array('label' => $langs->trans("Zone5Count"),   'checked' => 0),
        // EN: Meal counter column for list display.
        // FR: Colonne du compteur de paniers pour l'affichage de la liste.
        't.meal_count'   => array('label' => $langs->trans("MealCount"),    'checked' => 0),
        't.date_creation'=> array('label' => $langs->trans("DateCreation"), 'checked' => 0),
        // EN: Validation timestamp column to expose approval dates in the list.
        // FR: Colonne de validation pour afficher les dates d'approbation dans la liste.
        't.date_validation'=> array('label' => $langs->trans("DateValidation"), 'checked' => 0),
        't.tms'          => array('label' => $langs->trans("DateModificationShort"), 'checked' => 0),
        't.status'       => array('label' => $langs->trans("Status"),       'checked' => 1),
);

// Update arrayfields from request (column selector)
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

/**
 * Mass actions (UI)
 */
$arrayofmassactions = array();
if ($permWrite || $permWriteChild || $permWriteAll) {
	// EN: Allow bulk submission when the user can update timesheets.
	// FR: Autorise la soumission en masse lorsque l'utilisateur peut mettre à jour les feuilles.
	$arrayofmassactions['submit_selection'] = img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans('MassActionTimesheetSubmit');
}
$hasValidationRights = ($permValidate || $permValidateOwn || $permValidateChild || $permValidateAll || !empty($user->admin) || $permWrite || $permWriteChild || $permWriteAll);
if ($hasValidationRights) {
	// EN: Offer approval and refusal shortcuts to authorised validators.
	// FR: Propose des raccourcis d'acceptation et de refus aux validateurs habilités.
	$arrayofmassactions['approve_selection'] = img_picto('', 'validate', 'class="pictofixedwidth"').$langs->trans('MassActionTimesheetApprove');
	$arrayofmassactions['refuse_selection'] = img_picto('', 'warning', 'class="pictofixedwidth"').$langs->trans('MassActionTimesheetRefuse');
}
if ($permSeal) {
	// EN: Display the sealing action only to users granted with the dedicated right.
	// FR: Affiche l'action de scellement uniquement aux utilisateurs disposant du droit dédié.
	$arrayofmassactions['seal_selection'] = img_picto('', 'lock', 'class="pictofixedwidth"').$langs->trans('MassActionTimesheetSeal');
}
if ($permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin)) {
	// EN: Keep the existing delete action while restricting it to users with delete capabilities.
	// FR: Conserve l'action de suppression en la limitant aux utilisateurs autorisés à supprimer.
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('DeleteSelection');
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$arrayofselected = is_array($toselect) ? $toselect : array();

// EN: Handle the selected IDs before the generic Dolibarr mass-action handler executes.
// FR: Traite les identifiants sélectionnés avant que le gestionnaire d'actions de masse Dolibarr ne s'exécute.
$massActionProcessed = false;
if (!empty($massaction) && empty($cancel)) {
	$selectedIds = array();
	foreach ($arrayofselected as $candidateId) {
		$candidateId = (int) $candidateId;
		if ($candidateId > 0 && !in_array($candidateId, $selectedIds, true)) {
			$selectedIds[] = $candidateId;
		}
	}

	if (!empty($selectedIds)) {
		// EN: Execute the submission mass action when available.
		// FR: Exécute l'action de masse de validation lorsqu'elle est disponible.
		if ($massaction === 'submit_selection') {
			$massActionProcessed = true;
			$successCount = 0;
			$errorMessages = array();
			foreach ($selectedIds as $sheetId) {
				$timesheet = new TimesheetWeek($db);
				$resultFetch = $timesheet->fetch($sheetId);
				if ($resultFetch <= 0) {
					if ($resultFetch === 0) {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionNotFound', '#'.$sheetId);
					} else {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', '#'.$sheetId, tw_list_translate_error($timesheet->error, $langs));
					}
					continue;
				}

				$sheetRef = dol_escape_htmltag($timesheet->ref !== '' ? $timesheet->ref : '#'.$timesheet->id);
				if (!tw_can_act_on_user($timesheet->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionPermissionDenied', $sheetRef);
					continue;
				}

				$resultAction = $timesheet->submit($user);
				if ($resultAction > 0) {
					$successCount++;
				} else {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', $sheetRef, tw_list_translate_error($timesheet->error, $langs));
				}
			}
			if ($successCount > 0) {
				setEventMessages($langs->trans('TimesheetWeekMassSubmitSuccess', $successCount), null, 'mesgs');
			}
			if (!empty($errorMessages)) {
				setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $errorMessages)), null, 'errors');
			}
		}

		// EN: Execute the approval mass action for authorised validators.
		// FR: Exécute l'action de masse d'acceptation pour les validateurs autorisés.
		if ($massaction === 'approve_selection') {
			$massActionProcessed = true;
			$successCount = 0;
			$errorMessages = array();
			foreach ($selectedIds as $sheetId) {
				$timesheet = new TimesheetWeek($db);
				$resultFetch = $timesheet->fetch($sheetId);
				if ($resultFetch <= 0) {
					if ($resultFetch === 0) {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionNotFound', '#'.$sheetId);
					} else {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', '#'.$sheetId, tw_list_translate_error($timesheet->error, $langs));
					}
					continue;
				}

				$sheetRef = dol_escape_htmltag($timesheet->ref !== '' ? $timesheet->ref : '#'.$timesheet->id);
				if (!tw_can_validate_timesheet($timesheet, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionPermissionDenied', $sheetRef);
					continue;
				}

				$resultAction = $timesheet->approve($user);
				if ($resultAction > 0) {
					$successCount++;
				} else {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', $sheetRef, tw_list_translate_error($timesheet->error, $langs));
				}
			}
			if ($successCount > 0) {
				setEventMessages($langs->trans('TimesheetWeekMassApproveSuccess', $successCount), null, 'mesgs');
			}
			if (!empty($errorMessages)) {
				setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $errorMessages)), null, 'errors');
			}
		}

		// EN: Execute the refusal mass action for authorised validators.
		// FR: Exécute l'action de masse de refus pour les validateurs autorisés.
		if ($massaction === 'refuse_selection') {
			$massActionProcessed = true;
			$successCount = 0;
			$errorMessages = array();
			foreach ($selectedIds as $sheetId) {
				$timesheet = new TimesheetWeek($db);
				$resultFetch = $timesheet->fetch($sheetId);
				if ($resultFetch <= 0) {
					if ($resultFetch === 0) {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionNotFound', '#'.$sheetId);
					} else {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', '#'.$sheetId, tw_list_translate_error($timesheet->error, $langs));
					}
					continue;
				}

				$sheetRef = dol_escape_htmltag($timesheet->ref !== '' ? $timesheet->ref : '#'.$timesheet->id);
				if (!tw_can_validate_timesheet($timesheet, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionPermissionDenied', $sheetRef);
					continue;
				}

				$resultAction = $timesheet->refuse($user);
				if ($resultAction > 0) {
					$successCount++;
				} else {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', $sheetRef, tw_list_translate_error($timesheet->error, $langs));
				}
			}
			if ($successCount > 0) {
				setEventMessages($langs->trans('TimesheetWeekMassRefuseSuccess', $successCount), null, 'mesgs');
			}
			if (!empty($errorMessages)) {
				setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $errorMessages)), null, 'errors');
			}
		}

		// EN: Execute the sealing mass action for users with sealing rights.
		// FR: Exécute l'action de masse de scellement pour les utilisateurs disposant du droit associé.
		if ($massaction === 'seal_selection') {
			$massActionProcessed = true;
			$successCount = 0;
			$errorMessages = array();
			foreach ($selectedIds as $sheetId) {
				$timesheet = new TimesheetWeek($db);
				$resultFetch = $timesheet->fetch($sheetId);
				if ($resultFetch <= 0) {
					if ($resultFetch === 0) {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionNotFound', '#'.$sheetId);
					} else {
						$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', '#'.$sheetId, tw_list_translate_error($timesheet->error, $langs));
					}
					continue;
				}

				$sheetRef = dol_escape_htmltag($timesheet->ref !== '' ? $timesheet->ref : '#'.$timesheet->id);
				if (!$permSeal) {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionPermissionDenied', $sheetRef);
					continue;
				}

				$resultAction = $timesheet->seal($user);
				if ($resultAction > 0) {
					$successCount++;
				} else {
					$errorMessages[] = $langs->trans('TimesheetWeekMassActionGenericError', $sheetRef, tw_list_translate_error($timesheet->error, $langs));
				}
			}
			if ($successCount > 0) {
				setEventMessages($langs->trans('TimesheetWeekMassSealSuccess', $successCount), null, 'mesgs');
			}
			if (!empty($errorMessages)) {
				setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $errorMessages)), null, 'errors');
			}
		}
	}
}
if ($massActionProcessed) {
	// EN: Reset the mass action to avoid running the generic include a second time.
	// FR: Réinitialise l'action de masse pour éviter d'exécuter l'inclusion générique une seconde fois.
	$massaction = '';
}

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_ref = '';
    $search_user = 0;
    $search_year = 0;
    $search_week = 0;
    // EN: Reset the ISO multi-week selector when clearing filters from the toolbar.
    // FR: Réinitialise le sélecteur multi-semaines ISO lors de la suppression des filtres via la barre d'outils.
    $search_weekyears = array();
    $searchWeekTuples = array();
    if ($multicompanyEnabled) {
        // EN: Clear the entity filter alongside other search parameters.
        // FR: Réinitialise le filtre d'entité en même temps que les autres paramètres de recherche.
        $search_entities = array();
    }
    $search_status = array();
}

/**
 * SQL
 */
$sqlfields = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours,";
if ($multicompanyEnabled) {
	// EN: Include the entity column and its label in the result set when Multicompany is active.
	// FR: Inclut la colonne entité et son libellé dans le jeu de résultats lorsque Multicompany est actif.
	$sqlfields .= " t.entity, e.label as entity_label,";
}
// EN: Expose zone and meal counters in the list query.
// FR: Expose les compteurs de zones et de paniers dans la requête de liste.
$sqlfields .= " t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count,";
$sqlfields .= " t.date_creation, t.tms, t.date_validation, t.fk_user_valid,";
$sqlfields .= " u.rowid as uid, u.firstname, u.lastname, u.login, u.photo as user_photo, u.statut as user_status";
$sql = $sqlfields;
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
if ($multicompanyEnabled) {
        // EN: Resolve the entity name to display native Dolibarr badges in the list.
        // FR: Récupère le nom de l'entité pour afficher les badges natifs de Dolibarr dans la liste.
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON e.rowid = t.entity";
}
$sql .= " WHERE 1=1";
// EN: Restrict the listing to the entities allowed for the timesheet module.
// FR: Restreint la liste aux entités autorisées pour le module de feuilles de temps.
$sql .= " AND t.entity IN (".getEntity('timesheetweek').")";
if (!$canSeeAllEmployees) {
	if (!empty($allowedUserIds)) {
		// EN: Restrict the SQL query to employees that belong to the manager's scope.
		// FR: Restreint la requête SQL aux salariés qui relèvent du périmètre du responsable.
		$sql .= ' AND t.fk_user IN ('.implode(',', $allowedUserIds).')';
	} else {
		// EN: Block access when the user has no visible employees within the current entity selection.
		// FR: Bloque l'accès lorsque l'utilisateur n'a aucun salarié visible dans la sélection d'entités courante.
		$sql .= ' AND 1=0';
	}
}
if ($search_ref !== '')     $sql .= natural_search('t.ref', $search_ref);
if ($search_user > 0)       $sql .= " AND t.fk_user = ".((int)$search_user);
if ($search_year > 0)       $sql .= " AND t.year = ".((int)$search_year);
if ($search_week > 0)       $sql .= " AND t.week = ".((int)$search_week);
if (!empty($searchWeekTuples)) {
        // EN: Apply each selected ISO week constraint as an OR group.
        // FR: Applique chaque contrainte de semaine ISO sélectionnée sous forme de groupe OR.
        $weekConditions = array();
        foreach ($searchWeekTuples as $tuple) {
                $weekConditions[] = '(t.year = '.((int) $tuple['year']).' AND t.week = '.((int) $tuple['week']).')';
        }
        if (!empty($weekConditions)) {
                $sql .= ' AND ('.implode(' OR ', $weekConditions).')';
        }
}
if ($multicompanyEnabled && !empty($search_entities)) {
        $entityConditions = array();
        foreach ($search_entities as $entityId) {
                $entityConditions[] = (int) $entityId;
        }
        if (!empty($entityConditions)) {
                // EN: Narrow the query to the selected entities when the Multicompany filter is used.
                // FR: Restreint la requête aux entités sélectionnées lorsque le filtre Multicompany est utilisé.
                $sql .= ' AND t.entity IN ('.implode(',', $entityConditions).')';
        }
}
if (!empty($search_status)) {
	$statusFilter = array();
	foreach ($search_status as $statusValue) {
		$statusFilter[] = (int) $statusValue;
	}
	if (!empty($statusFilter)) {
		$sql .= ' AND t.status IN ('.implode(',', $statusFilter).')';
	}
}

// EN: Duplicate the SQL without pagination to compute the total amount of records.
// FR: Duplique la requête sans pagination pour calculer le nombre total d'enregistrements.
$sqlList = $sql;
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	// EN: Replace the field list by a COUNT(*) to follow the Dolibarr counting pattern.
	// FR: Remplace la liste des champs par un COUNT(*) pour suivre le modèle de comptage de Dolibarr.
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);
	$resqlcount = $db->query($sqlforcount);
	if ($resqlcount) {
		$objforcount = $db->fetch_object($resqlcount);
		$nbtotalofrecords = (int) $objforcount->nbtotalofrecords;
		// EN: Release the count result resource to avoid leaking database handles.
		// FR: Libère le résultat du comptage pour éviter de laisser des descripteurs ouverts.
		$db->free($resqlcount);

		if (($page * $limit) > (int) $nbtotalofrecords) {
			// EN: Reset the pagination when the current offset exceeds the number of rows.
			// FR: Réinitialise la pagination lorsque le décalage courant dépasse le nombre de lignes.
			$page = 0;
			$offset = 0;
		}
	} else {
		dol_print_error($db);
	}
}

if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";
$sqlList .= $db->order($sortfield, $sortorder);
$sqlList .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sqlList);
if (!$resql) dol_print_error($db);
$num = $resql ? $db->num_rows($resql) : 0;

/**
 * Header
 */
$title = $langs->trans("TimesheetWeek_List");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist page-list');

/**
 * Build param for pagination links
 */
$param = '';
if ($search_ref)   $param .= '&search_ref='.urlencode($search_ref);
if ($search_user)  $param .= '&search_user='.(int)$search_user;
if ($search_year)  $param .= '&search_year='.(int)$search_year;
if ($search_week)  $param .= '&search_week='.(int)$search_week;
if (!empty($search_weekyears)) {
        // EN: Persist each selected ISO week across pagination links.
        // FR: Conserve chaque semaine ISO sélectionnée lors de la pagination.
        foreach ($search_weekyears as $isoWeekyear) {
                $param .= '&search_weekyear[]='.urlencode($isoWeekyear);
        }
}
if ($multicompanyEnabled && !empty($search_entities)) {
        // EN: Preserve each selected entity during pagination and sorting.
        // FR: Préserve chaque entité sélectionnée lors de la pagination et du tri.
        foreach ($search_entities as $entityId) {
                $param .= '&search_entity[]='.(int) $entityId;
        }
}
if (!empty($search_status)) {
    foreach ($search_status as $statusValue) {
        $param .= '&search_status[]='.(int) $statusValue;
    }
}
// EN: Keep the selected limit within pagination links to honour the user's choice.
// FR: Conserve la limite sélectionnée dans les liens de pagination pour respecter le choix de l'utilisateur.
$param .= '&limit='.(int) $limit;

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?action=create', '', $user->hasRight('timesheetweek','timesheetweek','write'));

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bookcal', 0, $newcardbutton, '', $limit, 0, 0, 1);

/**
 * Column selector on left of titles
 */
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedfields = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

/**
 * Form + table
 */
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
// EN: Preserve the selected list limit across filter submissions while avoiding duplicated DOM identifiers.
// FR: Conserve la limite de liste sélectionnée lors des filtrages tout en évitant les identifiants dupliqués dans le DOM.
print '<input type="hidden" name="limit" id="limit-hidden" value="'.((int) $limit).'">';

if ($massactionbutton || $massaction) {
	// EN: Insert the standard Dolibarr mass action pre-template to wrap the toolbar within the form scope.
	// FR: Insère le modèle pré-actions de masse Dolibarr standard pour encapsuler la barre d'outils dans le formulaire.
	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';
}

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

/**
 * Filter row
 */
print '<tr class="liste_titre_filter">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print '<td class="liste_titre center maxwidthsearch">';
        print $form->showFilterButtons('left');
        print '</td>';
}
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="12"></td>';
}
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	// EN: Render the Dolibarr user selector with photos while keeping it within the authorised scope.
	// FR: Affiche le sélecteur utilisateur Dolibarr avec photos tout en respectant le périmètre autorisé.
	// EN: Default the employee selector to an empty value when no filter is applied.
	// FR: Définit une valeur vide par défaut pour le sélecteur salarié lorsqu'aucun filtre n'est appliqué.
	$employeeSelectSelected = $search_user > 0 ? $search_user : '';
	$employeeSelectHtml = $form->select_dolusers($employeeSelectSelected, 'search_user', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1);
	if (!$canSeeAllEmployees) {
		// EN: Remove any option outside the authorised employees while preserving placeholders.
		// FR: Supprime toute option hors des salariés autorisés tout en conservant les valeurs de remplacement.
		$employeeSelectHtml = tw_filter_select_by_user_ids($employeeSelectHtml, $allowedUserIds, $search_user);
	}
	// EN: Hide any trailing internal ID to keep the dropdown label clean for end users.
	// FR: Masque tout identifiant interne pour conserver un libellé propre côté utilisateur final.
	print tw_strip_user_id_from_select($employeeSelectHtml);
	print '</td>';
}
if (!empty($arrayfields['t.entity']['checked'])) {
        print '<td class="liste_titre center">';
        if (!empty($entityFilterOptions)) {
                // EN: Reuse the native Dolibarr multiselect to align the entity filter with the Multicompany user interface.
                // FR: Réutilise le multiselect natif de Dolibarr pour aligner le filtre d'entité avec l'interface Multicompany.
                print $form->multiselectarray('search_entity', $entityFilterOptions, $search_entities, 0, 0, 'minwidth150 maxwidth200', 0, 0, '', '', '', '', '', 1);
        } else {
                // EN: Display an empty cell when no entity is available, keeping the layout consistent.
                // FR: Affiche une cellule vide lorsqu'aucune entité n'est disponible, afin de conserver la mise en page.
                print '&nbsp;';
        }
        print '</td>';
}
if (!empty($arrayfields['t.year']['checked'])) {
        print '<td class="liste_titre center"><input class="flat" type="number" name="search_year" value="'.($search_year>0?(int)$search_year:'').'" style="width:80px"></td>';
}
if (!empty($arrayfields['t.week']['checked'])) {
        // EN: Determine which year should drive the ISO week selector (either the filter or the current year).
        // FR: Détermine l'année qui doit piloter le sélecteur ISO de semaine (filtre ou année courante).
        $currentWeekSelectorYear = $search_year > 0 ? $search_year : 0;
        if ($currentWeekSelectorYear === 0 && !empty($searchWeekTuples)) {
                // EN: Align the selector year with the first selected ISO tuple when available.
                // FR: Aligne l'année du sélecteur sur la première paire ISO sélectionnée lorsque disponible.
                $currentWeekSelectorYear = (int) $searchWeekTuples[0]['year'];
        }
        $selectedWeekValues = $search_weekyears;
        if (empty($selectedWeekValues) && $search_week > 0 && $search_year > 0) {
                // EN: Display the typed year/week combination in the selector for visual feedback.
                // FR: Affiche la combinaison année/semaine saisie dans le sélecteur pour le retour visuel.
                $selectedWeekValues[] = sprintf('%04d-W%02d', $search_year, $search_week);
        }
        // EN: Reuse the Dolibarr week selector in multi-select mode for consistent UX with the card view.
        // FR: Réutilise le sélecteur de semaine Dolibarr en mode multi-sélection pour harmoniser l'UX avec la fiche.
        print '<td class="liste_titre center">'.getWeekSelectorDolibarr($form, 'search_weekyear', $selectedWeekValues, $currentWeekSelectorYear, true, true).'</td>';
}
if (!empty($arrayfields['t.total_hours']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
// EN: Keep filters empty for zone and meal counters (display only).
// FR: Laisse les filtres vides pour les compteurs de zones et paniers (affichage seul).
if (!empty($arrayfields['t.zone1_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.zone2_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.zone3_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.zone4_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.zone5_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.meal_count']['checked'])) {
        print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.date_creation']['checked'])) {
        print '<td class="liste_titre center">&nbsp;</td>';
}
if (!empty($arrayfields['t.date_validation']['checked'])) {
        // EN: Validation date has no filter because approvals are historical events.
        // FR: La date de validation n'a pas de filtre car les approbations sont des événements historiques.
        print '<td class="liste_titre center">&nbsp;</td>';
}
if (!empty($arrayfields['t.tms']['checked'])) {
        print '<td class="liste_titre center">&nbsp;</td>';
}
if (!empty($arrayfields['t.status']['checked'])) {
        $statusOptions = array(
                TimesheetWeek::STATUS_DRAFT     => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_DRAFT, 0),
                TimesheetWeek::STATUS_SUBMITTED => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_SUBMITTED, 0),
                TimesheetWeek::STATUS_APPROVED  => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_APPROVED, 0),
                TimesheetWeek::STATUS_SEALED    => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_SEALED, 0),
                TimesheetWeek::STATUS_REFUSED   => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_REFUSED, 0),
        );

        print '<td class="liste_titre center">';
        print $form->multiselectarray('search_status', $statusOptions, $search_status, 0, 0, 'minwidth150 maxwidth200', 0, 0, '', '', '', '', '', 1);
        print '</td>';
}
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
        print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('right').'</td>';
}
print '</tr>'."\n";

/**
 * Titles row
 */
print '<tr class="liste_titre">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'maxwidthsearch center ');
}
if (!empty($arrayfields['t.ref']['checked'])) {
	print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], "t.ref", "", $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['user']['checked'])) {
        print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], "u.lastname", "", $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.entity']['checked'])) {
        // EN: Display the entity header for Multicompany-aware listings.
        // FR: Affiche l'entête d'entité pour les listes compatibles Multicompany.
        print_liste_field_titre($arrayfields['t.entity']['label'], $_SERVER["PHP_SELF"], "t.entity", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.year']['checked'])) {
        print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], "t.year", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.week']['checked'])) {
	print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], "t.week", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
        print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], "t.overtime_hours", "", $param, '', $sortfield, $sortorder, 'right ');
}
// EN: Display headers for weekly zone counters.
// FR: Affiche les entêtes des compteurs hebdomadaires de zones.
if (!empty($arrayfields['t.zone1_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.zone1_count']['label'], $_SERVER["PHP_SELF"], "t.zone1_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.zone2_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.zone2_count']['label'], $_SERVER["PHP_SELF"], "t.zone2_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.zone3_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.zone3_count']['label'], $_SERVER["PHP_SELF"], "t.zone3_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.zone4_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.zone4_count']['label'], $_SERVER["PHP_SELF"], "t.zone4_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.zone5_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.zone5_count']['label'], $_SERVER["PHP_SELF"], "t.zone5_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
// EN: Display header for weekly meal counter.
// FR: Affiche l'entête du compteur hebdomadaire de paniers.
if (!empty($arrayfields['t.meal_count']['checked'])) {
        print_liste_field_titre($arrayfields['t.meal_count']['label'], $_SERVER["PHP_SELF"], "t.meal_count", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.date_creation']['checked'])) {
        print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], "t.date_creation", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.date_validation']['checked'])) {
        print_liste_field_titre($arrayfields['t.date_validation']['label'], $_SERVER["PHP_SELF"], "t.date_validation", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.tms']['checked'])) {
        print_liste_field_titre($arrayfields['t.tms']['label'], $_SERVER["PHP_SELF"], "t.tms", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.status']['checked'])) {
	print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], "t.status", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
print '</tr>'."\n";

/**
 * Rows
 */
$totalsAccumulator = array(
        // EN: Track sums to display a total row at the bottom of the listing.
        // FR: Suit les sommes pour afficher une ligne totale en bas de la liste.
        'total_hours'      => 0.0,
        'overtime_hours'   => 0.0,
        'zone1_count'      => 0,
        'zone2_count'      => 0,
        'zone3_count'      => 0,
        'zone4_count'      => 0,
        'zone5_count'      => 0,
        'meal_count'       => 0,
);
$i = 0;
$imax = ($limit ? min($num, $limit) : $num);
while ($i < $imax) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;

	print '<tr class="oddeven">';

	// Left selection checkbox column
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, $arrayofselected) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
	}

	// Ref (link with ref text)
	if (!empty($arrayfields['t.ref']['checked'])) {
		$tswstatic->id = $obj->rowid;
		$tswstatic->ref = $obj->ref;
		$tswstatic->status = $obj->status;
		print '<td>'.$tswstatic->getNomUrl(1, 'ref').'</td>';
	}

	// Employee
	if (!empty($arrayfields['user']['checked'])) {
		$usertmp->id = $obj->uid;
		$usertmp->firstname = $obj->firstname;
		$usertmp->lastname = $obj->lastname;
		$usertmp->login = $obj->login;
		// EN: Feed status and photo so getNomUrl can expose the avatar and badge correctly.
		// FR: Renseigne le statut et la photo pour que getNomUrl affiche correctement l'avatar et le badge.
		$usertmp->statut = isset($obj->user_status) ? (int) $obj->user_status : $usertmp->statut;
		$usertmp->photo = $obj->user_photo;
		print '<td>'.$usertmp->getNomUrl(-1).'</td>';
	}

        if (!empty($arrayfields['t.entity']['checked'])) {
                $entityName = '';
                if (property_exists($obj, 'entity_label')) {
                        $entityName = trim((string) $obj->entity_label);
                }
                if ($entityName === '') {
                        // EN: Provide a fallback label when the entity dictionary does not expose a name.
                        // FR: Fournit un libellé de secours lorsque le dictionnaire d'entités n'expose pas de nom.
                        $entityName = $langs->trans('Entity').' #'.(int) $obj->entity;
                }
                // EN: Mimic the native Dolibarr rendering for entity badges in Multicompany context.
                // FR: Reproduit le rendu natif de Dolibarr pour les badges d'entité en contexte Multicompany.
                $entityBadge = '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityName).'</span></div>';
                print '<td class="center">'.$entityBadge.'</td>';
        }

        // Year
        if (!empty($arrayfields['t.year']['checked'])) {
                print '<td class="center">'.(int)$obj->year.'</td>';
        }
	// Week
	if (!empty($arrayfields['t.week']['checked'])) {
		print '<td class="center">'.(int)$obj->week.'</td>';
	}
	// Total hours
	if (!empty($arrayfields['t.total_hours']['checked'])) {
		$tot = (float) $obj->total_hours;
		$hh = floor($tot);
		$mm = round(($tot - $hh) * 60);
		if ($mm == 60) { $hh++; $mm = 0; }
		print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';
	}
	// Overtime
        if (!empty($arrayfields['t.overtime_hours']['checked'])) {
                $tot = (float) $obj->overtime_hours;
                $hh = floor($tot);
                $mm = round(($tot - $hh) * 60);
                if ($mm == 60) { $hh++; $mm = 0; }
                print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';
        }
        // EN: Render weekly zone counters.
        // FR: Affiche les compteurs hebdomadaires de zones.
        if (!empty($arrayfields['t.zone1_count']['checked'])) {
                print '<td class="right">'.(int)$obj->zone1_count.'</td>';
        }
        if (!empty($arrayfields['t.zone2_count']['checked'])) {
                print '<td class="right">'.(int)$obj->zone2_count.'</td>';
        }
        if (!empty($arrayfields['t.zone3_count']['checked'])) {
                print '<td class="right">'.(int)$obj->zone3_count.'</td>';
        }
        if (!empty($arrayfields['t.zone4_count']['checked'])) {
                print '<td class="right">'.(int)$obj->zone4_count.'</td>';
        }
        if (!empty($arrayfields['t.zone5_count']['checked'])) {
                print '<td class="right">'.(int)$obj->zone5_count.'</td>';
        }
        // EN: Render weekly meal counter.
        // FR: Affiche le compteur hebdomadaire de paniers.
        if (!empty($arrayfields['t.meal_count']['checked'])) {
                print '<td class="right">'.(int)$obj->meal_count.'</td>';
        }
        // Creation
        if (!empty($arrayfields['t.date_creation']['checked'])) {
                print '<td class="center">'.($obj->date_creation ? dol_print_date($db->jdate($obj->date_creation),'dayhour') : '').'</td>';
        }
        // EN: Display the approval date when the sheet has been validated.
        // FR: Affiche la date d'approbation lorsque la feuille a été validée.
        if (!empty($arrayfields['t.date_validation']['checked'])) {
                print '<td class="center">'.($obj->date_validation ? dol_print_date($db->jdate($obj->date_validation),'dayhour') : '').'</td>';
        }
        // Modification
        if (!empty($arrayfields['t.tms']['checked'])) {
                print '<td class="center">'.($obj->tms ? dol_print_date($db->jdate($obj->tms),'dayhour') : '').'</td>';
        }
	// Status (badge)
        if (!empty($arrayfields['t.status']['checked'])) {
                $tswstatic->status = $obj->status;
                print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>';
        }
        // EN: Accumulate values to expose totals at the bottom of the table.
        // FR: Cumule les valeurs pour afficher les totaux en bas du tableau.
        $totalsAccumulator['total_hours'] += (float) $obj->total_hours;
        $totalsAccumulator['overtime_hours'] += (float) $obj->overtime_hours;
        $totalsAccumulator['zone1_count'] += (int) $obj->zone1_count;
        $totalsAccumulator['zone2_count'] += (int) $obj->zone2_count;
        $totalsAccumulator['zone3_count'] += (int) $obj->zone3_count;
        $totalsAccumulator['zone4_count'] += (int) $obj->zone4_count;
        $totalsAccumulator['zone5_count'] += (int) $obj->zone5_count;
        $totalsAccumulator['meal_count'] += (int) $obj->meal_count;
        // Right selection checkbox column (if setting to put it right)
        if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
                print '<td class="nowrap center">';
                if ($massactionbutton || $massaction) {
                        $selected = in_array($obj->rowid, $arrayofselected) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
	}

        print '</tr>';
        $i++;
}

if ($imax > 0) {
        // EN: Render the totals row for hours, zones and meal counters.
        // FR: Affiche la ligne de totaux pour les heures, les zones et les paniers.
        $totalLabelPrinted = false;
        print '<tr class="liste_total">';
        if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
                print '<td class="liste_total">&nbsp;</td>';
        }
        if (!empty($arrayfields['t.ref']['checked'])) {
                print '<td class="liste_total">'.($totalLabelPrinted ? '&nbsp;' : $langs->trans('Total')).'</td>';
                $totalLabelPrinted = true;
        }
        if (!empty($arrayfields['user']['checked'])) {
                print '<td class="liste_total">'.($totalLabelPrinted ? '&nbsp;' : $langs->trans('Total')).'</td>';
                $totalLabelPrinted = true;
        }
        if (!empty($arrayfields['t.entity']['checked'])) {
                print '<td class="liste_total center">'.($totalLabelPrinted ? '&nbsp;' : $langs->trans('Total')).'</td>';
                $totalLabelPrinted = true;
        }
        if (!empty($arrayfields['t.year']['checked'])) {
                print '<td class="liste_total center">'.($totalLabelPrinted ? '&nbsp;' : $langs->trans('Total')).'</td>';
                $totalLabelPrinted = true;
        }
        if (!empty($arrayfields['t.week']['checked'])) {
                print '<td class="liste_total center">'.($totalLabelPrinted ? '&nbsp;' : $langs->trans('Total')).'</td>';
                $totalLabelPrinted = true;
        }
        if (!empty($arrayfields['t.total_hours']['checked'])) {
                $hours = (float) $totalsAccumulator['total_hours'];
                $hoursInt = floor($hours);
                $minutes = round(($hours - $hoursInt) * 60);
                if ($minutes == 60) { $hoursInt++; $minutes = 0; }
                $formattedHours = sprintf('%02d:%02d', $hoursInt, $minutes);
                print '<td class="liste_total right">'.$formattedHours.'</td>';
        }
        if (!empty($arrayfields['t.overtime_hours']['checked'])) {
                $hours = (float) $totalsAccumulator['overtime_hours'];
                $hoursInt = floor($hours);
                $minutes = round(($hours - $hoursInt) * 60);
                if ($minutes == 60) { $hoursInt++; $minutes = 0; }
                $formattedHours = sprintf('%02d:%02d', $hoursInt, $minutes);
                print '<td class="liste_total right">'.$formattedHours.'</td>';
        }
        if (!empty($arrayfields['t.zone1_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['zone1_count'].'</td>';
        }
        if (!empty($arrayfields['t.zone2_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['zone2_count'].'</td>';
        }
        if (!empty($arrayfields['t.zone3_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['zone3_count'].'</td>';
        }
        if (!empty($arrayfields['t.zone4_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['zone4_count'].'</td>';
        }
        if (!empty($arrayfields['t.zone5_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['zone5_count'].'</td>';
        }
        if (!empty($arrayfields['t.meal_count']['checked'])) {
                print '<td class="liste_total right">'.(int) $totalsAccumulator['meal_count'].'</td>';
        }
        if (!empty($arrayfields['t.date_creation']['checked'])) {
                print '<td class="liste_total center">&nbsp;</td>';
        }
        if (!empty($arrayfields['t.date_validation']['checked'])) {
                print '<td class="liste_total center">&nbsp;</td>';
        }
        if (!empty($arrayfields['t.tms']['checked'])) {
                print '<td class="liste_total center">&nbsp;</td>';
        }
        if (!empty($arrayfields['t.status']['checked'])) {
                print '<td class="liste_total center">&nbsp;</td>';
        }
        if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
                print '<td class="liste_total">&nbsp;</td>';
        }
        print '</tr>';
} else {
        $colspan = 1;
        foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
        print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

if ($massactionbutton || $massaction) {
	// EN: Close the Dolibarr mass action container to keep compatibility with the core JavaScript helpers.
	// FR: Termine le conteneur des actions de masse Dolibarr pour rester compatible avec les helpers JavaScript cœur.
	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_post.tpl.php';
}

print '</form>';

// EN: Align the limit selector styling and refresh logic with the native Dolibarr implementation.
// FR: Aligne le style et la logique de rafraîchissement du sélecteur de limite sur l'implémentation native de Dolibarr.
$script = <<<'JAVASCRIPT'
<script type="text/javascript">
jQuery(function ($) {
	// EN: Align the limit selector styling and refresh logic with the native Dolibarr implementation.
	// FR: Aligne le style et la logique de rafraîchissement du sélecteur de limite sur l'implémentation native de Dolibarr.
	var $limitSelect = $("select#limit");
	if ($limitSelect.length && $.fn.select2) {
		// EN: Reuse the select2 setup used in Dolibarr core lists to keep the same behaviour.
		// FR: Réutilise la configuration select2 des listes cœur Dolibarr pour conserver le même comportement.
		var normalizeString = function (value) {
			return (value || "").toLowerCase();
		};
		$limitSelect.select2({
			dir: "ltr",
			width: "resolve",
			minimumInputLength: 0,
			language: (typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,
			matcher: function (params, data) {
				if ($.trim(params.term) === "") {
					return data;
				}
				var term = normalizeString(params.term);
				var text = normalizeString(data.text || "");
				var keywords = term.split(' ');
				for (var i = 0; i < keywords.length; i++) {
					if (text.indexOf(keywords[i]) === -1) {
						return null;
					}
				}
				return data;
			},
			theme: "default limit",
			containerCssClass: ":all:",
			selectionCssClass: ":all:",
			dropdownCssClass: "ui-dialog",
			templateResult: function (data, container) {
				if (data.element) {
					$(container).addClass($(data.element).attr("class"));
				}
				if (data.id == "-1" && $(data.element).attr("data-html") == undefined) {
					return '&nbsp;';
				}
				if ($(data.element).attr("data-html") != undefined) {
					if (typeof htmlEntityDecodeJs === 'function') {
						return htmlEntityDecodeJs($(data.element).attr("data-html"));
					}
				}
				return data.text;
			},
			templateSelection: function (selection) {
				if (selection.id == "-1") {
					return '<span class=\"placeholder\">' + selection.text + '</span>';
				}
				return selection.text;
			},
			escapeMarkup: function (markup) {
				return markup;
			}
		});
	}
	// EN: Submit the parent form immediately when the limit changes to match Dolibarr lists.
	// FR: Soumet immédiatement le formulaire parent lors d'un changement de limite pour correspondre aux listes Dolibarr.
	$(".selectlimit").off("change.timesheetweekLimit").on("change.timesheetweekLimit", function () {
		var $current = $(this);
		var $targetForm = $current.parents('form:first');
		if (!$targetForm.length) {
			$targetForm = $("#searchFormList");
		}
		if ($targetForm.length) {
			var $limitHidden = $("#limit-hidden");
			if ($limitHidden.length) {
				$limitHidden.val($current.val());
			}
			$targetForm.submit();
		}
	});
	var $paginationArea = $(".pagination");
	if ($paginationArea.length) {
		// EN: Apply the Dolibarr black helper class on pagination containers and links.
		// FR: Applique la classe Dolibarr de couleur noire sur les conteneurs et liens de pagination.
		$paginationArea.addClass("colorblack");
		// EN: Keep the Dolibarr create button color by excluding action anchors from the black helper class.
		// FR: Préserve la couleur du bouton de création Dolibarr en excluant les ancres d'action de la classe noire.
		$paginationArea.find("a:not([class*='butAction'])").addClass("colorblack");
		// EN: Avoid forcing the icon span to black while keeping pagination counters dark.
		// FR: Évite de forcer la couleur noire sur l'icône tout en conservant les compteurs de pagination foncés.
		$paginationArea.find("span:not([class*='fa'])").addClass("colorblack");
	}
});
</script>
JAVASCRIPT;
print $script;

llxFooter();
$db->close();
