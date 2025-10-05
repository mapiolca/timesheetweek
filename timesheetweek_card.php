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
$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// ---- Init ----
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// ---- Fetch ----
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // set $object if id/ref provided

// ---- Permissions (compat create/write, read/readChild/readAll, delete/deleteAll...) ----
$has = static function($perm) use ($user) {
	return $user->hasRight('timesheetweek','timesheetweek',$perm);
};

// Lecture
$permReadOwn   = $has('read') || $has('lire'); // legacy safeguard
$permReadChild = $has('readChild') || $has('lire_sub'); // safeguard
$permReadAll   = $has('readAll') || $has('lire_tous');  // safeguard

// Création / edition
$permCreateOwn   = $has('create') || $has('write');
$permCreateChild = $has('createChild') || $has('writeChild');
$permCreateAll   = $has('createAll') || $has('writeAll');

// Validation
$permValidateOwn   = $has('validate');
$permValidateChild = $has('validateChild');
$permValidateAll   = $has('validateAll');

// Suppression
$permDeleteOwn   = $has('delete');
$permDeleteChild = $has('deleteChild');
$permDeleteAll   = $has('deleteAll');

// Export (pas utilisé ici, mais dispo)
$permExport = $has('export');

// Raccourcis combinés
$permReadAny   = ($permReadAll || $permReadChild || $permReadOwn);
$permCreateAny = ($permCreateAll || $permCreateChild || $permCreateOwn);
$permDeleteAny = ($permDeleteAll || $permDeleteChild || $permDeleteOwn);
$permValidateAny = ($permValidateAll || $permValidateChild || $permValidateOwn);

if (!$permReadAny && $action !== 'create' && $action !== 'add') accessforbidden();

// ---- Helpers ----
/**
 * Vérifie si $user courant a le droit d’agir sur la fiche $userid (propriétaire).
 */
function canActOnUser($userid, $own, $child, $all, User $user) {
	if ($all) return true;
	if ($own && ($userid == $user->id)) return true;
	if ($child) {
		$subs = $user->getAllChildIds(1);
		if (is_array($subs) && in_array((int)$userid, $subs)) return true;
	}
	return false;
}

// ----------------- Action: Create (add) -----------------
if ($action === 'add') {
	if (!$permCreateAny) accessforbidden();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	// Si l'utilisateur cible n'est pas autorisé selon la portée, refuse
	if (!canActOnUser($fk_user ?: $user->id, $permCreateOwn, $permCreateChild, $permCreateAll, $user)) {
		accessforbidden();
	}

	$object->ref          = '(PROV)';
	$object->fk_user      = $fk_user > 0 ? $fk_user : $user->id;
	$object->status       = TimesheetWeek::STATUS_DRAFT;
	$object->note         = $note;

	// Défault validator = manager hiérarchique si non fourni
	if ($fk_user_valid > 0) {
		$object->fk_user_valid = $fk_user_valid;
	} else {
		$uTmp = new User($db);
		$uTmp->fetch($object->fk_user);
		$object->fk_user_valid = !empty($uTmp->fk_user) ? (int)$uTmp->fk_user : null; // fallback null
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

// ----------------- Action: Save grid lines -----------------
if ($action === 'save' && $id > 0) {
	// Re-fetch object (in case)
	if ($object->id <= 0) $object->fetch($id);

	// Vérifie droits d'écriture sur le propriétaire de la fiche
	if (!canActOnUser($object->fk_user, $permCreateOwn, $permCreateChild, $permCreateAll, $user)) {
		accessforbidden();
	}

	$db->begin();

	// On supprime puis réinsère (simple) — ou on pourrait upsert si besoin fin
	$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $object->id);

	// Balayer inputs
	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(\w+)$/', $key, $m)) {
			$taskid = (int) $m[1];
			$day    = $m[2];
			$hours  = (string) $val;

			// Accepte HH:MM ou décimal
			$h = 0.0;
			if (strpos($hours, ':') !== false) {
				list($H,$M) = array_pad(explode(':', $hours, 2), 2, '0');
				$h = ((int)$H) + ((int)$M)/60.0;
			} else {
				$h = (float) str_replace(',', '.', $hours);
			}

			if ($h > 0) {
				// Date jour ISO
				$map = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);
				$dto = new DateTime();
				$dto->setISODate((int)$object->year, (int)$object->week);
				$dto->modify('+'.$map[$day].' day');

				$line = new TimesheetWeekLine($db);
				$line->fk_timesheet_week = (int) $object->id;
				$line->fk_task  = $taskid;
				$line->day_date = $dto->format('Y-m-d');
				$line->hours    = $h;
				$line->zone     = (int) GETPOST('zone_'.$day, 'int');
				$line->meal     = GETPOST('meal_'.$day) ? 1 : 0;

				$res = $line->create($user);
				if ($res < 0) {
					$db->rollback();
					setEventMessages($line->error, $line->errors, 'errors');
					header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
					exit;
				}
			}
		}
	}

	// Recalcule totaux & heures sup (stockés sur la fiche)
	$totalHours = (float) GETPOST('grand_total_hours', 'alpha'); // Peut être vide -> recalc côté serveur si besoin
	if (!$totalHours) {
		// Recalcule depuis les lignes
		$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
		$resSum = $db->query($sqlSum);
		if ($resSum) {
			$o = $db->fetch_object($resSum);
			$totalHours = (float) $o->sh;
		}
	}

	// Heures contractuelles
	$uEmp = new User($db);
	$uEmp->fetch($object->fk_user);
	$contract = !empty($uEmp->weeklyhours) ? (float)$uEmp->weeklyhours : 35.0;
	$overtime = max(0, $totalHours - $contract);

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

// ----------------- Action: Delete -----------------
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	// check droit de suppression par portée
	if (!canActOnUser($object->fk_user, $permDeleteOwn, $permDeleteChild, $permDeleteAll, $user)) {
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
	if (!$permCreateAny) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	// Employé
	print '<tr>';
	print '<td class="titlefield">'.$langs->trans("Employee").'</td>';
	print '<td>'.$form->select_dolusers($user->id, 'fk_user', 1).'</td>';
	print '</tr>';

	// Semaine
	print '<tr>';
	print '<td>'.$langs->trans("Week").'</td>';
	print '<td>'.getWeekSelectorDolibarr($form, 'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td>';
	print '</tr>';

	// Validateur - par défaut manager hiérarchique si dispo
	$defaultValidatorId = null;
	if (!empty($user->fk_user)) $defaultValidatorId = (int)$user->fk_user;
	print '<tr>';
	print '<td>'.$langs->trans("Validator").'</td>';
	print '<td>'.$form->select_dolusers($defaultValidatorId, 'fk_user_valid', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200').'</td>';
	print '</tr>';

	// Note
	print '<tr>';
	print '<td>'.$langs->trans("Note").'</td>';
	print '<td><textarea name="note" class="quatrevingtpercent" rows="3"></textarea></td>';
	print '</tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	// JS Range semaine
	print <<<'JS'
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

} else if ($id > 0) {
	// ---- READ MODE (fiche + grille) ----
	// Vérifie droit de lecture sur propriétaire de la fiche
	if (!canActOnUser($object->fk_user, $permReadOwn, $permReadChild, $permReadAll, $user)) {
		accessforbidden();
	}

	// Head + banner
	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'ref', $linkback);

	print '<div class="fichecenter">';
	// Left block
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';
	// Employé
	if ($object->fk_user > 0) {
		$u=new User($db); $u->fetch($object->fk_user);
		print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	}
	// Année
	print '<tr><td>'.$langs->trans("Year").'</td><td>'.dol_escape_htmltag($object->year).'</td></tr>';
	// Semaine
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.dol_escape_htmltag($object->week).'</td></tr>';
	// Note (dans la partie gauche comme demandé)
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.nl2br(dol_escape_htmltag($object->note)).'</td></tr>';
	// Validator
	if ($object->fk_user_valid > 0) {
		$v=new User($db); $v->fetch($object->fk_user_valid);
		print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$v->getNomUrl(1).'</td></tr>';
	}
	print '</table>';
	print '</div>';

	// Right block
	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '</table>';
	print '</div>';

	print '</div>'; // fichecenter

	print dol_get_fiche_end(); // <<< IMPORTANT : la grille est en-dessous de la fiche

	// ------- GRID (Assigned Tasks grouped by Project) -------
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<h3>'.$langs->trans("AssignedTasks").'</h3>';

	$tasks = $object->getAssignedTasks($object->fk_user); // array de tâches assignées
	$lines = $object->getLines(); // objets TimesheetWeekLine existants

	// Indexer les heures existantes: hoursBy[taskid][Y-m-d] = décimal
	$hoursBy = array();
	if (!empty($lines)) {
		foreach ($lines as $L) {
			$keydate = $L->day_date;
			if (!isset($hoursBy[$L->fk_task])) $hoursBy[$L->fk_task] = array();
			$hoursBy[$L->fk_task][$keydate] = (float) $L->hours;
		}
	}

	if (empty($tasks)) {
		print '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
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

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		// Header jours
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans("Project / Task").'</th>';
		foreach ($days as $d) {
			print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
		}
		print '<th class="right">'.$langs->trans("Total").'</th>';
		print '</tr>';

		// Ligne zone + panier
		print '<tr class="liste_titre">';
		print '<td></td>';
		foreach ($days as $d) {
			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat">';
			for ($z=1; $z<=5; $z++) print '<option value="'.$z.'">'.$z.'</option>';
			print '</select><br>';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td>';
		print '</tr>';

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
		foreach ($byproject as $pid => $pdata) {
			// Ligne projet (style Suivi du temps)
			print '<tr class="oddeven trforbreak nobold">';
			// cellule unique sur toute la ligne projet
			$colspan = 1 + count($days) + 1;
			print '<td colspan="'.$colspan.'">';
			// getNomUrl project
			$proj = new Project($db);
			$proj->id = $pid;
			$proj->ref = $pdata['ref'];
			$proj->title = $pdata['title'];
			print $proj->getNomUrl(1);
			print '</td>';
			print '</tr>';

			// Tâches
			foreach ($pdata['tasks'] as $task) {
				print '<tr>';
				print '<td class="paddingleft">';
				$tsk = new Task($db);
				$tsk->id = (int)$task['task_id'];
				$tsk->ref = $task['task_ref'] ?? '';
				$tsk->label = $task['task_label'];
				print $tsk->getNomUrl(1, 'withproject'); // avec lien tâche
				print '</td>';

				$rowTotal = 0.0;
				foreach ($days as $d) {
					$iname = 'hours_'.$task['task_id'].'_'.$d;
					$val = '';
					$keydate = $weekdates[$d];
					if (isset($hoursBy[$task['task_id']][$keydate])) {
						// pré-remplir au format HH:MM
						$val = formatHours($hoursBy[$task['task_id']][$keydate]);
						$rowTotal += (float)$hoursBy[$task['task_id']][$keydate];
					}
					print '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($val).'" placeholder="00:00"></td>';
				}
				print '<td class="right task-total">'.formatHours($rowTotal).'</td>';
				print '</tr>';
			}
		}

		// Totaux
		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Total").'</td>';
		foreach ($days as $d) print '<td class="right day-total">00:00</td>';
		$grand = (float)$object->total_hours;
		print '<td class="right grand-total">'.formatHours($grand).'</td>';
		print '</tr>';

		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Meals").'</td>';
		print '<td colspan="'.count($days).'" class="right meal-total">0</td>';
		print '<td></td>';
		print '</tr>';

		print '<tr class="liste_total">';
		print '<td class="right">'.$langs->trans("Overtime").' (>'.formatHours($contractedHours).')</td>';
		$ot = (float)$object->overtime_hours;
		print '<td colspan="'.count($days).'" class="right overtime-total">'.formatHours($ot).'</td>';
		print '<td></td>';
		print '</tr>';

		print '</table>';
		print '</div>';

		// Bouton Save (si droits d’édition sur ce salarié)
		if (canActOnUser($object->fk_user, $permCreateOwn, $permCreateChild, $permCreateAll, $user) &&
