<?php
/* Copyright (C) 2025-2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \\file tpl/timesheetweek_card_desktop.tpl.php
 * \\brief Desktop weekly input grid.
 */

if (!defined('DOL_VERSION')) {
	exit;
}

		if (empty($tasks)) {
			echo '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
		} else {
		// Heures contractuelles
		$contractedHours = $contractedHoursDisp;

		// Inputs zone/panier bloqués si statut != brouillon
		$disabledAttr = ($object->status != tw_status('draft')) ? ' disabled' : '';
		$canOverrideHolidayLock = tw_can_override_holiday_lock($user);
		$holidayMarkerByDay = tw_get_holiday_markers_by_day($db, $object->fk_user, $weekdates, $langs, !empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

		echo '<div class="div-table-responsive grille-saisie-temps-wrapper">';
		// EN: Scope the vertical and horizontal centering helper to the specific cells that need alignment (days/zones/baskets/hours/totals).
		echo '<style>';
		echo ':root { --tw-grid-top-gap: 0px; }';
		echo '.grille-saisie-temps-wrapper { max-height: 70vh; overflow-y: auto; overflow-x: auto; position: relative; }';
		echo '.grille-saisie-temps-wrapper.sticky-active { max-height: calc(100vh - var(--tw-grid-top-gap, 0px)); position: sticky; top: var(--tw-grid-top-gap, 0px); z-index: 4; }';
		echo '.grille-saisie-temps .cellule-jour,';
		echo '.grille-saisie-temps .cellule-zone-panier,';
		echo '.grille-saisie-temps .cellule-temps,';
		echo '.grille-saisie-temps .cellule-total { vertical-align: middle; text-align: center; }';
		echo '.grille-saisie-temps .col-project-task { position: sticky; left: 0; z-index: 20; }';
		echo '.grille-saisie-temps .col-summary-sticky { position: sticky; left: 0; z-index: 8; }';
		echo '.grille-saisie-temps .liste_titre .col-project-task { z-index: 21; }';
		echo '.grille-saisie-temps .liste_titre .col-summary-sticky { z-index: 9; }';
		echo '.grille-saisie-temps .trforbreak .col-summary-sticky { z-index: 8; }';
		echo '.grille-saisie-temps .trforbreak .col-project-task-filler {}';
		echo '.grille-saisie-temps .col-total { position: sticky; right: 0; z-index: 6; }';
		echo '.grille-saisie-temps .liste_titre .col-total { z-index: 10; }';
		echo '.grille-saisie-temps .sticky-header th { position: sticky; top: 0; z-index: 12; }';
		echo '</style>';
		// EN: Keep the grid flush with the top menu while the bottom of the table is still outside the viewport.
		echo '<script>';
		echo '(function($){';
		echo '$(function(){';
		echo "\tvar \$wrapper = $('.grille-saisie-temps-wrapper');";
		echo "\tif (!\$wrapper.length) { return; }";
		echo "\tvar currentTopGap = 0;";
		echo "\tfunction updateGridTopGap() {";
		echo "\t\tvar selectors = ['#id-top-menu', '#id-top', '#mainmenu', 'header.navbar', 'header.navbar-fixed-top', 'header'];";
		echo "\t\tvar topGap = 0;";
		echo "\t\tfor (var i = 0; i < selectors.length; i++) {";
		echo "\t\t\tvar \$candidate = $(selectors[i]);";
		echo "\t\t\tif (\$candidate.length && \$candidate.first().outerHeight()) {";
		echo "\t\t\t\ttopGap = \$candidate.first().outerHeight();";
		echo "\t\t\t\tbreak;";
		echo "\t\t\t}";
		echo "\t\t}";
		echo "\t\tcurrentTopGap = topGap;";
		echo "\t\tdocument.documentElement.style.setProperty('--tw-grid-top-gap', topGap + 'px');";
		echo "\t}";
		echo "\tfunction toggleStickyState(isSticky) {";
		echo "\t\tif (isSticky) {";
		echo "\t\t\t\$wrapper.addClass('sticky-active');";
		echo "\t\t\tvar maxHeight = 'calc(100vh - ' + currentTopGap + 'px)';";
		echo "\t\t\t\$wrapper.css('max-height', maxHeight);";
		echo "\t\t} else {";
		echo "\t\t\t\$wrapper.removeClass('sticky-active');";
		echo "\t\t\t\$wrapper.css('max-height', '70vh');";
		echo "\t\t}";
		echo "\t}";
		echo "\tfunction updateStickyPosition() {";
		echo "\t\tif (!\$wrapper.length) { return; }";
		echo "\t\tvar rect = \$wrapper[0].getBoundingClientRect();";
		echo "\t\tvar bottomVisible = rect.bottom <= window.innerHeight;";
		echo "\t\ttoggleStickyState(!bottomVisible);";
		echo "\t}";
		echo "\tupdateGridTopGap();";
		echo "\tupdateStickyPosition();";
		echo "\t$(window).on('resize scroll', function(){";
		echo "\t\tupdateGridTopGap();";
		echo "\t\tupdateStickyPosition();";
		echo "\t});";
		echo '});';
		echo '})(jQuery);';
		echo '</script>';
		echo '<table class="noborder centpercent grille-saisie-temps">';

				// EN: Apply the vertical-centering helper on each day header to keep labels visually aligned.
				// Header jours
echo '<tr class="liste_titre sticky-header">';
echo '<th class="col-project-task">'.$langs->trans("ProjectTaskColumn").'</th>';
				foreach ($days as $d) {
						// EN: Render day headers safely even if week dates are undefined.
						// FR: Affiche les en-têtes de jours en sécurité même sans dates de semaine définies.
						$labelDate = '';
						if (!empty($weekdates[$d])) {
								$tmpTs = strtotime($weekdates[$d]);
								if ($tmpTs !== false) {
										$labelDate = dol_print_date($tmpTs, 'day');
								}
						}
						$dayLabelKey = isset($dayLabelKeys[$d]) ? $dayLabelKeys[$d] : $d;
						// EN: Translate the full day name to avoid ambiguous abbreviations.
						// FR: Traduit le nom complet du jour pour éviter les abréviations ambiguës.
						$dayLabel = $langs->trans($dayLabelKey);
						echo '<th class="cellule-jour">'.$dayLabel;
						if ($labelDate !== '') {
								echo '<br><span class="opacitymedium">'.$labelDate.'</span>';
						}
						echo '</th>';
				}
echo '<th class="center cellule-total">'.$langs->trans("Total").'</th>';
echo '</tr>';

				// EN: Add the vertical-centering helper on zone and meal cells so both controls stay centered whatever their height.
				// FR: Ajoute l'aide de centrage vertical sur les cellules zone et repas afin que les deux contrôles restent centrés quelle que soit leur hauteur.
// Ligne zone + panier (préfills depuis lignes)
if (!$isDailyRateEmployee) {
echo '<tr class="liste_titre">';
echo '<td class="col-project-task"></td>';
foreach ($days as $d) {
	$dayDisabledAttr = $disabledAttr;
	$hasLockedLeaveDay = !empty($holidayMarkerByDay[$d]['has_leave']) && empty($holidayMarkerByDay[$d]['is_public_holiday']);
	if ($object->status == tw_status('draft') && $hasLockedLeaveDay && !$canOverrideHolidayLock) {
		$dayDisabledAttr = ' disabled';
	}
// EN: Attach the vertical-centering helper to keep both zone selector and meal checkbox aligned.
// FR: Attache l'aide de centrage vertical pour garder alignés le sélecteur de zone et la case repas.
echo '<td class="center cellule-zone-panier">';
// EN: Prefix zone selector with its label to improve understanding.
// FR: Préfixe le sélecteur de zone avec son libellé pour améliorer la compréhension.
echo '<span class="zone-select">'.$langs->trans("Zone").' ';
echo '<select name="zone_'.$d.'" class="flat"'.$dayDisabledAttr.'>';
// EN: Provide an empty choice so the default zone selector starts blank.
// FR: Propose un choix vide pour que le sélecteur de zone soit vide par défaut.
$selEmpty = ($dayZone[$d] === null || $dayZone[$d] === '') ? ' selected' : '';
echo '<option value=""'.$selEmpty.'></option>';
for ($z = 1; $z <= 5; $z++) {
$sel = ($dayZone[$d] !== null && (int) $dayZone[$d] === $z) ? ' selected' : '';
echo '<option value="'.$z.'"'.$sel.'>'.$z.'</option>';
}
echo '</select></span><br>';
$checked = $dayMeal[$d] ? ' checked' : '';
echo '<label><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.$checked.$dayDisabledAttr.'> '.$langs->trans("Meal").'</label>';
echo '</td>';
}
echo '<td class=""></td>';
echo '</tr>';
}

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
$dailyRateOptions = array();
if ($isDailyRateEmployee) {
	if ($useQuarterDayDailyContract) {
		// EN: Provide fractional-day labels when the quarter-day constant is enabled.
		// FR: Fournit les libellés fractionnaires lorsque la constante quart de jour est activée.
		$dailyRateOptions = array(
			4 => $langs->trans('TimesheetWeekDailyRateQuarterDay'),
			2 => $langs->trans('TimesheetWeekDailyRateHalfDay'),
			1 => $langs->trans('TimesheetWeekDailyRateOneDay'),
		);
		if ($hasLegacyHalfDayDailyRate && empty($dailyRateOptions[3])) {
			// EN: Keep the historical afternoon code selectable to display past data consistently.
			// FR: Maintient le code historique de l'après-midi pour afficher les données passées de manière cohérente.
			$dailyRateOptions[3] = $langs->trans('TimesheetWeekDailyRateHalfDay');
		}
	} else {
		// EN: Prepare localized labels for each forfait-jour choice.
		// FR: Prépare les libellés localisés pour chaque choix de forfait jour.
		$dailyRateOptions = array(
			1 => $langs->trans('TimesheetWeekDailyRateFullDay'),
			2 => $langs->trans('TimesheetWeekDailyRateMorning'),
			3 => $langs->trans('TimesheetWeekDailyRateAfternoon'),
		);
	}
}
foreach ($byproject as $pid => $pdata) {
			// Ligne projet
echo '<tr class="oddeven trforbreak nobold">';
$colspanRemaining = count($days) + 1;
echo '<td class="col-project-task col-summary-sticky">';
$proj = new Project($db);
$proj->fetch($pid);
			if (empty($proj->ref)) { $proj->ref = $pdata['ref']; $proj->title = $pdata['title']; }
						echo tw_get_project_nomurl($proj, 1);
			echo '</td>';
echo '<td colspan="'.$colspanRemaining.'" class="col-project-task-filler"></td>';
			echo '</tr>';

			// Tâches
foreach ($pdata['tasks'] as $task) {
echo '<tr>';
echo '<td class="paddingleft col-project-task">';
$tsk = new Task($db);
$tsk->fetch((int)$task['task_id']);
if (empty($tsk->label)) { $tsk->id = (int)$task['task_id']; $tsk->ref = $task['task_ref'] ?? ''; $tsk->label = $task['task_label']; }
echo tw_get_task_nomurl($tsk, 1);
echo '</td>';

$rowTotal = 0.0;
foreach ($days as $d) {
// EN: Attach the vertical-centering helper to each time entry cell for consistent layouts.
// FR: Attache l'aide de centrage vertical à chaque cellule de temps pour des mises en page cohérentes.
$iname = 'hours_'.$task['task_id'].'_'.$d;
$rateName = 'daily_'.$task['task_id'].'_'.$d;
$val = '';
$rateVal = 0;
$dayPlaceholder = '00:00';
$hasHolidayDayMarker = !empty($holidayMarkerByDay[$d]['label']);
$isPublicHolidayLeaveDay = !empty($holidayMarkerByDay[$d]['is_public_holiday']);
$daySelectEmptyLabel = '';
$daySelectEmptySelected = ' selected';
$daySelectEmptyValue = '';
$daySelectEmptyDisabled = '';
$daySelectEmptyHidden = '';
$daySelectEmptyTitle = '';
$daySelectTitleAttr = '';
$isHolidayLockedDay = ($object->status == tw_status('draft') && $hasHolidayDayMarker && !$isPublicHolidayLeaveDay && !$canOverrideHolidayLock);
if ($hasHolidayDayMarker) {
	$dayPlaceholder = $holidayMarkerByDay[$d]['label'];
	$daySelectEmptyLabel = $dayPlaceholder;
	$daySelectTitleAttr = ' title="'.dol_escape_htmltag($dayPlaceholder).'"';
}
if ($isHolidayLockedDay) {
	$daySelectEmptyValue = '__holiday__';
	$daySelectEmptyDisabled = ' disabled';
	$daySelectEmptyHidden = ' hidden';
	$daySelectEmptyTitle = ' data-dayplaceholder="'.dol_escape_htmltag($dayPlaceholder).'"';
}
$keydate = $weekdates[$d];
if (isset($hoursBy[(int)$task['task_id']][$keydate])) {
$val = formatHours($hoursBy[(int)$task['task_id']][$keydate]);
$rowTotal += (float)$hoursBy[(int)$task['task_id']][$keydate];
}
if (isset($dailyRateBy[(int)$task['task_id']][$keydate])) {
$rateVal = (int)$dailyRateBy[(int)$task['task_id']][$keydate];
}
if ($isDailyRateEmployee) {
$disabledSelect = ($object->status != tw_status('draft')) ? ' disabled' : '';
$disabledSelect = $isHolidayLockedDay ? ' disabled' : $disabledSelect;
$selectHtml = '<select name="'.$rateName.'" class="flat daily-rate-select"'.$disabledSelect.$daySelectTitleAttr.'>';
if ($rateVal > 0) {
	$daySelectEmptySelected = '';
}
$selectHtml .= '<option value="'.$daySelectEmptyValue.'"'.$daySelectEmptySelected.$daySelectEmptyDisabled.$daySelectEmptyHidden.$daySelectEmptyTitle.'>'.dol_escape_htmltag($daySelectEmptyLabel).'</option>';
foreach ($dailyRateOptions as $code => $label) {
$selected = ($rateVal === (int) $code) ? ' selected' : '';
$selectHtml .= '<option value="'.$code.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
}
$selectHtml .= '</select>';
echo '<td class="center cellule-temps">'.$selectHtml.'</td>';
		} else {
$readonly = ($object->status != tw_status('draft')) ? ' readonly' : '';
$readonly = $isHolidayLockedDay ? '' : $readonly;
$inputDisabled = $isHolidayLockedDay ? ' disabled' : '';
echo '<td class="center cellule-temps"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($val).'" placeholder="'.dol_escape_htmltag($dayPlaceholder).'"'.$readonly.$inputDisabled.'></td>';
}
}
$grandInit += $rowTotal;
// EN: Center task totals so they stay aligned with other centered figures.
// FR: Centre les totaux de tâche pour les garder alignés avec les autres valeurs centrées.
if ($isDailyRateEmployee) {
echo '<td class="center task-total cellule-total">'.tw_format_days(($rowTotal > 0 ? ($rowTotal / 8.0) : 0.0), $langs).'</td>';
} else {
echo '<td class="center task-total cellule-total">'.formatHours($rowTotal).'</td>';
}
echo '</tr>';
}
}

$grand = ($object->total_hours > 0 ? (float) $object->total_hours : $grandInit);

if ($isDailyRateEmployee) {
$grandDays = ($grand > 0 ? ($grand / 8.0) : 0.0);
echo '<tr class="liste_total row-total-days">';
// EN: Center overall totals expressed in days for forfait jour employees.
// FR: Centre les totaux globaux exprimés en jours pour les salariés au forfait jour.
echo '<td class="left">'.$langs->trans("TimesheetWeekTotalDays").'</td>';
foreach ($days as $d) {
echo '<td class="center day-total cellule-total">'.tw_format_days(0, $langs).'</td>';
}
echo '<td class="center grand-total cellule-total">'.tw_format_days($grandDays, $langs).'</td>';
echo '</tr>';
} else {
echo '<tr class="liste_total row-total-hours">';
// EN: Center overall totals and daily sums for consistent middle alignment.
// FR: Centre les totaux généraux et journaliers pour un alignement médian homogène.
echo '<td class="left col-project-task col-summary-sticky">'.$langs->trans("Total").'</td>';
foreach ($days as $d) {
echo '<td class="center day-total cellule-total">00:00</td>';
}
echo '<td class="center grand-total cellule-total">'.formatHours($grand).'</td>';
echo '</tr>';

echo '<tr class="liste_total">';
// EN: Center meal counters to match the rest of the grid alignment.
// FR: Centre les compteurs de repas pour correspondre au reste de l'alignement de la grille.
echo '<td class="left col-project-task col-summary-sticky">'.$langs->trans("Meals").'</td>';
$initMeals = array_sum($dayMeal);
echo '<td colspan="'.count($days).'" class="cellule-total"></td>';
echo '<td class="left meal-total cellule-total">'.$initMeals.'</td>';
echo '</tr>';

echo '<tr class="liste_total">';
// EN: Center overtime summary cells so every footer row follows the same alignment pattern.
// FR: Centre les cellules du récapitulatif des heures supplémentaires pour harmoniser l'alignement de chaque ligne de pied.
echo '<td class="left col-project-task col-summary-sticky">'.$langs->trans("Overtime").' ('.formatHours($contractedHours).')</td>';
$ot = ($object->overtime_hours > 0 ? (float) $object->overtime_hours : max(0.0, $grand - $contractedHours));
echo '<td colspan="'.count($days).'"class="cellule-total"></td>';
echo '<td class="center overtime-total cellule-total">'.formatHours($ot).'</td>';
echo '</tr>';
}

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
		// EN: Use nowdoc to prevent PHP from stripping the JavaScript variable sigils.
		// FR: Utilise nowdoc pour empêcher PHP de retirer les sigles de variables JavaScript.
		$jsGrid = <<<'JS'
<script>
(function($){
var isDailyRateMode = %s;
var dailyRateHoursMap = %s;
var weeklyContract = %s;
function parseHours(v){
	if(!v) return 0;
	if(v.indexOf(":") === -1) return parseFloat(v)||0;
	var p=v.split(":"); var h=parseInt(p[0],10)||0; var m=parseInt(p[1],10)||0;
	return h + (m/60);
}
function elementHours($el){
	if(isDailyRateMode && $el.is('select')){
		var code=parseInt($el.val(),10);
		return dailyRateHoursMap[code] ? dailyRateHoursMap[code] : 0;
	}
	return parseHours($el.val());
}
function elementDays($el){
	// EN: Convert the hour contribution to days with a fixed 8h reference.
	// FR: Convertit la contribution horaire en jours sur la base fixe de 8h.
	return elementHours($el) / 8;
}
function formatHours(d){
	if(isNaN(d)) return "00:00";
	var h=Math.floor(d); var m=Math.round((d-h)*60);
	if(m===60){ h++; m=0; }
	// EN: Build HH:MM strings without padStart to work on legacy browsers.
	// FR: Construit les chaînes HH:MM sans padStart pour fonctionner sur les anciens navigateurs.
	var hh=(h<10?"0":"")+h;
	var mm=(m<10?"0":"")+m;
	return hh+":"+mm;
}
function formatDays(d){
	if(isNaN(d)) return "0.00";
	return (Math.round(d*100)/100).toFixed(2);
}
function updateTotals(){
	var totalRowSelector = isDailyRateMode ? ".row-total-days" : ".row-total-hours";
	var formatFn = isDailyRateMode ? formatDays : formatHours;
	var elementFn = isDailyRateMode ? elementDays : elementHours;
	var grand=0;
	var dayTotals=[];

	// EN: Reset per-task and per-day totals before recomputing the grid.
	// FR: Réinitialise les totaux par tâche et par jour avant de recalculer la grille.
	$(".task-total").text(formatFn(0));
	$(totalRowSelector+" .day-total").each(function(idx){
		dayTotals[idx]=0;
		$(this).text(formatFn(0));
	});
	$(totalRowSelector+" .grand-total").text(formatFn(0));

	$("table.noborder tr").each(function(){
		var rowT=0;
		$(this).find("input.hourinput, select.daily-rate-select").each(function(){
			var v=elementFn($(this));
			if(v>0){
				rowT+=v;
				// EN: Align the day counter with the footer cells by skipping the label column.
				// FR: Aligne le compteur journalier sur les cellules du pied en ignorant la colonne du libellé.
				var idx=$(this).closest("td").index()-1;
				if(idx>=0 && typeof dayTotals[idx]!=="undefined"){
					dayTotals[idx]+=v;
				}
				grand+=v;
			}
		});
		if(rowT>0) $(this).find(".task-total").text(formatFn(rowT));
	});

	// EN: Reflect the new per-day totals after iterating over every input cell.
	// FR: Répercute les nouveaux totaux journaliers après l'analyse de chaque cellule de saisie.
	$(totalRowSelector+" .day-total").each(function(idx){
		$(this).text(formatFn(dayTotals[idx]));
	});

	$(totalRowSelector+" .grand-total").text(formatFn(grand));

if(isDailyRateMode){
$(".meal-total").text('0');
		} else {
var meals = $(".mealbox:checked").length;
$(".meal-total").text(meals);
var ot = grand - weeklyContract; if (ot < 0) ot = 0;
$(".overtime-total").text(formatFn(ot));
if($(".header-overtime").length){
$(".header-overtime").text(formatFn(ot));
}
}

// met à jour l'entête
$(".header-total-main").text(formatFn(grand));
}
	$(function(){
		updateTotals(); // au chargement
		$(document).on("input change", "input.hourinput, select.daily-rate-select, input.mealbox", updateTotals);
	});
})(jQuery);
</script>
JS;
				// EN: Reuse the PHP hour map so JavaScript mirrors the backend conversions (quarter-day included).
				// FR: Réutilise la correspondance horaire PHP pour que JavaScript reflète les conversions (quart de jour inclus).
				$jsDailyRateHoursMap = tw_get_daily_rate_hours_map($useQuarterDayDailyContract);
				$jsGrid = sprintf(
					$jsGrid,
					$isDailyRateEmployee ? 'true' : 'false',
					json_encode($jsDailyRateHoursMap),
					json_encode((float) price2num($contractedHours, '6'))
				);
		echo $jsGrid;
	}

