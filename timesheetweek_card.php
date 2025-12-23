<?php
/* Copyright (C) 2025	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
// EN: Load the PDF model definitions to reuse Dolibarr's filtering helpers.
// FR: Charge les définitions de modèles PDF pour réutiliser les filtres de Dolibarr.
dol_include_once('/timesheetweek/core/modules/timesheetweek/modules_timesheetweek.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
// EN: Load price helpers to display day totals with Dolibarr formatting rules.
// FR: Charge les aides de prix pour afficher les totaux en jours avec les règles de formatage Dolibarr.
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

dol_include_once('/timesheetweek/class/timesheetweek.class.php');
dol_include_once('/timesheetweek/lib/timesheetweek.lib.php'); // getWeekSelectorDolibarr(), formatHours(), ...

$langs->loadLangs(array('timesheetweek@timesheetweek','projects','users','other'));

// ---- Params ----
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
// EN: Retrieve PDF display flags to align with Dolibarr's document generator options.
// FR: Récupère les indicateurs d'affichage PDF pour s'aligner sur les options du générateur de documents Dolibarr.
$hidedetails = GETPOSTISSET('hidedetails') ? GETPOSTINT('hidedetails') : (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);
$hidedesc = GETPOSTISSET('hidedesc') ? GETPOSTINT('hidedesc') : (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0);
$hideref = GETPOSTISSET('hideref') ? GETPOSTINT('hideref') : (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0);

// ---- Init ----
$object = new TimesheetWeek($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('timesheetweekcard','globalcard'));

// EN: Default daily rate flag to avoid undefined notices before data loading.
// FR: Définit le flag forfait jour par défaut pour éviter les notices avant chargement des données.
// EN: Default the quarter-day flag and daily rate usage before evaluating the employee profile.
// FR: Définit par défaut le drapeau quart de jour et l'utilisation du forfait avant d'évaluer le profil du salarié.
$useQuarterDayDailyContract = !empty($conf->global->TIMESHEETWEEK_QUARTERDAYFORDAILYCONTRACT);
$isDailyRateEmployee = false;

// ---- Fetch (set $object if id) ----
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// ---- SHIM STATUTS (mappe vers les constantes de la classe, avec fallback) ----
function tw_status($name) {
	static $map = null;
	if ($map === null) {
		$approved = null;
		if (defined('TimesheetWeek::STATUS_APPROVED')) {
			$approved = TimesheetWeek::STATUS_APPROVED;
		} elseif (defined('TimesheetWeek::STATUS_VALIDATED')) {
			$approved = TimesheetWeek::STATUS_VALIDATED;
		} else {
			$approved = 2;
		}
		$map = array(
			'draft'     => defined('TimesheetWeek::STATUS_DRAFT')     ? TimesheetWeek::STATUS_DRAFT     : 0,
			'submitted' => defined('TimesheetWeek::STATUS_SUBMITTED') ? TimesheetWeek::STATUS_SUBMITTED : 1,
						'approved'  => $approved, // <— Approuvée
						'sealed'    => defined('TimesheetWeek::STATUS_SEALED')    ? TimesheetWeek::STATUS_SEALED    : 8,
						'refused'   => defined('TimesheetWeek::STATUS_REFUSED')   ? TimesheetWeek::STATUS_REFUSED   : 3,
					);
	}
	return $map[$name];
}

function tw_translate_error($errorKey, $langs)
{
	if (empty($errorKey)) {
		return $langs->trans("Error");
	}
	$msg = $langs->trans($errorKey);
	if ($msg === $errorKey) {
		$msg = $langs->trans("Error").' ('.dol_escape_htmltag($errorKey).')';
	}
	return $msg;
}

/**
* EN: Format day totals by reusing Dolibarr price helpers to respect locale settings.
* FR: Formate les totaux en jours en réutilisant les aides de prix Dolibarr pour respecter les paramètres régionaux.
*
* @param float      $value  Day quantity to format / Quantité de jours à formater
* @param Translate  $langs  Translator instance / Instance de traduction
* @return string            Formatted value / Valeur formatée
*/
function tw_format_days($value, Translate $langs)
{
	global $conf;
	// EN: Normalize the numeric value to two decimals for predictable display.
	// FR: Normalise la valeur numérique à deux décimales pour un affichage prévisible.
	$normalized = price2num($value, '2');
	// EN: Use Dolibarr price formatter to apply thousand and decimal separators.
	// FR: Utilise le formateur de prix Dolibarr pour appliquer les séparateurs de milliers et décimales.
	return price($normalized, '', $langs, $conf, 1, 2);
}

/**
* EN: Fetch the timesheet employee and detect the daily rate flag with caching.
* FR: Récupère le salarié de la feuille et détecte le forfait jour avec mise en cache.
*
* @param DoliDB $db     Database handler / Gestionnaire de base de données
* @param int    $userId Employee identifier / Identifiant du salarié
* @return array         ['user' => ?User, 'is_daily_rate' => bool]
*/
function tw_get_employee_with_daily_rate(DoliDB $db, $userId)
{
	static $cache = array();
	$userId = (int) $userId;
	if ($userId <= 0) {
		return array('user' => null, 'is_daily_rate' => false);
	}
	if (isset($cache[$userId])) {
		return $cache[$userId];
	}
	$result = array('user' => null, 'is_daily_rate' => false);
	$tmpUser = new User($db);
	if ($tmpUser->fetch($userId) > 0) {
		$tmpUser->fetch_optionals($tmpUser->id, $tmpUser->table_element);
		$result['user'] = $tmpUser;
		$result['is_daily_rate'] = !empty($tmpUser->array_options['options_lmdb_daily_rate']);
	}
	$cache[$userId] = $result;
	return $result;
}

/**
* EN: Retrieve the list of activated PDF models for the module with entity scoping.
* FR: Récupère la liste des modèles PDF activés pour le module en respectant l'entité.
*
* @param DoliDB $db Database handler / Gestionnaire de base de données
* @return array<string,string> Enabled models keyed by code / Modèles actifs indexés par code
*/
function tw_get_enabled_pdf_models(DoliDB $db)
{
	// EN: Ask the module manager for the enabled templates of TimesheetWeek.
	// FR: Demande au gestionnaire du module les modèles activés de TimesheetWeek.
	$models = ModelePDFTimesheetWeek::liste_modeles($db);
	if (!is_array($models) || empty($models)) {
		return array();
	}

	// EN: Remove document definitions that advertise an ODT extension to keep PDF-only generation.
	// FR: Supprime les définitions de documents qui annoncent une extension ODT pour conserver une génération uniquement PDF.
	foreach ($models as $code => $modelInfo) {
		$type = '';
		$extension = '';
		if (is_array($modelInfo)) {
			if (!empty($modelInfo['type'])) {
				$type = strtolower((string) $modelInfo['type']);
			}
			if (!empty($modelInfo['extension'])) {
				$extension = strtolower((string) $modelInfo['extension']);
			}
		}
		$codeLower = strtolower((string) $code);
		if ($type !== '' && $type !== 'pdf') {
			unset($models[$code]);
			continue;
		}
		if ($extension !== '' && $extension !== 'pdf') {
			unset($models[$code]);
			continue;
		}
		if (substr($codeLower, -4) === '_odt' || substr($codeLower, -4) === '.odt') {
			unset($models[$code]);
		}
	}

	return $models;
}

/**
* EN: Return the hour equivalents for each daily rate code (adds quarter-day when enabled).
* FR: Retourne les équivalences en heures pour chaque code forfait (ajoute le quart de jour si activé).
*
* @param bool $useQuarterDayDailyContract Flag for quarter-day support / Drapeau d'activation du quart de jour
* @return array<int,float>               Hour mapping by code / Correspondance heures par code
*/
function tw_get_daily_rate_hours_map($useQuarterDayDailyContract)
{
	$map = array(
		1 => 8.0,
		2 => 4.0,
		3 => 4.0,
	);
	if ($useQuarterDayDailyContract) {
		// EN: Expose the quarter-day option with its 2-hour contribution when the feature is enabled.
		// FR: Expose l'option quart de jour avec sa contribution de 2 heures lorsque la fonctionnalité est activée.
		$map[4] = 2.0;
	}
	return $map;
}

// ---- Permissions (nouveau modèle) ----
$permRead          = $user->hasRight('timesheetweek','read');
$permReadChild     = $user->hasRight('timesheetweek','readChild');
$permReadAll       = $user->hasRight('timesheetweek','readAll');

$permWrite         = $user->hasRight('timesheetweek','write');
$permWriteChild    = $user->hasRight('timesheetweek','writeChild');
$permWriteAll      = $user->hasRight('timesheetweek','writeAll');

$permValidate      = $user->hasRight('timesheetweek','validate');
$permValidateOwn   = $user->hasRight('timesheetweek','validateOwn');
$permValidateChild = $user->hasRight('timesheetweek','validateChild');
$permValidateAll   = $user->hasRight('timesheetweek','validateAll');

$permDelete        = $user->hasRight('timesheetweek','delete');
$permDeleteChild   = $user->hasRight('timesheetweek','deleteChild');
$permDeleteAll     = $user->hasRight('timesheetweek','deleteAll');

$permSeal          = $user->hasRight('timesheetweek','seal');
$permUnseal        = $user->hasRight('timesheetweek','unseal');

$permReadAny   = ($permRead || $permReadChild || $permReadAll);
$permWriteAny  = ($permWrite || $permWriteChild || $permWriteAll);
$permDeleteAny = ($permDelete || $permDeleteChild || $permDeleteAll);

// EN: Initialise the document creation permission flag to prevent undefined notices later.
// FR: Initialise l'indicateur de permission de création documentaire pour éviter les notices plus tard.
$permissiontoadd = 0;

/** helpers permissions **/
function tw_can_validate_timesheet(
	TimesheetWeek $o,
	User $user,
	$permValidate,
	$permValidateOwn,
	$permValidateChild,
	$permValidateAll,
	$permWrite = false,
	$permWriteChild = false,
	$permWriteAll = false
) {
	$hasExplicitValidation = ($permValidate || $permValidateOwn || $permValidateChild || $permValidateAll);

	if (!empty($user->admin)) {
		$permValidateAll = true;
		$hasExplicitValidation = true;
	}

	if (!$hasExplicitValidation) {
				// Aucun droit de validation explicite : on retombe sur l'ancien comportement basé sur l'écriture
		if ($permWriteAll) {
			$permValidateAll = true;
		}
		if ($permWriteChild) {
			$permValidateChild = true;
		}

		if ($permWrite || $permWriteChild || $permWriteAll) {
						// Autorise la validation lorsque l'utilisateur est désigné validateur
			if ((int) $o->fk_user_valid === (int) $user->id) {
				$permValidate = true;
			}

						// Ancien comportement : les managers pouvaient valider via writeChild
			if (!$permValidateChild && $permWriteChild) {
				$permValidateChild = true;
			}
		}
	}

	if ($permValidateAll) return true;
	if ($permValidateChild && tw_is_manager_of($o->fk_user, $user)) return true;
	if ($permValidateOwn && ((int) $user->id === (int) $o->fk_user)) return true;
	if ($permValidate && ((int) $user->id === (int) $o->fk_user_valid)) return true;

	return false;
}

// Sécurise l'objet si présent
if (!empty($id) && $object->id <= 0) $object->fetch($id);

$canSendMail = false;
if ($object->id > 0) {
	$canSendMail = tw_can_act_on_user($object->fk_user, $permRead, $permReadChild, $permReadAll, $user)
	|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
}

// ----------------- Inline edits (crayons) -----------------
if ($action === 'setfk_user' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOSTINT('fk_user');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	if ($newval > 0) {
		$object->fk_user = $newval;
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	}
	$action = '';
}

if ($action === 'setvalidator' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOSTINT('fk_user_valid');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->fk_user_valid = ($newval > 0 ? $newval : null);
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setnote' && $object->id > 0 && $object->status == tw_status('draft')) {
	$newval = GETPOST('note', 'restricthtml');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	$object->note = $newval;
	$res = $object->update($user);
	if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
	$action = '';
}

if ($action === 'setweekyear' && $object->id > 0 && $object->status == tw_status('draft')) {
	$weekyear = GETPOST('weekyear', 'alpha');
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) accessforbidden();
	if (preg_match('/^(\d{4})-W(\d{2})$/', $weekyear, $m)) {
		$object->year = (int) $m[1];
		$object->week = (int) $m[2];
		$res = $object->update($user);
		if ($res > 0) setEventMessages($langs->trans("RecordModified"), null, 'mesgs');
		else setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans("InvalidWeekFormat"), null, 'errors');
	}
	$action = '';
}

// ----------------- Action: prepare send mail -----------------
if ($action === 'presend' && $id > 0) {
	if ($object->id <= 0) {
		$object->fetch($id);
	}

	$canSendMail = tw_can_act_on_user($object->fk_user, $permRead, $permReadChild, $permReadAll, $user)
	|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
	if (!$canSendMail) {
		accessforbidden();
	}

	if (GETPOST('mode', 'aZ09') === 'init') {
		$langs->load('mails');

		$defaultRecipients = array();
		if ($object->fk_user > 0) {
			$tmpMailUser = new User($db);
			if ($tmpMailUser->fetch($object->fk_user) > 0 && !empty($tmpMailUser->email)) {
				$defaultRecipients[] = $tmpMailUser->email;
			}
		}
		if ($object->fk_user_valid > 0) {
			$tmpMailValidator = new User($db);
			if ($tmpMailValidator->fetch($object->fk_user_valid) > 0 && !empty($tmpMailValidator->email)) {
				$defaultRecipients[] = $tmpMailValidator->email;
			}
		}
		$defaultRecipients = array_unique($defaultRecipients);

		if (empty(GETPOST('sendto', 'none'))) {
			$_POST['sendto'] = implode(', ', $defaultRecipients);
		}

		$defaultFrom = !empty($user->email) ? $user->email : getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
		if (empty(GETPOST('replyto', 'none')) && !empty($defaultFrom)) {
			$_POST['replyto'] = $defaultFrom;
		}

		if (empty(GETPOST('subject', 'restricthtml'))) {
			$_POST['subject'] = $langs->trans('TimesheetWeekMailDefaultSubject', $object->ref);
		}

		if (empty(GETPOST('message', 'restricthtml'))) {
			$_POST['message'] = $langs->trans('TimesheetWeekMailDefaultBody', $object->ref, $object->week, $object->year);
		}
	}
}

if ($action === 'send' && $id > 0 && !$canSendMail) {
	accessforbidden();
}

if ($action === 'send' && $id > 0) {
	if ($object->id <= 0) {
		$object->fetch($id);
	}
	if (!is_array($object->context)) {
		$object->context = array();
	}
	$object->context['actioncode'] = 'TIMESHEETWEEK_SENTBYMAIL';
	$object->context['timesheetweek_card_action'] = 'send';
}

if (in_array($action, array('presend', 'send'), true)) {
	$langs->load('mails');
}

// ----------------- Action: Create (add) -----------------
if ($action === 'add') {
	if (!$permWriteAny) accessforbidden();

	$weekyear      = GETPOST('weekyear', 'alpha'); // YYYY-Wxx
	$fk_user       = GETPOSTINT('fk_user');
	$fk_user_valid = GETPOSTINT('fk_user_valid');
	$note          = GETPOST('note', 'restricthtml');

	$targetUserId = $fk_user > 0 ? $fk_user : $user->id;
	if (!tw_can_act_on_user($targetUserId, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$object->ref     = '(PROV)';
	$object->fk_user = $targetUserId;
	$object->status  = tw_status('draft');
	$object->note    = $note;

	// Validateur par défaut = manager du salarié cible si non fourni
	if ($fk_user_valid > 0) {
		$object->fk_user_valid = $fk_user_valid;
	} else {
		$uTmp = new User($db);
		$uTmp->fetch($targetUserId);
		$object->fk_user_valid = !empty($uTmp->fk_user) ? (int)$uTmp->fk_user : null;
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
		$shouldCreate = true; // EN: Flag to know if creation must proceed. FR: Indicateur pour savoir si la création doit continuer.
		if ($object->year > 0 && $object->week > 0) {
			$existing = new TimesheetWeek($db);
			// EN: Look for an existing timesheet for the same user and week.
			// FR: Recherche une feuille existante pour le même utilisateur et la même semaine.
			$existingRes = $existing->fetchByUserWeek($object->fk_user, $object->year, $object->week);
			if ($existingRes > 0) {
				setEventMessages($langs->trans('TimesheetWeekRedirectExisting'), null, 'warnings');
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$existing->id);
				exit;
			}
			if ($existingRes < 0) {
				setEventMessages($existing->error, $existing->errors, 'errors');
				$action = 'create';
				$shouldCreate = false;
			}
		}

		if ($shouldCreate) {
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
}

// ----------------- Action: Save grid lines (UPSERT SQL direct) -----------------
if ($action === 'save' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status != tw_status('draft')) {
		setEventMessages($langs->trans("TimesheetIsNotEditable"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		// EN: Stop the save gracefully without triggering a full access forbidden screen to remain user friendly.
		// FR: Stoppe l'enregistrement en douceur sans déclencher un écran d'accès interdit pour rester convivial.
		setEventMessages($langs->trans("ErrorForbidden"), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	
	// EN: Detect whether the employee relies on daily rate entries to adapt the save workflow.
	// FR: Détecte si le salarié relève du forfait jour afin d'adapter le flux d'enregistrement.
	$employeeInfoDaily = tw_get_employee_with_daily_rate($db, $object->fk_user);
	$isDailyRateEmployee = $employeeInfoDaily['is_daily_rate'];
	
	$db->begin();
	
	$map = array("Monday"=>0,"Tuesday"=>1,"Wednesday"=>2,"Thursday"=>3,"Friday"=>4,"Saturday"=>5,"Sunday"=>6);
	$processed = 0;
	// EN: Mirror the front-end quarter-day mapping when converting select choices into stored hours.
	// FR: Reflète le mapping quart de jour du front-end lors de la conversion des sélections en heures stockées.
	$dailyRateHours = tw_get_daily_rate_hours_map($useQuarterDayDailyContract);
	$cellPattern = $isDailyRateEmployee ? '/^daily_(\d+)_(\w+)$/' : '/^hours_(\d+)_(\w+)$/' ;
	
	foreach ($_POST as $key => $val) {
		if (preg_match($cellPattern, $key, $m)) {
			$taskid = (int) $m[1];
			$day = $m[2];
			$dailyRateValue = 0;
			$h = 0.0;
			if ($isDailyRateEmployee) {
				$dailyRateValue = (int) $val;
				$h = isset($dailyRateHours[$dailyRateValue]) ? (float) $dailyRateHours[$dailyRateValue] : 0.0;
			} else {
				$hoursStr = trim((string) $val);
				if ($hoursStr !== '') {
					if (strpos($hoursStr, ':') !== false) {
						$tmp = explode(':', $hoursStr, 2);
						$H = (int) ($tmp[0] ?? 0);
						$M = (int) ($tmp[1] ?? 0);
						$h = $H + ($M/60.0);
					} else {
						$h = (float) str_replace(',', '.', $hoursStr);
					}
				}
			}

			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week);
			$dto->modify('+'.$map[$day].' day');
			$datestr = $dto->format('Y-m-d');

			$zone = (int) GETPOST('zone_'.$day, 'int');
			$meal = GETPOST('meal_'.$day) ? 1 : 0;

			$sqlSel = "SELECT rowid FROM ".MAIN_DB_PREFIX."timesheet_week_line
			WHERE fk_timesheet_week=".(int)$object->id." AND fk_task=".(int)$taskid." AND day_date='".$db->escape($datestr)."'";
	// EN: Secure the lookup by enforcing the entity restriction of the module.
	// FR: Sécurise la recherche en appliquant la restriction d'entité du module.
			$sqlSel .= " AND entity IN (".getEntity('timesheetweek').")";
			$resSel = $db->query($sqlSel);
			$existingId = 0;
			if ($resSel && $db->num_rows($resSel) > 0) {
				$o = $db->fetch_object($resSel);
				$existingId = (int) $o->rowid;
			}

			if ($h > 0 && $taskid > 0) {
				if ($existingId > 0) {
					$sqlUpd = "UPDATE ".MAIN_DB_PREFIX."timesheet_week_line
					SET hours=".((float)$h).", daily_rate=".(int)$dailyRateValue.", zone=".(int)$zone.", meal=".(int)$meal."
					WHERE rowid=".$existingId;
										// EN: Guarantee that the update only affects rows from authorized entities.
										// FR: Garantit que la mise à jour ne concerne que les lignes des entités autorisées.
					$sqlUpd .= " AND entity IN (".getEntity('timesheetweek').")";
					if (!$db->query($sqlUpd)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				} else {
					// EN: Store the line within the same entity as its parent timesheet to stay consistent.
					// FR: Enregistre la ligne dans la même entité que sa feuille parente pour rester cohérent.
					$sqlIns = "INSERT INTO ".MAIN_DB_PREFIX."timesheet_week_line (entity, fk_timesheet_week, fk_task, day_date, hours, daily_rate, zone, meal) VALUES (".
						(int) $object->entity.", ".
						(int) $object->id.", ".
						(int) $taskid.", ".
						"'".$db->escape($datestr)."', ".
						((float) $h).", ".
						(int) $dailyRateValue.", ".
						(int) $zone.", ".
						(int) $meal.")";
					")";
					if (!$db->query($sqlIns)) {
						$db->rollback();
						setEventMessages($db->lasterror(), null, 'errors');
						header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
						exit;
					}
				}
				$processed++;
			} else {
				if ($existingId > 0) {
										// EN: Delete the line only if it belongs to an allowed entity scope.
										// FR: Supprime la ligne uniquement si elle appartient à une entité autorisée.
					$db->query("DELETE FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE rowid=".$existingId." AND entity IN (".getEntity('timesheetweek').")");
					$processed++;
				}
			}
		}
	}

		// EN: Reload the lines to work with the freshly stored data before aggregating.
		// FR: Recharge les lignes pour travailler sur les données fraîchement enregistrées avant l'agrégation.
	$linesReloaded = $object->fetchLines();
	if ($linesReloaded < 0) {
		$db->rollback();
		setEventMessages($object->error ? $object->error : $db->lasterror(), $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

		// EN: Recompute totals and counters so they follow the exact same flow as hours and overtime.
		// FR: Recalcule les totaux et les compteurs afin qu'ils suivent exactement le même flux que les heures et les heures supplémentaires.
	$object->computeTotals();
	$upd = $object->update($user);
	if ($upd < 0) {
		$db->rollback();
		setEventMessages($object->error, $object->errors, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$db->commit();
	setEventMessages($langs->trans("TimesheetSaved").' ('.$processed.' '.$langs->trans("Lines").')', null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Submit -----------------
if ($action === 'submit' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if (!in_array((int) $object->status, array((int) tw_status('draft'), (int) tw_status('refused')), true)) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
		accessforbidden();
	}

	$totalHours = 0.0;
	$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id;
		// EN: Keep the aggregation limited to the current entity scope for correctness.
		// FR: Limite l'agrégation à l'entité courante pour garantir la cohérence.
	$sqlSum .= " AND entity IN (".getEntity('timesheetweek').")";
	$resSum = $db->query($sqlSum);
	if ($resSum) {
		$o = $db->fetch_object($resSum);
		$totalHours = (float) $o->sh;
	}
	if ($totalHours <= 0) {
		setEventMessages($langs->trans("NoHoursToSubmit"), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	if (!is_array($object->context)) {
		$object->context = array();
	}
	$object->context['actioncode'] = 'TIMESHEETWEEK_SUBMIT';
	$object->context['timesheetweek_card_action'] = 'submit';

	$res = $object->submit($user);
	if ($res > 0) {
		setEventMessages($langs->trans("TimesheetSubmitted"), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Back to draft -----------------
if ($action === 'setdraft' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

	if ($object->status == tw_status('sealed')) {
				// EN: Block direct draft revert on sealed sheets.
				// FR : Empêche de repasser en brouillon une feuille scellée sans la desceller.
		setEventMessages($langs->trans('CannotSetDraftWhenSealed'), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	if ($object->status == tw_status('draft')) {
		setEventMessages($langs->trans("AlreadyDraft"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$canEmployee  = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
	$canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
	if (!$canEmployee && !$canValidator) accessforbidden();

	if (!is_array($object->context)) {
		$object->context = array();
	}
	$object->context['timesheetweek_card_action'] = 'setdraft';

	$res = $object->revertToDraft($user);
	if ($res > 0) {
		setEventMessages($langs->trans("StatusSetToDraft"), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: ASK APPROVE / REFUSE (confirm popups) -----------------
if ($action === 'ask_validate' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) accessforbidden();
	if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) accessforbidden();
}
if ($action === 'ask_refuse' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) accessforbidden();
	if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) accessforbidden();
}

// ----------------- Action: CONFIRM APPROVE (Approuver) -----------------
if ($action === 'confirm_validate' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
		accessforbidden();
	}

	if (!is_array($object->context)) {
		$object->context = array();
	}
	$object->context['actioncode'] = 'TIMESHEETWEEK_APPROVE';
	$object->context['timesheetweek_card_action'] = 'confirm_validate';

	$res = $object->approve($user);
	if ($res > 0) {
		setEventMessages($langs->trans("TimesheetApproved"), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: CONFIRM REFUSE (Refuser) -----------------
if ($action === 'confirm_refuse' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if ($object->status != tw_status('submitted')) {
		setEventMessages($langs->trans("ActionNotAllowedOnThisStatus"), null, 'warnings');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	if (!tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)) {
		accessforbidden();
	}

	if (!is_array($object->context)) {
		$object->context = array();
	}
	$object->context['actioncode'] = 'TIMESHEETWEEK_REFUSE';
	$object->context['timesheetweek_card_action'] = 'confirm_refuse';

	$res = $object->refuse($user);
	if ($res > 0) {
		setEventMessages($langs->trans("TimesheetRefused"), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: SEAL -----------------
if ($action === 'seal' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if (!$permSeal) accessforbidden();

	if (!is_array($object->context)) {
		$object->context = array();
	}
		// EN: Flag the current action for triggers and logs.
		// FR : Marque l'action courante pour les triggers et journaux.
	$object->context['timesheetweek_card_action'] = 'seal';

	$res = $object->seal($user);
	if ($res > 0) {
		setEventMessages($langs->trans('TimesheetSealed'), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: UNSEAL -----------------
if ($action === 'unseal' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);
	if (!$permUnseal) accessforbidden();

	if (!is_array($object->context)) {
		$object->context = array();
	}
		// EN: Flag the unseal action to inform future triggers.
		// FR : Marque l'action de descellage pour informer les triggers.
	$object->context['timesheetweek_card_action'] = 'unseal';

	$res = $object->unseal($user);
	if ($res > 0) {
		setEventMessages($langs->trans('TimesheetUnsealed'), null, 'mesgs');
	} else {
		$errmsg = tw_translate_error($object->error, $langs);
		setEventMessages($errmsg, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// ----------------- Action: Delete -----------------
if ($action === 'confirm_delete' && $confirm === 'yes' && $id > 0) {
	if ($object->id <= 0) $object->fetch($id);

		// On autorise la suppression si l'utilisateur a les droits (own/child/all),
		// ou s'il a des droits validate* (validateur), quelque soit le statut
	$canDelete = tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)
	|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);

	if (!$canDelete) accessforbidden();

	$res = $object->delete($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/timesheetweek/timesheetweek_list.php',1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($object->id > 0) {
	$modelmail = 'timesheetweek';
	$defaulttopic = $langs->trans('TimesheetWeekMailDefaultSubject', $object->ref);
	$defaultmessage = $langs->trans('TimesheetWeekMailDefaultBody', $object->ref, $object->week, $object->year);
	$triggersendname = 'TIMESHEETWEEK_SENTBYMAIL';
	$trackid = 'timesheetweek'.$object->id;
	$permissiontosend = $canSendMail;
	$diroutput = isset($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : (defined('DOL_DATA_ROOT') ? DOL_DATA_ROOT.'/timesheetweek' : '');

	$moresubstit = array(
		'__TIMESHEETWEEK_REF__' => $object->ref,
		'__TIMESHEETWEEK_WEEK__' => $object->week,
		'__TIMESHEETWEEK_YEAR__' => $object->year,
		'__TIMESHEETWEEK_STATUS__' => $object->getLibStatut(0),
	);

	if ($object->fk_user > 0) {
		$employee = new User($db);
		if ($employee->fetch($object->fk_user) > 0) {
			$moresubstit['__TIMESHEETWEEK_EMPLOYEE_FULLNAME__'] = $employee->getFullName($langs);
			$moresubstit['__TIMESHEETWEEK_EMPLOYEE_EMAIL__'] = $employee->email;
		}
	}
	if ($object->fk_user_valid > 0) {
		$validator = new User($db);
		if ($validator->fetch($object->fk_user_valid) > 0) {
			$moresubstit['__TIMESHEETWEEK_VALIDATOR_FULLNAME__'] = $validator->getFullName($langs);
			$moresubstit['__TIMESHEETWEEK_VALIDATOR_EMAIL__'] = $validator->email;
		}
	}

	$param = array(
		'sendcontext' => 'timesheetweek',
		'returnurl' => dol_buildpath('/timesheetweek/timesheetweek_card.php', 1).'?id='.$object->id,
		'models' => $modelmail,
		'trackid' => $trackid,
	);

	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';

		// EN: Prepare PDF generation permissions once the object is fully loaded.
		// FR: Prépare les permissions de génération PDF une fois l'objet complètement chargé.
	$entityIdForDocs = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	$baseTimesheetDir = '';
	if (!empty($conf->timesheetweek->multidir_output[$entityIdForDocs])) {
		$baseTimesheetDir = $conf->timesheetweek->multidir_output[$entityIdForDocs];
	} elseif (!empty($conf->timesheetweek->dir_output)) {
		$baseTimesheetDir = $conf->timesheetweek->dir_output;
	} else {
		$baseTimesheetDir = DOL_DATA_ROOT.'/timesheetweek';
	}
	$upload_dir = $baseTimesheetDir.'/timesheetweek/'.dol_sanitizeFileName($object->ref);

		// EN: Authorise document creation to employees or managers allowed to act on the sheet.
		// FR: Autorise la création de documents aux salariés ou responsables habilités à agir sur la feuille.
	$permissiontoadd = (
		tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)
		|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll)
		|| !empty($user->admin)
	);

	if ($permissiontoadd && GETPOST('model', 'alpha')) {
			// EN: Allow PDF model changes only to users authorised to act on the sheet.
			// FR: Autorise le changement de modèle PDF uniquement aux utilisateurs habilités à agir sur la feuille.
			// EN: Allow administrators to switch the PDF model directly from the card view.
			// FR: Permet aux administrateurs de changer le modèle PDF directement depuis la fiche.
		$object->setDocModel($user, GETPOST('model', 'alpha'));
		$object->model_pdf = GETPOST('model', 'alpha');
	}

	if (empty($object->model_pdf)) {
			// EN: Default to the module configuration when no PDF model has been selected yet.
			// FR: Bascule sur la configuration du module lorsqu'aucun modèle PDF n'est encore sélectionné.
		$object->model_pdf = getDolGlobalString('TIMESHEETWEEK_ADDON_PDF', 'standard_timesheetweek');
	}

	$moreparams = array(
		'hidedetails' => $hidedetails,
		'hidedesc' => $hidedesc,
		'hideref' => $hideref,
	);

	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

				// EN: Manage attachment upload and deletion with Dolibarr helper to keep buttons functional.
				// FR: Gère l'envoi et la suppression des pièces jointes avec l'aide Dolibarr pour garder les boutons fonctionnels.
	if ($action === 'remove_file') {
		if (empty($permissiontoadd)) {
						// EN: Block removal requests when the user lacks the document permission.
						// FR: Bloque les demandes de suppression lorsque l'utilisateur n'a pas la permission sur le document.
			setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
			$action = '';
		} else {
						// EN: Retrieve the requested filename and default to the generated PDF when missing.
						// FR: Récupère le nom de fichier demandé et prend par défaut le PDF généré lorsqu'il est absent.
			$requestedFile = GETPOST('file', 'alphanohtml', 0, null, null, 1);
			if ($requestedFile === '' && !empty($object->ref)) {
				$requestedFile = dol_sanitizeFileName($object->ref).'.pdf';
			}
						// EN: Reduce the requested file to its basename to match Dolibarr's document deletion URL.
						// FR: Réduit le fichier demandé à son basename pour respecter l'URL de suppression Dolibarr.
			$requestedFile = dol_sanitizeFileName(basename((string) $requestedFile));
			if ($requestedFile !== '') {
							// EN: Store the sanitized filename for the confirmation dialog and Dolibarr workflow.
							// FR: Stocke le nom de fichier assaini pour la boîte de confirmation et le flux Dolibarr.
				$_GET['file'] = $requestedFile;
				$_REQUEST['file'] = $requestedFile;
				$_GET['urlfile'] = $requestedFile;
				$_REQUEST['urlfile'] = $requestedFile;
				$action = 'deletefile';
			} else {
							// EN: Warn the user when no filename is provided in the deletion URL.
							// FR: Avertit l'utilisateur lorsqu'aucun nom de fichier n'est fourni dans l'URL de suppression.
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('File')), null, 'errors');
				$action = '';
			}
		}
	}

	if (!empty($upload_dir)) {
				// EN: Mirror the dedicated documents tab behaviour for permissions and storage scope.
				// FR: Reproduit le comportement de l'onglet documents pour les permissions et le périmètre de stockage.
		$modulepart = 'timesheetweek';
		$permissiontoread = $permReadAny ? 1 : 0;
		$permissiontoadd = $permissiontoadd ? 1 : 0;
		$permissiontodownload = $permissiontoread;
		$permissiontodelete = $permissiontoadd;
		include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
	}
}

// ----------------- View -----------------
$form = new Form($db);
$formfile = new FormFile($db);
$title = $langs->trans("TimesheetWeek");

// EN: Render the header only after permission guards to avoid duplicated menus on errors.
// FR: Affiche l'en-tête uniquement après les gardes de permissions pour éviter les menus dupliqués en cas d'erreur.

// ---- CREATE MODE ----
if ($action === 'create') {
	if (!$permWriteAny) {
		// EN: Stop unauthorized creation attempts before any layout is printed.
		// FR: Stoppe les tentatives de création non autorisées avant tout affichage de mise en page.
		accessforbidden();
	}

	llxHeader('', $title);

	print load_fiche_titre($langs->trans("NewTimesheetWeek"), '', 'bookcal');

	echo '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	echo '<input type="hidden" name="token" value="'.newToken().'">';
	echo '<input type="hidden" name="action" value="add">';

	echo '<table class="border centpercent">';

	// Employé
	echo '<tr>';
	echo '<td class="titlefield">'.$langs->trans("Employee").'</td>';
	// EN: Display the employee selector with avatars to align with Dolibarr UX.
	// FR: Affiche le sélecteur salarié avec avatars pour rester cohérent avec l'UX Dolibarr.
	echo '<td>'.$form->select_dolusers($user->id, 'fk_user', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1).'</td>';

	echo '</tr>';

	// Semaine
	echo '<tr>';
	echo '<td>'.$langs->trans("Week").'</td>';
	echo '<td>'.getWeekSelectorDolibarr($form, 'weekyear').'<div id="weekrange" class="opacitymedium paddingleft small"></div></td>';
	echo '</tr>';

	// Validateur (défaut = manager)
	$defaultValidatorId = !empty($user->fk_user) ? (int)$user->fk_user : 0;
	echo '<tr>';
	echo '<td>'.$langs->trans("Validator").'</td>';
	// EN: Pre-select the default validator with picture support for clarity.
	// FR: Pré-sélectionne le validateur par défaut avec prise en charge de la photo pour plus de clarté.
	echo '<td>'.$form->select_dolusers($defaultValidatorId, 'fk_user_valid', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1).'</td>';

	echo '</tr>';

	// Note
	echo '<tr>';
	echo '<td>'.$langs->trans("Note").'</td>';
	echo '<td><textarea name="note" class="quatrevingtpercent" rows="3"></textarea></td>';
	echo '</tr>';

	echo '</table>';

	echo '<div class="center">';
	echo '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
	echo '&nbsp;<a class="button button-cancel" href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("Cancel").'</a>';
	echo '</div>';

	echo '</form>';

	$jsWeek = <<<'JS'
	<script>
	(function ($) {
		function parseYearWeek(val) {
			var m=/^(\d{4})-W(\d{2})$/.exec(val||'');return m?{y:parseInt(m[1],10),w:parseInt(m[2],10)}:null;
		}
		function isoWeekStart(y,w){var s=new Date(Date.UTC(y,0,1+(w-1)*7));var d=s.getUTCDay();var st=new Date(s);if(d>=1&&d<=4)st.setUTCDate(s.getUTCDate()-(d-1));else st.setUTCDate(s.getUTCDate()+(d===0?1:(8-d)));return st;}
		// EN: Local helper to zero-pad day/month values for legacy browser compatibility.
		// FR: Aide locale pour compléter les jours/mois avec un zéro et rester compatible avec les anciens navigateurs.
		function pad2(v){return (v<10?'0':'')+v;}
		function fmt(d){var dd=pad2(d.getUTCDate());var mm=pad2(d.getUTCMonth()+1);var yy=d.getUTCFullYear();return dd+'/'+mm+'/'+yy;}
		function updateWeekRange(){var v=$('#weekyear').val();var p=parseYearWeek(v);if(!p){$('#weekrange').text('');return;}var s=isoWeekStart(p.y,p.w);var e=new Date(s);e.setUTCDate(s.getUTCDate()+6);$('#weekrange').text('du '+fmt(s)+' au '+fmt(e));}
		$(function(){if($.fn.select2)$('#weekyear').select2({width:'resolve'});updateWeekRange();$('#weekyear').on('change',updateWeekRange);});
	})(jQuery);
	</script>
	JS;
	echo $jsWeek;

} else if ($id > 0) {
	// ---- READ MODE (fiche + grille) ----
	if (!tw_can_act_on_user($object->fk_user, $permRead, $permReadChild, $permReadAll, $user)) {
		// EN: Reject access to foreign sheets before emitting the global page structure.
		// FR: Refuse l'accès aux feuilles étrangères avant d'émettre la structure globale de la page.
		accessforbidden();
	}

	llxHeader('', $title);

	// Head + banner
	$head = timesheetweekPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimesheetWeek"), -1, 'bookcal');

	$linkback = '<a href="'.dol_buildpath('/timesheetweek/timesheetweek_list.php',1).'">'.$langs->trans("BackToList").'</a>';
	$morehtmlright = '';
	$morehtmlstatus = '';
	if (!empty($object->id)) {
		$morehtmlstatus = $object->getLibStatut(5);
	}

	$morehtmlref = '';
	if (!empty($conf->multicompany->enabled) && (int) $object->entity !== (int) $conf->entity) {
				// EN: Fetch the entity label to display the native Multicompany badge below the reference.
				// FR: Récupère le libellé de l'entité pour afficher le badge Multicompany natif sous la référence.
		$entityName = '';
		$entityId = (int) $object->entity;
		if ($entityId > 0) {
			$sqlEntity = 'SELECT label FROM '.MAIN_DB_PREFIX."entity WHERE rowid = ".$entityId;
			$resEntity = $db->query($sqlEntity);
			if ($resEntity) {
				$entityRow = $db->fetch_object($resEntity);
				if ($entityRow) {
					$entityName = trim((string) $entityRow->label);
				}
				$db->free($resEntity);
			}
			if ($entityName === '') {
								// EN: Fall back to a generic label when the entity dictionary is empty.
								// FR: Revient à un libellé générique lorsque le dictionnaire d'entités est vide.
				$entityName = $langs->trans('Entity').' #'.$entityId;
			}
			$entityBadge = '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityName).'</span></div>';
						// EN: Inject the entity badge underneath the reference to mimic Dolibarr's native layout.
						// FR: Insère le badge d'entité sous la référence pour reproduire la mise en page native de Dolibarr.
			$morehtmlref .= '<br>'.$entityBadge;
		}
	}

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', $morehtmlright, '', $morehtmlstatus);
	print timesheetweekRenderStatusBadgeCleanup();

		// Confirm modals
	if ($action === 'deletefile') {
			// EN: Ask for confirmation before delegating the deletion to Dolibarr core.
			// FR: Demande une confirmation avant de déléguer la suppression au cœur de Dolibarr.
		$urlfileForConfirm = GETPOST('urlfile', 'alphanohtml', 0, null, null, 1);
			// EN: Keep track of the file parameter required by the document workflow.
			// FR: Conserve le paramètre file requis par le workflow documentaire.
		$confirmFileParam = GETPOST('file', 'alphanohtml', 0, null, null, 1);
		$linkIdForConfirm = GETPOSTINT('linkid');
		$confirmUrl = $_SERVER["PHP_SELF"].'?id='.$object->id;
		if ($urlfileForConfirm !== '') {
			$confirmUrl .= '&urlfile='.urlencode($urlfileForConfirm);
		}
		if ($confirmFileParam === '' && !empty($object->ref)) {
			$confirmFileParam = dol_sanitizeFileName($object->ref).'.pdf';
		}
		if ($confirmFileParam !== '') {
			$confirmUrl .= '&file='.urlencode($confirmFileParam);
		}
		if ($linkIdForConfirm > 0) {
			$confirmUrl .= '&linkid='.$linkIdForConfirm;
		}
		$formconfirm = $form->formconfirm(
			$confirmUrl,
			$langs->trans('DeleteFile'),
			$langs->trans('ConfirmDeleteFile'),
			'confirm_deletefile',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}
	if ($action === 'delete') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDeleteObject'),
			'confirm_delete',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}
	if ($action === 'ask_validate') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'),
			$langs->trans('ConfirmValidate'),
			'confirm_validate',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}
	if ($action === 'ask_refuse') {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans("Refuse"),
			$langs->trans('ConfirmRefuse'),
			'confirm_refuse',
			array(),
			'yes',
			1
		);
		print $formconfirm;
	}

	echo '<div class="fichecenter">';

	$canEditInline = ($object->status == tw_status('draft') && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user));

	// Left block
	echo '<div class="fichehalfleft">';
	echo '<table class="border centpercent tableforfield">';

		// Employé
	echo '<tr><td class="titlefield">'.$langs->trans("Employee").'</td><td>';
	if ($action === 'editfk_user' && $canEditInline) {
		echo '<form id="tw-grid-form" class="tw-grid-form" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setfk_user">';
			// EN: Keep avatar rendering when editing the employee inline.
			// FR: Conserve l'affichage des avatars lors de l'édition du salarié en ligne.
		echo $form->select_dolusers($object->fk_user, 'fk_user', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1);
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		if ($object->fk_user > 0) {
			$u = new User($db);
			$u->fetch($object->fk_user);
				// EN: Display the Dolibarr avatar and badge to stay consistent with core cards.
				// FR: Affiche l'avatar Dolibarr et le badge pour rester cohérent avec les fiches cœur.
			echo $u->getNomUrl(-1);
		}
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editfk_user" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Semaine
	echo '<tr><td>'.$langs->trans("Week").'</td><td>';
	if ($action === 'editweekyear' && $canEditInline) {
		$prefill = sprintf("%04d-W%02d", (int)$object->year, (int)$object->week);
		echo '<form id="tw-grid-form" class="tw-grid-form" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setweekyear">';
		echo getWeekSelectorDolibarr($form, 'weekyear', $prefill);
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		echo dol_escape_htmltag($object->week).' / '.dol_escape_htmltag($object->year);
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editweekyear" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Note
	echo '<tr><td>'.$langs->trans("Note").'</td><td>';
	if ($action === 'editnote' && $canEditInline) {
		echo '<form id="tw-grid-form" class="tw-grid-form" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setnote">';
		echo '<textarea name="note" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag($object->note).'</textarea>';
		echo '<br><input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		echo nl2br(dol_escape_htmltag($object->note));
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editnote" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	// Validator
	echo '<tr><td>'.$langs->trans("Validator").'</td><td>';
	if ($action === 'editvalidator' && $canEditInline) {
		echo '<form id="tw-grid-form" class="tw-grid-form" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		echo '<input type="hidden" name="token" value="'.newToken().'">';
		echo '<input type="hidden" name="action" value="setvalidator">';
			// EN: Preserve photo display while updating the validator inline.
			// FR: Préserve l'affichage de la photo lors de la mise à jour du validateur en ligne.
		echo $form->select_dolusers($object->fk_user_valid, 'fk_user_valid', 1, '', '', 0, -1, '', 0, 'maxwidth200', '', '', '', 1);
		echo '&nbsp;<input type="submit" class="button small" value="'.$langs->trans("Save").'">';
		echo '&nbsp;<a class="button small button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
		echo '</form>';
	} else {
		if ($object->fk_user_valid > 0) {
			$v = new User($db);
			$v->fetch($object->fk_user_valid);
				// EN: Render the validator with avatar to match Dolibarr consistency.
				// FR: Affiche le validateur avec avatar pour respecter la cohérence Dolibarr.
			echo $v->getNomUrl(-1);
		}
		if ($canEditInline) {
			echo ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editvalidator" title="'.$langs->trans("Edit").'">'.img_edit('',1).'</a>';
		}
	}
	echo '</td></tr>';

	echo '</table>';
	echo '</div>';

	// EN: Load the employee once to reuse the daily rate flag across the header and grid.
	// FR: Charge le salarié une seule fois pour réutiliser le flag forfait jour dans l'entête et la grille.
	$employeeInfoDisplay = tw_get_employee_with_daily_rate($db, $object->fk_user);
	$timesheetEmployee = $employeeInfoDisplay['user'];
	$isDailyRateEmployee = $employeeInfoDisplay['is_daily_rate'];

		// Right block (Totaux en entête)
	$contractedHoursDisp = ($object->contract !== null ? (float) $object->contract : 35.0);
	if ($contractedHoursDisp <= 0 && $timesheetEmployee instanceof User) {
		$contractedHoursDisp = !empty($timesheetEmployee->weeklyhours) ? (float) $timesheetEmployee->weeklyhours : 35.0;
	}
	$th = (float) $object->total_hours;
	$ot = (float) $object->overtime_hours;
	if ($th <= 0) {
		$sqlSum = "SELECT SUM(hours) as sh FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int) $object->id;
			// EN: Respect entity boundaries when recomputing totals lazily.
			// FR: Respecte les frontières d'entité lors du recalcul paresseux des totaux.
		$sqlSum .= " AND entity IN (".getEntity('timesheetweek').")";
		$resSum = $db->query($sqlSum);
		if ($resSum) {
			$o = $db->fetch_object($resSum);
			$th = (float) $o->sh;
			$ot = max(0.0, $th - $contractedHoursDisp);
		}
	}
	$displayedTotal = $th;
	$displayedTotalLabel = $langs->trans("TotalHours");
	$headerTotalClass = 'header-total-hours header-total-main';
	if ($isDailyRateEmployee) {
			// EN: Convert stored hours into days for header display when the employee is forfait jour.
			// FR: Convertit les heures enregistrées en jours pour l'affichage d'entête lorsque le salarié est au forfait jour.
		$displayedTotal = ($th > 0 ? ($th / 8.0) : 0.0);
		$displayedTotalLabel = $langs->trans("TotalDays");
		$headerTotalClass = 'header-total-days header-total-main';
	}
	echo '<div class="fichehalfright">';
	echo '<table class="border centpercent tableforfield">';
	echo '<tr><td>'.$langs->trans("DateCreation").'</td><td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';
	echo '<tr><td>'.$langs->trans("LastModification").'</td><td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';
	echo '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
	if ($isDailyRateEmployee) {
		echo '<tr><td>'.$displayedTotalLabel.'</td><td><span class="'.$headerTotalClass.'">'.tw_format_days($displayedTotal, $langs).'</span></td></tr>';
	} else {
		echo '<tr><td>'.$displayedTotalLabel.'</td><td><span class="'.$headerTotalClass.'">'.formatHours($displayedTotal).'</span></td></tr>';
		echo '<tr><td>'.$langs->trans("Overtime").' ('.formatHours($contractedHoursDisp).')</td><td><span class="header-overtime">'.formatHours($ot).'</span></td></tr>';
	}
	echo '</table>';
	echo '</div>';

	echo '</div>'; // fichecenter

	// place correctement la grille
	echo '<div class="clearboth"></div>';

	// Clôt la fiche AVANT la grille
		echo '</div>'; // tw-card-header-content

	print dol_get_fiche_end();

	// ------- GRID (Assigned Tasks grouped by Project) -------
	echo '<div class="fiche tw-grid-fiche">';
	echo '<div class="tabBar tw-grid-tabbar">';
	echo '<div class="fichecenter tw-grid-fichecenter">';

	echo '<form id="tw-grid-form" class="tw-grid-form" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	echo '<input type="hidden" name="token" value="'.newToken().'">';
	echo '<input type="hidden" name="action" value="save">';

	$token = newToken();
	// Mobile: floating mini actions
	echo '<div class="tw-mobile-actions">';

	if ($object->status == tw_status('sealed')) {
			// EN: In sealed state only show the unseal control for authorized users.
			// FR : En statut scellé, n'afficher que l'action de descellage pour les utilisateurs autorisés.
		if ($permUnseal) {
			echo dolGetButtonAction('', $langs->trans('UnsealTimesheet'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unseal&token='.$token);
		}
	} else {
		if ($canSendMail) {
			echo dolGetButtonAction('', $langs->trans('Sendbymail'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&token='.$token);
		}
	
			// Soumettre : uniquement brouillon + au moins 1 ligne existante + droits
		if ($object->status == tw_status('draft')) {
					// Compter les lignes
			$nbLines = 0;
					// EN: Count lines only within authorized entities before enabling submission.
					// FR: Compte les lignes uniquement dans les entités autorisées avant d'autoriser la soumission.
			$rescnt = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id." AND entity IN (".getEntity('timesheetweek').")");
			if ($rescnt) { $o=$db->fetch_object($rescnt); $nbLines=(int)$o->nb; }
			if ($nbLines > 0 && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
				//echo dolGetButtonAction('', $langs->trans("Submit"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit&token='.$token);
				echo '<button type="submit" class="tw-fab tw-fab-save" title="'.$langs->trans("Save").'"><i class="fa fa-save"></i></button>';
			}
		}
	
			// Retour brouillon : si statut != brouillon (soumis / approuvé / refusé) pour salarié/or valideur
		if ($object->status != tw_status('draft')) {
			$canEmployee  = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
			$canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
			if ($canEmployee || $canValidator) {
				//echo dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setdraft&token='.$token);
				echo '<button type="button" class="tw-fab tw-fab-setdraft" id="twMobileSetdraftFab" title="'.$langs->trans("SetToDraft").'"><i class="fa fa-undo"></i></button>';
			}
		}
	
			// Approuver / Refuser quand soumis (validateur/manager/all/own)
		if ($object->status == tw_status('submitted')) {
			$canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
			if ($canValidator) {
				//echo dolGetButtonAction('', ($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_validate&token='.$token);
				echo '<button type="button" class="tw-fab tw-fab-approve" id="twMobileApproveFab" title="'.$langs->trans("Approve").'"><i class="fa fa-check"></i></button>';
				//echo dolGetButtonAction('', $langs->trans("Refuse"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse&token='.$token);
				echo '<button type="button" class="tw-fab tw-fab-refuse" id="twMobileRefuseFab" title="'.$langs->trans("Refuse").'"><i class="fa fa-times"></i></button>';
				
			}
		}
	
			// EN: Allow sealing once the sheet is approved and the user is authorized.
			// FR : Autorise le scellement dès que la feuille est approuvée et que l'utilisateur est habilité.
		if ($object->status == tw_status('approved') && $permSeal) {
			//echo dolGetButtonAction('', $langs->trans('SealTimesheet'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=seal&token='.$token);
			echo '<button type="button" class="tw-fab tw-fab-seal" id="twMobileSealFab" title="'.$langs->trans("Seal").'"><i class="fa fa-lock"></i></button>';
		}
	
			// Supprimer : brouillon OU soumis/approuvé/refusé si salarié (delete) ou validateur (validate*) ou all
		$canDelete = tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)
		|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
		if ($canDelete) {
			//echo dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.$token);
			echo '<button type="button" class="tw-fab tw-fab-delete" id="twMobileDeleteFab" title="'.$langs->trans("Delete").'"><i class="fa fa-trash"></i></button>';
			
		}
	
	echo '<button type="button" class="tw-fab tw-fab-details" id="twMobileHeaderFab" title="'.$langs->trans("Details").'"><i class="fa fa-info-circle"></i></button>';
	echo '</div>';
	


	echo '<h3>'.$langs->trans("AssignedTasks").'</h3>';

	// 1) CHARGER LIGNES EXISTANTES
$hoursBy = array(); // [taskid][YYYY-mm-dd] = hours
$dailyRateBy = array(); // [taskid][YYYY-mm-dd] = daily rate code
// EN: Track legacy afternoon code usage to keep it available in quarter-day mode.
// FR: Suit l'utilisation de l'ancien code après-midi pour le conserver en mode quart de jour.
$hasLegacyHalfDayDailyRate = false;
$dayMeal = array('Monday'=>0,'Tuesday'=>0,'Wednesday'=>0,'Thursday'=>0,'Friday'=>0,'Saturday'=>0,'Sunday'=>0);
$dayZone = array('Monday'=>null,'Tuesday'=>null,'Wednesday'=>null,'Thursday'=>null,'Friday'=>null,'Saturday'=>null,'Sunday'=>null);
$taskIdsFromLines = array();
$linesCount = 0;

$sqlLines = "SELECT fk_task, day_date, hours, daily_rate, zone, meal
FROM ".MAIN_DB_PREFIX."timesheet_week_line
WHERE fk_timesheet_week=".(int)$object->id;
		// EN: Limit the fetched lines to those belonging to authorized entities.
		// FR: Limite les lignes récupérées à celles appartenant aux entités autorisées.
$sqlLines .= " AND entity IN (".getEntity('timesheetweek').")";
$resLines = $db->query($sqlLines);
if ($resLines) {
	while ($o = $db->fetch_object($resLines)) {
		$linesCount++;
		$fk_task = (int)$o->fk_task;
		$daydate = $o->day_date;
		$hours   = (float)$o->hours;
		$dailyRate = isset($o->daily_rate) ? (int)$o->daily_rate : 0;
		$zone    = isset($o->zone) ? (int)$o->zone : null;
		$meal    = (int)$o->meal;

		if (!isset($hoursBy[$fk_task])) $hoursBy[$fk_task] = array();
		$hoursBy[$fk_task][$daydate] = $hours;
		if (!isset($dailyRateBy[$fk_task])) $dailyRateBy[$fk_task] = array();
		$dailyRateBy[$fk_task][$daydate] = $dailyRate;
		if ($dailyRate === 3) {
// EN: Remember the legacy afternoon code to keep it selectable when quarter-day mode is active.
// FR: Retient l'ancien code après-midi pour le rendre sélectionnable lorsque le mode quart de jour est actif.
			$hasLegacyHalfDayDailyRate = true;
		}

		$w = (int) date('N', strtotime($daydate));
		$dayName = array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday')[$w];

		if ($meal) $dayMeal[$dayName] = 1;
		if ($zone !== null) $dayZone[$dayName] = $zone;

		$taskIdsFromLines[$fk_task] = 1;
	}
}

	// 2) RÉCUPÉRER LES TÂCHES ASSIGNÉES
		$tasks = $object->getAssignedTasks($object->fk_user); // id, label, project_id, project_ref, project_title, task_ref?
		$tasksById = array();
		if (!empty($tasks)) {
			foreach ($tasks as $t) {
				$tasksById[(int)$t['task_id']] = $t;
			}
		}

	// 3) COMPLÉTER AVEC TÂCHES PRÉSENTES DANS LES LIGNES MAIS PAS DANS LES ASSIGNATIONS
		if (!empty($taskIdsFromLines)) {
			$missing = array();
			foreach (array_keys($taskIdsFromLines) as $tid) {
				if (!isset($tasksById[$tid])) $missing[] = (int)$tid;
			}
			if (!empty($missing)) {
						// EN: Bring in the same enriched task metadata for unassigned lines to keep filtering consistent.
						// FR: Récupère les mêmes métadonnées enrichies pour les lignes non assignées afin d'harmoniser le filtrage.
				$sqlMiss = "SELECT t.rowid as task_id, t.label as task_label, t.ref as task_ref, t.progress as task_progress,
				t.fk_statut as task_status, t.dateo as task_date_start, t.datee as task_date_end,
				p.rowid as project_id, p.ref as project_ref, p.title as project_title
				FROM ".MAIN_DB_PREFIX."projet_task t
				INNER JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = t.fk_projet
				WHERE t.rowid IN (".implode(',', array_map('intval',$missing)).")";
				$resMiss = $db->query($sqlMiss);
				if ($resMiss) {
					while ($o = $db->fetch_object($resMiss)) {
						$tasks[] = array(
							'task_id'       => (int)$o->task_id,
							'task_label'    => $o->task_label,
							'task_ref'      => $o->task_ref,
							'task_progress' => ($o->task_progress !== null ? (float)$o->task_progress : null),
							'task_status'   => ($o->task_status !== null ? (int)$o->task_status : null),
							'task_date_start' => ($o->task_date_start !== null ? (string)$o->task_date_start : null),
							'task_date_end' => ($o->task_date_end !== null ? (string)$o->task_date_end : null),
							'project_id'    => (int)$o->project_id,
							'project_ref'   => $o->project_ref,
							'project_title' => $o->project_title
						);
					}
				}
			}
		}

		$days = array("Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday");
		// EN: Map ISO day names to dedicated translation keys for full labels.
		// FR: Associe les noms ISO aux clés de traduction dédiées pour les libellés complets.
		$dayLabelKeys = array(
			'Monday'    => 'TimesheetWeekDayMonday',
			'Tuesday'   => 'TimesheetWeekDayTuesday',
			'Wednesday' => 'TimesheetWeekDayWednesday',
			'Thursday'  => 'TimesheetWeekDayThursday',
			'Friday'    => 'TimesheetWeekDayFriday',
			'Saturday'  => 'TimesheetWeekDaySaturday',
			'Sunday'    => 'TimesheetWeekDaySunday',
		);
		$weekdates = array();
		$weekStartDate = null;
		$weekEndDate = null;
		// EN: Derive week boundaries defensively because the week metadata can be missing on drafts.
		// FR: Calcule prudemment les bornes de semaine car les métadonnées peuvent manquer sur les brouillons.
		if (!empty($object->year) && !empty($object->week)) {
			$dto = new DateTime();
			$dto->setISODate((int)$object->year, (int)$object->week);
			foreach ($days as $d) {
				$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
				$weekdates[$d] = $dto->format('Y-m-d');
				$dto->modify('+1 day');
			}
			$weekStartDate = isset($weekdates['Monday']) ? $weekdates['Monday'] : null;
			$weekEndDate = isset($weekdates['Sunday']) ? $weekdates['Sunday'] : null;
		} else {
			foreach ($days as $d) {
				$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
				$weekdates[$d] = null;
			}
		}

		if (!empty($tasks)) {
				// EN: Define closed statuses dynamically to remain compatible across Dolibarr versions.
				// FR: Définit dynamiquement les statuts clos pour rester compatible entre versions de Dolibarr.
			$closedStatuses = array();
			if (defined('Task::STATUS_DONE')) $closedStatuses[] = Task::STATUS_DONE;
			if (defined('Task::STATUS_CLOSED')) $closedStatuses[] = Task::STATUS_CLOSED;
			if (defined('Task::STATUS_FINISHED')) $closedStatuses[] = Task::STATUS_FINISHED;
			if (defined('Task::STATUS_CANCELLED')) $closedStatuses[] = Task::STATUS_CANCELLED;
			if (defined('Task::STATUS_CANCELED')) $closedStatuses[] = Task::STATUS_CANCELED;

			$weekStartTs = ($weekStartDate ? strtotime($weekStartDate.' 00:00:00') : null);
			$weekEndTs = ($weekEndDate ? strtotime($weekEndDate.' 23:59:59') : null);

			$filteredTasks = array();
			foreach ($tasks as $t) {
						// EN: Skip tasks already completed or closed to declutter the weekly view.
						// FR: Ignore les tâches déjà terminées ou clôturées pour épurer la vue hebdomadaire.
				$progress = isset($t['task_progress']) ? $t['task_progress'] : null;
				if ($progress !== null && (float)$progress >= 100) {
					continue;
				}

				$status = isset($t['task_status']) ? $t['task_status'] : null;
				if ($status !== null) {
					if (!empty($closedStatuses) && in_array((int)$status, $closedStatuses, true)) {
						continue;
					}
					if (empty($closedStatuses) && (int)$status >= 3) {
						continue;
					}
				}

						// EN: Extract scheduling information to hide tasks outside the sheet week.
						// FR: Analyse les dates de planification pour masquer les tâches hors de la semaine de la feuille.
				$startRaw = isset($t['task_date_start']) ? $t['task_date_start'] : null;
				$endRaw = isset($t['task_date_end']) ? $t['task_date_end'] : null;
				$startTs = null;
				$endTs = null;
				if (!empty($startRaw)) {
					if (is_numeric($startRaw)) {
						$startTs = (int)$startRaw;
					} else {
						$startTs = strtotime($startRaw);
						if ($startTs === false) $startTs = null;
					}
				}
				if (!empty($endRaw)) {
					if (is_numeric($endRaw)) {
						$endTs = (int)$endRaw;
					} else {
						$endTs = strtotime($endRaw);
						if ($endTs === false) $endTs = null;
					}
				}

						// EN: Ignore tasks that start after the sheet week or end before it.
						// FR: Ignore les tâches qui commencent après la semaine ou se terminent avant celle-ci.
				if ($weekStartTs !== null && $weekEndTs !== null && $startTs !== null && $startTs > $weekEndTs) {
					continue;
				}
				if ($weekStartTs !== null && $endTs !== null && $endTs < $weekStartTs) {
					continue;
				}

				$filteredTasks[] = $t;
			}
			$tasks = array_values($filteredTasks);
		}

		// 4) AFFICHAGE
		if (empty($tasks)) {
			echo '<div class="opacitymedium">'.$langs->trans("NoTasksAssigned").'</div>';
		} else {
		// Heures contractuelles
			$contractedHours = $contractedHoursDisp;

		// Inputs zone/panier bloqués si statut != brouillon
			$disabledAttr = ($object->status != tw_status('draft')) ? ' disabled' : '';

			echo '<div class="fichecenter div-table-responsive grille-saisie-temps-wrapper">';
		// EN: Scope the vertical and horizontal centering helper to the specific cells that need alignment (days/zones/baskets/hours/totals).
			echo '<style>';
			echo ':root { --tw-grid-top-gap: 0px; --tw-project-task-bg: #f5f5f5; --tw-project-row-bg: var(--tw-project-task-bg); --tw-grid-header-bg: #e1e1e1; --tw-kb: 0px; }';
			echo '.grille-saisie-temps-wrapper { max-height: 70vh; overflow-y: auto; overflow-x: auto; position: relative; }';
			echo '.grille-saisie-temps-wrapper.sticky-active { max-height: calc(100vh - var(--tw-grid-top-gap, 0px)); position: sticky; top: var(--tw-grid-top-gap, 0px); z-index: 4; }';
			echo '.grille-saisie-temps .cellule-jour,';
			echo '.grille-saisie-temps .cellule-zone-panier,';
			echo '.grille-saisie-temps .cellule-temps,';
			echo '.grille-saisie-temps .cellule-total { vertical-align: middle; text-align: center; }';
			echo '.grille-saisie-temps .col-project-task { position: sticky; left: 0; z-index: 20; }';
			echo '.grille-saisie-temps .col-task { position: sticky; left: 0; z-index: 19; }';
			echo '.grille-saisie-temps .col-project-task,';
			echo '.grille-saisie-temps .col-summary-sticky,';
			echo '.grille-saisie-temps .col-project-task-filler,';
			echo '.grille-saisie-temps .trforbreak { background-color: var(--tw-project-row-bg, var(--tw-project-task-bg)); }';
			echo '.grille-saisie-temps tr.trforbreak td, .grille-saisie-temps tr.trforbreak th { background-color: var(--tw-project-row-bg, var(--tw-project-task-bg)) !important; }';
			echo '.grille-saisie-temps .col-summary-sticky { position: sticky; left: 0; z-index: 8; }';
			echo '.grille-saisie-temps .liste_titre .col-project-task { z-index: 21; }';
			echo '.grille-saisie-temps .liste_titre .col-day { z-index: 19; }';
			echo '.grille-saisie-temps .liste_titre .col-summary-sticky { z-index: 9; }';
			echo '.grille-saisie-temps .trforbreak .col-summary-sticky { z-index: 8; }';
			echo '.grille-saisie-temps .trforbreak .col-project-task-filler {}';
			echo '.grille-saisie-temps .col-total { position: sticky; right: 0; z-index: 6; }';
			echo '.grille-saisie-temps .liste_titre .col-total { z-index: 10; }';
			echo '.grille-saisie-temps .sticky-header th { position: sticky; top: 0; z-index: 12; background-color: var(--tw-grid-header-bg, #e1e1e1); }';
			echo '.tw-day-nav { display: none; }';
			echo '.tw-task-link-labelonly { display: none !important; }';
			
			echo '@media (max-width: 768px) {';
			echo 'body.tw-mobile .tw-card-header-content { display: none; }';
			echo 'body.tw-mobile.tw-mobile-header-open .tw-card-header-content { display: block; }';
			echo 'body.tw-mobile .tw-mobile-headerbar { display: flex; gap: 8px; align-items: center; margin: 6px 0 10px; }';
			echo 'body.tw-mobile .tw-mobile-header-toggle { padding: 6px 10px; }';
			echo 'body.tw-mobile input, body.tw-mobile select, body.tw-mobile textarea { font-size: 16px !important; line-height: 1.2; }';
			echo 'body.tw-mobile { -webkit-text-size-adjust: 100%; }';
			echo '.tw-task-link-full { display: none !important; }';
			echo '.tw-task-link-labelonly { display: inline !important; }';
			echo 'body.tw-mobile .tw-grid-tabbar { padding: 0; margin: 0; background: transparent; border: 0; }';
			echo 'body.tw-mobile .tw-grid-fiche { margin: 0; padding: 0; }';
			echo 'body.tw-mobile:not(.tw-mobile-grid-open) .tw-grid-fiche { border: 0; background: transparent; box-shadow: none; }';
			echo 'body.tw-mobile:not(.tw-mobile-grid-open) .tw-grid-tabbar { display: block; }';
			echo 'body.tw-mobile:not(.tw-mobile-grid-open) .tw-mobile-actions .tw-fab-save, body.tw-mobile:not(.tw-mobile-grid-open) .tw-mobile-actions .tw-fab-submit { display: none !important; }';

			echo 'body.tw-mobile .tw-day-nav { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; border: 1px solid rgba(0,0,0,.2); border-radius: 6px; background: rgba(255,255,255,.6); }';
			echo 'body.tw-mobile .tw-day-header { display: flex; gap: 6px; align-items: center; justify-content: space-between; }';
			echo 'body.tw-mobile .tw-day-header-text { flex: 1; }';
			echo 'body.tw-mobile .tw-daycol { display: none; }';
			echo 'body.tw-mobile .tw-daycol.tw-day-active { display: table-cell; }';
			echo 'body.tw-mobile .tw-col-total { display: none; }';
			echo 'body.tw-mobile .col-project-task-filler { display: table-cell; }';
			echo 'body.tw-mobile .row-total-hours, body.tw-mobile .row-total-days, body.tw-mobile .row-total-meals, body.tw-mobile .row-total-overtime { display: none; }';
			echo 'body.tw-mobile .tw-native-actions { display: none !important; }';
			
			echo 'body.tw-mobile #builddoc { display: none !important; }';
			echo 'body.tw-mobile .tw-documents-block { display: none !important; }';
			echo 'body.tw-mobile .grille-saisie-temps-wrapper { overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }';
			echo 'body.tw-mobile:not(.tw-mobile-grid-open) .grille-saisie-temps-wrapper { display: none; }';
			echo 'body.tw-mobile form.tw-grid-form h3 { display: none; }';
			echo 'body.tw-mobile form.tw-grid-form .center.margintoponly input[type=submit].button { display: none !important; }';
			echo 'body.tw-mobile.tw-mobile-grid-open { overflow: hidden; }';
			echo 'body.tw-mobile.tw-mobile-grid-open .grille-saisie-temps-wrapper { position: fixed; top: var(--tw-grid-top-gap, 0px); left: 0; right: 0; bottom: calc(var(--tw-kb, 0px) + 76px + env(safe-area-inset-bottom)); max-height: none; }';
			echo 'body.tw-mobile.tw-mobile-grid-open .grille-saisie-temps-wrapper .grille-saisie-temps { width: 100%; }';

			echo 'body.tw-mobile .grille-saisie-temps { table-layout: fixed; width: 100%; }';
			echo 'body.tw-mobile .grille-saisie-temps th, body.tw-mobile .grille-saisie-temps td { box-sizing: border-box; }';
			echo 'body.tw-mobile .col-project-task, body.tw-mobile .col-task { width: 62%; min-width: 62%; max-width: 62%; }';
			echo 'body.tw-mobile .tw-daycol { width: 38%; min-width: 38%; max-width: 38%; }';
			echo 'body.tw-mobile .hourinput.tw-time-hidden { display: none !important; }';
			echo 'body.tw-mobile .tw-timepicker { display: inline-flex; align-items: center; justify-content: center; gap: 4px; width: 100%; }';
			echo 'body.tw-mobile .tw-timepicker .tw-time-sep { flex: 0 0 auto; padding: 0 2px; }';
			echo 'body.tw-mobile .tw-timepicker select.tw-time-h { width: 45%; min-width: 0; }';
			echo 'body.tw-mobile .tw-timepicker select.tw-time-m { width: 55%; min-width: 0; }';
			echo 'body.tw-mobile .tw-timepicker select { max-width: 100%; }';

			// EN: Keep meal checkbox and label on the same line on mobile (avoid wrapping).
			// FR: Garde la case repas et son libellé sur une seule ligne en mobile (évite le retour à la ligne).
			echo 'body.tw-mobile .tw-meal-inline { display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; }';
			echo 'body.tw-mobile .tw-meal-inline input { margin: 0; flex: 0 0 auto; }';
			echo 'body.tw-mobile .tw-meal-inline .tw-meal-label { flex: 0 0 auto; }';

			echo 'body.tw-mobile .tw-daycol select, body.tw-mobile .tw-daycol input, body.tw-mobile .tw-daycol textarea { width: 100%; max-width: 100%; }';
			echo 'body.tw-mobile .col-task a { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }';
			echo 'body.tw-mobile .tw-task-ref, body.tw-mobile .tw-task-sep { display: none; }';
			echo 'body.tw-mobile .tw-mobile-actions { display: flex; flex-direction: column; gap: 8px; position: fixed; right: 12px; bottom: calc(var(--tw-kb, 0px) + 12px + env(safe-area-inset-bottom)); z-index: 9999; }';
			echo 'body.tw-mobile .tw-fab { width: 42px; height: 42px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,.25); background: rgba(255,255,255,.9); box-shadow: 0 2px 8px rgba(0,0,0,.15); }';
			echo 'body.tw-mobile .tw-fab-save { background: #fff; }';
			echo 'body.tw-mobile .tw-fab-save i { color: #000; }';
			echo 'body.tw-mobile .tw-fab-submit { background: #fff; }';
			echo 'body.tw-mobile .tw-fab-submit i { color: #000; }';
			echo 'body.tw-mobile .tw-fab i { font-size: 18px; }';
			echo 'body.tw-mobile .grille-saisie-temps thead th { position: sticky; top: 0; z-index: 30; background-color: var(--tw-grid-header-bg, #e1e1e1); }';

			echo '}';

			echo '</style>';
		// EN: Keep the grid flush with the top menu while the bottom of the table is still outside the viewport.
			echo '<script>';
			echo '(function($){';
			echo '$(function(){';
			echo "\tvar \$wrapper = $('.grille-saisie-temps-wrapper');";
			echo "\tvar projectTaskBgCache = null;";
			echo "\tfunction applyProjectTaskBackground() {";
			echo "\t\tvar bgColor = '';";
			echo "\t\tvar \$localTitle = $('.grille-saisie-temps .liste_titre').first();";
			echo "\t\tif (\$localTitle.length) {";
			echo "\t\t\tbgColor = window.getComputedStyle(\$localTitle[0]).backgroundColor;";
			echo "\t\t}";
			echo "\t\tif (!bgColor || bgColor === 'rgba(0, 0, 0, 0)' || bgColor === 'transparent') {";
			echo "\t\t\tvar \$globalTitle = $('.liste_titre').first();";
			echo "\t\t\tif (\$globalTitle.length) {";
			echo "\t\t\t\tbgColor = window.getComputedStyle(\$globalTitle[0]).backgroundColor;";
			echo "\t\t\t}";
			echo "\t\t}";
			echo "\t\tif (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent' && bgColor !== projectTaskBgCache) {";
			echo "\t\t\tprojectTaskBgCache = bgColor;";
			echo "\t\t\tdocument.documentElement.style.setProperty('--tw-project-task-bg', bgColor);";
			echo "\t\t}";
			echo "\t}";
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
			echo "\t\tif (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) { return; }";
			echo "\t\tif (!\$wrapper.length) { return; }";
			echo "\t\tvar rect = \$wrapper[0].getBoundingClientRect();";
			echo "\t\tvar bottomVisible = rect.bottom <= window.innerHeight;";
			echo "\t\ttoggleStickyState(!bottomVisible);";
			echo "\t}";
			echo "\tvar htmlTarget = document.documentElement;";
			echo "\tvar bodyTarget = document.body;";
			echo "\tupdateGridTopGap();";
			echo "\tapplyProjectTaskBackground();";
			echo "\tupdateStickyPosition();";
			echo "\t$(window).on('resize scroll', function(){";
			echo "\t\tupdateGridTopGap();";
			echo "\t\tapplyProjectTaskBackground();";
			echo "\t\tupdateStickyPosition();";
			echo "\t});";
			echo "\tvar observerConfig = { attributes: true, attributeFilter: ['class'] };";
			echo "\tvar themeObserver = new MutationObserver(function(){";
			echo "\t\tapplyProjectTaskBackground();";
			echo "\t});";
			echo "\tif (htmlTarget) {";
			echo "\t\tthemeObserver.observe(htmlTarget, observerConfig);";
			echo "\t}";
			echo "\tif (bodyTarget) {";
			echo "\t\tthemeObserver.observe(bodyTarget, observerConfig);";
			echo "\t}";
			echo '});';
			echo '})(jQuery);';
			echo '</script>';
			echo '<table class="noborder centpercent grille-saisie-temps">';

				// EN: Apply the vertical-centering helper on each day header to keep labels visually aligned.
				// Header jours
			echo '<tr class="liste_titre sticky-header col-project-task">';
			echo '<th class="col-project-task">'.$langs->trans("ProjectTaskColumn").'</th>';
			foreach ($days as $d) {
				$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
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
				$dayDateISO = !empty($weekdates[$d]) ? $weekdates[$d] : '';
				echo '<th class="cellule-jour col-day tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($dayDateISO).'">';
				echo '<div class="tw-day-header">';
				echo '<button type="button" class="tw-day-nav tw-day-prev" aria-label="'.dol_escape_htmltag($langs->trans("Previous")).'">&#x2039;</button>';
				echo '<div class="tw-day-header-text">'.$dayLabel;
				if ($labelDate !== '') {
					echo '<br><span class="opacitymedium">'.$labelDate.'</span>';
				}
				echo '</div>';
				echo '<button type="button" class="tw-day-nav tw-day-next" aria-label="'.dol_escape_htmltag($langs->trans("Next")).'">&#x203A;</button>';
				echo '</div>';
				echo '</th>';
			}
			echo '<th class="center cellule-total tw-col-total">'.$langs->trans("Total").'</th>';
			echo '</tr>';

			// EN: Add the vertical-centering helper on zone and meal cells so both controls stay centered whatever their height.
			// FR: Ajoute l'aide de centrage vertical sur les cellules zone et repas afin que les deux contrôles restent centrés quelle que soit leur hauteur.
			// Ligne zone + panier (préfills depuis lignes)
			if (!$isDailyRateEmployee) {
				echo '<tr class="liste_titre">';
				echo '<td class=""></td>';
				foreach ($days as $d) {
					$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
					// EN: Attach the vertical-centering helper to keep both zone selector and meal checkbox aligned.
					// FR: Attache l'aide de centrage vertical pour garder alignés le sélecteur de zone et la case repas.
					echo '<td class="center cellule-zone-panier tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($weekdates[$d] ?? '').'">';
					// EN: Prefix zone selector with its label to improve understanding.
					// FR: Préfixe le sélecteur de zone avec son libellé pour améliorer la compréhension.
					echo '<span class="zone-select">'.$langs->trans("Zone").' ';
					echo '<select name="zone_'.$d.'" class="flat"'.$disabledAttr.'>';
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
				echo '<label class="tw-meal-inline"><span class="tw-meal-label">'.$langs->trans("Meal").'</span><input type="checkbox" name="meal_'.$d.'" value="1" class="mealbox"'.$checked.$disabledAttr.'></label>';
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
					echo '<td class="paddingleft col-task">';
					$tsk = new Task($db);
					$tsk->fetch((int)$task['task_id']);
					if (empty($tsk->label)) { $tsk->id = (int)$task['task_id']; $tsk->ref = $task['task_ref'] ?? ''; $tsk->label = $task['task_label']; }
					$nomurl = tw_get_task_nomurl($tsk, 1);
						$href = '';
						if (preg_match('/href="([^"]+)"/', $nomurl, $m)) $href = $m[1];
						echo '<span class="tw-task-link-full">'.$nomurl.'</span>';
						if (!empty($href)) {
							echo '<a class="tw-task-link-labelonly" href="'.$href.'">'.dol_escape_htmltag($tsk->label).'</a>';
						} else {
							echo '<span class="tw-task-link-labelonly">'.dol_escape_htmltag($tsk->label).'</span>';
						}
					echo '</td>';

					$rowTotal = 0.0;
					foreach ($days as $d) {
						$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
					// EN: Attach the vertical-centering helper to each time entry cell for consistent layouts.
					// FR: Attache l'aide de centrage vertical à chaque cellule de temps pour des mises en page cohérentes.
						$iname = 'hours_'.$task['task_id'].'_'.$d;
						$rateName = 'daily_'.$task['task_id'].'_'.$d;
						$val = '';
						$rateVal = 0;
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
							$selectHtml = '<select name="'.$rateName.'" class="flat daily-rate-select"'.$disabledSelect.'>';
							$selectHtml .= '<option value=""></option>';
							foreach ($dailyRateOptions as $code => $label) {
								$selected = ($rateVal === (int) $code) ? ' selected' : '';
								$selectHtml .= '<option value="'.$code.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
							}
							$selectHtml .= '</select>';
							echo '<td class="center cellule-temps tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($keydate).'">'.$selectHtml.'</td>';
						} else {
							$readonly = ($object->status != tw_status('draft')) ? ' readonly' : '';
							echo '<td class="center cellule-temps tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($keydate).'"><input type="text" class="flat hourinput" size="4" name="'.$iname.'" value="'.dol_escape_htmltag($val).'" placeholder="00:00"'.$readonly.'></td>';
						}
					}
					$grandInit += $rowTotal;
// EN: Center task totals so they stay aligned with other centered figures.
// FR: Centre les totaux de tâche pour les garder alignés avec les autres valeurs centrées.
					if ($isDailyRateEmployee) {
						echo '<td class="center task-total cellule-total tw-col-total">'.tw_format_days(($rowTotal > 0 ? ($rowTotal / 8.0) : 0.0), $langs).'</td>';
					} else {
						echo '<td class="center task-total cellule-total tw-col-total">'.formatHours($rowTotal).'</td>';
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
					$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
					echo '<td class="center day-total cellule-total tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($weekdates[$d] ?? '').'">'.tw_format_days(0, $langs).'</td>';
				}
				echo '<td class="center grand-total cellule-total">'.tw_format_days($grandDays, $langs).'</td>';
				echo '</tr>';
			} else {
				echo '<tr class="liste_total row-total-hours">';
// EN: Center overall totals and daily sums for consistent middle alignment.
// FR: Centre les totaux généraux et journaliers pour un alignement médian homogène.
				echo '<td class="left col-project-task col-summary-sticky">'.$langs->trans("Total").'</td>';
				foreach ($days as $d) {
					$activeClass = ($d === $days[0]) ? ' tw-day-active' : '';
					echo '<td class="center day-total cellule-total tw-daycol tw-day-'.$d.$activeClass.'" data-day="'.$d.'" data-date="'.dol_escape_htmltag($weekdates[$d] ?? '').'">00:00</td>';
				}
				echo '<td class="center grand-total cellule-total">'.formatHours($grand).'</td>';
				echo '</tr>';

				echo '<tr class="liste_total row-total-meals">';
// EN: Center meal counters to match the rest of the grid alignment.
// FR: Centre les compteurs de repas pour correspondre au reste de l'alignement de la grille.
				echo '<td class="left col-project-task col-summary-sticky">'.$langs->trans("Meals").'</td>';
				$initMeals = array_sum($dayMeal);
				echo '<td colspan="'.count($days).'" class="cellule-total"></td>';
				echo '<td class="left meal-total cellule-total">'.$initMeals.'</td>';
				echo '</tr>';

				echo '<tr class="liste_total row-total-overtime">';
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

		echo '</div>'; // tw-grid-tabbar

		echo '</div>'; // tw-grid-fiche

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
					updateTotals();	// au chargement
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

			// Mobile: simplified view (1 day column + navigation) + keyboard-aware floating actions
			$jsMobile = <<<'JSM'
<script>
(function($){
	$(function(){
		var mq = window.matchMedia('(max-width: 768px)');
		var days = %s;
		var weekdates = %s;

		function setMobile(flag){
			document.body.classList.toggle('tw-mobile', !!flag);
			var bar = document.querySelector('.tw-mobile-headerbar');
			var fabs = document.querySelector('.tw-mobile-actions');
			if (bar) bar.style.display = flag ? 'flex' : 'none';
			if (fabs) fabs.style.display = flag ? 'flex' : 'none';
		}

		function todayKey(){
			var iso = '';
			try { iso = (new Date()).toISOString().slice(0,10); } catch (e) {}
			for (var k in weekdates) {
				if (weekdates[k] === iso) return k;
			}
			return days[0] || null;
		}

		var current = null;

		function showDay(k){
			if (!k) return;
			current = k;
			$('.tw-daycol').removeClass('tw-day-active');
			$('.tw-day-' + k).addClass('tw-day-active');
		}

		function step(dir){
			if (!current) return;
			var idx = days.indexOf(current);
			if (idx < 0) idx = 0;
			idx = idx + dir;
			if (idx < 0) idx = 0;
			if (idx >= days.length) idx = days.length - 1;
			showDay(days[idx]);
		}

		function bindNav(){
			var $tbl = $('.grille-saisie-temps');
			$tbl.off('click.twDayNav')
				.on('click.twDayNav', '.tw-day-prev', function(e){ e.preventDefault(); step(-1); })
				.on('click.twDayNav', '.tw-day-next', function(e){ e.preventDefault(); step(1); });
		}

		function bindHeaderToggle(){
			function toggle(){
				document.body.classList.toggle('tw-mobile-header-open');
				var open = document.body.classList.contains('tw-mobile-header-open');
				var btn = document.getElementById('twMobileHeaderToggle');
				if (btn) {
					btn.setAttribute('aria-expanded', open ? 'true' : 'false');
					btn.textContent = open ? %s : %s;
				}
			}
			var btn = document.getElementById('twMobileHeaderToggle');
			if (btn) btn.addEventListener('click', function(e){ e.preventDefault(); toggle(); });
			var fab = document.getElementById('twMobileHeaderFab');
			if (fab) fab.addEventListener('click', function(e){
				e.preventDefault();
				var openGrid = document.body.classList.contains('tw-mobile-grid-open');
				document.body.classList.toggle('tw-mobile-grid-open', !openGrid);
			});
		}

		function bindSubmitFab(){
			var btn = document.getElementById('twMobileSubmitFab');
			if (!btn) return;
			var a = document.querySelector('a[href*="action=submit"]');
			if (!a) { btn.style.display = 'none'; return; }
			var href = a.getAttribute('href');
			btn.style.display = '';
			btn.addEventListener('click', function(e){ e.preventDefault(); if (href) window.location.href = href; });
		}

		function bindActionFabs(){
			// Hide all by default, then enable only what is allowed for current status
			['twMobileSetdraftFab','twMobileApproveFab','twMobileRefuseFab','twMobileSealFab','twMobileDeleteFab'].forEach(function(id){
				var b = document.getElementById(id);
				if (b) b.style.display = 'none';
			});

			// Only show actions that match the TimesheetWeek workflow:
			// - Soumise: retour brouillon / approuver / refuser
			// - Brouillon: supprimer
			// - Approuvée: sceller
			if (twCurrentStatus === TW_STATUS_SUBMITTED) {
				bindActionFab('twMobileSetdraftFab', '.tw-native-actions a[href*="action=setdraft"]');
				bindActionFab('twMobileApproveFab', '.tw-native-actions a[href*="action=ask_validate"]');
				bindActionFab('twMobileRefuseFab', '.tw-native-actions a[href*="action=ask_refuse"]');
			} else if (twCurrentStatus === TW_STATUS_DRAFT) {
				bindActionFab('twMobileDeleteFab', '.tw-native-actions a[href*="action=delete"]');
			} else if (twCurrentStatus === TW_STATUS_APPROVED) {
				bindActionFab('twMobileSealFab', '.tw-native-actions a[href*="action=seal"]');
			}
		}


		function keyboardAware(){
			if (!window.visualViewport) return;
			var vv = window.visualViewport;
			function upd(){
				var kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
				document.documentElement.style.setProperty('--tw-kb', kb + 'px');
			}
			vv.addEventListener('resize', upd);
			vv.addEventListener('scroll', upd);
			document.addEventListener('focusin', upd);
			document.addEventListener('focusout', function(){ document.documentElement.style.setProperty('--tw-kb', '0px'); });
			upd();
		}


		function pad2(n){ n = parseInt(n, 10) || 0; return (n < 10 ? '0' : '') + n; }

		function parseTimeToHM(v){
			if (!v) return { h: 0, m: 0, empty: true };
			var s = String(v).trim();
			if (!s) return { h: 0, m: 0, empty: true };

			var m = s.match(/^\s*(\d{1,2})\s*:\s*(\d{1,2})\s*$/);
			if (m) {
				var h = Math.min(23, Math.max(0, parseInt(m[1], 10) || 0));
				var mi = Math.min(59, Math.max(0, parseInt(m[2], 10) || 0));
				return { h: h, m: mi, empty: false };
			}

			// Accept decimal hours like 1.5 or 1,5 -> 01:30 (best-effort)
			m = s.match(/^\s*(\d{1,2})[\.,](\d{1,2})\s*$/);
			if (m) {
				var hh = Math.min(23, Math.max(0, parseInt(m[1], 10) || 0));
				var frac = parseInt(m[2], 10) || 0;
				// Treat 1.5 as 1h30, 1.25 as 1h15 (approx)
				var mm = Math.round((frac / Math.pow(10, String(frac).length)) * 60);
				mm = Math.min(59, Math.max(0, mm));
				return { h: hh, m: mm, empty: false };
			}

			m = s.match(/^\s*(\d{1,2})\s*$/);
			if (m) {
				var h2 = Math.min(23, Math.max(0, parseInt(m[1], 10) || 0));
				return { h: h2, m: 0, empty: false };
			}

			return { h: 0, m: 0, empty: true };
		}

		function buildTimePickers(){
			if (!document.body.classList.contains('tw-mobile')) return;

			var inputs = document.querySelectorAll('input.hourinput');
			inputs.forEach(function(inp){
				if (inp.dataset.twPickerInit) return;
				inp.dataset.twPickerInit = '1';

				var parsed = parseTimeToHM(inp.value);
				var isDisabled = !!inp.disabled || !!inp.readOnly || inp.getAttribute('readonly') !== null;

				var wrap = document.createElement('div');
				wrap.className = 'tw-timepicker';

				var selH = document.createElement('select');
				selH.className = 'flat tw-time-h';
				for (var h = 0; h <= 23; h++) {
					var optH = document.createElement('option');
					optH.value = String(h);
					optH.textContent = pad2(h);
					selH.appendChild(optH);
				}

				var sep = document.createElement('span');
				sep.className = 'tw-time-sep';
				sep.textContent = ':';

				var selM = document.createElement('select');
				selM.className = 'flat tw-time-m';
				for (var mi = 0; mi <= 59; mi++) {
					var optM = document.createElement('option');
					optM.value = String(mi);
					optM.textContent = pad2(mi);
					selM.appendChild(optM);
				}

				selH.value = String(parsed.h);
				selM.value = String(parsed.m);

				if (isDisabled) {
					selH.disabled = true;
					selM.disabled = true;
				}

				function sync(){
					var hh = parseInt(selH.value, 10) || 0;
					var mm = parseInt(selM.value, 10) || 0;
					inp.value = pad2(hh) + ':' + pad2(mm);
					if (window.jQuery) {
						window.jQuery(inp).trigger('input').trigger('keyup').trigger('change');
					} else {
						try { inp.dispatchEvent(new Event('input', { bubbles: true })); } catch(e) {}
						try { inp.dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}
					}
				}

				selH.addEventListener('change', sync);
				selM.addEventListener('change', sync);

				wrap.appendChild(selH);
				wrap.appendChild(sep);
				wrap.appendChild(selM);

				inp.classList.add('tw-time-hidden');
				inp.insertAdjacentElement('afterend', wrap);
			});
		}

		function destroyTimePickers(){
			document.querySelectorAll('.tw-timepicker').forEach(function(w){ w.remove(); });
			document.querySelectorAll('input.hourinput.tw-time-hidden').forEach(function(inp){ inp.classList.remove('tw-time-hidden'); });
			document.querySelectorAll('input.hourinput[data-tw-picker-init]').forEach(function(inp){ delete inp.dataset.twPickerInit; });
		}

		function init(flag){
			setMobile(flag);
			if (flag) document.body.classList.remove('tw-mobile-grid-open');
			if (!flag) { destroyTimePickers(); return; }
			buildTimePickers();
			document.body.classList.remove('tw-mobile-header-open');
			bindNav();
			bindHeaderToggle();
			bindSubmitFab();
			keyboardAware();
			showDay(todayKey());
		}

		init(mq.matches);
		if (mq.addEventListener) mq.addEventListener('change', function(e){ init(e.matches); });
		else if (mq.addListener) mq.addListener(function(e){ init(e.matches); });
	});
})(jQuery);
</script>
JSM;
			$jsMobile = sprintf(
				$jsMobile,
				json_encode(array_values($days)),
				json_encode($weekdates),
				json_encode($langs->trans("Hide")),
				json_encode($langs->trans("Details"))
			);
			echo $jsMobile;


		}

		// ---- Boutons d’action (barre) ----
		echo '<div class="tabsAction tw-native-actions">';

		$token = newToken();

		if ($object->status == tw_status('sealed')) {
				// EN: In sealed state only show the unseal control for authorized users.
				// FR : En statut scellé, n'afficher que l'action de descellage pour les utilisateurs autorisés.
			if ($permUnseal) {
				echo dolGetButtonAction('', $langs->trans('UnsealTimesheet'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unseal&token='.$token);
			}
		} else {
			if ($canSendMail) {
				echo dolGetButtonAction('', $langs->trans('Sendbymail'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&token='.$token);
			}

				// Soumettre : uniquement brouillon + au moins 1 ligne existante + droits
			if ($object->status == tw_status('draft')) {
						// Compter les lignes
				$nbLines = 0;
						// EN: Count lines only within authorized entities before enabling submission.
						// FR: Compte les lignes uniquement dans les entités autorisées avant d'autoriser la soumission.
				$rescnt = $db->query("SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."timesheet_week_line WHERE fk_timesheet_week=".(int)$object->id." AND entity IN (".getEntity('timesheetweek').")");
				if ($rescnt) { $o=$db->fetch_object($rescnt); $nbLines=(int)$o->nb; }
				if ($nbLines > 0 && tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user)) {
					echo dolGetButtonAction('', $langs->trans("Submit"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=submit&token='.$token);
				}
			}

				// Retour brouillon : si statut != brouillon (soumis / approuvé / refusé) pour salarié/or valideur
			if ($object->status != tw_status('draft')) {
				$canEmployee  = tw_can_act_on_user($object->fk_user, $permWrite, $permWriteChild, $permWriteAll, $user);
				$canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
				if ($canEmployee || $canValidator) {
					echo dolGetButtonAction('', $langs->trans("SetToDraft"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setdraft&token='.$token);
				}
			}

				// Approuver / Refuser quand soumis (validateur/manager/all/own)
			if ($object->status == tw_status('submitted')) {
				$canValidator = tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
				if ($canValidator) {
					echo dolGetButtonAction('', ($langs->trans("Approve")!='Approve'?$langs->trans("Approve"):'Approuver'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_validate&token='.$token);
					echo dolGetButtonAction('', $langs->trans("Refuse"), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=ask_refuse&token='.$token);
				}
			}

				// EN: Allow sealing once the sheet is approved and the user is authorized.
				// FR : Autorise le scellement dès que la feuille est approuvée et que l'utilisateur est habilité.
			if ($object->status == tw_status('approved') && $permSeal) {
				echo dolGetButtonAction('', $langs->trans('SealTimesheet'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=seal&token='.$token);
			}

				// Supprimer : brouillon OU soumis/approuvé/refusé si salarié (delete) ou validateur (validate*) ou all
			$canDelete = tw_can_act_on_user($object->fk_user, $permDelete, $permDeleteChild, $permDeleteAll, $user)
			|| tw_can_validate_timesheet($object, $user, $permValidate, $permValidateOwn, $permValidateChild, $permValidateAll, $permWrite, $permWriteChild, $permWriteAll);
			if ($canDelete) {
				echo dolGetButtonAction('', $langs->trans("Delete"), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.$token);
				
			}
		}

		echo '</div>';

		if ($action !== 'presend') {
				// EN: Mirror Dolibarr's document block so PDF tools appear consistently on the card.
				// FR: Reproduit le bloc documentaire de Dolibarr pour afficher les outils PDF de manière cohérente sur la fiche.
			print '<div class="fichecenter"><div class="fichehalfleft">';
			print '<a name="builddoc"></a>';

				// EN: Enable the document generation area (can be toggled by hooks if needed).
				// FR: Active la zone de génération documentaire (peut être désactivée via des hooks si nécessaire).
			$includedocgeneration = 1;

			if ($includedocgeneration) {
					// EN: Build the target directories depending on the entity, falling back to Dolibarr defaults.
					// FR: Construit les répertoires cibles selon l'entité en retombant sur les valeurs par défaut de Dolibarr.
				$docEntityId = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
				$object->element = 'timesheetweek';
				$docRef = dol_sanitizeFileName($object->ref);
				$entityOutput = !empty($conf->timesheetweek->multidir_output[$docEntityId]) ? $conf->timesheetweek->multidir_output[$docEntityId] : '';
				if (empty($entityOutput) && !empty($conf->timesheetweek->dir_output)) {
					$entityOutput = $conf->timesheetweek->dir_output;
				}
				if (empty($entityOutput)) {
					$entityOutput = DOL_DATA_ROOT.'/timesheetweek';
				}
				$relativePath = $object->element.'/'.$docRef;
				$filedir = rtrim($entityOutput, '/') . '/' . $relativePath;
				$urlsource = $_SERVER['PHP_SELF'].'?id='.$object->id;
				$genallowed = $permReadAny ? 1 : 0;
				if ($permReadAny) {
						// EN: Narrow the generation list to the PDF models enabled in the configuration.
						// FR: Restreint la liste de génération aux modèles PDF activés dans la configuration.
					$enabledDocModels = tw_get_enabled_pdf_models($db);
					if (!empty($enabledDocModels)) {
						$genallowed = $enabledDocModels;
					}
				}
				$delallowed = $permissiontoadd ? 1 : 0;

				$documentHtml = $formfile->showdocuments(
					'timesheetweek:TimesheetWeek',
					$relativePath,
					$filedir,
					$urlsource,
					$genallowed,
					$delallowed,
					$object->model_pdf,
					1,
					0,
					0,
					28,
					0,
					'',
					'',
					'',
					$langs->defaultlang,
					'',
					$object,
					0,
					'remove_file',
					''
				);
				if (!empty($documentHtml)) {
					// EN: Sanitize each segment of the file path to preserve directories while avoiding traversal attacks.
					// FR: Assainit chaque segment du chemin du fichier pour conserver les dossiers tout en évitant les attaques de traversée.
					$documentHtml = preg_replace_callback('/([?&]file=)([^"&]+)/', function ($matches) {
						$decoded = urldecode($matches[2]);
						while (strpos($decoded, './') === 0) {
							$decoded = substr($decoded, 2);
						}
						$decoded = ltrim($decoded, '/');
						$parts = preg_split('#/+?#', $decoded, -1, PREG_SPLIT_NO_EMPTY);
						$sanitizedParts = array();
						foreach ($parts as $part) {
							if ($part === '.' || $part === '..') {
								continue;
							}
							$cleanPart = dol_sanitizeFileName($part);
							if ($cleanPart === '') {
								continue;
							}
							$sanitizedParts[] = $cleanPart;
						}
						if (empty($sanitizedParts)) {
							$basename = dol_sanitizeFileName(basename($decoded));
							if ($basename !== '') {
								$sanitizedParts[] = $basename;
							}
						}
						$cleanPath = implode('/', $sanitizedParts);
						return $matches[1].rawurlencode($cleanPath);
					}, $documentHtml);
					// EN: Force remove actions to keep only the document basename to match Dolibarr expectations.
					// FR: Force les actions de suppression à conserver uniquement le nom de fichier pour correspondre aux attentes Dolibarr.
					$documentHtml = preg_replace_callback('/(action=remove_file[^"<>]*[?&]file=)([^"&]+)/', function ($matches) {
						$decoded = rawurldecode($matches[2]);
						$normalized = str_replace('\\', '/', $decoded);
						$basename = dol_sanitizeFileName(basename($normalized));
						if ($basename === '') {
							return $matches[0];
						}
						return $matches[1].rawurlencode($basename);
					}, $documentHtml);
					$documentBaseUrl = rtrim(dol_buildpath('/document.php', 2), '/');
					$decodeFlags = ENT_QUOTES;
					if (defined('ENT_HTML5')) {
						$decodeFlags |= ENT_HTML5;
					} else {
						$decodeFlags |= ENT_COMPAT;
					}
					$documentHtml = preg_replace_callback('/href="([^"<>]*document\.php[^"<>]*)"/', function ($matches) use ($documentBaseUrl, $decodeFlags) {
						$originalHref = $matches[1];
						$decodedHref = html_entity_decode($originalHref, $decodeFlags, 'UTF-8');
						if (preg_match('#^[a-z]+://#i', $decodedHref)) {
							return 'href="'.$originalHref.'"';
						}
						$normalizedHref = $decodedHref;
						if (strpos($normalizedHref, '/document.php') === 0) {
							$normalizedHref = substr($normalizedHref, strlen('/document.php'));
						} elseif (strpos($normalizedHref, 'document.php') === 0) {
							$normalizedHref = substr($normalizedHref, strlen('document.php'));
						} else {
							return 'href="'.$originalHref.'"';
						}
						$absoluteHref = $documentBaseUrl.$normalizedHref;
						return 'href="'.dol_escape_htmltag($absoluteHref).'"';
					}, $documentHtml);
				}
				print '<div class="tw-documents-block">';
				print $documentHtml;
				print '</div>';
			}

			print '</div>'; // tw-grid-fichecenter
			print '</div></div>';
		}

		if ($action === 'presend') {
			$formmail = new FormMail($db);
			$formmail->showform = 1;
			$formmail->withfrom = 1;
			$formmail->fromtype = 'user';
			$formmail->fromid = $user->id;
			$formmail->fromname = $user->getFullName($langs);
			$formmail->frommail = !empty($user->email) ? $user->email : getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
			$formmail->withreplyto = 1;
			$formmail->replyto = GETPOST('replyto', 'none');
			$formmail->withto = 1;
			$formmail->withtofree = 1;
			$formmail->withtocc = 1;
			$formmail->withbcc = 1;
			$formmail->withtopic = 1;
			$formmail->withfile = 2;
			$formmail->withmaindocfile = 1;
			$formmail->withdeliveryreceipt = 1;
			$formmail->withtpl = 1;
			$formmail->withsubstit = 1;
			$formmail->withcancel = 1;
			$formmail->modelmail = $modelmail;
			$formmail->trackid = $trackid;
			$formmail->subject = GETPOST('subject', 'restricthtml');
			$formmail->topic = $formmail->subject;
			$formmail->content = GETPOST('message', 'restricthtml');
			$formmail->message = $formmail->content;
			$formmail->sendto = GETPOST('sendto', 'none');
			$formmail->substit = $moresubstit;
			$formmail->param = array_merge($param, array(
				'id' => $object->id,
				'action' => 'send',
			));
			$formmail->langcode = $langs->defaultlang;

			include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
			print '</div>'; // tw-grid-fichecenter
			print '</div></div>'; // tw-grid-tabbar + tw-grid-fiche
		}

	}

// End of page
	llxFooter();
	$db->close();
