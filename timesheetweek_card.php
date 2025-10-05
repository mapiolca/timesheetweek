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
dol_include_once('/timesheetweek/class/timesheetweekline.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // doit contenir formatHours()

$langs->loadLangs(array("timesheetweek@timesheetweek", "other", "projects"));

// Get parameters
$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Init object
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions (module timesheetweek)
$permRead      = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll   = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite     = !empty($user->rights->timesheetweek->timesheetweek->write);
$permDelete    = !empty($user->rights->timesheetweek->timesheetweek->delete);

if (!$permRead) accessforbidden();

// ----------------- Action Create -----------------
if ($action == 'add' && $permWrite) {
	$weekyear      = GETPOST('weekyear', 'alpha'); // format attendu YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$object->ref          = '(PROV)';
	$object->fk_user      = $fk_user > 0 ? $fk_user : $user->id;
	$object->status       = TimesheetWeek::STATUS_DRAFT;
	$object->note         = $note;
	$object->fk_user_valid= $fk_user_valid > 0 ? $fk_user_valid : null;

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

// ----------------- Action Save -----------------
if ($action == 'save' && $permWrite && $id > 0) {
	$db->begin();

	// Nettoyer anciennes lignes
	$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $id);

	// Balayage des inputs
	foreach ($_POST as $key => $val) {
		if (preg_match('/^hours_(\d+)_(\w+)/', $key, $m)) {
			$taskid = (int) $m[1];
			$day    = $m[2];
			$hours  = (float) str_replace(',', '.', $val);

			if ($hours > 0) {
				$dto = new DateTime();
				$dto->setISODate($object->year, $object->week);
				$map = ["Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6];
				$dto->modify("+".$map[$day]." day");

				$line = new TimesheetWeekLine($db);
				$line->fk_timesheet_week = $object->id;
				$line->fk_task  = $taskid;
				$line->day_date = $dto->format('Y-m-d');
				$line->hours    = $hours;
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

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- View -----------------
$form = new Form($db);
$title = $langs->trans("TimesheetWeek");
llxHeader('', $title);

// ---- Mode création ----
if ($action == 'create') {
	if (!$permWrite) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'object_timesheetweek@timesheetweek');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$form->select_dolusers($user->id,'fk_user',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.getWeekSelectorDolibarr($form,'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td></tr>';
	print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$form->select_dolusers($user->id,'fk_user_valid',1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" rows="3" class="quatrevingtpercent"></textarea></td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';
}
// ---- Mode consultation ----
elseif ($id > 0 && $action != 'create') {
	// Permissions de vue
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

	// ---- Fiche ----
	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head,'card',$langs->trans("TimesheetWeek"),-1,'time');

	dol_banner_tab($object,'ref');

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent">';

	if ($object->fk_user > 0) {
		$u=new User($db); $u->fetch($object->fk_user);
		print '<tr><td>'.$langs->trans("Employee").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans("Year").'</td><td>'.$object->year.'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.$object->week.'</td></tr>';
	if ($object->fk_user_valid > 0) {
		$u=new User($db); $u->fetch($object->fk_user_valid);
		print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	}
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent">';
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.nl2br(dol_escape_htmltag($object->note)).'</td></tr>';
	print '</table>';
	print '</div>';
	print '</div>';

	print dol_get_fiche_end();

	// ---- Grille des heures ----
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<h3>'.$langs->trans("AssignedTasks").'</h3>';
	$tasks = $object->getAssignedTasks($object->fk_user);
	// Charger les lignes déjà saisies
	$lines = $object->getLines();   // => tableau d’objets TimesheetWeekLine

	if (empty($tasks)) {
		print '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
	} else {
		$days = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
		$dto=new DateTime(); $dto->setISODate($object->year,$object->week);
		$weekdates=[]; foreach($days as $d){ $weekdates[$d]=$dto->format('Y-m-d'); $dto->modify('+1 day'); }

		$userEmployee=new User($db); $userEmployee->fetch($object->fk_user);
		$contractedHours=(!empty($userEmployee->weeklyhours)?(float)$userEmployee->weeklyhours:35.0);

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		// header
		print '<tr class="liste_titre"><th>'.$langs->trans("Project / Task").'</th>';
		foreach($days as $d){ print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]),'day').'</span></th>'; }
		print '<th>'.$langs->trans("Total").'</th></tr>';

		// zone + meal
		print '<tr class="liste_titre"><td></td>';
		foreach($days as $d){
			print '<td class="center">';
			print '<select name="zone_'.$d.'" class="flat">'; for($z=1;$z<=5;$z++) print '<option value="'.$z.'">'.$z.'</option>'; print '</select><br>';
			print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"> '.$langs->trans("Meal").'</label>';
			print '</td>';
		}
		print '<td></td></tr>';

		// regroupement projets
		$byproject=[];
		foreach($tasks as $t){
			$byproject[$t['project_id']]['ref']=$t['project_ref'];
			$byproject[$t['project_id']]['title']=$t['project_title'];
			$byproject[$t['project_id']]['tasks'][]=$t;
		}

		foreach($byproject as $pid=>$pdata){
			print '<tr class="trproject"><td class="bold">'.dol_escape_htmltag($pdata['ref'].' - '.$pdata['title']).'</td>';
			foreach($days as $d) print '<td class="center">-</td>'; print '<td></td></tr>';

			foreach($pdata['tasks'] as $task){
				print '<tr><td class="paddingleft"><a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$task['task_id'].'">'.dol_escape_htmltag($task['task_label']).'</a></td>';
				foreach($days as $d){
					$iname='hours_'.$task['task_id'].'_'.$d;
					$daydate = $weekdates[$d];
					$hourval = '';
					if (!empty($lines[$task['task_id']][$daydate])) {
						$hourval = (float) $lines[$task['task_id']][$daydate]->hours;
					}
					print '<td class="center"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.$hourval.'"></td>';

				}
				print '<td class="right task-total">00:00</td></tr>';
			}
		}

		// totaux
		print '<tr class="liste_total"><td class="right">'.$langs->trans("Total").'</td>';
		foreach($days as $d) print '<td class="right day-total">00:00</td>';
		print '<td class="right grand-total">00:00</td></tr>';

		print '<tr class="liste_total"><td class="right">'.$langs->trans("Meals").'</td><td colspan="'.count($days).'" class="right meal-total">0</td><td></td></tr>';
		print '<tr class="liste_total"><td class="right">'.$langs->trans("Overtime").' (>'.formatHours($contractedHours).')</td><td colspan="'.count($days).'" class="right overtime-total">00:00</td><td></td></tr>';

		print '</table></div>';
		print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
		print '</form>';

		// JS
		print sprintf("
<script>
(function($){
function parseHours(v){if(!v)return 0;if(v.indexOf(':')==-1)return parseFloat(v)||0;var p=v.split(':');var h=parseInt(p[0],10)||0;var m=parseInt(p[1],10)||0;return h+(m/60);}
function formatHours(d){if(isNaN(d))return '00:00';var h=Math.floor(d);var m=Math.round((d-h)*60);if(m===60){h++;m=0;}return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');}
function updateTotals(){var grand=0;$('.task-total').text('00:00');$('.day-total').text('00:00');
$('tr').each(function(){var rowT=0;$(this).find('input.hourinput').each(function(){var v=parseHours($(this).val());if(!isNaN(v)&&v>0){rowT+=v;var idx=$(this).closest('td').index();var cell=$('tr.liste_total:first td').eq(idx);var cur=parseHours(cell.text());cell.text(formatHours(cur+v));grand+=v;}});if(rowT>0)$(this).find('.task-total').text(formatHours(rowT));});
$('.grand-total').text(formatHours(grand));$('.meal-total').text($('.mealbox:checked').length);
var ot=grand-".((float)$contractedHours).";if(ot<0)ot=0;$('.overtime-total').text(formatHours(ot));}
$(document).on('input change','input.hourinput, input.mealbox',updateTotals);
})(jQuery);
</script>");
	}

	// boutons
	print '<div class="tabsAction">';
	if ($permWrite) print dolGetButtonAction('',$langs->trans("Modify"),'default',$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit');
	if ($permDelete) print dolGetButtonAction('',$langs->trans("Delete"),'delete',$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete');
	print '</div>';
}

// JS pour le sélecteur semaine
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

// End of page
llxFooter();
$db->close();
