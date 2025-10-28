<?php
/* Copyright (C) 2025  Pierre Ardoin
 * GPL v3
 */

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

$permViewAny = ($permRead || $permReadChild || $permReadAll || $permWrite || $permWriteChild || $permWriteAll || $permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin));
if (!$permViewAny) accessforbidden();

/* ---- PARAMS ---- */
$action       = GETPOST('action', 'aZ09') ?: 'list';
$massaction   = GETPOST('massaction', 'alpha');
$confirm      = GETPOST('confirm', 'alpha');
$cancel       = GETPOST('cancel', 'alpha');
$toselect     = GETPOST('toselect', 'array:int');
$contextpage  = GETPOST('contextpage', 'aZ') ?: 'timesheetweeklist';

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

/* ---- ACTIONS ---- */
if (GETPOST('cancel','alpha')) { $action='list'; $massaction=''; }
if (!GETPOST('confirmmassaction','alpha') && $massaction!='presend' && $massaction!='confirm_presend') { $massaction=''; }

/* Contexte massactions */
$objectclass = 'TimesheetWeek';
$objectlabel = 'TimesheetWeek';
$object = new TimesheetWeek($db);
$permissiontoread   = ($permRead || $permReadChild || $permReadAll);
$permissiontoadd    = ($permWrite || $permWriteChild || $permWriteAll);
$permissiontodelete = ($permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin));
$uploaddir  = !empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek';

/* Handler générique: confirmations, selectall, presend */
include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

/* Handlers spécifiques après confirmation */
$arrayofselected = is_array($toselect) ? $toselect : array();

if ($massaction === 'delete') {
	if (!$permissiontodelete) {
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$db->begin(); $ok=0; $ko=array();
		foreach ((array)$arrayofselected as $id) {
			$id=(int)$id; if ($id<=0) continue;
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) { $ko[]='#'.$id; continue; }
			$res = $o->delete($user);
			if ($res>0) $ok++; else $ko[] = ($o->ref ?: '#'.$id);
		}
		if ($ko) $db->rollback(); else $db->commit();
		if ($ok) setEventMessages($langs->trans('RecordsDeleted',$ok), null, 'mesgs');
		if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors', implode(', ',$ko)), null, 'errors');
	}
	$massaction='';
}

/* ---- SQL ---- */
if (!$sortfield) $sortfield = 't.rowid';
if (!$sortorder) $sortorder = 'DESC';

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
