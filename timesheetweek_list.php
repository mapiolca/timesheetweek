<?php
/* Copyright (C) 2025  Pierre Ardoin
 * GPL v3
 */

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
$permSeal = $user->hasRight('timesheetweek','timesheetweek','seal');
$permValidate = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateOwn = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll = $user->hasRight('timesheetweek','timesheetweek','validateAll');
// EN: Prepare Dolibarr's generic permission flags for mass-action helpers.
// FR: Prépare les indicateurs de permission Dolibarr pour les helpers d'actions de masse.
$permissiontoread = ($permRead || $permReadChild || $permReadAll);
$permissiontoadd = ($permWrite || $permWriteChild || $permWriteAll);
$permissiontodelete = ($permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin));
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

/* ---- DROITS ---- */
$permRead = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll = $user->hasRight('timesheetweek','timesheetweek','readAll');
$permWrite = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll = $user->hasRight('timesheetweek','timesheetweek','writeAll');
$permDelete = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll = $user->hasRight('timesheetweek','timesheetweek','deleteAll');
$canSeeAllEmployees = (!empty($user->admin) || $permReadAll || $permWriteAll || $permDeleteAll);

if (!function_exists('tw_can_validate_timesheet_masslist')) {
	/**
	 * EN: Determine if the current user is allowed to validate the provided sheet.
	 * FR: Détermine si l'utilisateur courant est autorisé à valider la feuille fournie.
	 *
	 * @param TimesheetWeek $sheet	Sheet to evaluate
	 * @param User          $user	Current Dolibarr user
	 * @param bool          $permValidate	Direct validation right
	 * @param bool          $permValidateOwn	Validation on own sheets
	 * @param bool          $permValidateChild	Validation on subordinate sheets
	 * @param bool          $permValidateAll	Global validation right
	 * @param bool          $permWrite	Write right on own sheets
	 * @param bool          $permWriteChild	Write right on subordinate sheets
	 * @param bool          $permWriteAll	Global write right
	 * @return bool	True when validation is authorised
	 */
	function tw_can_validate_timesheet_masslist(
		TimesheetWeek $sheet,
		User $user,
		$permValidate,
		$permValidateOwn,
		$permValidateChild,
		$permValidateAll,
		$permWrite,
		$permWriteChild,
		$permWriteAll
	) {
		// EN: Check explicit validation rights first to keep the behaviour consistent with the card view.
		// FR: Vérifie d'abord les droits explicites de validation pour rester cohérent avec la fiche détaillée.
		$hasExplicitValidation = ($permValidate || $permValidateOwn || $permValidateChild || $permValidateAll);

		if (!empty($user->admin)) {
			$permValidateAll = true;
			$hasExplicitValidation = true;
		}

		if (!$hasExplicitValidation) {
			// EN: Reuse write permissions when legacy configurations rely on them for validation.
			// FR: Réutilise les permissions d'écriture lorsque les anciennes configurations s'en servent pour valider.
			if ($permWriteAll) {
				$permValidateAll = true;
			}
			if ($permWriteChild) {
				$permValidateChild = true;
			}
			if ($permWrite || $permWriteChild || $permWriteAll) {
				if ((int) $sheet->fk_user_valid === (int) $user->id) {
					$permValidate = true;
				}
				if (!$permValidateChild && $permWriteChild) {
					$permValidateChild = true;
				}
			}
		}

		if ($permValidateAll) {
			return true;
		}
		if ($permValidateChild && tw_is_manager_of($sheet->fk_user, $user)) {
			return true;
		}
		if ($permValidateOwn && ((int) $user->id === (int) $sheet->fk_user)) {
			return true;
		}
		if ($permValidate && ((int) $user->id === (int) $sheet->fk_user_valid)) {
			return true;
		}

		return false;
	}
}
/**
 * Params
 */
$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$confirm      = GETPOST('confirm', 'alpha');
$cancel       = GETPOST('cancel', 'alpha');
$toselect     = GETPOST('toselect', 'array', 2);
$contextpage  = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

$sortfield    = GETPOST('sortfield', 'aZ09comma');
$sortorder    = GETPOST('sortorder', 'aZ09comma');
$page         = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0) $page = 0;
$limit        = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset       = $limit * $page;

$search_ref   = trim(GETPOST('search_ref','alphanohtml'));
$search_user  = GETPOSTINT('search_user');
$search_year  = GETPOSTINT('search_year');
$search_week  = GETPOSTINT('search_week');

$multicompanyEnabled = !empty($conf->multicompany->enabled);

/* Filtres multi entité/semaine ISO */
$search_entities = array();
if ($multicompanyEnabled) {
	$tmp = GETPOST('search_entity', 'array', 2);
	if (!is_array($tmp)) { $one = GETPOSTINT('search_entity'); $tmp = $one>0 ? array($one) : array(); }
	foreach ($tmp as $eid) { $eid=(int)$eid; if ($eid>=0 && !in_array($eid,$search_entities,true)) $search_entities[]=$eid; }
}
$rawWeekyearFilter = GETPOST('search_weekyear', 'array', 2);
if (!is_array($rawWeekyearFilter)) {
	$legacyWeekyear = trim(GETPOST('search_weekyear', 'alphanohtml'));
	$rawWeekyearFilter = $legacyWeekyear !== '' ? array($legacyWeekyear) : array();
}
$search_weekyears = array(); $searchWeekTuples = array();
foreach ($rawWeekyearFilter as $candidate) {
	$candidate = trim((string)$candidate);
	if ($candidate === '') continue;
	if (preg_match('/^(\d{4})-W(\d{2})$/', $candidate, $m)) {
		$iso = $m[1].'-W'.$m[2];
		if (!in_array($iso,$search_weekyears,true)) { $search_weekyears[]=$iso; $searchWeekTuples[] = array('year'=>(int)$m[1],'week'=>(int)$m[2]); }
	}
}

/* ---- OBJETS ---- */
$form = new Form($db);
$tswstatic = new TimesheetWeek($db);
$usertmp   = new User($db);

/* ---- ARRAYFIELDS ---- */
$arrayfields = array(
	't.ref'         => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'user'          => array('label'=>$langs->trans("Employee"), 'checked'=>1),
);
if ($multicompanyEnabled) $arrayfields['t.entity'] = array('label'=>$langs->trans('Entity'), 'checked'=>1);
$arrayfields += array(
	't.year'        => array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'        => array('label'=>$langs->trans("Week"), 'checked'=>1),
	't.total_hours' => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.status'      => array('label'=>$langs->trans("Status"), 'checked'=>1),
);
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

/**
* Mass actions (UI)
*/
$arrayofmassactions = array();
// EN: Offer approval when the user holds validation or equivalent legacy permissions.
// FR: Propose l'approbation lorsque l'utilisateur dispose des droits de validation ou équivalents hérités.
$canDisplayValidationActions = (
	$permValidate || $permValidateOwn || $permValidateChild || $permValidateAll ||
	$permWrite || $permWriteChild || $permWriteAll || !empty($user->admin)
);
if ($canDisplayValidationActions) {
	$arrayofmassactions['approve_selection'] = img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans('ApproveSelection');
	$arrayofmassactions['refuse_selection'] = img_picto('', 'uncheck', 'class="pictofixedwidth"').$langs->trans('RefuseSelection');
}
// EN: Display the sealing control only to users granted with the dedicated right.
// FR: Affiche le contrôle de scellement uniquement pour les utilisateurs disposant du droit dédié.
if ($permSeal) {
	$arrayofmassactions['sceller'] = img_picto('', 'lock', 'class="pictofixedwidth"').$langs->trans('SealSelection');
}
// EN: Expose the draft-only bulk deletion with Dolibarr's confirmation flow when the operator may delete sheets.
// FR: Expose la suppression massive limitée aux brouillons avec la confirmation Dolibarr lorsque l'opérateur peut supprimer des feuilles.
if ($permissiontodelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans('DeleteSelection');
}

$massactionbutton = $form->selectMassAction($massaction, $arrayofmassactions);
$objectclass = 'TimesheetWeek';
$objectlabel = 'TimesheetWeek';
$object = new TimesheetWeek($db);

$uploaddir = !empty($conf->timesheetweek->multidir_output[$conf->entity] ?? null)
? $conf->timesheetweek->multidir_output[$conf->entity]
: (!empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek');
$upload_dir = $uploaddir;

// Affiche le menu d’actions
$showmassactionbutton = 1;

include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

// EN: Normalise the selected identifiers provided by Dolibarr's mass-action handler.
// FR: Normalise les identifiants sélectionnés fournis par le gestionnaire d'actions de masse de Dolibarr.
$arrayofselected = is_array($toselect) ? $toselect : array();

$massActionProcessed = false;

if ($massaction === 'approve_selection') {
	$massActionProcessed = true;
	if (!$canDisplayValidationActions) {
		// EN: Stop the approval when the operator lacks validation permissions.
		// FR: Empêche l'approbation lorsque l'opérateur n'a pas les permissions de validation.
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin();
		$ok = 0;
		$ko = array();
		foreach ((array) $arrayofselected as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) {
				$ko[] = '#'.$id;
				continue;
			}
			if (!tw_can_validate_timesheet_masslist($o, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
				// EN: Reject the sheet when the current user cannot validate it according to delegation rules.
				// FR: Rejette la feuille lorsque l'utilisateur courant ne peut pas la valider selon les règles de délégation.
				$ko[] = $o->ref ?: '#'.$id;
				continue;
			}
			$res = $o->approve($user);
			if ($res > 0) {
				$ok++;
			} else {
				$ko[] = $o->ref ?: '#'.$id;
			}
		}
		if ($ko) {
			$db->rollback();
		} else {
			$db->commit();
		}
		if ($ok) {
			setEventMessages($langs->trans('TimesheetWeekMassApproveSuccess', $ok), null, 'mesgs');
		}
		if ($ko) {
			setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
		}
	}
}

if ($massaction === 'refuse_selection') {
	$massActionProcessed = true;
	if (!$canDisplayValidationActions) {
		// EN: Prevent the refusal when the operator is not authorised to validate sheets.
		// FR: Empêche le refus lorsque l'opérateur n'est pas autorisé à valider les feuilles.
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin();
		$ok = 0;
		$ko = array();
		foreach ((array) $arrayofselected as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) {
				$ko[] = '#'.$id;
				continue;
			}
			if (!tw_can_validate_timesheet_masslist($o, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
				// EN: Skip the refusal when the user cannot manage the employee under current rights.
				// FR: Ignore le refus lorsque l'utilisateur ne peut pas gérer l'employé avec les droits actuels.
				$ko[] = $o->ref ?: '#'.$id;
				continue;
			}
			$res = $o->refuse($user);
			if ($res > 0) {
				$ok++;
			} else {
				$ko[] = $o->ref ?: '#'.$id;
			}
		}
		if ($ko) {
			$db->rollback();
		} else {
			$db->commit();
		}
		if ($ok) {
			setEventMessages($langs->trans('TimesheetWeekMassRefuseSuccess', $ok), null, 'mesgs');
		}
		if ($ko) {
			setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
		}
	}
}

if ($massaction === 'sceller') {
	$massActionProcessed = true;
	if (!$permSeal) {
		// EN: Refuse sealing when the operator does not own the dedicated right.
		// FR: Refuse le scellement lorsque l'opérateur ne possède pas le droit dédié.
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin();
		$ok = 0;
		$ko = array();
		foreach ((array) $arrayofselected as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) {
				$ko[] = '#'.$id;
				continue;
			}
			if (!tw_can_validate_timesheet_masslist($o, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
				// EN: Keep the sheet untouched when the manager cannot act on the employee scope.
				// FR: Laisse la feuille inchangée lorsque le gestionnaire ne peut pas agir sur le périmètre de l'employé.
				$ko[] = $o->ref ?: '#'.$id;
				continue;
			}
			$res = $o->seal($user);
			if ($res > 0) {
				$ok++;
			} else {
				$ko[] = $o->ref ?: '#'.$id;
			}
		}
		if ($ko) {
			$db->rollback();
		} else {
			$db->commit();
		}
		if ($ok) {
			setEventMessages($langs->trans('TimesheetWeekMassSealSuccess', $ok), null, 'mesgs');
		}
		if ($ko) {
			setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
		}
	}
}
if ($massaction === 'delete') {
	$massActionProcessed = true;
	if (!$permissiontodelete) {
		// EN: Block the deletion when the user lacks the necessary rights.
		// FR: Bloque la suppression lorsque l'utilisateur ne dispose pas des droits nécessaires.
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin();
		$ok = 0;
		$ko = array();
		$nonDraftDetected = false;
		foreach ((array) $arrayofselected as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) {
				$ko[] = '#'.$id;
				continue;
			}
			if (!tw_can_act_on_user($o->fk_user, $permDelete, $permDeleteChild, ($permDeleteAll || !empty($user->admin)), $user)) {
				// EN: Prevent deletion outside the managerial scope defined by Dolibarr rights.
				// FR: Empêche la suppression en dehors du périmètre managérial défini par les droits Dolibarr.
				$ko[] = $o->ref ?: '#'.$id;
				continue;
			}
			if ((int) $o->status !== TimesheetWeek::STATUS_DRAFT) {
				// EN: Enforce the draft-only restriction required for bulk deletions.
				// FR: Applique la restriction aux brouillons exigée pour les suppressions massives.
				$ko[] = $o->ref ?: '#'.$id;
				$nonDraftDetected = true;
				continue;
			}
			$res = $o->delete($user);
			if ($res > 0) {
				$ok++;
			} else {
				$ko[] = ($o->ref ?: '#'.$id);
			}
		}
		if ($ko) {
			$db->rollback();
		} else {
			$db->commit();
		}
		if ($ok) {
			setEventMessages($langs->trans('RecordsDeleted', $ok), null, 'mesgs');
		}
		if ($ko) {
			setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
		}
		if ($nonDraftDetected) {
			// EN: Inform the operator that only draft sheets are eligible for removal.
			// FR: Informe l'opérateur que seules les feuilles en brouillon sont éligibles à la suppression.
			setEventMessages($langs->trans('TimesheetWeekMassDeleteOnlyDraft'), null, 'errors');
		}
	}
	$massaction = '';
}

if ($massActionProcessed) {
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

$sqlfields = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours,"
            .($multicompanyEnabled ? " t.entity, e.label as entity_label," : "")
            ." u.rowid as uid, u.firstname, u.lastname, u.login, u.photo as user_photo, u.statut as user_status";
$sql  = $sqlfields;
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
if ($multicompanyEnabled) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON e.rowid = t.entity";
$sql .= " WHERE 1=1";
$sql .= " AND t.entity IN (".getEntity('timesheetweek').")";

if (!$canSeeAllEmployees) {
	// borne simple: restreindre à l'utilisateur courant
	$sql .= ' AND t.fk_user = '.((int)$user->id);
}
if ($search_ref !== '')     $sql .= natural_search('t.ref', $search_ref);
if ($search_user > 0)       $sql .= " AND t.fk_user = ".((int)$search_user);
if ($search_year > 0)       $sql .= " AND t.year = ".((int)$search_year);
if ($search_week > 0)       $sql .= " AND t.week = ".((int)$search_week);
if (!empty($searchWeekTuples)) {
	$conds=array(); foreach ($searchWeekTuples as $tp) $conds[]='(t.year='.(int)$tp['year'].' AND t.week='.(int)$tp['week'].')';
	if ($conds) $sql .= ' AND ('.implode(' OR ',$conds).')';
}
if ($multicompanyEnabled && !empty($search_entities)) $sql .= ' AND t.entity IN ('.implode(',', array_map('intval',$search_entities)).')';

$sqlList = $sql.$db->order($sortfield,$sortorder).$db->plimit($limit+1,$offset);
$resql = $db->query($sqlList);
if (!$resql) dol_print_error($db);
$num = $resql ? $db->num_rows($resql) : 0;

/* ---- VUE ---- */
$title = $langs->trans("TimesheetWeek_List");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist page-list');

/* Param barre */
$param = '';
if ($search_ref)  $param .= '&search_ref='.urlencode($search_ref);
if ($search_user) $param .= '&search_user='.(int)$search_user;
if ($search_year) $param .= '&search_year='.(int)$search_year;
if ($search_week) $param .= '&search_week='.(int)$search_week;
foreach ($search_weekyears as $iso) $param .= '&search_weekyear[]='.urlencode($iso);
foreach ($search_entities as $eid) $param .= '&search_entity[]='.(int)$eid;
$param .= '&limit='.(int)$limit;

/* Massactions disponibles */
$arrayofmassactions = array();
if ($permissiontodelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete','class="pictofixedwidth"').$langs->trans("DeleteSelection");
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions); // pas de champ hidden massaction

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create', '', $permWrite || $permWriteChild || $permWriteAll);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="limit" id="limit-hidden" value="'.((int)$limit).'">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, '', 'bookcal', 0, $newcardbutton, '', $limit, 0, 0, 1);

/* Confirmation standard Dolibarr */
$topicmail = "SendTimesheetWeekRef";
$modelmail = "timesheetweek";
$objecttmp = new TimesheetWeek($db);
$trackid = 'tsw'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

/* Sélecteur colonnes et boutons de sélection */
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedfields = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

$topicmail = "SendTimesheetWeekRef";
$modelmail = "timesheetweek";
$objecttmp = new TimesheetWeek($db);
$trackid = 'tsw'.$object->id;
// EN: Display Dolibarr's standard confirmation prompts for mass actions.
// FR: Affiche les fenêtres de confirmation standard de Dolibarr pour les actions de masse.
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

/* Ligne filtres */
print '<tr class="liste_titre_filter">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="12"></td>';
}
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	$employeeSelectSelected = $search_user>0?$search_user:'';
	$employeeSelectHtml = $form->select_dolusers($employeeSelectSelected, 'search_user', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1);
	print $employeeSelectHtml;
	print '</td>';
}
if (!empty($arrayfields['t.entity']['checked'])) {
	print '<td class="liste_titre center">';
	if ($multicompanyEnabled) {
		// options d'entité
		$opts=array();
		$resEnt = $db->query('SELECT rowid,label FROM '.MAIN_DB_PREFIX.'entity ORDER BY label');
		if ($resEnt) { while ($r=$db->fetch_object($resEnt)) { $opts[(int)$r->rowid]=($r->label?:$langs->trans('Entity').' #'.$r->rowid); } $db->free($resEnt); }
		print $form->multiselectarray('search_entity', $opts, $search_entities, 0, 0, 'minwidth150 maxwidth200', 0, 0, '', '', '', '', '', 1);
	} else print '&nbsp;';
	print '</td>';
}
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre center"><input class="flat" type="number" name="search_year" value="'.($search_year>0?(int)$search_year:'').'" style="width:80px"></td>';
}
if (!empty($arrayfields['t.week']['checked'])) {
	$selectorYear = $search_year>0?$search_year:0;
	$selectedWeekValues = $search_weekyears;
	if (empty($selectedWeekValues) && $search_week>0 && $search_year>0) $selectedWeekValues[] = sprintf('%04d-W%02d',$search_year,$search_week);
	print '<td class="liste_titre center">'.getWeekSelectorDolibarr($form,'search_weekyear',$selectedWeekValues,$selectorYear,true,true).'</td>';
}
if (!empty($arrayfields['t.total_hours']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.status']['checked'])) print '<td class="liste_titre center">&nbsp;</td>';
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('right').'</td>';
}
print '</tr>'."\n";

/* Titres */
print '<tr class="liste_titre">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'maxwidthsearch center ');
}
if (!empty($arrayfields['t.ref']['checked']))   print_liste_field_titre($arrayfields['t.ref']['label'],   $_SERVER["PHP_SELF"], "t.ref",   "", $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['user']['checked']))    print_liste_field_titre($arrayfields['user']['label'],    $_SERVER["PHP_SELF"], "u.lastname", "", $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.entity']['checked']))print_liste_field_titre($arrayfields['t.entity']['label'],$_SERVER["PHP_SELF"], "t.entity","", $param, '', $sortfield, $sortorder,'center ');
if (!empty($arrayfields['t.year']['checked']))  print_liste_field_titre($arrayfields['t.year']['label'],  $_SERVER["PHP_SELF"], "t.year",  "", $param, '', $sortfield, $sortorder,'center ');
if (!empty($arrayfields['t.week']['checked']))  print_liste_field_titre($arrayfields['t.week']['label'],  $_SERVER["PHP_SELF"], "t.week",  "", $param, '', $sortfield, $sortorder,'center ');
if (!empty($arrayfields['t.total_hours']['checked'])) print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", "", $param, '', $sortfield, $sortorder,'right ');
if (!empty($arrayfields['t.status']['checked']))print_liste_field_titre($arrayfields['t.status']['label'],$_SERVER["PHP_SELF"], "t.status","", $param, '', $sortfield, $sortorder,'center ');
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
print '</tr>'."\n";

/* Lignes */
$i = 0; $imax = ($limit ? min($num, $limit) : $num);
while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, (array)$toselect) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
	}

	if (!empty($arrayfields['t.ref']['checked'])) {
		$tswstatic->id = $obj->rowid;
		$tswstatic->ref = $obj->ref;
		$tswstatic->status = $obj->status;
		print '<td>'.$tswstatic->getNomUrl(1,'ref').'</td>';
	}
	if (!empty($arrayfields['user']['checked'])) {
		$usertmp->id = $obj->uid;
		$usertmp->firstname = $obj->firstname;
		$usertmp->lastname = $obj->lastname;
		$usertmp->login = $obj->login;
		$usertmp->statut = (int)$obj->user_status;
		$usertmp->photo = $obj->user_photo;
		print '<td>'.$usertmp->getNomUrl(-1).'</td>';
	}
	if (!empty($arrayfields['t.entity']['checked'])) {
		$entityName = property_exists($obj,'entity_label') ? trim((string)$obj->entity_label) : '';
		if ($entityName==='') $entityName = $langs->trans('Entity').' #'.(int)$obj->entity;
		print '<td class="center">'.dol_escape_htmltag($entityName).'</td>';
	}
	if (!empty($arrayfields['t.year']['checked']))  print '<td class="center">'.(int)$obj->year.'</td>';
	if (!empty($arrayfields['t.week']['checked']))  print '<td class="center">'.(int)$obj->week.'</td>';

	if (!empty($arrayfields['t.total_hours']['checked'])) {
		$tot = (float)$obj->total_hours; $hh=floor($tot); $mm=round(($tot-$hh)*60); if ($mm==60){$hh++;$mm=0;}
		print '<td class="right">'.sprintf('%02d:%02d',$hh,$mm).'</td>';
	}

	if (!empty($arrayfields['t.status']['checked'])) {
		$tswstatic->status = $obj->status;
		print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>';
	}

	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, (array)$toselect) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
	}

	print '</tr>';
	$i++;
}

if ($imax == 0) {
	$colspan = 1; foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

/* JS select limit */
$script = <<<'JAVASCRIPT'
<script type="text/javascript">
jQuery(function ($) {
	$(".selectlimit").off("change.timesheetweekLimit").on("change.timesheetweekLimit", function () {
		var $current = $(this), $form = $current.closest("form");
		if (!$form.length) $form = $("#searchFormList");
		if ($form.length) { $("#limit-hidden").val($current.val()); $form.submit(); }
	});
});
</script>
JAVASCRIPT;
print $script;

llxFooter();
$db->close();
