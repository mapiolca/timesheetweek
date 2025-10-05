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
// facultatif si vous avez une classe ligne dédiée
// dol_include_once('/timesheetweek/class/timesheetweekline.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr() + formatHours()

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
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // set $object->id if found

// --- Permissions ---
$permRead      = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll   = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite     = !empty($user->rights->timesheetweek->timesheetweek->write);
$permDelete    = !empty($user->rights->timesheetweek->timesheetweek->delete);
$permApproveAll= !empty($user->rights->timesheetweek->timesheetweek->approveall); // à créer dans droits module si besoin

if (!$permRead) accessforbidden();

$form = new Form($db);
$useajax = !empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile);

// ----------- Helpers -----------
/**
 * Retourne l'id du valideur hiérarchique si défini, sinon null
 */
function tw_get_default_validator($db, $fk_user)
{
	$u = new User($db);
	if ($fk_user > 0 && $u->fetch($fk_user) > 0) {
		// Champs souvent utilisés pour le n+1 : fk_user (Dolibarr vxx), ou fk_user_superior
		if (!empty($u->fk_user)) return (int) $u->fk_user;
		if (!empty($u->fk_user_superior)) return (int) $u->fk_user_superior;
	}
	return null;
}

/**
 * Retourne true si l'utilisateur $current peut voir la fiche $object selon droits
 */
function tw_can_see($object, $current, $permRead, $permReadAll, $permReadChild)
{
	if (!$permRead) return false;
	if ($permReadAll) return true;
	if ($object->fk_user == $current->id) return true;
	if ($permReadChild) {
		$childs = $current->getAllChildIds(1);
		if (is_array($childs) && in_array($object->fk_user, $childs)) return true;
	}
	return false;
}

/**
 * Formate un float heures en HH:MM (fallback si lib absent)
 */
if (!function_exists('tw_formatHoursSafe')) {
	function tw_formatHoursSafe($hoursFloat)
	{
		if (function_exists('formatHours')) return formatHours($hoursFloat);
		if (is_null($hoursFloat) || $hoursFloat === '') return '00:00';
		$d = (float) $hoursFloat;
		$h = floor($d);
		$m = round(($d - $h) * 60);
		if ($m == 60) { $h++; $m = 0; }
		return str_pad((string) $h, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
	}
}

/**
 * Convertit HH:MM ou décimal -> float heures
 */
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

// ----------- Actions -----------

// CREATE
if ($action === 'add' && $permWrite) {
	$weekyear       = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user_post   = GETPOSTINT('fk_user');
	$fk_user_valid  = GETPOSTINT('fk_user_valid');
	$note           = GETPOST('note', 'restricthtml');

	$object->ref     = '(PROV)';
	$object->fk_user = $fk_user_post > 0 ? $fk_user_post : $user->id;
	$object->status  = TimesheetWeek::STATUS_DRAFT;
	$object->note    = $note;

	// validator par défaut si vide
	if ($fk_user_valid > 0) {
		$object->fk_user_valid = $fk_user_valid;
	} else {
		$def = tw_get_default_validator($db, $object->fk_user);
		$object->fk_user_valid = $def ?: null;
	}

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
if ($action === 'save' && $permWrite && $object->id > 0) {
	// Protection brouillon uniquement pour édition ? Vous pouvez lever la contrainte si besoin
	if ($object->status != TimesheetWeek::STATUS_DRAFT) {
		setEventMessages($langs->trans("ErrorRecordNotDraft"), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->begin();

	// Récupérer zones/paniers par jour (globaux à la feuille)
	$days = array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
	$dto = new DateTime();
	$dto->setISODate($object->year, $object->week);
	$dayDates = array(); // 'Monday' => 'Y-m-d'
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

	// Calcul des totaux
	$grandTotal = 0.0;

	// Pour chaque input hours_taskid_day
	foreach ($_POST as $key => $val) {
		if (!preg_match('/^hours_(\d+)_(\w+)$/', $key, $m)) continue;
		$taskid = (int) $m[1];
		$dayKey = $m[2]; // Monday, Tuesday, ...

		if (empty($dayDates[$dayKey])) continue;
		$thedate = $dayDates[$dayKey];

		$hours = tw_parse_hours_to_float($val);
		if ($hours < 0) $hours = 0;
		$grandTotal += $hours;

		$zone = isset($zoneByDate[$thedate]) ? (int) $zoneByDate[$thedate] : null;
		$meal = isset($mealByDate[$thedate]) ? (int) $mealByDate[$thedate] : 0;

		// upsert dans llx_timesheet_week_line
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line
				WHERE fk_timesheet_week = ".((int) $object->id)."
				AND fk_task = ".((int) $taskid)."
				AND day_date = '".$db->escape($thedate)."'";
		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		}
		$exists = ($db->num_rows($resql) > 0);
		$db->free($resql);

		if ($exists) {
			// UPDATE (si 0h -> on garde la ligne et met à 0, sinon vous pouvez choisir de DELETE)
			$sqlu = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line
					SET hours = ".((float) $hours).",
						zone = ".($zone !== null ? (int) $zone : "NULL").",
						meal = ".((int) $meal)."
					WHERE fk_timesheet_week = ".((int) $object->id)."
					AND fk_task = ".((int) $taskid)."
					AND day_date = '".$db->escape($thedate)."'";
			if (!$db->query($sqlu)) {
				$db->rollback();
				setEventMessages($db->lasterror(), null, 'errors');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
				exit;
			}
		} else {
			// INSERT seulement si heures > 0 (sinon pas d'intérêt)
			if ($hours > 0) {
				$sqli = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line(fk_timesheet_week, fk_task, day_date, hours, zone, meal)
						VALUES (".((int) $object->id).", ".((int) $taskid).", '".$db->escape($thedate)."', ".((float) $hours).", ".($zone !== null ? (int) $zone : "NULL").", ".((int) $meal).")";
				if (!$db->query($sqli)) {
					$db->rollback();
					setEventMessages($db->lasterror(), null, 'errors');
					header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
					exit;
				}
			}
		}
	}

	// Durée contractuelle (heures sup)
	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contracted = !empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0;
	$overtime = $grandTotal - $contracted;
	if ($overtime < 0) $overtime = 0;

	// Mise à jour totaux feuille
	$sqlp = "UPDATE ".MAIN_DB_PREFIX."timesheet_week
			SET total_hours = ".((float) $grandTotal).",
				overtime_hours = ".((float) $overtime)."
			WHERE rowid = ".((int) $object->id);
	if (!$db->query($sqlp)) {
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

// SUBMIT (confirm)
if ($action === 'confirm_submit' && $confirm === 'yes' && $permWrite && $object->id > 0) {
	// Passage à soumis
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_SUBMITTED)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) {
		setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// BACK TO DRAFT (confirm)
if ($action === 'confirm_setdraft' && $confirm === 'yes' && $permWrite && $object->id > 0) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_DRAFT)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) {
		setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// APPROVE (confirm)
if ($action === 'confirm_approve' && $confirm === 'yes' && $object->id > 0) {
	// Validation par valideur ou par droit global
	$isValidator = ($object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) || $permApproveAll;
	if (!$isValidator) accessforbidden();

	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_APPROVED).", date_validation = '".$db->idate(dol_now())."' WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) {
		setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
		// TODO: recopier les temps dans tâches projet (projet->fiche temps)
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// REFUSE (confirm)
if ($action === 'confirm_refuse' && $confirm === 'yes' && $object->id > 0) {
	$isValidator = ($object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) || $permApproveAll;
	if (!$isValidator) accessforbidden();

	$sql = "UPDATE ".MAIN_DB_PREFIX."timesheet_week SET status = ".((int) TimesheetWeek::STATUS_REFUSED)." WHERE rowid = ".((int) $object->id);
	if ($db->query($sql)) {
		setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// DELETE (confirm)
if ($action === 'confirm_delete' && $confirm === 'yes' && $permDelete && $object->id > 0) {
	$db->begin();
	$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week = ".((int) $object->id));
	$res = $object->delete($user);
	if ($res > 0) {
		$db->commit();
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php', 1));
		exit;
	} else {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
}

// ----------- View -----------
$title = $langs->trans("TimesheetWeek");
if ($action == 'create') $title = $langs->trans("NewTimesheetWeek");
$help_url = '';

llxHeader('', $title, $help_url);

// MODE CREATE
if ($action === 'create') {
	if (!$permWrite) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'time');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';

	// Employé (pré-sélection utilisateur courant)
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	print $form->select_dolusers($user->id, 'fk_user', 1);
	print '</td></tr>';

	// Semaine (sélecteur Dolibarr-like)
	print '<tr><td>'.$langs->trans("Week").'</td><td>';
	print getWeekSelectorDolibarr($form, 'weekyear'); // produit YYYY-Wxx
	print ' <div id="weekrange" class="opacitymedium paddingleft small"></div>';
	print '</td></tr>';

	// Valideur (par défaut hiérarchique)
	$defaultValidator = tw_get_default_validator($db, $user->id);
	print '<tr><td>'.$langs->trans("Validator").'</td><td>';
	print $form->select_dolusers($defaultValidator ?: $user->id, 'fk_user_valid', 1);
	print '</td></tr>';

	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>';
	print '<textarea name="note" class="quatrevingtpercent" rows="3"></textarea>';
	print '</td></tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'"> ';
	print '<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	// JS week range
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
	// Contrôle d'accès
	$cansee = tw_can_see($object, $user, $permRead, $permReadAll, $permReadChild);
	if (!$cansee) accessforbidden();

	// Contexte
	$isOwner     = ($object->fk_user == $user->id);
	$isValidator = ($object->fk_user_valid > 0 && $object->fk_user_valid == $user->id) || $permApproveAll;

	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'time');

	// ---- Confirm popups ----
	$formconfirm = '';
	if ($useajax) {
		// Soumettre
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $isOwner && $permWrite) {
			$formconfirm .= $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('Submit'),
				$langs->trans('ConfirmSubmit', $object->ref),
				'confirm_submit',
				array(),
				1,
				'action-submit'
			);
		}
		// Retour brouillon
		if (in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED)) && ($isOwner || $isValidator)) {
			$formconfirm .= $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('SetToDraft'),
				$langs->trans('ConfirmSetToDraft', $object->ref),
				'confirm_setdraft',
				array(),
				1,
				'action-setdraft'
			);
		}
		// Approuver / Refuser
		if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $isValidator) {
			$formconfirm .= $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('Validate'),
				$langs->trans('ConfirmValidate', $object->ref),
				'confirm_approve',
				array(),
				1,
				'action-approve'
			);
			$formconfirm .= $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('Refuse'),
				$langs->trans('ConfirmRefuse', $object->ref),
				'confirm_refuse',
				array(),
				1,
				'action-refuse'
			);
		}
		// Supprimer
		if ($permDelete) {
			$formconfirm .= $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id,
				$langs->trans('DeleteTimesheetWeek'),
				$langs->trans('ConfirmDeleteObject'),
				'confirm_delete',
				array(),
				1,
				'action-delete'
			);
		}
	} else {
		// Non-Ajax : confirmation suite à ask_*
		if ($action == 'ask_submit') {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Submit'), $langs->trans('ConfirmSubmit', $object->ref), 'confirm_submit');
		}
		if ($action == 'ask_setdraft') {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('SetToDraft'), $langs->trans('ConfirmSetToDraft', $object->ref), 'confirm_setdraft');
		}
		if ($action == 'ask_approve') {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidate', $object->ref), 'confirm_approve');
		}
		if ($action == 'ask_refuse') {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Refuse'), $langs->trans('ConfirmRefuse', $object->ref), 'confirm_refuse');
		}
		if ($action == 'delete') {
			$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteTimesheetWeek'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete');
		}
	}
	print $formconfirm;

	// Banner
	dol_banner_tab($object, 'ref');

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	// Employé
	if ($object->fk_user > 0) {
		$u = new User($db);
		$u->fetch($object->fk_user);
		print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	} else {
		print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td></td></tr>';
	}
	// Année
	print '<tr><td>'.$langs->trans("Year").'</td><td>'.dol_escape_htmltag($object->year).'</td></tr>';
	// Semaine
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.dol_escape_htmltag($object->week).'</td></tr>';
	// Validateur
	if ($object->fk_user_valid > 0) {
		$v = new User($db);
		$v->fetch($object->fk_user_valid);
		print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$v->getNomUrl(1).'</td></tr>';
	} else {
		print '<tr><td>'.$langs->trans("Validator").'</td><td class="opacitymedium">'.$langs->trans("None").'</td></tr>';
	}

	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.nl2br(dol_escape_htmltag($object->note)).'</td></tr>';

	// Totaux (si stockés)
	if (property_exists($object, 'total_hours')) {
		print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.tw_formatHoursSafe((float) $object->total_hours).'</td></tr>';
	}
	if (property_exists($object, 'overtime_hours')) {
		print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.tw_formatHoursSafe((float) $object->overtime_hours).'</td></tr>';
	}

	print '</table>';
	print '</div>'; // fichehalfright
	print '</div>'; // fichecenter

	print dol_get_fiche_end();

	// ---- Tableau des temps par semaine (grille) ----
	// Regroupement projet -> tâches affectées à l'utilisateur de la feuille
	$tasks = $object->getAssignedTasks($object->fk_user); // array: project_id, project_ref, project_title, task_id, task_label
	// Lignes existantes
	$lines = $object->getLines(); // array d'objets (fk_task, day_date, hours, zone, meal)

	// Indexation des valeurs pour pré-remplissage
	$valByTaskDay = array();  // key "taskid|Y-m-d" => 'HH:MM'
	$zoneByDate = array();    // 'Y-m-d' => int|null
	$mealByDate = array();    // 'Y-m-d' => 0/1
	if (is_array($lines)) {
		foreach ($lines as $l) {
			$key = $l->fk_task.'|'.$l->day_date;
			$valByTaskDay[$key] = tw_formatHoursSafe((float) $l->hours);
			// on prend la dernière valeur pour zone/meal par date (portée jour)
			if (!isset($zoneByDate[$l->day_date]) && $l->zone !== null) $zoneByDate[$l->day_date] = (int) $l->zone;
			if (!isset($mealByDate[$l->day_date])) $mealByDate[$l->day_date] = (int) $l->meal;
		}
	}

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
		$weekdates = array(); // 'Monday' => 'Y-m-d'
		foreach ($days as $d) {
			$weekdates[$d] = $dto->format('Y-m-d');
			$dto->modify('+1 day');
		}

		$userEmployee = new User($db);
		$userEmployee->fetch($object->fk_user);
		$contractedHours = !empty($userEmployee->weeklyhours) ? (float) $userEmployee->weeklyhours : 35.0;

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		// En-tête
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Project / Task").'</th>';
		foreach ($days as $d) {
			print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
		}
		print '<th>'.$langs->trans("Total").'</th>';
		print '</tr>';

		// Ligne Zone + Panier
		print '<tr class="liste_titre">';
		print '<td></td>';
		foreach ($days as $d) {
			$thedate = $weekdates[$d];
			$curZone = isset($zoneByDate[$thedate]) ? (int) $zoneByDate[$thedate] : '';
			$curMeal = !empty($mealByDate[$thedate]) ? 1 : 0;

			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat">';
			print '<option value=""></option>';
			for ($z=1; $z<=5; $z++) {
				print '<option value="'.$z.'"'.($curZone==$z?' selected':'').'>'.$z.'</option>';
			}
			print '</select><br>';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($curMeal?' checked':'').'> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td>';
		print '</tr>';

		// Regrouper tâches par projet
		$byproject = array(); // pid => ['ref'=>, 'title'=>, 'tasks'=>[]]
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

		$projectstatic = new Project($db);
		$taskstatic = new Task($db);

		foreach ($byproject as $pid => $pdata) {
			// Ligne projet (style perweek)
			$projectstatic->id = $pid;
			$projectstatic->ref = $pdata['ref'];
			$projectstatic->title = $pdata['title'];

			print '<tr class="oddeven trforbreak nobold">';
			print '<td colspan="'.(count($days)+2).'" class="bold">';
			print $projectstatic->getNomUrl(1, '', 0, '', 0, 0, '');
			if (!empty($projectstatic->title)) {
				print ' <span class="opacitymedium"> - '.dol_escape_htmltag($projectstatic->title).'</span>';
			}
			print '</td>';
			print '</tr>';

			// Lignes tâches
			foreach ($pdata['tasks'] as $t) {
				print '<tr>';
				print '<td class="paddingleft">';

				$taskstatic->id = $t['task_id'];
				$taskstatic->label = $t['task_label'];
				$taskstatic->ref = ''; // certaines versions n'ont pas de "ref" sur les tâches
				print $taskstatic->getNomUrl(1, 'project', 0, '', 0, 0, '');

				if (empty($taskstatic->label) && !empty($t['task_label'])) {
					print dol_escape_htmltag($t['task_label']);
				}

				print '</td>';

				$rowTotal = 0.0;
				foreach ($days as $d) {
					$thedate = $weekdates[$d];
					$name = 'hours_'.$t['task_id'].'_'.$d;
					$prefill = '';
					$key = $t['task_id'].'|'.$thedate;
					if (isset($valByTaskDay[$key])) $prefill = $valByTaskDay[$key];

					print '<td class="center">';
					print '<input type="text" class="flat hourinput" size="4" name="'.$name.'" value="'.dol_escape_htmltag($prefill).'" placeholder="00:00">';
					print '</td>';

					$rowTotal += tw_parse_hours_to_float($prefill);
				}
				print '<td class="right task-total">'.tw_formatHoursSafe($rowTotal).'</td>';
				print '</tr>';
			}
		}

		// Totaux
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
		print '<td class="right">'.$langs->trans("Overtime").' (>'.tw_formatHoursSafe($contractedHours).')</td>';
		print '<td colspan="'.count($days).'" class="right overtime-total">00:00</td>';
		print '<td></td>';
		print '</tr>';

		print '</table>';
		print '</div>';

		// Bouton Sauver seulement en Brouillon
		if ($object->status == TimesheetWeek::STATUS_DRAFT && $permWrite && $isOwner) {
			print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		}

		// JS totaux
		print '<script>
(function($){
	function parseHours(v){ if(!v) return 0; v=(""+v).replace(",","."); if(v.indexOf(":")===-1) return parseFloat(v)||0;
		var p=v.split(":"),h=parseInt(p[0],10)||0,m=parseInt(p[1],10)||0; if(m<0)m=0; if(m>59)m=59; return h+(m/60); }
	function formatHours(d){ if(isNaN(d)) return "00:00"; var h=Math.floor(d), m=Math.round((d-h)*60); if(m===60){h++;m=0;} return String(h).padStart(2,"0")+":"+String(m).padStart(2,"0"); }
	function updateTotals(){
		var grand=0;
		$(".task-total").text("00:00");
		$(".day-total").text("00:00");

		// indices colonnes jour -> cumul
		var dayTotals = {};
		$("table.noborder tr").each(function(){
			var rowTotal=0;
			$(this).find("input.hourinput").each(function(){
				var v=parseHours($(this).val());
				if(v>0){
					rowTotal += v;
					var idx = $(this).closest("td").index();
					dayTotals[idx] = (dayTotals[idx]||0) + v;
					grand += v;
				}
			});
			if(rowTotal>0) $(this).find(".task-total").text(formatHours(rowTotal));
		});
		// write dayTotals
		$("tr.liste_total:first td").each(function(i){
			if ($(this).hasClass("day-total")) {
				var idx = $(this).index();
				var v = dayTotals[idx]||0;
				$(this).text(formatHours(v));
			}
		});
		$(".grand-total").text(formatHours(grand));

		// meals
		$(".meal-total").text($(".mealbox:checked").length);

		// overtime
		var contracted = '.((float) $contractedHours).';
		var ot = grand - contracted; if (ot<0) ot=0;
		$(".overtime-total").text(formatHours(ot));
	}
	$(function(){
		// maj initiale au chargement
		updateTotals();
		$(document).on("input change","input.hourinput, input.mealbox, select[name^=zone_]", updateTotals);
	});
})(jQuery);
</script>';
	}

	print '</form>';

	// --- Boutons d'action ---
	print '<div class="tabsAction">';

	// Cacher "Modifier" si brouillon
	if ($permWrite && $object->status != TimesheetWeek::STATUS_DRAFT) {
		print dolGetButtonAction('', $langs->trans("Modify"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit');
	}

	// Soumettre (brouillon -> soumis)
	if ($object->status == TimesheetWeek::STATUS_DRAFT && $isOwner && $permWrite) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('Submit'), 'default', '', 'action-submit', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('Submit'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_submit', '', 1);
		}
	}

	// Retour à brouillon (si soumis/refusé) pour owner ou valideur
	if (in_array($object->status, array(TimesheetWeek::STATUS_SUBMITTED, TimesheetWeek::STATUS_REFUSED)) && ($isOwner || $isValidator)) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', '', 'action-setdraft', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_setdraft', '', 1);
		}
	}

	// Approuver / Refuser (si soumis) pour valideur
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED && $isValidator) {
		if ($useajax) {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', '', 'action-approve', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', '', 'action-refuse', 1);
		} else {
			print dolGetButtonAction('', $langs->trans('Validate'), 'ok', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_approve', '', 1);
			print dolGetButtonAction('', $langs->trans('Refuse'), 'danger', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse', '', 1);
		}
	}

	// Supprimer
	if ($useajax) {
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', '', 'action-delete', $permDelete);
	} else {
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permDelete);
	}

	print '</div>';
}

// End page
llxFooter();
$db->close();
