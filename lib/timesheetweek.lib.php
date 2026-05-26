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
 * EN: Remove any textual hint of the internal Dolibarr user ID from an HTML select.
 * FR: Supprime toute mention textuelle de l'identifiant interne Dolibarr d'un utilisateur dans un select HTML.
 *
 * @param string $html
 * @return string
 */
function tw_strip_user_id_from_select($html)
{
	if ($html === '' || $html === null) {
		return (string) $html;
	}

	return preg_replace_callback('/(<option\b[^>]*>)([^<]*)(<\/option>)/i', function ($matches) {
		$text = (string) $matches[2];
		// EN: Remove common ID patterns appended by core helpers ("(id: 5)", "#5", "[5]").
		// FR: Supprime les motifs d'ID courants ajoutés par les helpers cœur ("(id: 5)", "#5", "[5]").
		$cleaned = preg_replace(array('/\s*\(id:\s*\d+\)/i', '/\s*#\s*\d+/', '/\s*\[\s*\d+\s*\]/'), '', $text);
		return $matches[1].$cleaned.$matches[3];
	}, $html);
}

/**
 * EN: Keep only the authorised user identifiers within an HTML select while preserving placeholders.
 * FR: Conserve uniquement les identifiants utilisateurs autorisés dans un select HTML en préservant les placeholders.
 *
 * @param string $html
 * @param array  $allowedUserIds
 * @param int    $selectedUserId
 * @return string
 */
function tw_filter_select_by_user_ids($html, array $allowedUserIds, $selectedUserId = 0)
{
	if ($html === '' || $html === null) {
		return (string) $html;
	}

	$allowedMap = array();
	foreach ($allowedUserIds as $candidate) {
		$candidateId = (int) $candidate;
		if ($candidateId > 0) {
			$allowedMap[$candidateId] = true;
		}
	}

	$selectedUserId = (int) $selectedUserId;
	if ($selectedUserId > 0) {
		$allowedMap[$selectedUserId] = true;
	}

	$pattern = '/<option\b[^>]*value="?(-?\d+)"?[^>]*>.*?<\/option>/i';

	return preg_replace_callback($pattern, function ($matches) use ($allowedMap) {
		$value = (int) $matches[1];
		if ($value <= 0) {
			// EN: Preserve empty, "all" and sentinel options to keep the widget functional.
			// FR: Préserve les options vides, "tous" et sentinelles pour maintenir le widget fonctionnel.
			return $matches[0];
		}
		if (!isset($allowedMap[$value])) {
			// EN: Remove any employee outside the authorised perimeter.
			// FR: Retire tout salarié hors du périmètre autorisé.
			return '';
		}
		return $matches[0];
	}, $html);
}

/**
 * EN: Render a simple user selector from explicit ids, without applying Dolibarr entity filtering.
 * FR: Affiche un sélecteur utilisateur depuis des identifiants explicites, sans filtre d'entité Dolibarr.
 *
 * @param DoliDB    $db             Database handler / Gestionnaire de base de données
 * @param int[]     $userIds        User identifiers / Identifiants utilisateur
 * @param string    $htmlname       Select field name / Nom du champ select
 * @param int       $selectedUserId Selected user / Utilisateur sélectionné
 * @param Translate $langs          Translator / Traducteur
 * @param string    $css            Additional CSS classes / Classes CSS supplémentaires
 * @return string HTML select / Select HTML
 */
function tw_render_user_select_from_ids($db, array $userIds, $htmlname, $selectedUserId, Translate $langs, $css = 'maxwidth200')
{
	$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($candidateId) {
		return (int) $candidateId > 0;
	})));

	$selectedUserId = (int) $selectedUserId;
	$htmlname = preg_replace('/[^a-z0-9_\\-\\[\\]]/i', '', (string) $htmlname);
	if ($htmlname === '') {
		$htmlname = 'fk_user';
	}

	$out = '<select class="flat '.dol_escape_htmltag($css).'" name="'.dol_escape_htmltag($htmlname).'" id="'.dol_escape_htmltag($htmlname).'">';
	$out .= '<option value="">&nbsp;</option>';
	if (empty($userIds)) {
		$out .= '</select>';
		return $out;
	}

	$sql = "SELECT rowid, lastname, firstname, login";
	$sql .= " FROM ".MAIN_DB_PREFIX."user";
	$sql .= " WHERE rowid IN (".implode(',', $userIds).")";
	$sql .= " ORDER BY lastname ASC, firstname ASC, login ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		$out .= '</select>';
		return $out;
	}

	while ($obj = $db->fetch_object($resql)) {
		$userId = (int) $obj->rowid;
		$label = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
		if ($label === '') {
			$label = (string) $obj->login;
		}
		if ($label === '') {
			$label = $langs->trans('User').' #'.$userId;
		}
		$out .= '<option value="'.$userId.'"'.($selectedUserId === $userId ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	$db->free($resql);

	$out .= '</select>';
	return $out;
}

/**
 * EN: Render a multiple user selector from explicit ids, without applying Dolibarr entity filtering.
 * FR: Affiche un sélecteur utilisateur multiple depuis des identifiants explicites, sans filtre d'entité Dolibarr.
 *
 * @param DoliDB    $db              Database handler / Gestionnaire de base de données
 * @param int[]     $userIds         User identifiers / Identifiants utilisateur
 * @param string    $htmlname        Select field name / Nom du champ select
 * @param int[]     $selectedUserIds Selected users / Utilisateurs sélectionnés
 * @param Translate $langs           Translator / Traducteur
 * @param string    $css             Additional CSS classes / Classes CSS supplémentaires
 * @return string HTML select / Select HTML
 */
function tw_render_user_multiselect_from_ids($db, array $userIds, $htmlname, array $selectedUserIds, Translate $langs, $css = 'minwidth300')
{
	$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($candidateId) {
		return (int) $candidateId > 0;
	})));

	$selectedMap = array();
	foreach ($selectedUserIds as $selectedUserId) {
		$selectedUserId = (int) $selectedUserId;
		if ($selectedUserId > 0) {
			$selectedMap[$selectedUserId] = true;
		}
	}

	$fieldname = preg_replace('/[^a-z0-9_\\-]/i', '', (string) $htmlname);
	if ($fieldname === '') {
		$fieldname = 'fk_user';
	}
	$htmlid = $fieldname;

	$out = '<select class="flat '.dol_escape_htmltag($css).'" name="'.dol_escape_htmltag($fieldname).'[]" id="'.dol_escape_htmltag($htmlid).'" multiple>';
	if (empty($userIds)) {
		$out .= '</select>';
		return $out;
	}

	$sql = "SELECT rowid, lastname, firstname, login";
	$sql .= " FROM ".MAIN_DB_PREFIX."user";
	$sql .= " WHERE rowid IN (".implode(',', $userIds).")";
	$sql .= " ORDER BY lastname ASC, firstname ASC, login ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		$out .= '</select>';
		return $out;
	}

	while ($obj = $db->fetch_object($resql)) {
		$userId = (int) $obj->rowid;
		$label = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
		if ($label === '') {
			$label = (string) $obj->login;
		}
		if ($label === '') {
			$label = $langs->trans('User').' #'.$userId;
		}
		$out .= '<option value="'.$userId.'"'.(isset($selectedMap[$userId]) ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	$db->free($resql);

	$out .= '</select>';
	return $out;
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
 * EN: Convert an entity SQL list into unique positive identifiers.
 * FR: Convertit une liste SQL d'entités en identifiants positifs uniques.
 *
 * @param string|array $entityList Entity list / Liste d'entités
 * @return int[] Entity identifiers / Identifiants d'entité
 */
function tw_parse_entity_ids($entityList)
{
	if (is_array($entityList)) {
		$candidates = $entityList;
	} else {
		$candidates = explode(',', (string) $entityList);
	}

	$entityIds = array();
	foreach ($candidates as $candidate) {
		$entityId = (int) trim((string) $candidate);
		if ($entityId > 0 && !in_array($entityId, $entityIds, true)) {
			$entityIds[] = $entityId;
		}
	}

	return $entityIds;
}

/**
 * EN: Build the SQL predicate telling if a user can access a given entity.
 * FR: Construit le prédicat SQL indiquant si un utilisateur peut accéder à une entité.
 *
 * @param string $userAlias       SQL alias of llx_user / Alias SQL de llx_user
 * @param string $entitySql       Entity SQL expression / Expression SQL de l'entité
 * @return string SQL predicate / Prédicat SQL
 */
function tw_sql_user_has_entity_access($userAlias, $entitySql)
{
	$userAlias = preg_replace('/[^a-z0-9_]/i', '', (string) $userAlias);
	if ($userAlias === '') {
		$userAlias = 'u';
	}
	$entitySql = trim((string) $entitySql);
	if ($entitySql === '') {
		$entitySql = '0';
	}

	if (function_exists('isModEnabled') && isModEnabled('multicompany') && function_exists('getDolGlobalInt') && getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE')) {
		return "EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."usergroup_user AS twugu WHERE twugu.fk_user = ".$userAlias.".rowid AND twugu.entity IN (0, ".$entitySql."))";
	}

	return "(".$userAlias.".entity IN (0, ".$entitySql."))";
}

/**
 * EN: Check if a Dolibarr user can access a specific entity.
 * FR: Vérifie si un utilisateur Dolibarr peut accéder à une entité donnée.
 *
 * @param DoliDB $db       Database handler / Gestionnaire de base de données
 * @param int    $userId   User identifier / Identifiant utilisateur
 * @param int    $entityId Entity identifier / Identifiant d'entité
 * @return bool True when access is allowed / Vrai si l'accès est autorisé
 */
function tw_user_has_access_to_entity($db, $userId, $entityId)
{
	$userId = (int) $userId;
	$entityId = (int) $entityId;
	if ($userId <= 0 || $entityId <= 0) {
		return false;
	}

	$sql = "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user AS u";
	$sql .= " WHERE u.rowid = ".$userId;
	$sql .= " AND ".tw_sql_user_has_entity_access('u', (string) $entityId);

	$resql = $db->query($sql);
	if (!$resql) {
		return false;
	}

	$hasAccess = ($db->num_rows($resql) > 0);
	$db->free($resql);
	return $hasAccess;
}

/**
 * EN: Tell if list-style reads must ignore employee access to the current entity.
 * FR: Indique si les lectures de type liste ignorent l'accès salarié à l'entité courante.
 *
 * @return bool True when all shared-entity timesheets are visible / Vrai si toutes les feuilles partagées sont visibles
 */
function tw_show_all_multicompany_users_timesheets()
{
	return function_exists('getDolGlobalInt') && (bool) getDolGlobalInt('TIMESHEETWEEK_SHOW_ALL_MULTICOMPANY_USERS_TIMESHEET', 0);
}

/**
 * EN: Build the SQL predicate used by read views for employee access to the current entity.
 * FR: Construit le prédicat SQL utilisé en lecture pour l'accès salarié à l'entité courante.
 *
 * @param string $userAlias       SQL alias of llx_user / Alias SQL de llx_user
 * @param int    $currentEntityId Current entity / Entité courante
 * @return string SQL predicate / Prédicat SQL
 */
function tw_sql_timesheet_read_user_entity_access($userAlias, $currentEntityId = 0)
{
	global $conf;

	if (tw_show_all_multicompany_users_timesheets()) {
		return '1=1';
	}

	$currentEntityId = (int) $currentEntityId;
	if ($currentEntityId <= 0 && !empty($conf->entity)) {
		$currentEntityId = (int) $conf->entity;
	}

	return tw_sql_user_has_entity_access($userAlias, (string) $currentEntityId);
}

/**
 * EN: Build the SQL predicate used to select reminder recipients.
 * FR: Construit le prédicat SQL utilisé pour sélectionner les destinataires du rappel.
 *
 * @param string $userAlias       SQL alias of llx_user / Alias SQL de llx_user
 * @param int    $currentEntityId Current entity / Entité courante
 * @return string SQL predicate / Prédicat SQL
 */
function tw_sql_timesheet_reminder_eligible_user($userAlias, $currentEntityId = 0)
{
	global $conf;

	$userAlias = preg_replace('/[^a-z0-9_]/i', '', (string) $userAlias);
	if ($userAlias === '') {
		$userAlias = 'u';
	}

	$currentEntityId = (int) $currentEntityId;
	if ($currentEntityId <= 0 && !empty($conf->entity)) {
		$currentEntityId = (int) $conf->entity;
	}

	$sql = "(".$userAlias.".statut = 1";
	$sql .= " AND ".$userAlias.".employee = 1";
	$sql .= " AND ".$userAlias.".email IS NOT NULL";
	$sql .= " AND ".$userAlias.".email <> ''";
	$sql .= " AND ".tw_sql_timesheet_read_user_entity_access($userAlias, $currentEntityId);
	$sql .= ")";

	return $sql;
}

/**
 * EN: Return users eligible to receive the weekly reminder.
 * FR: Renvoie les utilisateurs pouvant recevoir le rappel hebdomadaire.
 *
 * @param DoliDB $db              Database handler / Gestionnaire de base de données
 * @param int    $currentEntityId Current entity / Entité courante
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_get_timesheet_reminder_eligible_user_ids($db, $currentEntityId = 0)
{
	$sql = "SELECT DISTINCT u.rowid";
	$sql .= " FROM ".MAIN_DB_PREFIX."user AS u";
	$sql .= " WHERE ".tw_sql_timesheet_reminder_eligible_user('u', (int) $currentEntityId);
	$sql .= " ORDER BY u.lastname ASC, u.firstname ASC, u.login ASC, u.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}

	$userIds = array();
	while ($obj = $db->fetch_object($resql)) {
		$userIds[] = (int) $obj->rowid;
	}
	$db->free($resql);

	return $userIds;
}

/**
 * EN: Check if a user is visible in read views according to the current entity option.
 * FR: Vérifie si un utilisateur est visible en lecture selon l'option d'entité courante.
 *
 * @param DoliDB $db              Database handler / Gestionnaire de base de données
 * @param int    $userId          User identifier / Identifiant utilisateur
 * @param int    $currentEntityId Current entity / Entité courante
 * @return bool True when visible in read views / Vrai si visible en lecture
 */
function tw_user_has_timesheet_read_entity_access($db, $userId, $currentEntityId = 0)
{
	global $conf;

	if (tw_show_all_multicompany_users_timesheets()) {
		return true;
	}

	$currentEntityId = (int) $currentEntityId;
	if ($currentEntityId <= 0 && !empty($conf->entity)) {
		$currentEntityId = (int) $conf->entity;
	}

	return tw_user_has_access_to_entity($db, $userId, $currentEntityId);
}

/**
 * EN: Return users having at least one timesheet in the requested entities.
 * FR: Renvoie les utilisateurs ayant au moins une feuille dans les entités demandées.
 *
 * @param DoliDB $db        Database handler / Gestionnaire de base de données
 * @param int[]  $entityIds Entity identifiers / Identifiants d'entité
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_get_timesheet_user_ids_for_entities($db, array $entityIds)
{
	$entityIds = tw_parse_entity_ids($entityIds);
	if (empty($entityIds) && function_exists('getEntity')) {
		$entityIds = tw_parse_entity_ids(getEntity('timesheetweek'));
	}
	if (empty($entityIds)) {
		return array();
	}

	$sql = "SELECT DISTINCT t.fk_user";
	$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week AS t";
	$sql .= " WHERE t.entity IN (".implode(',', array_map('intval', $entityIds)).")";
	$sql .= " AND t.fk_user > 0";

	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}

	$userIds = array();
	while ($obj = $db->fetch_object($resql)) {
		$userIds[] = (int) $obj->fk_user;
	}
	$db->free($resql);

	return $userIds;
}

/**
 * EN: Filter user ids according to own/child/all timesheet rights.
 * FR: Filtre des utilisateurs selon les droits feuilles propre/enfant/toutes.
 *
 * @param int[] $candidateUserIds Candidate users / Utilisateurs candidats
 * @param User  $user             Current user / Utilisateur courant
 * @param bool  $own              Own scope / Périmètre propre
 * @param bool  $child            Child scope / Périmètre subordonné
 * @param bool  $all              Global scope / Périmètre global
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_filter_user_ids_by_timesheet_scope(array $candidateUserIds, User $user, $own, $child, $all)
{
	$candidateUserIds = array_values(array_unique(array_filter(array_map('intval', $candidateUserIds), function ($candidateId) {
		return (int) $candidateId > 0;
	})));

	if ($all || !empty($user->admin)) {
		return $candidateUserIds;
	}

	$scopeIds = array();
	if ($own) {
		$scopeIds[] = (int) $user->id;
	}
	if ($child) {
		$scopeIds = array_merge($scopeIds, tw_get_user_child_ids($user));
	}
	$scopeIds = array_values(array_unique(array_filter($scopeIds, function ($candidateId) {
		return (int) $candidateId > 0;
	})));

	if (empty($scopeIds)) {
		return array();
	}

	return array_values(array_intersect($candidateUserIds, $scopeIds));
}

/**
 * EN: Return users attached to at least one requested entity.
 * FR: Renvoie les utilisateurs rattachés à au moins une des entités demandées.
 *
 * @param DoliDB $db        Database handler / Gestionnaire de base de données
 * @param int[]  $entityIds Entity identifiers / Identifiants d'entité
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_get_user_ids_for_entities($db, array $entityIds)
{
	$entityIds = tw_parse_entity_ids($entityIds);
	if (empty($entityIds)) {
		return array();
	}

	$conditions = array();
	foreach ($entityIds as $entityId) {
		$conditions[] = tw_sql_user_has_entity_access('u', (string) (int) $entityId);
	}

	$sql = "SELECT DISTINCT u.rowid FROM ".MAIN_DB_PREFIX."user AS u";
	$sql .= " WHERE (".implode(' OR ', $conditions).")";

	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}

	$userIds = array();
	while ($obj = $db->fetch_object($resql)) {
		$userIds[] = (int) $obj->rowid;
	}
	$db->free($resql);

	return $userIds;
}

/**
 * EN: Return users visible for a timesheet action, combining hierarchy rights and entity access.
 * FR: Renvoie les utilisateurs visibles pour une action feuille, en combinant droits hiérarchiques et accès entité.
 *
 * @param DoliDB       $db        Database handler / Gestionnaire de base de données
 * @param int|int[]    $entityIds Entity identifiers / Identifiants d'entité
 * @param User         $user      Current user / Utilisateur courant
 * @param bool         $own       Own scope / Périmètre propre
 * @param bool         $child     Child scope / Périmètre subordonné
 * @param bool         $all       Global scope / Périmètre global
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_get_timesheet_visible_user_ids($db, $entityIds, User $user, $own, $child, $all)
{
	$entityIds = tw_parse_entity_ids($entityIds);
	if (empty($entityIds) && function_exists('getEntity')) {
		$entityIds = tw_parse_entity_ids(getEntity('timesheetweek'));
	}
	if (empty($entityIds)) {
		return array();
	}

	$entityUserIds = tw_get_user_ids_for_entities($db, $entityIds);
	if (empty($entityUserIds)) {
		return array();
	}

	if ($all || !empty($user->admin)) {
		return $entityUserIds;
	}

	$scopeIds = array();
	if ($own) {
		$scopeIds[] = (int) $user->id;
	}
	if ($child) {
		$scopeIds = array_merge($scopeIds, tw_get_user_child_ids($user));
	}
	$scopeIds = array_values(array_unique(array_filter($scopeIds, function ($candidateId) {
		return (int) $candidateId > 0;
	})));

	if (empty($scopeIds)) {
		return array();
	}

	return array_values(array_intersect($entityUserIds, $scopeIds));
}

/**
 * EN: Return user ids visible in list read filters according to the multicompany option.
 * FR: Renvoie les utilisateurs visibles dans les filtres de lecture selon l'option multicompany.
 *
 * @param DoliDB       $db              Database handler / Gestionnaire de base de données
 * @param int|int[]    $entityIds       Shared timesheet entities / Entités de feuilles partagées
 * @param User         $user            Current user / Utilisateur courant
 * @param bool         $own             Own scope / Périmètre propre
 * @param bool         $child           Child scope / Périmètre subordonné
 * @param bool         $all             Global scope / Périmètre global
 * @param int          $currentEntityId Current entity / Entité courante
 * @return int[] User identifiers / Identifiants utilisateur
 */
function tw_get_timesheet_read_visible_user_ids($db, $entityIds, User $user, $own, $child, $all, $currentEntityId = 0)
{
	global $conf;

	if (tw_show_all_multicompany_users_timesheets()) {
		$entityIds = tw_parse_entity_ids($entityIds);
		if (empty($entityIds) && function_exists('getEntity')) {
			$entityIds = tw_parse_entity_ids(getEntity('timesheetweek'));
		}
		return tw_filter_user_ids_by_timesheet_scope(tw_get_timesheet_user_ids_for_entities($db, $entityIds), $user, $own, $child, $all);
	}

	$currentEntityId = (int) $currentEntityId;
	if ($currentEntityId <= 0 && !empty($conf->entity)) {
		$currentEntityId = (int) $conf->entity;
	}

	return tw_get_timesheet_visible_user_ids($db, array($currentEntityId), $user, $own, $child, $all);
}

/**
 * EN: Resolve a public-holiday rule to an ISO date for a given year.
 * FR: Résout une règle de jour férié vers une date ISO pour une année donnée.
 *
 * @param int    $year    Target year / Année cible
 * @param string $dayrule Dolibarr day rule / Règle Dolibarr
 * @param int    $month   Fixed month / Mois fixe
 * @param int    $day     Fixed day / Jour fixe
 * @return string ISO date or empty string / Date ISO ou chaîne vide
 */
function tw_resolve_public_holiday_rule_date($year, $dayrule, $month = 0, $day = 0)
{
	$year = (int) $year;
	$dayrule = strtolower(trim((string) $dayrule));
	if ($year <= 0) {
		return '';
	}

	if ($dayrule === '' || $dayrule === 'date') {
		$month = (int) $month;
		$day = (int) $day;
		if ($month <= 0 || $day <= 0 || !checkdate($month, $day, $year)) {
			return '';
		}
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}

	if (!function_exists('easter_days')) {
		return '';
	}

	$easter = new DateTime($year.'-03-21', new DateTimeZone('UTC'));
	$easter->modify('+'.((int) easter_days($year)).' days');
	$offsets = array(
		'easter' => 0,
		'eastermonday' => 1,
		'goodfriday' => -2,
		'viernessanto' => -2,
		'ascension' => 39,
		'pentecost' => 49,
		'pentecotemonday' => 50,
		'fronleichnam' => 60,
	);

	if (isset($offsets[$dayrule])) {
		$date = clone $easter;
		$offset = (int) $offsets[$dayrule];
		if ($offset !== 0) {
			$date->modify(($offset > 0 ? '+' : '').$offset.' days');
		}
		return $date->format('Y-m-d');
	}

	if ($dayrule === 'genevafast') {
		$date = new DateTime($year.'-09-01', new DateTimeZone('UTC'));
		$date->modify('next sunday');
		$date->modify('next thursday');
		return $date->format('Y-m-d');
	}

	return '';
}

/**
 * EN: Tell if a date matches a public-holiday rule set.
 * FR: Indique si une date correspond à un jeu de règles de jours fériés.
 *
 * @param string $isoDate ISO date / Date ISO
 * @param array  $rules   Rules from llx_c_hrm_public_holiday / Règles de llx_c_hrm_public_holiday
 * @return bool True when public holiday / Vrai si jour férié
 */
function tw_is_public_holiday_from_rules($isoDate, array $rules)
{
	$isoDate = substr((string) $isoDate, 0, 10);
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
		return false;
	}

	$targetYear = (int) substr($isoDate, 0, 4);
	foreach ($rules as $rule) {
		$rule = (array) $rule;
		$ruleYear = isset($rule['year']) ? (int) $rule['year'] : 0;
		if ($ruleYear > 0 && $ruleYear !== $targetYear) {
			continue;
		}
		$ruleDate = tw_resolve_public_holiday_rule_date(
			$targetYear,
			isset($rule['dayrule']) ? (string) $rule['dayrule'] : '',
			isset($rule['month']) ? (int) $rule['month'] : 0,
			isset($rule['day']) ? (int) $rule['day'] : 0
		);
		if ($ruleDate === $isoDate) {
			return true;
		}
	}

	return false;
}

/**
 * EN: Split a leave day value between leave/RTT and public-holiday counters.
 * FR: Répartit une valeur de congé entre compteurs congé/RTT et jour férié.
 *
 * @param float $dayValue        Day value / Valeur du jour
 * @param bool  $isRtt           RTT flag / Drapeau RTT
 * @param bool  $isPublicHoliday Public-holiday flag / Drapeau jour férié
 * @return array{leave_days:float,rtt_days:float,public_holiday_days:float}
 */
function tw_split_leave_day_value($dayValue, $isRtt, $isPublicHoliday)
{
	$result = array('leave_days' => 0.0, 'rtt_days' => 0.0, 'public_holiday_days' => 0.0);
	$dayValue = (float) $dayValue;
	if ($dayValue <= 0) {
		return $result;
	}

	if ($isPublicHoliday) {
		$result['public_holiday_days'] = $dayValue;
	} elseif ($isRtt) {
		$result['rtt_days'] = $dayValue;
	} else {
		$result['leave_days'] = $dayValue;
	}

	return $result;
}

/**
 * EN: Resolve the country of one entity from Dolibarr company constants.
 * FR: Résout le pays d'une entité depuis les constantes société Dolibarr.
 *
 * @param DoliDB $db       Database handler / Gestionnaire de base de données
 * @param int    $entityId Entity identifier / Identifiant d'entité
 * @return array{country_id:int,country_code:string}
 */
function tw_get_entity_country_info($db, $entityId)
{
	static $cache = array();
	$entityId = (int) $entityId;
	if ($entityId <= 0) {
		$entityId = 1;
	}
	if (isset($cache[$entityId])) {
		return $cache[$entityId];
	}

	$sql = "SELECT entity, value FROM ".MAIN_DB_PREFIX."const";
	$sql .= " WHERE name = 'MAIN_INFO_SOCIETE_COUNTRY'";
	$sql .= " AND entity IN (0, ".$entityId.")";
	$sql .= " ORDER BY entity DESC";

	$value = '';
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$value = (string) $obj->value;
		}
		$db->free($resql);
	}

	$countryId = 0;
	$countryCode = '';
	if ($value !== '') {
		$parts = explode(':', $value);
		$countryId = !empty($parts[0]) && is_numeric($parts[0]) ? (int) $parts[0] : 0;
		if (!empty($parts[1])) {
			$countryCode = (string) $parts[1];
		} elseif ($countryId <= 0 && preg_match('/^[A-Z]{2,3}$/i', $value)) {
			$countryCode = strtoupper((string) $value);
		}
	}

	if ($countryCode === '') {
		$sqlCode = "SELECT entity, value FROM ".MAIN_DB_PREFIX."const";
		$sqlCode .= " WHERE name = 'MAIN_INFO_SOCIETE_COUNTRY_CODE'";
		$sqlCode .= " AND entity IN (0, ".$entityId.")";
		$sqlCode .= " ORDER BY entity DESC";
		$resCode = $db->query($sqlCode);
		if ($resCode) {
			$objCode = $db->fetch_object($resCode);
			if ($objCode) {
				$countryCode = strtoupper((string) $objCode->value);
			}
			$db->free($resCode);
		}
	}

	if ($countryCode === '' && $countryId > 0) {
		$sqlCountry = "SELECT code FROM ".MAIN_DB_PREFIX."c_country WHERE rowid = ".$countryId;
		$resCountry = $db->query($sqlCountry);
		if ($resCountry) {
			$objCountry = $db->fetch_object($resCountry);
			if ($objCountry) {
				$countryCode = (string) $objCountry->code;
			}
			$db->free($resCountry);
		}
	}

	if ($countryId <= 0 && $countryCode !== '') {
		$sqlCountryId = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code = '".$db->escape($countryCode)."'";
		$resCountryId = $db->query($sqlCountryId);
		if ($resCountryId) {
			$objCountryId = $db->fetch_object($resCountryId);
			if ($objCountryId) {
				$countryId = (int) $objCountryId->rowid;
			}
			$db->free($resCountryId);
		}
	}

	$cache[$entityId] = array('country_id' => $countryId, 'country_code' => $countryCode);
	return $cache[$entityId];
}

/**
 * EN: Fetch active public-holiday rules for an entity.
 * FR: Charge les règles actives de jours fériés pour une entité.
 *
 * @param DoliDB $db       Database handler / Gestionnaire de base de données
 * @param int    $entityId Entity identifier / Identifiant d'entité
 * @return array<int,array<string,int|string>>
 */
function tw_fetch_public_holiday_rules_for_entity($db, $entityId)
{
	static $cache = array();
	$entityId = (int) $entityId;
	if ($entityId <= 0) {
		$entityId = 1;
	}
	if (isset($cache[$entityId])) {
		return $cache[$entityId];
	}

	$countryInfo = tw_get_entity_country_info($db, $entityId);
	$countryId = (int) $countryInfo['country_id'];

	$sql = "SELECT id, code, dayrule, year, month, day";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_hrm_public_holiday";
	$sql .= " WHERE active = 1";
	$sql .= " AND (fk_country IS NULL OR fk_country IN (0".($countryId > 0 ? ", ".$countryId : '')."))";
	$sql .= " AND entity IN (0, ".$entityId.")";

	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$entityId] = array();
		return $cache[$entityId];
	}

	$rules = array();
	while ($obj = $db->fetch_object($resql)) {
		$rules[] = array(
			'id' => (int) $obj->id,
			'code' => (string) $obj->code,
			'dayrule' => (string) $obj->dayrule,
			'year' => (int) $obj->year,
			'month' => (int) $obj->month,
			'day' => (int) $obj->day,
		);
	}
	$db->free($resql);

	$cache[$entityId] = $rules;
	return $cache[$entityId];
}

/**
 * EN: Build a public-holiday map for week days.
 * FR: Construit une carte des jours fériés pour les jours d'une semaine.
 *
 * @param DoliDB $db        Database handler / Gestionnaire de base de données
 * @param array  $weekdates ISO dates by day key / Dates ISO par clé de jour
 * @param int    $entityId  Entity identifier / Identifiant d'entité
 * @return array<string,bool>
 */
function tw_get_public_holiday_map_by_day($db, array $weekdates, $entityId)
{
	$rules = tw_fetch_public_holiday_rules_for_entity($db, $entityId);
	$map = array();
	foreach ($weekdates as $dayKey => $isoDate) {
		$map[$dayKey] = tw_is_public_holiday_from_rules((string) $isoDate, $rules);
	}
	return $map;
}

/**
 * EN: Tell if a date is a public holiday for the requested entity.
 * FR: Indique si une date est fériée pour l'entité demandée.
 *
 * @param DoliDB $db       Database handler / Gestionnaire de base de données
 * @param string $isoDate  ISO date / Date ISO
 * @param int    $entityId Entity identifier / Identifiant d'entité
 * @return bool True when public holiday / Vrai si jour férié
 */
function tw_is_public_holiday_for_entity($db, $isoDate, $entityId)
{
	return tw_is_public_holiday_from_rules((string) $isoDate, tw_fetch_public_holiday_rules_for_entity($db, $entityId));
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
