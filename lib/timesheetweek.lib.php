<?php
/* Copyright (C) 2025		Pierre ARDOIN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    timesheetweek/lib/timesheetweek.lib.php
 * \ingroup timesheetweek
 * \brief   Library files with common functions for TimesheetWeek
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function timesheetweekAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("timesheetweek@timesheetweek");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/timesheetweek/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/timesheetweek/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/timesheetweek/admin/myobjectline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/timesheetweek/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@timesheetweek:/timesheetweek/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@timesheetweek:/timesheetweek/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'timesheetweek@timesheetweek');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'timesheetweek@timesheetweek', 'remove');

	return $head;
}

/**
 * Prepare array of tabs for TimesheetWeek
 *
 * @param	TimesheetWeek	$object					TimesheetWeek
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function timesheetweekPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("timesheetweek@timesheetweek");

	$showtabofpagecontact = 1;
	$showtabofpagenote = 1;
	$showtabofpagedocument = 1;
	$showtabofpageagenda = 1;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/timesheetweek/timesheetweek_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("TimesheetWeek");
	$head[$h][2] = 'card';
	$h++;

	if ($showtabofpagecontact) {
		$head[$h][0] = dol_buildpath("/timesheetweek/timesheetweek_contact.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans("Contacts");
		$head[$h][2] = 'contact';
		$h++;
	}

	if ($showtabofpagenote) {
		if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
			$nbNote = 0;
			if (!empty($object->note_private)) {
				$nbNote++;
			}
			if (!empty($object->note_public)) {
				$nbNote++;
			}
			$head[$h][0] = dol_buildpath('/timesheetweek/timesheetweek_note.php', 1).'?id='.$object->id;
			$head[$h][1] = $langs->trans('Notes');
			if ($nbNote > 0) {
				$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
			}
			$head[$h][2] = 'note';
			$h++;
		}
	}

	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
		$upload_dir = $conf->timesheetweek->dir_output."/timesheetweek/".dol_sanitizeFileName($object->ref);
		$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dol_buildpath("/timesheetweek/timesheetweek_document.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}

	if ($showtabofpageagenda) {
		$head[$h][0] = dol_buildpath("/timesheetweek/timesheetweek_agenda.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@timesheetweek:/timesheetweek/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@timesheetweek:/timesheetweek/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'timesheetweek@timesheetweek');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'timesheetweek@timesheetweek', 'remove');

	return $head;
}

/**
 * Génère un <select> listant les semaines de l'année courante
 * avec leur numéro + plage du/au
 *
 * @param string $htmlname Nom du champ select
 * @param int|null $selected Semaine sélectionnée par défaut
 * @param int|null $year Année (par défaut année courante)
 * @return string
 */
function getWeekSelector($htmlname, $selected = null, $year = null)
{
	global $langs;

	if (empty($year)) $year = date('o'); // année ISO

	if (empty($selected)) $selected = date('W'); // semaine courante

	$out = '<select name="'.$htmlname.'" id="'.$htmlname.'">';

	// On boucle sur 52 semaines
	for ($week = 1; $week <= 53; $week++) {
		$dto = new DateTime();
		$dto->setISODate($year, $week); // Lundi de la semaine ISO
		$start = $dto->format('d/m/Y');
		$dto->modify('+6 days');
		$end = $dto->format('d/m/Y');

		$label = $langs->trans("Week")." ".$week." (du ".$start." au ".$end.")";
		$val = $year.'-W'.str_pad($week, 2, '0', STR_PAD_LEFT);

		$out .= '<option value="'.$val.'"'.($week == $selected ? ' selected' : '').'>'.$label.'</option>';
	}

	$out .= '</select>';

	return $out;
}

/**
 * Génère un <select> listant les semaines de l'année courante
 * au format visuel Dolibarr (classe flat + select2)
 *
 * @param Form   $form      Objet Form Dolibarr
 * @param string $htmlname  Nom du champ select
 * @param int    $selected  Numéro de semaine par défaut
 * @param int    $year      Année (par défaut année courante)
 * @return string
 */
function getWeekSelectorDolibarr($form, $htmlname, $selected = 0, $year = 0)
{
	global $langs;

	if (empty($year)) $year = (int) date('o');     // Année ISO
	if (empty($selected)) $selected = (int) date('W'); // Semaine ISO courante

	$out = '<select class="flat minwidth200" name="'.$htmlname.'" id="'.$htmlname.'">';

	for ($week = 1; $week <= 53; $week++) {
		$dto = new DateTime();
		$dto->setISODate($year, $week); // Lundi de la semaine
		$start = dol_print_date($dto->getTimestamp(), 'day');
		$dto->modify('+6 days');
		$end = dol_print_date($dto->getTimestamp(), 'day');

		$label = $langs->trans("Week").' '.$week.' ('.$start.' → '.$end.')';
		$val = $year.'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT);

		$out .= '<option value="'.$val.'"'.($week === (int) $selected ? ' selected' : '').'>'.$label.'</option>';
	}

	$out .= '</select>';

	return $out;
}

function timesheetweekShowPerWeek($db, $userId, $year, $week)
{
    global $langs, $form;

    $langs->load("timesheetweek@timesheetweek");

    // Calcul des dates de la semaine ISO
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $start = clone $dto;
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = clone $dto;
        $dto->modify('+1 day');
    }

    // --- Construction tableau ---
    $out = '<div class="div-table-responsive">';
    $out .= '<table class="noborder centpercent">';

    // Ligne entêtes avec jours
    $out .= '<tr class="liste_titre">';
    $out .= '<th>'.$langs->trans("Task").'</th>';
    foreach ($days as $d) {
        $out .= '<th style="text-align:center;">'.dol_print_date($d->getTimestamp(), "%a %d/%m").'</th>';
    }
    $out .= '</tr>';

    // Ligne options Panier / Déplacement
    $out .= '<tr class="liste_titre">';
    $out .= '<td>'.$langs->trans("Options").'</td>';
    foreach ($days as $i => $d) {
        $daykey = $d->format("Y-m-d");

        // Déplacement (1 à 5)
        $out .= '<td style="text-align:center;">';
        $out .= '<select name="zone_'.$daykey.'" class="flat">';
        for ($z = 1; $z <= 5; $z++) {
            $out .= '<option value="'.$z.'">'.$z.'</option>';
        }
        $out .= '</select><br>';

        // Panier repas
        $out .= '<label><input type="checkbox" name="panier_'.$daykey.'" value="1"> '.$langs->trans("Meal").'</label>';

        $out .= '</td>';
    }
    $out .= '</tr>';

    // Ligne des heures travaillées (placeholder → à connecter avec projet)
    $out .= '<tr>';
    $out .= '<td>'.$langs->trans("HoursWorked").'</td>';
    foreach ($days as $i => $d) {
        $daykey = $d->format("Y-m-d");
        $out .= '<td style="text-align:center;">';
        $out .= '<input type="text" name="hours_'.$daykey.'" value="" size="3" class="center">';
        $out .= '</td>';
    }
    $out .= '</tr>';

    $out .= '</table>';
    $out .= '</div>';

    return $out;
}

/**
 * Convert decimal hours into HH:MM format
 *
 * @param float|int $hoursDecimal
 * @return string
 */
function formatHours($hoursDecimal)
{
	$h = floor($hoursDecimal);
	$m = round(($hoursDecimal - $h) * 60);
	return sprintf("%02d:%02d", $h, $m);
}
