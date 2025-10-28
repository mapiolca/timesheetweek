<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

/* ----- DROITS ----- */
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
$canSeeAllEmployees = (!empty($user->admin) || $permReadAll || $permWriteAll || $permDeleteAll || $permValidateAll);
$permViewAny = ($permRead || $permReadChild || $permReadAll || $permWrite || $permWriteChild || $permWriteAll || $permDelete || $permDeleteChild || $permDeleteAll || $permValidate || $permValidateOwn || $permValidateChild || $permValidateAll || !empty($user->admin));
if (!$permViewAny) accessforbidden();

/* ----- REQUIRES ----- */
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','other','users'));

/* ----- PARAMS ----- */
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

$rawWeekyearFilter = GETPOST('search_weekyear', 'array', 2);
if (!is_array($rawWeekyearFilter)) {
	$legacyWeekyear = trim(GETPOST('search_weekyear', 'alphanohtml'));
	$rawWeekyearFilter = $legacyWeekyear !== '' ? array($legacyWeekyear) : array();
}
$search_weekyears = array(); $searchWeekTuples = array();
foreach ($rawWeekyearFilter as $candidateWeekyear) {
	$candidateWeekyear = trim((string) $candidateWeekyear);
	if ($candidateWeekyear === '') continue;
	if (preg_match('/^(\d{4})-W(\d{2})$/', $candidateWeekyear, $m)) {
		$isoWeekyear = $m[1].'-W'.$m[2];
		if (!in_array($isoWeekyear, $search_weekyears, true)) {
			$search_weekyears[] = $isoWeekyear;
			$searchWeekTuples[] = array('year' => (int)$m[1], 'week' => (int)$m[2]);
		}
	}
}

/* Multicompany */
$multicompanyEnabled = !empty($conf->multicompany->enabled);
$search_entities = array();
if ($multicompanyEnabled) {
	$rawEntityFilter = GETPOST('search_entity', 'array', 2);
	if (!is_array($rawEntityFilter)) {
		$legacyEntity = GETPOSTINT('search_entity');
		$rawEntityFilter = $legacyEntity > 0 ? array($legacyEntity) : array();
	}
	foreach ($rawEntityFilter as $entityCandidate) {
		$entityId = (int)$entityCandidate;
		if ($entityId >= 0 && !in_array($entityId, $search_entities, true)) $search_entities[] = $entityId;
	}
}

/* Status */
$search_status = GETPOST('search_status', 'array', 2);
$search_status = is_array($search_status) ? $search_status : array();
$hasStatusRequest = function_exists('GETPOSTISSET') ? GETPOSTISSET('search_status') : (isset($_GET['search_status']) || isset($_POST['search_status']));
if (!$hasStatusRequest) {
	$rawStatus = GETPOST('search_status', 'alpha');
	if (!empty($rawStatus)) $search_status = array_map('trim', explode(',', $rawStatus));
}
$search_status = array_values(array_unique(array_filter(array_map('strval', $search_status), function($v){return $v!=='' && $v!=='-1';})));

/* ----- OBJETS ----- */
$form = new Form($db);
$tswstatic = new TimesheetWeek($db);
$usertmp = new User($db);

/* Allowed users */
$allowedUserIds = array();
if (!$canSeeAllEmployees) {
	if ($permRead || $permWrite || $permDelete || $permValidate || $permValidateOwn) $allowedUserIds[] = (int)$user->id;
	if ($permReadChild || $permWriteChild || $permDeleteChild || $permValidateChild) $allowedUserIds = array_merge($allowedUserIds, tw_get_user_child_ids($user));
	$allowedUserIds = array_values(array_unique(array_filter($allowedUserIds, function($id){return (int)$id>0;})));
}

/* Entity options */
$entityFilterOptions = array();
if ($multicompanyEnabled) {
	$allowedEntityIds = array();
	$allowedEntityString = getEntity('timesheetweek');
	if (!empty($allowedEntityString)) {
		foreach (explode(',', $allowedEntityString) as $c) {
			$c = trim($c); if ($c==='') continue;
			$cid = (int)$c; if ($cid>0 && !in_array($cid,$allowedEntityIds,true)) $allowedEntityIds[]=$cid;
		}
	}
	$sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX."entity";
	if (!empty($allowedEntityIds)) $sqlEntity .= ' WHERE rowid IN ('.implode(',', $allowedEntityIds).')';
	$sqlEntity .= ' ORDER BY label ASC';
	$resEntity = $db->query($sqlEntity);
	if ($resEntity) { while ($er = $db->fetch_object($resEntity)) { $entityFilterOptions[(int)$er->rowid] = trim((string)$er->label) ?: $langs->trans('Entity').' #'.(int)$er->rowid; } $db->free($resEntity); }
}

/* ----- Colonnes ----- */
$arrayfields = array(
	't.ref'          => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'user'           => array('label'=>$langs->trans("Employee"), 'checked'=>1),
);
if ($multicompanyEnabled) $arrayfields['t.entity'] = array('label'=>$langs->trans('Entity'), 'checked'=>1);
$arrayfields += array(
	't.year'         => array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'         => array('label'=>$langs->trans("Week"), 'checked'=>1),
	't.total_hours'  => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.overtime_hours'=>array('label'=>$langs->trans("Overtime"), 'checked'=>0),
	't.zone1_count'  => array('label'=>$langs->trans("Zone1Count"), 'checked'=>0),
	't.zone2_count'  => array('label'=>$langs->trans("Zone2Count"), 'checked'=>0),
	't.zone3_count'  => array('label'=>$langs->trans("Zone3Count"), 'checked'=>0),
	't.zone4_count'  => array('label'=>$langs->trans("Zone4Count"), 'checked'=>0),
	't.zone5_count'  => array('label'=>$langs->trans("Zone5Count"), 'checked'=>0),
	't.meal_count'   => array('label'=>$langs->trans("MealCount"), 'checked'=>0),
	't.date_creation'=> array('label'=>$langs->trans("DateCreation"), 'checked'=>0),
	't.date_validation'=> array('label'=>$langs->trans("DateValidation"), 'checked'=>0),
	't.tms'          => array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0),
	't.status'       => array('label'=>$langs->trans("Status"), 'checked'=>1),
);
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

/* ----- SQL LISTE ----- */
if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";

$sqlfields = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours,"
	.($multicompanyEnabled ? " t.entity, e.label as entity_label," : "")
	." t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count,"
	." t.date_creation, t.tms, t.date_validation, t.fk_user_valid,"
	." u.rowid as uid, u.firstname, u.lastname, u.login, u.photo as user_photo, u.statut as user_status";
$sql = $sqlfields." FROM ".MAIN_DB_PREFIX."timesheet_week as t"
	." LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user"
	.($multicompanyEnabled ? " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON e.rowid = t.entity" : "")
	." WHERE 1=1"
	." AND t.entity IN (".getEntity('timesheetweek').")";

if (!$canSeeAllEmployees) {
	if (!empty($allowedUserIds)) $sql .= ' AND t.fk_user IN ('.implode(',', $allowedUserIds).')';
	else $sql .= ' AND 1=0';
}
if ($search_ref !== '')     $sql .= natural_search('t.ref', $search_ref);
if ($search_user > 0)       $sql .= " AND t.fk_user = ".((int)$search_user);
if ($search_year > 0)       $sql .= " AND t.year = ".((int)$search_year);
if ($search_week > 0)       $sql .= " AND t.week = ".((int)$search_week);
if (!empty($searchWeekTuples)) {
	$conds = array(); foreach ($searchWeekTuples as $tp) $conds[]='(t.year='.(int)$tp['year'].' AND t.week='.(int)$tp['week'].')';
	if ($conds) $sql .= ' AND ('.implode(' OR ', $conds).')';
}
if ($multicompanyEnabled && !empty($search_entities)) $sql .= ' AND t.entity IN ('.implode(',', array_map('intval',$search_entities)).')';
if (!empty($search_status)) $sql .= ' AND t.status IN ('.implode(',', array_map('intval',$search_status)).')';

$sqlList = $sql.$db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$resql = $db->query($sqlList);
if (!$resql) dol_print_error($db);
$num = $resql ? $db->num_rows($resql) : 0;

/* ----- HEADER ----- */
$title = $langs->trans("TimesheetWeek_List");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist page-list');

/* ----- Barre params ----- */
$param = '';
if ($search_ref)   $param .= '&search_ref='.urlencode($search_ref);
if ($search_user)  $param .= '&search_user='.(int)$search_user;
if ($search_year)  $param .= '&search_year='.(int)$search_year;
if ($search_week)  $param .= '&search_week='.(int)$search_week;
if (!empty($search_weekyears)) foreach ($search_weekyears as $iso) $param .= '&search_weekyear[]='.urlencode($iso);
if ($multicompanyEnabled && !empty($search_entities)) foreach ($search_entities as $eid) $param .= '&search_entity[]='.(int)$eid;
if (!empty($search_status)) foreach ($search_status as $sv) $param .= '&search_status[]='.(int)$sv;
$param .= '&limit='.(int)$limit;

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?action=create', '', $user->hasRight('timesheetweek','timesheetweek','write'));

/* ----- FORM OUVERTURE + MASSACTIONS ----- */
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="massaction" value="">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="limit" id="limit-hidden" value="'.((int) $limit).'">';

/* Déclaration des massactions */
$arrayofmassactions = array(
	'approve_selection' => img_picto('', 'validate', 'class="pictofixedwidth"').$langs->trans("ApproveSelection"),
	'refuse_selection'  => img_picto('', 'warning',  'class="pictofixedwidth"').$langs->trans("RefuseSelection"),
	'predelete'         => img_picto('', 'delete',   'class="pictofixedwidth"').$langs->trans("DeleteSelection"),
);
$massactionbutton = $form->selectMassAction($massaction, $arrayofmassactions);

/* Contexte requis avant include */
$showmassactionbutton = 1;
$objectclass = 'TimesheetWeek';
$objectlabel = 'TimesheetWeek';
$object      = new TimesheetWeek($db);
$permissiontoread   = ($permRead || $permReadChild || $permReadAll);
$permissiontoadd    = ($permWrite || $permWriteChild || $permWriteAll);
$permissiontodelete = ($permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin));
$uploaddir  = !empty($conf->timesheetweek->multidir_output[$conf->entity] ?? null)
	? $conf->timesheetweek->multidir_output[$conf->entity]
	: (!empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek');
$upload_dir = $uploaddir;

/* Include générique: gère selectall, confirmations, map predelete->delete */
include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

/* Affiche la boîte de confirmation si générée */
if ($massactionbutton || $massaction) print $formconfirm;

/* ----- Handlers serveurs ----- */
$arrayofselected = is_array($toselect) ? $toselect : array();

if ($massaction === 'approve_selection') {
	$db->begin(); $ok=0; $ko=array();
	foreach ((array)$arrayofselected as $id) {
		$o = new TimesheetWeek($db);
		if ($o->fetch((int)$id) <= 0) { $ko[] = '#'.$id; continue; }
		$res = $o->approve($user);
		if ($res > 0) $ok++; else $ko[] = $o->ref ?: '#'.$id;
	}
	if ($ko) $db->rollback(); else $db->commit();
	if ($ok) setEventMessages($langs->trans('TimesheetWeekMassApproveSuccess', $ok), null, 'mesgs');
	if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
	$massaction = '';
}

if ($massaction === 'refuse_selection') {
	$db->begin(); $ok=0; $ko=array();
	foreach ((array)$arrayofselected as $id) {
		$o = new TimesheetWeek($db);
		if ($o->fetch((int)$id) <= 0) { $ko[] = '#'.$id; continue; }
		$res = $o->refuse($user);
		if ($res > 0) $ok++; else $ko[] = $o->ref ?: '#'.$id;
	}
	if ($ko) $db->rollback(); else $db->commit();
	if ($ok) setEventMessages($langs->trans('TimesheetWeekMassRefuseSuccess', $ok), null, 'mesgs');
	if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
	$massaction = '';
}

/* predelete -> delete après clic Oui */
if ($massaction === 'delete') {
	if (!$permissiontodelete) {
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin(); $ok=0; $ko=array();
		foreach ((array)$arrayofselected as $id) {
			$o = new TimesheetWeek($db);
			if ($o->fetch((int)$id) <= 0) { $ko[] = '#'.$id; continue; }
			$res = $o->delete($user);
			if ($res > 0) $ok++; else $ko[] = $o->ref ?: '#'.$id;
		}
		if ($ko) $db->rollback(); else $db->commit();
		if ($ok) setEventMessages($langs->trans('RecordsDeleted', $ok), null, 'mesgs');
		if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ', $ko)), null, 'errors');
	}
	$massaction = '';
}

/* ----- Barre liste ----- */
$nbtotalofrecords = '';
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bookcal', 0, $newcardbutton, '', $limit, 0, 0, 1);

/* ----- Tableau ----- */
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedfields = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

/* Filtres */
print '<tr class="liste_titre_filter">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="12"></td>';
}
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	$employeeSelectSelected = $search_user > 0 ? $search_user : '';
	$employeeSelectHtml = $form->select_dolusers($employeeSelectSelected, 'search_user', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1);
	if (!$canSeeAllEmployees) $employeeSelectHtml = tw_filter_select_by_user_ids($employeeSelectHtml, $allowedUserIds, $search_user);
	print tw_strip_user_id_from_select($employeeSelectHtml);
	print '</td>';
}
if (!empty($arrayfields['t.entity']['checked'])) {
	print '<td class="liste_titre center">'.(!empty($entityFilterOptions) ? $form->multiselectarray('search_entity', $entityFilterOptions, $search_entities, 0, 0, 'minwidth150 maxwidth200', 0, 0, '', '', '', '', '', 1) : '&nbsp;').'</td>';
}
if (!empty($arrayfields['t.year']['checked'])) print '<td class="liste_titre center"><input class="flat" type="number" name="search_year" value="'.($search_year>0?(int)$search_year:'').'" style="width:80px"></td>';
if (!empty($arrayfields['t.week']['checked'])) {
	$currentWeekSelectorYear = $search_year > 0 ? $search_year : (empty($searchWeekTuples) ? 0 : (int)$searchWeekTuples[0]['year']);
	$selectedWeekValues = $search_weekyears;
	if (empty($selectedWeekValues) && $search_week > 0 && $search_year > 0) $selectedWeekValues[] = sprintf('%04d-W%02d', $search_year, $search_week);
	print '<td class="liste_titre center">'.getWeekSelectorDolibarr($form, 'search_weekyear', $selectedWeekValues, $currentWeekSelectorYear, true, true).'</td>';
}
if (!empty($arrayfields['t.total_hours']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.overtime_hours']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.zone1_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.zone2_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.zone3_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.zone4_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.zone5_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.meal_count']['checked'])) print '<td class="liste_titre right">&nbsp;</td>';
if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="liste_titre center">&nbsp;</td>';
if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="liste_titre center">&nbsp;</td>';
if (!empty($arrayfields['t.tms']['checked'])) print '<td class="liste_titre center">&nbsp;</td>';
if (!empty($arrayfields['t.status']['checked'])) {
	$statusOptions = array(
		TimesheetWeek::STATUS_DRAFT     => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_DRAFT, 0),
		TimesheetWeek::STATUS_SUBMITTED => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_SUBMITTED, 0),
		TimesheetWeek::STATUS_APPROVED  => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_APPROVED, 0),
		TimesheetWeek::STATUS_SEALED    => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_SEALED, 0),
		TimesheetWeek::STATUS_REFUSED   => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_REFUSED, 0),
	);
	print '<td class="liste_titre center">'.$form->multiselectarray('search_status', $statusOptions, $search_status, 0, 0, 'minwidth150 maxwidth200', 0, 0, '', '', '', '', '', 1).'</td>';
}
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('right').'</td>';
print '</tr>'."\n";

/* Titres */
print '<tr class="liste_titre">';
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'maxwidthsearch center ');
if (!empty($arrayfields['t.ref']['checked'])) print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], "t.ref", "", $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['user']['checked'])) print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], "u.lastname", "", $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.entity']['checked'])) print_liste_field_titre($arrayfields['t.entity']['label'], $_SERVER["PHP_SELF"], "t.entity", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.year']['checked'])) print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], "t.year", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.week']['checked'])) print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], "t.week", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.total_hours']['checked'])) print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.overtime_hours']['checked'])) print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], "t.overtime_hours", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.zone1_count']['checked'])) print_liste_field_titre($arrayfields['t.zone1_count']['label'], $_SERVER["PHP_SELF"], "t.zone1_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.zone2_count']['checked'])) print_liste_field_titre($arrayfields['t.zone2_count']['label'], $_SERVER["PHP_SELF"], "t.zone2_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.zone3_count']['checked'])) print_liste_field_titre($arrayfields['t.zone3_count']['label'], $_SERVER["PHP_SELF"], "t.zone3_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.zone4_count']['checked'])) print_liste_field_titre($arrayfields['t.zone4_count']['label'], $_SERVER["PHP_SELF"], "t.zone4_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.zone5_count']['checked'])) print_liste_field_titre($arrayfields['t.zone5_count']['label'], $_SERVER["PHP_SELF"], "t.zone5_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.meal_count']['checked'])) print_liste_field_titre($arrayfields['t.meal_count']['label'], $_SERVER["PHP_SELF"], "t.meal_count", "", $param, '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.date_creation']['checked'])) print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], "t.date_creation", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.date_validation']['checked'])) print_liste_field_titre($arrayfields['t.date_validation']['label'], $_SERVER["PHP_SELF"], "t.date_validation", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.tms']['checked'])) print_liste_field_titre($arrayfields['t.tms']['label'], $_SERVER["PHP_SELF"], "t.tms", "", $param, '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.status']['checked'])) print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], "t.status", "", $param, '', $sortfield, $sortorder, 'center ');
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>'."\n";

/* Lignes */
$totalsAccumulator = array('total_hours'=>0.0,'overtime_hours'=>0.0,'zone1_count'=>0,'zone2_count'=>0,'zone3_count'=>0,'zone4_count'=>0,'zone5_count'=>0,'meal_count'=>0);
$i = 0; $imax = ($limit ? min($num, $limit) : $num);
while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, (array)$toselect) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';
	}

	if (!empty($arrayfields['t.ref']['checked'])) {
		$tswstatic->id = $obj->rowid; $tswstatic->ref = $obj->ref; $tswstatic->status = $obj->status;
		print '<td>'.$tswstatic->getNomUrl(1, 'ref').'</td>';
	}

	if (!empty($arrayfields['user']['checked'])) {
		$usertmp->id = $obj->uid; $usertmp->firstname = $obj->firstname; $usertmp->lastname = $obj->lastname; $usertmp->login = $obj->login;
		$usertmp->statut = isset($obj->user_status) ? (int)$obj->user_status : $usertmp->statut;
		$usertmp->photo = $obj->user_photo;
		print '<td>'.$usertmp->getNomUrl(-1).'</td>';
	}

	if (!empty($arrayfields['t.entity']['checked'])) {
		$entityName = property_exists($obj, 'entity_label') ? trim((string)$obj->entity_label) : '';
		if ($entityName === '') $entityName = $langs->trans('Entity').' #'.(int)$obj->entity;
		$entityBadge = '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityName).'</span></div>';
		print '<td class="center">'.$entityBadge.'</td>';
	}

	if (!empty($arrayfields['t.year']['checked'])) print '<td class="center">'.(int)$obj->year.'</td>';
	if (!empty($arrayfields['t.week']['checked'])) print '<td class="center">'.(int)$obj->week.'</td>';

	if (!empty($arrayfields['t.total_hours']['checked'])) {
		$tot = (float)$obj->total_hours; $hh = floor($tot); $mm = round(($tot-$hh)*60); if ($mm==60){$hh++;$mm=0;}
		print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';
	}
	if (!empty($arrayfields['t.overtime_hours']['checked'])) {
		$tot = (float)$obj->overtime_hours; $hh = floor($tot); $mm = round(($tot-$hh)*60); if ($mm==60){$hh++;$mm=0;}
		print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';
	}
	if (!empty($arrayfields['t.zone1_count']['checked'])) print '<td class="right">'.(int)$obj->zone1_count.'</td>';
	if (!empty($arrayfields['t.zone2_count']['checked'])) print '<td class="right">'.(int)$obj->zone2_count.'</td>';
	if (!empty($arrayfields['t.zone3_count']['checked'])) print '<td class="right">'.(int)$obj->zone3_count.'</td>';
	if (!empty($arrayfields['t.zone4_count']['checked'])) print '<td class="right">'.(int)$obj->zone4_count.'</td>';
	if (!empty($arrayfields['t.zone5_count']['checked'])) print '<td class="right">'.(int)$obj->zone5_count.'</td>';
	if (!empty($arrayfields['t.meal_count']['checked'])) print '<td class="right">'.(int)$obj->meal_count.'</td>';
	if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="center">'.($obj->date_creation ? dol_print_date($db->jdate($obj->date_creation),'dayhour') : '').'</td>';
	if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="center">'.($obj->date_validation ? dol_print_date($db->jdate($obj->date_validation),'dayhour') : '').'</td>';
	if (!empty($arrayfields['t.tms']['checked'])) print '<td class="center">'.($obj->tms ? dol_print_date($db->jdate($obj->tms),'dayhour') : '').'</td>';

	if (!empty($arrayfields['t.status']['checked'])) { $tswstatic->status = $obj->status; print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>'; }

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

/* Totaux */
if ($imax > 0) {
	$totalLabelPrinted = false;
	print '<tr class="liste_total">';
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) print '<td class="liste_total">&nbsp;</td>';
	if (!empty($arrayfields['t.ref']['checked'])) { print '<td class="liste_total">'.($totalLabelPrinted?'&nbsp;':$langs->trans('Total')).'</td>'; $totalLabelPrinted = true; }
	if (!empty($arrayfields['user']['checked'])) { print '<td class="liste_total">'.($totalLabelPrinted?'&nbsp;':$langs->trans('Total')).'</td>'; $totalLabelPrinted = true; }
	if (!empty($arrayfields['t.entity']['checked'])) { print '<td class="liste_total center">'.($totalLabelPrinted?'&nbsp;':$langs->trans('Total')).'</td>'; $totalLabelPrinted = true; }
	if (!empty($arrayfields['t.year']['checked'])) { print '<td class="liste_total center">'.($totalLabelPrinted?'&nbsp;':$langs->trans('Total')).'</td>'; $totalLabelPrinted = true; }
	if (!empty($arrayfields['t.week']['checked'])) { print '<td class="liste_total center">'.($totalLabelPrinted?'&nbsp;':$langs->trans('Total')).'</td>'; $totalLabelPrinted = true; }
	if (!empty($arrayfields['t.total_hours']['checked'])) {
		$hours = (float)$totalsAccumulator['total_hours']; $h=floor($hours); $m=round(($hours-$h)*60); if ($m==60){$h++;$m=0;}
		print '<td class="liste_total right">'.sprintf('%02d:%02d',$h,$m).'</td>';
	}
	if (!empty($arrayfields['t.overtime_hours']['checked'])) {
		$hours = (float)$totalsAccumulator['overtime_hours']; $h=floor($hours); $m=round(($hours-$h)*60); if ($m==60){$h++;$m=0;}
		print '<td class="liste_total right">'.sprintf('%02d:%02d',$h,$m).'</td>';
	}
	if (!empty($arrayfields['t.zone1_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['zone1_count'].'</td>';
	if (!empty($arrayfields['t.zone2_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['zone2_count'].'</td>';
	if (!empty($arrayfields['t.zone3_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['zone3_count'].'</td>';
	if (!empty($arrayfields['t.zone4_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['zone4_count'].'</td>';
	if (!empty($arrayfields['t.zone5_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['zone5_count'].'</td>';
	if (!empty($arrayfields['t.meal_count']['checked'])) print '<td class="liste_total right">'.(int)$totalsAccumulator['meal_count'].'</td>';
	if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="liste_total center">&nbsp;</td>';
	if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="liste_total center">&nbsp;</td>';
	if (!empty($arrayfields['t.tms']['checked'])) print '<td class="liste_total center">&nbsp;</td>';
	if (!empty($arrayfields['t.status']['checked'])) print '<td class="liste_total center">&nbsp;</td>';
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) print '<td class="liste_total">&nbsp;</td>';
	print '</tr>';
} else {
	$colspan = 1; foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

/* JS UI */
$script = <<<'JAVASCRIPT'
<script type="text/javascript">
jQuery(function ($) {
	var $limitSelect = $("select#limit");
	if ($limitSelect.length && $.fn.select2) {
		var normalizeString = function (value) { return (value || "").toLowerCase(); };
		$limitSelect.select2({
			dir: "ltr", width: "resolve", minimumInputLength: 0,
			language: (typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,
			matcher: function (params, data) {
				if ($.trim(params.term) === "") return data;
				var term = normalizeString(params.term), text = normalizeString(data.text || "");
				var keywords = term.split(' ');
				for (var i=0;i<keywords.length;i++) if (text.indexOf(keywords[i]) === -1) return null;
				return data;
			},
			theme: "default limit", containerCssClass: ":all:", selectionCssClass: ":all:", dropdownCssClass: "ui-dialog",
			templateResult: function (data, container) { if (data.element) { $(container).addClass($(data.element).attr("class")); } return data.text; },
			templateSelection: function (selection) { if (selection.id == "-1") return '<span class="placeholder">'+selection.text+'</span>'; return selection.text; },
			escapeMarkup: function (markup) { return markup; }
		});
	}
	$(".selectlimit").off("change.timesheetweekLimit").on("change.timesheetweekLimit", function () {
		var $current = $(this), $targetForm = $current.parents('form:first');
		if (!$targetForm.length) { $targetForm = $("#searchFormList"); }
		if ($targetForm.length) { var $limitHidden=$("#limit-hidden"); if ($limitHidden.length) $limitHidden.val($current.val()); $targetForm.submit(); }
	});
	var $paginationArea = $(".pagination");
	if ($paginationArea.length) {
		$paginationArea.addClass("colorblack");
		$paginationArea.find("a:not([class*='butAction'])").addClass("colorblack");
		$paginationArea.find("span:not([class*='fa'])").addClass("colorblack");
	}
});
</script>
JAVASCRIPT;
print $script;

llxFooter();
$db->close();
