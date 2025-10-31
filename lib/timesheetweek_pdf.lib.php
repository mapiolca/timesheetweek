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

/**
 * \file        lib/timesheetweek_pdf.lib.php
 * \ingroup     timesheetweek
 * \brief       Helper functions for TimesheetWeek PDF exports
 */

// EN: Load Dolibarr helpers required to build PDF documents.
// FR: Charge les helpers Dolibarr nécessaires pour construire les documents PDF.
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/timesheetweek/lib/timesheetweek.lib.php');
dol_include_once('/timesheetweek/class/timesheetweek.class.php');

defined('TIMESHEETWEEK_PDF_SUMMARY_SUBDIR') || define('TIMESHEETWEEK_PDF_SUMMARY_SUBDIR', 'summaries');

/**
 * EN: Normalise a value before sending it to TCPDF by decoding HTML entities and applying the output charset.
 * FR: Normalise une valeur avant envoi à TCPDF en décodant les entités HTML et en appliquant le jeu de caractères de sortie.
 *
 * @param string $value
 * @return string
 */
function tw_pdf_normalize_string($value)
{
	// EN: Convert any scalar input into a string for consistent processing.
	// FR: Convertit toute entrée scalaire en chaîne pour un traitement cohérent.
	$value = (string) $value;
	if (function_exists('dol_html_entity_decode')) {
		// EN: Decode HTML entities with Dolibarr helper to restore accented characters before rendering.
		// FR: Décode les entités HTML avec l'helper Dolibarr pour restaurer les caractères accentués avant affichage.
		$value = dol_html_entity_decode($value, ENT_QUOTES | ENT_HTML401, 'UTF-8');
	} else {
		// EN: Fallback on PHP native decoder when the Dolibarr helper is unavailable.
		// FR: Utilise le décodeur natif PHP si l'helper Dolibarr est indisponible.
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML401, 'UTF-8');
	}
	// EN: Access the global translations handler to convert text with the right PDF charset.
	// FR: Accède au gestionnaire global de traductions pour convertir le texte avec le bon jeu de caractères PDF.
	global $langs;
	if ($langs instanceof Translate) {
		// EN: Convert the text into the PDF output charset defined by Dolibarr translations.
		// FR: Convertit le texte dans le jeu de caractères de sortie PDF défini par les traductions Dolibarr.
		$value = $langs->convToOutputCharset($value);
	}
	// EN: Return the fully normalised value ready to be consumed by TCPDF rendering calls.
	// FR: Retourne la valeur totalement normalisée, prête à être consommée par les appels de rendu TCPDF.
	return $value;
}

/**
 * EN: Wrap the normalised value in a safe HTML span ready for TCPDF output.
 * FR: Entoure la valeur normalisée dans un span HTML sûr prêt pour la sortie TCPDF.
 *
 * @param string $value     EN: Value to render in the PDF cell / FR: Valeur à afficher dans la cellule PDF
 * @param bool   $allowHtml EN: Whether HTML tags are authorised / FR: Indique si les balises HTML sont autorisées
 * @return string
 */
function tw_pdf_format_cell_html($value, $allowHtml = false)
{
	// EN: Normalise the input before escaping to keep accents visible in PDF cells.
	// FR: Normalise la valeur avant échappement pour conserver les accents visibles dans les cellules PDF.
	$normalizedValue = tw_pdf_normalize_string($value);
	if ($allowHtml) {
		// EN: Harmonise explicit line breaks to the XHTML form expected by TCPDF.
		// FR: Harmonise les retours à la ligne explicites au format XHTML attendu par TCPDF.
		$normalizedValue = preg_replace('/(\r\n|\r|\n)/', '<br />', $normalizedValue);
		// EN: Normalise existing break tags to the XHTML variant to stay compatible with TCPDF.
		// FR: Normalise les balises de saut de ligne existantes en variante XHTML pour rester compatible avec TCPDF.
		$normalizedValue = preg_replace('/<br\s*\/?\s*>/i', '<br />', $normalizedValue);
		// EN: Allow a limited set of inline tags to preserve safe styling in PDF cells.
		// FR: Autorise un ensemble limité de balises inline pour préserver un style sûr dans les cellules PDF.
		$allowedTags = '<br><strong><b><em><i><u><span>';
		$sanitizedValue = strip_tags($normalizedValue, $allowedTags);
		// EN: Escape stray ampersands so TCPDF receives valid HTML entities.
		// FR: Échappe les esperluettes isolées pour que TCPDF reçoive des entités HTML valides.
		$sanitizedValue = preg_replace('/&(?![a-zA-Z0-9#]+;)/', '&amp;', $sanitizedValue);
		return '<span>'.$sanitizedValue.'</span>';
	}
	// EN: Escape HTML special characters using Dolibarr helper to protect TCPDF output.
	// FR: Échappe les caractères spéciaux HTML avec l'helper Dolibarr pour sécuriser la sortie TCPDF.
	$escapedValue = dol_escape_htmltag($normalizedValue);
	// EN: Wrap the escaped value in a span to stay compatible with TCPDF HTML rendering expectations.
	// FR: Encapsule la valeur échappée dans un span pour rester compatible avec les attentes de rendu HTML de TCPDF.
	return '<span>'.$escapedValue.'</span>';
}

/**
 * EN: Prepare multi-line header content while allowing simple HTML formatting.
 * FR: Prépare un contenu d'entête multi-lignes en autorisant une mise en forme HTML simple.
 *
 * @param string $value
 * @return string
 */
function tw_pdf_format_header_html($value)
{
	// EN: Normalise the input before applying header-specific adjustments.
	// FR: Normalise la valeur avant d'appliquer les ajustements spécifiques à l'entête.
	$normalizedValue = tw_pdf_normalize_string($value);
	// EN: Convert plain line breaks into HTML breaks to mimic on-screen layout.
	// FR: Convertit les retours à la ligne simples en sauts HTML pour imiter la mise en page à l'écran.
	$normalizedValue = preg_replace("/(\r\n|\r|\n)/", '<br />', $normalizedValue);
	// EN: Harmonise any existing break tags to the XHTML form expected by TCPDF.
	// FR: Harmonise les balises de saut de ligne existantes au format XHTML attendu par TCPDF.
	$normalizedValue = preg_replace('/<br\s*\/?\s*>/i', '<br />', $normalizedValue);
	// EN: Allow only basic formatting tags to keep the header safe.
	// FR: Autorise uniquement les balises de mise en forme basiques pour sécuriser l'entête.
	$allowedTags = '<br><strong><b><em><i><u><span>';
	$sanitizedValue = strip_tags($normalizedValue, $allowedTags);
	// EN: Escape lone ampersands to provide valid HTML markup to TCPDF.
	// FR: Échappe les esperluettes isolées afin de fournir un HTML valide à TCPDF.
	$sanitizedValue = preg_replace('/&(?![a-zA-Z0-9#]+;)/', '&amp;', $sanitizedValue);
	// EN: Return the formatted span ready to be rendered in the PDF header.
	// FR: Retourne le span formaté prêt à être rendu dans l'entête PDF.
	return '<span>'.$sanitizedValue.'</span>';
}

/**
 * EN: Convert a hexadecimal color code to an RGB triplet usable by TCPDF.
 * FR: Convertit un code couleur hexadécimal en triplet RVB utilisable par TCPDF.
 *
 * @param string $hexColor
 * @param array<int,int> $fallback
 * @return array<int,int>
 */
function tw_pdf_hex_to_rgb($hexColor, array $fallback = array(33, 37, 41))
{
	$normalized = trim((string) $hexColor);
	if ($normalized === '') {
		return $fallback;
	}
	if ($normalized[0] === '#') {
		$normalized = substr($normalized, 1);
	}
	if (dol_strlen($normalized) === 3) {
		$normalized = $normalized[0].$normalized[0].$normalized[1].$normalized[1].$normalized[2].$normalized[2];
	}
	if (!preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
		return $fallback;
	}
	$red = (int) hexdec(substr($normalized, 0, 2));
	$green = (int) hexdec(substr($normalized, 2, 2));
	$blue = (int) hexdec(substr($normalized, 4, 2));
	return array($red, $green, $blue);
}

/**
 * EN: Render the Dolibarr-styled header containing the logo and company name.
 * FR: Dessine l'entête au style Dolibarr avec le logo et le nom de l'entreprise.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $topMargin
 * @param string $title
 * @param string|array $status
 * @param string $weekRange
 * @param string $subtitle
 * @return float
 */
function tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $title = '', $status = '', $weekRange = '', $subtitle = '')
{
	global $mysoc;

	$defaultFontSize = pdf_getPDFFontSize($langs);
	$logoHeight = 0.0;
	$posX = $leftMargin;
	$posY = $topMargin;
	$logoPath = '';
	$logoDisplayed = false;
	$pageWidth = $pdf->getPageWidth();
	$margins = method_exists($pdf, 'getMargins') ? (array) $pdf->getMargins() : array();
	$rightMargin = isset($margins['right']) ? (float) $margins['right'] : (float) getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$rightBlockWidth = max(90.0, $pageWidth * 0.28);
	$rightBlockX = max($leftMargin, $pageWidth - $rightMargin - $rightBlockWidth);
	$rightBlockBottom = $posY;

	if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
		// EN: Resolve the preferred logo file between large and thumbnail versions.
		// FR: Résout le fichier logo privilégié entre les versions grande et miniature.
		if (!empty($mysoc->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$conf->entity] ?? null)) {
				$logodir = $conf->mycompany->multidir_output[$conf->entity];
			}
			if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && !empty($mysoc->logo_small)) {
				$logoCandidate = $logodir.'/logos/thumbs/'.$mysoc->logo_small;
				if (is_readable($logoCandidate)) {
					$logoPath = $logoCandidate;
				}
			}
			if ($logoPath === '') {
				$logoCandidate = $logodir.'/logos/'.$mysoc->logo;
				if (is_readable($logoCandidate)) {
					$logoPath = $logoCandidate;
				}
			}
		}
		if ($logoPath === '') {
			$defaultLogo = DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.svg';
			if (is_readable($defaultLogo)) {
				$logoPath = $defaultLogo;
			}
		}
		if ($logoPath !== '') {
		$logoHeight = pdf_getHeightForLogo($logoPath);
		$pdf->Image($logoPath, $posX, $posY, 0, $logoHeight);
		// EN: Track that a logo is displayed to hide the company name for visual consistency.
		// FR: Indique qu'un logo est affiché pour masquer le nom de la société et préserver la cohérence visuelle.
		$logoDisplayed = true;
		}
	}

	$companyName = !empty($mysoc->name) ? $mysoc->name : 'Dolibarr ERP & CRM';
	$leftBlockWidth = max(60.0, $rightBlockX - $posX - 2.0);
	if (!$logoDisplayed) {
		// EN: Show the company name only when no logo is available to avoid duplicate branding.
		// FR: Affiche le nom de la société uniquement lorsqu'aucun logo n'est disponible pour éviter une double identité visuelle.
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $defaultFontSize);
		$pdf->SetXY($posX, $posY + max($logoHeight - 6.0, 0.0));
		$pdf->MultiCell($leftBlockWidth, 5, tw_pdf_format_cell_html($companyName), 0, 'L', 0, 1, '', '', true, 0, true);
	}

	// EN: Render the summary title and metadata within the right column of the header.
	// FR: Affiche le titre de synthèse et les métadonnées dans la colonne droite de l'entête.
	// EN: Remove unnecessary spaces around the header title for accurate checks.
	// FR: Supprime les espaces superflus autour du titre d'entête pour des vérifications précises.
	$trimmedTitle = trim((string) $title);
	if (dol_strlen($trimmedTitle) > 0) {
		$pdf->SetFont('', 'B', $defaultFontSize + 2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($rightBlockX, $posY);
		// EN: Force the header title to stay on a single line for a cleaner top-right layout.
		// FR: Force le titre d'entête à rester sur une seule ligne pour un rendu plus propre en haut à droite.
		$pdf->Cell($rightBlockWidth, 6, tw_pdf_normalize_string($trimmedTitle), 0, 0, 'R', 0, '', 0, false, 'T', 'T');
		$rightBlockBottom = max($rightBlockBottom, $posY + 6.0);
	}
	// EN: Insert the status badge under the title when provided by the caller.
	// FR: Insère le badge de statut sous le titre lorsqu'il est fourni par l'appelant.
	$statusHandled = false;
	if (is_array($status)) {
		// EN: Extract the plain text and Dolibarr colors for the PDF badge rendering.
		// FR: Extrait le texte brut et les couleurs Dolibarr pour le rendu du badge PDF.
		$badgeTextSource = !empty($status['label']) ? $status['label'] : '';
		$badgeText = tw_pdf_normalize_string($badgeTextSource);
		$badgeText = trim($badgeText);
		$badgeBackground = tw_pdf_hex_to_rgb(!empty($status['backgroundColor']) ? $status['backgroundColor'] : '', array(173, 181, 189));
		$badgeTextColor = tw_pdf_hex_to_rgb(!empty($status['textColor']) ? $status['textColor'] : '', array(33, 37, 41));
		if (dol_strlen($badgeText) > 0) {
			// EN: Draw a rounded rectangle badge with the Timesheet colors.
			// FR: Dessine un badge arrondi avec les couleurs Timesheet.
			$badgeFontSize = max(6.0, $defaultFontSize - 1.0);
			$pdf->SetFont('', 'B', $badgeFontSize);
			$textWidth = $pdf->GetStringWidth($badgeText);
			$badgePaddingX = 3.0;
			$badgePaddingY = 1.4;
			$badgeWidth = min($rightBlockWidth, $textWidth + (2.0 * $badgePaddingX));
			$badgeHeight = max(6.0, ($badgeFontSize * 0.6) + (2.0 * $badgePaddingY));
			$badgeX = $rightBlockX + max(0.0, $rightBlockWidth - $badgeWidth);
			$badgeY = $rightBlockBottom + 1.5;
			$pdf->SetFillColor($badgeBackground[0], $badgeBackground[1], $badgeBackground[2]);
			if (method_exists($pdf, 'RoundedRect')) {
				$pdf->RoundedRect($badgeX, $badgeY, $badgeWidth, $badgeHeight, 2.0, '1111', 'F', array(), array($badgeBackground[0], $badgeBackground[1], $badgeBackground[2]));
			} else {
				$pdf->Rect($badgeX, $badgeY, $badgeWidth, $badgeHeight, 'F');
			}
			$pdf->SetTextColor($badgeTextColor[0], $badgeTextColor[1], $badgeTextColor[2]);
			$pdf->SetXY($badgeX, $badgeY);
			if (method_exists($pdf, 'Cell')) {
				$pdf->Cell($badgeWidth, $badgeHeight, $badgeText, 0, 0, 'C', 0, '', 0, false, 'T', 'M');
			} else {
				$pdf->MultiCell($badgeWidth, $badgeHeight, $badgeText, 0, 'C', 0, 1, '', '', true, 0, true);
			}
			$pdf->SetFont('', '', $defaultFontSize);
			$pdf->SetTextColor(0, 0, 0);
			$rightBlockBottom = max($rightBlockBottom, $badgeY + $badgeHeight);
			$statusHandled = true;
		}
		if (!$statusHandled && !empty($status['html'])) {
			// EN: Fallback to the HTML badge when structured data cannot be rendered.
			// FR: Revient au badge HTML lorsque les données structurées ne peuvent pas être rendues.
			$fallbackStatus = trim((string) $status['html']);
			if ($fallbackStatus !== '') {
				$pdf->SetFont('', '', $defaultFontSize);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetXY($rightBlockX, $rightBlockBottom + 1.0);
				$pdf->MultiCell($rightBlockWidth, 5, tw_pdf_format_header_html($fallbackStatus), 0, 'R', 0, 1, '', '', true, 0, true);
				$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
				$statusHandled = true;
			}
		}
	}
	if (!$statusHandled) {
		// EN: Preserve legacy behaviour by rendering the status as raw HTML.
		// FR: Préserve le comportement historique en affichant le statut en HTML brut.
		$trimmedStatus = trim((string) $status);
		if (dol_strlen($trimmedStatus) > 0) {
			$pdf->SetFont('', '', $defaultFontSize);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($rightBlockX, $rightBlockBottom + 1.0);
			$pdf->MultiCell($rightBlockWidth, 5, tw_pdf_format_header_html($trimmedStatus), 0, 'R', 0, 1, '', '', true, 0, true);
			$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
		}
	}

// EN: Trim the ISO week range label before output.
	// FR: Supprime les espaces du libellé de plage de semaines avant affichage.
	$trimmedWeekRange = trim((string) $weekRange);
	if (dol_strlen($trimmedWeekRange) > 0) {
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($rightBlockX, $rightBlockBottom + 1.0);
		// EN: Show the ISO week range immediately below the title to mirror Dolibarr headers.
		// FR: Affiche la plage de semaines ISO juste sous le titre pour refléter les entêtes Dolibarr.
		$pdf->MultiCell($rightBlockWidth, 5, tw_pdf_format_header_html($trimmedWeekRange), 0, 'R', 0, 1, '', '', true, 0, true);
		$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
	}
	// EN: Remove unnecessary spaces around the header subtitle before rendering.
	// FR: Supprime les espaces superflus autour du sous-titre d'entête avant affichage.
	$trimmedSubtitle = trim((string) $subtitle);
	if (dol_strlen($trimmedSubtitle) > 0) {
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($rightBlockX, $rightBlockBottom + 1.0);
		$pdf->MultiCell($rightBlockWidth, 5, tw_pdf_format_header_html($trimmedSubtitle), 0, 'R', 0, 1, '', '', true, 0, true);
		$rightBlockBottom = max($rightBlockBottom, $pdf->GetY());
	}

	$pdf->SetTextColor(0, 0, 0);

	return max($posY + max($logoHeight, 16.0), $rightBlockBottom);
}

/**
 * EN: Draw the footer using the standard Dolibarr helper to keep consistent branding.
 * FR: Dessine le pied de page avec le helper Dolibarr standard pour conserver la charte.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $rightMargin
 * @param float $bottomMargin
 * @param CommonObject|null $object
 * @param int $hideFreeText
 * @param float|null $autoPageBreakMargin
 * @return int
 */
function tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, $object = null, $hideFreeText = 0, $autoPageBreakMargin = null)
{
	global $mysoc;

	// EN: Backup automatic page break configuration to avoid splitting the footer on two pages.
	// FR: Sauvegarde la configuration de saut automatique pour éviter de scinder le pied entre deux pages.
	$previousAutoBreak = method_exists($pdf, 'getAutoPageBreak') ? $pdf->getAutoPageBreak() : true;
	$previousBreakMargin = null;
	if (method_exists($pdf, 'getBreakMargin')) {
		$previousBreakMargin = (float) $pdf->getBreakMargin();
	} elseif ($autoPageBreakMargin !== null) {
		$previousBreakMargin = (float) $autoPageBreakMargin;
	} elseif (isset($pdf->bMargin)) {
		// EN: Fallback on TCPDF public margin when helper methods are unavailable.
		// FR: Utilise la marge publique de TCPDF si les helpers sont indisponibles.
		$previousBreakMargin = (float) $pdf->bMargin;
	} else {
		$previousBreakMargin = (float) $bottomMargin;
	}
	$pdf->SetAutoPageBreak(false, 0);

	// EN: Determine if Dolibarr must show the detailed footer blocks (tax numbers, contacts, ...).
	// FR: Détermine si Dolibarr doit afficher les blocs détaillés du pied (numéros fiscaux, contacts, ...).
	$showDetails = empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 0 : $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;

	// EN: Delegate the rendering to pdf_pagefoot to mirror the official Dolibarr layout and logic.
	// FR: Délègue le rendu à pdf_pagefoot pour reproduire la mise en forme et la logique officielles de Dolibarr.
	$footHeight = pdf_pagefoot($pdf, $langs, 'INVOICE_FREE_TEXT', $mysoc, $bottomMargin, $leftMargin, $pdf->getPageHeight(), $object, $showDetails, $hideFreeText);

	// EN: Restore the automatic page break configuration so the following content keeps the same flow.
	// FR: Restaure la configuration de saut automatique pour conserver le même flux pour le contenu suivant.
	$pdf->SetAutoPageBreak($previousAutoBreak, $previousBreakMargin);

	return $footHeight;
}


/**
 * EN: Create a new landscape page and ensure header/footer are drawn.
 * FR: Crée une nouvelle page paysage et dessine l'entête/pied de page.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param Conf $conf
 * @param float $leftMargin
 * @param float $topMargin
 * @param float $rightMargin
 * @param float $bottomMargin
 * @param float|null $autoPageBreakMargin
 * @param string $headerTitle
 * @param string|array $headerStatus
 * @param string $headerWeekRange
 * @param string $headerSubtitle
 * @return float
 */
function tw_pdf_add_landscape_page($pdf, $langs, $conf, $leftMargin, $topMargin, $rightMargin, $bottomMargin, &$headerState = null, $autoPageBreakMargin = null, $headerTitle = '', $headerStatus = '', $headerWeekRange = '', $headerSubtitle = '')
{
	$pdf->AddPage('L');
	// EN: Detect if TCPDF automatic callbacks manage header/footer rendering.
	// FR: Détecte si les callbacks automatiques de TCPDF gèrent le rendu entête/pied.
	$callbacksOn = is_array($headerState) && !empty($headerState['automatic']);

	if ($callbacksOn) {
		// EN: Recompute the header height when missing to avoid duplicated footer calls.
		// FR: Recalcule la hauteur d'entête lorsqu'elle manque pour éviter les appels de pied dupliqués.
		$headerBottom = !empty($headerState['value'])
				? (float) $headerState['value']
				: tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
	} else {
		$headerBottom = tw_pdf_draw_header($pdf, $langs, $conf, $leftMargin, $topMargin, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
		tw_pdf_draw_footer($pdf, $langs, $conf, $leftMargin, $rightMargin, $bottomMargin, null, 0, $autoPageBreakMargin);
		if (is_array($headerState)) {
			// EN: Store the header height for further pages when callbacks remain disabled.
			// FR: Mémorise la hauteur d'entête pour les prochaines pages lorsque les callbacks restent inactifs.
			$headerState['value'] = $headerBottom;
		}
	}
	$contentStart = $headerBottom + 4.0;
	// EN: Force the top margin below the header so every page keeps data between header and footer.
	// FR: Force la marge haute sous l'entête pour que chaque page maintienne les données entre entête et pied.
	$pdf->SetTopMargin($contentStart);
	$pdf->SetXY($leftMargin, $contentStart);
	return $contentStart;
}

/**
 * EN: Display the employee banner for the current section on the PDF.
 * FR: Affiche la bannière de l'employé pour la section courante du PDF.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param User $userObject
 * @param float $defaultFontSize
 * @return void
 */
function tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize)
{
	$pdf->SetFont('', 'B', $defaultFontSize + 1);
	$pdf->SetTextColor(0, 0, 60);
	$pdf->MultiCell(0, 6, tw_pdf_format_cell_html($langs->trans('TimesheetWeekSummaryUserTitle', $userObject->getFullName($langs))), 0, 'L', 0, 1, '', '', true, 0, true);
	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);
}


/**
 * EN: Determine the height required for a table row considering wrapped content.
 * FR: Détermine la hauteur nécessaire pour une ligne du tableau en considérant les retours à la ligne.
 *
 * @param TCPDF $pdf
 * @param float[] $columnWidths
 * @param string[] $values
 * @param float $lineHeight
 * @param bool[] $htmlFlags
 * @return float
 */
function tw_pdf_estimate_row_height($pdf, array $columnWidths, array $values, $lineHeight, array $htmlFlags = array())
{
	$maxLines = 1;
	foreach ($values as $index => $value) {
		$allowHtml = !empty($htmlFlags[$index]);
		$formatted = tw_pdf_format_cell_html($value, $allowHtml);
		$plainSource = $allowHtml ? preg_replace('/<br\s*\/?\s*>/i', "
", $formatted) : $formatted;
		$plain = dol_string_nohtmltag($plainSource);
		$plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML401, 'UTF-8');
		$width = isset($columnWidths[$index]) ? (float) $columnWidths[$index] : 0.0;
		$currentLines = ($width > 0.0) ? max(1, $pdf->getNumLines($plain, $width)) : 1;
		$maxLines = max($maxLines, $currentLines);
	}
	return $lineHeight * $maxLines;
}

/**
 * EN: Render a row with uniform cell height and consistent column widths.
 * FR: Affiche une ligne avec une hauteur uniforme des cellules et des largeurs cohérentes par colonne.
 *
 * @param TCPDF $pdf
 * @param float[] $columnWidths
 * @param string[] $values
 * @param float $lineHeight
 * @param array $options
 * @return void
 */
function tw_pdf_render_row($pdf, array $columnWidths, array $values, $lineHeight, array $options = array())
{
	$border = $options['border'] ?? 1;
	$fill = !empty($options['fill']);
	$alignments = $options['alignments'] ?? array();
	$htmlFlags = $options['html_flags'] ?? array();
	$rowHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $values, $lineHeight, $htmlFlags);
	$initialX = $pdf->GetX();
	$initialY = $pdf->GetY();
	$offset = 0.0;
	foreach ($values as $index => $value) {
		$width = isset($columnWidths[$index]) ? (float) $columnWidths[$index] : 0.0;
		$align = $alignments[$index] ?? 'L';
		$allowHtml = !empty($htmlFlags[$index]);
		$pdf->SetXY($initialX + $offset, $initialY);
		$pdf->MultiCell(
			$width,
			$rowHeight,
			tw_pdf_format_cell_html($value, $allowHtml),
			$border,
			$align,
			$fill,
			0,
			'',
			'',
			true,
			0,
			true,
			true,
			$rowHeight,
			'T',
			false
		);
		$offset += $width;
	}
	$pdf->SetXY($initialX, $initialY + $rowHeight);
}

/**
 * EN: Estimate the full height required to display a user table without page breaks.
 * FR: Estime la hauteur complète nécessaire pour afficher un tableau utilisateur sans saut de page.
 *
 * @param TCPDF $pdf
 * @param Translate $langs
 * @param User $userObject
 * @param float[] $columnWidths
 * @param string[] $columnLabels
 * @param string[][] $recordRows
 * @param string[] $totalsRow
 * @param float $lineHeight
 * @param float $contentWidth
 * @param bool[] $htmlFlags
 * @param float[] $recordLineHeights
 * @return float
 */
function tw_pdf_estimate_user_table_height($pdf, $langs, $userObject, array $columnWidths, array $columnLabels, array $recordRows, array $totalsRow, $lineHeight, $contentWidth, array $htmlFlags = array(), array $recordLineHeights = array())
{
	$bannerText = $langs->trans('TimesheetWeekSummaryUserTitle', $userObject->getFullName($langs));
	$bannerPlain = dol_string_nohtmltag(tw_pdf_format_cell_html($bannerText));
	$bannerLines = max(1, $pdf->getNumLines($bannerPlain, $contentWidth));
	$bannerHeight = 6 * $bannerLines;

	$headerHeight = tw_pdf_estimate_row_height($pdf, $columnWidths, $columnLabels, $lineHeight, $htmlFlags);
	$totalHeight = $bannerHeight + 2 + $headerHeight;

	foreach ($recordRows as $rowIndex => $rowValues) {
		// EN: Apply the precomputed line height when available to reflect the final rendering footprint.
		// FR: Applique la hauteur de ligne pré-calculée lorsque disponible pour refléter l'empreinte finale du rendu.
		$rowLineHeight = isset($recordLineHeights[$rowIndex]) ? (float) $recordLineHeights[$rowIndex] : $lineHeight;
		$totalHeight += tw_pdf_estimate_row_height($pdf, $columnWidths, $rowValues, $rowLineHeight, $htmlFlags);
	}

	$totalHeight += tw_pdf_estimate_row_height($pdf, $columnWidths, $totalsRow, $lineHeight, $htmlFlags);

	return $totalHeight;
}

/**
 * EN: Format decimal hours into the HH:MM representation expected by HR teams.
 * FR: Formate les heures décimales en représentation HH:MM attendue par les équipes RH.
 *
 * @param float $hours
 * @return string
 */
function tw_format_hours_decimal($hours)
{
	$hours = (float) $hours;
	$hoursInt = (int) floor($hours);
	$minutes = (int) round(($hours - $hoursInt) * 60);
	if ($minutes === 60) {
		$hoursInt++;
		$minutes = 0;
	}
	return sprintf('%02d:%02d', $hoursInt, $minutes);
}

/**
 * EN: Convert a decimal number of days into a locale-aware string for PDF output.
 * FR: Convertit un nombre décimal de jours en chaîne localisée pour la sortie PDF.
 *
 * @param float      $days   Day quantity / Quantité de jours
 * @param Translate  $langs  Translator instance / Instance de traduction
 * @return string            Formatted value / Valeur formatée
 */
function tw_format_days_decimal($days, Translate $langs)
{
	global $conf;

	// EN: Normalise the numeric value to two decimals before applying locale formatting.
	// FR: Normalise la valeur numérique à deux décimales avant d'appliquer le formatage local.
	$normalized = price2num((float) $days, '2');

	// EN: Use Dolibarr price helper to honour thousands and decimal separators for the PDF output.
	// FR: Utilise l'assistant de prix Dolibarr pour respecter les séparateurs de milliers et décimaux dans le PDF.
	return price($normalized, '', $langs, $conf, 1, 2);
}

/**
 * EN: Convert relative column weights into absolute widths matching the printable area.
 * FR: Convertit les pondérations de colonnes en largeurs absolues adaptées à la zone imprimable.
 *
 * @param float[] $weights       Relative weights / Pondérations relatives
 * @param float   $usableWidth   Available width / Largeur disponible
 * @return float[]               Absolute widths / Largeurs absolues
 */
function tw_pdf_compute_column_widths(array $weights, $usableWidth)
{
	$widths = $weights;
	$totalWeight = array_sum($weights);
	$usableWidth = (float) $usableWidth;

	if ($totalWeight > 0 && $usableWidth > 0) {
		foreach ($weights as $index => $weight) {
			$ratio = (float) $weight / $totalWeight;
			$widths[$index] = $ratio * $usableWidth;
		}
	}

	return $widths;
}

/**
 * EN: Resolve the user name who sealed a timesheet using linked agenda events.
 * FR: Résout le nom de l'utilisateur ayant scellé une feuille via les événements agenda liés.
 *
 * @param DoliDB $db         Database handler / Gestionnaire de base de données
 * @param int    $timesheetId Timesheet identifier / Identifiant de feuille de temps
 * @param int    $entityId    Entity identifier / Identifiant d'entité
 * @return string             User full name or empty string / Nom complet de l'utilisateur ou chaîne vide
 */
function tw_pdf_resolve_sealed_by($db, $timesheetId, $entityId)
{
	static $cache = array();

	$timesheetId = (int) $timesheetId;
	$entityId = (int) $entityId;
	$cacheKey = $timesheetId.'-'.$entityId;

	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	if ($timesheetId <= 0) {
		$cache[$cacheKey] = '';
		return '';
	}
	$sql = "SELECT ac.fk_user_author as sealer_id, u.firstname, u.lastname";
	$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as ac";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = ac.fk_user_author";
	$sql .= " WHERE ac.code = 'TSWK_SEAL'";
	$sql .= " AND ac.elementtype = 'timesheetweek'";
	$sql .= " AND ac.fk_element = ".$timesheetId;
	$sql .= " AND ac.entity = ".$entityId;
	$sql .= " ORDER BY ac.rowid DESC";
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) {
		$cache[$cacheKey] = '';
		return '';
	}

	$result = '';
	if ($row = $db->fetch_object($resql)) {
		if (!empty($row->lastname) || !empty($row->firstname)) {
			$result = dolGetFirstLastname($row->firstname, $row->lastname);
		}
	}
	$db->free($resql);

	$cache[$cacheKey] = $result;

	return $result;
}

/**
 * EN: Build the HTML badge associated with a timesheet status for PDF rendering.
 * FR: Construit le badge HTML associé à un statut de feuille pour le rendu PDF.
 *
 * @param int       $status Timesheet status code / Code de statut de la feuille
 * @param Translate $langs  Translator / Gestionnaire de traductions
 * @return string           HTML badge string / Chaîne HTML du badge
 */
function tw_pdf_build_status_badge($status, $langs)
{
	$status = (int) $status;
	if (method_exists($langs, 'loadLangs')) {
		$langs->loadLangs(array('timesheetweek@timesheetweek', 'other'));
	}

	$badge = TimesheetWeek::LibStatut($status, 5);
	if (!is_string($badge) || $badge === '') {
		return '<span>'.dol_escape_htmltag($langs->trans('Unknown')).'</span>';
	}

	return $badge;
}

/**
 * EN: Compose the status cell with the badge and contextual messages for the PDF table.
 * FR: Compose la cellule de statut avec le badge et les messages contextuels pour le tableau PDF.
 *
 * @param Translate $langs       Translator instance / Instance de traduction
 * @param int       $status      Timesheet status code / Code du statut de la feuille
 * @param string    $approvedBy  Approver full name / Nom complet de l'approbateur
 * @param string    $sealedBy    Sealer full name / Nom complet du scelleur
 * @return string                HTML snippet for the cell / Fragment HTML pour la cellule
 */
function tw_pdf_compose_status_cell($langs, $status, $approvedBy, $sealedBy)
{
	$status = (int) $status;
	$approvedBy = trim((string) $approvedBy);
	$sealedBy = trim((string) $sealedBy);

	$parts = array();
	$parts[] = tw_pdf_build_status_badge($status, $langs);

	if ($status === TimesheetWeek::STATUS_APPROVED) {
		$label = $approvedBy !== '' ? $approvedBy : $langs->trans('Unknown');
		$parts[] = '<span>'.dol_escape_htmltag($langs->trans('TimesheetWeekSummaryStatusApprovedBy', $label)).'</span>';
	} elseif ($status === TimesheetWeek::STATUS_SEALED) {
		$approvedLabel = $approvedBy !== '' ? $approvedBy : $langs->trans('Unknown');
		$sealedLabel = $sealedBy !== '' ? $sealedBy : $langs->trans('Unknown');
		$parts[] = '<span>'.dol_escape_htmltag($langs->trans('TimesheetWeekSummaryStatusApprovedBy', $approvedLabel)).'</span>';
		$parts[] = '<span>'.dol_escape_htmltag($langs->trans('TimesheetWeekSummaryStatusSealedBy', $sealedLabel)).'</span>';
	}

	return implode('<br />', $parts);
}

/**
 * EN: Build the dataset required to generate a PDF summary of weekly timesheets.
 * FR: Construit l'ensemble de données nécessaire pour générer un résumé PDF des feuilles hebdomadaires.
 *
 * @param DoliDB   $db                 Database handler
 * @param int[]    $timesheetIds       Selected timesheet identifiers
 * @param User     $user               Current Dolibarr user
 * @param bool     $permReadOwn        Permission to read own sheets
 * @param bool     $permReadChild      Permission to read subordinates sheets
 * @param bool     $permReadAll        Permission to read all sheets
 * @return array{users:array<int,array{user:User,records:array<int,array<string,mixed>>,totals:array<string,float|int>>>,errors:string[]>|
 *              array{errors:string[]}
 */
function tw_collect_summary_data($db, array $timesheetIds, User $user, $permReadOwn, $permReadChild, $permReadAll)
{
	$ids = array();
	foreach ($timesheetIds as $candidate) {
		$candidate = (int) $candidate;
		if ($candidate > 0) {
			$ids[] = $candidate;
		}
	}
	$ids = array_values(array_unique($ids));
	if (empty($ids)) {
		return array('errors' => array('TimesheetWeekSummaryNoSelection'));
	}

	$idList = implode(',', $ids);
	$sql = "SELECT t.rowid, t.entity, t.year, t.week, t.total_hours, t.overtime_hours, t.zone1_count, t.zone2_count, t.zone3_count, t.zone4_count, t.zone5_count, t.meal_count, t.fk_user, t.fk_user_valid, t.status, u.lastname, u.firstname, u.weeklyhours, uv.lastname as validator_lastname, uv.firstname as validator_firstname";
	$sql .= " FROM ".MAIN_DB_PREFIX."timesheet_week as t";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as uv ON uv.rowid = t.fk_user_valid";
	$sql .= " WHERE t.rowid IN (".$idList.")";
	$sql .= " AND t.entity IN (".getEntity('timesheetweek').")";

	$resql = $db->query($sql);
	if (!$resql) {
		return array('errors' => array($db->lasterror()));
	}

	$dataset = array();
	$errors = array();

	while ($row = $db->fetch_object($resql)) {
		$targetUserId = (int) $row->fk_user;
		$canRead = tw_can_act_on_user($targetUserId, $permReadOwn, $permReadChild, ($permReadAll || !empty($user->admin)), $user);
		if (!$canRead) {
			$errors[] = 'TimesheetWeekSummaryUnauthorizedSheet';
			continue;
		}

		$week = (int) $row->week;
		$year = (int) $row->year;

		$weekStart = new DateTime();
		$weekStart->setISODate($year, $week);
		$weekEnd = clone $weekStart;
		$weekEnd->modify('+6 days');

		$contractHours = (float) $row->weeklyhours;
		if ($contractHours <= 0) {
			$contractHours = 35.0;
		}
		$contractHours = min($contractHours, (float) $row->total_hours);

		if (!isset($dataset[$targetUserId])) {
			$userSummary = new User($db);
			if ($userSummary->fetch($targetUserId) <= 0) {
				// EN: Skip users that cannot be fetched due to deletion or entity mismatch.
				// FR: Ignore les utilisateurs introuvables suite à une suppression ou à un écart d'entité.
				$errors[] = 'TimesheetWeekSummaryMissingUser';
				continue;
			}
			// EN: Load extrafields to detect the daily rate flag used by forfait jour employees.
			// FR: Charge les extrafields pour détecter le flag forfait jour utilisé par les salariés concernés.
			$userSummary->fetch_optionals($userSummary->id, $userSummary->table_element);
			$dataset[$targetUserId] = array(
				'user' => $userSummary,
				// EN: Persist the daily rate flag to adapt PDF rendering later on.
				// FR: Conserve le flag forfait jour afin d'adapter le rendu PDF ultérieurement.
				'is_daily_rate' => !empty($userSummary->array_options['options_lmdb_daily_rate']),
				'records' => array(),
				'totals' => array(
					'total_hours' => 0.0,
					'contract_hours' => 0.0,
					'overtime_hours' => 0.0,
					'meal_count' => 0,
					'zone1_count' => 0,
					'zone2_count' => 0,
					'zone3_count' => 0,
					'zone4_count' => 0,
					'zone5_count' => 0
				)
			);
		}

		$approvedBy = '';
		if (!empty($row->validator_lastname) || !empty($row->validator_firstname)) {
			// EN: Build the approver full name respecting Dolibarr formatting.
			// FR: Construit le nom complet de l'approbateur selon le format Dolibarr.
			$approvedBy = dolGetFirstLastname($row->validator_firstname, $row->validator_lastname);
		}

		$status = (int) $row->status;
		$sealedBy = '';
		if ($status === TimesheetWeek::STATUS_SEALED) {
			// EN: Resolve the user who sealed the timesheet through agenda history.
			// FR: Résout l'utilisateur ayant scellé la feuille via l'historique agenda.
			$sealedBy = tw_pdf_resolve_sealed_by($db, (int) $row->rowid, (int) $row->entity);
		}

		$record = array(
			'id' => (int) $row->rowid,
			'entity' => (int) $row->entity,
			'week' => $week,
			'year' => $year,
			'week_start' => $weekStart,
			'week_end' => $weekEnd,
			'total_hours' => (float) $row->total_hours,
			'contract_hours' => (float) $contractHours,
			'overtime_hours' => (float) $row->overtime_hours,
			'meal_count' => (int) $row->meal_count,
			'zone1_count' => (int) $row->zone1_count,
			'zone2_count' => (int) $row->zone2_count,
			'zone3_count' => (int) $row->zone3_count,
			'zone4_count' => (int) $row->zone4_count,
			'zone5_count' => (int) $row->zone5_count,
			'approved_by' => $approvedBy,
			'sealed_by' => $sealedBy,
			'status' => $status
		);

		$dataset[$targetUserId]['records'][] = $record;
		$dataset[$targetUserId]['totals']['total_hours'] += $record['total_hours'];
		$dataset[$targetUserId]['totals']['contract_hours'] += $record['contract_hours'];
		$dataset[$targetUserId]['totals']['overtime_hours'] += $record['overtime_hours'];
		$dataset[$targetUserId]['totals']['meal_count'] += $record['meal_count'];
		$dataset[$targetUserId]['totals']['zone1_count'] += $record['zone1_count'];
		$dataset[$targetUserId]['totals']['zone2_count'] += $record['zone2_count'];
		$dataset[$targetUserId]['totals']['zone3_count'] += $record['zone3_count'];
		$dataset[$targetUserId]['totals']['zone4_count'] += $record['zone4_count'];
		$dataset[$targetUserId]['totals']['zone5_count'] += $record['zone5_count'];
	}

	$db->free($resql);

	if (empty($dataset)) {
		$errors[] = 'TimesheetWeekSummaryNoData';
	}

	return array(
		'users' => $dataset,
		'errors' => array_values(array_unique($errors))
	);
}

/**
 * EN: Generate the PDF file summarising selected weekly timesheets.
 * FR: Génère le fichier PDF résumant les feuilles de temps hebdomadaires sélectionnées.
 *
 * @param DoliDB    $db                 Database handler
 * @param Conf      $conf               Dolibarr configuration
 * @param Translate $langs              Translator
 * @param User      $user               Current Dolibarr user
 * @param int[]     $timesheetIds       Selected timesheet identifiers
 * @param bool      $permReadOwn        Permission to read own sheets
 * @param bool      $permReadChild      Permission to read subordinates sheets
 * @param bool      $permReadAll        Permission to read all sheets
 * @return array{success:bool,file?:string,relative?:string,errors?:string[],warnings?:string[]}
 */
function tw_generate_summary_pdf($db, $conf, $langs, User $user, array $timesheetIds, $permReadOwn, $permReadChild, $permReadAll)
{
	// EN: Ensure all translations required by the PDF summary are available before rendering.
	// FR: Garantit la disponibilité des traductions nécessaires à la synthèse PDF avant le rendu.
	if (method_exists($langs, 'loadLangs')) {
		$langs->loadLangs(array('timesheetweek@timesheetweek', 'errors'));
	} else {
		// EN: Fallback for older Dolibarr versions that expose only the singular loader.
		// FR: Solution de secours pour les versions de Dolibarr ne proposant que le chargeur unitaire.
		$langs->load('timesheetweek@timesheetweek');
		$langs->load('errors');
	}

	// EN: Guarantee the Dolibarr "main" dictionary is available even when not preloaded.
	// FR: Garantit la disponibilité du dictionnaire « main » de Dolibarr même s'il n'est pas préchargé.
	if (!property_exists($langs, 'loadedlangs') || empty($langs->loadedlangs['main'])) {
		$langs->load('main');
	}

	// EN: Guarantee the Dolibarr "companies" dictionary is available to translate company details.
	// FR: Garantit la disponibilité du dictionnaire « companies » pour traduire les informations société.
	if (!property_exists($langs, 'loadedlangs') || empty($langs->loadedlangs['companies'])) {
		$langs->load('companies');
	}

	$dataResult = tw_collect_summary_data($db, $timesheetIds, $user, $permReadOwn, $permReadChild, $permReadAll);
	$rawWarnings = !empty($dataResult['errors']) ? $dataResult['errors'] : array();
	$warnings = array();
	foreach ($rawWarnings as $warn) {
		if ($warn === null) {
			continue;
		}
		$warnings[] = $langs->trans($warn);
	}
	$warnings = array_values(array_unique(array_filter($warnings)));

	if (empty($dataResult['users'])) {
		return array('success' => false, 'errors' => (!empty($warnings) ? $warnings : array($langs->trans('TimesheetWeekSummaryNoData'))));
	}

	$dataset = $dataResult['users'];
	if (empty($dataset)) {
		return array('success' => false, 'errors' => (!empty($warnings) ? $warnings : array($langs->trans('TimesheetWeekSummaryNoData'))));
	}

	uasort($dataset, function ($a, $b) {
		$aName = dol_string_nohtmltag($a['user']->lastname);
		$bName = dol_string_nohtmltag($b['user']->lastname);
		return strcasecmp($aName, $bName);
	});
	$sortedUsers = array_values($dataset);

	// EN: Track the lowest and highest ISO weeks among the selected records for filename generation.
	// FR: Suit les semaines ISO minimale et maximale parmi les enregistrements sélectionnés pour le nom du fichier.
	$earliestWeek = null;
	$latestWeek = null;
	foreach ($sortedUsers as $userSummary) {
		if (empty($userSummary['records']) || !is_array($userSummary['records'])) {
			continue;
		}
		foreach ($userSummary['records'] as $record) {
			if (!isset($record['week']) || !isset($record['year'])) {
				continue;
			}
			$weekValue = (int) $record['week'];
			$yearValue = (int) $record['year'];
			$compositeKey = sprintf('%04d%02d', $yearValue, $weekValue);
			if ($earliestWeek === null || strcmp($compositeKey, $earliestWeek['key']) < 0) {
				$earliestWeek = array(
					'key' => $compositeKey,
					'week' => $weekValue,
					'year' => $yearValue
				);
			}
			if ($latestWeek === null || strcmp($compositeKey, $latestWeek['key']) > 0) {
				$latestWeek = array(
					'key' => $compositeKey,
					'week' => $weekValue,
					'year' => $yearValue
				);
			}
		}
	}
	$firstWeekLabel = $earliestWeek !== null ? sprintf('%02d', $earliestWeek['week']) : '00';
	$lastWeekLabel = $latestWeek !== null ? sprintf('%02d', $latestWeek['week']) : $firstWeekLabel;
	// EN: Derive the ISO years associated with the boundary weeks for display.
	// FR: Détermine les années ISO associées aux semaines limites pour l'affichage.
	$firstWeekYear = $earliestWeek !== null ? sprintf('%04d', $earliestWeek['year']) : date('Y');
	$lastWeekYear = $latestWeek !== null ? sprintf('%04d', $latestWeek['year']) : $firstWeekYear;

	$uploaddir = !empty($conf->timesheetweek->multidir_output[$conf->entity] ?? null)
		? $conf->timesheetweek->multidir_output[$conf->entity]
		: (!empty($conf->timesheetweek->dir_output) ? $conf->timesheetweek->dir_output : DOL_DATA_ROOT.'/timesheetweek');

	$targetDir = rtrim($uploaddir, '/').'/'.TIMESHEETWEEK_PDF_SUMMARY_SUBDIR;
	if (dol_mkdir($targetDir) < 0) {
		return array('success' => false, 'errors' => array($langs->trans('ErrorCanNotCreateDir', $targetDir)));
	}

	$timestamp = dol_now();
	// EN: Generate the human-readable filename using translations before sanitising it for storage.
	// FR: Génère le nom lisible via les traductions avant de le nettoyer pour l'enregistrement.
	$displayFilename = $langs->trans('TimesheetWeekSummaryFilename', $firstWeekLabel, $lastWeekLabel);
	// EN: Remove accents and special characters before running Dolibarr sanitisation while keeping readable spaces.
	// FR: Supprime les accents et caractères spéciaux avant l'assainissement Dolibarr tout en conservant des espaces lisibles.
	$asciiFilename = dol_string_unaccent($displayFilename);
	$asciiFilename = preg_replace('/[^A-Za-z0-9._\\- ]+/', '', $asciiFilename);
	$asciiFilename = trim(preg_replace('/\s+/', ' ', $asciiFilename));
	if ($asciiFilename !== '') {
		// EN: Reuse the cleaned ASCII string to preserve natural spacing in the filename.
		// FR: Réutilise la chaîne ASCII nettoyée pour préserver les espaces naturels dans le nom de fichier.
		$displayFilename = $asciiFilename;
	}
	// EN: Sanitize the filename to match Dolibarr's document security checks and avoid missing file errors.
	// FR: Nettoie le nom de fichier pour correspondre aux contrôles de sécurité Dolibarr et éviter les erreurs d'absence de fichier.
	$filename = dol_sanitizeFileName($displayFilename);
	if ($filename === '') {
		// EN: Fallback on the original label when sanitisation returns an empty value (extreme edge cases).
		// FR: Revient au libellé initial si le nettoyage renvoie une valeur vide (cas extrêmes).
		$filename = dol_sanitizeFileName('timesheetweek-summary-'.$firstWeekLabel.'-'.$lastWeekLabel.'.pdf');
	}
	$filepath = $targetDir.'/'.$filename;

	// EN: Prepare the title and metadata strings reused inside the header block.
	// FR: Prépare les libellés du titre et des métadonnées réemployés dans l'entête.
	$headerTitle = $langs->trans('TimesheetWeekSummaryTitle');
	// EN: No status badge is required for summary reports, keep the slot empty.
	// FR: Aucun badge de statut n'est nécessaire pour les rapports de synthèse, on laisse l'emplacement vide.
	$headerStatus = '';
	// EN: Build the human-readable ISO week range displayed under the title.
	// FR: Construit la plage de semaines ISO lisible affichée sous le titre.
	$headerWeekRangeLabel = $langs->trans('TimesheetWeekSummaryHeaderWeekRange');
	// EN: Inject the selected ISO week bounds into the translated label to reflect the chosen range.
	// FR: Injecte les bornes de semaine ISO sélectionnées dans le libellé traduit pour refléter la plage choisie.
	$headerWeekRange = sprintf($headerWeekRangeLabel, $firstWeekLabel, $firstWeekYear, $lastWeekLabel, $lastWeekYear);
	$headerSubtitle = $langs->trans('TimesheetWeekSummaryGeneratedOnBy', dol_print_date($timestamp, 'dayhour'), $user->getFullName($langs));

	$format = pdf_getFormat();
	$pdfFormat = array($format['width'], $format['height']);
	$margeGauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
	$margeDroite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
	$margeHaute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
	$margeBasse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
	$footerReserve = 12;
	// EN: Compute the effective auto-break margin to guarantee enough room for the full footer block.
	// FR: Calcule la marge effective des sauts automatiques pour réserver l'espace complet du pied de page.
	$autoPageBreakMargin = $margeBasse + $footerReserve;

	$pdf = pdf_getInstance($pdfFormat);
	$defaultFontSize = pdf_getPDFFontSize($langs);
	$pdf->SetPageOrientation('L');
	$pdf->SetAutoPageBreak(true, $autoPageBreakMargin);
	$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
	$headerState = array('value' => 0.0, 'automatic' => false);
	if (method_exists($pdf, 'setHeaderCallback') && method_exists($pdf, 'setFooterCallback')) {
		// EN: Delegate header rendering to TCPDF so every page created by the engine receives it automatically.
		// FR: Confie le rendu de l'entête à TCPDF afin que chaque page créée par le moteur le reçoive automatiquement.
		$pdf->setPrintHeader(true);
		$pdf->setHeaderCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeHaute, &$headerState, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle) {
			$headerState['value'] = tw_pdf_draw_header($pdfInstance, $langs, $conf, $margeGauche, $margeHaute, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
			$headerState['automatic'] = true;
		});
		// EN: Delegate footer drawing to TCPDF to guarantee presence on automatic page breaks.
		// FR: Confie le dessin du pied de page à TCPDF pour garantir sa présence lors des sauts automatiques.
		$pdf->setPrintFooter(true);
		$pdf->setFooterCallback(function ($pdfInstance) use ($langs, $conf, $margeGauche, $margeDroite, $margeBasse, $autoPageBreakMargin) {
			tw_pdf_draw_footer($pdfInstance, $langs, $conf, $margeGauche, $margeDroite, $margeBasse, null, 0, $autoPageBreakMargin);
		});
	} else {
		// EN: Disable default TCPDF decorations when callbacks are unavailable and rely on manual drawing.
		// FR: Désactive les décorations TCPDF par défaut si les callbacks sont indisponibles et s'appuie sur le dessin manuel.
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
	}
	// EN: Enable alias replacement for total pages when the method exists on the PDF engine.
	// FR: Active le remplacement de l'alias pour le nombre total de pages quand la méthode existe sur le moteur PDF.
	if (method_exists($pdf, 'AliasNbPages')) {
		$pdf->AliasNbPages();
	} elseif (method_exists($pdf, 'setAliasNbPages')) {
		// EN: Fallback for engines exposing the alias configuration through a setter.
		// FR: Solution de secours pour les moteurs exposant la configuration de l'alias via un setter.
		$pdf->setAliasNbPages();
	}
	$pdf->SetCreator('Dolibarr '.DOL_VERSION);
	$pdf->SetAuthor($user->getFullName($langs));
	$pdf->SetTitle(tw_pdf_normalize_string($langs->trans('TimesheetWeekSummaryTitle')));
	$pdf->SetSubject(tw_pdf_normalize_string($langs->trans('TimesheetWeekSummaryTitle')));
	$pdf->SetFont(pdf_getPDFFont($langs), '', $defaultFontSize);
	$pdf->Open();
	$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $headerState, $autoPageBreakMargin, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
	$pageHeight = $pdf->getPageHeight();

	// EN: Offset the cursor slightly below the header to leave breathing space before the content.
	// FR: Décale le curseur juste sous l'entête pour laisser un espace avant le contenu.
	$contentTop += 2.0;
	$pdf->SetXY($margeGauche, $contentTop);
	$pdf->SetFont('', '', $defaultFontSize);
	$pdf->SetTextColor(0, 0, 0);

	$usableWidth = $pdf->getPageWidth() - $margeGauche - $margeDroite;

	// EN: Describe the standard hour-based layout used for classic employees.
	// FR: Décrit la mise en page standard en heures utilisée pour les salariés classiques.
		$hoursColumnConfig = array(
			'weights' => array(14, 20, 20, 16, 18, 18, 14, 11, 11, 11, 11, 11, 24),
			'labels' => array(
				$langs->trans('TimesheetWeekSummaryColumnWeek'),
				$langs->trans('TimesheetWeekSummaryColumnStart'),
				$langs->trans('TimesheetWeekSummaryColumnEnd'),
				$langs->trans('TimesheetWeekSummaryColumnTotalHours'),
				$langs->trans('TimesheetWeekSummaryColumnContractHours'),
				$langs->trans('TimesheetWeekSummaryColumnOvertime'),
				$langs->trans('TimesheetWeekSummaryColumnMeals'),
				$langs->trans('TimesheetWeekSummaryColumnZone1'),
				$langs->trans('TimesheetWeekSummaryColumnZone2'),
				$langs->trans('TimesheetWeekSummaryColumnZone3'),
				$langs->trans('TimesheetWeekSummaryColumnZone4'),
				$langs->trans('TimesheetWeekSummaryColumnZone5'),
				$langs->trans('TimesheetWeekSummaryColumnStatus')
			),
			'row_alignments' => array('C', 'C', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L'),
			'totals_alignments' => array('L', 'C', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L'),
			'html_flags' => array(false, false, false, false, false, false, false, false, false, false, false, false, true)
		);

		$dailyColumnConfig = array(
			'weights' => array(16, 20, 20, 18, 18, 28),
			'labels' => array(
				$langs->trans('TimesheetWeekSummaryColumnWeek'),
				$langs->trans('TimesheetWeekSummaryColumnStart'),
				$langs->trans('TimesheetWeekSummaryColumnEnd'),
				$langs->trans('TimesheetWeekSummaryColumnTotalDays'),
				$langs->trans('TimesheetWeekSummaryColumnContractDays'),
				$langs->trans('TimesheetWeekSummaryColumnStatus')
			),
			'row_alignments' => array('C', 'C', 'C', 'R', 'R', 'L'),
			'totals_alignments' => array('L', 'C', 'C', 'R', 'R', 'L'),
			'html_flags' => array(false, false, false, false, false, true)
		);

$lineHeight = 6;
	$hoursPerDay = 8.0;

	$isFirstUser = true;
	foreach ($sortedUsers as $userSummary) {
			$userObject = $userSummary['user'];
			$records = $userSummary['records'];
			$totals = $userSummary['totals'];
			$isDailyRateEmployee = !empty($userSummary['is_daily_rate']);
			$columnConfig = $isDailyRateEmployee ? $dailyColumnConfig : $hoursColumnConfig;
			$columnLabels = $columnConfig['labels'];
			$columnWidths = tw_pdf_compute_column_widths($columnConfig['weights'], $usableWidth);
			$rowAlignments = $columnConfig['row_alignments'];
			$totalsAlignments = $columnConfig['totals_alignments'];

			$recordRows = array();
			$recordLineHeights = array();
			// EN: Keep track of each row height to share the same baseline during layout estimation and rendering.
			// FR: Suit la hauteur de chaque ligne pour partager la même base lors de l'estimation et du rendu de la mise en page.
			foreach ($records as $recordIndex => $record) {
				$statusCell = tw_pdf_compose_status_cell($langs, $record['status'], $record['approved_by'], $record['sealed_by']);
				// EN: Double the base line height when the status is approved or sealed to provide extra vertical space.
				// FR: Double la hauteur de ligne de base lorsque le statut est approuvé ou scellé pour offrir plus d'espace vertical.
				$isDoubleHeightStatus = in_array((int) $record['status'], array(TimesheetWeek::STATUS_APPROVED, TimesheetWeek::STATUS_SEALED), true);
				$recordLineHeights[$recordIndex] = $lineHeight * ($isDoubleHeightStatus ? 2 : 1);
				if ($isDailyRateEmployee) {
					$recordRows[] = array(
						sprintf('%d / %d', $record['week'], $record['year']),
						dol_print_date($record['week_start']->getTimestamp(), 'day'),
						dol_print_date($record['week_end']->getTimestamp(), 'day'),
						tw_format_days_decimal(($record['total_hours'] / $hoursPerDay), $langs),
						tw_format_days_decimal(($record['contract_hours'] / $hoursPerDay), $langs),
						$statusCell
					);
				} else {
					$recordRows[] = array(
						sprintf('%d / %d', $record['week'], $record['year']),
						dol_print_date($record['week_start']->getTimestamp(), 'day'),
						dol_print_date($record['week_end']->getTimestamp(), 'day'),
						tw_format_hours_decimal($record['total_hours']),
						tw_format_hours_decimal($record['contract_hours']),
						tw_format_hours_decimal($record['overtime_hours']),
						(string) $record['meal_count'],
						(string) $record['zone1_count'],
						(string) $record['zone2_count'],
						(string) $record['zone3_count'],
						(string) $record['zone4_count'],
						(string) $record['zone5_count'],
						$statusCell
					);
				}
			}

			if ($isDailyRateEmployee) {
				$totalsRow = array(
					$langs->trans('TimesheetWeekSummaryTotalsLabel'),
					'',
					'',
					tw_format_days_decimal(($totals['total_hours'] / $hoursPerDay), $langs),
					tw_format_days_decimal(($totals['contract_hours'] / $hoursPerDay), $langs),
					''
				);
			} else {
				$totalsRow = array(
					$langs->trans('TimesheetWeekSummaryTotalsLabel'),
					'',
					'',
					tw_format_hours_decimal($totals['total_hours']),
					tw_format_hours_decimal($totals['contract_hours']),
					tw_format_hours_decimal($totals['overtime_hours']),
					(string) $totals['meal_count'],
					(string) $totals['zone1_count'],
					(string) $totals['zone2_count'],
					(string) $totals['zone3_count'],
					(string) $totals['zone4_count'],
					(string) $totals['zone5_count'],
					''
				);
			}

			$htmlFlags = $columnConfig['html_flags'] ?? array();

			// EN: Anticipate the dynamic line height of each record to size the table and manage page breaks accurately.
			// FR: Anticipe la hauteur de ligne dynamique de chaque enregistrement pour dimensionner le tableau et gérer précisément les sauts de page.
			$tableHeight = tw_pdf_estimate_user_table_height($pdf, $langs, $userObject, $columnWidths, $columnLabels, $recordRows, $totalsRow, $lineHeight, $usableWidth, $htmlFlags, $recordLineHeights);
		$spacingBeforeTable = $isFirstUser ? 0 : 4;
		$availableHeight = ($pageHeight - ($margeBasse + $footerReserve)) - $pdf->GetY();
		if (($spacingBeforeTable + $tableHeight) > $availableHeight) {
			$contentTop = tw_pdf_add_landscape_page($pdf, $langs, $conf, $margeGauche, $margeHaute, $margeDroite, $margeBasse, $headerState, $autoPageBreakMargin, $headerTitle, $headerStatus, $headerWeekRange, $headerSubtitle);
			$pageHeight = $pdf->getPageHeight();
			$availableHeight = ($pageHeight - ($margeBasse + $footerReserve)) - $pdf->GetY();
			if (($spacingBeforeTable + $tableHeight) > $availableHeight) {
				// EN: Warn users when a table cannot fit even on a fresh page.
				// FR: Avertit les utilisateurs lorsqu'un tableau ne tient pas même sur une page vierge.
				$warnings[] = $langs->trans('TimesheetWeekSummaryTableTooTall', $userObject->getFullName($langs));
			}
		}
		
		if ($isFirstUser) {
			// EN: Skip the initial spacer so the first table begins on the opening page.
			// FR: Ignore l'espacement initial pour que le premier tableau démarre sur la page d'ouverture.
			$isFirstUser = false;
		} else {
			$pdf->Ln(4);
		}
		
		tw_pdf_print_user_banner($pdf, $langs, $userObject, $defaultFontSize);
		$headerY = $pdf->GetY() + 2;
		// EN: Position the table header just after the employee banner.
		// FR: Positionne l'entête du tableau juste après l'en-tête salarié.
		$pdf->SetY($headerY);
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetLineWidth(0.2);
		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$pdf->SetX($margeGauche);
		// EN: Draw the header row with uniform dimensions for every column.
		// FR: Dessine la ligne d'entête avec des dimensions uniformes pour chaque colonne.
			tw_pdf_render_row($pdf, $columnWidths, $columnLabels, $lineHeight, array(
		'fill' => true,
		'alignments' => array_fill(0, count($columnLabels), 'C'),
		'html_flags' => $htmlFlags
	));

		$pdf->SetFont('', '', $defaultFontSize - 1);
		$alignments = $rowAlignments;
		// EN: Render each data row while keeping consistent heights across the table.
		// FR: Affiche chaque ligne de données en conservant des hauteurs cohérentes dans le tableau.
		foreach ($recordRows as $rowIndex => $rowData) {
			// EN: Reuse the dynamic line height computed earlier to align rendering with the layout estimation.
			// FR: Réutilise la hauteur de ligne dynamique calculée précédemment pour aligner le rendu sur l'estimation de mise en page.
			$currentLineHeight = $recordLineHeights[$rowIndex] ?? $lineHeight;
			$pdf->SetX($margeGauche);
			tw_pdf_render_row($pdf, $columnWidths, $rowData, $currentLineHeight, array(
				'alignments' => $alignments,
				'html_flags' => $htmlFlags
			));
		}

		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$pdf->SetX($margeGauche);
		// EN: Print the totals row with left-aligned label and right-aligned figures.
		// FR: Imprime la ligne de totaux avec libellé aligné à gauche et chiffres alignés à droite.
		tw_pdf_render_row($pdf, $columnWidths, $totalsRow, $lineHeight, array(
			'alignments' => $totalsAlignments,
			'html_flags' => $htmlFlags
		));

		}
	$pdf->Output($filepath, 'F');

	return array(
		'success' => true,
		'file' => $filepath,
		'relative' => TIMESHEETWEEK_PDF_SUMMARY_SUBDIR.'/'.$filename,
		'warnings' => $warnings
	);
}
