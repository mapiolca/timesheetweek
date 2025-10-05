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
$permRead      = !empty($user->rights->timesheetweek->timesheetweek->read);
$permReadAll   = !empty($user->rights->timesheetweek->timesheetweek->readall);
$permReadChild = !empty($user->rights->timesheetweek->timesheetweek->readchild);
$permWrite     = !empty($user->rights->timesheetweek->timesheetweek->write);
$permDelete    = !empty($user->rights->timesheetweek->timesheetweek->delete);

if (!$permRead) accessforbidden();

/* =================================
 * Actions: Create
 * ================================= */
if ($action == 'add' && $permWrite) {
	$weekyear       = GETPOST('weekyear', 'alpha'); // format YYYY-Wxx
	$fk_user        = GETPOSTINT('fk_user');
	$fk_user_valid  = GETPOSTINT('fk_user_valid');
	$note           = GETPOST('note', 'restricthtml');

	$object->ref            = '(PROV)';
	$object->fk_user        = $fk_user > 0 ? $fk_user : $user->id;
	$object->status         = TimesheetWeek::STATUS_DRAFT;
	$object->note           = $note;
	$object->fk_user_valid  = $fk_user_valid > 0 ? $fk_user_valid : null;

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

	// Confirm popup (AJAX & non-AJAX)
	$formconfirm = '';
	if ($action == 'delete') {
		// Non-AJAX confirm (show immediately)
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('DeleteTimesheetWeek'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			0,
			1
		);
	} else {
		// AJAX confirm attached to #action-delete
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('DeleteTimesheetWeek'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			1,
			'action-delete'
		);
	}
	print $formconfirm;

	// Banner
	dol_banner_tab($object,'ref');

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent">';

	if ($object->fk_user > 0) {
		$u = new User($db); $u->fetch($object->fk_user);
		print '<tr><td>'.$langs->trans("Employee").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans("Year").'</td><td>'.$object->year.'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.$object->week.'</td></tr>';
	if ($object->fk_user_valid > 0) {
		$uv = new User($db); $uv->fetch($object->fk_user_valid);
		print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$uv->getNomUrl(1).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans("TotalHours").'</td><td>'.formatHours((float)$object->total_hours).'</td></tr>';
	print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.formatHours((float)$object->overtime_hours).'</td></tr>';

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

	/* ===== Grid of hours ===== */
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
		if (!isset($dayMeta[$d])) $dayMeta[$d] = array('zone'=>(int)$L->zone,'meal'=>(int)$L->meal);
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
			// Fetch project to ensure proper getNomUrl
			$projectstatic->fetch($pid);

			print '<tr class="oddeven trforbreak nobold"><td colspan="'.$colspan.'">';
			print $projectstatic->getNomUrl(1).' - '.dol_escape_htmltag($pdata['project_title']);
			print '</td></tr>';

			foreach ($pdata['tasks'] as $task) {
				$taskstatic->fetch((int)$task['task_id']); // ensure getNomUrl() ok

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

	// Action buttons
	print '<div class="tabsAction">';
	if ($permWrite) {
		print dolGetButtonAction('', $langs->trans("Modify"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit');
	}

	$useajax = !empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile);
	if ($useajax) {
		// AJAX confirm attached to #action-delete
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', '', 'action-delete', $permDelete);
	} else {
		// Non-AJAX fallback
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
	function updateWeekRange(){var v=$('#weekyear').val(),p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w),e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){if($.fn.select2)$('#weekyear').select2({width:'resolve'});updateWeekRange();$('#weekyear').on('change',updateWeekRange);});
})(jQuery);
</script>
JS;

llxFooter();
$db->close();
