<?php
/* Copyright (C) 2025 Pierre ARDOIN
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
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr(), formatHours()

$langs->loadLangs(array("timesheetweek@timesheetweek", "other", "projects"));

$id      = GETPOSTINT('id');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// Load object by id/ref if provided by GET/POST
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$permRead       = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll    = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild  = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite      = !empty($user->rights->timesheetweek->timesheetweek->write);
$permDelete     = !empty($user->rights->timesheetweek->timesheetweek->delete);
// Nouveau : peut valider toutes les feuilles
$permValidateAll = !empty($user->rights->timesheetweek->timesheetweek->validateall);

if (!$permRead) accessforbidden();

/**
 * Helper: retourne l'ID du responsable hiérarchique (si défini) d’un user donné.
 */
function tw_get_manager_id_for_user($db, $userid) {
	$u = new User($db);
	if ($u->fetch((int) $userid) > 0) {
		if (!empty($u->fk_user)) return (int) $u->fk_user;
	}
	return 0;
}

/* =================================
 * Actions: Create
 * ================================= */
if ($action == 'add' && $permWrite) {
	$weekyear       = GETPOST('weekyear', 'alpha'); // format YYYY-Wxx
	$fk_user        = GETPOSTINT('fk_user');
	$fk_user_valid  = GETPOSTINT('fk_user_valid');
	$note           = GETPOST('note', 'restricthtml');

	$effectiveFkUser = $fk_user > 0 ? $fk_user : $user->id;
	$defaultValidatorId = tw_get_manager_id_for_user($db, $effectiveFkUser);

	$object->ref            = '(PROV)';
	$object->fk_user        = $effectiveFkUser;
	$object->status         = TimesheetWeek::STATUS_DRAFT;
	$object->note           = $note;
	$object->fk_user_valid  = $fk_user_valid > 0 ? $fk_user_valid : ($defaultValidatorId ?: null);

	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int) $m[1];
		$object->week = (int) $m[2];
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		$action = 'create';
	}

	if ($action == 'add') {
		$rescreate = $object->create($user);
		if ($rescreate > 0) {
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Safety: refetch $object if needed
if ($id > 0 && $object->id <= 0) $object->fetch($id);

// Helpers on ownership/validation
$isOwner      = ($object->fk_user > 0 && $user->id == $object->fk_user);
$isValidator  = ($object->fk_user_valid > 0 && $user->id == $object->fk_user_valid) || $permValidateAll;

/* =================================
 * Actions: Inline edit (pencils) only in DRAFT
 * ================================= */
$allowInlineEdit = ($permWrite && $object->status == TimesheetWeek::STATUS_DRAFT);

if ($allowInlineEdit) {
	// Set fk_user
	if ($action == 'set_fk_user' && GETPOST('token') == $_SESSION['newtoken']) {
		$val = GETPOSTINT('fk_user');
		if ($val > 0) {
			$object->fk_user = $val;
			// update direct
			$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET fk_user=".(int)$val." WHERE rowid=".(int)$object->id);
		}
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	// Set week/year
	if ($action == 'set_weekyear' && GETPOST('token') == $_SESSION['newtoken']) {
		$weekyear = GETPOST('weekyear_edit', 'alpha');
		if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
			$object->year = (int) $m[1];
			$object->week = (int) $m[2];
			$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET year=".(int)$object->year.", week=".(int)$object->week." WHERE rowid=".(int)$object->id);
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		}
	}
	// Set fk_user_valid
	if ($action == 'set_fk_user_valid' && GETPOST('token') == $_SESSION['newtoken']) {
		$val = GETPOSTINT('fk_user_valid');
		$object->fk_user_valid = $val > 0 ? $val : null;
		$db->query("UPDATE ".MAIN_DB_PREFIX."timesheet_week SET fk_user_valid ".($val>0?"=".(int)$val:"= NULL")." WHERE rowid=".(int)$object->id);
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	// Set note
	if ($action == 'set_note' && GETPOST('token') == $_SESSION['newtoken']) {
		$val = GETPOST('note_edit', 'restricthtml');
		$object->note = $val;
		$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET note='". $db->escape($val)."' WHERE rowid=".(int)$object->id;
		$db->query($sql);
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

/* =================================
 * Actions: Save lines (UPSERT)
 * ================================= */
if ($action == 'save' && $permWrite && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	$db->begin();

	// Contracted weekly hours
	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contractedHours = (!empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0);

	$map = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);

	// Build index of existing lines for upsert
	$linesExisting = array(); // "taskid|YYYY-MM-DD" => rowid
	$sqlsel = "SELECT rowid, fk_task, day_date FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $object->id;
	$resq = $db->query($sqlsel);
	if ($resq) {
		while ($o = $db->fetch_object($resq)) {
			$linesExisting[(int)$o->fk_task.'|'.$o->day_date] = (int) $o->rowid;
		}
	}

	// Zone / meal per day (sheet-level)
	$dayZone = array(); $dayMeal = array();
	foreach ($map as $day => $off) {
		$dayZone[$day] = GETPOSTINT('zone_'.$day);
		$dayMeal[$day] = GETPOST('meal_'.$day, 'alpha') ? 1 : 0;
	}

	$totalPosted = 0.0;

	// Parse all hour inputs
	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/', $key, $m)) {
			$taskid = (int) $m[1];
			$day    = $m[2];

			// Parse "HH:MM" or decimal
			$hours = 0.0;
			$val = trim($val);
			if ($val !== '') {
				if (strpos($val, ':') !== false) {
					list($hh, $mm) = array_pad(explode(':', $val, 2), 2, '0');
					$hours = (int)$hh + ((int)$mm / 60.0);
				} else {
					$hours = (float) str_replace(',', '.', $val);
				}
			}

			// Day date (ISO week)
			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week);
			$dto->modify('+'.$map[$day].' day');
			$dayDate = $dto->format('Y-m-d');

			$zone = (int) $dayZone[$day];
			$meal = (int) $dayMeal[$day];

			$keyline = $taskid.'|'.$dayDate;

			if ($hours > 0) {
				$totalPosted += $hours;

				if (!empty($linesExisting[$keyline])) {
					// Update
					$sqlu  = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET";
					$sqlu .= " hours = ".((float)$hours).", zone = ".((int)$zone).", meal = ".((int)$meal);
					$sqlu .= " WHERE rowid = ".((int)$linesExisting[$keyline]);
					if (!$db->query($sqlu)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				} else {
					// Insert
					$sqli  = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(";
					$sqli .= "fk_timesheet_week, fk_task, day_date, hours, zone, meal";
					$sqli .= ") VALUES (";
					$sqli .= (int)$object->id.", ".(int)$taskid.", '".$db->escape($dayDate)."', ".((float)$hours).", ".((int)$zone).", ".((int)$meal);
					$sqli .= ")";
					if (!$db->query($sqli)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				}
			} else {
				// Hours cleared => delete if exists
				if (!empty($linesExisting[$keyline])) {
					$sqld = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE rowid = ".((int)$linesExisting[$keyline]);
					if (!$db->query($sqld)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				}
			}
		}
	}

	// Update totals into parent
	$overtime = $totalPosted - $contractedHours;
	if ($overtime < 0) $overtime = 0;

	$sqlparent  = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET";
	$sqlparent .= " total_hours = ".((float)$totalPosted).",";
	$sqlparent .= " overtime_hours = ".((float)$overtime);
	$sqlparent .= " WHERE rowid = ".((int)$object->id);
	if (!$db->query($sqlparent)) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

/* =================================
 * Actions: Workflow Submit / Draft / Approve / Refuse (with confirm)
 * ================================= */
function tw_update_status($db, $object, $newstatus, $setdatevalidation = false, $setvalidatorifempty = false, $useridvalidator = 0) {
	$now = dol_now();
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status=".(int)$newstatus;
	if ($setdatevalidation) $sql .= ", date_validation='".$db->idate($now)."'";
	if ($setvalidatorifempty && $useridvalidator > 0) {
		$sql .= ", fk_user_valid = COALESCE(fk_user_valid,".(int)$useridvalidator.")";
	}
	$sql .= " WHERE rowid=".(int)$object->id;
	return $db->query($sql) ? 1 : -1;
}

// Submit
if ($action == 'confirm_submit' && $confirm === 'yes' && $id > 0) {
	// Only owner can submit in our rule (can extend later)
	if ($isOwner && $permWrite && $object->status == TimesheetWeek::STATUS_DRAFT) {
		$res = tw_update_status($db, $object, TimesheetWeek::STATUS_SUBMITTED, false, false, 0);
		if ($res > 0) {
			setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Back to draft
if ($action == 'confirm_setdraft' && $confirm === 'yes' && $id > 0) {
	if (($isOwner || $isValidator) && in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED))) {
		$res = tw_update_status($db, $object, TimesheetWeek::STATUS_DRAFT, false, false, 0);
		if ($res > 0) {
			setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Approve
if ($action == 'confirm_approve' && $confirm === 'yes' && $id > 0) {
	if ($isValidator && $object->status == TimesheetWeek::STATUS_SUBMITTED) {
		$res = tw_update_status($db, $object, TimesheetWeek::STATUS_APPROVED, true, true, $user->id);
		if ($res > 0) {
			setEventMessages($langs->trans("Validated"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Refuse
if ($action == 'confirm_refuse' && $confirm === 'yes' && $id > 0) {
	if ($isValidator && $object->status == TimesheetWeek::STATUS_SUBMITTED) {
		$res = tw_update_status($db, $object, TimesheetWeek::STATUS_REFUSED, false, true, $user->id);
		if ($res > 0) {
			setEventMessages($langs->trans("Refused"), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

/* =================================
 * Actions: Delete (confirm)
 * ================================= */
if ($action == 'confirm_delete' && $permDelete && $id > 0 && GETPOST('token') == $_SESSION['newtoken']) {
	if ($confirm === 'yes') {
		$resdel = $object->delete($user);
		if ($resdel > 0) {
			header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php',1));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

/* =================================
 * View
 * ================================= */
$form = new Form($db);
$title = $langs->trans("TimesheetWeek");
llxHeader('', $title);

/* ------- Create mode ------- */
if ($action == 'create') {
	if (!$permWrite) accessforbidden();

	// Déterminer l'utilisateur concerné par défaut (user courant)
	$prefUserId = GETPOSTINT('fk_user') ? GETPOSTINT('fk_user') : $user->id;
	$defaultValidatorId = tw_get_manager_id_for_user($db, $prefUserId);

	// Titre avec picto
	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'time');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$form->select_dolusers($prefUserId,'fk_user',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.getWeekSelectorDolibarr($form,'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td></tr>';
	print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$form->select_dolusers($defaultValidatorId,'fk_user_valid',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" rows="3" class="quatrevingtpercent"></textarea></td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';
}
/* ------- View mode ------- */
elseif ($id > 0 && $action != 'create') {
	// Restrict who can see
	$cansee = false;
	if ($permReadAll) $cansee = true;
	elseif ($permReadChild) {
		if ($object->fk_user == $user->id) $cansee = true;
		else {
			$subs = $user->getAllChildIds();
			if (is_array($subs) && in_array($object->fk_user,$subs)) $cansee = true;
		}
	} elseif ($permRead && $object->fk_user == $user->id) $cansee = true;
	if (!$cansee) accessforbidden();

	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head,'card',$langs->trans("TimesheetWeek"),-1,'time');

	// Confirm popups
	$formconfirm = '';
	$useajax = !empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile);

	if ($action == 'delete') {
		$formconfirm .= $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('DeleteTimesheetWeek'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			$useajax ? 1 : 0,
			$useajax ? 'action-delete' : ''
		);
	}
	if ($action == 'ask_submit') {
		$formconfirm .= $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Submit'),
			$langs->trans('ConfirmSubmit', $object->ref),
			'confirm_submit',
			array(),
			$useajax ? 1 : 0,
			$useajax ? 'action-submit' : ''
		);
	}
	if ($action == 'ask_setdraft') {
		$formconfirm .= $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('SetToDraft'),
			$langs->trans('ConfirmSetToDraft', $object->ref),
			'confirm_setdraft',
			array(),
			$useajax ? 1 : 0,
			$useajax ? 'action-setdraft' : ''
		);
	}
	if ($action == 'ask_approve') {
		$formconfirm .= $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Validate'),
			$langs->trans('ConfirmValidate', $object->ref),
			'confirm_approve',
			array(),
			$useajax ? 1 : 0,
			$useajax ? 'action-approve' : ''
		);
	}
	if ($action == 'ask_refuse') {
		$formconfirm .= $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Refuse'),
			$langs->trans('ConfirmRefuse', $object->ref),
			'confirm_refuse',
			array(),
			$useajax ? 1 : 0,
			$useajax ? 'action-refuse' : ''
		);
	}
	print $formconfirm;

	// Banner
	dol_banner_tab($object,'ref');

	print '<div class="fichecenter">';

	/* Left block */
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent">';

	// Employee
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	if ($allowInlineEdit && $action == 'edit_fk_user') {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_fk_user">';
		print $form->select_dolusers($object->fk_user, 'fk_user', 1);
		print '&nbsp;'.$form->buttonsSaveCancel("Save", "Cancel");
		print '</form>';
	} else {
		if ($object->fk_user > 0) {
			$u = new User($db); $u->fetch($object->fk_user);
			print $u->getNomUrl(1);
		}
		if ($allowInlineEdit) {
			print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_fk_user">'.img_edit($langs->trans("Edit"), 1).'</a>';
		}
	}
	print '</td></tr>';

	// Week / Year
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($allowInlineEdit && $action == 'edit_weekyear') {
		$currentYW = sprintf('%04d-W%02d', $object->year, $object->week);
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_weekyear">';
		print getWeekSelectorDolibarr($form, 'weekyear_edit', $currentYW);
		print ' <span id="weekrange_edit" class="opacitymedium small"></span>';
		print '&nbsp;'.$form->buttonsSaveCancel("Save", "Cancel");
		print '</form>';
		print '<script>(function($){$(function(){var v="'.dol_escape_js($currentYW).'"; if($("#weekyear_edit").length){ $("#weekyear_edit").val(v).trigger("change"); } });})(jQuery);</script>';
	} else {
		print $object->week.' / '.$object->year;
		if ($allowInlineEdit) {
			print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_weekyear">'.img_edit($langs->trans("Edit"), 1).'</a>';
		}
	}
	print '</td></tr>';

	// Validator
	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($allowInlineEdit && $action == 'edit_fk_user_valid') {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_fk_user_valid">';
		print $form->select_dolusers($object->fk_user_valid > 0 ? $object->fk_user_valid : 0, 'fk_user_valid', 1);
		print '&nbsp;'.$form->buttonsSaveCancel("Save", "Cancel");
		print '</form>';
	} else {
		if ($object->fk_user_valid > 0) {
			$uv = new User($db); $uv->fetch($object->fk_user_valid);
			print $uv->getNomUrl(1);
		} else {
			print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
		}
		if ($allowInlineEdit) {
			print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_fk_user_valid">'.img_edit($langs->trans("Edit"), 1).'</a>';
		}
	}
	print '</td></tr>';

	// Totals (read-only)
	print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.formatHours((float)$object->total_hours).'</td></tr>';
	print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.formatHours((float)$object->overtime_hours).'</td></tr>';

	print '</table>';
	print '</div>';

	/* Right block */
	print '<div class="fichehalfright">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation,'dayhour').'</td></tr>';
	// Note (inline)
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($allowInlineEdit && $action == 'edit_note') {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="set_note">';
		print '<textarea name="note_edit" rows="3" class="quatrevingtpercent">'.dol_escape_htmltag($object->note).'</textarea>';
		print '<br>'.$form->buttonsSaveCancel("Save", "Cancel");
		print '</form>';
	} else {
		print nl2br(dol_escape_htmltag($object->note));
		if ($allowInlineEdit) {
			print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_note">'.img_edit($langs->trans("Edit"), 1).'</a>';
		}
	}
	print '</td></tr>';

	print '</table>';
	print '</div>';

	// Close fichecenter and clear
	print '</div>'; // .fichecenter
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	/* ===== Grid of hours (outside fiche head) ===== */
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<h3>'.$langs->trans("AssignedTasks").'</h3>';

	// Assigned tasks
	$tasks = $object->getAssignedTasks($object->fk_user);

	// Existing lines (prefill)
	$lines = array();
	$sqlL = "SELECT rowid, fk_task, day_date, hours, zone, meal 
	         FROM ".MAIN_DB_PREFIX."timesheet_week_line
	         WHERE fk_timesheet_week = ".((int)$object->id);
	$resL = $db->query($sqlL);
	if ($resL) {
		while ($r = $db->fetch_object($resL)) $lines[] = $r;
	}

	// Build maps for prefill
	$hoursMap = array(); // [taskid][date] => ['hours'=>,'zone'=>,'meal'=>]
	$dayMeta  = array(); // [date] => ['zone'=>,'meal'=>]
	foreach ($lines as $L) {
		$t = (int) $L->fk_task;
		$d = $L->day_date;
		if (!isset($hoursMap[$t])) $hoursMap[$t] = array();
		$hoursMap[$t][$d] = array('hours'=>(float)$L->hours,'zone'=>(int)$L->zone,'meal'=>(int)$L->meal);
		if (!isset($dayMeta[$d])) $dayMeta[$d] = array('zone'=>(int)$L->zone,'meal'=> (int)$L->meal);
	}

	if (empty($tasks)) {
		print '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
	} else {
		$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
		$dto = new DateTime(); $dto->setISODate($object->year,$object->week);
		$weekdates = array();
		foreach($days as $d){ $weekdates[$d]=$dto->format('Y-m-d'); $dto->modify('+1 day'); }

		// Contracted hours for overtime line
		$userEmployee = new User($db); $userEmployee->fetch($object->fk_user);
		$contractedHours = (!empty($userEmployee->weeklyhours)?(float)$userEmployee->weeklyhours:35.0);

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		// Header row
		print '<tr class="liste_titre"><th>'.$langs->trans("Project / Task").'</th>';
		foreach($days as $d){
			print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]),'day').'</span></th>';
		}
		print '<th>'.$langs->trans("Total").'</th></tr>';

		// Zone + Meal line (by day)
		print '<tr class="liste_titre"><td></td>';
		foreach($days as $d){
			$curDate = $weekdates[$d];
			$zPref = isset($dayMeta[$curDate]) ? (int)$dayMeta[$curDate]['zone'] : 1;
			$mPref = isset($dayMeta[$curDate]) ? (int)$dayMeta[$curDate]['meal'] : 0;

			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat">';
			for($z=1;$z<=5;$z++){
				print '<option value="'.$z.'"'.($zPref==$z?' selected':'').'>'.$z.'</option>';
			}
			print '</select><br>';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($mPref?' checked':'').'> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td></tr>';

		// Group tasks by project
		$byproject = array();
		foreach ($tasks as $t) {
			$pid = (int) $t['project_id'];
			if (!isset($byproject[$pid])) {
				$byproject[$pid] = array(
					'project_id'    => $pid,
					'project_ref'   => $t['project_ref'],
					'project_title' => $t['project_title'],
					'tasks'         => array()
				);
			}
			$byproject[$pid]['tasks'][] = $t;
		}

		$projectstatic = new Project($db);
		$taskstatic    = new Task($db);

		$colspan = 1 + count($days) + 1;

		foreach ($byproject as $pid => $pdata) {
			$projectstatic->fetch($pid);

			print '<tr class="oddeven trforbreak nobold"><td colspan="'.$colspan.'">';
			print $projectstatic->getNomUrl(1).' - '.dol_escape_htmltag($pdata['project_title']);
			print '</td></tr>';

			foreach ($pdata['tasks'] as $task) {
				$taskstatic->fetch((int)$task['task_id']);

				print '<tr>';
				print '<td class="paddingleft">'.$taskstatic->getNomUrl(1).'</td>';

				foreach ($days as $d) {
					$curDate = $weekdates[$d];
					$prefVal = '';
					if (isset($hoursMap[(int)$task['task_id']][$curDate])) {
						$prefHours = (float) $hoursMap[(int)$task['task_id']][$curDate]['hours'];
						$hh = floor($prefHours);
						$mm = round(($prefHours - $hh)*60);
						if ($mm === 60) { $hh++; $mm = 0; }
						$prefVal = sprintf('%02d:%02d', $hh, $mm);
					}
					$iname='hours_'.$task['task_id'].'_'.$d;
					print '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($prefVal).'" placeholder="0:00"></td>';
				}
				print '<td class="right task-total">00:00</td>';
				print '</tr>';
			}
		}

		// Totals rows
		print '<tr class="liste_total"><td class="right">'.$langs->trans("Total").'</td>';
		foreach($days as $d) print '<td class="right day-total">00:00</td>';
		print '<td class="right grand-total">00:00</td></tr>';

		print '<tr class="liste_total"><td class="right">'.$langs->trans("Meals").'</td><td colspan="'.count($days).'" class="right meal-total">0</td><td></td></tr>';
		print '<tr class="liste_total"><td class="right">'.$langs->trans("Overtime").' (>'.formatHours($contractedHours).')</td><td colspan="'.count($days).'" class="right overtime-total">00:00</td><td></td></tr>';

		print '</table></div>';

		print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		print '</form>';

		// Totals JS (init on load)
		print sprintf("
<script>
(function($){
	function parseHours(v){if(!v)return 0;if(v.indexOf(':')==-1)return parseFloat(v)||0;var p=v.split(':'),h=parseInt(p[0],10)||0,m=parseInt(p[1],10)||0;return h+(m/60);}
	function formatHours(d){if(isNaN(d))return '00:00';var h=Math.floor(d),m=Math.round((d-h)*60);if(m===60){h++;m=0;}return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');}
	function updateTotals(){
		var grand=0;$('.task-total').text('00:00');$('.day-total').text('00:00');
		$('tr').each(function(){
			var rowT=0;
			$(this).find('input.hourinput').each(function(){
				var v=parseHours($(this).val());
				if(!isNaN(v)&&v>0){
					rowT+=v;
					var idx=$(this).closest('td').index();
					var cell=$('tr.liste_total:first td').eq(idx);
					var cur=parseHours(cell.text());
					cell.text(formatHours(cur+v));
					grand+=v;
				}
			});
			if(rowT>0)$(this).find('.task-total').text(formatHours(rowT));
		});
		$('.grand-total').text(formatHours(grand));
		$('.meal-total').text($('.mealbox:checked').length);
		var weeklyContract = %s; var ot = grand - weeklyContract; if(ot<0) ot=0;
		$('.overtime-total').text(formatHours(ot));
	}
	$(function(){ updateTotals(); });
	$(document).on('input change','input.hourinput, input.mealbox', updateTotals);
})(jQuery);
</script>", (float) $contractedHours);
	}

	// Action buttons (outside fiche head)
	print '<div class="tabsAction">';
	// Hide Modify if DRAFT
	if ($permWrite && $object->status != TimesheetWeek::STATUS_DRAFT) {
		print dolGetButtonAction('', $langs->trans("Modify"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit');
	}

	// Workflow buttons
	// Submit
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $isOwner && $permWrite) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('Submit'), 'default', '', 'action-submit', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('Submit'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_submit', '', 1);
		}
	}
	// Back to draft
	if (in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED)) && ($isOwner || $isValidator)) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', '', 'action-setdraft', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_setdraft', '', 1);
		}
	}
	// Approve / Refuse for validator or validateall
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $isValidator) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', '', 'action-approve', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', '', 'action-refuse', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_approve', '', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse', '', 1);
		}
	}

	// Delete
	if ($useajax) {
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', '', 'action-delete', $permDelete);
	} else {
		$deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, '', $permDelete);
	}

	print '</div>';
}

// Week selector helper (du/au)
print <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val) {
		var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;
	}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7)),d=s.getUTCDay(),st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0'),mm=String(d.getUTCMonth()+1).padStart(2,'0'),yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w),e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	function updateWeekRangeEdit(){var v=$('#weekyear_edit').val();var p=parseYearWeek(v);if(!p){$('#weekrange_edit').text('');return;}var s=isoWeekStart(p.y,p.w),e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange_edit').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){
		if($.fn.select2) {
			if($('#weekyear').length) $('#weekyear').select2({width:'resolve'});
			if($('#weekyear_edit').length) $('#weekyear_edit').select2({width:'resolve'});
		}
		if($('#weekyear').length){updateWeekRange();$('#weekyear').on('change',updateWeekRange);}
		if($('#weekyear_edit').length){updateWeekRangeEdit();$('#weekyear_edit').on('change',updateWeekRangeEdit);}
	});
})(jQuery);
</script>
JS;

llxFooter();
$db->close();
