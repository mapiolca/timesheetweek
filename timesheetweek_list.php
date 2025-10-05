<?php
/* Copyright (C) 2025
 * Author: Pierre ARDOIN
 * GPL v3+
 */

/**
 * \file       timesheetweek_list.php
 * \ingroup    timesheetweek
 * \brief      List of weekly timesheets (aligné sur le style expensereport/list.php)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // formatHours()

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('timesheetweek@timesheetweek','other','projects','users'));

$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$show_files   = GETPOSTINT('show_files');
$confirm      = GETPOST('confirm', 'alpha');
$cancel       = GETPOST('cancel', 'alpha');
$toselect     = GETPOST('toselect', 'array');
$contextpage  = GETPOST('contextpage', 'aZ') ?: 'timesheetweeklist';
$mode         = GETPOST('mode', 'alpha');

$optioncss    = GETPOST('optioncss', 'alpha');

// Pagination
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) $page = 0;
$offset    = $limit * $page;

if (!$sortorder) $sortorder = "DESC";
if (!$sortfield) $sortfield = "t.rowid";

// Recherche
$search_all       = trim(GETPOST('search_all','alphanohtml'));
$search_ref       = trim(GETPOST('search_ref','alpha'));
$search_user      = GETPOST('search_user','intcomma');       // peut être '' ou int
$search_validator = GETPOST('search_validator','intcomma');  // peut être '' ou int
$search_year      = GETPOSTINT('search_year');
$search_week      = GETPOSTINT('search_week');
$search_status    = GETPOST('search_status','intcomma');
$search_note      = trim(GETPOST('search_note','alpha'));

// Permissions du module
$permRead      = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll   = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite     = !empty($user->rights->timesheetweek->timesheetweek->write);
$permDelete    = !empty($user->rights->timesheetweek->timesheetweek->delete);

if (!$permRead) accessforbidden();

$hookmanager->initHooks(array('timesheetweeklist'));

// Objet technique + extrafields
$object = new TimesheetWeek($db);
// Adapter la table extrafields sur la table réelle (si tu ajoutes des extrafields plus tard)
$object->table_element = 'timesheet_week';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Champs pour recherche globale
$fieldstosearchall = array(
	't.ref'  => 'Ref',
	't.note' => 'Note',
);

// Définition des colonnes (arrayfields) + selector
$arrayfields = array(
	't.ref'             => array('label'=>$langs->trans("Ref"),             'checked'=>1),
	'user'              => array('label'=>$langs->trans("User"),            'checked'=>1),
	't.year'            => array('label'=>$langs->trans("Year"),            'checked'=>1),
	't.week'            => array('label'=>$langs->trans("Week"),            'checked'=>1),
	't.status'          => array('label'=>$langs->trans("Status"),          'checked'=>1),
	't.note'            => array('label'=>$langs->trans("Note"),            'checked'=>0),
	'validator'         => array('label'=>$langs->trans("Validator"),       'checked'=>1),
	't.total_hours'     => array('label'=>$langs->trans("TotalHours"),      'checked'=>1),
	't.overtime_hours'  => array('label'=>$langs->trans("Overtime"),        'checked'=>1),
	't.date_creation'   => array('label'=>$langs->trans("DateCreation"),    'checked'=>0),
	't.date_validation' => array('label'=>$langs->trans("DateValidation"),  'checked'=>0),
	't.tms'             => array('label'=>$langs->trans("DateModification"),'checked'=>0),
);
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

// Mass actions
$diroutputmassaction = $conf->timesheetweek->dir_output.'/temp/massgeneration/'.$user->id;

// --- Actions ---
if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields (changer colonnes)
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		$search_all = $search_ref = $search_note = '';
		$search_user = $search_validator = '';
		$search_year = $search_week = 0;
		$search_status = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
	 || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}

	// Mass actions (génériques)
	$objectclass = 'TimesheetWeek';
	$objectlabel = 'TimesheetWeek';
	$uploaddir   = $conf->timesheetweek->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}

// --- SQL SELECT ---
$sql = "SELECT";
$sql .= " t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.note,";
$sql .= " t.date_creation, t.date_validation, t.fk_user_valid, t.tms,";
$sql .= " t.total_hours, t.overtime_hours,";
$sql .= " u.lastname as ulastname, u.firstname as ufirstname, u.login as ulogin, u.rowid as uid,";
$sql .= " v.lastname as vlastname, v.firstname as vfirstname, v.login as vlogin, v.rowid as vid";

// Add extrafields select
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$db->sanitize($key)." as options_".$db->sanitize($key) : '');
	}
}

// Hook select
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;
$sql = preg_replace('/,\s*$/', '', $sql);

$sqlfields = $sql; // for count

$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
$sql .= " WHERE t.entity IN (".getEntity('timesheetweek').")";

// Permissions filter
if (!$permReadAll && !$permReadChild) {
	$sql .= " AND t.fk_user = ".((int) $user->id);
} elseif ($permReadChild && !$permReadAll) {
	$childids = $user->getAllChildIds(1);
	if (!is_array($childids) || empty($childids)) {
		$sql .= " AND t.fk_user = ".((int) $user->id);
	} else {
		$sql .= " AND (t.fk_user = ".((int) $user->id)." OR t.fk_user IN (".implode(',', array_map('intval',$childids))."))";
	}
}

// Search all
if (!empty($search_all)) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
// Filters
if ($search_ref !== '')       $sql .= natural_search('t.ref', $search_ref);
if ($search_note !== '')      $sql .= natural_search('t.note', $search_note);
if ($search_user !== '' && (int)$search_user >= 0)         $sql .= " AND t.fk_user = ".((int) $search_user);
if ($search_validator !== '' && (int)$search_validator>=0) $sql .= " AND t.fk_user_valid = ".((int) $search_validator);
if ($search_year > 0)         $sql .= " AND t.year = ".((int) $search_year);
if ($search_week > 0)         $sql .= " AND t.week = ".((int) $search_week);
if ($search_status !== '' && (int)$search_status >= 0) $sql .= " AND t.status IN (".$db->sanitize($search_status).")";

// Extrafields where
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';

// Hook where
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

// Count
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = ($objforcount ? $objforcount->nbtotalofrecords : 0);
		$db->free($resql);
		if (($page * $limit) > $nbtotalofrecords) { $page = 0; $offset = 0; }
	} else {
		dol_print_error($db);
	}
}

// Order + limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) $sql .= $db->plimit($limit + 1, $offset);

// Execute
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

// ---------------- View ----------------
$form = new Form($db);
$title = $langs->trans("TimesheetWeekList");
$help_url = '';
$morejs = array();
$morecss = array();

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'bodyforlist');

$arrayofselected = is_array($toselect) ? $toselect : array();

// Build param for links
$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage='.urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.((int) $limit);
if ($optioncss != '') $param .= '&optioncss='.urlencode($optioncss);
if ($search_all)         $param .= '&search_all='.urlencode($search_all);
if ($search_ref)         $param .= '&search_ref='.urlencode($search_ref);
if ($search_note)        $param .= '&search_note='.urlencode($search_note);
if ($search_user !== '' && $search_user !== null)         $param .= '&search_user='.urlencode($search_user);
if ($search_validator !== '' && $search_validator !== null)$param .= '&search_validator='.urlencode($search_validator);
if ($search_year > 0)    $param .= '&search_year='.(int)$search_year;
if ($search_week > 0)    $param .= '&search_week='.(int)$search_week;
if ($search_status !== '' && (int)$search_status >= 0) $param .= '&search_status='.urlencode($search_status);
// Add $param from extrafields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

// Mass actions list
$arrayofmassactions = array(
	'generate_doc' => img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("ReGeneratePDF"),
	'builddoc'     => img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
	'presend'      => img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
);
if ($permDelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend','predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

// Mode boutons (list/kanban)
$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'),   '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=common'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ((empty($mode) || $mode == 'common') ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-list imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ($mode == 'kanban' ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create', '', $permWrite);

// Barre titre + pagination
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'object_timesheetweek@timesheetweek', 0, $newcardbutton, '', $limit, 0, 0, 1);

// Form global
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="'.($action == 'edit' ? 'update' : 'list').'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

// Pre-mass action (email confirm etc.)
$topicmail = "SendTimesheetWeek";
$modelmail = "timesheetweek";
$objecttmp = $object; // only for include
$trackid = 'tsw';
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

// Affichage de la zone “quick search context”
if ($search_all) {
	$setupstring = '';
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
		$setupstring .= $key."=".$val.";";
	}
	print '<!-- Quick search on fields: '.$setupstring.' -->'."\n";
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).implode(', ', $fieldstosearchall).'</div>'."\n";
}

// Hooks avant la liste
$moreforfilter = '';
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action);
if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint; else $moreforfilter = $hookmanager->resPrint;

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">'.$moreforfilter.'</div>';
}

// Sélecteur de colonnes (engrenage)
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedfields = ($mode != 'kanban' ? $htmlofselectarray : '');
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

// Table
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? ' listwithfilterbefore' : '').'">'."\n";

// Ligne de filtres
print '<tr class="liste_titre_filter">';

// Action column (checkbox à gauche)
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons('left');
	print $searchpicto;
	print '</td>';
}

// Ref
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre">';
	print '<input class="flat" size="10" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
	print '</td>';
}

// User
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	print $form->select_dolusers(($search_user !== '' ? (int)$search_user : ''), 'search_user', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}

// Year
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre right">';
	print '<input class="flat" type="number" min="2000" max="2099" name="search_year" value="'.($search_year>0?$search_year:'').'" style="width:80px">';
	print '</td>';
}

// Week
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre right">';
	print '<input class="flat" type="number" min="1" max="53" name="search_week" value="'.($search_week>0?$search_week:'').'" style="width:70px">';
	print '</td>';
}

// Status
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre center">';
	print '<select class="flat" name="search_status">';
	print '<option value=""></option>';
	$st = array(
		0=>$langs->trans('Draft'),
		1=>$langs->trans('InProgress'),
		2=>$langs->trans('Submitted'),
		3=>$langs->trans('Approved'),
		4=>$langs->trans('Refused')
	);
	foreach ($st as $k=>$lab) {
		print '<option value="'.$k.'"'.(($search_status!=='' && (int)$search_status===$k)?' selected':'').'>'.$lab.'</option>';
	}
	print '</select>';
	print '</td>';
}

// Note
if (!empty($arrayfields['t.note']['checked'])) {
	print '<td class="liste_titre">';
	print '<input class="flat" type="text" size="12" name="search_note" value="'.dol_escape_htmltag($search_note).'">';
	print '</td>';
}

// Validator
if (!empty($arrayfields['validator']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	print $form->select_dolusers(($search_validator !== '' ? (int)$search_validator : ''), 'search_validator', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}

// Total hours
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
}

// Overtime
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
}

// Date creation
if (!empty($arrayfields['t.date_creation']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}

// Date validation
if (!empty($arrayfields['t.date_validation']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}

// TMS
if (!empty($arrayfields['t.tms']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}

// Extrafields inputs
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

// Hook fields in filter line
$parameters = array('arrayfields' => $arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action);
print $hookmanager->resPrint;

// Action col (checkbox à droite)
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';
}

print '</tr>';

// Ligne des titres de colonnes
print '<tr class="liste_titre">';

if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'maxwidthsearch center ');
}

if (!empty($arrayfields['t.ref']['checked'])) {
	print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], "t.ref", "", $param, '', $sortfield, $sortorder, 'left ');
}
if (!empty($arrayfields['user']['checked'])) {
	print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], "u.lastname", "", $param, '', $sortfield, $sortorder, 'left ');
}
if (!empty($arrayfields['t.year']['checked'])) {
	print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], "t.year", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.week']['checked'])) {
	print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], "t.week", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.status']['checked'])) {
	print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], "t.status", "", $param, '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['t.note']['checked'])) {
	print_liste_field_titre($arrayfields['t.note']['label'], $_SERVER["PHP_SELF"], "t.note", "", $param, '', $sortfield, $sortorder, 'left ');
}
if (!empty($arrayfields['validator']['checked'])) {
	print_liste_field_titre($arrayfields['validator']['label'], $_SERVER["PHP_SELF"], "v.lastname", "", $param, '', $sortfield, $sortorder, 'left ');
}
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", "", $param, '', $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], "t.overtime_hours", "", $param, '', $sortfield, $sortorder, 'right ');
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
// Extrafields titles
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';

// Hook titles
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
print $hookmanager->resPrint;

if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
}

print '</tr>'."\n";

// Boucle des lignes
$totalarray = array();
$totalarray['nbfield'] = 0;

$usertmp = new User($db);
$uservalid = new User($db);

$imax = ($limit ? min($num, $limit) : $num);
for ($i=0; $i < $imax; $i++) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) break;

	print '<tr class="oddeven" data-rowid="'.((int)$obj->rowid).'">';

	// Checkbox left
	if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, $arrayofselected) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
	}

	// Ref (link to card)
	if (!empty($arrayfields['t.ref']['checked'])) {
		$link = dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?id='.(int)$obj->rowid;
		print '<td><a href="'.$link.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
	}

	// User
	if (!empty($arrayfields['user']['checked'])) {
		print '<td class="left">';
		if ((int)$obj->uid > 0) {
			$usertmp->id = $obj->uid;
			$usertmp->lastname = $obj->ulastname;
			$usertmp->firstname = $obj->ufirstname;
			$usertmp->login = $obj->ulogin;
			print $usertmp->getNomUrl(-1);
		}
		print '</td>';
	}

	// Year
	if (!empty($arrayfields['t.year']['checked'])) {
		print '<td class="right">'.((int)$obj->year).'</td>';
	}
	// Week
	if (!empty($arrayfields['t.week']['checked'])) {
		print '<td class="right">'.((int)$obj->week).'</td>';
	}
	// Status
	if (!empty($arrayfields['t.status']['checked'])) {
		$map = array(0=>'Draft',1=>'InProgress',2=>'Submitted',3=>'Approved',4=>'Refused');
		$lab = isset($map[(int)$obj->status]) ? $langs->trans($map[(int)$obj->status]) : (string)$obj->status;
		print '<td class="center">'.dol_escape_htmltag($lab).'</td>';
	}
	// Note
	if (!empty($arrayfields['t.note']['checked'])) {
		print '<td>'.dol_escape_htmltag($obj->note).'</td>';
	}
	// Validator
	if (!empty($arrayfields['validator']['checked'])) {
		print '<td class="left">';
		if ((int)$obj->vid > 0) {
			$uservalid->id = $obj->vid;
			$uservalid->lastname = $obj->vlastname;
			$uservalid->firstname = $obj->vfirstname;
			$uservalid->login = $obj->vlogin;
			print $uservalid->getNomUrl(-1);
		}
		print '</td>';
	}
	// Total hours
	if (!empty($arrayfields['t.total_hours']['checked'])) {
		print '<td class="right">'.formatHours((float)$obj->total_hours).'</td>';
	}
	// Overtime
	if (!empty($arrayfields['t.overtime_hours']['checked'])) {
		print '<td class="right">'.formatHours((float)$obj->overtime_hours).'</td>';
	}
	// Date creation
	if (!empty($arrayfields['t.date_creation']['checked'])) {
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
	}
	// Date validation
	if (!empty($arrayfields['t.date_validation']['checked'])) {
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_validation), 'dayhour').'</td>';
	}
	// TMS
	if (!empty($arrayfields['t.tms']['checked'])) {
		print '<td class="center">'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
	}

	// Extrafields values
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action);
	print $hookmanager->resPrint;

	// Checkbox right
	if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
		print '<td class="center">';
		if ($massactionbutton || $massaction) {
			$selected = in_array($obj->rowid, $arrayofselected) ? 1 : 0;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
	}

	print '</tr>';
}

// Total line / No record
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) if (!empty($val['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

// Footer hook
$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action);
print $hookmanager->resPrint;

print '</table>';
print '</div>';

print '</form>';

// Zone documents de masse
if (empty($id)) {
	$hidegeneratedfilelistifempty = 1;
	if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) $hidegeneratedfilelistifempty = 0;

	$urlsource = $_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource .= str_replace('&amp;', '&', $param);

	$filedir = $diroutputmassaction;
	$genallowed = $permRead;
	$delallowed = $permWrite;

	print $form->showdocuments('massfilesarea_timesheetweek', '', $filedir, $urlsource, 0, $delallowed, '', 1, 1, 0, 48, 1, $param, $title, '', '', '', null, $hidegeneratedfilelistifempty);
}

llxFooter();
$db->close();
