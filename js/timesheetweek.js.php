<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

/**
 * \file    timesheetweek/js/timesheetweek.js.php
 * \ingroup timesheetweek
 * \brief   JavaScript file for module TimesheetWeek.
 */

global $dolibarr_nocache;

// Load Dolibarr environment.
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$tmp2 = is_string($tmp2) ? $tmp2 : __FILE__;
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/../main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

header('Content-Type: application/javascript');
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
?>

/* Javascript library of module TimesheetWeek */

jQuery(document).ready(function () {
	'use strict';

	function isCurrentPath(pathPattern) {
		return pathPattern.test(String(window.location.pathname || ''));
	}

	function moveUserBankLastSheetsBlock() {
		if (!isCurrentPath(/\/user\/bank\.php$/)) {
			return;
		}

		var $block = jQuery('#timesheetweek-userbank-last-sheets-block');
		if (!$block.length) {
			return;
		}

		var $rightColumn = jQuery('.fichehalfright').first();
		if (!$rightColumn.length) {
			return;
		}

		var $lastNativeTable = $rightColumn.children('.div-table-responsive-no-min').last();
		if ($lastNativeTable.length) {
			$block.insertAfter($lastNativeTable);
		} else {
			$rightColumn.append($block);
		}
	}

	moveUserBankLastSheetsBlock();

	if (!jQuery('body').hasClass('page-notification')) {
		return;
	}

	var moduleLabelFallback = 'TimesheetWeek';

	function buildBookCalPicto() {
		return jQuery('<span/>', {
			'class': 'fa fa-calendar-check pictofixedwidth infobox-portal',
			'aria-hidden': 'true'
		});
	}

	jQuery('table.noborder tr.oddeven').each(function () {
		var $row = jQuery(this);
		var $cells = $row.find('td');
		var code = jQuery.trim($cells.eq(1).text());
		var moduleLabel = jQuery.trim($cells.eq(0).text());

		if (code.indexOf('TIMESHEETWEEK') !== 0) {
			return;
		}
		if (!moduleLabel || moduleLabel.indexOf('@') !== -1 || moduleLabel.toLowerCase() === 'timesheetweek') {
			moduleLabel = moduleLabelFallback;
		}

		$cells.eq(0).empty()
			.append(buildBookCalPicto())
			.append(document.createTextNode(moduleLabel));

		// TimesheetWeek notification events do not use amount thresholds.
		$cells.eq(4).html('&nbsp;');
		$row.find('input[name^="NOTIF_' + code + '_old_"][name$="_amount"]').prop('disabled', true).hide();
		$row.find('input[name="NOTIF_' + code + '_new_amount"]').prop('disabled', true).hide();
	});

	jQuery('select[name^="constvalue_TIMESHEETWEEK_"][name$="_TEMPLATE"], input[name="constname[]"]').each(function () {
		var $field = jQuery(this);
		var constName = String($field.val() || '');
		if ($field.is('select')) {
			constName = String($field.attr('name') || '').replace(/^constvalue_/, '');
		}
		if (constName.indexOf('TIMESHEETWEEK_') !== 0 || constName.substr(-9) !== '_TEMPLATE') {
			return;
		}

		var $labelCell = $field.closest('tr').find('td').eq(0);
		if ($labelCell.children('.fa-calendar-check').length) {
			return;
		}
		$labelCell.children('.pictofixedwidth').first().remove();
		$labelCell.prepend(buildBookCalPicto());
	});
});
