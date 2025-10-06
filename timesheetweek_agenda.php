<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025 Pierre ARDOIN
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       timesheetweek_agenda.php
 *  \ingroup    timesheetweek
 *  \brief      Tab of events on TimesheetWeek
 */

//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');				// Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');				// Do not load object $user
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');				// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');				// Do not load object $langs
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');		// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');		// Do not check injection attack on POST parameters
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');				// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');				// Do not check style html tag into posted data
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');				// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  	// Do not load ajax.lib.php library
//if (! defined("NOLOGIN"))                  define("NOLOGIN", '1');					// If this page is public (can be called outside logged session). This include the NOIPCHECK too.
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');					// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined("MAIN_LANG_DEFAULT"))        define('MAIN_LANG_DEFAULT', 'auto');					// Force lang to a particular value
//if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined("MAIN_SECURITY_FORCECSP"))   define('MAIN_SECURITY_FORCECSP', 'none');	// Disable all Content Security Policies
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');		// Force use of CSRF protection with tokens even for GET
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');				// Disable browser notification

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/agenda.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');

/**
 * Fetch assigned internal users for a list of agenda events
 *
 * @param DoliDB $db
 * @param int[]  $eventIds
 * @return array<int, array<int, stdClass>>
 */
function tw_fetch_assigned_users_for_events($db, array $eventIds)
{
        $result = array();

        if (empty($eventIds)) {
                return $result;
        }

        $ids = array();
        foreach ($eventIds as $eventId) {
                $eventId = (int) $eventId;
                if ($eventId > 0) {
                        $ids[$eventId] = $eventId;
                }
        }

        if (empty($ids)) {
                return $result;
        }

        $sql = "SELECT ar.fk_actioncomm AS action_id, u.rowid AS user_id, u.lastname, u.firstname, u.login"
                ." FROM ".MAIN_DB_PREFIX."actioncomm_resources as ar"
                ." LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (u.rowid = ar.fk_element AND ar.element_type IN ('user','internal'))"
                ." WHERE ar.fk_actioncomm IN (".implode(',', $ids).")"
                ." AND ar.element_type IN ('user','internal')"
                ." ORDER BY ar.fk_actioncomm, u.lastname, u.firstname";

        $res = $db->query($sql);
        if ($res) {
                while ($obj = $db->fetch_object($res)) {
                        $result[(int) $obj->action_id][] = $obj;
                }
                $db->free($res);
        }

        return $result;
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("timesheetweek@timesheetweek", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

if (GETPOST('actioncode', 'array')) {
	$actioncode = GETPOST('actioncode', 'array', 3);
	if (!count($actioncode)) {
		$actioncode = '0';
	}
} else {
	$actioncode = GETPOST("actioncode", "alpha", 3) ? GETPOST("actioncode", "alpha", 3) : (GETPOST("actioncode") == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}
$search_rowid = GETPOST('search_rowid');
$search_agenda_label = GETPOST('search_agenda_label');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
	$sortorder = 'DESC,DESC';
}

// Initialize a technical objects
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->timesheetweek->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($object->element.'agenda', 'globalcard')); // Note that conf->hooks_modules contains array
// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id > 0 || !empty($ref)) {
	$upload_dir = $conf->timesheetweek->multidir_output[!empty($object->entity) ? $object->entity : $conf->entity]."/".$object->id;
}

// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = getDolGlobalInt('TIMESHEETWEEK_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('timesheetweek', 'timesheetweek', 'read');
	$permissiontoadd = $user->hasRight('timesheetweek', 'timesheetweek', 'write');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
}

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, $object->id, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled("timesheetweek")) {
	accessforbidden();
}
if (!$permissiontoread) {
	accessforbidden();
}


/*
 *  Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$actioncode = '';
		$search_agenda_label = '';
	}
}



/*
 *	View
 */

$form = new Form($db);

if ($object->id > 0) {
	$title = $langs->trans("TimesheetWeek")." - ".$langs->trans('Agenda');
	//$title = $object->ref." - ".$langs->trans("Agenda");
	$help_url = 'EN:Module_Agenda_En|DE:Modul_Terminplanung';

	llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-timesheetweek page-card_agenda');

	if (isModEnabled('notification')) {
		$langs->load("mails");
	}
	$head = timesheetweekPrepareHead($object);


	print dol_get_fiche_head($head, 'agenda', $langs->trans("TimesheetWeek"), -1, $object->picto);

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	/*
	// Ref customer
	$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . (is_object($object->thirdparty) ? $object->thirdparty->getNomUrl(1) : '');
	// Project
	if (isModEnabled('project')) {
		$langs->load("projects");
		$morehtmlref.='<br>'.$langs->trans('Project') . ' ';
		if ($permissiontoadd) {
			if ($action != 'classify') {
				//$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&token='.newToken().'&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
			}
			$morehtmlref.=' : ';
			if ($action == 'classify') {
				//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
				$morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				$morehtmlref.='<input type="hidden" name="action" value="classin">';
				$morehtmlref.='<input type="hidden" name="token" value="'.newToken().'">';
				$morehtmlref.=$formproject->select_projects($object->socid, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
				$morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
				$morehtmlref.='</form>';
			} else {
				$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
			}
		} else {
			if (!empty($object->fk_project)) {
                                $proj = new Project($db);
                                $proj->fetch($object->fk_project);
                                $morehtmlref .= ': '.tw_get_project_nomurl($proj);
			} else {
				$morehtmlref .= '';
			}
		}
	}*/
        $morehtmlref .= '</div>';

        $morehtmlstatus = '';
        if (!empty($object->id)) {
                $morehtmlstatus = $object->getLibStatut(5);
        }

        dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', '', '', $morehtmlstatus);
        print timesheetweekRenderStatusBadgeCleanup();

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$object->info($object->id);
	dol_print_object_info($object, 1);

	print '</div>';

	print dol_get_fiche_end();



	// Actions buttons

	$objthirdparty = $object;
	$objcon = new stdClass();

	$out = '&origin='.urlencode($object->element.(property_exists($object, 'module') ? '@'.$object->module : '')).'&originid='.urlencode((string) $object->id);
	$urlbacktopage = $_SERVER['PHP_SELF'].'?id='.$object->id;
	$out .= '&backtopage='.urlencode($urlbacktopage);
	$permok = $user->hasRight('agenda', 'myactions', 'create');
	if ((!empty($objthirdparty->id) || !empty($objcon->id)) && $permok) {
		//$out.='<a href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create';
		if (get_class($objthirdparty) == 'Societe') {
			$out .= '&socid='.urlencode((string) $objthirdparty->id);
		}
		$out .= (!empty($objcon->id) ? '&contactid='.urlencode($objcon->id) : '');
		//$out.=$langs->trans("AddAnAction").' ';
		//$out.=img_picto($langs->trans("AddAnAction"),'filenew');
		//$out.="</a>";
	}

	$morehtmlright = '';

	//$messagingUrl = DOL_URL_ROOT.'/societe/messaging.php?socid='.$object->id;
	//$morehtmlright .= dolGetButtonTitle($langs->trans('ShowAsConversation'), '', 'fa fa-comments imgforviewmode', $messagingUrl, '', 1);
	//$messagingUrl = DOL_URL_ROOT.'/societe/agenda.php?socid='.$object->id;
	//$morehtmlright .= dolGetButtonTitle($langs->trans('MessageListViewType'), '', 'fa fa-bars imgforviewmode', $messagingUrl, '', 2);

	if (isModEnabled('agenda')) {
		if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
			$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out);
		} else {
			$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out, '', 0);
		}
	}


        if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
                print '<br>';

                $param = '&id='.$object->id;
                if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
                        $param .= '&contextpage='.urlencode($contextpage);
                }
                if ($limit > 0 && $limit != $conf->liste_limit) {
                        $param .= '&limit='.((int) $limit);
                }
                if (!empty($search_rowid)) {
                        $param .= '&search_rowid='.urlencode($search_rowid);
                }
                if (!empty($search_agenda_label)) {
                        $param .= '&search_agenda_label='.urlencode($search_agenda_label);
                }
                if (!empty($sortfield)) {
                        $param .= '&sortfield='.urlencode($sortfield);
                }
                if (!empty($sortorder)) {
                        $param .= '&sortorder='.urlencode($sortorder);
                }

                $sqlWhere = " WHERE a.entity IN (".getEntity('actioncomm', 1).")"
                        ." AND a.elementtype='".$db->escape($object->element)."'"
                        ." AND a.fk_element=".(int) $object->id;

                if (!empty($search_rowid)) {
                        $sqlWhere .= " AND a.id=".(int) $search_rowid;
                }
                if (!empty($search_agenda_label)) {
                        $sqlWhere .= " AND (a.label LIKE '%".$db->escape($search_agenda_label)."%'"
                                ." OR a.note LIKE '%".$db->escape($search_agenda_label)."%')";
                }
                if (!empty($actioncode) && $actioncode !== '0') {
                        if (is_array($actioncode)) {
                                $list = array();
                                foreach ($actioncode as $code) {
                                        if ($code === '0') continue;
                                        $list[] = "'".$db->escape($code)."'";
                                }
                                if (count($list)) {
                                        $sqlWhere .= " AND COALESCE(a.code, ca.code) IN (".implode(',', $list).")";
                                }
                        } else {
                                $sqlWhere .= " AND COALESCE(a.code, ca.code)='".$db->escape($actioncode)."'";
                        }
                }

                $sqlFrom = " FROM ".MAIN_DB_PREFIX."actioncomm as a"
                        ." LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as ca ON (ca.id = a.fk_action OR ca.code = a.code)"
                        ." LEFT JOIN ".MAIN_DB_PREFIX."user as creator ON creator.rowid = a.fk_user_author";

                $sqlCount = "SELECT COUNT(a.id) as nb".$sqlFrom.$sqlWhere;
                $nbEvent = 0;
                $resCount = $db->query($sqlCount);
                if ($resCount) {
                        $objCount = $db->fetch_object($resCount);
                        if ($objCount) {
                                $nbEvent = (int) $objCount->nb;
                        }
                        $db->free($resCount);
                }

                $sql = "SELECT a.id, a.label, a.datep, a.datep2, a.durationp, a.fulldayevent, a.percent, COALESCE(a.code, ca.code) as type_code, a.code,"
                        ." a.fk_user_author, ca.label as action_label, creator.lastname as creator_lastname, creator.firstname as creator_firstname,"
                        ." creator.login as creator_login, creator.rowid as creator_id"
                        .$sqlFrom
                        .$sqlWhere;
                if (!empty($sortfield)) {
                        $sql .= $db->order($sortfield, $sortorder);
                }
                if (!empty($limit)) {
                        $sql .= $db->plimit($limit, $offset);
                }

                $resql = $db->query($sql);
                if (!$resql) {
                        dol_print_error($db);
                } else {
                        $num = $db->num_rows($resql);

                        $rows = array();
                        if ($num > 0) {
                                while ($obj = $db->fetch_object($resql)) {
                                        $rows[] = $obj;
                                }
                        }

                        $titlelist = $langs->trans("Actions").(is_numeric($nbEvent) ? '<span class="opacitymedium colorblack paddingleft">('.$nbEvent.')</span>' : '');
                        print_barre_liste($titlelist, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbEvent, '', 0, $morehtmlright, '', 0, 1, 0);

                        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
                        print '<input type="hidden" name="token" value="'.newToken().'">';
                        print '<input type="hidden" name="id" value="'.$object->id.'">';
                        print '<input type="hidden" name="limit" value="'.$limit.'">';
                        print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
                        print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
                        if (is_array($actioncode)) {
                                foreach ($actioncode as $code) {
                                        print '<input type="hidden" name="actioncode[]" value="'.dol_escape_htmltag($code).'">';
                                }
                        } else {
                                print '<input type="hidden" name="actioncode" value="'.dol_escape_htmltag($actioncode).'">';
                        }

                        print '<div class="div-table-responsive">';
                        print '<table class="noborder centpercent">';

                        print '<tr class="liste_titre">';
                        print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], 'a.id', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('Label'), $_SERVER["PHP_SELF"], 'a.label', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('Type'), $_SERVER["PHP_SELF"], 'a.code', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('DateStart'), $_SERVER["PHP_SELF"], 'a.datep', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('DateEnd'), $_SERVER["PHP_SELF"], 'a.datep2', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('AssignedTo'), $_SERVER["PHP_SELF"], '', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('Author'), $_SERVER["PHP_SELF"], 'a.fk_user_author', $param, '', '', $sortfield, $sortorder);
                        print_liste_field_titre($langs->trans('Status'), $_SERVER["PHP_SELF"], 'a.percent', $param, '', '', $sortfield, $sortorder, 'right');
                        print '</tr>';

                        print '<tr class="liste_titre">';
                        print '<td class="liste_titre"><input type="text" class="flat" name="search_rowid" value="'.dol_escape_htmltag($search_rowid).'"></td>';
                        print '<td class="liste_titre"><input type="text" class="flat" name="search_agenda_label" value="'.dol_escape_htmltag($search_agenda_label).'"></td>';
                        print '<td class="liste_titre"></td>';
                        print '<td class="liste_titre"></td>';
                        print '<td class="liste_titre"></td>';
                        print '<td class="liste_titre"></td>';
                        print '<td class="liste_titre"></td>';
                        print '<td class="liste_titre right">';
                        print '<button type="submit" class="button small" name="button_search" value="1">'.$langs->trans('Search').'</button>';
                        print '&nbsp;';
                        print '<button type="submit" class="button small" name="button_removefilter" value="1">'.$langs->trans('RemoveFilter').'</button>';
                        print '</td>';
                        print '</tr>';

                        if ($num > 0) {
                                $eventIds = array();
                                foreach ($rows as $row) {
                                        $eventIds[] = (int) $row->id;
                                }

                                $assignedByEvent = tw_fetch_assigned_users_for_events($db, $eventIds);

                                $actionstatic = new ActionComm($db);
                                $userstatic = new User($db);
                                $creatorstatic = new User($db);

                                foreach ($rows as $obj) {
                                        $actionstatic->fetch($obj->id);

                                        $creatorstatic->id = (int) $obj->creator_id;
                                        $creatorstatic->lastname = $obj->creator_lastname;
                                        $creatorstatic->firstname = $obj->creator_firstname;
                                        $creatorstatic->login = $obj->creator_login;

                                        $assignedHtml = '';
                                        if (!empty($assignedByEvent[$obj->id])) {
                                                $links = array();
                                                foreach ($assignedByEvent[$obj->id] as $assignedUser) {
                                                        if (!empty($assignedUser->user_id)) {
                                                                $userstatic->id = (int) $assignedUser->user_id;
                                                                $userstatic->lastname = $assignedUser->lastname;
                                                                $userstatic->firstname = $assignedUser->firstname;
                                                                $userstatic->login = $assignedUser->login;
                                                                $links[] = $userstatic->getNomUrl(1);
                                                        }
                                                }
                                                $assignedHtml = implode(', ', $links);
                                        }

                                        $typeLabel = '';
                                        if (!empty($obj->action_label)) {
                                                $typeLabel = $langs->trans($obj->action_label);
                                        } elseif (!empty($obj->type_code)) {
                                                $typeLabel = $langs->trans($obj->type_code);
                                        }
                                        if (empty($typeLabel)) {
                                                $typeLabel = $obj->type_code;
                                        }

                                        print '<tr class="oddeven">';
                                        print '<td>'.$actionstatic->getNomUrl(1, '', 0, 'classfortooltip').'</td>';
                                        print '<td>'.dol_escape_htmltag($actionstatic->label).'</td>';
                                        print '<td>'.dol_escape_htmltag($typeLabel).'</td>';
                                        print '<td>'.dol_print_date($actionstatic->datep, 'dayhour').'</td>';
                                        print '<td>'.dol_print_date($actionstatic->datef, 'dayhour').'</td>';
                                        print '<td>'.$assignedHtml.'</td>';
                                        print '<td>'.($creatorstatic->id > 0 ? $creatorstatic->getNomUrl(1) : '').'</td>';
                                        print '<td class="right">'.$actionstatic->getLibStatut(5).'</td>';
                                        print '</tr>';
                                }
                        } else {
                                print '<tr class="oddeven"><td colspan="8" class="opacitymedium center">'.$langs->trans('NoEvent').'</td></tr>';
                        }

                        print '</table>';
                        print '</div>';
                        print '</form>';

                        $db->free($resql);
                }
        }
}

// End of page
llxFooter();
$db->close();
