<?php
/* GPL v3 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');

$langs->loadLangs(array('timesheetweek@timesheetweek','other','users'));

/* --- DROITS --- */
$permRead         = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild    = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll      = $user->hasRight('timesheetweek','timesheetweek','readAll');
$permWrite        = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild   = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll     = $user->hasRight('timesheetweek','timesheetweek','writeAll');
$permDelete       = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild  = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll    = $user->hasRight('timesheetweek','timesheetweek','deleteAll');

$canView = ($permRead || $permReadChild || $permReadAll || $permWrite || $permWriteChild || $permWriteAll || $permDelete || $permDeleteChild || $permDeleteAll || !empty($user->admin));
if (!$canView) accessforbidden();

/* --- PARAMS --- */
$action      = GETPOST('action','aZ09');
$massaction  = GETPOST('massaction','alpha');
$confirm     = GETPOST('confirm','alpha');
$toselect    = GETPOST('toselect','array');

$sortfield   = GETPOST('sortfield','aZ09comma');
$sortorder   = GETPOST('sortorder','aZ09comma');
$page        = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) $page = 0;
$limit       = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset      = $limit * $page;

/* --- OBJETS UI --- */
$form      = new Form($db);
$tswstatic = new TimesheetWeek($db);
$usertmp   = new User($db);

/* --- CHAMPS LISTE MINIMES (ajoute tes colonnes ici) --- */
$arrayfields = array(
	't.ref'         => array('label' => $langs->trans('Ref'), 'checked' => 1),
	'user'          => array('label' => $langs->trans('Employee'), 'checked' => 1),
	't.year'        => array('label' => $langs->trans('Year'), 'checked' => 1),
	't.week'        => array('label' => $langs->trans('Week'), 'checked' => 1),
	't.status'      => array('label' => $langs->trans('Status'), 'checked' => 1),
);

/* --- SQL --- */
if (!$sortfield) $sortfield = 't.rowid';
if (!$sortorder) $sortorder = 'DESC';

$sql  = "SELECT t.rowid, t.ref, t.fk_user, t.year, t.week, t.status,";
$sql .= " u.rowid as uid, u.firstname, u.lastname, u.login, u.photo as user_photo, u.statut as user_status";
$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
$sql .= " JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
$sql .= " WHERE t.entity IN (".getEntity('timesheetweek').")";
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) dol_print_error($db);
$num = $resql ? $db->num_rows($resql) : 0;

/* --- EN-TETE --- */
$title = $langs->trans("TimesheetWeek_List");
llxHeader('', $title);

/* --- MASSACTIONS: DECLARATION + CONTEXTE + INCLUDE + CONFIRM --- */
$arrayofmassactions = array(
	'approve_selection' => img_picto('', 'validate','class="pictofixedwidth"').$langs->trans("ApproveSelection"),
	'refuse_selection'  => img_picto('', 'warning', 'class="pictofixedwidth"').$langs->trans("RefuseSelection"),
	'predelete'         => img_picto('', 'delete',  'class="pictofixedwidth"').$langs->trans("DeleteSelection"),
);

/* OUVERTURE FORM AVANT LA BARRE */
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="massaction" value="">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int)$page).'">';
print '<input type="hidden" name="limit" id="limit-hidden" value="'.((int)$limit).'">';

/* Sélecteur de massactions */
$massactionbutton = $form->selectMassAction($massaction, $arrayofmassactions);

/* Contexte requis par actions_massactions.inc.php */
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

/* Include générique: gère selectall, confirmations, predelete->delete */
include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

/* Affichage de la boîte de confirmation */
if ($massactionbutton || $massaction) print $formconfirm;

/* --- HANDLERS MASSACTIONS SERVEUR --- */
$arrayofselected = is_array($toselect) ? $toselect : array();

if ($massaction === 'approve_selection') {
	$ok = 0; $ko = array(); $db->begin();
	foreach ((array)$arrayofselected as $id) {
		$id = (int)$id; if ($id <= 0) continue;
		$o = new TimesheetWeek($db);
		if ($o->fetch($id) <= 0) { $ko[] = '#'.$id; continue; }
		$res = method_exists($o,'approve') ? $o->approve($user) : -1;
		if ($res > 0) $ok++; else $ko[] = ($o->ref ?: '#'.$id);
	}
	if ($ko) $db->rollback(); else $db->commit();
	if ($ok) setEventMessages($langs->trans('TimesheetWeekMassApproveSuccess',$ok), null, 'mesgs');
	if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors',implode(', ',$ko)), null, 'errors');
	$massaction = '';
}

if ($massaction === 'refuse_selection') {
	$ok = 0; $ko = array(); $db->begin();
	foreach ((array)$arrayofselected as $id) {
		$id = (int)$id; if ($id <= 0) continue;
		$o = new TimesheetWeek($db);
		if ($o->fetch($id) <= 0) { $ko[] = '#'.$id; continue; }
		$res = method_exists($o,'refuse') ? $o->refuse($user) : -1;
		if ($res > 0) $ok++; else $ko[] = ($o->ref ?: '#'.$id);
	}
	if ($ko) $db->rollback(); else $db->commit();
	if ($ok) setEventMessages($langs->trans('TimesheetWeekMassRefuseSuccess',$ok), null, 'mesgs');
	if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors',implode(', ',$ko)), null, 'errors');
	$massaction = '';
}

/* Important: predelete -> delete après clic Oui */
if ($massaction === 'delete') {
	if (!$permissiontodelete) {
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} else {
		$ok = 0; $ko = array(); $db->begin();
		foreach ((array)$arrayofselected as $id) {
			$id = (int)$id; if ($id <= 0) continue;
			$o = new TimesheetWeek($db);
			if ($o->fetch($id) <= 0) { $ko[] = '#'.$id; continue; }
			$res = $o->delete($user);
			if ($res > 0) $ok++; else $ko[] = ($o->ref ?: '#'.$id);
		}
		if ($ko) $db->rollback(); else $db->commit();
		if ($ok) setEventMessages($langs->trans('RecordsDeleted',$ok), null, 'mesgs');
		if ($ko) setEventMessages($langs->trans('TimesheetWeekMassActionErrors',implode(', ',$ko)), null, 'errors');
	}
	$massaction = '';
}

/* --- BARRE LISTE --- */
$nbtotalofrecords = ''; // calcule si besoin
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bookcal', 0, '', '', $limit, 0, 0, 1);

/* --- TABLE --- */
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">'."\n";

/* Ligne filtres minimale (ajoute tes filtres ici si besoin) */
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
print '<td class="liste_titre">&nbsp;</td>'; // ref
print '<td class="liste_titre">&nbsp;</td>'; // user
print '<td class="liste_titre center">&nbsp;</td>'; // year
print '<td class="liste_titre center">&nbsp;</td>'; // week
print '<td class="liste_titre center">&nbsp;</td>'; // status
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('right').'</td>';
print '</tr>'."\n";

/* Titres */
print '<tr class="liste_titre">';
print '<th class="center"><input type="checkbox" class="checkall" data-checkall="checkforselect"></th>';
print '<th>'.$langs->trans('Ref').'</th>';
print '<th>'.$langs->trans('Employee').'</th>';
print '<th class="center">'.$langs->trans('Year').'</th>';
print '<th class="center">'.$langs->trans('Week').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th class="center">&nbsp;</th>';
print '</tr>'."\n";

/* Lignes */
$i = 0;
$imax = ($limit ? min($num, $limit) : $num);
while ($i < $imax) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	print '<tr class="oddeven">';

	/* checkbox gauche */
	print '<td class="nowrap center">';
	if ($massactionbutton || $massaction) {
		$selected = in_array($obj->rowid, (array)$toselect) ? 1 : 0;
		print '<input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
	}
	print '</td>';

	/* Ref */
	$tswstatic->id = $obj->rowid;
	$tswstatic->ref = $obj->ref;
	$tswstatic->status = $obj->status;
	print '<td>'.$tswstatic->getNomUrl(1,'ref').'</td>';

	/* User */
	$usertmp->id = $obj->uid;
	$usertmp->firstname = $obj->firstname;
	$usertmp->lastname = $obj->lastname;
	$usertmp->login = $obj->login;
	$usertmp->statut = (int)$obj->user_status;
	$usertmp->photo = $obj->user_photo;
	print '<td>'.$usertmp->getNomUrl(-1).'</td>';

	print '<td class="center">'.(int)$obj->year.'</td>';
	print '<td class="center">'.(int)$obj->week.'</td>';

	$tswstatic->status = $obj->status;
	print '<td class="center">'.$tswstatic->getLibStatut(5).'</td>';

	print '<td class="center">&nbsp;</td>';

	print '</tr>';
	$i++;
}

if ($imax == 0) {
	print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
