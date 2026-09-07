<?php
/* Copyright (C) 2026 Liam Esteffe
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
 * \file    htdocs/custom/aiassistant/assistant.php
 * \ingroup aiassistant
 * \brief   Dedicated full-page AI business assistant
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
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

dol_include_once('/aiassistant/lib/aiassistant.lib.php');

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("aiassistant@aiassistant"));

if (empty($user->id) || !empty($user->socid)) {
	accessforbidden();
}
if (!isModEnabled('aiassistant') || !aiassistantCanRead($user)) {
	accessforbidden();
}

llxHeader('', $langs->trans("AiAssistantPageTitle"), '', '', 0, 0, '', '', '', 'mod-aiassistant page-assistant');

print load_fiche_titre($langs->trans("AiAssistantPageTitle"), '', 'fa-magic');

print '<div class="fichecenter">';
print aiassistantRenderChat(array('variant' => 'page'));
print '</div>';

llxFooter();
$db->close();
