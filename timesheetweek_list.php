<?php
/* Copyright (C)
 * 2025 - Pierre ARDOIN
 *
 * GPLv3
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','users','other'));

// Rights (Dolibarr standard)
$permRead          = $user->hasRight('timesheetweek', 'timesheetweek', 'read');
$permReadAll       = $user->hasRight('timesheetweek', 'timesheetweek', 'readAll');
$permReadChild     = $user->hasRight('timesheetweek', 'timesheetweek', 'readChild');

$permCreate        = $user->hasRight('timesheetweek', 'timesheetweek', 'create');
$permCreateChild   = $user->hasRight('timesheetweek', 'timesheetweek', 'createChild');
$permCreateAll     = $user->hasRight('timesheetweek', 'timesheetweek', 'createAll');

if (!$permRead && !$permReadChild && !$permReadAll) accessforbidden();

$childids = $user->getAllChildIds(1);

// Params
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

$limit    = GETPOSTINT('limit') ?: $conf->liste_limit;
$sortfield= GETPOST('sortfield', 'aZ09comma');
$sortorder= GETPOST('sortorder', 'aZ09comma');
$page     = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;

if (empty($sortfield)) $sortfield = 't.rowid';
if (empty($sortorder)) $sortorder = 'DESC';

// Filters
$search_all      = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref      = GETPOST('search_ref','alpha');
$search_user     = GETPOST('search_user','intcomma');
$search_validator= GETPOST('search_validator','intcomma');
$search_year     = GETPOSTINT('search_year');
$search_week     = GETPOSTINT('search_week');
$search_status   = GETPOST('search_status','intcomma');

// Objects
$form = new Form($db);
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

$fieldstosearchall = array(
	't.ref'=>'Ref',
	'u.lastname'=>'Lastname',
	'u.firstname'=>'Firstname',
	't.note'=>'Note'
);

$arrayfields = array(
	't.ref' => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'user'  => array('label'=>$langs->trans("Employee"), 'checked'=>1),
	't.year'=> array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'=> array('label'=>$langs->trans("Week"), 'checked'=>1),
	'validator' => array('label'=>$langs->trans("Validator"), 'checked'=>1),
	't.total_hours' => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.overtime_hours' => array('label'=>$langs->trans("Overtime"), 'checked'=>1),
	't.status' => array('label'=>$langs->trans("Status"), 'checked'=>1),
	't.date_creation' => array('label'=>$langs->trans("DateCreation"), 'checked'=>0),
	't.tms' => array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0)
);
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

// Purge filters
if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search_ref=$search_user=$search_validator=$search_year=$search_week=$search_status='';
	$search_all='';
}

// SQL
$sql = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.note, t.date_creation, t.date_validation, t.fk_user_valid, t.tms, t.total_hours, t.overtime_hours";
$sql .= ", u.rowid as uid, u.lastname as ulastname, u.firstname as ufirstname, u.login as ulogin";
$sql .= ", v.rowid as vid, v.lastname as vlastname, v.firstname as vfirstname, v.login as vlogin";
// Extra
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		if ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate') {
			$sql .= ", ef.".$db->escape($key)." as options_".$db->escape($key);
		}
	}
}
$sqlfields = $sql;

$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";
$sql .= " WHERE t.entity IN (".getEntity('timesheetweek').")";

// Search all
if (!empty($search_all)) $sql .= natural_search(array_keys($fieldstosearchall), $search_all);

// Filters
if ($search_ref)     $sql .= natural_search('t.ref', $search_ref);
if ($search_user !== '' && $search_user >= 0) $sql .= " AND t.fk_user IN (".$db->sanitize($search_user).")";
if ($search_validator !== '' && $search_validator >= 0) $sql .= " AND t.fk_user_valid IN (".$db->sanitize($search_validator).")";
if ($search_year)    $sql .= " AND t.year = ".((int)$search_year);
if ($search_week)    $sql .= " AND t.week = ".((int)$search_week);
if ($search_status !== '' && $search_status >= 0) $sql .= " AND t.status IN (".$db->sanitize($search_status).")";

// Rights restriction
if (!$permReadAll) {
	if ($permReadChild) {
		$all = $childids;
		$all[] = $user->id;
		if (empty($all)) $all = array(0);
		$sql .= " AND t.fk_user IN (".$db->sanitize(implode(',', $all)).")";
	} elseif ($permRead) {
		$sql .= " AND t.fk_user = ".((int)$user->id);
	} else {
		$sql .= " AND 1=0";
	}
}

// Where from extrafields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';

// Count for pagination
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);
	$rescount = $db->query($sqlforcount);
	if ($rescount) {
		$objforcount = $db->fetch_object($rescount);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
		$db->free($rescount);
	}
	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0; $offset = 0;
	}
}

// Order & limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) $sql .= $db->plimit($limit+1, $offset);

// Query
$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

// View
$title = $langs->trans("TimesheetWeekList");
$morejs = array();
$morecss = array();

llxHeader('', $title, '', '', 0, 0, $morejs, $morecss, '', 'bodyforlist');

$newbtn = '';
// New button if allowed (any create scope)
if ($permCreate || $permCreateChild || $permCreateAll) {
	$newbtn = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create');
}

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, '', $db->num_rows($resql), $nbtotalofrecords, 'bookcal', 0, $newbtn, '', $limit, 0, 0, 1);

// Filters form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="searchFormList">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedfields = $htmlofselectarray;

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';

// Search line
print '<tr class="liste_titre_filter">';

if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" size="10" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
}
if (!empty($arrayfields['user']['checked'])) {
	print '<td class="liste_titre">';
	if ($permReadAll || $permReadChild) {
		print $form->select_dolusers($search_user, 'search_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	} else {
		print $user->getNomUrl(-1);
		print '<input type="hidden" name="search_user" value="'.((int)$user->id).'">';
	}
	print '</td>';
}
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" size="4" name="search_year" value="'.($search_year ? (int)$search_year : '').'"></td>';
}
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre"><input class="flat" type="text" size="3" name="search_week" value="'.($search_week ? (int)$search_week : '').'"></td>';
}
if (!empty($arrayfields['validator']['checked'])) {
	print '<td class="liste_titre">';
	print $form->select_dolusers($search_validator, 'search_validator', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
}
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre right">';
	// Simple select for status
	print '<select class="flat" name="search_status">';
	print '<option value=""></option>';
	print '<option value="'.TimesheetWeek::STATUS_DRAFT.'"'.($search_status===''.TimesheetWeek::STATUS_DRAFT ? ' selected':'').'>'.$langs->trans("Draft").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_SUBMITTED.'"'.($search_status===''.TimesheetWeek::STATUS_SUBMITTED ? ' selected':'').'>'.$langs->trans("Submitted").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_APPROVED.'"'.($search_status===''.TimesheetWeek::STATUS_APPROVED ? ' selected':'').'>'.$langs->trans("Approved").'</option>';
	print '<option value="'.TimesheetWeek::STATUS_REFUSED.'"'.($search_status===''.TimesheetWeek::STATUS_REFUSED ? ' selected':'').'>'.$langs->trans("Refused").'</option>';
	print '</select>';
	print '</td>';
}
if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="liste_titre">&nbsp;</td>';
if (!empty($arrayfields['t.tms']['checked'])) print '<td class="liste_titre">&nbsp;</td>';

// Right column with buttons
print '<td class="liste_titre center">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';

print '</tr>';

// Title line
print '<tr class="liste_titre">';
if (!empty($arrayfields['t.ref']['checked'])) print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], 't.ref', '', '', '', $sortfield, $sortorder);
if (!empty($arrayfields['user']['checked'])) print_liste_field_titre($arrayfields['user']['label'], $_SERVER["PHP_SELF"], 'u.lastname', '', '', '', $sortfield, $sortorder);
if (!empty($arrayfields['t.year']['checked'])) print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], 't.year', '', '', '', $sortfield, $sortorder);
if (!empty($arrayfields['t.week']['checked'])) print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], 't.week', '', '', '', $sortfield, $sortorder);
if (!empty($arrayfields['validator']['checked'])) print_liste_field_titre($arrayfields['validator']['label'], $_SERVER["PHP_SELF"], 'v.lastname', '', '', '', $sortfield, $sortorder);
if (!empty($arrayfields['t.total_hours']['checked'])) print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], 't.total_hours', '', '', '', $sortfield, $sortorder, 'right');
if (!empty($arrayfields['t.overtime_hours']['checked'])) print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], 't.overtime_hours', '', '', '', $sortfield, $sortorder, 'right');
if (!empty($arrayfields['t.status']['checked'])) print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], 't.status', '', '', '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['t.date_creation']['checked'])) print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], 't.date_creation', '', '', '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['t.tms']['checked'])) print_liste_field_titre($arrayfields['t.tms']['label'], $_SERVER["PHP_SELF"], 't.tms', '', '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch');
print '</tr>';

// Loop
$totalarray = array();
$nbtotalofrecords = (isset($nbtotalofrecords) ? $nbtotalofrecords : 0);

$num = $resql ? $db->num_rows($resql) : 0;
$i = 0;
$imax = ($limit ? min($num, $limit) : $num);

$u = new User($db);
$v = new User($db);
$ts = new TimesheetWeek($db);

while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	// Ref
	if (!empty($arrayfields['t.ref']['checked'])) {
		$ts->id = $obj->rowid;
		$ts->ref = $obj->ref;
		print '<td class="nowrap">';
		print '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a>';
		print '</td>';
	}

	// User
	if (!empty($arrayfields['user']['checked'])) {
		print '<td class="left">';
		$u->id = $obj->uid;
		$u->lastname = $obj->ulastname;
		$u->firstname = $obj->ufirstname;
		$u->login = $obj->ulogin;
		print $u->getNomUrl(-1);
		print '</td>';
	}

	// Year
	if (!empty($arrayfields['t.year']['checked'])) {
		print '<td class="center">'.(int)$obj->year.'</td>';
	}

	// Week
	if (!empty($arrayfields['t.week']['checked'])) {
		print '<td class="center">'.(int)$obj->week.'</td>';
	}

	// Validator
	if (!empty($arrayfields['validator']['checked'])) {
		print '<td class="left">';
		if ((int)$obj->vid > 0) {
			$v->id = $obj->vid;
			$v->lastname = $obj->vlastname;
			$v->firstname = $obj->vfirstname;
			$v->login = $obj->vlogin;
			print $v->getNomUrl(-1);
		} else {
			print '&nbsp;';
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

	// Status
	if (!empty($arrayfields['t.status']['checked'])) {
		$ts->status = $obj->status;
		print '<td class="center">'.$ts->getLibStatut(5).'</td>';
	}

	// Date creation
	if (!empty($arrayfields['t.date_creation']['checked'])) {
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
	}

	// TMS
	if (!empty($arrayfields['t.tms']['checked'])) {
		print '<td class="center">'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
	}

	print '<td class="center">&nbsp;</td>';

	print '</tr>';

	$i++;
}

if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key=>$val) if (!empty($val['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
