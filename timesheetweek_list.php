<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 * GPL v3+
 */

/**
 * \file       timesheetweek_list.php
 * \ingroup    timesheetweek
 * \brief      List of weekly timesheets
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');

$langs->loadLangs(array('other','users','timesheetweek@timesheetweek'));

global $db, $conf, $user, $hookmanager;

// ---------------- Permissions ----------------
$permRead          = $user->hasRight('timesheetweek', 'timesheetweek', 'read');
$permReadAll       = $user->hasRight('timesheetweek', 'timesheetweek', 'readAll');
$permReadChild     = $user->hasRight('timesheetweek', 'timesheetweek', 'readChild');

$permWrite         = $user->hasRight('timesheetweek', 'timesheetweek', 'write');
$permWriteChild    = $user->hasRight('timesheetweek', 'timesheetweek', 'writeChild');
$permWriteAll      = $user->hasRight('timesheetweek', 'timesheetweek', 'writeAll');

$permValidate      = $user->hasRight('timesheetweek', 'timesheetweek', 'validate');
$permValidateChild = $user->hasRight('timesheetweek', 'timesheetweek', 'validateChild');
$permValidateAll   = $user->hasRight('timesheetweek', 'timesheetweek', 'validateAll');
$permValidateOwn   = $user->hasRight('timesheetweek', 'timesheetweek', 'validateOwn');

$permDelete        = $user->hasRight('timesheetweek', 'timesheetweek', 'delete');
$permDeleteChild   = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteAll');

if (!$permRead) accessforbidden();

// ---------------- Get/Post ----------------
$action      = GETPOST('action', 'aZ09');
$massaction  = GETPOST('massaction', 'alpha');
$confirm     = GETPOST('confirm', 'alpha');
$toselect    = GETPOST('toselect','array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

// Change selected columns like Dolibarr standard
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$search_all      = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref      = GETPOST('search_ref','alpha');
$search_user     = GETPOST('search_user','intcomma');
$search_year     = GETPOST('search_year','int');
$search_week     = GETPOST('search_week','int');
$search_status   = GETPOST('search_status','alpha');
$search_thours   = GETPOST('search_total_hours','alpha');
$search_ohours   = GETPOST('search_overtime_hours','alpha');

$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;
if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";

$childids = $user->getAllChildIds(1);

// ---------------- Security filter on users ----------------
$filterUserIds = array();
if ($permReadAll) {
	// no filter
} elseif ($permReadChild) {
	$filterUserIds = $childids;
	$filterUserIds[] = $user->id;
} else {
	$filterUserIds = array($user->id);
}

// ---------------- Massactions confirm handlers ----------------
if ($action == 'mass_approve' && $confirm == 'yes' && GETPOST('token','alpha') == $_SESSION['newtoken']) {
	$selected = is_array($toselect) ? $toselect : array();
	foreach ($selected as $tid) {
		$tw = new TimesheetWeek($db);
		if ($tw->fetch((int) $tid) > 0) {
			$can = false;
			if (!empty($tw->fk_user_valid) && $tw->fk_user_valid == $user->id && $permValidate) $can = true;
			if ($tw->fk_user == $user->id && $permValidateOwn) $can = true;
			if (in_array($tw->fk_user, $childids) && $permValidateChild) $can = true;
			if ($permValidateAll) $can = true;

			if ($can && $tw->status == TimesheetWeek::STATUS_SUBMITTED) {
				if ((int)$tw->fk_user_valid !== (int)$user->id) { $tw->fk_user_valid = $user->id; }
				$tw->status = TimesheetWeek::STATUS_APPROVED;
				$tw->date_validation = dol_now();
				$tw->update($user);
			}
		}
	}
	setEventMessages($langs->trans("TimesheetsApproved"), null, 'mesgs');
}
if ($action == 'mass_refuse' && $confirm == 'yes' && GETPOST('token','alpha') == $_SESSION['newtoken']) {
	$selected = is_array($toselect) ? $toselect : array();
	foreach ($selected as $tid) {
		$tw = new TimesheetWeek($db);
		if ($tw->fetch((int) $tid) > 0) {
			$can = false;
			if (!empty($tw->fk_user_valid) && $tw->fk_user_valid == $user->id && $permValidate) $can = true;
			if ($tw->fk_user == $user->id && $permValidateOwn) $can = true;
			if (in_array($tw->fk_user, $childids) && $permValidateChild) $can = true;
			if ($permValidateAll) $can = true;

			if ($can && $tw->status == TimesheetWeek::STATUS_SUBMITTED) {
				if ((int)$tw->fk_user_valid !== (int)$user->id) { $tw->fk_user_valid = $user->id; }
				$tw->status = TimesheetWeek::STATUS_REFUSED;
				$tw->date_validation = dol_now();
				$tw->update($user);
			}
		}
	}
	setEventMessages($langs->trans("TimesheetsRefused"), null, 'mesgs');
}
if ($action == 'mass_delete' && $confirm == 'yes' && GETPOST('token','alpha') == $_SESSION['newtoken']) {
	$selected = is_array($toselect) ? $toselect : array();
	foreach ($selected as $tid) {
		$tw = new TimesheetWeek($db);
		if ($tw->fetch((int) $tid) > 0) {
			$can = false;
			if ($tw->fk_user == $user->id && $permDelete) $can = true;
			if (in_array($tw->fk_user, $childids) && $permDeleteChild) $can = true;
			if ($permDeleteAll) $can = true;
			if ($can) $tw->delete($user);
		}
	}
	setEventMessages($langs->trans("RecordsDeleted"), null, 'mesgs');
}

// ---------------- SQL ----------------
$sql = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours,";
$sql.= " t.date_creation, t.date_validation, t.tms, t.fk_user_valid,";
$sql.= " u.rowid as uid, u.lastname as ulastname, u.firstname as ufirstname, u.login as ulogin,";
$sql.= " v.rowid as vid, v.lastname as vlastname, v.firstname as vfirstname, v.login as vlogin";
$sql.= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";

if (function_exists('dol_column_exists') && dol_column_exists($db, MAIN_DB_PREFIX.'timesheet_week', 'entity')) {
	$sql.= " WHERE t.entity IN (".getEntity('timesheetweek').")";
} else {
	$sql.= " WHERE 1=1";
}
if (!empty($search_all))      $sql.= natural_search(array('t.ref','u.lastname','u.firstname','u.login'), $search_all);
if (!empty($search_ref))      $sql.= natural_search('t.ref', $search_ref);
if (!empty($search_year))     $sql.= " AND t.year = ".((int)$search_year);
if (!empty($search_week))     $sql.= " AND t.week = ".((int)$search_week);
if ($search_user !== '' && $search_user >= 0) $sql.= " AND t.fk_user IN (".$db->sanitize($search_user).")";
if ($search_status !== '' && $search_status !== null)   $sql.= " AND t.status IN (".$db->sanitize($search_status).")";
if ($search_thours !== '')    $sql.= natural_search('t.total_hours', $search_thours, 1);
if ($search_ohours !== '')    $sql.= natural_search('t.overtime_hours', $search_ohours, 1);

// Rights restriction
if (!$permReadAll) {
	if ($filterUserIds) {
		$sql.= " AND t.fk_user IN (".($db->sanitize(implode(',', array_map('intval', $filterUserIds)))).")";
	} else {
		$sql.= " AND t.fk_user = ".((int)$user->id);
	}
}

$sqlfields = $sql;
$sql.= $db->order($sortfield, $sortorder);
if ($limit) $sql.= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

// Count
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlcount = preg_replace('/^SELECT .* FROM/i','SELECT COUNT(*) as nb FROM', $sqlfields);
	$sqlcount = preg_replace('/ORDER BY .*$/','', $sqlcount);
	$resc = $db->query($sqlcount);
	if ($resc) { $obj = $db->fetch_object($resc); $nbtotalofrecords = (int) $obj->nb; $db->free($resc); }
	if (($page * $limit) > $nbtotalofrecords) { $page = 0; $offset = 0; }
}

$num = ($resql ? $db->num_rows($resql) : 0);

// ---------------- View ----------------
$form = new Form($db);
$formother = new FormOther($db);

$title = $langs->trans("TimesheetWeek");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist');

// Params in URL
$param = '';
if ($search_all)      $param .= '&search_all='.urlencode($search_all);
if ($search_ref)      $param .= '&search_ref='.urlencode($search_ref);
if ($search_user!=='' && $search_user>=0) $param .= '&search_user='.urlencode($search_user);
if ($search_year)     $param .= '&search_year='.(int)$search_year;
if ($search_week)     $param .= '&search_week='.(int)$search_week;
if ($search_status!=='') $param .= '&search_status='.urlencode($search_status);
if ($search_thours!=='') $param .= '&search_total_hours='.urlencode($search_thours);
if ($search_ohours!=='') $param .= '&search_overtime_hours='.urlencode($search_ohours);

// Column chooser definition
$arrayfields = array(
	't.ref'            => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'user'             => array('label'=>$langs->trans("User"), 'checked'=>1),
	't.year'           => array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'           => array('label'=>$langs->trans("Week"), 'checked'=>1),
	't.total_hours'    => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.overtime_hours' => array('label'=>$langs->trans("Overtime"), 'checked'=>1),
	't.date_creation'  => array('label'=>$langs->trans("DateCreation"), 'checked'=>0),
	't.date_validation'=> array('label'=>$langs->trans("DateValidation"), 'checked'=>0),
	't.tms'            => array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0),
	't.status'         => array('label'=>$langs->trans("Status"), 'checked'=>1),
);

// New button + mass actions
$newbutton = '';
if ($permWrite) {
	$newbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create');
}
$massactions = array(
	'approve_selection' => img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans("ApproveSelection"),
	'refuse_selection'  => img_picto('', 'error', 'class="pictofixedwidth"').$langs->trans("RefuseSelection"),
	'delete_selection'  => img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("DeleteSelection"),
);
$massactionbutton = $form->selectMassAction('', $massactions);

// Barre liste (haut du tableau)
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bookcal', 0, $newbutton, '', $limit, 0, 0, 1);

// Form list
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste listwithfilterbefore">'."\n";

// ---- Filter row
print '<tr class="liste_titre_filter">';
// Column selector + filter buttons at left
$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfieldshtml = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';

// Ref
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input class="flat" size="12" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
}
// User
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre maxwidthonsmartphone">';
	if ($permReadAll || $permReadChild) {
		print $form->select_dolusers($search_user, 'search_user', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth200');
	} else {
		print '&nbsp;';
	}
	print '</td>';
}
// Year
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre"><input class="flat" size="4" type="text" name="search_year" value="'.dol_escape_htmltag($search_year).'"></td>';
}
// Week
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre"><input class="flat" size="3" type="text" name="search_week" value="'.dol_escape_htmltag($search_week).'"></td>';
}
// Total Hours
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print '<td class="liste_titre right"><input class="flat" size="5" type="text" name="search_total_hours" value="'.dol_escape_htmltag($search_thours).'"></td>';
}
// Overtime
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print '<td class="liste_titre right"><input class="flat" size="5" type="text" name="search_overtime_hours" value="'.dol_escape_htmltag($search_ohours).'"></td>';
}
// Date creation
if (!empty($arrayfields['t.date_creation']['checked'])) { print '<td class="liste_titre">&nbsp;</td>'; }
// Date validation
if (!empty($arrayfields['t.date_validation']['checked'])) { print '<td class="liste_titre">&nbsp;</td>'; }
// tms
if (!empty($arrayfields['t.tms']['checked'])) { print '<td class="liste_titre">&nbsp;</td>'; }
// Status
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre center">';
	print '<select class="flat" name="search_status">';
	print '<option value=""></option>';
	print '<option value="'.TimesheetWeek::STATUS_DRAFT.'"'.(($search_status!=='') && (string)$search_status===(string)TimesheetWeek::STATUS_DRAFT?' selected':'').'>'.$langs->trans("Draft").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_SUBMITTED.'"'.(($search_status!=='') && (string)$search_status===(string)TimesheetWeek::STATUS_SUBMITTED?' selected':'').'>'.$langs->trans("Submitted").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_APPROVED.'"'.(($search_status!=='') && (string)$search_status===(string)TimesheetWeek::STATUS_APPROVED?' selected':'').'>'.$langs->trans("Approved").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_REFUSED.'"'.(($search_status!=='') && (string)$search_status===(string)TimesheetWeek::STATUS_REFUSED?' selected':'').'>'.$langs->trans("Refused").'</option>';
	print '</select>';
	print '</td>';
}
// Right filter buttons
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';

print '</tr>';

// ---- Title row
print '<tr class="liste_titre">';
print_liste_field_titre($selectedfieldshtml, $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'maxwidthsearch center ');

if (!empty($arrayfields['t.ref']['checked']))            print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], "t.ref", $param, '', '', $sortfield, $sortorder);
if (!empty($arrayfields['user']['checked']))             print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], "u.lastname", $param, '', '', $sortfield, $sortorder);
if (!empty($arrayfields['t.year']['checked']))           print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], "t.year", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.week']['checked']))           print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], "t.week", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.total_hours']['checked']))    print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], "t.total_hours", $param, '', '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.overtime_hours']['checked'])) print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], "t.overtime_hours", $param, '', '', $sortfield, $sortorder, 'right ');
if (!empty($arrayfields['t.date_creation']['checked']))  print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], "t.date_creation", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.date_validation']['checked']))print_liste_field_titre($arrayfields['t.date_validation']['label'], $_SERVER["PHP_SELF"], "t.date_validation", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.tms']['checked']))            print_liste_field_titre($arrayfields['t.tms']['label'], $_SERVER["PHP_SELF"], "t.tms", $param, '', '', $sortfield, $sortorder, 'center ');
if (!empty($arrayfields['t.status']['checked']))         print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], "t.status", $param, '', '', $sortfield, $sortorder, 'center ');

print_liste_field_titre('', $_SERVER["PHP_SELF"], "", '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>'."\n";

// ---- Rows
$timesheetstatic = new TimesheetWeek($db);
$arrayofselected = is_array($toselect) ? $toselect : array();

$i = 0;
$imax = ($limit ? min($num, $limit) : $num);
while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	// Checkbox
	print '<td class="center">';
	$selected = in_array($obj->rowid, $arrayofselected);
	print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
	print '</td>';

	// Ref
	if (!empty($arrayfields['t.ref']['checked'])) {
		$timesheetstatic->id = $obj->rowid;
		$timesheetstatic->ref = $obj->ref;
		$timesheetstatic->status = $obj->status;
		print '<td>'.$timesheetstatic->getNomUrl(1).'</td>';
	}
	// User
	if (!empty($arrayfields['user']['checked'])) {
		$us = new User($db); $us->id = $obj->uid; $us->lastname = $obj->ulastname; $us->firstname = $obj->ufirstname; $us->login = $obj->ulogin;
		print '<td>'.$us->getNomUrl(-1).'</td>';
	}
	// Year
	if (!empty($arrayfields['t.year']['checked'])) print '<td class="center">'.dol_escape_htmltag($obj->year).'</td>';
	// Week
	if (!empty($arrayfields['t.week']['checked'])) print '<td class="center">'.dol_escape_htmltag($obj->week).'</td>';
	// Total hours
	if (!empty($arrayfields['t.total_hours']['checked'])) print '<td class="right">'.price($obj->total_hours).'</td>';
	// Overtime
	if (!empty($arrayfields['t.overtime_hours']['checked'])) print '<td class="right">'.price($obj->overtime_hours).'</td>';
	// date create
	if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
	// date validation
	if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="center">'.dol_print_date($db->jdate($obj->date_validation), 'day').'</td>';
	// tms
	if (!empty($arrayfields['t.tms']['checked'])) print '<td class="center">'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
	// status (badge)
	if (!empty($arrayfields['t.status']['checked'])) {
		$timesheetstatic->status = $obj->status;
		$badge = $timesheetstatic->getLibStatut(5);
		if (empty($badge)) {
			// Fallback colored badges
			$lab = '';
			$cls = 'statusdraft';
			if ((string)$obj->status === (string)TimesheetWeek::STATUS_DRAFT) { $lab=$langs->trans("Draft"); $cls='statusdraft'; }
			elseif ((string)$obj->status === (string)TimesheetWeek::STATUS_SUBMITTED) { $lab=$langs->trans("Submitted"); $cls='status1'; }
			elseif ((string)$obj->status === (string)TimesheetWeek::STATUS_APPROVED) { $lab=$langs->trans("Approved"); $cls='status4'; }
			elseif ((string)$obj->status === (string)TimesheetWeek::STATUS_REFUSED) { $lab=$langs->trans("Refused"); $cls='status6'; }
			$badge = '<span class="badge '.$cls.'">'.$lab.'</span>';
		}
		print '<td class="center">'.$badge.'</td>';
	}

	// right spacer
	print '<td class="center"></td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	$colspan = 2; // checkbox + last action col
	foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

// Mass actions confirmation popups with selected IDs preserved
if (in_array($massaction, array('approve_selection','refuse_selection','delete_selection'))) {
	$form = new Form($db);
	$formq = array(
		array('type'=>'hidden','name'=>'token','value'=>newToken()),
		array('type'=>'hidden','name'=>'confirm','value'=>'yes')
	);
	if (is_array($toselect)) {
		foreach ($toselect as $selid) {
			$formq[] = array('type'=>'hidden','name'=>'toselect[]','value'=>(int)$selid);
		}
	}
	if ($massaction == 'approve_selection') {
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ApproveSelection'), $langs->trans("ConfirmApprove"), 'mass_approve', $formq, 0, 1);
	}
	if ($massaction == 'refuse_selection') {
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('RefuseSelection'), $langs->trans("ConfirmRefuse"), 'mass_refuse', $formq, 0, 1);
	}
	if ($massaction == 'delete_selection') {
		print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('DeleteSelection'), $langs->trans("ConfirmDelete"), 'mass_delete', $formq, 0, 1);
	}
}

llxFooter();
$db->close();
