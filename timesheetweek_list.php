<?php
/*  TimesheetWeek - List
 *  GPLv3
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../main.inc.php'))			$res = require_once __DIR__.'/../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../main.inc.php'))		$res = require_once __DIR__.'/../../main.inc.php';
if (!$res && file_exists(__DIR__.'/../../../main.inc.php'))	$res = require_once __DIR__.'/../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // formatHours()

$langs->loadLangs(array('timesheetweek@timesheetweek','users','projects','other'));

/**
 * Status shim (compat STATUS_APPROVED/STATUS_VALIDATED)
 */
function tw_status($name) {
	static $map = null;
	if ($map === null) {
		$approved = null;
		if (defined('TimesheetWeek::STATUS_APPROVED')) {
			$approved = TimesheetWeek::STATUS_APPROVED;
		} elseif (defined('TimesheetWeek::STATUS_VALIDATED')) {
			$approved = TimesheetWeek::STATUS_VALIDATED;
		} else {
			$approved = 2;
		}
		$map = array(
			'draft'     => defined('TimesheetWeek::STATUS_DRAFT')     ? TimesheetWeek::STATUS_DRAFT     : 0,
			'submitted' => defined('TimesheetWeek::STATUS_SUBMITTED') ? TimesheetWeek::STATUS_SUBMITTED : 1,
			'approved'  => $approved,
			'refused'   => defined('TimesheetWeek::STATUS_REFUSED')   ? TimesheetWeek::STATUS_REFUSED   : 3,
		);
	}
	return $map[$name];
}
function tw_can_validate_timesheet_row($rowUserId, $rowValidatorId, User $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll) {
	if ($permValidateAll) return true;
	if ($permValidateOwn && (int)$user->id === (int)$rowUserId) return true;
	if ($permValidate && (int)$user->id === (int)$rowValidatorId) return true;
	if ($permValidateChild) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$rowUserId, $subs, true)) return true;
	}
	return false;
}
function tw_can_delete_timesheet_row($rowUserId, User $user, $permDelete, $permDeleteChild, $permDeleteAll) {
	if ($permDeleteAll) return true;
	if ($permDelete && (int)$user->id === (int)$rowUserId) return true;
	if ($permDeleteChild) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$rowUserId, $subs, true)) return true;
	}
	return false;
}

// Params
$action      = GETPOST('action', 'aZ09');
$massaction  = GETPOST('massaction', 'alpha');
$confirm     = GETPOST('confirm', 'alpha');
$toselect    = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ09') ?: 'timesheetweeklist';
$optioncss   = GETPOST('optioncss', 'alpha');

// Pagination/tri
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page < 0) $page = 0;
$offset    = $limit * $page;
if (empty($sortfield)) $sortfield = 't.rowid';
if (empty($sortorder)) $sortorder = 'DESC';

// Filters
$search_all     = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref     = GETPOST('search_ref', 'alpha');
$search_user    = GETPOST('search_user', 'intcomma');
$search_year    = GETPOST('search_year', 'intcomma');
$search_week    = GETPOST('search_week', 'intcomma');
$search_status  = GETPOST('search_status', 'intcomma');
$search_total_h = GETPOST('search_total_hours', 'alpha');
$search_ot_h    = GETPOST('search_overtime_hours', 'alpha');

$search_datecday   = GETPOSTINT('search_datecday');
$search_datecmonth = GETPOSTINT('search_datecmonth');
$search_datecyear  = GETPOSTINT('search_datecyear');
$search_datecendday   = GETPOSTINT('search_datecendday');
$search_datecendmonth = GETPOSTINT('search_datecendmonth');
$search_datecendyear  = GETPOSTINT('search_datecendyear');
$search_datec = dol_mktime(0, 0, 0, $search_datecmonth, $search_datecday, $search_datecyear);
$search_datec_end = dol_mktime(23, 59, 59, $search_datecendmonth, $search_datecendday, $search_datecendyear);

// Object
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);

// Permissions (nouveau modèle)
$permRead          = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild     = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll       = $user->hasRight('timesheetweek','timesheetweek','readAll');

$permWrite         = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild    = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll      = $user->hasRight('timesheetweek','timesheetweek','writeAll');

$permValidate      = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateOwn   = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll   = $user->hasRight('timesheetweek','timesheetweek','validateAll');

$permDelete        = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild   = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek','timesheetweek','deleteAll');

if (!($permRead || $permReadChild || $permReadAll)) accessforbidden();

// Build allowed user ids for list restriction
$allowedUserIds = array((int) $user->id);
if ($permReadAll) {
	$allowedUserIds = array(); // no restriction
} elseif ($permReadChild) {
	$childs = $user->getAllChildIds(1);
	if (is_array($childs)) $allowedUserIds = array_unique(array_merge($allowedUserIds, array_map('intval',$childs)));
}

// Selectable columns
$arrayfields = array(
	't.ref'              => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'user'               => array('label'=>$langs->trans("User"), 'checked'=>1),
	't.year'             => array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'             => array('label'=>$langs->trans("Week"), 'checked'=>1),
	't.total_hours'      => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.overtime_hours'   => array('label'=>$langs->trans("Overtime"), 'checked'=>1),
	'validator'          => array('label'=>$langs->trans("Validator"), 'checked'=>1),
	't.date_creation'    => array('label'=>$langs->trans("DateCreation"), 'checked'=>0),
	't.date_validation'  => array('label'=>$langs->trans("DateValidation"), 'checked'=>0),
	't.tms'              => array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0),
	't.status'           => array('label'=>$langs->trans("Status"), 'checked'=>1),
);

// Persist user selection of columns
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

// Purge filters
if (GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) {
	$search_all = $search_ref = '';
	$search_user = $search_year = $search_week = $search_status = '';
	$search_total_h = $search_ot_h = '';
	$search_datecday = $search_datecmonth = $search_datecyear = '';
	$search_datecendday = $search_datecendmonth = $search_datecendyear = '';
}

// Prepare selection
$arrayofselected = is_array($toselect) ? $toselect : array();

/*
 * Mass actions with confirmation popups
 * - We use custom actions confirm_mass_approve / confirm_mass_refuse to process after popup
 */
if ($action == 'confirm_mass_approve' && $confirm == 'yes') {
	$error = 0; $ok = 0;
	foreach ($arrayofselected as $rowid) {
		$tw = new TimesheetWeek($db);
		if ($tw->fetch((int)$rowid) > 0) {
			$can = tw_can_validate_timesheet_row($tw->fk_user, $tw->fk_user_valid, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll);
			if ($can && (int)$tw->status === (int) tw_status('submitted')) {
				$res = (method_exists($tw,'approve') ? $tw->approve($user, (int)$user->id) : 0);
				if ($res > 0) $ok++; else $error++;
			}
		}
	}
	if ($ok) setEventMessages($langs->trans("TimesheetApproved").' ('.$ok.')', null, 'mesgs');
	if ($error) setEventMessages($langs->trans("Error").' ('.$error.')', null, 'errors');
	$massaction = ''; $action = 'list';
}
if ($action == 'confirm_mass_refuse' && $confirm == 'yes') {
	$error = 0; $ok = 0;
	foreach ($arrayofselected as $rowid) {
		$tw = new TimesheetWeek($db);
		if ($tw->fetch((int)$rowid) > 0) {
			$can = tw_can_validate_timesheet_row($tw->fk_user, $tw->fk_user_valid, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll);
			if ($can && (int)$tw->status === (int) tw_status('submitted')) {
				$res = (method_exists($tw,'refuse') ? $tw->refuse($user, (int)$user->id) : 0);
				if ($res > 0) $ok++; else $error++;
			}
		}
	}
	if ($ok) setEventMessages($langs->trans("TimesheetRefused").' ('.$ok.')', null, 'mesgs');
	if ($error) setEventMessages($langs->trans("Error").' ('.$error.')', null, 'errors');
	$massaction = ''; $action = 'list';
}

// Build SQL
$sql = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.note, t.date_creation, t.date_validation, t.fk_user_valid, t.tms, t.total_hours, t.overtime_hours";
$sql.= ", u.rowid as uid, u.lastname as ulastname, u.firstname as ufirstname, u.login as ulogin";
$sql.= ", v.rowid as vid, v.lastname as vlastname, v.firstname as vfirstname, v.login as vlogin";
$sql.= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";
$sql.= " WHERE t.entity IN (".getEntity('timesheetweek').")";

// Restrict rights
if (!$permReadAll) {
	if (!empty($allowedUserIds)) {
		$sql .= " AND t.fk_user IN (".implode(',', array_map('intval', $allowedUserIds)).")";
	}
}

// Global search
if (!empty($search_all)) {
	$sql .= natural_search(array('t.ref','u.lastname','u.firstname','v.lastname','v.firstname','t.note'), $search_all);
}
// Specific filters
if (!empty($search_ref))    $sql .= natural_search('t.ref', $search_ref);
if ($search_user !== '' && $search_user >= 0) $sql .= " AND t.fk_user IN (".$db->sanitize($search_user).")";
if ($search_year !== '' && $search_year >= 0) $sql .= " AND t.year = ".((int)$search_year);
if ($search_week !== '' && $search_week >= 0) $sql .= " AND t.week = ".((int)$search_week);
if ($search_status !== '' && $search_status >= 0) $sql .= " AND t.status IN (".$db->sanitize($search_status).")";
if ($search_total_h !== '') $sql .= natural_search('t.total_hours', $search_total_h, 1);
if ($search_ot_h    !== '') $sql .= natural_search('t.overtime_hours', $search_ot_h, 1);
if ($search_datec)      $sql .= " AND t.date_creation >= '".$db->idate($search_datec)."'";
if ($search_datec_end)  $sql .= " AND t.date_creation <= '".$db->idate($search_datec_end)."'";

// Count
$sqlcount = preg_replace('/SELECT .* FROM/i', 'SELECT COUNT(*) as nb FROM', $sql);
$sqlcount = preg_replace('/ORDER BY .*$/i', '', $sqlcount);
$resc = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($resc) { $obj = $db->fetch_object($resc); $nbtotalofrecords = (int) $obj->nb; $db->free($resc); }

$sql .= $db->order($sortfield, $sortorder);
if ($limit) $sql .= $db->plimit($limit+1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

// View
$form = new Form($db);
$formother = new FormOther($db);

$title = $langs->trans("TimesheetWeekList");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist');

$param = '';
if ($limit && $limit != $conf->liste_limit) $param .= '&limit='.((int)$limit);
if ($optioncss) $param .= '&optioncss='.urlencode($optioncss);

if ($search_all)     $param .= '&search_all='.urlencode($search_all);
if ($search_ref)     $param .= '&search_ref='.urlencode($search_ref);
if ($search_user !== '' && $search_user >= 0) $param .= '&search_user='.urlencode($search_user);
if ($search_year !== '' && $search_year >= 0) $param .= '&search_year='.urlencode($search_year);
if ($search_week !== '' && $search_week >= 0) $param .= '&search_week='.urlencode($search_week);
if ($search_status !== '' && $search_status >= 0) $param .= '&search_status='.urlencode($search_status);
if ($search_total_h !== '') $param .= '&search_total_hours='.urlencode($search_total_h);
if ($search_ot_h    !== '') $param .= '&search_overtime_hours='.urlencode($search_ot_h);
if ($search_datecday)      $param .= '&search_datecday='.((int)$search_datecday);
if ($search_datecmonth)    $param .= '&search_datecmonth='.((int)$search_datecmonth);
if ($search_datecyear)     $param .= '&search_datecyear='.((int)$search_datecyear);
if ($search_datecendday)   $param .= '&search_datecendday='.((int)$search_datecendday);
if ($search_datecendmonth) $param .= '&search_datecendmonth='.((int)$search_datecendmonth);
if ($search_datecendyear)  $param .= '&search_datecendyear='.((int)$search_datecendyear);

// Mass actions available
$arrayofmassactions = array(
	'mass_approve' => img_picto('', 'validate', 'class="pictofixedwidth"').($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'),
	'mass_refuse'  => img_picto('', 'disable', 'class="pictofixedwidth"').$langs->trans("Refuse"),
	'predelete'    => img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete"),
);
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

// New button
$newcardbutton = '';
if ($user->hasRight('timesheetweek','timesheetweek','write') || $user->hasRight('timesheetweek','timesheetweek','writeChild') || $user->hasRight('timesheetweek','timesheetweek','writeAll')) {
	$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?action=create');
}

// Title bar
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bookcal', 0, $newcardbutton, '', $limit);

// POPUP CONFIRMATIONS (custom mass actions)
// ------------------------------------------------
if ($massaction == 'mass_approve') {
	$titleconfirm = ($langs->trans('Approve')!='Approve'?$langs->trans('Approve'):'Approuver');
	$question     = array();
	$formconfirm  = $form->formconfirm($_SERVER["PHP_SELF"].'?'.ltrim($param,'&'), $titleconfirm, $langs->transnoentitiesnoconv("ConfirmApproveSelection"), 'confirm_mass_approve', $question, $arrayofselected, 0, 1);
	print $formconfirm;
}
if ($massaction == 'mass_refuse') {
	$titleconfirm = $langs->trans('Refuse');
	$question     = array();
	$formconfirm  = $form->formconfirm($_SERVER["PHP_SELF"].'?'.ltrim($param,'&'), $titleconfirm, $langs->transnoentitiesnoconv("ConfirmRefuseSelection"), 'confirm_mass_refuse', $question, $arrayofselected, 0, 1);
	print $formconfirm;
}

// Confirm for delete mass action (standard template)
$objecttmp = new TimesheetWeek($db);
$trackid = 'tsw';
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

// Column selector (rendered once in header)
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, 1);
$selectedfields = $htmlofselectarray;

// Search/filter form
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore">'."\n";

// Line: filters
print '<tr class="liste_titre_filter">';
// Left column: loupe + buttons
print '<td class="liste_titre center maxwidthsearch">';
print $form->showFilterButtons('left');
print '</td>';

// Ref
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre left">';
	print '<input class="flat" size="12" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
	print '</td>';
}
// User
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre left minwidth200">';
	print $form->select_dolusers($search_user, 'search_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}
// Year
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre center">';
	print '<input class="flat" size="4" type="text" name="search_year" value="'.dol_escape_htmltag($search_year).'">';
	print '</td>';
}
// Week
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre center">';
	print '<input class="flat" size="3" type="text" name="search_week" value="'.dol_escape_htmltag($search_week).'">';
	print '</td>';
}
// Total hours
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print '<td class="liste_titre right">';
	print '<input class="flat" size="6" type="text" name="search_total_hours" value="'.dol_escape_htmltag($search_total_h).'">';
	print '</td>';
}
// Overtime
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print '<td class="liste_titre right">';
	print '<input class="flat" size="6" type="text" name="search_overtime_hours" value="'.dol_escape_htmltag($search_ot_h).'">';
	print '</td>';
}
// Validator
if (!empty($arrayfields['validator']['checked'])) {
	print '<td class="liste_titre left">&nbsp;</td>';
}
// Date creation
if (!empty($arrayfields['t.date_creation']['checked'])) {
	print '<td class="liste_titre center">';
	print '<div class="nowrapfordate">';
	print $form->selectDate($search_datec ? $search_datec : -1, 'search_datec', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
	print '</div><div class="nowrapfordate">';
	print $form->selectDate($search_datec_end ? $search_datec_end : -1, 'search_datecend', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
	print '</div>';
	print '</td>';
}
// Date validation
if (!empty($arrayfields['t.date_validation']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}
// Date modif
if (!empty($arrayfields['t.tms']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}
// Status
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre center">';
	$cur = ($search_status !== '' ? (int)$search_status : -1);
	$statuses = array(
		-1 => $langs->trans("All"),
		tw_status('draft')     => $langs->trans("Draft"),
		tw_status('submitted') => $langs->trans("Submitted"),
		tw_status('approved')  => ($langs->trans("Approved")!='Approved'?$langs->trans("Approved"):'Approuvée'),
		tw_status('refused')   => $langs->trans("Refused"),
	);
	print '<select class="flat" name="search_status">';
	foreach ($statuses as $k=>$lab) print '<option value="'.$k.'"'.($cur===$k?' selected':'').'>'.$lab.'</option>';
	print '</select>';
	print '</td>';
}

print '</tr>'."\n";

// Titles
print '<tr class="liste_titre">';
// Left header cell: select-all + columns selector (rendered once)
print '<th class="center maxwidthsearch">';
print '<input type="checkbox" id="checkall" class="marginrightonly"> ';
print $selectedfields;
print '</th>';

if (!empty($arrayfields['t.ref']['checked']))            print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], "t.ref", $param, '', '', $sortfield, $sortorder);
if (!empty($arrayfields['user']['checked']))             print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], "u.lastname", $param, '', '', $sortfield, $sortorder, 'left ');
if (!empty($arrayfields['t.year']['checked']))           print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], "t.year", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.week']['checked']))           print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], "t.week", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.total_hours']['checked']))    print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", $param, '', '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.overtime_hours']['checked'])) print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], "t.overtime_hours", $param, '', '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['validator']['checked']))        print_liste_field_titre($arrayfields['validator']['label'], $_SERVER["PHP_SELF"], "v.lastname", $param, '', '', $sortfield, $sortorder, 'left ');
if (!empty($arrayfields['t.date_creation']['checked']))  print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], "t.date_creation", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.date_validation']['checked']))print_liste_field_titre($arrayfields['t.date_validation']['label'], $_SERVER["PHP_SELF"], "t.date_validation", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.tms']['checked']))            print_liste_field_titre($arrayfields['t.tms']['label'], $_SERVER["PHP_SELF"], "t.tms", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.status']['checked']))         print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], "t.status", $param, '', '', $sortfield, $sortorder, 'center ');

print '</tr>'."\n";

// Loop
$total_total_hours = 0;
$total_overtime_hours = 0;

$tswstatic = new TimesheetWeek($db);
$usertmp   = new User($db);
$validtmp  = new User($db);

$i = 0;
$imax = ($limit ? min($num, $limit) : $num);

while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven" data-rowid="'.$obj->rowid.'">';

	// Left select checkbox
	print '<td class="center">';
	$selected = (is_array($toselect) && in_array($obj->rowid, $toselect)) ? ' checked' : '';
	print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.(int)$obj->rowid.'"'.$selected.'>';
	print '</td>';

	$tswstatic->id = (int)$obj->rowid;
	$tswstatic->ref = $obj->ref;
	$tswstatic->status = (int)$obj->status;

	// Ref
	if (!empty($arrayfields['t.ref']['checked'])) {
		print '<td class="nowraponall">';
		print $tswstatic->getNomUrl(1);
		print '</td>';
	}
	// User
	if (!empty($arrayfields['user']['checked'])) {
		print '<td class="left">';
		$usertmp->id = (int)$obj->uid; $usertmp->firstname = $obj->ufirstname; $usertmp->lastname = $obj->ulastname; $usertmp->login = $obj->ulogin;
		print $usertmp->getNomUrl(-1);
		print '</td>';
	}
	// Year
	if (!empty($arrayfields['t.year']['checked'])) {
		print '<td class="center">'.dol_escape_htmltag($obj->year).'</td>';
	}
	// Week
	if (!empty($arrayfields['t.week']['checked'])) {
		print '<td class="center">'.dol_escape_htmltag($obj->week).'</td>';
	}
	// Total hours
	if (!empty($arrayfields['t.total_hours']['checked'])) {
		print '<td class="right">'.formatHours((float)$obj->total_hours).'</td>';
		$total_total_hours += (float)$obj->total_hours;
	}
	// Overtime hours
	if (!empty($arrayfields['t.overtime_hours']['checked'])) {
		print '<td class="right">'.formatHours((float)$obj->overtime_hours).'</td>';
		$total_overtime_hours += (float)$obj->overtime_hours;
	}
	// Validator
	if (!empty($arrayfields['validator']['checked'])) {
		print '<td class="left">';
		if (!empty($obj->vid)) {
			$validtmp->id = (int)$obj->vid; $validtmp->firstname = $obj->vfirstname; $validtmp->lastname = $obj->vlastname; $validtmp->login = $obj->vlogin;
			print $validtmp->getNomUrl(-1);
		} else {
			print '&nbsp;';
		}
		print '</td>';
	}
	// Dates
	if (!empty($arrayfields['t.date_creation']['checked'])) {
		print '<td class="center">'.($obj->date_creation?dol_print_date($db->jdate($obj->date_creation),'dayhour'):'').'</td>';
	}
	if (!empty($arrayfields['t.date_validation']['checked'])) {
		print '<td class="center">'.($obj->date_validation?dol_print_date($db->jdate($obj->date_validation),'dayhour'):'').'</td>';
	}
	if (!empty($arrayfields['t.tms']['checked'])) {
		print '<td class="center">'.($obj->tms?dol_print_date($db->jdate($obj->tms),'dayhour'):'').'</td>';
	}
	// Status
	if (!empty($arrayfields['t.status']['checked'])) {
		print '<td class="center nowrap">';
		$lib = $tswstatic->getLibStatut(5);
		$lib = str_replace(array('Validée','Validated'), array('Approuvée','Approved'), $lib);
		print $lib;
		print '</td>';
	}

	print '</tr>';

	$i++;
}

if ($num == 0) {
	$colspan = 1; // checkbox col
	foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

// Totals line
print '<tr class="liste_total">';
print '<td class="right">'.$langs->trans("Total").'</td>';
if (!empty($arrayfields['t.ref']['checked']))            print '<td></td>';
if (!empty($arrayfields['user']['checked']))             print '<td></td>';
if (!empty($arrayfields['t.year']['checked']))           print '<td></td>';
if (!empty($arrayfields['t.week']['checked']))           print '<td></td>';
if (!empty($arrayfields['t.total_hours']['checked']))    print '<td class="right">'.formatHours($total_total_hours).'</td>';
if (!empty($arrayfields['t.overtime_hours']['checked'])) print '<td class="right">'.formatHours($total_overtime_hours).'</td>';
if (!empty($arrayfields['validator']['checked']))        print '<td></td>';
if (!empty($arrayfields['t.date_creation']['checked']))  print '<td></td>';
if (!empty($arrayfields['t.date_validation']['checked']))print '<td></td>';
if (!empty($arrayfields['t.tms']['checked']))            print '<td></td>';
if (!empty($arrayfields['t.status']['checked']))         print '<td></td>';
print '</tr>';

print '</table>';
print '</div>';
print '</form>';

// JS: select all rows checkbox
print '<script>
jQuery(function($){
	$("#checkall").on("change", function(){
		$(".checkforselect").prop("checked", this.checked);
	});
});
</script>';

llxFooter();
$db->close();
