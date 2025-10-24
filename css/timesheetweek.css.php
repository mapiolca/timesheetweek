<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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

if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

require_once __DIR__.'/../main.inc.php';

header('Content-Type: text/css; charset=UTF-8');

echo '/* EN: Harmonise pagination colours with Dolibarr palette while keeping theme overrides. */' . "\n";
echo '/* FR: Harmonise les couleurs de pagination avec la palette Dolibarr tout en respectant les surcharges du thème. */' . "\n";

echo ':root {' . "\n";
echo "\t--tw-pagination-bg: var(--colorBackBody, #f7f7f7);" . "\n";
echo "\t--tw-pagination-border: var(--colorBorderCard, #bfc4c9);" . "\n";
echo "\t--tw-pagination-color: var(--colorText, #202124);" . "\n";
echo "\t--tw-pagination-hover-bg: var(--colorButActionHoverBg, #e4effa);" . "\n";
echo "\t--tw-pagination-hover-color: var(--colorButActionHoverText, #0b6aa2);" . "\n";
echo "\t--tw-pagination-active-bg: var(--colorButActionBg, #0b6aa2);" . "\n";
echo "\t--tw-pagination-active-color: var(--colorButActionText, #ffffff);" . "\n";
echo "\t--tw-pagination-active-border: var(--colorButActionBg, #0b6aa2);" . "\n";
echo '}' . "\n\n";

echo '/* EN: Normalise the pagination container spacing. */' . "\n";
echo '/* FR: Normalise les espacements du conteneur de pagination. */' . "\n";
echo '.pagination ul,' . "\n";
echo '.pagination ul li {' . "\n";
echo "\tmargin: 0;" . "\n";
echo "\tpadding: 0;" . "\n";
echo "\tlist-style: none;" . "\n";
echo '}' . "\n\n";

echo '/* EN: Display pagination items inline to mimic Dolibarr navigation badges. */' . "\n";
echo '/* FR: Affiche les éléments de pagination en ligne pour reproduire les badges de navigation Dolibarr. */' . "\n";
echo '.pagination ul li {' . "\n";
echo "\tdisplay: inline-block;" . "\n";
echo "\tmargin-right: 4px;" . "\n";
echo '}' . "\n\n";

echo '/* EN: Remove residual margin on the last pagination item for clean alignment. */' . "\n";
echo '/* FR: Supprime la marge résiduelle sur le dernier élément de pagination pour un alignement propre. */' . "\n";
echo '.pagination ul li:last-child {' . "\n";
echo "\tmargin-right: 0;" . "\n";
echo '}' . "\n\n";

echo '/* EN: Apply Dolibarr-like background, border and transition to pagination links. */' . "\n";
echo '/* FR: Applique un fond, une bordure et une transition similaires à Dolibarr sur les liens de pagination. */' . "\n";
echo '.pagination ul li > a,' . "\n";
echo '.pagination ul li > span,' . "\n";
echo 'div.pagination > a,' . "\n";
echo 'div.pagination > span {' . "\n";
echo "\tdisplay: inline-block;" . "\n";
echo "\tpadding: 4px 8px;" . "\n";
echo "\tborder-radius: 3px;" . "\n";
echo "\tborder: 1px solid var(--tw-pagination-border);" . "\n";
echo "\tbackground: var(--tw-pagination-bg);" . "\n";
echo "\tcolor: var(--tw-pagination-color);" . "\n";
echo "\ttext-decoration: none;" . "\n";
echo "\ttransition: background 0.15s ease-in-out, color 0.15s ease-in-out, border-color 0.15s ease-in-out;" . "\n";
echo '}' . "\n\n";

echo '/* EN: Highlight hovered pagination items with the action colour palette. */' . "\n";
echo '/* FR: Met en évidence les éléments survolés avec la palette des actions. */' . "\n";
echo '.pagination ul li > a:hover,' . "\n";
echo 'div.pagination > a:hover {' . "\n";
echo "\tbackground: var(--tw-pagination-hover-bg);" . "\n";
echo "\tcolor: var(--tw-pagination-hover-color);" . "\n";
echo "\tborder-color: var(--tw-pagination-active-border);" . "\n";
echo '}' . "\n\n";

echo '/* EN: Apply the primary action colours to the active pagination element. */' . "\n";
echo '/* FR: Applique les couleurs d\'action principales à l\'élément de pagination actif. */' . "\n";
echo '.pagination ul li.paginationactive > span,' . "\n";
echo '.pagination ul li.paginationactive > a,' . "\n";
echo '.pagination ul li.active > span,' . "\n";
echo '.pagination ul li.active > a,' . "\n";
echo 'div.pagination > span.paginationactive,' . "\n";
echo 'div.pagination > a.paginationactive {' . "\n";
echo "\tbackground: var(--tw-pagination-active-bg);" . "\n";
echo "\tborder-color: var(--tw-pagination-active-border);" . "\n";
echo "\tcolor: var(--tw-pagination-active-color);" . "\n";
echo '}' . "\n";
