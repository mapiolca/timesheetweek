<?php
/* Copyright (C) 2025  Pierre ARDOIN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License...
 */

/**
 * \file       timesheetweek_card.php
 * \ingroup    timesheetweek
 * \brief      Page to create/edit/view a weekly timesheet
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // helpers: getWeekSelectorDolibarr(), formatHours()

$langs->loadLangs(array('timesheetweek@timesheetweek', 'other', 'projects'));

// --- Parameters ---
$id         = GETPOSTINT('id');
$action     = GETPOST('action', 'aZ09');
$confirm    = GETPOST('confirm', 'alpha');

// --- Init ---
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// --- Load object ---
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

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

// --- Global access gate: allow page if user has ANY read OR ANY create right ---
$hasAnyAccess = ($permRead || $permReadAll || $permReadChild || $permCreate || $permCreateChild || $permCreateAll);
if (!$hasAnyAccess) accessforbidden();

$form = new Form($db);
$useajax = !empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile);

// ----------- Helpers -----------
function tw_get_default_validator($db, $fk_user)
{
	$u = new User($db);
	if ($fk_user > 0 && $u->fetch($fk_user) > 0) {
		if (!empty($u->fk_user)) return (int) $u->fk_user;
		if (!empty($u->fk_user_superior)) return (int) $u->fk_user_superior;
	}
	return null;
}
function tw_child_ids(User $u) {
	static $cache = null;
	if ($cache !== null) return $cache;
	$cache = $u->getAllChildIds(1);
	return $cache;
}
function tw_can_see($object, $current, $permRead, $permReadAll, $permReadChild)
{
	if ($permReadAll) return true;
	if ($object->fk_user == $current->id) return true;
	if ($permReadChild) {
		$subs = tw_child_ids($current);
		if (is_array($subs) && in_array($object->fk_user, $subs)) return true;
	}
	return false;
}
function tw_has_create_for($targetUserId, $current, $permCreate, $permCreateChild, $permCreateAll)
{
	if ($targetUserId == $current->id && $permCreate) return true;
	if ($permCreateAll) return true;
	if ($permCreateChild && in_array($targetUserId, (array) tw_child_ids($current))) return true;
	return false;
}
function tw_has_validate_for($targetUserId, $current, $permValidate, $permValidateChild, $permValidateAll)
{
	if ($permValidateAll) return true;
	if ($permValidate && $targetUserId == $current->id) return true;
	if ($permValidateChild && in_array($targetUserId, (array) tw_child_ids($current))) return true;
	return false;
}
function tw_has_delete_for($targetUserId, $current, $permDelete, $permDeleteChild, $permDeleteAll)
{
	if ($permDeleteAll) return true;
	if ($permDelete && $targetUserId == $current->id) return true;
	if ($permDeleteChild && in_array($targetUserId, (array) tw_child_ids($current))) return true;
	return false;
}
function tw_formatHoursSafe($hoursFloat)
{
	if (function_exists('formatHours')) return formatHours($hoursFloat);
	if ($hoursFloat === null || $hoursFloat === '') return '00:00';
	$d = (float) $hoursFloat;
	$h = floor($d);
	$m = round(($d - $h) * 60);
	if ($m == 60) { $h++; $m = 0; }
	return str_pad((string) $h, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}
function tw_parse_hours_to_float($val)
{
	$val = trim((string) $val);
	if ($val === '') return 0.0;
	$val = str_replace(',', '.', $val);
	if (strpos($val, ':') === false) {
		return (float) $val;
	}
	$parts = explode(':', $val, 2);
	$h = (int) $parts[0];
	$m = (int) $parts[1];
	if ($m < 0) $m = 0;
	if ($m > 59) $m = 59;
	return (float) ($h + ($m / 60));
}
function tw_weekyear($y, $w) {
	$y = (int) $y; $w = (int) $w;
	return sprintf('%04d-W%02d', $y, $w);
}
function tw_fetch_lines_arrays(DoliDB $db, $fk_timesheet_week)
{
	$valByTaskDay = array(); // key = taskId|YYYY-mm-dd => "HH:MM"
	$zoneByDate   = array(); // date => int zone
	$mealByDate   = array(); // date => 0/1

	$sql = "SELECT rowid, fk_task, day_date, hours, zone, meal
		FROM ".MAIN_DB_PREFIX."timesheet_week_line
		WHERE fk_timesheet_week = ".((int) $fk_timesheet_week);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$dateKey = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $obj->day_date) ? $obj->day_date : date('Y-m-d', $db->jdate($obj->day_date)));
			$key = ((int) $obj->fk_task).'|'.$dateKey;
			$valByTaskDay[$key] = tw_formatHoursSafe((float) $obj->hours);

			if (!isset($zoneByDate[$dateKey]) && $obj->zone !== null) $zoneByDate[$dateKey] = (int) $obj->zone;
			if (!isset($mealByDate[$dateKey])) $mealByDate[$dateKey] = (int) $obj->meal;
		}
		$db->free($resql);
	}
	return array($valByTaskDay, $zoneByDate, $mealByDate);
}

// ----------- Actions -----------

// CREATE
if ($action === 'add') {
	$weekyear       = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user_post   = GETPOSTINT('fk_user');
	$fk_user_valid  = GETPOSTINT('fk_user_valid');
	$note           = GETPOST('note', 'restricthtml');

	$target_userid = $fk_user_post > 0 ? $fk_user_post : $user->id;
	if (!tw_has_create_for($target_userid, $user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();

	$object->ref     = '(PROV)';
	$object->fk_user = $target_userid;
	$object->status  = TimesheetWeek::STATUS_DRAFT;
	$object->note    = $note;

	if ($fk_user_valid > 0) $object->fk_user_valid = $fk_user_valid;
	else $object->fk_user_valid = tw_get_default_validator($db, $object->fk_user) ?: null;

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
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// SAVE LINES (upsert)
if ($action === 'save' && $object->id > 0) {
	if (!tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();

	if ($object->status != TimesheetWeek::STATUS_DRAFT) {
		setEventMessages($langs->trans("ErrorRecordNotDraft"), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	$db->begin();

	$days = array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
	$dto = new DateTime();
	$dto->setISODate($object->year, $object->week);
	$dayDates = array();
	foreach ($days as $d) {
		$dayDates[$d] = $dto->format('Y-m-d');
		$dto->modify('+1 day');
	}
	$zoneByDate = array();
	$mealByDate = array();
	foreach ($days as $d) {
		$zoneByDate[$dayDates[$d]] = GETPOSTINT('zone_'.$d) ?: null;
		$mealByDate[$dayDates[$d]] = GETPOST('meal_'.$d) ? 1 : 0;
	}

	$grandTotal = 0.0;

	foreach ($_POST as $key => $val) {
		if (!preg_match('/^hours_(\d+)_(\w+)$/', $key, $m)) continue;
		$taskid = (int) $m[1];
		$dayKey = $m[2];
		if (empty($dayDates[$dayKey])) continue;
		$thedate = $dayDates[$dayKey];

		$hours = tw_parse_hours_to_float($val);
		if ($hours < 0) $hours = 0;
		$grandTotal += $hours;

		$zone = isset($zoneByDate[$thedate]) ? (int) $zoneByDate[$thedate] : null;
		$meal = isset($mealByDate[$thedate]) ? (int) $mealByDate[$thedate] : 0;

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line
			WHERE fk_timesheet_week = ".((int) $object->id)."
			AND fk_task = ".((int) $taskid)."
			AND day_date = '".$db->escape($thedate)."'";
		$resql = $db->query($sql);
		if (!$resql) { $db->rollback(); setEventMessages($db->lasterror(), null, 'errors'); header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit; }
		$exists = ($db->num_rows($resql) > 0);
		$db->free($resql);

		if ($exists) {
			$sqlu = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line
				SET hours = ".((float) $hours).",
					zone = ".($zone !== null ? (int) $zone : "NULL").",
					meal = ".((int) $meal)."
				WHERE fk_timesheet_week = ".((int) $object->id)."
				AND fk_task = ".((int) $taskid)."
				AND day_date = '".$db->escape($thedate)."'";
			if (!$db->query($sqlu)) { $db->rollback(); setEventMessages($db->lasterror(), null, 'errors'); header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit; }
		} else {
			if ($hours > 0) {
				$sqli = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(fk_timesheet_week, fk_task, day_date, hours, zone, meal)
					VALUES (".((int) $object->id).", ".((int) $taskid).", '".$db->escape($thedate)."', ".((float) $hours).", ".($zone !== null ? (int) $zone : "NULL").", ".((int) $meal).")";
				if (!$db->query($sqli)) { $db->rollback(); setEventMessages($db->lasterror(), null, 'errors'); header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit; }
			}
		}
	}

	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contracted = !empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0;
	$overtime = $grandTotal - $contracted;
	if ($overtime < 0) $overtime = 0;

	$sqlp = "UPDATE ".MAIN_DB_PREFIX."timesheet_week
		SET total_hours = ".((float) $grandTotal).",
			overtime_hours = ".((float) $overtime)."
		WHERE rowid = ".((int) $object->id);
	if (!$db->query($sqlp)) { $db->rollback(); setEventMessages($db->lasterror(), null, 'errors'); header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit; }

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ---------- INLINE EDIT (pencils) ----------
$canEditHeader = false;
if ($object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT) {
	$canEditHeader = tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll);
}

// Update fk_user
if ($action === 'update_fk_user' && $canEditHeader) {
	$val = GETPOSTINT('new_fk_user');
	if ($val > 0) {
		if (!tw_has_create_for($val, $user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();
		$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET fk_user = ".((int) $val)." WHERE rowid = ".((int) $object->id);
		if ($db->query($sql)) setEventMessages($langs->trans("Modified"), null, 'mesgs');
		else setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}
// Update weekyear
if ($action === 'update_weekyear' && $canEditHeader) {
	$weekyear = GETPOST('new_weekyear', 'alpha');
	if (preg_match('/^(\d{4})-W(\d{1,2})$/', $weekyear, $m)) {
		$y = (int) $m[1];
		$w = (int) $m[2];
		$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET year = ".$y.", week = ".$w." WHERE rowid = ".((int) $object->id);
		if ($db->query($sql)) setEventMessages($langs->trans("Modified"), null, 'mesgs');
		else setEventMessages($db->lasterror(), null, 'errors');
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}
// Update validator
if ($action === 'update_fk_user_valid' && $canEditHeader) {
	$val = GETPOSTINT('new_fk_user_valid');
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET fk_user_valid = ".($val>0?(int)$val:"NULL")." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("Modified"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}
// Update note
if ($action === 'update_note' && $canEditHeader) {
	$val = GETPOST('new_note', 'restricthtml');
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET note = '".$db->escape($val)."' WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("Modified"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// SUBMIT / BACK TO DRAFT / APPROVE / REFUSE / DELETE (confirm)
if ($action === 'confirm_submit' && $confirm === 'yes' && $object->id > 0) {
	if (!tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_SUBMITTED)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit;
}
if ($action === 'confirm_setdraft' && $confirm === 'yes' && $object->id > 0) {
	$allowed = false;
	if (tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) $allowed = true;
	if (tw_has_validate_for($object->fk_user, $user, $permValidate, $permValidateChild, $permValidateAll)) $allowed = true;
	if (!$allowed) accessforbidden();

	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_DRAFT)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit;
}
if ($action === 'confirm_approve' && $confirm === 'yes' && $object->id > 0) {
	$isValidator = tw_has_validate_for($object->fk_user, $user, $permValidate, $permValidateChild, $permValidateAll);
	if (!$isValidator) accessforbidden();
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_APPROVED).", date_validation = '".$db->idate(dol_now())."' WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit;
}
if ($action === 'confirm_refuse' && $confirm === 'yes' && $object->id > 0) {
	$isValidator = tw_has_validate_for($object->fk_user, $user, $permValidate, $permValidateChild, $permValidateAll);
	if (!$isValidator) accessforbidden();
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_REFUSED)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	else setEventMessages($db->lasterror(), null, 'errors');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit;
}
if ($action === 'confirm_delete' && $confirm === 'yes' && $object->id > 0) {
	if (!tw_has_delete_for($object->fk_user, $user, $permDelete, $permDeleteChild, $permDeleteAll)) accessforbidden();
	$db->begin();
	$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week = ".((int) $object->id));
	$res = $object->delete($user);
	if ($res > 0) { $db->commit(); setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs'); header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php', 1)); exit; }
	else { $db->rollback(); setEventMessages($object->error, $object->errors, 'errors'); header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id); exit; }
}

// ----------- View -----------
$title = ($action == 'create') ? $langs->trans("NewTimesheetWeek") : $langs->trans("TimesheetWeek");
llxHeader('', $title, '');

// MODE CREATE
if ($action === 'create') {
	if (!($permCreate || $permCreateChild || $permCreateAll)) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'time');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	$defaultUser = $user->id;
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$form->select_dolusers($defaultUser, 'fk_user', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	print getWeekSelectorDolibarr($form, 'weekyear');
	print ' <div id="weekrange" class="opacitymedium paddingleft small"></div>';
	print '</td></tr>';
	$defaultValidator = tw_get_default_validator($db, $defaultUser);
	print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$form->select_dolusers($defaultValidator ?: $user->id, 'fk_user_valid', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" class="quatrevingtpercent" rows="3"></textarea></td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'"> ';
	print '<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	print <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val){var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){ if ($.fn.select2) $('#weekyear').select2({width:'resolve'}); updateWeekRange(); $('#weekyear').on('change', updateWeekRange); });
})(jQuery);
</script>
JS;

} elseif ($object->id > 0) {
	if (!tw_can_see($object, $user, $permRead, $permReadAll, $permReadChild)) accessforbidden();

	$isOwner     = ($object->fk_user == $user->id);
	$isValidator = tw_has_validate_for($object->fk_user, $user, $permValidate, $permValidateChild, $permValidateAll);

	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'time');

	$formconfirm = '';
	if ($useajax) {
		if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Submit'), $langs->trans('ConfirmSubmit', $object->ref), 'confirm_submit', array(), 1, 'action-submit');
		}
		if (in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED)) && (tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll) || $isValidator)) {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetToDraft'), $langs->trans('ConfirmSetToDraft', $object->ref), 'confirm_setdraft', array(), 1, 'action-setdraft');
		}
		if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $isValidator) {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidate', $object->ref), 'confirm_approve', array(), 1, 'action-approve');
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Refuse'), $langs->trans('ConfirmRefuse', $object->ref), 'confirm_refuse', array(), 1, 'action-refuse');
		}
		if (tw_has_delete_for($object->fk_user, $user, $permDelete, $permDeleteChild, $permDeleteAll)) {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteTimesheetWeek'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', array(), 1, 'action-delete');
		}
	}
	print $formconfirm;

	dol_banner_tab($object, 'ref');

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	$canEditHeader = ($object->status == TimesheetWeek::STATUS_DRAFT) && tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll);
	if ($action === 'edit_fk_user' && $canEditHeader) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_fk_user">';
		print $form->select_dolusers($object->fk_user, 'new_fk_user', 1);
		print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		print ' <a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		if ($object->fk_user > 0) { $u = new User($db); $u->fetch($object->fk_user); print $u->getNomUrl(1); }
		if ($canEditHeader) print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_fk_user">'.img_edit().'</a>';
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($action === 'edit_weekyear' && $canEditHeader) {
		$currentWeek = tw_weekyear($object->year, $object->week);
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_weekyear">';
		print getWeekSelectorDolibarr($form, 'weekyear');
		print '<input type="hidden" name="new_weekyear" id="new_weekyear" value="'.$currentWeek.'">';
		print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		print ' <a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
		print '<script>jQuery(function($){ $("#weekyear").val("'.dol_escape_js($currentWeek).'").trigger("change"); $("#weekyear").on("change", function(){ $("#new_weekyear").val($(this).val()); }); });</script>';
	} else {
		print dol_escape_htmltag($object->week).' / '.dol_escape_htmltag($object->year);
		if ($canEditHeader) print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_weekyear">'.img_edit().'</a>';
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($action === 'edit_fk_user_valid' && $canEditHeader) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_fk_user_valid">';
		print $form->select_dolusers($object->fk_user_valid, 'new_fk_user_valid', 1);
		print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		print ' <a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		if ($object->fk_user_valid > 0) { $v = new User($db); $v->fetch($object->fk_user_valid); print $v->getNomUrl(1); }
		else print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		if ($canEditHeader) print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_fk_user_valid">'.img_edit().'</a>';
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($action === 'edit_note' && $canEditHeader) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update_note">';
		print '<textarea name="new_note" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag($object->note).'</textarea>';
		print '<br><input type="submit" class="button small" value="'.$langs->trans("Save").'"> ';
		print '<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		print '</form>';
	} else {
		print nl2br(dol_escape_htmltag($object->note));
		if ($canEditHeader) print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_note">'.img_edit().'</a>';
	}
	print '</td></tr>';

	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	if (property_exists($object, 'total_hours')) {
		print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.tw_formatHoursSafe((float) $object->total_hours).'</td></tr>';
	}
	if (property_exists($object, 'overtime_hours')) {
		print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.tw_formatHoursSafe((float) $object->overtime_hours).'</td></tr>';
	}
	print '</table>';
	print '</div>';
	print '</div>';

	print dol_get_fiche_end();

	// ======== TABLEAU DES TEMPS ========
	print '<div class="fichecenter">';

	$tasks = $object->getAssignedTasks($object->fk_user);
	list($valByTaskDay, $zoneByDate, $mealByDate) = tw_fetch_lines_arrays($db, $object->id);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<h3 class="paddingtop">'.$langs->trans("TimesheetWeekEntries").'</h3>';

	if (empty($tasks)) {
		print '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
	} else {
		$days = array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
		$dto = new DateTime();
		$dto->setISODate($object->year, $object->week);
		$weekdates = array();
		foreach ($days as $d) { $weekdates[$d] = $dto->format('Y-m-d'); $dto->modify('+1 day'); }

		$userEmployee = new User($db);
		$userEmployee->fetch($object->fk_user);
		$contractedHours = !empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0;

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Project / Task").'</th>';
		foreach ($days as $d) {
			print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
		}
		print '<th>'.$langs->trans("Total").'</th>';
		print '</tr>';

		print '<tr class="liste_titre">';
		print '<td></td>';
		foreach ($days as $d) {
			$thedate = $weekdates[$d];
			$curZone = isset($zoneByDate[$thedate]) ? (int) $zoneByDate[$thedate] : '';
			$curMeal = !empty($mealByDate[$thedate]) ? 1 : 0;

			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat" '.($object->status==TimesheetWeek::STATUS_DRAFT?'':'disabled').'>';
			print '<option value=""></option>';
			for ($z=1; $z<=5; $z++) print '<option value="'.$z.'"'.($curZone==$z?' selected':'').'>'.$z.'</option>';
			print '</select><br>';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($curMeal?' checked':'').' '.($object->status==TimesheetWeek::STATUS_DRAFT?'':'disabled').'> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td>';
		print '</tr>';

		$byproject = array();
		foreach ($tasks as $t) {
			$pid = $t['project_id'];
			if (empty($byproject[$pid])) $byproject[$pid] = array('ref'=>$t['project_ref'], 'title'=>$t['project_title'], 'tasks'=>array());
			$byproject[$pid]['tasks'][] = $t;
		}

		$projectstatic = new Project($db);
		$taskstatic = new Task($db);

		foreach ($byproject as $pid => $pdata) {
			$projectstatic->id = $pid;
			$projectstatic->ref = $pdata['ref'];
			$projectstatic->title = $pdata['title'];

			print '<tr class="oddeven trforbreak nobold">';
			print '<td colspan="'.(count($days)+2).'" class="bold">';
			print $projectstatic->getNomUrl(1, '', 0, '', 0, 0, '');
			if (!empty($projectstatic->title)) print ' <span class="opacitymedium"> - '.dol_escape_htmltag($projectstatic->title).'</span>';
			print '</td></tr>';

			foreach ($pdata['tasks'] as $t) {
				$taskstatic->id = $t['task_id'];
				$taskstatic->label = $t['task_label'];
				$taskstatic->ref = '';
				print '<tr>';
				print '<td class="paddingleft">'.$taskstatic->getNomUrl(1, 'project', 0, '', 0, 0, '').'</td>';

				$rowTotal = 0.0;
				foreach ($days as $d) {
					$thedate = $weekdates[$d];
					$name = 'hours_'.$t['task_id'].'_'.$d;
					$key = $t['task_id'].'|'.$thedate;
					$prefill = isset($valByTaskDay[$key]) ? $valByTaskDay[$key] : '';

					print '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$name.'" value="'.dol_escape_htmltag($prefill).'" placeholder="00:00" '.($object->status==TimesheetWeek::STATUS_DRAFT?'':'disabled').'></td>';

					$rowTotal += tw_parse_hours_to_float($prefill);
				}
				print '<td class="right task-total">'.tw_formatHoursSafe($rowTotal).'</td>';
				print '</tr>';
			}
		}

		print '<tr class="liste_total"><td class="right">'.$langs->trans("Total").'</td>';
		foreach ($days as $d) print '<td class="right day-total">00:00</td>';
		print '<td class="right grand-total">00:00</td></tr>';

		print '<tr class="liste_total"><td class="right">'.$langs->trans("Meals").'</td><td colspan="'.count($days).'" class="right meal-total">0</td><td></td></tr>';
		print '<tr class="liste_total"><td class="right">'.$langs->trans("Overtime").' (>'.tw_formatHoursSafe($contractedHours).')</td><td colspan="'.count($days).'" class="right overtime-total">00:00</td><td></td></tr>';

		print '</table>';
		print '</div>';

		if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) {
			print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		}

		$jsTotals = <<<'JS'
<script>
(function($){
	function parseHours(v){ if(!v) return 0; v=(""+v).replace(",","."); if(v.indexOf(":")===-1) return parseFloat(v)||0;
		var p=v.split(":"),h=parseInt(p[0],10)||0,m=parseInt(p[1],10)||0; if(m<0)m=0; if(m>59)m=59; return h+(m/60); }
	function formatHours(d){ if(isNaN(d)) return "00:00"; var h=Math.floor(d), m=Math.round((d-h)*60); if(m===60){h++;m=0;} return String(h).padStart(2,"0")+":"+String(m).padStart(2,"0"); }
	function updateTotals(){
		var grand=0; $(".task-total").text("00:00"); $(".day-total").text("00:00");
		var dayTotals = {};
		$("table.noborder tr").each(function(){
			var rowTotal=0;
			$(this).find("input.hourinput").each(function(){
				var v=parseHours($(this).val());
				if(v>0){
					rowTotal+=v;
					var idx=$(this).closest("td").index();
					dayTotals[idx]=(dayTotals[idx]||0)+v;
					grand+=v;
				}
			});
			if(rowTotal>0) $(this).find(".task-total").text(formatHours(rowTotal));
		});
		$("tr.liste_total:first td").each(function(){
			if ($(this).hasClass("day-total")) {
				var idx=$(this).index();
				var v=dayTotals[idx]||0;
				$(this).text(formatHours(v));
			}
		});
		$(".grand-total").text(formatHours(grand));
		$(".meal-total").text($(".mealbox:checked").length);
		var contracted = %s;
		var ot=grand-contracted; if(ot<0) ot=0;
		$(".overtime-total").text(formatHours(ot));
	}
	$(function(){
		updateTotals();
		$(document).on("input change","input.hourinput, input.mealbox, select[name^=zone_]", updateTotals);
	});
})(jQuery);
</script>
JS;
		print sprintf($jsTotals, (float) $contractedHours);
	}

	print '</form>';
	print '</div>'; // fichecenter (tableau des temps)

	// ---- Boutons d’action ----
	print '<div class="tabsAction">';
	if ($object->status != TimesheetWeek::STATUS_DRAFT && tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) {
		print dolGetButtonAction('', $langs->trans("Modify"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit');
	}
	if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll)) {
		if ($useajax) print dolGetButtonAction('', $langs->trans('Submit'), 'default', '', 'action-submit', 1);
		else print dolGetButtonAction('', $langs->trans('Submit'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_submit', '', 1);
	}
	if (in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED)) && (tw_has_create_for($object->fk_user, $user, $permCreate, $permCreateChild, $permCreateAll) || $isValidator)) {
		if ($useajax) print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', '', 'action-setdraft', 1);
		else print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_setdraft', '', 1);
	}
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $isValidator) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', '', 'action-approve', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', '', 'action-refuse', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_approve', '', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse', '', 1);
		}
	}
	if (tw_has_delete_for($object->fk_user, $user, $permDelete, $permDeleteChild, $permDeleteAll)) {
		if ($useajax) print dolGetButtonAction('', $langs->trans("Delete"), 'delete', '', 'action-delete', 1);
		else print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', 1);
	}
	print '</div>';
}

llxFooter();
$db->close();
