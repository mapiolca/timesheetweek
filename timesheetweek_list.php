<?php
/* Copyright (C) 2025
 * Author: Pierre ARDOIN
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // doit contenir formatHours()

$langs->loadLangs(array('timesheetweek@timesheetweek','other','projects'));

// ----------------------------------------------------------------------------
// Permissions
// ----------------------------------------------------------------------------
$permRead      = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll   = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite     = !empty($user->rights->timesheetweek->timesheetweek->write);
if (!$permRead) accessforbidden();

// ----------------------------------------------------------------------------
$hookmanager->initHooks(array('timesheetweeklist'));

// ----------------------------------------------------------------------------
// Parameters
// ----------------------------------------------------------------------------
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 50);
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page      = (GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page'));
if (!is_numeric($page) || $page < 0) $page = 0;
$offset = $limit * $page;

$contextpage = GETPOST('contextpage', 'aZ') ?: 'timesheetweeklist';
$action      = GETPOST('action', 'aZ09');

// Search filters
$search_ref       = trim(GETPOST('search_ref','alpha'));
$search_user      = GETPOSTINT('search_user');
$search_validator = GETPOSTINT('search_validator');
$search_year      = GETPOSTINT('search_year');
$search_week      = GETPOSTINT('search_week');
$search_status    = GETPOST('search_status','alpha'); // may be '' or '0','1','2',...
$search_note      = trim(GETPOST('search_note','alpha'));

// Mass actions (not used here)
$massaction = GETPOST('massaction', 'alpha');

// Manage remove filters
if (GETPOST('button_removefilter_x') || GETPOST('button_removefilter')) {
	$search_ref = $search_note = '';
	$search_user = $search_validator = $search_year = $search_week = 0;
	$search_status = '';
}

// ----------------------------------------------------------------------------
// Objects
// ----------------------------------------------------------------------------
$form   = new Form($db);
$object = new TimesheetWeek($db);

// ----------------------------------------------------------------------------
// Arrayfields (columns visibility)
// ----------------------------------------------------------------------------
$arrayfields = array(
	't.rowid'           => array('label'=>'TechnicalID',     'checked'=>0, 'position'=>10),
	't.ref'             => array('label'=>'Ref',             'checked'=>1, 'position'=>20),
	't.fk_user'         => array('label'=>'Employee',        'checked'=>1, 'position'=>30),
	't.year'            => array('label'=>'Year',            'checked'=>1, 'position'=>40),
	't.week'            => array('label'=>'Week',            'checked'=>1, 'position'=>50),
	't.status'          => array('label'=>'Status',          'checked'=>1, 'position'=>60),
	't.note'            => array('label'=>'Note',            'checked'=>0, 'position'=>70),
	't.date_creation'   => array('label'=>'DateCreation',    'checked'=>1, 'position'=>80),
	't.date_validation' => array('label'=>'DateValidation',  'checked'=>1, 'position'=>90),
	't.fk_user_valid'   => array('label'=>'Validator',       'checked'=>1, 'position'=>100),
	't.total_hours'     => array('label'=>'TotalHours',      'checked'=>1, 'position'=>110),
	't.overtime_hours'  => array('label'=>'Overtime',        'checked'=>1, 'position'=>120),
	't.tms'             => array('label'=>'DateModification','checked'=>0, 'position'=>130),
);

// ----------------------------------------------------------------------------
// Build SQL
// ----------------------------------------------------------------------------
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.ref,";
$sql .= " t.fk_user,";
$sql .= " t.year,";
$sql .= " t.week,";
$sql .= " t.status,";
$sql .= " t.note,";
$sql .= " t.date_creation,";
$sql .= " t.date_validation,";
$sql .= " t.fk_user_valid,";
$sql .= " t.tms,";
$sql .= " t.total_hours,";
$sql .= " t.overtime_hours,";
$sql .= " u.login as user_login, u.firstname as user_firstname, u.lastname as user_lastname,";
$sql .= " v.login as valid_login, v.firstname as valid_firstname, v.lastname as valid_lastname";
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as v ON v.rowid = t.fk_user_valid";
$sql .= " WHERE 1=1";

// Permissions filter
if (!$permReadAll && !$permReadChild) {
	// Only own
	$sql .= " AND t.fk_user = ".((int) $user->id);
} elseif ($permReadChild && !$permReadAll) {
	$childs = $user->getAllChildIds();
	if (!is_array($childs) || empty($childs)) {
		$sql .= " AND t.fk_user = ".((int) $user->id);
	} else {
		$sql .= " AND (t.fk_user = ".((int) $user->id)." OR t.fk_user IN (".implode(',', array_map('intval',$childs))."))";
	}
}

// Search filters
if ($search_ref !== '')        $sql .= natural_search('t.ref', $search_ref);
if ($search_note !== '')       $sql .= natural_search('t.note', $search_note);
if ($search_user > 0)          $sql .= " AND t.fk_user = ".((int) $search_user);
if ($search_validator > 0)     $sql .= " AND t.fk_user_valid = ".((int) $search_validator);
if ($search_year > 0)          $sql .= " AND t.year = ".((int) $search_year);
if ($search_week > 0)          $sql .= " AND t.week = ".((int) $search_week);
if ($search_status !== '' && $search_status !== '-1') {
	$sql .= " AND t.status = ".((int) $search_status);
}

// Sorting
if (!$sortfield) $sortfield = 't.rowid';
if (!$sortorder) $sortorder = 'DESC';
$sql .= $db->order($sortfield, $sortorder);

// Count for pagination
$sqlCount = preg_replace('/SELECT .* FROM/i', 'SELECT COUNT(*) as nb FROM', $sql);
$sqlCount = preg_replace('/ORDER BY .*$/i', '', $sqlCount);

$totalnb = 0;
$resql = $db->query($sqlCount);
if ($resql) { $obj = $db->fetch_object($resql); $totalnb = ($obj?$obj->nb:0); $db->free($resql); }

// ----------------------------------------------------------------------------
// Build param for links
// ----------------------------------------------------------------------------
$param = '';
$param .= ($search_ref !== '' ? '&search_ref='.urlencode($search_ref) : '');
$param .= ($search_note !== '' ? '&search_note='.urlencode($search_note) : '');
if ($search_user > 0)       $param .= '&search_user='.$search_user;
if ($search_validator > 0)  $param .= '&search_validator='.$search_validator;
if ($search_year > 0)       $param .= '&search_year='.$search_year;
if ($search_week > 0)       $param .= '&search_week='.$search_week;
if ($search_status !== '' && $search_status !== '-1') $param .= '&search_status='.$search_status;
$param .= '&limit='.$limit;

// ----------------------------------------------------------------------------
// Header
// ----------------------------------------------------------------------------
$title = $langs->trans("TimesheetWeekList");
llxHeader('', $title);

$newcardbutton = '';
if ($permWrite) {
	$newcardbutton = '<a class="butActionNew" href="'.dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create">'.$langs->trans("New").'</a>';
}

print '<form method="POST" id="searchForm" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

print_barre_liste(
	$langs->trans("TimesheetWeekList"),
	$page,
	$_SERVER["PHP_SELF"],
	$param,
	$sortfield,
	$sortorder,
	'',
	$totalnb,
	$limit,
	0,
	'object_timesheetweek@timesheetweek',
	0,
	$newcardbutton,
	'',
	$limit
);

// ----------------------------------------------------------------------------
// Table start
// ----------------------------------------------------------------------------
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// ---- Header row (sortable titles)
print '<tr class="liste_titre">';

foreach ($arrayfields as $key => $val) {
	if (empty($val['checked'])) continue;
	$align = (in_array($key, array('t.week','t.year','t.status','t.total_hours','t.overtime_hours')) ? 'right' : 'left');
	print_liste_field_titre(
		$langs->trans($val['label']),
		$_SERVER["PHP_SELF"],
		$key,
		'',
		$param,
		'',
		$align
	);
}
print_liste_field_titre('&nbsp;', $_SERVER["PHP_SELF"], '', '', $param, 'center', 'center');
print '</tr>';

// ---- Filter row
print '<tr class="liste_titre">';

foreach ($arrayfields as $key => $val) {
	if (empty($val['checked'])) continue;

	$tdclass = 'liste_titre';
	if (in_array($key, array('t.week','t.year','t.status'))) $tdclass .= ' right';
	print '<td class="'.$tdclass.'">';

	if ($key === 't.ref') {
		print '<input class="flat" type="text" size="8" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
	} elseif ($key === 't.fk_user') {
		print $form->select_dolusers($search_user, 'search_user', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'minwidth150 maxwidth200');
	} elseif ($key === 't.year') {
		print '<input class="flat" type="number" min="2000" max="2099" name="search_year" value="'.($search_year>0?$search_year:'').'" style="width:80px">';
	} elseif ($key === 't.week') {
		print '<input class="flat" type="number" min="1" max="53" name="search_week" value="'.($search_week>0?$search_week:'').'" style="width:70px">';
	} elseif ($key === 't.status') {
		print '<select class="flat" name="search_status">';
		print '<option value="-1">&nbsp;</option>';
		$st = array(
			0 => $langs->trans('Draft'),
			1 => $langs->trans('InProgress'),
			2 => $langs->trans('Submitted'),
			3 => $langs->trans('Approved'),
			4 => $langs->trans('Refused'),
		);
		foreach ($st as $k=>$lab) {
			print '<option value="'.$k.'"'.(($search_status!==''
				&& $search_status!=='-1'
				&& (int)$search_status===$k)?' selected':'').'>'.$lab.'</option>';
		}
		print '</select>';
	} elseif ($key === 't.note') {
		print '<input class="flat quatrevingtpercent" type="text" name="search_note" value="'.dol_escape_htmltag($search_note).'">';
	} elseif ($key === 't.fk_user_valid') {
		print $form->select_dolusers($search_validator, 'search_validator', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'minwidth150 maxwidth200');
	} else {
		print '&nbsp;';
	}

	print '</td>';
}

print '<td class="liste_titre center">';
print '<input type="submit" class="button small" name="button_search" value="'.$langs->trans('Search').'">';
print '&nbsp; ';
print '<input type="submit" class="button small button_removefilter" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'">';
print '</td>';

print '</tr>';

// ----------------------------------------------------------------------------
// Fetch & render rows
// ----------------------------------------------------------------------------
$sqlList = $sql.$db->plimit($limit+1, $offset);
$resql = $db->query($sqlList);
if (!$resql) {
	setEventMessages($db->lasterror(), null, 'errors');
} else {
	$num = $db->num_rows($resql);
	$i = 0;

	while ($i < min($num, $limit)) {
		$obj = $db->fetch_object($resql);

		print '<tr class="oddeven">';

		foreach ($arrayfields as $key => $val) {
			if (empty($val['checked'])) continue;

			$align = (in_array($key, array('t.week','t.year','t.status','t.total_hours','t.overtime_hours')) ? 'right' : 'left');
			print '<td class="'.$align.'">';

			switch ($key) {
				case 't.rowid':
					print (int) $obj->rowid;
					break;

				case 't.ref':
					$link = dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?id='.(int)$obj->rowid;
					print '<a href="'.$link.'">'.dol_escape_htmltag($obj->ref).'</a>';
					break;

				case 't.fk_user':
					if ((int) $obj->fk_user > 0) {
						$u = new User($db);
						if ($u->fetch((int)$obj->fk_user) > 0) print $u->getNomUrl(1);
						else print (int) $obj->fk_user;
					}
					break;

				case 't.year':
					print (int) $obj->year;
					break;

				case 't.week':
					print (int) $obj->week;
					break;

				case 't.status':
					$map = array(
						0 => $langs->trans('Draft'),
						1 => $langs->trans('InProgress'),
						2 => $langs->trans('Submitted'),
						3 => $langs->trans('Approved'),
						4 => $langs->trans('Refused'),
					);
					$txt = isset($map[(int)$obj->status]) ? $map[(int)$obj->status] : (string) $obj->status;
					print dol_escape_htmltag($txt);
					break;

				case 't.note':
					print dol_escape_htmltag($obj->note);
					break;

				case 't.date_creation':
					print dol_print_date($db->jdate($obj->date_creation), 'dayhour');
					break;

				case 't.date_validation':
					print dol_print_date($db->jdate($obj->date_validation), 'dayhour');
					break;

				case 't.fk_user_valid':
					if ((int) $obj->fk_user_valid > 0) {
						$uv = new User($db);
						if ($uv->fetch((int)$obj->fk_user_valid) > 0) print $uv->getNomUrl(1);
						else print (int) $obj->fk_user_valid;
					}
					break;

				case 't.total_hours':
					print formatHours((float)$obj->total_hours);
					break;

				case 't.overtime_hours':
					print formatHours((float)$obj->overtime_hours);
					break;

				case 't.tms':
					print dol_print_date($db->jdate($obj->tms), 'dayhour');
					break;

				default:
					print '&nbsp;';
			}

			print '</td>';
		}

		// Action column
		print '<td class="center">';
		$link = dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?id='.(int)$obj->rowid;
		print '<a class="btn btn-link" href="'.$link.'">'.$langs->trans('Card').'</a>';
		print '</td>';

		print '</tr>';

		$i++;
	}

	print '</table>';
	print '</div>'; // .div-table-responsive

	$db->free($resql);
}

// Bottom line with pagination info is already handled by print_barre_liste() at top

// Buttons (new)
print '<div class="tabsAction">';
if ($permWrite) {
	print '<a class="butActionNew" href="'.dol_buildpath('/timesheetweek/timesheetweek_card.php',1).'?action=create">'.$langs->trans("New").'</a>';
}
print '</div>';

print '</form>';

llxFooter();
$db->close();
