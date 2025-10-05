<?php
/* Copyright (C) 2025
 * Pierre ARDOIN - Les Métiers du Bâtiment
 * GPL v3+
 */

// Dolibarr env
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
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr

$langs->loadLangs(array("timesheetweek@timesheetweek","other","projects"));

// ----------------------- Helpers -----------------------
if (!function_exists('tsw_format_hours')) {
	/**
	 * Format décimal -> HH:MM (toujours 00:00)
	 * @param float $dec
	 * @return string
	 */
	function tsw_format_hours($dec)
	{
		$dec = (float) $dec;
		if ($dec < 0) $dec = 0;
		$h = floor($dec);
		$m = round(($dec - $h) * 60);
		if ($m == 60) { $h++; $m = 0; }
		return str_pad((string)$h, 2, '0', STR_PAD_LEFT).':'.str_pad((string)$m, 2, '0', STR_PAD_LEFT);
	}
}
if (!function_exists('tsw_parse_hours')) {
	/**
	 * Parse "HH:MM" ou décimal -> décimal
	 * @param string $s
	 * @return float
	 */
	function tsw_parse_hours($s)
	{
		$s = trim((string)$s);
		if ($s === '') return 0.0;
		if (strpos($s, ':') !== false) {
			list($h,$m) = array_map('trim', explode(':', $s, 2));
			$h = (int) $h;
			$m = (int) $m;
			if ($m < 0) $m = 0;
			if ($m > 59) $m = 59;
			return (float) ($h + ($m/60));
		}
		$s = str_replace(',', '.', $s);
		return (float) $s;
	}
}

// ----------------------- Params & init -----------------------
$id     = GETPOSTINT('id');
$action = GETPOST('action','aZ09');

$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// Fetch
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions (utilise la nouvelle arborescence de droits du module)
$permRead        = $user->hasRight('timesheetweek','timesheetweek','read');
$permReadAll     = $user->hasRight('timesheetweek','timesheetweek','readAll');
$permReadChild   = $user->hasRight('timesheetweek','timesheetweek','readChild');
$permWrite       = $user->hasRight('timesheetweek','timesheetweek','write');
$permWriteAll    = $user->hasRight('timesheetweek','timesheetweek','writeAll');
$permWriteChild  = $user->hasRight('timesheetweek','timesheetweek','writeChild');
$permValidate    = $user->hasRight('timesheetweek','timesheetweek','validate');
$permValidateAll = $user->hasRight('timesheetweek','timesheetweek','validateAll');
$permValidateChild = $user->hasRight('timesheetweek','timesheetweek','validateChild');
$permValidateOwn = $user->hasRight('timesheetweek','timesheetweek','validateOwn');
$permDelete      = $user->hasRight('timesheetweek','timesheetweek','delete');
$permDeleteAll   = $user->hasRight('timesheetweek','timesheetweek','deleteAll');
$permDeleteChild = $user->hasRight('timesheetweek','timesheetweek','deleteChild');

if (!$permRead) accessforbidden();

// ----------------------- Helper permissions on object -----------------------
/**
 * @param TimesheetWeek $o
 * @param User $u
 * @return bool
 */
function tsw_user_can_read_object($o, $u, $permReadAll, $permReadChild, $permRead)
{
	if ($permReadAll) return true;
	if ($o->fk_user == $u->id && $permRead) return true;
	if ($permReadChild) {
		$childs = $u->getAllChildIds(1);
		if (is_array($childs) && in_array((int)$o->fk_user, $childs)) return true;
	}
	return false;
}
/**
 * @param TimesheetWeek $o
 * @param User $u
 * @return bool
 */
function tsw_user_can_write_object($o, $u, $permWriteAll, $permWriteChild, $permWrite)
{
	if ($permWriteAll) return true;
	if ($o->fk_user == $u->id && $permWrite) return true;
	if ($permWriteChild) {
		$childs = $u->getAllChildIds(1);
		if (is_array($childs) && in_array((int)$o->fk_user, $childs)) return true;
	}
	return false;
}
/**
 * @param TimesheetWeek $o
 * @param User $u
 * @return bool
 */
function tsw_user_can_validate_object($o, $u, $permValidateAll, $permValidateChild, $permValidate, $permValidateOwn)
{
	if ($permValidateAll) return true;
	if ($permValidate && $o->fk_user_valid > 0 && (int)$o->fk_user_valid === (int)$u->id) return true;
	if ($permValidateOwn && $o->fk_user == $u->id) return true;
	if ($permValidateChild) {
		$childs = $u->getAllChildIds(1);
		if (is_array($childs) && in_array((int)$o->fk_user, $childs)) return true;
	}
	return false;
}
/**
 * @param TimesheetWeek $o
 * @param User $u
 * @return bool
 */
function tsw_user_can_delete_object($o, $u, $permDeleteAll, $permDeleteChild, $permDelete)
{
	if ($permDeleteAll) return true;
	if ($o->fk_user == $u->id && $permDelete) return true;
	if ($permDeleteChild) {
		$childs = $u->getAllChildIds(1);
		if (is_array($childs) && in_array((int)$o->fk_user, $childs)) return true;
	}
	return false;
}

// ----------------------- Actions -----------------------

// Create
if ($action === 'add' && $permWrite) {
	$weekyear      = GETPOST('weekyear','alpha'); // YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note','restricthtml');

	$object->ref          = '(PROV)';
	$object->fk_user      = $fk_user > 0 ? $fk_user : $user->id;
	$object->fk_user_valid= $fk_user_valid > 0 ? $fk_user_valid : null;
	$object->note         = $note;
	$object->status       = TimesheetWeek::STATUS_DRAFT;

	if (preg_match('/^(\d{4})-W(\d{2})$/', (string)$weekyear, $m)) {
		$object->year = (int)$m[1];
		$object->week = (int)$m[2];
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
		$action = 'create';
	}

	if ($action === 'add') {
		$resc = $object->create($user);
		if ($resc > 0) {
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Save lines
if ($action === 'save' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');

	// Reload to have year/week
	if ($object->id <= 0) $object->fetch($id);

	if (!tsw_user_can_write_object($object, $user, $permWriteAll, $permWriteChild, $permWrite)) accessforbidden();

	$db->begin();

	// For each hours_*_Day
	foreach ($_POST as $k => $v) {
		if (preg_match('/^hours_(\d+)_(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/', $k, $m)) {
			$taskid = (int) $m[1];
			$dayname = $m[2];

			$hoursDec = tsw_parse_hours($v); // decimal hours

			// Compute date for the day of ISO week
			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week); // monday
			$offsetMap = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);
			$dto->modify('+'.$offsetMap[$dayname].' day');
			$daydate = $dto->format('Y-m-d');

			$zone = GETPOSTINT('zone_'.$dayname);
			if ($zone < 0) $zone = 0;
			if ($zone > 5) $zone = 5;
			$meal = GETPOST('meal_'.$dayname,'alpha') ? 1 : 0;

			// Does line exist?
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line";
			$sql .= " WHERE fk_timesheet_week=".(int)$object->id;
			$sql .= " AND fk_task=".(int)$taskid;
			$sql .= " AND day_date='".$db->escape($daydate)."'";
			$res = $db->query($sql);
			$lineid = 0;
			if ($res) {
				$o = $db->fetch_object($res);
				if ($o) $lineid = (int)$o->rowid;
				$db->free($res);
			}

			if ($hoursDec > 0) {
				if ($lineid > 0) {
					// update
					$upd = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line SET";
					$upd .= " hours=".(float)$hoursDec.", zone=".(int)$zone.", meal=".(int)$meal;
					$upd .= " WHERE rowid=".(int)$lineid;
					if (!$db->query($upd)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
						exit;
					}
				} else {
					// insert
					$ins = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line (entity, fk_timesheet_week, fk_task, day_date, hours, zone, meal)";
					$ins .= " VALUES (".(int)$conf->entity.", ".(int)$object->id.", ".(int)$taskid.", '".$db->escape($daydate)."', ".((float)$hoursDec).", ".(int)$zone.", ".(int)$meal.")";
					if (!$db->query($ins)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
						exit;
					}
				}
			} else {
				// hours == 0 -> delete if exists
				if ($lineid > 0) {
					$del = "DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE rowid=".(int)$lineid;
					if (!$db->query($del)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
						exit;
					}
				}
			}
		}
	}

	// Recompute totals
	$object->fetch($object->id);
	$object->updateTotalsInDB();

	$db->commit();

	setEventMessages($langs->trans("TimesheetSaved"), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Submit
if ($action === 'submit' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');
	if ($object->id <= 0) $object->fetch($id);
	if (!tsw_user_can_write_object($object, $user, $permWriteAll, $permWriteChild, $permWrite)) accessforbidden();

	$resS = $object->submit($user);
	if ($resS > 0) {
		setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	} else {
		setEventMessages($object->error ? $object->error : $langs->trans("Error"), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Back to draft
if ($action === 'backtodraft' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');
	if ($object->id <= 0) $object->fetch($id);
	if (!tsw_user_can_write_object($object, $user, $permWriteAll, $permWriteChild, $permWrite)) accessforbidden();

	$resB = $object->revertToDraft($user);
	if ($resB > 0) {
		setEventMessages($langs->trans("SetToDraft"), null, 'mesgs');
	} else {
		setEventMessages($object->error ? $object->error : $langs->trans("Error"), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Approve
if ($action === 'approve' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');
	if ($object->id <= 0) $object->fetch($id);
	if (!tsw_user_can_validate_object($object, $user, $permValidateAll, $permValidateChild, $permValidate, $permValidateOwn)) accessforbidden();

	$resA = $object->approve($user);
	if ($resA > 0) {
		setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
	} else {
		setEventMessages($object->error ? $object->error : $langs->trans("Error"), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Refuse
if ($action === 'refuse' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');
	if ($object->id <= 0) $object->fetch($id);
	if (!tsw_user_can_validate_object($object, $user, $permValidateAll, $permValidateChild, $permValidate, $permValidateOwn)) accessforbidden();

	$resR = $object->refuse($user);
	if ($resR > 0) {
		setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	} else {
		setEventMessages($object->error ? $object->error : $langs->trans("Error"), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Delete
if ($action === 'confirm_delete' && $id > 0) {
	if (!checkToken()) accessforbidden('CSRF token not valid');
	if ($object->id <= 0) $object->fetch($id);
	if (!tsw_user_can_delete_object($object, $user, $permDeleteAll, $permDeleteChild, $permDelete)) accessforbidden();

	$resD = $object->delete($user);
	if ($resD > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header('Location: '.dol_buildpath('/timesheetweek/timesheetweek_list.php',1));
		exit;
	} else {
		setEventMessages($object->error ? $object->error : $langs->trans("Error"), $object->errors, 'errors');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
}

// ----------------------- View -----------------------
$form = new Form($db);

$title = $langs->trans("TimesheetWeek");
if ($action === 'create') $title = $langs->trans("NewTimesheetWeek");

llxHeader('', $title, '', '', 0, 0, array(), array(), '', '');

if ($action === 'create') {
	if (!$permWrite) accessforbidden();

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'time');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$form->select_dolusers($user->id, 'fk_user', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.getWeekSelectorDolibarr($form, 'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td></tr>';
	print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$form->select_dolusers($user->id, 'fk_user_valid', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" rows="3" class="quatrevingtpercent"></textarea></td></tr>';
	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	print '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	// Week range JS
	print <<<JS
<script>
(function ($) {
	function parseYearWeek(val){var m=/^(\\d{4})-W(\\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;}
	function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
	function fmt(d){var dd=String(d.getUTCDate()).padStart(2,'0');var mm=String(d.getUTCMonth()+1).padStart(2,'0');var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
	function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
	$(function(){if($.fn.select2)$('#weekyear').select2({width:'resolve'});updateWeekRange();$('#weekyear').on('change',updateWeekRange);});
})(jQuery);
</script>
JS;

} elseif ($id > 0) {
	// Check read perms on this object
	if (!tsw_user_can_read_object($object, $user, $permReadAll, $permReadChild, $permRead)) accessforbidden();

	$head = array();
	$morehtmlright = $object->getLibStatut(5);

	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'time');

	// Banner
	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
	// Right zone already uses $morehtmlright for badge
	dol_banner_tab($object, 'ref', $linkback, 1, 'rowid', 'ref', '', '', 0, '', '', '', $morehtmlright);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';
	// Employé
	if ($object->fk_user > 0) {
		$u = new User($db); $u->fetch($object->fk_user);
		print '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>'.$u->getNomUrl(1).'</td></tr>';
	}
	// Année / Semaine
	print '<tr><td>'.$langs->trans("Year").'</td><td>'.dol_escape_htmltag($object->year).'</td></tr>';
	print '<tr><td>'.$langs->trans("Week").'</td><td>'.dol_escape_htmltag($object->week).'</td></tr>';
	// Note
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.nl2br(dol_escape_htmltag($object->note)).'</td></tr>';
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	// Totaux
	print '<tr><td class="titlefield">'.$langs->trans("TotalHours").'</td><td>'.tsw_format_hours((float)$object->total_hours).'</td></tr>';
	print '<tr><td>'.$langs->trans("Overtime").'</td><td>'.tsw_format_hours((float)$object->overtime_hours).'</td></tr>';
	// Dates
	print '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms,'dayhour').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation,'dayhour').'</td></tr>';
	// Validateur
	if ($object->fk_user_valid > 0) {
		$uv = new User($db); $uv->fetch($object->fk_user_valid);
		print '<tr><td>'.$langs->trans("Validator").'</td><td>'.$uv->getNomUrl(1).'</td></tr>';
	}
	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end(); // <<< END HEADER AREA

	// ---------------- Table of hours (below header) ----------------

	// Prepare week days
	$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
	$dto = new DateTime();
	$dto->setISODate((int)$object->year, (int)$object->week);
	$weekdates = array();
	foreach ($days as $d) {
		$weekdates[$d] = $dto->format('Y-m-d');
		$dto->modify('+1 day');
	}

	// Load existing lines into structures
	$object->fetchLines();
	$hoursByTaskByDay = array(); // [taskid][dayname] = "HH:MM"
	$zoneByDay = array();        // [dayname] = 0..5
	$mealByDay = array();        // [dayname] = 0/1

	foreach ($object->lines as $line) {
		$dayN = (int) date('N', strtotime($line->day_date)); // 1=Mon ..7=Sun
		$dn = $days[$dayN-1];
		if (!isset($hoursByTaskByDay[$line->fk_task])) $hoursByTaskByDay[$line->fk_task] = array();
		$hoursByTaskByDay[$line->fk_task][$dn] = tsw_format_hours((float)$line->hours);
		// Zone/meal per day: take first found if not set
		if (!isset($zoneByDay[$dn])) $zoneByDay[$dn] = (int)$line->zone;
		if (!isset($mealByDay[$dn])) $mealByDay[$dn] = (int)$line->meal;
		// If any line has meal 1, keep 1
		if ((int)$line->meal === 1) $mealByDay[$dn] = 1;
	}

	// Get tasks grouped by project for this user
	$tasks = $object->getAssignedTasks($object->fk_user);

	// Form for save
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';

	// Header row: days
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Project / Task").'</th>';
	foreach ($days as $d) {
		print '<th>'.$langs->trans(substr($d,0,3)).'<br><span class="opacitymedium">'.dol_print_date(strtotime($weekdates[$d]), 'day').'</span></th>';
	}
	print '<th>'.$langs->trans("Total").'</th>';
	print '</tr>';

	// Row: Zone + Meal
	print '<tr class="liste_titre">';
	print '<td></td>';
	foreach ($days as $d) {
		$curZone = isset($zoneByDay[$d]) ? (int)$zoneByDay[$d] : 0;
		$curMeal = !empty($mealByDay[$d]) ? 1 : 0;
		print '<td class="center">';
		print '<div class="opacitymedium">'.$langs->trans("Zone").'</div>';
		print '<select name="zone_'.$d.'" class="flat">';
		for ($z=0;$z<=5;$z++) {
			print '<option value="'.$z.'"'.($z===$curZone?' selected':'').'>'.$z.'</option>';
		}
		print '</select><br>';
		print '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.($curMeal?' checked':'').'> '.$langs->trans("Meal").'</label>';
		print '</td>';
	}
	print '<td></td>';
	print '</tr>';

	// Group by project
	$byproject = array();
	foreach ($tasks as $t) {
		$pid = (int) $t['project_id'];
		if (empty($byproject[$pid])) {
			$byproject[$pid] = array(
				'ref' => $t['project_ref'],
				'title' => $t['project_title'],
				'tasks' => array()
			);
		}
		$byproject[$pid]['tasks'][] = $t;
	}

	$projectstatic = new Project($db);
	$taskstatic = new Task($db);

	// Rows
	foreach ($byproject as $pid => $pdata) {
		// Project line: single cell, classes per perweek style
		print '<tr class="oddeven trforbreak nobold">';
		print '<td colspan="'.(2 + count($days)).'">';
		// Project getNomUrl
		$projectstatic->id = $pid;
		$projectstatic->ref = $pdata['ref'];
		$projectstatic->title = $pdata['title'];
		print $projectstatic->getNomUrl(1, '', 0, 'classfortooltip');
		print ' &nbsp; <span class="opacitymedium">'.dol_escape_htmltag($pdata['title']).'</span>';
		print '</td>';
		print '</tr>';

		// Task lines
		foreach ($pdata['tasks'] as $t) {
			$taskId = (int) $t['task_id'];
			print '<tr>';
			print '<td class="paddingleft">';
			$taskstatic->id = $taskId;
			$taskstatic->label = $t['task_label'];
			$taskstatic->ref = ''; // tasks usually no ref, getNomUrl will use label
			print $taskstatic->getNomUrl(1);
			print '</td>';
			$rowtotal = 0.0;

			foreach ($days as $d) {
				$name = 'hours_'.$taskId.'_'.$d;
				$val = isset($hoursByTaskByDay[$taskId][$d]) ? $hoursByTaskByDay[$taskId][$d] : '00:00';
				print '<td class="center"><input type="text" class="flat hourinput" size="5" name="'.$name.'" value="'.$val.'" placeholder="00:00"></td>';
				$rowtotal += tsw_parse_hours($val);
			}
			print '<td class="right task-total">'.tsw_format_hours($rowtotal).'</td>';
			print '</tr>';
		}
	}

	// Totals rows
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

	// Contracted hours from employee
	$userEmployee = new User($db);
	$userEmployee->fetch($object->fk_user);
	$contractedHours = (!empty($userEmployee->weeklyhours) ? (float)$userEmployee->weeklyhours : 35.0);

	print '<tr class="liste_total">';
	print '<td class="right">'.$langs->trans("Overtime").' (>'.tsw_format_hours($contractedHours).')</td>';
	print '<td colspan="'.count($days).'" class="right overtime-total">00:00</td>';
	print '<td></td>';
	print '</tr>';

	print '</table>';
	print '</div>';

	// Save button
	if (tsw_user_can_write_object($object, $user, $permWriteAll, $permWriteChild, $permWrite)) {
		print '<div class="center margintoponly"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
	}
	print '</form>';

	// ---------------- Buttons actions ----------------
	print '<div class="tabsAction">';

	// Submit visible only if at least one line
	$hasline = $object->hasAtLeastOneLine();

	if ($object->status == TimesheetWeek::STATUS_DRAFT) {
		if ($hasline && tsw_user_can_write_object($object,$user,$permWriteAll,$permWriteChild,$permWrite)) {
			print dolGetButtonAction('', $langs->trans("Submit"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit&token='.newToken(), '', false);
		}
	}
	if ($object->status == TimesheetWeek::STATUS_SUBMITTED) {
		// Retour brouillon (autorisé)
		if (tsw_user_can_write_object($object,$user,$permWriteAll,$permWriteChild,$permWrite)) {
			print dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backtodraft&token='.newToken(), '', false);
		}
		// Approve / Refuse
		if (tsw_user_can_validate_object($object, $user, $permValidateAll, $permValidateChild, $permValidate, $permValidateOwn)) {
			print dolGetButtonAction('', $langs->trans("Approve"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=approve&token='.newToken(), '', false);
			print dolGetButtonAction('', $langs->trans("Refuse"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refuse&token='.newToken(), '', false);
		}
		// Delete allowed too
		if (tsw_user_can_delete_object($object,$user,$permDeleteAll,$permDeleteChild,$permDelete)) {
			print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete', '', false);
		}
	}
	if ($object->status == TimesheetWeek::STATUS_APPROVED || $object->status == TimesheetWeek::STATUS_REFUSED) {
		// Keep delete and back to draft available if allowed
		if (tsw_user_can_write_object($object,$user,$permWriteAll,$permWriteChild,$permWrite)) {
			print dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=backtodraft&token='.newToken(), '', false);
		}
		if (tsw_user_can_delete_object($object,$user,$permDeleteAll,$permDeleteChild,$permDelete)) {
			print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete', '', false);
		}
	}
	print '</div>';

	// Confirm delete popup
	if ($action === 'delete') {
		print $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("DeleteTimesheet"),
			$langs->trans("ConfirmDeleteObject"),
			'confirm_delete',
			array(),
			'no',
			1
		);
	}

	// ---------------- JS totals ----------------
	$js = <<<JS
<script>
(function($){
	function parseHours(val){
		if(!val) return 0;
		if(val.indexOf(':')===-1) return parseFloat(val)||0;
		var p=val.split(':'), h=parseInt(p[0],10)||0, m=parseInt(p[1],10)||0;
		if(m<0)m=0; if(m>59)m=59;
		return h + m/60;
	}
	function formatHours(dec){
		if(isNaN(dec)||dec<0) dec=0;
		var h=Math.floor(dec), m=Math.round((dec-h)*60);
		if(m===60){h++;m=0;}
		return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
	}
	function updateTotals(){
		var grand=0;
		// reset day totals
		$('td.day-total').text('00:00');
		// by row
		$('table input.hourinput').closest('tr').each(function(){
			var rowtot=0;
			$(this).find('input.hourinput').each(function(){
				var v=parseHours($(this).val());
				if(v>0){
					rowtot+=v; grand+=v;
					var td=$(this).closest('td'), idx=td.index();
					var cell=$('tr.liste_total:first td').eq(idx);
					var cur=parseHours(cell.text());
					cell.text(formatHours(cur+v));
				}
			});
			$(this).find('.task-total').text(formatHours(rowtot));
		});
		$('.grand-total').text(formatHours(grand));
		$('.meal-total').text($('.mealbox:checked').length);
		var weekly = %s;
		var ot = grand - weekly;
		if(ot<0) ot=0;
		$('.overtime-total').text(formatHours(ot));
	}
	$(function(){
		updateTotals();
		$(document).on('input change','input.hourinput, input.mealbox', updateTotals);
	});
})(jQuery);
</script>
JS;
	print sprintf($js, (float)$contractedHours);
}

// Footer
llxFooter();
$db->close();
