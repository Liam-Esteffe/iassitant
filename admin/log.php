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
 * \file    htdocs/custom/aiassistant/admin/log.php
 * \ingroup aiassistant
 * \brief   Audit log of AI assistant actions
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/user/class/user.class.php";
dol_include_once('/aiassistant/lib/aiassistant.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "aiassistant@aiassistant"));

$backtopage = GETPOST('backtopage', 'alpha');

if (!$user->admin) {
	accessforbidden();
}
if (!isModEnabled('aiassistant')) {
	accessforbidden('Module AiAssistant not enabled');
}

llxHeader('', $langs->trans("AiAssistantLog"), '', '', 0, 0, '', '', '', 'mod-aiassistant page-admin-log');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("AiAssistantLog"), $linkback, 'title_setup');

$head = aiassistantAdminPrepareHead();
print dol_get_fiche_head($head, 'log', $langs->trans("AiAssistantSetup"), -1, "fa-magic");

print '<span class="opacitymedium">'.$langs->trans("AiAssistantLogHelp").'</span><br><br>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("User").'</th>';
print '<th>'.$langs->trans("AiAssistantQuestion").'</th>';
print '<th>'.$langs->trans("AiAssistantActionType").'</th>';
print '<th>'.$langs->trans("Status").'</th>';
print '<th>'.$langs->trans("Ref").'</th>';
print '</tr>';

$sql = "SELECT l.rowid, l.datec, l.fk_user, l.question, l.action_type, l.status, l.error_message, l.object_type, l.fk_object, l.object_ref, l.payload_summary";
$sql .= " FROM ".$db->prefix()."aiassistant_log as l";
$sql .= " WHERE l.entity IN (".getEntity('aiassistant').")";
$sql .= " ORDER BY l.datec DESC, l.rowid DESC";
$sql .= $db->plimit(100);

$resql = $db->query($sql);
if (!$resql) {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("AiAssistantLogMissing").'</span></td></tr>';
} else {
	$num = $db->num_rows($resql);
	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	$userstatic = new User($db);
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
		print '<td>';
		if ($userstatic->fetch((int) $obj->fk_user) > 0) {
			print $userstatic->getNomUrl(-1);
		} else {
			print (int) $obj->fk_user;
		}
		print '</td>';
		print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($obj->question).'">'.dol_escape_htmltag(dol_trunc($obj->question, 80)).'</td>';
		print '<td>'.dol_escape_htmltag($obj->action_type).'</td>';
		print '<td>';
		if ($obj->status === 'success') {
			print '<span class="badge badge-status4">'.dol_escape_htmltag($langs->trans("AiAssistantActionDone")).'</span>';
		} else {
			print '<span class="badge badge-status8" title="'.dol_escape_htmltag($obj->error_message).'">'.dol_escape_htmltag($langs->trans("Error")).'</span>';
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag($obj->object_ref);
		if (!empty($obj->payload_summary)) {
			print '<div class="opacitymedium small">'.dol_escape_htmltag(dol_trunc($obj->payload_summary, 120)).'</div>';
		}
		print '</td>';
		print '</tr>';
	}
	$db->free($resql);
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
