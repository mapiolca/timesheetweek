<?php
/* Copyright (C)
 * 2025 - Pierre ARDOIN
 *
 * GPLv3
 */

/**
 * \file       timesheetweek_card.php
 * \ingroup    timesheetweek
 * \brief      Page to create/edit/view a weekly timesheet
 */

// ---- Bootstrap Dolibarr env (robuste pour /custom) ----
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = include "../../main.inc.php";
if (!$res) die("Include of main fails");

// ---- Requires ----
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr(), formatHours(), ...

$langs->loadLangs(array('timesheetweek@timesheetweek','projects','users','other'));

// ---- Params ----
$id      = GETPOSTINT('id');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// ---- Init ----
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// ---- Fetch (set $object if id) ----
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// ---- SHIM STATUTS (mappe vers les constantes de la classe, avec fallback) ----
function tw_status($name) {
        static $map = null;
        if ($map === null) {
		$approved = null;
		if (defined('TimesheetWeek::STATUS_APPROVED')) {
			$approved = TimesheetWeek::STATUS_APPROVED;
		} elseif (defined('TimesheetWeek::STATUS_VALIDATED')) {
			$approved = TimesheetWeek::STATUS_VALIDATED;
		} else {
			$approved = 2;
		}
		$map = array(
			'draft'     => defined('TimesheetWeek::STATUS_DRAFT')     ? TimesheetWeek::STATUS_DRAFT     : 0,
			'submitted' => defined('TimesheetWeek::STATUS_SUBMITTED') ? TimesheetWeek::STATUS_SUBMITTED : 1,
			'approved'  => $approved, // <— Approuvée
			'refused'   => defined('TimesheetWeek::STATUS_REFUSED')   ? TimesheetWeek::STATUS_REFUSED   : 3,
		);
        }
        return $map[$name];
}

function tw_translate_error($errorKey, $langs)
{
        if (empty($errorKey)) {
                return $langs->trans("Error");
        }
        $msg = $langs->trans($errorKey);
        if ($msg === $errorKey) {
                $msg = $langs->trans("Error").' ('.dol_escape_htmltag($errorKey).')';
        }
        return $msg;
}

// ---- Permissions (nouveau modèle) ----
$permRead          = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild     = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll       = $user->hasRight('timesheetweek','timesheetweek','readAll');

$permWrite         = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild    = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll      = $user->hasRight('timesheetweek','timesheetweek','writeAll');

$permValidate      = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateOwn   = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll   = $user->hasRight('timesheetweek','timesheetweek','validateAll');

$permDelete        = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild   = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek','timesheetweek','deleteAll');

$permReadAny   = ($permRead || $permReadChild || $permReadAll);
$permWriteAny  = ($permWrite || $permWriteChild || $permWriteAll);
$permDeleteAny = ($permDelete || $permDeleteChild || $permDeleteAll);

/** helpers permissions **/
function tw_can_act_on_user($userid, $own, $child, $all, User $user) {
	if ($all) return true;
	if ($own && ((int)$userid === (int)$user->id)) return true;
	if ($child) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$userid, $subs, true)) return true;
	}
	return false;
}
function tw_is_manager_of($userid, User $user) {
	$subs = $user->getAllChildIds(1);
	return (is_array($subs) && in_array((int)$userid, $subs, true));
}
function tw_can_validate_timesheet(
        TimesheetWeek $o,
        User $user,
        $permValidate,
        $permValidateOwn,
        $permValidateChild,
        $permValidateAll,
        $permWrite = false,
        $permWriteChild = false,
        $permWriteAll = false
) {
        $rightsNode = null;
        if (isset($user->rights->timesheetweek) && isset($user->rights->timesheetweek->timesheetweek)) {
                $rightsNode = $user->rights->timesheetweek->timesheetweek;
        }

        $validateRightsDefined = (
                is_object($rightsNode)
                && (property_exists($rightsNode, 'validate')
                        || property_exists($rightsNode, 'validateOwn')
                        || property_exists($rightsNode, 'validateChild')
                        || property_exists($rightsNode, 'validateAll'))
        );

        if (!$validateRightsDefined) {
                // Fall back to legacy behaviour where validation relied on write permissions
                if (!$permValidateAll && ($permWriteAll || !empty($user->admin))) {
                        $permValidateAll = true;
                }
                if (!$permValidateChild && $permWriteChild) {
                        $permValidateChild = true;
                }
                if (!$permValidate && (
                        $permValidateAll
                        || $permValidateChild
                        || ((int)$o->fk_user_valid === (int)$user->id && ($permWrite || $permWriteChild || $permWriteAll))
                )) {
                        $permValidate = true;
                }
        } else {
                if (!empty($user->admin)) {
                        $permValidateAll = true;
                }
        }

        if ($permValidateAll) return true;
        if ($permValidateChild && tw_is_manager_of($o->fk_user, $user)) return true;
        if ($permValidateOwn && ((int)$user->id === (int)$o->fk_user)) return true;
        if ($permValidate && ((int)$user->id === (int)$o->fk_user_valid)) return true;
        return false;
}

// Sécurise l'objet si présent
if (!empty($id) && $object->id <= 0) $object->fetch($id);

// ----------------- Inline edits (crayons) -----------------
if ($action === 'setfk_user' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOSTINT('fk_user');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	if ($newval > 0) {
		$object->fk_user = $newval;
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	}
	$action = '';
}

if ($action === 'setvalidator' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOSTINT('fk_user_valid');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->fk_user_valid = ($newval > 0 ? $newval : null);
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setnote' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOST('note', 'restricthtml');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->note = $newval;
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setweekyear' && $object->id > 0 && $object->status == tw_status('draft')) {
	$weekyear = GETPOST('weekyear', 'alpha');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int) $m[1];
		$object->week = (int) $m[2];
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
	}
	$action = '';
}

// ----------------- Action: Create (add) -----------------
if ($action === 'add') {
	if (!$permWriteAny) accessforbidden();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$targetUserId = $fk_user > 0 ? $fk_user : $user->id;
	if (!tw_can_act_on_user($targetUserId, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$object->ref     = '(PROV)';
	$object->fk_user = $targetUserId;
	$object->status  = tw_status('draft');
	$object->note    = $note;

	// Validateur par défaut = manager du salarié cible si non fourni
	if ($fk_user_valid > 0) {
		$object->fk_user_valid = $fk_user_valid;
	} else {
		$uTmp = new User($db);
		$uTmp->fetch($targetUserId);
		$object->fk_user_valid = !empty($uTmp->fk_user) ? (int)$uTmp->fk_user : null;
	}

	// Parse semaine
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

// ----------------- Action: Save grid lines (UPSERT SQL direct) -----------------
if ($action === 'save' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status != tw_status('draft')) {
		setEventMessages($langs->trans("TimesheetIsNotEditable"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$db->begin();

	$map = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);
	$processed = 0;

	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(\w+)$/', $key, $m)) {
			$taskid   = (int) $m[1];
			$day      = $m[2];
			$hoursStr = trim((string) $val);

			$h = 0.0;
			if ($hoursStr !== '') {
				if (strpos($hoursStr, ':') !== false) {
					$tmp = explode(':', $hoursStr, 2);
					$H = (int) ($tmp[0] ?? 0);
					$M = (int) ($tmp[1] ?? 0);
					$h = $H + ($M/60.0);
				} else {
					$h = (float) str_replace(',', '.', $hoursStr);
				}
			}

			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week);
			$dto->modify('+'.$map[$day].' day');
			$datestr = $dto->format('Y-m-d');

			$zone = (int) GETPOST('zone_'.$day, 'int');
			$meal = GETPOST('meal_'.$day) ? 1 : 0;

			$sqlSel = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line 
				WHERE fk_timesheet_week=".(int)$object->id." AND fk_task=".(int)$taskid." AND day_date='".$db->escape($datestr)."'";
			$resSel = $db->query($sqlSel);
			$existingId = 0;
			if ($resSel && $db->num_rows($resSel) > 0) {
				$o = $db->fetch_object($resSel);
				$existingId = (int) $o->rowid;
			}

			if ($h > 0 && $taskid > 0) {
				if ($existingId > 0) {
					$sqlUpd = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line 
						SET hours=".((float)$h).", zone=".(int)$zone.", meal=".(int)$meal."
						WHERE rowid=".$existingId;
					if (!$db->query($sqlUpd)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				} else {
					$sqlIns = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line (fk_timesheet_week, fk_task, day_date, hours, zone, meal) VALUES (".
						(int)$object->id.", ".
						(int)$taskid.", ".
						"'".$db->escape($datestr)."', ".
						((float)$h).", ".
						(int)$zone.", ".
						(int)$meal.
					")";
					if (!$db->query($sqlIns)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				}
				$processed++;
			} else {
				if ($existingId > 0) {
					$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE rowid=".$existingId);
					$processed++;
				}
			}
		}
	}

	// Totaux
	$totalHours = 0.0;
	$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
	$resSum = $db->query($sqlSum);
	if ($resSum) {
		$o = $db->fetch_object($resSum);
		$totalHours = (float) $o->sh;
	}

	$uEmp = new User($db);
	$uEmp->fetch($object->fk_user);
	$contract = !empty($uEmp->weeklyhours) ? (float)$uEmp->weeklyhours : 35.0;
	$overtime = max(0.0, $totalHours - $contract);

	$object->total_hours    = $totalHours;
	$object->overtime_hours = $overtime;
	$upd = $object->update($user);
	if ($upd < 0) {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved").' ('.$processed.' '.$langs->trans("Lines").')', null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Submit -----------------
if ($action === 'submit' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status != tw_status('draft')) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$totalHours = 0.0;
	$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
	$resSum = $db->query($sqlSum);
	if ($resSum) {
		$o = $db->fetch_object($resSum);
		$totalHours = (float) $o->sh;
	}
	if ($totalHours <= 0) {
		setEventMessages($langs->trans("NoHoursToSubmit"), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

        $res = $object->submit($user);
        if ($res > 0) {
                setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
        } else {
                $errmsg = tw_translate_error($object->error, $langs);
                setEventMessages($errmsg, $object->errors, 'errors');
        }
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
        exit;
}

// ----------------- Action: Back to draft -----------------
if ($action === 'setdraft' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status == tw_status('draft')) {
		setEventMessages($langs->trans("AlreadyDraft"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$canEmployee  = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
        $canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
	if (!$canEmployee && !$canValidator) accessforbidden();

        $res = $object->revertToDraft($user);
        if ($res > 0) {
                setEventMessages($langs->trans("StatusSetToDraft"), null, 'mesgs');
        } else {
                $errmsg = tw_translate_error($object->error, $langs);
                setEventMessages($errmsg, $object->errors, 'errors');
        }
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
        exit;
}

// ----------------- Action: ASK APPROVE / REFUSE (confirm popups) -----------------
if ($action === 'ask_validate' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) accessforbidden();
        if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) accessforbidden();
}
if ($action === 'ask_refuse' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) accessforbidden();
        if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) accessforbidden();
}

// ----------------- Action: CONFIRM APPROVE (Approuver) -----------------
if ($action === 'confirm_validate' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
        if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
		accessforbidden();
	}

        $res = $object->approve($user);
        if ($res > 0) {
                setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
        } else {
                $errmsg = tw_translate_error($object->error, $langs);
                setEventMessages($errmsg, $object->errors, 'errors');
        }
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
        exit;
}

// ----------------- Action: CONFIRM REFUSE (Refuser) -----------------
if ($action === 'confirm_refuse' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
        if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
		accessforbidden();
	}

        $res = $object->refuse($user);
        if ($res > 0) {
                setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
        } else {
                $errmsg = tw_translate_error($object->error, $langs);
                setEventMessages($errmsg, $object->errors, 'errors');
        }
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
        exit;
}

// ----------------- Action: Delete -----------------
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	// On autorise la suppression si l'utilisateur a les droits (own/child/all),
	// ou s'il a des droits validate* (validateur), quelque soit le statut
        $canDelete = tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)
                || tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);

	if (!$canDelete) accessforbidden();

	$res = $object->delete($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php',1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// ----------------- View -----------------
$form = new Form($db);
$title = $langs->trans("TimesheetWeek");
llxHeader('', $title);

// ---- CREATE MODE ----
if ($action === 'create') {
	if (!$permWriteAny) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal');

	echo '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	echo '<input type="hidden" name="token" value="'.newToken().'">';
	echo '<input type="hidden" name="action" value="add">';

	echo '<table class="border centpercent">';

	// Employé
	echo '<tr>';
	echo '<td class="titlefield">'.$langs->trans("Employee").'</td>';
	echo '<td>'.$form->select_dolusers($user->id, 'fk_user', 1).'</td>';
	echo '</tr>';

	// Semaine
	echo '<tr>';
	echo '<td>'.$langs->trans("Week").'</td>';
	echo '<td>'.getWeekSelectorDolibarr($form, 'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td>';
	echo '</tr>';

	// Validateur (défaut = manager)
	$defaultValidatorId = !empty($user->fk_user) ? (int)$user->fk_user : 0;
	echo '<tr>';
	echo '<td>'.$langs->trans("Validator").'</td>';
	echo '<td>'.$form->select_dolusers($defaultValidatorId, 'fk_user_valid', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200').'</td>';
	echo '</tr>';

	// Note
	echo '<tr>';
	echo '<td>'.$langs->trans("Note").'</td>';
	echo '<td><textarea name="note" class="quatrevingtpercent" rows="3"></textarea></td>';
	echo '</tr>';

	echo '</table>';

	echo '<div class="center">';
	echo '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	echo '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	echo '</div>';

	echo '</form>';

	$jsWeek = <<<'JS'
<script>
(function ($) {
	function parseYearWeek(val) {
		var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;
	}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){if($.fn.select2)$('#weekyear').select2({width:'resolve'});updateWeekRange();$('#weekyear').on('change',updateWeekRange);});
})(jQuery);
</script>
JS;
	echo $jsWeek;

} else if ($id > 0) {
	// ---- READ MODE (fiche + grille) ----
	if (!tw_can_act_on_user($object->fk_user, $permRead, $permReadChild, $permReadAll, $user)) {
		accessforbidden();
	}

	// Head + banner
	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'ref', $linkback);

	// Confirm modals
	if ($action === 'delete') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}
	if ($action === 'ask_validate') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'),
			$langs->trans('ConfirmValidate'),
			'confirm_validate',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}
	if ($action === 'ask_refuse') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("Refuse"),
			$langs->trans('ConfirmRefuse'),
			'confirm_refuse',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}

	echo '<div class="fichecenter">';

	$canEditInline = ($object->status == tw_status('draft') && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user));

	// Left block
	echo '<div class="fichehalfleft">';
	echo '<table class="border centpercent tableforfield">';

	// Employé
	echo '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	if ($action === 'editfk_user' && $canEditInline) {
		echo '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setfk_user">';
		echo $form->select_dolusers($object->fk_user, 'fk_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		if ($object->fk_user > 0) {
			$u = new User($db); $u->fetch($object->fk_user);
			echo $u->getNomUrl(1);
		}
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editfk_user" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Semaine
	echo '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($action === 'editweekyear' && $canEditInline) {
		$prefill = sprintf("%04d-W%02d", (int)$object->year, (int)$object->week);
		echo '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setweekyear">';
		echo getWeekSelectorDolibarr($form, 'weekyear', $prefill);
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		echo dol_escape_htmltag($object->week).' / '.dol_escape_htmltag($object->year);
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editweekyear" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Note
	echo '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($action === 'editnote' && $canEditInline) {
		echo '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setnote">';
		echo '<textarea name="note" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag($object->note).'</textarea>';
		echo '<br><input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		echo nl2br(dol_escape_htmltag($object->note));
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editnote" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Validator
	echo '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($action === 'editvalidator' && $canEditInline) {
		echo '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setvalidator">';
		echo $form->select_dolusers($object->fk_user_valid, 'fk_user_valid', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		if ($object->fk_user_valid > 0) {
			$v = new User($db); $v->fetch($object->fk_user_valid);
			echo $v->getNomUrl(1);
		}
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editvalidator" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	echo '</table>';
	echo '</div>';

	// Right block (Totaux en entête)
	$uEmpDisp = new User($db);
	$uEmpDisp->fetch($object->fk_user);
	$contractedHoursDisp = (!empty($uEmpDisp->weeklyhours)?(float)$uEmpDisp->weeklyhours:35.0);
	$th = (float) $object->total_hours;
	$ot = (float) $object->overtime_hours;
	if ($th <= 0) {
		$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
		$resSum = $db->query($sqlSum);
		if ($resSum) {
			$o = $db->fetch_object($resSum);
			$th = (float) $o->sh;
			$ot = max(0.0, $th - $contractedHoursDisp);
		}
	}
	echo '<div class="fichehalfright">';
	echo '<table class="border centpercent tableforfield">';
	echo '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	echo '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	echo '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	echo '<tr><td>'.$langs->trans("TotalHours").'</td><td><span class="header-total-hours">'.formatHours($th).'</span></td></tr>';
	echo '<tr><td>'.$langs->trans("Overtime").' ('.formatHours($contractedHoursDisp).')</td><td><span class="header-overtime">'.formatHours($ot).'</span></td></tr>';
	echo '</table>';
	echo '</div>';

	echo '</div>'; // fichecenter

	// place correctement la grille
	echo '<div class="clearboth"></div>';

	// Clôt la fiche AVANT la grille
	print dol_get_fiche_end();

	// ------- GRID (Assigned Tasks grouped by Project) -------
	echo '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	echo '<input type="hidden" name="token" value="'.newToken().'">';
	echo '<input type="hidden" name="action" value="save">';

	echo '<h3>'.$langs->trans("AssignedTasks").'</h3>';

	// 1) CHARGER LIGNES EXISTANTES
	$hoursBy = array(); // [taskid][YYYY-mm-dd] = hours
	$dayMeal = array('Monday'=>0,'Tuesday'=>0,'Wednesday'=>0,'Thursday'=>0,'Friday'=>0,'Saturday'=>0,'Sunday'=>0);
	$dayZone = array('Monday'=>null,'Tuesday'=>null,'Wednesday'=>null,'Thursday'=>null,'Friday'=>null,'Saturday'=>null,'Sunday'=>null);
	$taskIdsFromLines = array();
	$linesCount = 0;

	$sqlLines = "SELECT fk_task, day_date, hours, zone, meal 
		FROM ".MAIN_DB_PREFIX."timesheet_week_line
		WHERE fk_timesheet_week=".(int)$object->id;
	$resLines = $db->query($sqlLines);
	if ($resLines) {
		while ($o = $db->fetch_object($resLines)) {
			$linesCount++;
			$fk_task = (int)$o->fk_task;
			$daydate = $o->day_date;
			$hours   = (float)$o->hours;
			$zone    = isset($o->zone) ? (int)$o->zone : null;
			$meal    = (int)$o->meal;

			if (!isset($hoursBy[$fk_task])) $hoursBy[$fk_task] = array();
			$hoursBy[$fk_task][$daydate] = $hours;

			$w = (int) date('N', strtotime($daydate));
			$dayName = array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday')[$w];

			if ($meal) $dayMeal[$dayName] = 1;
			if ($zone !== null) $dayZone[$dayName] = $zone;

			$taskIdsFromLines[$fk_task] = 1;
		}
	}

	// 2) RÉCUPÉRER LES TÂCHES ASSIGNÉES
	$tasks = $object->getAssignedTasks($object->fk_user); // id, label, project_id, project_ref, project_title, task_ref?
	$tasksById = array();
	if (!empty($tasks)) {
		foreach ($tasks as $t) {
			$tasksById[(int)$t['task_id']] = $t;
		}
	}

	// 3) COMPLÉTER AVEC TÂCHES PRÉSENTES DANS LES LIGNES MAIS PAS DANS LES ASSIGNATIONS
	if (!empty($taskIdsFromLines)) {
		$missing = array();
		foreach (array_keys($taskIdsFromLines) as $tid) {
			if (!isset($tasksById[$tid])) $missing[] = (int)$tid;
		}
		if (!empty($missing)) {
			$sqlMiss = "SELECT t.rowid as task_id, t.label as task_label, t.ref as task_ref,
							p.rowid as project_id, p.ref as project_ref, p.title as project_title
						FROM ".MAIN_DB_PREFIX."projet_task t
						INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet
						WHERE t.rowid IN (".implode(',', array_map('intval',$missing)).")";
			$resMiss = $db->query($sqlMiss);
			if ($resMiss) {
				while ($o = $db->fetch_object($resMiss)) {
					$tasks[] = array(
						'task_id'       => (int)$o->task_id,
						'task_label'    => $o->task_label,
						'task_ref'      => $o->task_ref,
						'project_id'    => (int)$o->project_id,
						'project_ref'   => $o->project_ref,
						'project_title' => $o->project_title
					);
				}
			}
		}
	}

	// 4) AFFICHAGE
	if (empty($tasks)) {
		echo '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
	} else {
		$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
		$dto = new DateTime();
		$dto->setISODate((int)$object->year, (int)$object->week);
		$weekdates = array();
		foreach ($days as $d) {
			$weekdates[$d] = $dto->format('Y-m-d');
			$dto->modify('+1 day');
		}

		// Heures contractuelles
		$userEmployee=new User($db); $userEmployee->fetch($object->fk_user);
		$contractedHours = (!empty($userEmployee->weeklyhours)?(float)$userEmployee->weeklyhours:35.0);

		// Inputs zone/panier bloqués si statut != brouillon
		$disabledAttr = ($object->status != tw_status('draft')) ? ' disabled' : '';

		echo '<div class="div-table-responsive">';
		echo '<table class="noborder centpercent">';

		// Header jours
		echo '<tr class="liste_titre">';
		echo '<th>'.$langs->trans("Project / Task").'</th>';
		foreach ($days as $d) {
			echo '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
		}
		echo '<th class="right">'.$langs->trans("Total").'</th>';
		echo '</tr>';

		// Ligne zone + panier (préfills depuis lignes)
		echo '<tr class="liste_titre">';
		echo '<td></td>';
		foreach ($days as $d) {
			echo '<td class="center">';
			echo '<select name="zone_'.$d.'" class="flat"'.$disabledAttr.'>';
			for ($z=1; $z<=5; $z++) {
				$sel = ($dayZone[$d] !== null && (int)$dayZone[$d] === $z) ? ' selected' : '';
				echo '<option value="'.$z.'"'.$sel.'>'.$z.'</option>';
			}
			echo '</select><br>';
			$checked = $dayMeal[$d] ? ' checked' : '';
			echo '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.$checked.$disabledAttr.'> '.$langs->trans("Meal").'</label>';
			echo '</td>';
		}
		echo '<td></td>';
		echo '</tr>';

		// Regrouper par projet
		$byproject = array();
		foreach ($tasks as $t) {
			$pid = (int)$t['project_id'];
			if (empty($byproject[$pid])) {
				$byproject[$pid] = array(
					'ref'   => $t['project_ref'],
					'title' => $t['project_title'],
					'tasks' => array()
				);
			}
			$byproject[$pid]['tasks'][] = $t;
		}

		// Lignes
		$grandInit = 0.0;
		foreach ($byproject as $pid => $pdata) {
			// Ligne projet
			echo '<tr class="oddeven trforbreak nobold">';
			$colspan = 1 + count($days) + 1;
			echo '<td colspan="'.$colspan.'">';
			$proj = new Project($db);
			$proj->fetch($pid);
			if (empty($proj->ref)) { $proj->ref = $pdata['ref']; $proj->title = $pdata['title']; }
			echo $proj->getNomUrl(1);
			echo '</td>';
			echo '</tr>';

			// Tâches
			foreach ($pdata['tasks'] as $task) {
				echo '<tr>';
				echo '<td class="paddingleft">';
				$tsk = new Task($db);
				$tsk->fetch((int)$task['task_id']);
				if (empty($tsk->label)) { $tsk->id = (int)$task['task_id']; $tsk->ref = $task['task_ref'] ?? ''; $tsk->label = $task['task_label']; }
				echo $tsk->getNomUrl(1, 'withproject');
				echo '</td>';

				$rowTotal = 0.0;
				foreach ($days as $d) {
					$iname = 'hours_'.$task['task_id'].'_'.$d;
					$val = '';
					$keydate = $weekdates[$d];
					if (isset($hoursBy[(int)$task['task_id']][$keydate])) {
						$val = formatHours($hoursBy[(int)$task['task_id']][$keydate]);
						$rowTotal += (float)$hoursBy[(int)$task['task_id']][$keydate];
					}
					$readonly = ($object->status != tw_status('draft')) ? ' readonly' : '';
					echo '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($val).'" placeholder="00:00"'.$readonly.'></td>';
				}
				$grandInit += $rowTotal;
				echo '<td class="right task-total">'.formatHours($rowTotal).'</td>';
				echo '</tr>';
			}
		}

		$grand = ($object->total_hours > 0 ? (float)$object->total_hours : $grandInit);

		echo '<tr class="liste_total">';
		echo '<td class="right">'.$langs->trans("Total").'</td>';
		foreach ($days as $d) echo '<td class="right day-total">00:00</td>';
		echo '<td class="right grand-total">'.formatHours($grand).'</td>';
		echo '</tr>';

		echo '<tr class="liste_total">';
		echo '<td class="right">'.$langs->trans("Meals").'</td>';
		$initMeals = array_sum($dayMeal);
		echo '<td colspan="'.count($days).'" class="right meal-total">'.$initMeals.'</td>';
		echo '<td></td>';
		echo '</tr>';

		echo '<tr class="liste_total">';
		echo '<td class="right">'.$langs->trans("Overtime").' ('.formatHours($contractedHours).')</td>';
		$ot = ($object->overtime_hours > 0 ? (float)$object->overtime_hours : max(0.0, $grand - $contractedHours));
		echo '<td colspan="'.count($days).'" class="right overtime-total">'.formatHours($ot).'</td>';
		echo '<td></td>';
		echo '</tr>';

		echo '</table>';
		echo '</div>';

		// Bouton Save
		if ($object->status == tw_status('draft') && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
			echo '<div class="center margintoponly"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		} else {
			echo '<div class="opacitymedium center margintoponly">'.$langs->trans("TimesheetIsNotEditable").'</div>';
		}

		echo '</form>';

		// JS totaux + mise à jour entête live
		$jsGrid = <<<JS
<script>
(function($){
	function parseHours(v){
		if(!v) return 0;
		if(v.indexOf(":") === -1) return parseFloat(v)||0;
		var p=v.split(":"); var h=parseInt(p[0],10)||0; var m=parseInt(p[1],10)||0;
		return h + (m/60);
	}
	function formatHours(d){
		if(isNaN(d)) return "00:00";
		var h=Math.floor(d); var m=Math.round((d-h)*60);
		if(m===60){ h++; m=0; }
		return String(h).padStart(2,"0")+":"+String(m).padStart(2,"0");
	}
	function updateTotals(){
		var grand=0;
		$(".task-total").text("00:00");
		$(".day-total").text("00:00");

		$("table.noborder tr").each(function(){
			var rowT=0;
			$(this).find("input.hourinput").each(function(){
				var v=parseHours($(this).val());
				if(v>0){
					rowT+=v;
					var idx=$(this).closest("td").index();
					var daycell=$("tr.liste_total:first td").eq(idx);
					var cur=parseHours(daycell.text());
					daycell.text(formatHours(cur+v));
					grand+=v;
				}
			});
			if(rowT>0) $(this).find(".task-total").text(formatHours(rowT));
		});

		$(".grand-total").text(formatHours(grand));

		var meals = $(".mealbox:checked").length;
		$(".meal-total").text(meals);

		var weeklyContract = {$contractedHours};
		var ot = grand - weeklyContract; if (ot < 0) ot = 0;
		$(".overtime-total").text(formatHours(ot));

		// met à jour l'entête
		$(".header-total-hours").text(formatHours(grand));
		$(".header-overtime").text(formatHours(ot));
	}
	$(function(){
		updateTotals(); // au chargement
		$(document).on("input change", "input.hourinput, input.mealbox", updateTotals);
	});
})(jQuery);
</script>
JS;
		echo $jsGrid;
	}

	// ---- Boutons d’action (barre) ----
	echo '<div class="tabsAction">';

	$token = newToken();

	// Soumettre : uniquement brouillon + au moins 1 ligne existante + droits
	if ($object->status == tw_status('draft')) {
		// Compter les lignes
		$nbLines = 0;
		$rescnt = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id);
		if ($rescnt) { $o=$db->fetch_object($rescnt); $nbLines=(int)$o->nb; }
		if ($nbLines > 0 && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
			echo dolGetButtonAction('', $langs->trans("Submit"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit&token='.$token);
		}
	}

	// Retour brouillon : si statut != brouillon (soumis / approuvé / refusé) pour salarié/or valideur
	if ($object->status != tw_status('draft')) {
		$canEmployee  = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
                $canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
		if ($canEmployee || $canValidator) {
			echo dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setdraft&token='.$token);
		}
	}

	// Approuver / Refuser quand soumis (validateur/manager/all/own)
	if ($object->status == tw_status('submitted')) {
                $canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
		if ($canValidator) {
			echo dolGetButtonAction('', ($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_validate&token='.$token);
			echo dolGetButtonAction('', $langs->trans("Refuse"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse&token='.$token);
		}
	}

	// Supprimer : brouillon OU soumis/approuvé/refusé si salarié (delete) ou validateur (validate*) ou all
        $canDelete = tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)
                || tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
	if ($canDelete) {
		echo dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.$token);
	}

	echo '</div>';

	// --- Patch d’affichage : remplacer "Validée/Validated" par "Approuvée/Approved" ---
	$jsStatusPatch = <<<'JS'
<script>
(function($){
	$(function(){
		$('.status, .statusbadge, .badge, .badgestatus').each(function(){
			var t = $(this).text().trim();
			if (t === 'Validée') $(this).text('Approuvée');
			if (t === 'Validated') $(this).text('Approved');
		});
	});
})(jQuery);
</script>
JS;
	echo $jsStatusPatch;
}

// End of page
llxFooter();
$db->close();
