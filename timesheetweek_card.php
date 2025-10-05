<?php
/* Copyright (C) 2025
 * Pierre ARDOIN
 *
 * GPLv3
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
dol_include_once('/timesheetweek/class/timesheetweekline.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

$langs->loadLangs(array("timesheetweek@timesheetweek", "other", "projects", "users"));

// Params
$id      = GETPOSTINT('id');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');

// Object
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// Load object if id
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// ---- Permissions (Dolibarr standard) ----
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

// Helper: children ids
$childids = $user->getAllChildIds(1);

// Helper: can see object
function canSeeTimesheet($user, $obj, $permReadAll, $permReadChild, $permRead) {
	if (empty($obj) || empty($obj->fk_user)) return false;
	if ($permReadAll) return true;
	if ($permReadChild) {
		$childs = $user->getAllChildIds(1);
		if ($obj->fk_user == $user->id || in_array($obj->fk_user, $childs)) return true;
	}
	if ($permRead && $obj->fk_user == $user->id) return true;
	return false;
}

// Helper: can create for target user
function canCreateFor($user, $targetUserId, $permCreate, $permCreateChild, $permCreateAll) {
	if ($permCreateAll) return true;
	if ($permCreate && $targetUserId == $user->id) return true;
	if ($permCreateChild && $targetUserId > 0) {
		$childs = $user->getAllChildIds(1);
		if (in_array($targetUserId, $childs)) return true;
	}
	return false;
}

// Helper: can validate for target user
function canValidateFor($user, $targetUserId, $permValidate, $permValidateChild, $permValidateAll) {
	if ($permValidateAll) return true;
	if ($permValidate && $targetUserId == $user->id) return true;
	if ($permValidateChild && $targetUserId > 0) {
		$childs = $user->getAllChildIds(1);
		if (in_array($targetUserId, $childs)) return true;
	}
	return false;
}

// Helper: can delete
function canDeleteFor($user, $targetUserId, $permDelete, $permDeleteChild, $permDeleteAll) {
	if ($permDeleteAll) return true;
	if ($permDelete && $targetUserId == $user->id) return true;
	if ($permDeleteChild && $targetUserId > 0) {
		$childs = $user->getAllChildIds(1);
		if (in_array($targetUserId, $childs)) return true;
	}
	return false;
}

// ----------------- Actions: create -----------------
if ($action == 'add') {
	if (!$permCreate && !$permCreateChild && !$permCreateAll) accessforbidden();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user_post  = GETPOSTINT('fk_user');	// employee
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$targetUserId = $fk_user_post > 0 ? $fk_user_post : $user->id;
	if (!canCreateFor($user, $targetUserId, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();

	$object->ref          = '(PROV)';
	$object->fk_user      = $targetUserId;
	$object->status       = TimesheetWeek::STATUS_DRAFT;
	$object->note         = $note;
	$object->fk_user_valid= $fk_user_valid > 0 ? $fk_user_valid : null;
	$object->entity       = $conf->entity;

	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int) $m[1];
		$object->week = (int) $m[2];
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		$action = 'create';
	}

	if ($action == 'add') {
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

// ----------------- Actions: per-field update (pencil) -----------------
if ($id > 0 && in_array($action, array('update_field'))) {
	if ($object->fetch($id) > 0) {
		// Only when draft and allowed to edit this sheet
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			$field = GETPOST('field', 'aZ09');
			$error = 0;

			if ($field == 'fk_user') {
                $newuser = GETPOSTINT('fk_user');
                if (!canCreateFor($user, $newuser, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();
                $object->fk_user = $newuser;
            } elseif ($field == 'weekyear') {
                $weekyear = GETPOST('weekyear', 'alpha');
                if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
                    $object->year = (int) $m[1];
                    $object->week = (int) $m[2];
                } else {
                    $error++;
                    setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
                }
            } elseif ($field == 'fk_user_valid') {
                $object->fk_user_valid = GETPOSTINT('fk_user_valid') ?: null;
            } elseif ($field == 'note') {
                $object->note = GETPOST('note', 'restricthtml');
            }

			if (!$error) {
				$resu = $object->update($user);
				if ($resu <= 0) setEventMessages($object->error, $object->errors, 'errors');
			}
		}
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

// ----------------- Actions: save lines/grid -----------------
if ($action == 'save' && $id > 0) {
	if ($object->fetch($id) <= 0) accessforbidden();
	if ($object->status != TimesheetWeek::STATUS_DRAFT) accessforbidden();
	if (!canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();

	$db->begin();

	// Clean previous lines of this week
	$sqlDel = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $object->id;
	if (!$db->query($sqlDel)) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$daysMap = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);

	// Compute totals
	$grandTotalDec = 0.0;

	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/', $key, $m)) {
			$taskid = (int) $m[1];
			$dayKey = $m[2];

			$val = trim($val);
			if ($val === '' || $val === '0' || $val === '0:00' || $val === '00:00') continue;

			// Accept HH:MM or decimal
			$hoursdec = 0.0;
			if (strpos($val, ':') !== false) {
				list($hh, $mm) = array_pad(explode(':', $val), 2, '0');
				$hoursdec = (int)$hh + (max(0, (int)$mm) / 60.0);
			} else {
				$hoursdec = (float) str_replace(',', '.', $val);
			}
			if ($hoursdec <= 0) continue;

			$dto = new DateTime();
			$dto->setISODate($object->year, $object->week);
			$dto->modify('+'.$daysMap[$dayKey].' day');
			$daydate = $dto->format('Y-m-d');

			$zone = GETPOSTINT('zone_'.$dayKey);
			$meal = GETPOST('meal_'.$dayKey) ? 1 : 0;

			$line = new TimesheetWeekLine($db);
			$line->fk_timesheet_week = $object->id;
			$line->fk_task  = $taskid;
			$line->day_date = $daydate;
			$line->hours    = $hoursdec;
			$line->zone     = $zone;
			$line->meal     = $meal;

			// Upsert: check if exists (task+date)
			$existsId = $line->getLine($object->id, $taskid, $daydate);
			if ($existsId > 0) {
				$line->id = $existsId;
				$resu = $line->update($user);
			} else {
				$resu = $line->create($user);
			}

			if ($resu < 0) {
				$db->rollback();
				setEventMessages($line->error, $line->errors, 'errors');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
				exit;
			}

			$grandTotalDec += $hoursdec;
		}
	}

	// Save totals on header
	// Contracted hours for user
	$employee = new User($db);
	$employee->fetch($object->fk_user);
	$weeklyContract = (!empty($employee->weeklyhours) ? (float)$employee->weeklyhours : 35.0);
	$overtime = max(0, $grandTotalDec - $weeklyContract);

	$object->total_hours   = $grandTotalDec;
	$object->overtime_hours= $overtime;

	$reshead = $object->update($user);
	if ($reshead < 0) {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Actions: workflow (submit/backtodraft/approve/refuse) -----------------
if ($id > 0 && in_array($action, array('submit','backtodraft','approve','refuse'))) {
	if ($object->fetch($id) <= 0) accessforbidden();

	// Submit: only owner (or creator for target) with create* perms
	if ($action == 'submit') {
		if (!canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) accessforbidden();
		if ($object->status != TimesheetWeek::STATUS_DRAFT) accessforbidden();
		$object->status = TimesheetWeek::STATUS_SUBMITTED;
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	}

	// Back to draft: owner or validator or validate* perms
	if ($action == 'backtodraft') {
		// Allow owner, validator, or any validate for target user
		$ok = false;
		if ($user->id == $object->fk_user) $ok = true;
		if ($object->fk_user_valid > 0 && $user->id == $object->fk_user_valid) $ok = true;
		if (!$ok) $ok = canValidateFor($user, $object->fk_user, $permValidate, $permValidateChild, $permValidateAll);
		if (!$ok) accessforbidden();
		$object->status = TimesheetWeek::STATUS_DRAFT;
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	}

	// Approve / Refuse: validator or validate* perms
	if ($action == 'approve' || $action == 'refuse') {
		$ok = false;
		if ($object->fk_user_valid > 0 && $user->id == $object->fk_user_valid) $ok = true;
		if (!$ok) $ok = canValidateFor($user, $object->fk_user, $permValidate, $permValidateChild, $permValidateAll);
		if (!$ok) accessforbidden();

		if ($object->status != TimesheetWeek::STATUS_SUBMITTED) accessforbidden();

		$object->status = ($action == 'approve') ? TimesheetWeek::STATUS_APPROVED : TimesheetWeek::STATUS_REFUSED;
		if ($action == 'approve') $object->date_validation = dol_now();
		$res = $object->update($user);
		if ($res > 0) {
			setEventMessages($langs->trans($action == 'approve' ? "Approved" : "Refused"), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Actions: delete -----------------
if ($action == 'confirm_delete' && $confirm == 'yes' && $id > 0) {
	if ($object->fetch($id) <= 0) accessforbidden();
	if (!canDeleteFor($user, $object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll)) accessforbidden();

	$db->begin();
	$res = $object->delete($user);
	if ($res > 0) {
		$db->commit();
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php', 1));
		exit;
	} else {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// ----------------- View -----------------
$form = new Form($db);
$title = $langs->trans("TimesheetWeek");
$helpurl = '';

llxHeader('', $title, $helpurl);

if (GETPOST('action') == 'delete') {
	// Confirm box
	$formconfirm = $form->formconfirm(
		$_SERVER["PHP_SELF"].'?id='.$id,
		$langs->trans("Delete"),
		$langs->trans("ConfirmDeleteRecord"),
		'confirm_delete',
		'',
		0,
		1
	);
	print $formconfirm;
}

// Create mode
if ($action == 'create') {
	if (!$permCreate && !$permCreateChild && !$permCreateAll) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';

	// Employee
	$defaultEmployeeId = $user->id;
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	print $form->select_dolusers($defaultEmployeeId, 'fk_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth300');
	print '</td></tr>';

	// Week selector
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	print getWeekSelectorDolibarr($form, 'weekyear');
	print ' <span id="weekrange" class="opacitymedium small"></span>';
	print '</td></tr>';

	// Default validator = employee manager if exists
	$uemp = new User($db);
	$uemp->fetch($defaultEmployeeId);
	$defaultValidatorId = ($uemp->fk_user > 0 ? $uemp->fk_user : 0);

	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	print $form->select_dolusers($defaultValidatorId, 'fk_user_valid', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth300');
	print '</td></tr>';

	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	print '<textarea name="note" class="quatrevingtpercent" rows="3"></textarea>';
	print '</td></tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	// Week range js
	print getWeekRangeHelperJS();

} elseif ($id > 0) {
	// View/Edit
	if ($object->id <= 0) {
		print '<div class="error">'.$langs->trans("RecordNotFound").'</div>';
	} else {
		if (!canSeeTimesheet($user, $object, $permReadAll, $permReadChild, $permRead)) accessforbidden();

		$head = timesheetweekPrepareHead($object);
		print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

		// Banner
		$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
		dol_banner_tab($object, 'ref', $linkback, 0, 'ref');

		// Fiche center
		print '<div class="fichecenter">';

		// --- LEFT (Employee, Week, Validator, Note) ---
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">';

		// Employee
		print '<tr>';
		print '<td class="titlefield">'.$langs->trans("Employee").'</td>';
		print '<td>';
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			if (GETPOST('edit') == 'fk_user') {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="update_field">';
				print '<input type="hidden" name="field" value="fk_user">';
				print $form->select_dolusers($object->fk_user, 'fk_user', 1);
				print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
				print '&nbsp;<a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
				print '</form>';
			} else {
				$u = new User($db);
				$u->fetch($object->fk_user);
				print $u->getNomUrl(1);
				print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&edit=fk_user">'.img_edit('',1).'</a>';
			}
		} else {
			$u = new User($db);
			$u->fetch($object->fk_user);
			print $u->getNomUrl(1);
		}
		print '</td>';
		print '</tr>';

		// Week
		print '<tr>';
		print '<td>'.$langs->trans("Week").'</td>';
		print '<td>';
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			if (GETPOST('edit') == 'weekyear') {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="update_field">';
				print '<input type="hidden" name="field" value="weekyear">';
				$current = sprintf('%04d-W%02d', $object->year, $object->week);
				print getWeekSelectorDolibarr($form, 'weekyear', $current);
				print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
				print '&nbsp;<a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
				print '</form>';
				print '<div class="opacitymedium small" id="weekrange"></div>';
			} else {
				print $langs->trans("Week").' '.$object->week.' - '.$object->year;
				print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&edit=weekyear">'.img_edit('',1).'</a>';
			}
		} else {
			print $langs->trans("Week").' '.$object->week.' - '.$object->year;
		}
		print '</td>';
		print '</tr>';

		// Validator
		print '<tr>';
		print '<td>'.$langs->trans("Validator").'</td>';
		print '<td>';
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			if (GETPOST('edit') == 'fk_user_valid') {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="update_field">';
				print '<input type="hidden" name="field" value="fk_user_valid">';
				print $form->select_dolusers($object->fk_user_valid, 'fk_user_valid', 1);
				print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'">';
				print '&nbsp;<a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
				print '</form>';
			} else {
				if ((int)$object->fk_user_valid > 0) {
					$uv = new User($db);
					$uv->fetch($object->fk_user_valid);
					print $uv->getNomUrl(1);
				}
				print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&edit=fk_user_valid">'.img_edit('',1).'</a>';
			}
		} else {
			if ((int)$object->fk_user_valid > 0) {
				$uv = new User($db);
				$uv->fetch($object->fk_user_valid);
				print $uv->getNomUrl(1);
			}
		}
		print '</td>';
		print '</tr>';

		// Note (left column)
		print '<tr>';
		print '<td>'.$langs->trans("Note").'</td>';
		print '<td>';
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			if (GETPOST('edit') == 'note') {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="update_field">';
				print '<input type="hidden" name="field" value="note">';
				print '<textarea name="note" rows="3" class="quatrevingtpercent">'.dol_escape_htmltag($object->note).'</textarea>';
				print '<br><input type="submit" class="button small" value="'.$langs->trans("Save").'">';
				print '&nbsp;<a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
				print '</form>';
			} else {
				print nl2br(dol_escape_htmltag($object->note));
				print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&edit=note">'.img_edit('',1).'</a>';
			}
		} else {
			print nl2br(dol_escape_htmltag($object->note));
		}
		print '</td>';
		print '</tr>';

		print '</table>';
		print '</div>'; // left

		// --- RIGHT (dates, status, totals) ---
		print '<div class="fichehalfright">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">';

		print '<tr><td class="titlefield">'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
		print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
		print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
		print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(1).'</td></tr>';
		print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.formatHours((float)$object->total_hours).'</td></tr>';
		print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.formatHours((float)$object->overtime_hours).'</td></tr>';

		print '</table>';
		print '</div>'; // right

		print '</div>'; // fichecenter

		print dol_get_fiche_end();

		// ---- GRID OF HOURS (below fiche) ----
		// Compute week dates
		$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
		$dto = new DateTime();
		$dto->setISODate($object->year, $object->week);
		$weekdates = array();
		foreach ($days as $d) {
			$weekdates[$d] = $dto->format('Y-m-d');
			$dto->modify('+1 day');
		}

		// Employee weekly hours
		$employee = new User($db);
		$employee->fetch($object->fk_user);
		$contractedHours = (!empty($employee->weeklyhours) ? (float)$employee->weeklyhours : 35.0);

		// Assigned tasks
		$tasks = $object->getAssignedTasks($object->fk_user);

		// Lines map (task->date -> hours), and per-day flags (zone, meal)
		$lines = $object->getLines();
		$mapHours = array(); // $mapHours[$taskid][$yyyy-mm-dd] = hours
		$perdayZone = array(); // [$date] = zone (first found)
		$perdayMeal = array(); // [$date] = meal (1 if any line had meal)

		if (!empty($lines)) {
			foreach ($lines as $L) {
				$mapHours[(int)$L->fk_task][$L->day_date] = (float)$L->hours;
				if (!isset($perdayZone[$L->day_date])) $perdayZone[$L->day_date] = (int)$L->zone;
				if ((int)$L->meal > 0) $perdayMeal[$L->day_date] = 1;
			}
		}

		// Group tasks by project
		$byProject = array();
		foreach ($tasks as $t) {
			$pid = (int)$t['project_id'];
			if (empty($byProject[$pid])) {
				$byProject[$pid] = array(
					'ref' => $t['project_ref'],
					'title' => $t['project_title'],
					'tasks' => array()
				);
			}
			$byProject[$pid]['tasks'][] = $t;
		}

		// Cache for getNomUrl
		$cacheProject = array();
		$cacheTask = array();

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save">';

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		// Header row
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("ProjectTask").'</th>';
		foreach ($days as $d) {
			$label = $langs->trans(substr($d,0,3));
			print '<th class="center">'.$label.'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
		}
		print '<th class="right">'.$langs->trans("Total").'</th>';
		print '</tr>';

		// Zone + Meal row
		print '<tr class="liste_titre">';
		print '<td></td>';
		foreach ($days as $d) {
			$date = $weekdates[$d];
			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat">';
			$selZone = isset($perdayZone[$date]) ? (int)$perdayZone[$date] : 0;
			for ($z=0; $z<=5; $z++) {
				print '<option value="'.$z.'"'.($selZone==$z?' selected':'').'>'.$z.'</option>';
			}
			print '</select><br>';
			$checked = !empty($perdayMeal[$date]) ? ' checked' : '';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.$checked.'> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td>';
		print '</tr>';

		// Rows per project + tasks
		$colspan = 2 + count($days); // project row colspans
		foreach ($byProject as $pid => $pdata) {
			// Project link
			if (!isset($cacheProject[$pid])) {
				$prj = new Project($db);
				if ($prj->fetch($pid) > 0) $cacheProject[$pid] = $prj;
			}
			$projLabel = isset($cacheProject[$pid]) ? $cacheProject[$pid]->getNomUrl(1) : dol_escape_htmltag($pdata['ref'].' - '.$pdata['title']);

			print '<tr class="oddeven trforbreak nobold">';
			print '<td colspan="'.$colspan.'">'.$projLabel.'</td>';
			print '</tr>';

			// Tasks
			if (!empty($pdata['tasks'])) {
				foreach ($pdata['tasks'] as $t) {
					$taskid = (int)$t['task_id'];
					if (!isset($cacheTask[$taskid])) {
						$tk = new Task($db);
						if ($tk->fetch($taskid) > 0) $cacheTask[$taskid] = $tk;
					}
					print '<tr>';
					print '<td class="paddingleft">';
					print isset($cacheTask[$taskid]) ? $cacheTask[$taskid]->getNomUrl(1) : dol_escape_htmltag($t['task_label']);
					print '</td>';

					$rowTotal = 0.0;
					foreach ($days as $d) {
						$date = $weekdates[$d];
						$val = isset($mapHours[$taskid][$date]) ? formatHours($mapHours[$taskid][$date]) : '';
						print '<td class="center">';
						if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
							print '<input type="text" class="flat hourinput" size="4" name="hours_'.$taskid.'_'.$d.'" value="'.dol_escape_htmltag($val).'" placeholder="0:00">';
						} else {
							print ($val !== '' ? $val : '&nbsp;');
						}
						print '</td>';
						if ($val !== '') {
							// parse val HH:MM
							if (strpos($val, ':') !== false) {
								list($hh, $mm) = array_pad(explode(':',$val), 2, '0');
								$rowTotal += (int)$hh + ((int)$mm)/60.0;
							} else {
								$rowTotal += (float)$val;
							}
						}
					}
					print '<td class="right task-total">'.formatHours($rowTotal).'</td>';
					print '</tr>';
				}
			}
		}

		// Totals
		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Total").'</td>';
		foreach ($days as $d) print '<td class="right day-total">00:00</td>';
		print '<td class="right grand-total">'.formatHours((float)$object->total_hours).'</td>';
		print '</tr>';

		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Meals").'</td>';
		print '<td colspan="'.count($days).'" class="right meal-total">'.(int)array_sum($perdayMeal ?: array()).'</td>';
		print '<td></td>';
		print '</tr>';

		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Overtime").' (>'.formatHours($contractedHours).')</td>';
		print '<td colspan="'.count($days).'" class="right overtime-total">'.formatHours((float)$object->overtime_hours).'</td>';
		print '<td></td>';
		print '</tr>';

		print '</table>';
		print '</div>'; // responsive

		// Save button only in draft + allowed
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		}
		print '</form>';

		// ---- Actions buttons ----
		print '<div class="tabsAction">';
		// Submit (only draft, only for allowed)
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canCreateFor($user, $object->fk_user, $permCreate, $permCreateChild, $permCreateAll)) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit&token='.newToken().'">'.$langs->trans("Submit").'</a>';
		}
		// Back to draft
		if ($object->status == TimesheetWeek::STATUS_SUBMITTED || $object->status == TimesheetWeek::STATUS_APPROVED || $object->status == TimesheetWeek::STATUS_REFUSED) {
			$ok = false;
			if ($user->id == $object->fk_user) $ok = true;
			if ($object->fk_user_valid > 0 && $user->id == $object->fk_user_valid) $ok = true;
			if (!$ok) $ok = canValidateFor($user, $object->fk_user, $permValidate, $permValidateChild, $permValidateAll);
			if ($ok) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backtodraft&token='.newToken().'">'.$langs->trans("SetToDraft").'</a>';
			}
		}
		// Approve / Refuse (only submitted)
		if ($object->status == TimesheetWeek::STATUS_SUBMITTED) {
			$ok = false;
			if ($object->fk_user_valid > 0 && $user->id == $object->fk_user_valid) $ok = true;
			if (!$ok) $ok = canValidateFor($user, $object->fk_user, $permValidate, $permValidateChild, $permValidateAll);
			if ($ok) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=approve&token='.newToken().'">'.$langs->trans("Approve").'</a>';
				print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refuse&token='.newToken().'">'.$langs->trans("Refuse").'</a>';
			}
		}
		// Delete (only draft)
		if ($object->status == TimesheetWeek::STATUS_DRAFT && canDeleteFor($user, $object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll)) {
			print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
		}
		print '</div>';

		// JS helpers
		print getWeekRangeHelperJS();
		print getGridTotalsJS((float)$contractedHours);
	}
}

llxFooter();
$db->close();


// ----------------- Small JS helpers -----------------

/**
 * Show week range (du..au)
 */
function getWeekRangeHelperJS() {
	return <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val) {
		var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;
	}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){if($('#weekyear').length){updateWeekRange();$('#weekyear').on('change',updateWeekRange);}});
})(jQuery);
</script>
JS;
}

/**
 * Totals computation for grid
 */
function getGridTotalsJS($weeklyContract) {
	$weeklyContract = (float) $weeklyContract;
	return '<script>
(function($){
	function parseHours(v){if(!v)return 0;if(v.indexOf(":")==-1)return parseFloat(v)||0;var p=v.split(":");var h=parseInt(p[0],10)||0;var m=parseInt(p[1],10)||0;return h+(m/60);}
	function formatHours(d){if(isNaN(d))return "00:00";var h=Math.floor(d);var m=Math.round((d-h)*60);if(m===60){h++;m=0;}return String(h).padStart(2,"0")+":"+String(m).padStart(2,"0");}
	function updateTotals(){
		var grand=0;
		$(".task-total").text("00:00");
		$(".day-total").text("00:00");

		$("table .hourinput").each(function(){
			var v = parseHours($(this).val());
			if(!isNaN(v) && v>0){
				var td=$(this).closest("td");
				var idx=td.index(); // index in row
				var cell=$("tr.liste_total:first td").eq(idx);
				var cur=parseHours(cell.text());
				cell.text(formatHours(cur+v));
				grand+=v;
			}
		});
		$(".grand-total").text(formatHours(grand));
		var meals = $(".mealbox:checked").length;
		$(".meal-total").text(meals);
		var ot = grand - '.$weeklyContract.';
		if(ot<0) ot=0;
		$(".overtime-total").text(formatHours(ot));
	}
	$(function(){ updateTotals(); $(document).on("input change","input.hourinput, input.mealbox, select[name^=zone_]", updateTotals); });
})(jQuery);
</script>';
}
