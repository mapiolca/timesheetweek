<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 *
 * GPL v3+
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

global $db, $conf, $user;

// Params
$id      = GETPOSTINT('id');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Init
$object = new TimesheetWeek($db);
$hookmanager->initHooks(array('timesheetweekcard', 'globalcard'));

// Fetch object (id/ref)
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';
if (!empty($object->id)) {
	$id = $object->id;
}

// --- Permissions (module) ---
$permRead          = $user->hasRight('timesheetweek', 'timesheetweek', 'read');
$permReadAll       = $user->hasRight('timesheetweek', 'timesheetweek', 'readAll');
$permReadChild     = $user->hasRight('timesheetweek', 'timesheetweek', 'readChild');

$permWrite         = $user->hasRight('timesheetweek', 'timesheetweek', 'write');
$permWriteChild    = $user->hasRight('timesheetweek', 'timesheetweek', 'writeChild');
$permWriteAll      = $user->hasRight('timesheetweek', 'timesheetweek', 'writeAll');

$permValidate      = $user->hasRight('timesheetweek', 'timesheetweek', 'validate');
$permValidateChild = $user->hasRight('timesheetweek', 'timesheetweek', 'validateChild');
$permValidateAll   = $user->hasRight('timesheetweek', 'timesheetweek', 'validateAll');
$permValidateOwn   = $user->hasRight('timesheetweek', 'timesheetweek', 'validateOwn');

$permDelete        = $user->hasRight('timesheetweek', 'timesheetweek', 'delete');
$permDeleteChild   = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek', 'timesheetweek', 'deleteAll');

// Helpers permissions
function twIsOwnerOrChildOrAll($user, $obj, $write=false) {
	if (!$obj->id) return false;
	$fkuser = (int) $obj->fk_user;
	if ($fkuser == $user->id) return true;
	$childids = $user->getAllChildIds(1);
	if (in_array($fkuser, $childids)) {
		return $write ? $user->hasRight('timesheetweek','timesheetweek','writeChild') : $user->hasRight('timesheetweek','timesheetweek','readChild');
	}
	return $write ? $user->hasRight('timesheetweek','timesheetweek','writeAll') : $user->hasRight('timesheetweek','timesheetweek','readAll');
}
function twCanWriteHeader($user, $obj) {
	if ($obj->status != TimesheetWeek::STATUS_DRAFT) return false;
	if ($obj->fk_user == $user->id && $user->hasRight('timesheetweek','timesheetweek','write')) return true;
	$childids = $user->getAllChildIds(1);
	if (in_array((int)$obj->fk_user, $childids) && $user->hasRight('timesheetweek','timesheetweek','writeChild')) return true;
	if ($user->hasRight('timesheetweek','timesheetweek','writeAll')) return true;
	return false;
}
function twCanDelete($user, $obj) {
	if ($obj->fk_user == $user->id && $user->hasRight('timesheetweek','timesheetweek','delete')) return true;
	$childids = $user->getAllChildIds(1);
	if (in_array((int)$obj->fk_user, $childids) && $user->hasRight('timesheetweek','timesheetweek','deleteChild')) return true;
	if ($user->hasRight('timesheetweek','timesheetweek','deleteAll')) return true;
	return false;
}
function twCanValidate($user, $obj) {
	// validateur désigné
	if (!empty($obj->fk_user_valid) && (int) $obj->fk_user_valid === (int) $user->id && $user->hasRight('timesheetweek','timesheetweek','validate')) return true;
	// validateOwn
	if ($obj->fk_user == $user->id && $user->hasRight('timesheetweek','timesheetweek','validateOwn')) return true;
	// validateChild
	$childids = $user->getAllChildIds(1);
	if (in_array((int)$obj->fk_user, $childids) && $user->hasRight('timesheetweek','timesheetweek','validateChild')) return true;
	// validateAll
	if ($user->hasRight('timesheetweek','timesheetweek','validateAll')) return true;
	return false;
}

// ---------- Helpers: numérotation ----------
function twLoadNumberingModule($db, $conf, $moduleclass)
{
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir.'core/modules/timesheetweek/'.$moduleclass.'.php', 0);
		if (is_readable($file)) {
			require_once $file;
			if (class_exists($moduleclass)) {
				try {
					return new $moduleclass($db);
				} catch (Throwable $e) {
					try { return new $moduleclass(); } catch (Throwable $e2) {}
				}
			}
		}
	}
	return null;
}
function twGenerateFinalRef($db, $conf, $langs, $user, $object)
{
	$moduleclass = getDolGlobalString('TIMESHEETWEEK_MYOBJECT_ADDON', 'mod_timesheetweek_advanced');
	$gen = twLoadNumberingModule($db, $conf, $moduleclass);

	$ref = '';
	if ($gen && method_exists($gen, 'getNextValue')) {
		try {
			$rm = new ReflectionMethod($gen, 'getNextValue');
			$argc = $rm->getNumberOfParameters();
			if ($argc == 0)						$ref = $gen->getNextValue();
			elseif ($argc == 1)					$ref = $gen->getNextValue($object);
			elseif ($argc == 2)					$ref = $gen->getNextValue($user, $object);
			else								$ref = $gen->getNextValue($db, $object, $user);
		} catch (Throwable $e) {
			$ref = '';
		}
	}

	if (empty($ref)) {
		$yyyy = sprintf('%04d', (int) $object->year);
		$ss   = sprintf('%02d', (int) $object->week);
		$ref  = 'FH'.$yyyy.$ss.'-'.$object->id;
	}

	$refbase = $ref;
	$suffix = 1;
	while (1) {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week
			WHERE ref = '".$db->escape($ref)."' AND entity = ".((int) $object->entity);
		$resql = $db->query($sql);
		if ($resql) {
			if ($db->num_rows($resql) == 0) break;
			$db->free($resql);
			$suffix++;
			$ref = $refbase.'-'.$suffix;
		} else {
			break;
		}
	}
	return $ref;
}

// ---------- Actions ----------

// CREATE
if ($action == 'add') {
	if (!$user->hasRight('timesheetweek','timesheetweek','write')) accessforbidden();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$object->ref           = '(PROV)';
	$object->fk_user       = $fk_user > 0 ? $fk_user : $user->id;
	$object->status        = TimesheetWeek::STATUS_DRAFT;
	$object->note          = $note;
	$object->fk_user_valid = $fk_user_valid > 0 ? $fk_user_valid : null;
	$object->entity        = $conf->entity;

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

// UPDATE header fields (inline pencils) - only DRAFT & perm
if ($id > 0 && $object->id) {
	$canEditHeader = twCanWriteHeader($user, $object);

	// Update employee
	if ($action == 'update_employee' && $canEditHeader) {
		if (!checkToken()) accessforbidden('CSRF token not valid');
		$new_user = GETPOSTINT('fk_user');
		$object->fk_user = $new_user > 0 ? $new_user : $object->fk_user;
		$object->update($user);
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	// Update week
	if ($action == 'update_week' && $canEditHeader) {
		if (!checkToken()) accessforbidden('CSRF token not valid');
		$weekyear = GETPOST('weekyear', 'alpha');
		if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
			$object->year = (int) $m[1];
			$object->week = (int) $m[2];
			$object->update($user);
		} else {
			setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		}
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	// Update validator
	if ($action == 'update_validator' && $canEditHeader) {
		if (!checkToken()) accessforbidden('CSRF token not valid');
		$new_valid = GETPOSTINT('fk_user_valid');
		$object->fk_user_valid = $new_valid > 0 ? $new_valid : null;
		$object->update($user);
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	// Update note
	if ($action == 'update_note' && $canEditHeader) {
		if (!checkToken()) accessforbidden('CSRF token not valid');
		$object->note = GETPOST('note', 'restricthtml');
		$object->update($user);
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

// SAVE lines
if ($action == 'save' && $id > 0) {
	if (!twIsOwnerOrChildOrAll($user, $object, true)) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");

	$dto = new DateTime();
	$dto->setISODate($object->year, $object->week);
	$weekdates = array();
	foreach ($days as $d) {
		$weekdates[$d] = $dto->format('Y-m-d');
		$dto->modify('+1 day');
	}

	$grandTotal = 0;

	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/', $key, $m)) {
			$taskid = (int) $m[1];
			$day    = $m[2];
			$daydate = $weekdates[$day];

			$raw = trim($val);
			$hours = 0.0;
			if ($raw !== '') {
				if (strpos($raw, ':') !== false) {
					list($hh, $mm) = array_pad(explode(':', $raw), 2, '0');
					$hours = (int) $hh + (max(0,(int)$mm)/60);
				} else {
					$hours = (float) str_replace(',', '.', $raw);
				}
			}

			$zone = GETPOST('zone_'.$day, 'alpha');
			$meal = GETPOST('meal_'.$day, 'alpha') ? 1 : 0;

			$line = new TimesheetWeekLine($db);
			$exists = $line->fetchByComposite($object->id, $taskid, $daydate) > 0;

			if ($hours > 0) {
				$line->fk_timesheet_week = $object->id;
				$line->fk_task  = $taskid;
				$line->day_date = $daydate;
				$line->hours    = $hours;
				$line->zone     = $zone;
				$line->meal     = $meal;
				if ($exists) $line->update($user);
				else $line->create($user);
				$grandTotal += $hours;
			} else {
				if ($exists) $line->delete($user);
			}
		}
	}

	// Update totals on header
	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contractedHours = (!empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0);
	$overtime = max(0, $grandTotal - $contractedHours);

	$object->total_hours    = $grandTotal;
	$object->overtime_hours = $overtime;
	$object->update($user);

	setEventMessages($langs->trans("Saved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// SUBMIT
if ($action === 'submit' && $id > 0) {
	$canSubmit = twIsOwnerOrChildOrAll($user, $object, true);
	if (!$canSubmit) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	$nbLines = method_exists($object,'countLines') ? $object->countLines() : count($object->getLines());
	if (empty($nbLines)) {
		setEventMessages($langs->trans("NoTimeLinesToSubmit"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->begin();
	if ($object->ref == '(PROV)' || empty($object->ref)) {
		$newref = twGenerateFinalRef($db, $conf, $langs, $user, $object);
		if (empty($newref)) {
			$db->rollback();
			setEventMessages($langs->trans("ErrorCantGenerateRef"), null, 'errors');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		}
		$object->ref = $newref;
		if ($object->update($user) <= 0) {
			$db->rollback();
			setEventMessages($object->error, $object->errors, 'errors');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		}
	}
	$object->status = TimesheetWeek::STATUS_SUBMITTED;
	$object->date_validation = dol_now();
	if ($object->update($user) > 0) {
		$db->commit();
		setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// CONFIRM APPROVE
if ($action == 'confirm_approve' && $confirm == 'yes' && $id > 0) {
	if (!twCanValidate($user, $object)) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	if ((int)$object->fk_user_valid !== (int)$user->id) {
		$object->fk_user_valid = $user->id;
	}

	$object->status = TimesheetWeek::STATUS_APPROVED;
	$object->date_validation = dol_now();

	if ($object->update($user) > 0) {
		setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// CONFIRM REFUSE
if ($action == 'confirm_refuse' && $confirm == 'yes' && $id > 0) {
	if (!twCanValidate($user, $object)) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	if ((int)$object->fk_user_valid !== (int)$user->id) {
		$object->fk_user_valid = $user->id;
	}

	$object->status = TimesheetWeek::STATUS_REFUSED;
	$object->date_validation = dol_now();

	if ($object->update($user) > 0) {
		setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// BACK TO DRAFT
if ($action == 'backtodraft' && $id > 0) {
	if (!twIsOwnerOrChildOrAll($user, $object, true)) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	$object->status = TimesheetWeek::STATUS_DRAFT;
	if ($object->update($user) > 0) {
		setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// DELETE
if ($action == 'confirm_delete' && $confirm == 'yes' && $id > 0) {
	if (!twCanDelete($user, $object)) accessforbidden();
	if (!checkToken()) accessforbidden('CSRF token not valid');

	$resdel = $object->delete($user);
	if ($resdel > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

// ---------- View ----------
$form = new Form($db);

$title = $langs->trans("TimesheetWeek");
$help_url = '';
$morecss = array();
$morejs = array();

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// CREATE MODE
if ($action == 'create') {
	if (!$user->hasRight('timesheetweek','timesheetweek','write')) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal@timesheetweek');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	// Employee
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	print $form->select_dolusers($user->id, 'fk_user', 1);
	print '</td></tr>';
	// Week
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	print getWeekSelectorDolibarr($form, 'weekyear');
	print '<div id="weekrange" class="opacitymedium paddingleft small"></div>';
	print '</td></tr>';
	// Validator default to manager if available
	$defaultValidatorId = !empty($user->fk_user) ? (int) $user->fk_user : 0;
	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	print $form->select_dolusers($defaultValidatorId, 'fk_user_valid', 1);
	print '</td></tr>';
	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	print '<textarea name="note" rows="3" class="quatrevingtpercent"></textarea>';
	print '</td></tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	// JS week range
	print <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val) { var m=/^(\d{4})-W(\d{2})$/.exec(val||''); return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null; }
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s); if(d>=1&&d<=4) st.setUTCDate(s.getUTCDate()-(d-1)); else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d))); return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){ if ($.fn.select2) $('#weekyear').select2({width:'resolve'}); updateWeekRange(); $('#weekyear').on('change',updateWeekRange); });
})(jQuery);
</script>
JS;

} else if ($id > 0 && $object->id) {
	// VIEW MODE

	// Security view
	$cansee = false;
	if ($permReadAll) $cansee = true;
	else if ($permReadChild) {
		if ($object->fk_user == $user->id) $cansee = true;
		else {
			$subs = $user->getAllChildIds(1);
			if (in_array($object->fk_user, $subs)) $cansee = true;
		}
	} else if ($permRead && $object->fk_user == $user->id) $cansee = true;
	if (!$cansee) accessforbidden();

	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

	// Banner
	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', '', '', 0, '', '', '');

	print '<div class="fichecenter">';

	// Left block
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	$canEditHeader = twCanWriteHeader($user, $object);
	$draft = ($object->status == TimesheetWeek::STATUS_DRAFT);

	// Employee
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	$uemp = new User($db);
	$uemp->fetch($object->fk_user);
	if ($canEditHeader) {
		if ($action == 'edit_employee') {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update_employee">';
			print $form->select_dolusers($object->fk_user, 'fk_user', 1);
			print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'"> ';
			print ' <a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
			print '</form>';
		} else {
			print $uemp->getNomUrl(1).' ';
			print '<a class="editlink editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_employee">'.img_edit().'</a>';
		}
	} else {
		print $uemp->getNomUrl(1);
	}
	print '</td></tr>';

	// Week / Year
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($canEditHeader) {
		if ($action == 'edit_week') {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update_week">';
			print getWeekSelectorDolibarr($form, 'weekyear', sprintf('%04d-W%02d', $object->year, $object->week));
			print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'"> ';
			print ' <a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
			print '</form>';
		} else {
			print $langs->trans("Week").' '.(int)$object->week.' - '.(int)$object->year.' ';
			print '<a class="editlink editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_week">'.img_edit().'</a>';
		}
	} else {
		print $langs->trans("Week").' '.(int)$object->week.' - '.(int)$object->year;
	}
	print '</td></tr>';

	// Validator
	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($canEditHeader) {
		if ($action == 'edit_validator') {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update_validator">';
			print $form->select_dolusers((int)$object->fk_user_valid, 'fk_user_valid', 1);
			print ' <input type="submit" class="button small" value="'.$langs->trans("Save").'"> ';
			print ' <a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
			print '</form>';
		} else {
			if ($object->fk_user_valid > 0) {
				$uval = new User($db);
				$uval->fetch($object->fk_user_valid);
				print $uval->getNomUrl(1).' ';
			} else {
				print '<span class="opacitymedium">'.$langs->trans("None").'</span> ';
			}
			print '<a class="editlink editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_validator">'.img_edit().'</a>';
		}
	} else {
		if ($object->fk_user_valid > 0) {
			$uval = new User($db); $uval->fetch($object->fk_user_valid);
			print $uval->getNomUrl(1);
		} else print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
	}
	print '</td></tr>';

	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($canEditHeader) {
		if ($action == 'edit_note') {
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update_note">';
			print '<textarea name="note" rows="3" class="quatrevingtpercent">'.dol_htmlentitiesbr_decode($object->note).'</textarea>';
			print ' <div class="paddingleft"><input type="submit" class="button small" value="'.$langs->trans("Save").'"> ';
			print ' <a class="button button-cancel small" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a></div>';
			print '</form>';
		} else {
			print nl2br(dol_escape_htmltag($object->note)).' ';
			print '<a class="editlink editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_note">'.img_edit().'</a>';
		}
	} else {
		print nl2br(dol_escape_htmltag($object->note));
	}
	print '</td></tr>';

	// Totals in header
	print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.dol_escape_htmltag(sprintf('%0.2f', (float)$object->total_hours)).'</td></tr>';
	print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.dol_escape_htmltag(sprintf('%0.2f', (float)$object->overtime_hours)).'</td></tr>';

	print '</table>';
	print '</div>';

	// Right header
	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '</table>';
	print '</div>';

	print '</div>'; // fichecenter

	print dol_get_fiche_end();
	print '<div class="clearboth"></div>';

	// Lines (grid)
	$tasks = $object->getAssignedTasks($object->fk_user);
	$lines = $object->getLines();

	$hoursByTaskDay = array();
	$zoneByDay = array();
	$mealByDay = array();

	foreach ($lines as $L) {
		$tid = (int) $L->fk_task;
		$dte = $L->day_date;
		$hoursByTaskDay[$tid][$dte] = (float) $L->hours;
		if (!isset($zoneByDay[$dte]) && $L->zone !== '') $zoneByDay[$dte] = $L->zone;
		if (!isset($mealByDay[$dte])) $mealByDay[$dte] = (int) $L->meal;
	}

	$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
	$dto = new DateTime();
	$dto->setISODate($object->year, $object->week);
	$weekdates = array();
	foreach ($days as $d) {
		$weekdates[$d] = $dto->format('Y-m-d');
		$dto->modify('+1 day');
	}

	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contractedHours = (!empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0);

	$nbLines = method_exists($object,'countLines') ? $object->countLines() : count($lines);
	$editableGrid = twIsOwnerOrChildOrAll($user, $object, true) && ($object->status == TimesheetWeek::STATUS_DRAFT);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<div class="div-table-responsive nohover">';
	print '<table class="noborder centpercent">';

	// Header row
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Project / Task").'</th>';
	foreach ($days as $d) {
		print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
	}
	print '<th class="right">'.$langs->trans("Total").'</th>';
	print '</tr>';

	// Zone + Meal row
	print '<tr class="liste_titre">';
	print '<td></td>';
	foreach ($days as $d) {
		$dte = $weekdates[$d];
		$sel = isset($zoneByDay[$dte]) ? $zoneByDay[$dte] : '';
		$meal = !empty($mealByDay[$dte]) ? 1 : 0;
		print '<td class="center">';
		$disabled = (!$editableGrid ? ' disabled' : '');
		print '<select name="zone_'.$d.'" class="flat"'.$disabled.'>';
		print '<option value=""></option>';
		for ($z=1;$z<=5;$z++) {
			print '<option value="'.$z.'"'.($sel==$z?' selected':'').'>'.$z.'</option>';
		}
		print '</select><br>';
		print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($meal?' checked':'').$disabled.'> '.$langs->trans("Meal").'</label>';
		print '</td>';
	}
	print '<td></td>';
	print '</tr>';

	// Group tasks by project
	$byproject = array();
	foreach ($tasks as $t) {
		$pid = (int) $t['project_id'];
		if (empty($byproject[$pid])) {
			$byproject[$pid] = array(
				'ref' => $t['project_ref'],
				'title' => $t['project_title'],
				'project_id' => $pid,
				'tasks' => array()
			);
		}
		$byproject[$pid]['tasks'][] = $t;
	}

	$projectstatic = new Project($db);
	$taskstatic = new Task($db);

	foreach ($byproject as $pid => $pdata) {
		// Project row
		print '<tr class="oddeven trforbreak nobold">';
		print '<td colspan="'.(count($days)+2).'">';
		$projectstatic->id = $pid;
		$projectstatic->ref = $pdata['ref'];
		$projectstatic->title = $pdata['title'];
		print $projectstatic->getNomUrl(1, '', 0, 0, '', 0, 1);
		print '</td>';
		print '</tr>';

		// Task rows
		foreach ($pdata['tasks'] as $t) {
			$tid = (int) $t['task_id'];
			print '<tr>';
			print '<td class="paddingleft">';
			$taskstatic->id = $tid;
			$taskstatic->label = $t['task_label'];
			print $taskstatic->getNomUrl(1);
			print '</td>';
			foreach ($days as $d) {
				$dte = $weekdates[$d];
				$val = isset($hoursByTaskDay[$tid][$dte]) ? $hoursByTaskDay[$tid][$dte] : 0.0;
				$valText = ($val > 0 ? sprintf('%02d:%02d', floor($val), round(($val - floor($val)) * 60)) : '');
				$dis = $editableGrid ? '' : ' disabled';
				print '<td class="center">';
				print '<input type="text" class="flat hourinput" size="4" name="hours_'.$tid.'_'.$d.'" placeholder="0:00" value="'.dol_escape_htmltag($valText).'"'.$dis.'>';
				print '</td>';
			}
			print '<td class="right task-total">00:00</td>';
			print '</tr>';
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
	print '<td class="right">'.$langs->trans("Overtime").' (>'.dol_escape_htmltag(sprintf('%02d:%02d', floor($contractedHours), round(($contractedHours - floor($contractedHours))*60))).')</td>';
	print '<td colspan="'.count($days).'" class="right overtime-total">00:00</td>';
	print '<td></td>';
	print '</tr>';

	print '</table>';
	print '</div>';

	// Save button (only editable)
	if ($editableGrid) {
		print '<div class="center margintoponly"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
	}
	print '</form>';

	// Action buttons
	print '<div class="tabsAction">';

	// Submit (only if at least one line)
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $nbLines > 0 && twIsOwnerOrChildOrAll($user, $object, true)) {
		print '<form class="inline-block" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="submit">';
		print '<button class="butAction" type="submit">'.$langs->trans("Submit").'</button>';
		print '</form>';
	} elseif ($object->status == TimesheetWeek::STATUS_DRAFT && $nbLines == 0) {
		print '<a class="butActionRefused" href="#" title="'.$langs->trans("NoTimeLinesToSubmit").'">'.$langs->trans("Submit").'</a>';
	}

	// Back to draft (visible on submitted/approved/refused if writer)
	if (twIsOwnerOrChildOrAll($user, $object, true) && $object->status != TimesheetWeek::STATUS_DRAFT) {
		print '<form class="inline-block" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="backtodraft">';
		print '<button class="butAction" type="submit">'.$langs->trans("SetToDraft").'</button>';
		print '</form>';
	}

	// Approve / Refuse (popup confirm) when submitted
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED && twCanValidate($user, $object)) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_approve">'.$langs->trans("Approve").'</a>';
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse">'.$langs->trans("Refuse").'</a>';
	}

	// Delete (also visible on approved/refused if deleter)
	if (twCanDelete($user, $object)) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete">'.$langs->trans("Delete").'</a>';
	}

	print '</div>';

	// Confirm popups with token
	if ($action == 'delete') {
		$formq = array(
			array('type'=>'hidden','name'=>'token','value'=>newToken()),
			array('type'=>'hidden','name'=>'confirm','value'=>'yes')
		);
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Delete"), $langs->trans("ConfirmDelete"), 'confirm_delete', $formq, 0, 1);
	}
	if ($action == 'ask_approve') {
		$formq = array(
			array('type'=>'hidden','name'=>'token','value'=>newToken()),
			array('type'=>'hidden','name'=>'confirm','value'=>'yes')
		);
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Approve"), $langs->trans("ConfirmApprove"), 'confirm_approve', $formq, 0, 1);
	}
	if ($action == 'ask_refuse') {
		$formq = array(
			array('type'=>'hidden','name'=>'token','value'=>newToken()),
			array('type'=>'hidden','name'=>'confirm','value'=>'yes')
		);
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Refuse"), $langs->trans("ConfirmRefuse"), 'confirm_refuse', $formq, 0, 1);
	}

	// JS totals + disable zone/meal if submitted
	$disableZoneMeal = ($object->status == TimesheetWeek::STATUS_SUBMITTED);
	print "<script>
(function($){
	function parseHours(v){ if(!v) return 0; if(v.indexOf(':')==-1) return parseFloat(v)||0; var p=v.split(':'), h=parseInt(p[0],10)||0, m=parseInt(p[1],10)||0; return h+(m/60); }
	function formatHours(d){ if(isNaN(d)) return '00:00'; var h=Math.floor(d); var m=Math.round((d-h)*60); if(m===60){h++;m=0;} return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0'); }
	function updateTotals(){
		var grand=0;
		$('.task-total').text('00:00');
		$('.day-total').text('00:00');

		$('table .hourinput').each(function(){
			var v=parseHours($(this).val());
			if(isNaN(v)||v<=0) return;
			grand += v;
			var tr=$(this).closest('tr');
			var cur=parseHours(tr.find('.task-total').text());
			tr.find('.task-total').text(formatHours(cur+v));
			var td=$(this).closest('td'), idx=td.index();
			var daycell=$('tr.liste_total:first td').eq(idx);
			var curd=parseHours(daycell.text());
			daycell.text(formatHours(curd+v));
		});
		$('.grand-total').text(formatHours(grand));
		$('.meal-total').text($('.mealbox:checked').length);
		var weekly=".$contractedHours.";
		var ot=Math.max(0, grand - weekly);
		$('.overtime-total').text(formatHours(ot));
	}
	$(function(){
		".($disableZoneMeal ? "$('select[name^=zone_], input[name^=meal_]').prop('disabled', true);" : "")."
		updateTotals();
		$(document).on('input change','input.hourinput, input.mealbox, select[name^=zone_]',updateTotals);
	});
})(jQuery);
</script>";

} else {
	// Not found
	print '<div class="error">'.$langs->trans("ErrorRecordNotFound").'</div>';
}

llxFooter();
$db->close();
