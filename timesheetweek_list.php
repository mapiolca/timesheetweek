<?php
// Load Dolibarr environment
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
 * Security
 */
if (!$user->hasRight('timesheetweek','timesheetweek','read')) accessforbidden();

/**
 * Objects
 */
$form = new Form($db);
$tswstatic = new TimesheetWeek($db);
$usertmp = new User($db);

/**
 * Arrayfields (select columns)
 */
$arrayfields = array(
	't.ref'          => array('label' => $langs->trans("Ref"),          'checked' => 1),
	'user'           => array('label' => $langs->trans("Employee"),     'checked' => 1),
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
	't.tms'          => array('label' => $langs->trans("DateModificationShort"), 'checked' => 0),
	't.status'       => array('label' => $langs->trans("Status"),       'checked' => 1),
);

// Update arrayfields from request (column selector)
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

/**
 * Mass actions (UI)
 */
$arrayofmassactions = array(
	'approve_selection' => img_picto('', 'validate', 'class="pictofixedwidth"').$langs->trans("ApproveSelection"),
	'refuse_selection'  => img_picto('', 'warning',  'class="pictofixedwidth"').$langs->trans("RefuseSelection"),
	'predelete'         => img_picto('', 'delete',   'class="pictofixedwidth"').$langs->trans("DeleteSelection"),
);
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$arrayofselected = is_array($toselect) ? $toselect : array();

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_ref = '';
    $search_user = 0;
    $search_year = 0;
    $search_week = 0;
    $search_status = array();
}

/**
 * SQL
 */
$sql  = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status, t.total_hours, t.overtime_hours,";
// EN: Expose zone and meal counters in the list query.
// FR: Expose les compteurs de zones et de paniers dans la requête de liste.
$sql .= " t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count,";
$sql .= " t.date_creation, t.tms, t.date_validation, t.fk_user_valid,";
$sql .= " u.rowid as uid, u.firstname, u.lastname, u.login";
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " WHERE 1=1";
if ($search_ref !== '')     $sql .= natural_search('t.ref', $search_ref);
if ($search_user > 0)       $sql .= " AND t.fk_user = ".((int)$search_user);
if ($search_year > 0)       $sql .= " AND t.year = ".((int)$search_year);
if ($search_week > 0)       $sql .= " AND t.week = ".((int)$search_week);
if (!empty($search_status)) {
    $statusFilter = array();
    foreach ($search_status as $statusValue) {
        $statusFilter[] = (int) $statusValue;
    }
    if (!empty($statusFilter)) {
        $sql .= ' AND t.status IN ('.implode(',', $statusFilter).')';
    }
}
if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) dol_print_error($db);
$num = $resql ? $db->num_rows($resql) : 0;

/**
 * Header
 */
$title = $langs->trans("TimesheetWeekList");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist page-list');

/**
 * Build param for pagination links
 */
$param = '';
if ($search_ref)   $param .= '&search_ref='.urlencode($search_ref);
if ($search_user)  $param .= '&search_user='.(int)$search_user;
if ($search_year)  $param .= '&search_year='.(int)$search_year;
if ($search_week)  $param .= '&search_week='.(int)$search_week;
if (!empty($search_status)) {
    foreach ($search_status as $statusValue) {
        $param .= '&search_status[]='.(int) $statusValue;
    }
}

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?action=create', '', $user->hasRight('timesheetweek','timesheetweek','write'));

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, '', 'bookcal', 0, $newcardbutton, '', $limit, 0, 0, 1);

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
	print $form->select_dolusers($search_user, 'search_user', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}
if (!empty($arrayfields['t.year']['checked'])) {
	print '<td class="liste_titre center"><input class="flat" type="number" name="search_year" value="'.($search_year>0?(int)$search_year:'').'" style="width:80px"></td>';
}
if (!empty($arrayfields['t.week']['checked'])) {
	print '<td class="liste_titre center"><input class="flat" type="number" name="search_week" value="'.($search_week>0?(int)$search_week:'').'" min="1" max="53" style="width:70px"></td>';
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
if (!empty($arrayfields['t.tms']['checked'])) {
	print '<td class="liste_titre center">&nbsp;</td>';
}
if (!empty($arrayfields['t.status']['checked'])) {
        $statusOptions = array(
                TimesheetWeek::STATUS_DRAFT     => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_DRAFT, 0),
                TimesheetWeek::STATUS_SUBMITTED => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_SUBMITTED, 0),
                TimesheetWeek::STATUS_APPROVED  => TimesheetWeek::LibStatut(TimesheetWeek::STATUS_APPROVED, 0),
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
		print '<td>'.$usertmp->getNomUrl(-1).'</td>';
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
	// Modification
	if (!empty($arrayfields['t.tms']['checked'])) {
		print '<td class="center">'.($obj->tms ? dol_print_date($db->jdate($obj->tms),'dayhour') : '').'</td>';
	}
	// Status (badge)
	if (!empty($arrayfields['t.status']['checked'])) {
		$tswstatic->status = $obj->status;
		print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>';
	}
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

if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $k=>$v) if (!empty($v['checked'])) $colspan++;
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
