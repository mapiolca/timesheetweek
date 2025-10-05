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
dol_include_once('/timesheetweek/class/timesheetweekline.class.php');
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

// ---- Permissions (nouveau modèle) ----
$permRead          = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadChild     = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permReadAll       = $user->hasRight('timesheetweek','timesheetweek','readAll');

$permWrite         = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteChild    = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permWriteAll      = $user->hasRight('timesheetweek','timesheetweek','writeAll');

$permValidate      = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateAll   = $user->hasRight('timesheetweek','timesheetweek','validateAll');

$permDelete        = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteChild   = $user->hasRight('timesheetweek','timesheetweek','deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek','timesheetweek','deleteAll');

$permReadAny   = ($permRead || $permReadChild || $permReadAll);
$permWriteAny  = ($permWrite || $permWriteChild || $permWriteAll);
$permDeleteAny = ($permDelete || $permDeleteChild || $permDeleteAll);

// ---- Helpers ----
/**
 * Vérifie si $user courant a le droit d’agir (écriture) sur la fiche du salarié $userid selon la portée.
 */
function tw_can_act_on_user($userid, $own, $child, $all, User $user) {
	if ($all) return true;
	if ($own && ((int)$userid === (int)$user->id)) return true;
	if ($child) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$userid, $subs, true)) return true;
	}
	return false;
}
/**
 * Vérifie si $user courant a le droit de valider sur la fiche du salarié $userid selon la portée.
 */
function tw_can_validate_on_user($userid, $own, $child, $all, User $user) {
	if ($all) return true;
	if ($own && ((int)$userid === (int)$user->id)) return true;
	if ($child) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$userid, $subs, true)) return true;
	}
	return false;
}

// Sécurise l'objet si présent
if (!empty($id) && $object->id <= 0) $object->fetch($id);

// ----------------- Inline edits (crayons) -----------------
if ($action === 'setfk_user' && $object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT) {
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

if ($action === 'setvalidator' && $object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT) {
	$newval = GETPOSTINT('fk_user_valid');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->fk_user_valid = ($newval > 0 ? $newval : null);
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setnote' && $object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT) {
	$newval = GETPOST('note', 'restricthtml');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->note = $newval;
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setweekyear' && $object->id > 0 && $object->status == TimesheetWeek::STATUS_DRAFT) {
	$weekyear = GETPOST('weekyear', 'alpha');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int)$m[1];
		$object->week = (int)$m[2];
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
	$object->status  = TimesheetWeek::STATUS_DRAFT;
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

// ----------------- Action: Save grid lines (UPSERT) -----------------
if ($action === 'save' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	// Edition autorisée et statut brouillon
	if ($object->status != TimesheetWeek::STATUS_DRAFT) {
		setEventMessages($langs->trans("TimesheetIsNotEditable"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$db->begin();

	// Map jour -> offset
	$map = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);

	$processed = 0;

	// Balayage des inputs
	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(\w+)$/', $key, $m)) {
			$taskid = (int) $m[1];
			$day    = $m[2];
			$hoursStr  = trim((string) $val);

			// Parse HH:MM ou décimal
			$h = 0.0;
			if ($hoursStr !== '') {
				if (strpos($hoursStr, ':') !== false) {
					$tmp = explode(':', $hoursStr, 2);
					$H = isset($tmp[0]) ? $tmp[0] : '0';
					$M = isset($tmp[1]) ? $tmp[1] : '0';
					$h = ((int)$H) + ((int)$M)/60.0;
				} else {
					$h = (float) str_replace(',', '.', $hoursStr);
				}
			}

			// Calcule date du jour ISO
			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week);
			$dto->modify('+'.$map[$day].' day');
			$datestr = $dto->format('Y-m-d');

			$zone = (int) GETPOST('zone_'.$day, 'int');
			$meal = GETPOST('meal_'.$day) ? 1 : 0;

			// Existe déjà ?
			$sqlSel = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line 
				WHERE fk_timesheet_week=".(int)$object->id." AND fk_task=".(int)$taskid." AND day_date='".$db->escape($datestr)."'";
			$resSel = $db->query($sqlSel);
			$existingId = 0;
			if ($resSel && $db->num_rows($resSel) > 0) {
				$o = $db->fetch_object($resSel);
				$existingId = (int) $o->rowid;
			}

			if ($h > 0) {
				if ($existingId > 0) {
					// UPDATE
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
					// INSERT
					$line = new TimesheetWeekLine($db);
					$line->fk_timesheet_week = (int) $object->id;
					$line->fk_task  = $taskid;
					$line->day_date = $datestr;
					$line->hours    = $h;
					$line->zone     = $zone;
					$line->meal     = $meal;

					$res = $line->create($user);
					if ($res < 0) {
						$db->rollback();
						setEventMessages($line->error, $line->errors, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				}
				$processed++;
			} else {
				// 0 ou vide => DELETE si existe
				if ($existingId > 0) {
					$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE rowid=".$existingId);
					$processed++;
				}
			}
		}
	}

	// Recalcule total + heures sup
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
	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Submit (Soumettre) -----------------
if ($action === 'submit' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status != TimesheetWeek::STATUS_DRAFT) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	// Refuser soumission si aucune heure
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

	$object->status = TimesheetWeek::STATUS_SUBMITTED;
	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Back to draft (Retour au brouillon) -----------------
if ($action === 'setdraft' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status != TimesheetWeek::STATUS_SUBMITTED) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$canEmployee = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
	$canValidator = false;
	// Le validateur courant ou toute personne ayant droits de validation sur ce salarié
	if ((int)$user->id === (int)$object->fk_user_valid && ($permValidate || $permValidateChild || $permValidateAll)) {
		$canValidator = true;
	} elseif (tw_can_validate_on_user($object->fk_user, $permValidate, $permValidateChild, $permValidateAll, $user)) {
		$canValidator = true;
	}

	if (!$canEmployee && !$canValidator) {
		accessforbidden();
	}

	$object->status = TimesheetWeek::STATUS_DRAFT;
	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages($langs->trans("StatusSetToDraft"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Delete -----------------
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if (!tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)) {
		accessforbidden();
	}

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

	// Confirm delete modal si besoin
	if ($action === 'delete') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			'no',
			1
		);
		print $formconfirm;
	}

	echo '<div class="fichecenter">';

	$canEditInline = ($object->status == TimesheetWeek::STATUS_DRAFT && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user));

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

	// Semaine (Year/Week)
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

	// Note (gauche)
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

	$tasks = $object->getAssignedTasks($object->fk_user); // array de tâches assignées
	$lines = $object->getLines(); // objets TimesheetWeekLine existants

	// Indexer les heures existantes: hoursBy[taskid][Y-m-d] = décimal
	$hoursBy = array();
	$dayMeal = array('Monday'=>0,'Tuesday'=>0,'Wednesday'=>0,'Thursday'=>0,'Friday'=>0,'Saturday'=>0,'Sunday'=>0);
	$dayZone = array('Monday'=>null,'Tuesday'=>null,'Wednesday'=>null,'Thursday'=>null,'Friday'=>null,'Saturday'=>null,'Sunday'=>null);

	if (!empty($lines)) {
		foreach ($lines as $L) {
			$keydate = $L->day_date; // Y-m-d
			$w = (int) date('N', strtotime($keydate));
			$dayName = array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday')[$w];

			if (!isset($hoursBy[$L->fk_task])) $hoursBy[$L->fk_task] = array();
			$hoursBy[$L->fk_task][$keydate] = (float) $L->hours;

			if ((int)$L->meal === 1) $dayMeal[$dayName] = 1;
			if ($L->zone !== null && $L->zone !== '') $dayZone[$dayName] = (int)$L->zone;
		}
	}

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

		// Ligne zone + panier (préfills)
		echo '<tr class="liste_titre">';
		echo '<td></td>';
		foreach ($days as $d) {
			echo '<td class="center">';
			echo '<select name="zone_'.$d.'" class="flat">';
			for ($z=1; $z<=5; $z++) {
				$sel = ($dayZone[$d] !== null && (int)$dayZone[$d] === $z) ? ' selected' : '';
				echo '<option value="'.$z.'"'.$sel.'>'.$z.'</option>';
			}
			echo '</select><br>';
			$checked = $dayMeal[$d] ? ' checked' : '';
			echo '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.$checked.'> '.$langs->trans("Meal").'</label>';
			echo '</td>';
		}
		echo '<td></td>';
		echo '</tr>';

		// Regrouper par projet
		$byproject = array();
		foreach ($tasks as $t) {
			$pid = $t['project_id'];
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
			// Ligne projet (style suivi du temps), cellule unique
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
					if (isset($hoursBy[$task['task_id']][$keydate])) {
						$val = formatHours($hoursBy[$task['task_id']][$keydate]);
						$rowTotal += (float)$hoursBy[$task['task_id']][$keydate];
					}
					echo '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($val).'" placeholder="00:00"></td>';
				}
				$grandInit += $rowTotal;
				echo '<td class="right task-total">'.formatHours($rowTotal).'</td>';
				echo '</tr>';
			}
		}

		// Totaux init
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

		// Bouton Save (si brouillon + droits d’écriture)
		if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
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

	// Boutons d’action (barre)
	echo '<div class="tabsAction">';
	// Supprimer (en brouillon)
	if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)) {
		echo dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete');
	}
	// Soumettre (si brouillon + droits écriture)
	if ($object->status == TimesheetWeek::STATUS_DRAFT && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		echo dolGetButtonAction('', $langs->trans("Submit"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit');
	}
	// Retour au brouillon (si soumis + salarié ou validateur)
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED) {
		$canEmployee = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
		$canValidator = ((int)$user->id === (int)$object->fk_user_valid && ($permValidate || $permValidateChild || $permValidateAll))
			|| tw_can_validate_on_user($object->fk_user, $permValidate, $permValidateChild, $permValidateAll, $user);
		if ($canEmployee || $canValidator) {
			echo dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setdraft');
		}
	}
	echo '</div>';
}

// End of page
llxFooter();
$db->close();
