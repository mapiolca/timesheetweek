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

        $showtabofpagecontact = 0;
	$showtabofpagenote = 1;
	$showtabofpagedocument = 1;
	$showtabofpageagenda = 1;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/timesheetweek/timesheetweek_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("TimesheetWeek");
	$head[$h][2] = 'card';
	$h++;

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
 * Replace the inner text of an HTML anchor with the provided label
 *
 * @param string $linkHtml
 * @param string $label
 * @return string
 */
function tw_replace_anchor_text($linkHtml, $label)
{
        $escaped = dol_escape_htmltag($label);
        if (empty($linkHtml)) {
                return $escaped;
        }

        return preg_replace('/>([^<]*)</u', '>'.$escaped.'<', $linkHtml, 1);
}

/**
 * Return the project link formatted as "Ref - Label"
 *
 * @param Project $project
 * @param int     $withpicto
 * @return string
 */
function tw_get_project_nomurl(Project $project, $withpicto = 0)
{
        $text = $project->ref;
        if (!empty($project->title)) {
                $text .= ' - '.$project->title;
        } elseif (!empty($project->label)) {
                $text .= ' - '.$project->label;
        }

        $anchor = $project->getNomUrl(0);
        $anchor = tw_replace_anchor_text($anchor, $text);

        if ($withpicto) {
                $picto = img_object('', !empty($project->picto) ? $project->picto : 'project');
                return $picto.' '.$anchor;
        }

        return $anchor;
}

/**
 * Return the task link formatted as "Ref - Label"
 *
 * @param Task $task
 * @param int  $withpicto
 * @param bool $withproject
 * @return string
 */
function tw_get_task_nomurl(Task $task, $withpicto = 0, $withproject = false)
{
        $text = $task->ref;
        if (!empty($task->label)) {
                $text .= ' - '.$task->label;
        }

        $anchor = $task->getNomUrl(0, $withproject ? 'withproject' : '');
        $anchor = tw_replace_anchor_text($anchor, $text);

        if ($withpicto) {
                $picto = img_object('', !empty($task->picto) ? $task->picto : 'projecttask');
                return $picto.' '.$anchor;
        }

        return $anchor;
}

/**
 * EN: Cache and return the identifiers of the users managed by the current user.
 * FR: Met en cache et renvoie les identifiants des utilisateurs gérés par l'utilisateur courant.
 *
 * @param User $user Current Dolibarr user object
 * @return int[] List of subordinate user identifiers
 */
function tw_get_user_child_ids(User $user)
{
	static $cache = array();
	$userId = (int) $user->id;
	if (!isset($cache[$userId])) {
		$rawList = $user->getAllChildIds(1);
		if (!is_array($rawList)) {
			$rawList = array();
		}
		$cache[$userId] = array();
		foreach ($rawList as $candidate) {
			$childId = (int) $candidate;
			if ($childId > 0 && !in_array($childId, $cache[$userId], true)) {
				// EN: Store each subordinate identifier only once to keep SQL filters strict.
				// FR: Conserve chaque identifiant de subordonné une seule fois pour garder des filtres SQL stricts.
				$cache[$userId][] = $childId;
			}
		}
	}
	return $cache[$userId];
}

/**
 * EN: Determine whether the current user manages the provided employee identifier.
 * FR: Détermine si l'utilisateur courant gère l'identifiant d'employé fourni.
 *
 * @param int  $targetUserId Identifier of the employee to test
 * @param User $user         Current Dolibarr user object
 * @return bool              True when the employee is a subordinate
 */
function tw_is_manager_of($targetUserId, User $user)
{
	return in_array((int) $targetUserId, tw_get_user_child_ids($user), true);
}

/**
 * EN: Check if the current user can act on the target employee with own/child/all permissions.
 * FR: Vérifie si l'utilisateur courant peut agir sur l'employé cible via les permissions propre/enfant/toutes.
 *
 * @param int  $targetUserId Employee identifier to check
 * @param bool $own          Allowance on own resources
 * @param bool $child        Allowance on subordinate resources
 * @param bool $all          Allowance on all resources
 * @param User $user         Current Dolibarr user object
 * @return bool              True if the action is permitted
 */
function tw_can_act_on_user($targetUserId, $own, $child, $all, User $user)
{
	if ($all) {
		return true;
	}
	if ($own && ((int) $targetUserId === (int) $user->id)) {
		return true;
	}
	if ($child && tw_is_manager_of($targetUserId, $user)) {
		return true;
	}
	return false;
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

if (!function_exists('timesheetweekRenderStatusBadgeCleanup')) {
        /**
         * Injecte un helper jQuery pour supprimer le texte doublon autour du badge de statut
         * et harmoniser le vocabulaire en FR/EN.
         *
         * @return string
         */
        function timesheetweekRenderStatusBadgeCleanup()
        {
                return <<<'JS'
<script>
(function (factory) {
    if (typeof jQuery !== 'undefined') {
        factory(jQuery);
    }
})(function ($) {
    $(function () {
        $('.statusref').each(function () {
            $(this).contents().filter(function () {
                return this.nodeType === 3 && $.trim(this.nodeValue).length > 0;
            }).remove();
        });

        $('.statusref .badge, .status .badge, .badgestatus, .badge-status').each(function () {
            var text = $.trim($(this).text());
            if (text === 'Validée') {
                $(this).text('Approuvée');
            }
            if (text === 'Validated') {
                $(this).text('Approved');
            }
        });
    });
});
</script>
JS;
        }
}

/**
 * Génère un <select> listant les semaines de l'année courante
 * au format visuel Dolibarr (classe flat + select2)
 *
 * @param Form        $form      Objet Form Dolibarr
 * @param string      $htmlname  Nom du champ select
 * @param int|array   $selected  Numéro(s) de semaine sélectionné(s)
 * @param int         $year      Année (par défaut année courante)
 * @param bool        $includeEmpty Indique si une option vide est requise
 * @param bool        $multiple  Active le mode multi-sélection
 * @return string
 */
function getWeekSelectorDolibarr($form, $htmlname, $selected = 0, $year = 0, $includeEmpty = false, $multiple = false)
{
        global $langs;

        if ($multiple) {
                // EN: Normalise the ISO year-week values and reuse Dolibarr's native multi-select layout.
                // FR: Normalise les valeurs ISO année-semaine et réutilise la présentation multi-sélection native de Dolibarr.
                if (!is_array($selected)) {
                        $selected = $selected !== '' ? array($selected) : array();
                }

                $selectedValues = array();
                foreach ($selected as $candidate) {
                        if (is_string($candidate) && preg_match('/^(\d{4})-W(\d{2})$/', $candidate, $matches)) {
                                $selectedValues[$matches[1].'-W'.$matches[2]] = true;
                        }
                }

                if (empty($year)) {
                        // EN: Default to the current ISO year when no context year is supplied.
                        // FR: Utilise l'année ISO courante lorsqu'aucune année de contexte n'est fournie.
                        $year = (int) date('o');
                }

                $options = array();
                for ($week = 1; $week <= 53; $week++) {
                        $dto = new DateTime();
                        $dto->setISODate($year, $week);
                        $start = dol_print_date($dto->getTimestamp(), 'day');
                        $dto->modify('+6 days');
                        $end = dol_print_date($dto->getTimestamp(), 'day');

                        $isoKey = $year.'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT);
                        $options[$isoKey] = $langs->trans('Week').' '.$week.' ('.$start.' → '.$end.')';
                }

                return $form->multiselectarray(
                        $htmlname,
                        $options,
                        array_keys($selectedValues),
                        0,
                        0,
                        'minwidth150 maxwidth200',
                        0,
                        0,
                        '',
                        '',
                        '',
                        '',
                        '',
                        1
                );
        }

        // EN: Prepare the selected week and year for the single-choice selector.
        // FR: Prépare la semaine et l'année sélectionnées pour le sélecteur mono-choix.
        $selectedYear = 0;
        $selectedWeek = 0;

        if (is_string($selected) && preg_match('/^(\d{4})-W(\d{2})$/', $selected, $matches)) {
                $selectedYear = (int) $matches[1];
                $selectedWeek = (int) $matches[2];
        } else {
                $selectedWeek = (int) $selected;
        }

        if (empty($year)) {
                // EN: Use the parsed year when possible, otherwise rely on the current ISO year.
                // FR: Utilise l'année extraite lorsque possible, sinon se base sur l'année ISO courante.
                $year = $selectedYear > 0 ? $selectedYear : (int) date('o');
        }

        if ($selectedYear <= 0) {
                // EN: Align the selected year with the rendered year when none is provided explicitly.
                // FR: Aligne l'année sélectionnée avec l'année affichée lorsqu'aucune n'est fournie explicitement.
                $selectedYear = $year;
        }

        if ($selectedWeek <= 0) {
                // EN: Default to the current week except when an empty option must be shown.
                // FR: Utilise la semaine courante sauf si une option vide doit être proposée.
                $selectedWeek = $includeEmpty ? 0 : (int) date('W');
        }

        $out = '<select class="flat minwidth200" name="'.$htmlname.'" id="'.$htmlname.'">';

        if ($includeEmpty) {
                // EN: Offer an empty option so list filters can be cleared.
                // FR: Ajoute une option vide pour permettre de réinitialiser les filtres de liste.
                $out .= '<option value=""'.($selectedWeek === 0 ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans('SelectWeekPlaceholder')).'</option>';
        }

        for ($week = 1; $week <= 53; $week++) {
                $dto = new DateTime();
                $dto->setISODate($year, $week); // Lundi de la semaine
                $start = dol_print_date($dto->getTimestamp(), 'day');
                $dto->modify('+6 days');
                $end = dol_print_date($dto->getTimestamp(), 'day');

                $label = $langs->trans('Week').' '.$week.' ('.$start.' → '.$end.')';
                $val = $year.'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT);
                $isselected = ($selectedWeek === $week && $selectedYear === $year);
                $out .= '<option value="'.$val.'"'.($isselected ? ' selected' : '').'>'.$label.'</option>';
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
    $hoursDecimal = (float) $hoursDecimal;
    $hours = (int) floor($hoursDecimal);
    $minutes = (int) round(($hoursDecimal - $hours) * 60);

    if ($minutes >= 60) {
        $hours += (int) floor($minutes / 60);
        $minutes = $minutes % 60;
    }

    return sprintf("%02d:%02d", $hours, $minutes);
}
