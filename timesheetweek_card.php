<?php
/* Copyright (C) 2025
 * Pierre ARDOIN
 * GPL v3
 */

/**
 * \file       timesheetweek_card.php
 * \ingroup    timesheetweek
 * \brief      Create/Edit/View a weekly timesheet
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))	$res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php"))	$res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/class/timesheetweekline.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr()

$langs->loadLangs(array("timesheetweek@timesheetweek", "projects", "users", "other"));

/* ------------------------ Small helpers ------------------------ */

function tsw_check_token()
{
	$tok = GETPOST('token', 'alphanohtml');
	if (empty($tok) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tok)) {
		accessforbidden('CSRF token invalid');
	}
}

function tsw_hhmm_to_float($v)
{
	$v = trim((string) $v);
	if ($v === '' || $v === '0' || $v === '0:00' || $v === '00:00') return 0.0;
	if (strpos($v, ':') === false) return (float) str_replace(',', '.', $v);
	list($h, $m) = array_pad(explode(':', $v, 2), 2, '0');
	$h = (int) $h; $m = (int) $m;
	if ($m < 0) $m = 0; if ($m > 59) $m = 59;
	return (float) ($h + $m / 60.0);
}

function tsw_float_to_hhmm($dec)
{
	$dec = (float) $dec;
	if ($dec < 0) $dec = 0;
	$h = (int) floor($dec);
	$m = (int) round(($dec - $h) * 60);
	if ($m == 60) { $h++; $m = 0; }
	return str_pad((string)$h, 2, '0', STR_PAD_LEFT).':'.str_pad((string)$m, 2, '0', STR_PAD_LEFT);
}

/**
 * Fallback de génération de ref si la classe ne fournit pas getNextRef()
 * Format demandé: FHyyyyss-XXX (XXX incrémental annuel)
 */
function tsw_compute_next_ref(DoliDB $db, TimesheetWeek $obj)
{
	$y = (int) $obj->year;
	$ss = str_pad((string) $obj->week, 2, '0', STR_PAD_LEFT);

	$like = 'FH'.$y.$ss.'-%';
	$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."timesheet_week";
	$sql .= " WHERE ref LIKE '".$db->escape($like)."'";
	$sql .= " ORDER BY ref DESC";
	$sql .= " ".$db->plimit(1);
	$resql = $db->query($sql);
	$nextnum = 1;
	if ($resql && ($o = $db->fetch_object($resql))) {
		// Parse last XXX
		if (preg_match('/^FH'.$y.$ss.'-(\d{3})$/', $o->ref, $m)) {
			$nextnum = (int) $m[1] + 1;
		}
	}
	$db->free($resql);
	return 'FH'.$y.$ss.'-'.str_pad((string)$nextnum, 3, '0', STR_PAD_LEFT);
}

/* ------------------------ Params ------------------------ */

$id      = GETPOSTINT('id');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$field   = GETPOST('field', 'alpha');

/* ------------------------ Object & Hooks ------------------------ */

$object = new TimesheetWeek($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// Load object if id provided
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

/* ------------------------ Permissions ------------------------ */

$permRead          = $user->hasRight('timesheetweek','timesheetweek','read');
$permWrite         = $user->hasRight('timesheetweek','timesheetweek','write');
$permDelete        = $user->hasRight('timesheetweek','timesheetweek','delete');

$permValidate      = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll   = $user->hasRight('timesheetweek','timesheetweek','validateAll');
$permValidateOwn   = $user->hasRight('timesheetweek','timesheetweek','validateOwn');

if (!$permRead) accessforbidden();

/* ------------------------ Actions : Create ------------------------ */

if ($action === 'add' && $permWrite) {
	tsw_check_token();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wss
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$object->ref           = '(PROV)';
	$object->fk_user       = $fk_user > 0 ? $fk_user : $user->id;
	$object->status        = TimesheetWeek::STATUS_DRAFT;
	$object->note          = $note;
	$object->fk_user_valid = $fk_user_valid > 0 ? $fk_user_valid : ((isset($user->fk_user) && $user->fk_user) ? $user->fk_user : $user->id);
	$object->entity        = (int) $conf->entity;

	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int) $m[1];
		$object->week = (int) $m[2];
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		$action = 'create';
	}

	if ($action === 'add') {
		$res = $object->create($user);
		if ($res > 0) {
			// (PROV) => (PROV{id})
			if (!empty($object->ref) && strpos($object->ref, '(PROV') === 0 && strpos($object->ref, (string)$object->id) === false) {
				if (method_exists($object, 'updateRef')) {
					$object->updateRef('(PROV'.$object->id.')');
				} else {
					$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET ref='(PROV".$db->escape($object->id).")' WHERE rowid=".(int)$object->id);
				}
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

/* ------------------------ Actions : Inline edits (draft only) ------------------------ */

if ($id > 0 && $object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT && $permWrite) {
	// Toggle to edit mode for a field
	if ($action === 'edit_field') {
		// just switch rendering mode; nothing to do here
	}

	// Save each field
	if ($action === 'set_fk_user') {
		tsw_check_token();
		$object->fk_user = GETPOSTINT('fk_user');
		$object->update($user);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	if ($action === 'set_week') {
		tsw_check_token();
		$weekyear = GETPOST('weekyear', 'alpha');
		if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
			$object->year = (int) $m[1];
			$object->week = (int) $m[2];
			$object->update($user);
		} else {
			setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	if ($action === 'set_fk_user_valid') {
		tsw_check_token();
		$object->fk_user_valid = GETPOSTINT('fk_user_valid');
		$object->update($user);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	if ($action === 'set_note') {
		tsw_check_token();
		$object->note = GETPOST('note', 'restricthtml');
		$object->update($user);
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
}

/* ------------------------ Actions : Save grid ------------------------ */

if ($action === 'save' && $permWrite && $id > 0) {
	tsw_check_token();

	$db->begin();

	$daysMap = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);

	// read per-day zone & meal (shared for all task lines that day)
	$zones = array();
	$meals = array();
	foreach ($daysMap as $day => $ofs) {
		$zones[$day] = max(0, min(5, (int) GETPOST('zone_'.$day, 'int')));
		$meals[$day] = GETPOST('meal_'.$day) ? 1 : 0;
	}

	$total = 0.0;

	foreach ($_POST as $key => $val) {
		if (!preg_match('/^hours_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/', $key, $m)) continue;
		$taskid = (int) $m[1];
		$day    = $m[2];
		$hoursFloat = tsw_hhmm_to_float($val);

		$dto = new DateTime();
		$dto->setISODate((int)$object->year, (int)$object->week);
		$dto->modify('+'.$daysMap[$day].' day');
		$daydate = $dto->format('Y-m-d');

		if ($hoursFloat <= 0) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line";
			$sql .= " WHERE fk_timesheet_week=".(int)$object->id." AND fk_task=".(int)$taskid." AND day_date='".$db->escape($daydate)."'";
			if (!$db->query($sql)) { $db->rollback(); dol_print_error($db); exit; }
			continue;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line";
		$sql .= " WHERE fk_timesheet_week=".(int)$object->id." AND fk_task=".(int)$taskid." AND day_date='".$db->escape($daydate)."'";
		$resql = $db->query($sql);
		if (!$resql) { $db->rollback(); dol_print_error($db); exit; }
		$existid = ($obj = $db->fetch_object($resql)) ? (int)$obj->rowid : 0;

		$zone = isset($zones[$day]) ? (int)$zones[$day] : 0;
		$meal = isset($meals[$day]) ? (int)$meals[$day] : 0;

		if ($existid > 0) {
			$sqlu = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET";
			$sqlu .= " hours=".((float)$hoursFloat).", zone=".(int)$zone.", meal=".(int)$meal;
			$sqlu .= " WHERE rowid=".(int)$existid;
			if (!$db->query($sqlu)) { $db->rollback(); dol_print_error($db); exit; }
		} else {
			$sqli = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(";
			$sqli .= "fk_timesheet_week, fk_task, day_date, hours, zone, meal)";
			$sqli .= " VALUES(";
			$sqli .= (int)$object->id.",".(int)$taskid.",'".$db->escape($daydate)."',".((float)$hoursFloat).",".(int)$zone.",".(int)$meal.")";
			if (!$db->query($sqli)) { $db->rollback(); dol_print_error($db); exit; }
		}

		$total += $hoursFloat;
	}

	// Totaux entête (total & overtime)
	$employee = new User($db);
	$employee->fetch($object->fk_user);
	$contracted = (float) (empty($employee->weeklyhours) ? 35.0 : $employee->weeklyhours);
	$overtime = $total - $contracted;
	if ($overtime < 0) $overtime = 0;

	$object->total_hours = $total;
	$object->overtime_hours = $overtime;
	if (method_exists($object, 'update')) $object->update($user);
	else {
		$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET total_hours=".(float)$total.", overtime_hours=".(float)$overtime." WHERE rowid=".(int)$object->id);
	}

	$db->commit();

	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

/* ------------------------ Actions : Workflows ------------------------ */

// Submit (generate definitive ref + set submitted) – only if at least one line
if ($action === 'submit' && $id > 0 && $permWrite) {
	tsw_check_token();

	$hasLines = false;
	$resct = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id);
	$hasLines = ($resct && ($o = $db->fetch_object($resct)) && (int)$o->nb > 0);
	if (!$hasLines) {
		setEventMessages($langs->trans("AtLeastOneLineRequired"), null, 'warnings');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}

	// Generate definitive ref
	if (!empty($object->ref) && strpos($object->ref, '(PROV') === 0) {
		$ref = null;
		if (method_exists($object, 'getNextRef')) $ref = $object->getNextRef();
		if (!$ref) $ref = tsw_compute_next_ref($db, $object);
		if ($ref) {
			if (method_exists($object, 'updateRef')) $object->updateRef($ref);
			else $db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET ref='".$db->escape($ref)."' WHERE rowid=".(int)$object->id);
			$object->ref = $ref;
		}
	}

	$object->status = TimesheetWeek::STATUS_SUBMITTED;
	$object->date_validation = dol_now();
	if (method_exists($object, 'update')) $object->update($user);
	else {
		$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status=".(int)TimesheetWeek::STATUS_SUBMITTED.", date_validation='".$db->idate(dol_now())."' WHERE rowid=".(int)$object->id);
	}

	setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Back to draft (even after approval/refusal)
if ($action === 'backtodraft' && $id > 0 && $permWrite) {
	tsw_check_token();

	$object->status = TimesheetWeek::STATUS_DRAFT;
	if (method_exists($object, 'update')) $object->update($user);
	else $db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status=".(int)TimesheetWeek::STATUS_DRAFT." WHERE rowid=".(int)$object->id);

	setEventMessages($langs->trans("StatusSetToDraft"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Approve / confirm approve
if ($action === 'confirm_approve' && $confirm === 'yes' && $id > 0) {
	tsw_check_token();

	$canapprove = false;
	if ($permValidateAll) $canapprove = true;
	else if ($permValidateChild) {
		$subs = $user->getAllChildIds(1);
		if (in_array($object->fk_user, (array)$subs)) $canapprove = true;
	}
	else if ($permValidateOwn && $object->fk_user == $user->id) $canapprove = true;
	else if ($permValidate && $object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) $canapprove = true;

	if (!$canapprove) accessforbidden();

	if ($object->fk_user_valid != $user->id) {
		$object->fk_user_valid = $user->id;
	}

	$object->status = TimesheetWeek::STATUS_APPROVED;
	if (method_exists($object, 'update')) $object->update($user);
	else $db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status=".(int)TimesheetWeek::STATUS_APPROVED.", fk_user_valid=".(int)$user->id." WHERE rowid=".(int)$object->id);

	setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Refuse / confirm refuse
if ($action === 'confirm_refuse' && $confirm === 'yes' && $id > 0) {
	tsw_check_token();

	$canrefuse = false;
	if ($permValidateAll) $canrefuse = true;
	else if ($permValidateChild) {
		$subs = $user->getAllChildIds(1);
		if (in_array($object->fk_user, (array)$subs)) $canrefuse = true;
	}
	else if ($permValidateOwn && $object->fk_user == $user->id) $canrefuse = true;
	else if ($permValidate && $object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) $canrefuse = true;

	if (!$canrefuse) accessforbidden();

	if ($object->fk_user_valid != $user->id) {
		$object->fk_user_valid = $user->id;
	}

	$object->status = TimesheetWeek::STATUS_REFUSED;
	if (method_exists($object, 'update')) $object->update($user);
	else $db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status=".(int)TimesheetWeek::STATUS_REFUSED.", fk_user_valid=".(int)$user->id." WHERE rowid=".(int)$object->id);

	setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Delete (with confirm)
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0 && $permDelete) {
	tsw_check_token();

	if (method_exists($object, 'fetch')) $object->fetch($id);
	$res = method_exists($object, 'delete') ? $object->delete($user) : $db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week WHERE rowid=".(int)$id);
	if ($res > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/timesheetweek/timesheetweek_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/* ------------------------ View ------------------------ */

$form = new Form($db);
$title = $langs->trans("TimesheetWeek");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforcard');

if ($action === 'create') {
	if (!$permWrite) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal');

	$defaultValidator = (isset($user->fk_user) && $user->fk_user > 0) ? (int)$user->fk_user : (int)$user->id;

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$form->select_dolusers($user->id,'fk_user',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.getWeekSelectorDolibarr($form,'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td></tr>';
	print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$form->select_dolusers($defaultValidator,'fk_user_valid',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" rows="3" class="quatrevingtpercent"></textarea></td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<button type="submit" class="button">'.$langs->trans("Create").'</button>';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

} elseif ($id > 0) {
	// Header
	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

	// Banner + status badge
	dol_banner_tab($object, 'ref', '', 0, '', '', '', '', 0, '', '', '');
	print '<div class="opacitymedium mtoponly">'.$object->getLibStatut(5).'</div>';

	print '<div class="fichecenter">';

	// Left (with inline edit in draft)
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	// Employee
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $action === 'edit_field' && $field === 'fk_user') {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_fk_user">';
		print $form->select_dolusers($object->fk_user, 'fk_user', 1);
		print ' <button type="submit" class="button small">'.$langs->trans("Save").'</button>';
		print ' <a class="button small button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		$u = new User($db); $u->fetch($object->fk_user);
		print $u->getNomUrl(1);
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_field&field=fk_user">'.img_picto($langs->trans("Modify"), 'edit').'</a>';
		}
	}
	print '</td></tr>';

	// Week
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $action === 'edit_field' && $field === 'week') {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_week">';
		print getWeekSelectorDolibarr($form, 'weekyear', $object->year, $object->week);
		print ' <button type="submit" class="button small">'.$langs->trans("Save").'</button>';
		print ' <a class="button small button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
		print '<div id="weekrange" class="opacitymedium paddingleft small"></div>';
	} else {
		print $object->week.' / '.$object->year;
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_field&field=week">'.img_picto($langs->trans("Modify"), 'edit').'</a>';
		}
	}
	print '</td></tr>';

	// Validator
	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $action === 'edit_field' && $field === 'fk_user_valid') {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_fk_user_valid">';
		print $form->select_dolusers($object->fk_user_valid ?: $user->id, 'fk_user_valid', 1);
		print ' <button type="submit" class="button small">'.$langs->trans("Save").'</button>';
		print ' <a class="button small button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		if ($object->fk_user_valid > 0) {
			$v = new User($db); $v->fetch($object->fk_user_valid);
			print $v->getNomUrl(1);
		}
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_field&field=fk_user_valid">'.img_picto($langs->trans("Modify"), 'edit').'</a>';
		}
	}
	print '</td></tr>';

	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $action === 'edit_field' && $field === 'note') {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_note">';
		print '<textarea name="note" rows="3" class="quatrevingtpercent">'.dol_escape_htmltag($object->note).'</textarea>';
		print '<br><button type="submit" class="button small">'.$langs->trans("Save").'</button>';
		print ' <a class="button small button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		print nl2br(dol_escape_htmltag($object->note));
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit_field&field=note">'.img_picto($langs->trans("Modify"), 'edit').'</a>';
		}
	}
	print '</td></tr>';

	print '</table>';
	print '</div>';

	// Right
	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("DateCreation").'</td><td>'.($object->date_creation ? dol_print_date($object->date_creation, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.($object->tms ? dol_print_date($object->tms, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.($object->date_validation ? dol_print_date($object->date_validation, 'dayhour') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.tsw_float_to_hhmm((float)$object->total_hours).'</td></tr>';
	print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.tsw_float_to_hhmm((float)$object->overtime_hours).'</td></tr>';
	print '</table>';
	print '</div>';

	print '</div>'; // fichecenter

	print dol_get_fiche_end();

	// --- Clear to push grid below header ---
	print '<div class="clearboth"></div>';

	/* --------- Build week dates --------- */
	$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
	$dto = new DateTime();
	$dto->setISODate((int)$object->year, (int)$object->week);
	$weekdates = array();
	foreach ($days as $d) {
		$weekdates[$d] = $dto->format('Y-m-d');
		$dto->modify('+1 day');
	}

	/* --------- Contracted hours --------- */
	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contractedHours = (float) (!empty($userEmployee->weeklyhours) ? $userEmployee->weeklyhours : 35.0);

	/* --------- Load lines and map by (task,date) --------- */
	$lines = array();
	if (method_exists($object, 'getLines')) $lines = $object->getLines();
	if (!is_array($lines)) $lines = array();

	if (empty($lines)) {
		$sqlL = "SELECT rowid, fk_task, day_date, hours, zone, meal FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
		$resL = $db->query($sqlL);
		if ($resL) {
			while ($r = $db->fetch_object($resL)) {
				$lineo = new stdClass();
				$lineo->rowid = (int)$r->rowid;
				$lineo->fk_task = (int)$r->fk_task;
				$lineo->day_date = $r->day_date;
				$lineo->hours = (float)$r->hours;
				$lineo->zone = (int)$r->zone;
				$lineo->meal = (int)$r->meal;
				$lines[] = $lineo;
			}
		}
	}

	$hoursByTaskDate = array();
	$zoneByDate = array();
	$mealByDate = array();
	foreach ($lines as $l) {
		$datekey = is_numeric($l->day_date) ? dol_print_date($l->day_date, '%Y-%m-%d') : $l->day_date;
		$tid = (int) $l->fk_task;
		$hoursByTaskDate[$tid][$datekey] = tsw_float_to_hhmm((float)$l->hours);
		if (!isset($zoneByDate[$datekey])) $zoneByDate[$datekey] = (int)$l->zone;
		if (!isset($mealByDate[$datekey])) $mealByDate[$datekey] = (int)$l->meal;
	}

	/* --------- Tasks: assigned + from lines --------- */
	$tasks = array();
	if (method_exists($object, 'getAssignedTasks')) {
		$tmp = $object->getAssignedTasks($object->fk_user);
		if (is_array($tmp)) $tasks = $tmp;
	}
	$taskmap = array();
	foreach ($tasks as $t) $taskmap[(int)$t['task_id']] = $t;

	if (!empty($lines)) {
		$sqlt = "SELECT t.rowid as task_id, t.ref as task_ref, t.label as task_label, p.rowid as project_id, p.ref as project_ref, p.title as project_title
				FROM ".MAIN_DB_PREFIX."projet_task t
				JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet
				JOIN ".MAIN_DB_PREFIX."timesheet_week_line l ON l.fk_task = t.rowid
				WHERE l.fk_timesheet_week=".(int)$object->id."
				AND p.entity IN (".getEntity('project', 1).")";
		$resqt = $db->query($sqlt);
		if ($resqt) {
			while ($o = $db->fetch_object($resqt)) {
				$tid = (int)$o->task_id;
				if (empty($taskmap[$tid])) {
					$taskmap[$tid] = array(
						'task_id' => $tid,
						'task_ref' => (string) $o->task_ref,
						'task_label' => (string) $o->task_label,
						'project_id' => (int) $o->project_id,
						'project_ref' => (string) $o->project_ref,
						'project_title' => (string) $o->project_title
					);
				}
			}
		}
	}
	$tasks = array_values($taskmap);

	/* --------- Grid form --------- */
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<h3>'.$langs->trans("TimesheetWeekEntries").'</h3>';

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';

	// Header
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Project / Task").'</th>';
	foreach ($days as $d) {
		print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
	}
	print '<th>'.$langs->trans("Total").'</th>';
	print '</tr>';

	// Zones + Meals (locked if submitted)
	$lockDayParams = ($object->status == TimesheetWeek::STATUS_SUBMITTED ? ' disabled' : '');
	print '<tr class="liste_titre">';
	print '<td></td>';
	foreach ($days as $d) {
		$date = $weekdates[$d];
		$selzone = isset($zoneByDate[$date]) ? (int)$zoneByDate[$date] : 0;
		$selmeal = !empty($mealByDate[$date]) ? 1 : 0;
		print '<td class="center">';
		print '<label class="opacitymedium">'.$langs->trans("Zone").'</label><br>';
		print '<select name="zone_'.$d.'" class="flat"'.$lockDayParams.'>';
		for ($z=0; $z<=5; $z++) print '<option value="'.$z.'"'.($z==$selzone?' selected':'').'>'.$z.'</option>';
		print '</select><br>';
		print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($selmeal?' checked':'').$lockDayParams.'> '.$langs->trans("Meal").'</label>';
		print '</td>';
	}
	print '<td></td>';
	print '</tr>';

	// Group by project
	if (empty($tasks)) {
		print '<tr><td colspan="'.(count($days)+2).'"><span class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</span></td></tr>';
	} else {
		$byproject = array();
		foreach ($tasks as $t) {
			$pid = (int) $t['project_id'];
			if (empty($byproject[$pid])) {
				$byproject[$pid] = array(
					'ref'   => $t['project_ref'],
					'title' => $t['project_title'],
					'project_id' => $pid,
					'tasks' => array()
				);
			}
			$byproject[$pid]['tasks'][] = $t;
		}

		foreach ($byproject as $pid => $pdata) {
			$proj = new Project($db);
			$proj->id = $pid;
			$proj->ref = $pdata['ref'];
			$proj->title = $pdata['title'];

			print '<tr class="oddeven trforbreak nobold">';
			print '<td colspan="'.(count($days)+2).'">'.$proj->getNomUrl(1).'</td>';
			print '</tr>';

			foreach ($pdata['tasks'] as $t) {
				$task = new Task($db);
				$task->id = (int)$t['task_id'];
				$task->ref = isset($t['task_ref']) ? $t['task_ref'] : '';
				$task->label = isset($t['task_label']) ? $t['task_label'] : '';
				$task->fk_project = $pid;

				print '<tr>';
				print '<td class="paddingleft">'.$task->getNomUrl(1, 'withproject').'</td>';

				$rowTotal = 0.0;
				foreach ($days as $d) {
					$date = $weekdates[$d];
					$val = isset($hoursByTaskDate[$task->id][$date]) ? $hoursByTaskDate[$task->id][$date] : '00:00';
					$name = 'hours_'.$task->id.'_'.$d;
					print '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$name.'" value="'.dol_escape_htmltag($val).'" placeholder="00:00"></td>';
					$rowTotal += tsw_hhmm_to_float($val);
				}
				print '<td class="right task-total">'.tsw_float_to_hhmm($rowTotal).'</td>';
				print '</tr>';
			}
		}
	}

	// Totals
	print '<tr class="liste_total">';
	print '<td class="right">'.$langs->trans("Total").'</td>';
	foreach ($days as $d) print '<td class="right day-total">00:00</td>';
	print '<td class="right grand-total">00:00</td>';
	print '</tr>';

	print '<tr class="liste_total">';
	print '<td class="right">'.$langs->trans("Meals").'</td>';
	print '<td colspan="'.count($days).'" class="right meal-total">0</td>';
	print '<td></td>';
	print '</tr>';

	print '<tr class="liste_total">';
	print '<td class="right">'.$langs->trans("Overtime").' (>'.tsw_float_to_hhmm($contractedHours).')</td>';
	print '<td colspan="'.count($days).'" class="right overtime-total">00:00</td>';
	print '<td></td>';
	print '</tr>';

	print '</table>';
	print '</div>';

	// Save button under grid
	print '<div class="center mtop1">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<button type="submit" class="button">'.$langs->trans("Save").'</button>';
	print '</div>';
	print '</form>';

	// Tabs actions
	print '<div class="tabsAction">';

	// Has lines ?
	$hasLines = false;
	$resct = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id);
	$hasLines = ($resct && ($o = $db->fetch_object($resct)) && (int)$o->nb > 0);

	// Submit only if draft + has lines
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $hasLines) {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="submit">';
		print '<button type="submit" class="butAction">'.$langs->trans("Submit").'</button>';
		print '</form>';
	}

	// Back to draft – remains available even if approved/refused
	if ($object->status != TimesheetWeek::STATUS_DRAFT && $permWrite) {
		print '<form class="inline-block" method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="backtodraft">';
		print '<button type="submit" class="butAction">'.$langs->trans("SetToDraft").'</button>';
		print '</form>';
	}

	// Approve / Refuse (only when submitted)
	$canModerate = false;
	if ($permValidateAll) $canModerate = true;
	else if ($permValidateChild) {
		$subs = $user->getAllChildIds(1);
		if (in_array($object->fk_user, (array)$subs)) $canModerate = true;
	}
	else if ($permValidateOwn && $object->fk_user == $user->id) $canModerate = true;
	else if ($permValidate && $object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) $canModerate = true;

	if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $canModerate) {
		// Approve => open confirm
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=approve">'.$langs->trans("Approve").'</a>';
		// Refuse => open confirm
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refuse">'.$langs->trans("Refuse").'</a>';
	}

	// Delete – remains available even if approved/refused
	if ($permDelete) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete">'.$langs->trans("Delete").'</a>';
	}
	print '</div>';

	// Confirm popups
	if ($action === 'delete') {
		print $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("Delete"),
			$langs->trans("ConfirmDeleteObject"),
			'confirm_delete',
			array(),
			0,
			'',
			newToken()
		);
	}
	if ($action === 'approve') {
		print $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("Approve"),
			$langs->trans("ConfirmApprove"),
			'confirm_approve',
			array(),
			0,
			'',
			newToken()
		);
	}
	if ($action === 'refuse') {
		print $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("Refuse"),
			$langs->trans("ConfirmRefuse"),
			'confirm_refuse',
			array(),
			0,
			'',
			newToken()
		);
	}

	// JS totals updater
	print "<script>
	(function($){
		function parseHours(v){if(!v)return 0;if(v.indexOf(':')===-1)return parseFloat(v)||0;var p=v.split(':'),h=parseInt(p[0],10)||0,m=parseInt(p[1],10)||0;return h+(m/60);}
		function formatHours(d){if(isNaN(d))return '00:00';var h=Math.floor(d),m=Math.round((d-h)*60);if(m===60){h++;m=0;}return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');}
		function updateTotals(){
			var grand=0;
			$('.task-total').text('00:00'); $('.day-total').text('00:00');
			$('table tr').each(function(){
				var rowT=0;
				$(this).find('input.hourinput').each(function(){
					var v=parseHours($(this).val());
					if(v>0){
						rowT+=v;
						var idx=$(this).closest('td').index();
						var cell=$('tr.liste_total:first td').eq(idx);
						var cur=parseHours(cell.text());
						cell.text(formatHours(cur+v));
						grand+=v;
					}
				});
				if(rowT>0) $(this).find('.task-total').text(formatHours(rowT));
			});
			$('.grand-total').text(formatHours(grand));
			$('.meal-total').text($('.mealbox:checked').length);
			var contracted=".((float)$contractedHours).";
			var ot=grand-contracted; if(ot<0) ot=0;
			$('.overtime-total').text(formatHours(ot));
		}
		$(document).on('input change','input.hourinput, input.mealbox',updateTotals);
		$(function(){ updateTotals(); });
	})(jQuery);
	</script>";
}

// JS week helper on create/edit week inline
print <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val){var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){if($('#weekyear').length){if($.fn.select2)$('#weekyear').select2({width:'resolve'});updateWeekRange();$('#weekyear').on('change',updateWeekRange);}});
})(jQuery);
</script>
JS;

llxFooter();
$db->close();
