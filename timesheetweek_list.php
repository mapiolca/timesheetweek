<?php
// Load Dolibarr env
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','other','users'));

$action      = GETPOST('action', 'aZ09');
$sortfield   = GETPOST('sortfield', 'aZ09comma');
$sortorder   = GETPOST('sortorder', 'aZ09comma');
$page        = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) $page = 0;
$limit       = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$offset      = $limit * $page;

$search_ref  = trim(GETPOST('search_ref','alphanohtml'));
$search_user = GETPOSTINT('search_user');
$search_year = GETPOSTINT('search_year');
$search_week = GETPOSTINT('search_week');
$search_status = GETPOST('search_status','alpha');

// Security
if (!$user->hasRight('timesheetweek','timesheetweek','read')) accessforbidden();

$form = new Form($db);

$tswstatic = new TimesheetWeek($db);

// SQL
$sql = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours,";
$sql.= " t.date_creation, t.tms, t.date_validation, t.fk_user_valid,";
$sql.= " u.rowid as uid, u.firstname, u.lastname, u.login";
$sql.= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql.= " WHERE 1=1";
if ($search_ref !== '')     $sql.= natural_search('t.ref', $search_ref);
if ($search_user > 0)       $sql.= " AND t.fk_user = ".((int)$search_user);
if ($search_year > 0)       $sql.= " AND t.year = ".((int)$search_year);
if ($search_week > 0)       $sql.= " AND t.week = ".((int)$search_week);
if ($search_status !== '' && $search_status !== '-1') $sql.= " AND t.status IN (".$db->sanitize($search_status).")";

if (empty($sortfield)) $sortfield = "t.rowid";
if (empty($sortorder)) $sortorder = "DESC";
$sql.= $db->order($sortfield, $sortorder);
$sql.= $db->plimit($limit+1, $offset);

$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

$num = $resql ? $db->num_rows($resql) : 0;

$title = $langs->trans("TimesheetWeekList");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist');

$param = '';
if ($search_ref)  $param .= '&search_ref='.urlencode($search_ref);
if ($search_user) $param .= '&search_user='.(int)$search_user;
if ($search_year) $param .= '&search_year='.(int)$search_year;
if ($search_week) $param .= '&search_week='.(int)$search_week;
if ($search_status !== '') $param .= '&search_status='.urlencode($search_status);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, '', 'time', 0, '', '', $limit, 0, 0, 1);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre left">';
print $form->showFilterButtons();
print '</td>';
print '<td class="liste_titre">';
print '<input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="12">';
print '</td>';
print '<td class="liste_titre">';
print $form->select_dolusers($search_user, 'search_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
print '</td>';
print '<td class="liste_titre center">';
print '<input class="flat" type="number" name="search_year" value="'.($search_year>0?(int)$search_year:'').'" style="width:80px">';
print '</td>';
print '<td class="liste_titre center">';
print '<input class="flat" type="number" name="search_week" value="'.($search_week>0?(int)$search_week:'').'" style="width:60px" min="1" max="53">';
print '</td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center">';
print '<input class="flat" type="text" name="search_status" value="'.dol_escape_htmltag($search_status).'" size="6" placeholder="0,1,2">';
print '</td>';
print '</tr>';

// Titles
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans("Ref"), $_SERVER["PHP_SELF"], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("Employee"), $_SERVER["PHP_SELF"], 'u.lastname', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("Year"), $_SERVER["PHP_SELF"], 't.year', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans("Week"), $_SERVER["PHP_SELF"], 't.week', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans("TotalHours"), $_SERVER["PHP_SELF"], 't.total_hours', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans("OvertimeHours"), $_SERVER["PHP_SELF"], 't.overtime_hours', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans("Status"), $_SERVER["PHP_SELF"], 't.status', '', $param, '', $sortfield, $sortorder, 'center ');
print '</tr>';

// Rows
$us = new User($db);
$i = 0;
$imax = ($limit ? min($num, $limit) : $num);

while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	// Ref column: **picto + text ref**
	$tswstatic->id = $obj->rowid;
	$tswstatic->ref = $obj->ref;
	$tswstatic->status = $obj->status;
	print '<td>';
	print $tswstatic->getNomUrl(1).' '.dol_escape_htmltag($tswstatic->ref);
	print '</td>';

	// Employee
	$us->id = $obj->uid;
	$us->firstname = $obj->firstname;
	$us->lastname = $obj->lastname;
	$us->login = $obj->login;
	print '<td>'.$us->getNomUrl(-1).'</td>';

	// Year/Week
	print '<td class="center">'.(int)$obj->year.'</td>';
	print '<td class="center">'.(int)$obj->week.'</td>';

	// Total hours
	$tot = (float) $obj->total_hours;
	$hh = floor($tot);
	$mm = round(($tot - $hh) * 60);
	if ($mm == 60) { $hh++; $mm = 0; }
	print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';

	// Overtime hours
	$tot = (float) $obj->overtime_hours;
	$hh = floor($tot);
	$mm = round(($tot - $hh) * 60);
	if ($mm == 60) { $hh++; $mm = 0; }
	print '<td class="right">'.str_pad((string)$hh,2,'0',STR_PAD_LEFT).':'.str_pad((string)$mm,2,'0',STR_PAD_LEFT).'</td>';

	// Status badge
	$tswstatic->status = $obj->status;
	print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>';

	print '</tr>';

	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
