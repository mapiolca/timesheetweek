<?php
/* List of weekly timesheets */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

$langs->loadLangs(array('timesheetweek@timesheetweek', 'projects', 'users'));

// --- Permissions (Dolibarr standard) ---
$permRead          = $user->hasRight('timesheetweek', 'timesheetweek', 'read');
$permReadAll       = $user->hasRight('timesheetweek', 'timesheetweek', 'readAll');
$permReadChild     = $user->hasRight('timesheetweek', 'timesheetweek', 'readChild');
$permCreate        = $user->hasRight('timesheetweek', 'timesheetweek', 'create');
$permCreateChild   = $user->hasRight('timesheetweek', 'timesheetweek', 'createChild');
$permCreateAll     = $user->hasRight('timesheetweek', 'timesheetweek', 'createAll');
$permValidate      = $user->hasRight('timesheetweek', 'timesheetweek', 'validate');
$permValidateChild = $user->hasRight('timesheetweek', 'timesheetweek', 'validateChild');
$permValidateAll   = $user->hasRight('timesheetweek', 'timesheetweek', 'validateAll');
$permDelete        = $user->hasRight('timesheetweek', 'timesheetweek', 'delete');
$permDeleteChild   = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteAll');
$permExport        = $user->hasRight('timesheetweek', 'timesheetweek', 'export');

if (!$permRead) accessforbidden();

$action      = GETPOST('action', 'aZ09');
$massaction  = GETPOST('massaction', 'alpha');
$confirm     = GETPOST('confirm', 'alpha');
$toselect    = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetweeklist';

// Pagination
$limit 		= GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield	= GETPOST('sortfield', 'aZ09comma');
$sortorder	= GETPOST('sortorder', 'aZ09comma');
$page 		= GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;
if (!$sortorder) $sortorder = "DESC";
if (!$sortfield) $sortfield = "t.rowid";

// Filters
$search_ref      = GETPOST('search_ref', 'alpha');
$search_user     = GETPOST('search_user', 'intcomma');
$search_validator= GETPOST('search_validator', 'intcomma');
$search_year     = GETPOSTINT('search_year');
$search_week     = GETPOSTINT('search_week');
$search_status   = GETPOST('search_status', 'intcomma');

// Restrict by rights
$childids = $user->getAllChildIds(1);
$restrictUserIds = array();
if (!$permReadAll) {
	if ($permReadChild) $restrictUserIds = array_merge(array($user->id), $childids);
	else $restrictUserIds = array($user->id);
}

// Object & extrafields
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Columns
$arrayfields = array(
	't.ref'            => array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'u.user'           => array('label'=>$langs->trans("Employee"), 'checked'=>1),
	't.year'           => array('label'=>$langs->trans("Year"), 'checked'=>1),
	't.week'           => array('label'=>$langs->trans("Week"), 'checked'=>1),
	't.total_hours'    => array('label'=>$langs->trans("TotalHours"), 'checked'=>1),
	't.overtime_hours' => array('label'=>$langs->trans("Overtime"), 'checked'=>1),
	't.date_creation'  => array('label'=>$langs->trans("DateCreation"), 'checked'=>0),
	't.date_validation'=> array('label'=>$langs->trans("DateValidation"), 'checked'=>0),
	'v.validator'      => array('label'=>$langs->trans("Validator"), 'checked'=>1),
	't.status'         => array('label'=>$langs->trans("Status"), 'checked'=>1),
);
// Extrafields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

// Purge filters
if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search_ref = $search_user = $search_validator = '';
	$search_year = $search_week = '';
	$search_status = '';
	$search_array_options = array();
}

// Build SQL
$sql = "SELECT";
$sql .= " t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.note, t.date_creation, t.date_validation, t.fk_user_valid, t.tms, t.total_hours, t.overtime_hours";
$sql .= ", u.rowid as uid, u.lastname as ulastname, u.firstname as ufirstname, u.login as ulogin";
$sql .= ", v.rowid as vid, v.lastname as vlastname, v.firstname as vfirstname, v.login as vlogin";
// Extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		if ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate') {
			$sql .= ", ef.".$db->escape($key)." as options_".$db->escape($key);
		}
	}
}
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
$sql .= " WHERE t.entity IN (".getEntity('timesheetweek').")";

if (!empty($search_ref))           $sql .= natural_search('t.ref', $search_ref);
if ($search_user !== '' && $search_user >= 0) $sql .= " AND t.fk_user IN (".$db->sanitize($search_user).")";
if ($search_validator !== '' && $search_validator >= 0) $sql .= " AND t.fk_user_valid IN (".$db->sanitize($search_validator).")";
if (!empty($search_year))          $sql .= " AND t.year = ".((int) $search_year);
if (!empty($search_week))          $sql .= " AND t.week = ".((int) $search_week);
if ($search_status !== '' && $search_status !== null) $sql .= " AND t.status IN (".$db->sanitize($search_status).")";

// Restrict rights
if (!$permReadAll) {
	if (!empty($restrictUserIds)) {
		$sql .= " AND t.fk_user IN (".$db->sanitize(implode(',', $restrictUserIds)).")";
	} else {
		$sql .= " AND t.fk_user = ".((int) $user->id);
	}
}

// Extrafields filters
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';

$sqlfields = $sql;
$sql .= $db->order($sortfield, $sortorder);
if (!empty($limit)) $sql .= $db->plimit($limit + 1, $offset);

// Count
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlcount = preg_replace('/^SELECT .* FROM/i', 'SELECT COUNT(*) as nb FROM', $sqlfields);
	$sqlcount = preg_replace('/\sORDER BY .*$/i', '', $sqlcount);
	$sqlcount = preg_replace('/\sLIMIT .*$/i', '', $sqlcount);
	$resc = $db->query($sqlcount);
	if ($resc) { $obj = $db->fetch_object($resc); $nbtotalofrecords = (int) $obj->nb; $db->free($resc); }
}

// Exec
$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

// Output
$form = new Form($db);
$title = $langs->trans("TimesheetWeekList");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist');

$param = '';
if ($search_ref)        $param .= '&search_ref='.urlencode($search_ref);
if ($search_user !== '' && $search_user >= 0) $param .= '&search_user='.urlencode($search_user);
if ($search_validator !== '' && $search_validator >= 0) $param .= '&search_validator='.urlencode($search_validator);
if ($search_year)       $param .= '&search_year='.urlencode((string) $search_year);
if ($search_week)       $param .= '&search_week='.urlencode((string) $search_week);
if ($search_status !== '' && $search_status !== null) $param .= '&search_status='.urlencode($search_status);

// Top buttons
$newcardbutton = '';
if ($permCreate || $permCreateChild || $permCreateAll) {
	$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create');
}
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', '', $nbtotalofrecords, 'time', 0, $newcardbutton, '', $limit);

// Filters row
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="searchFormList">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

// Row filter inputs
print '<tr class="liste_titre_filter">';

// Ref
if (!empty($arrayfields['t.ref']['checked'])) {
	print '<td class="liste_titre"><input type="text" class="flat" size="8" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
} else print '<td></td>';

// Employee
if (!empty($arrayfields['u.user']['checked'])) {
	print '<td class="liste_titre">';
	if ($permReadAll || $permReadChild) {
		print $form->select_dolusers($search_user, 'search_user', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	} else {
		print '&nbsp;';
	}
	print '</td>';
} else print '<td></td>';

// Year
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre center"><input type="text" class="flat maxwidth50" name="search_year" value="'.dol_escape_htmltag($search_year).'"></td>';
} else print '<td></td>';

// Week
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre center"><input type="text" class="flat maxwidth50" name="search_week" value="'.dol_escape_htmltag($search_week).'"></td>';
} else print '<td></td>';

// Total hours
if (!empty($arrayfields['t.total_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
} else print '<td></td>';

// Overtime
if (!empty($arrayfields['t.overtime_hours']['checked'])) {
	print '<td class="liste_titre right">&nbsp;</td>';
} else print '<td></td>';

// Date creation
if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="liste_titre">&nbsp;</td>'; else print '<td></td>';

// Date validation
if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="liste_titre">&nbsp;</td>'; else print '<td></td>';

// Validator
if (!empty($arrayfields['v.validator']['checked'])) {
	print '<td class="liste_titre">';
	print $form->select_dolusers($search_validator, 'search_validator', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
} else print '<td></td>';

// Status
if (!empty($arrayfields['t.status']['checked'])) {
	print '<td class="liste_titre center">';
	print '<select class="flat" name="search_status">';
	print '<option value=""></option>';
	$statuses = array(
		TimesheetWeek::STATUS_DRAFT      => $langs->trans('Draft'),
		TimesheetWeek::STATUS_INPROGRESS => $langs->trans('InProgress'),
		TimesheetWeek::STATUS_SUBMITTED  => $langs->trans('Submitted'),
		TimesheetWeek::STATUS_APPROVED   => $langs->trans('Approved'),
		TimesheetWeek::STATUS_REFUSED    => $langs->trans('Refused'),
	);
	foreach ($statuses as $k=>$lab) {
		print '<option value="'.$k.'"'.(($search_status!=='') && $search_status==$k?' selected':'').'>'.$lab.'</option>';
	}
	print '</select>';
	print '</td>';
} else print '<td></td>';

// Action buttons filter
print '<td class="liste_titre center">';
print $form->showFilterButtons();
print '</td>';

print '</tr>';

// Header row
print '<tr class="liste_titre">';
if (!empty($arrayfields['t.ref']['checked']))            print_liste_field_titre($arrayfields['t.ref']['label'], $_SERVER["PHP_SELF"], 't.ref', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['u.user']['checked']))           print_liste_field_titre($arrayfields['u.user']['label'], $_SERVER["PHP_SELF"], 'u.lastname', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.year']['checked']))           print_liste_field_titre($arrayfields['t.year']['label'], $_SERVER["PHP_SELF"], 't.year', '', $param, '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['t.week']['checked']))           print_liste_field_titre($arrayfields['t.week']['label'], $_SERVER["PHP_SELF"], 't.week', '', $param, '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['t.total_hours']['checked']))    print_liste_field_titre($arrayfields['t.total_hours']['label'], $_SERVER["PHP_SELF"], 't.total_hours', '', $param, '', $sortfield, $sortorder, 'right');
if (!empty($arrayfields['t.overtime_hours']['checked'])) print_liste_field_titre($arrayfields['t.overtime_hours']['label'], $_SERVER["PHP_SELF"], 't.overtime_hours', '', $param, '', $sortfield, $sortorder, 'right');
if (!empty($arrayfields['t.date_creation']['checked']))  print_liste_field_titre($arrayfields['t.date_creation']['label'], $_SERVER["PHP_SELF"], 't.date_creation', '', $param, '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['t.date_validation']['checked']))print_liste_field_titre($arrayfields['t.date_validation']['label'], $_SERVER["PHP_SELF"], 't.date_validation', '', $param, '', $sortfield, $sortorder, 'center');
if (!empty($arrayfields['v.validator']['checked']))      print_liste_field_titre($arrayfields['v.validator']['label'], $_SERVER["PHP_SELF"], 'v.lastname', '', $param, '', $sortfield, $sortorder);
if (!empty($arrayfields['t.status']['checked']))         print_liste_field_titre($arrayfields['t.status']['label'], $_SERVER["PHP_SELF"], 't.status', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder, 'center');
print '</tr>'."\n";

// Loop
$num = $resql ? $db->num_rows($resql) : 0;
$timesheetstatic = new TimesheetWeek($db);
$userstatic = new User($db);

$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	$timesheetstatic->id = $obj->rowid;
	$timesheetstatic->ref = $obj->ref;
	$timesheetstatic->status = $obj->status;

	print '<tr class="oddeven">';

	if (!empty($arrayfields['t.ref']['checked'])) {
		print '<td>'.$timesheetstatic->getNomUrl(1).'</td>';
	}
	if (!empty($arrayfields['u.user']['checked'])) {
		$userstatic->id = $obj->uid; $userstatic->lastname = $obj->ulastname; $userstatic->firstname = $obj->ufirstname;
		print '<td>'.$userstatic->getNomUrl(-1).'</td>';
	}
	if (!empty($arrayfields['t.year']['checked'])) print '<td class="center">'.dol_escape_htmltag($obj->year).'</td>';
	if (!empty($arrayfields['t.week']['checked'])) print '<td class="center">'.dol_escape_htmltag($obj->week).'</td>';
	if (!empty($arrayfields['t.total_hours']['checked'])) print '<td class="right">'.dol_escape_htmltag(number_format((float)$obj->total_hours, 2, ',', ' ')).'</td>';
	if (!empty($arrayfields['t.overtime_hours']['checked'])) print '<td class="right">'.dol_escape_htmltag(number_format((float)$obj->overtime_hours, 2, ',', ' ')).'</td>';
	if (!empty($arrayfields['t.date_creation']['checked'])) print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
	if (!empty($arrayfields['t.date_validation']['checked'])) print '<td class="center">'.dol_print_date($db->jdate($obj->date_validation), 'dayhour').'</td>';
	if (!empty($arrayfields['v.validator']['checked'])) {
		if (!empty($obj->vid)) {
			$userv = new User($db);
			$userv->id = $obj->vid; $userv->lastname = $obj->vlastname; $userv->firstname = $obj->vfirstname;
			print '<td>'.$userv->getNomUrl(-1).'</td>';
		} else {
			print '<td class="opacitymedium">'.$langs->trans("None").'</td>';
		}
	}
	if (!empty($arrayfields['t.status']['checked'])) print '<td class="center">'.$timesheetstatic->getLibStatut(5).'</td>';

	print '<td class="center">&nbsp;</td>';

	print '</tr>';

	$i++;
}

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
        if (!empty($val['checked'])) $colspan++;
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
