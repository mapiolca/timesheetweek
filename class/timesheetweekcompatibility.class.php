<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Compatibility registry for the TimesheetWeek module.
 *
 * @phpstan-type CompatibilityFeature array{
 *     label: string,
 *     description: string,
 *     min_dolibarr?: string,
 *     core_available_from?: string,
 *     module_available_from?: string,
 *     min_php?: string,
 *     compatibility_check: string,
 *     available: bool,
 *     reason: string
 * }
 */
class TimesheetWeekCompatibility
{
	public const MIN_DOLIBARR = '20.0.0';
	public const MIN_PHP = '8.0.0';

	/**
	 * Check Dolibarr version.
	 *
	 * @param string $version Version threshold
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		$current = defined('DOL_VERSION') ? DOL_VERSION : '0.0.0';
		return version_compare($current, $version, '>=');
	}

	/**
	 * Check PHP version.
	 *
	 * @param string $version Version threshold
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return the central feature compatibility registry.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getCompatibilityFeatures()
	{
		if (function_exists('dol_include_once')) {
			dol_include_once('/timesheetweek/class/actions_timesheetweek.class.php');
			dol_include_once('/timesheetweek/class/timesheetweeknotification.class.php');
		}
		$hasElementPropertiesHook = class_exists('ActionsTimesheetweek') && method_exists('ActionsTimesheetweek', 'getElementProperties');
		$hasNativeNotificationRouter = class_exists('TimesheetWeekNotification') && method_exists('TimesheetWeekNotification', 'getNativeNotificationSubstitutions');
		$hasUserBankLatestSheetsHook = self::isUserBankFormObjectOptionsHookAvailable();

		return array(
			'mobile_timesheet_autosave' => array(
				'label' => 'TimesheetWeekCompatibilityMobileAutosave',
				'description' => 'TimesheetWeekCompatibilityMobileAutosaveDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '>=')",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0'),
				'reason' => 'TimesheetWeekCompatibilityRequiresDolibarr20Php80',
			),
			'native_notification_triggers' => array(
				'label' => 'TimesheetWeekCompatibilityNativeNotificationTriggers',
				'description' => 'TimesheetWeekCompatibilityNativeNotificationTriggersDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '>=')",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0'),
				'reason' => 'TimesheetWeekCompatibilityRequiresDolibarr20Php80',
			),
			'native_agenda_autocreate' => array(
				'label' => 'TimesheetWeekCompatibilityAgenda',
				'description' => 'TimesheetWeekCompatibilityAgendaDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && isModEnabled('agenda')",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && (!function_exists('isModEnabled') || isModEnabled('agenda')),
				'reason' => 'TimesheetWeekCompatibilityAgendaDisabled',
			),
			'native_notifications' => array(
				'label' => 'TimesheetWeekCompatibilityNotifications',
				'description' => 'TimesheetWeekCompatibilityNotificationsDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && isModEnabled('notification')",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && (!function_exists('isModEnabled') || isModEnabled('notification')),
				'reason' => 'TimesheetWeekCompatibilityNotificationsDisabled',
			),
			'native_notification_workflow_router' => array(
				'label' => 'TimesheetWeekCompatibilityNotificationWorkflowRouter',
				'description' => 'TimesheetWeekCompatibilityNotificationWorkflowRouterDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && isModEnabled('notification') && class_exists('TimesheetWeekNotification')",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && (!function_exists('isModEnabled') || isModEnabled('notification')) && $hasNativeNotificationRouter,
				'reason' => 'TimesheetWeekCompatibilityNotificationWorkflowRouterUnavailable',
			),
			'email_template_class' => array(
				'label' => 'TimesheetWeekCompatibilityEmailTemplateClass',
				'description' => 'TimesheetWeekCompatibilityEmailTemplateClassDesc',
				'min_dolibarr' => '23.0.0',
				'core_available_from' => '23.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "version_compare(DOL_VERSION, '23.0.0', '>=') || class_exists('CEmailTemplate')",
				'available' => self::isDolibarrVersionAtLeast('23.0.0') || class_exists('CEmailTemplate'),
				'reason' => 'TimesheetWeekCompatibilityEmailTemplateClassUnavailable',
			),
			'multidir_documents' => array(
				'label' => 'TimesheetWeekCompatibilityMultidirDocuments',
				'description' => 'TimesheetWeekCompatibilityMultidirDocumentsDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '18.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "function_exists('getMultidirOutput')",
				'available' => function_exists('getMultidirOutput'),
				'reason' => 'TimesheetWeekCompatibilityMultidirUnavailable',
			),
			'element_properties_hook' => array(
				'label' => 'TimesheetWeekCompatibilityElementProperties',
				'description' => 'TimesheetWeekCompatibilityElementPropertiesDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => '20.0.0',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "class_exists('ActionsTimesheetweek') && method_exists('ActionsTimesheetweek', 'getElementProperties')",
				'available' => $hasElementPropertiesHook,
				'reason' => 'TimesheetWeekCompatibilityElementPropertiesUnavailable',
			),
			'user_bank_latest_timesheets' => array(
				'label' => 'TimesheetWeekCompatibilityUserBankLatestSheets',
				'description' => 'TimesheetWeekCompatibilityUserBankLatestSheetsDesc',
				'min_dolibarr' => '20.0.0',
				'core_available_from' => 'formObjectOptions hook in htdocs/user/bank.php',
				'module_available_from' => '1.8.4',
				'min_php' => '8.0.0',
				'compatibility_check' => "strpos(file_get_contents(DOL_DOCUMENT_ROOT.'/user/bank.php'), \"executeHooks('formObjectOptions'\") !== false",
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0') && $hasUserBankLatestSheetsHook,
				'reason' => 'TimesheetWeekCompatibilityUserBankHookUnavailable',
			),
		);
	}

	/**
	 * Check if the user bank card exposes the native formObjectOptions hook.
	 *
	 * @return bool
	 */
	public static function isUserBankFormObjectOptionsHookAvailable()
	{
		if (!defined('DOL_DOCUMENT_ROOT')) {
			return false;
		}

		$bankPage = DOL_DOCUMENT_ROOT.'/user/bank.php';
		if (!is_readable($bankPage)) {
			return false;
		}

		$content = file_get_contents($bankPage);
		if (!is_string($content)) {
			return false;
		}

		return strpos($content, "executeHooks('formObjectOptions'") !== false
			|| strpos($content, 'executeHooks("formObjectOptions"') !== false;
	}

	/**
	 * Check if a feature is available.
	 *
	 * @param string $featureCode Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($featureCode)
	{
		$features = self::getCompatibilityFeatures();
		return !empty($features[$featureCode]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getCompatibilityFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}

	/**
	 * Return conservative Agenda diagnostics without deleting uncertain records.
	 *
	 * @param DoliDB $db Database handler
	 * @return array<string,array{label:string,description:string,count:int,severity:string}>
	 */
	public static function getAgendaDiagnostics($db)
	{
		$diagnostics = array(
			'legacy_elementtype' => array(
				'label' => 'TimesheetWeekAgendaDiagnosticLegacyElementtype',
				'description' => 'TimesheetWeekAgendaDiagnosticLegacyElementtypeDesc',
				'count' => self::countSql($db, "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."actioncomm AS a INNER JOIN ".MAIN_DB_PREFIX."timesheet_week AS t ON t.rowid = a.fk_element WHERE a.elementtype = 'timesheetweek'"),
				'severity' => 'info',
			),
			'unresolved_links' => array(
				'label' => 'TimesheetWeekAgendaDiagnosticUnresolvedLinks',
				'description' => 'TimesheetWeekAgendaDiagnosticUnresolvedLinksDesc',
				'count' => self::countSql($db, "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."actioncomm AS a LEFT JOIN ".MAIN_DB_PREFIX."timesheet_week AS t ON t.rowid = a.fk_element WHERE a.elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek') AND a.fk_element IS NOT NULL AND t.rowid IS NULL"),
				'severity' => 'warning',
			),
			'potential_duplicates' => array(
				'label' => 'TimesheetWeekAgendaDiagnosticPotentialDuplicates',
				'description' => 'TimesheetWeekAgendaDiagnosticPotentialDuplicatesDesc',
				'count' => self::countSql($db, "SELECT COUNT(*) as nb FROM (SELECT a.fk_element, CASE WHEN COALESCE(a.code, '') LIKE 'AC_TIMESHEETWEEK_%' THEN SUBSTRING(a.code, 4) ELSE COALESCE(a.code, '') END AS action_code, DATE_FORMAT(a.datep, '%Y-%m-%d %H:%i') AS event_minute, TRIM(REPLACE(REPLACE(COALESCE(a.label, ''), t.ref, ''), '  ', ' ')) AS normalized_label, COUNT(*) as duplicate_count FROM ".MAIN_DB_PREFIX."actioncomm AS a INNER JOIN ".MAIN_DB_PREFIX."timesheet_week AS t ON t.rowid = a.fk_element WHERE a.elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek') AND a.fk_element IS NOT NULL GROUP BY a.fk_element, action_code, event_minute, normalized_label HAVING duplicate_count > 1) AS duplicates"),
				'severity' => 'warning',
			),
		);

		return $diagnostics;
	}

	/**
	 * Execute a COUNT SQL query safely for diagnostics.
	 *
	 * @param DoliDB $db Database handler
	 * @param string $sql SQL query returning a nb column
	 * @return int Count or -1 on SQL error
	 */
	protected static function countSql($db, $sql)
	{
		$resql = $db->query($sql);
		if (!$resql) {
			return -1;
		}

		$obj = $db->fetch_object($resql);
		$count = is_object($obj) && isset($obj->nb) ? (int) $obj->nb : 0;
		$db->free($resql);

		return $count;
	}
}
