<?php
/* Copyright (C) 2026 SuperAdmin
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
 * \file    htdocs/custom/aiassistant/admin/setup.php
 * \ingroup aiassistant
 * \brief   AiAssistant setup page
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
if (!$res && file_exists("../../../../../main.inc.php")) {
	$res = @include "../../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formsetup.class.php";
dol_include_once('/aiassistant/lib/aiassistant.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "aiassistant@aiassistant"));

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');

if (!$user->admin) {
	accessforbidden();
}
if (!isModEnabled('aiassistant')) {
	accessforbidden('Module AiAssistant not enabled');
}

$formSetup = new FormSetup($db);

$item = $formSetup->newItem('AIASSISTANT_CONTEXT_LIMIT');
$item->nameText = $langs->trans("AiAssistantContextLimit");
$item->helpText = $langs->trans("AiAssistantContextLimitHelp");
$item->defaultFieldValue = '20';
$item->cssClass = 'maxwidth100';

$item = $formSetup->newItem('AIASSISTANT_ENABLE_INVOICES')->setAsYesNo();
$item->nameText = $langs->trans("AiAssistantEnableInvoices");
$item = $formSetup->newItem('AIASSISTANT_ENABLE_ORDERS')->setAsYesNo();
$item->nameText = $langs->trans("AiAssistantEnableOrders");
$item = $formSetup->newItem('AIASSISTANT_ENABLE_THIRDPARTY')->setAsYesNo();
$item->nameText = $langs->trans("AiAssistantEnableThirdparty");
$item = $formSetup->newItem('AIASSISTANT_ENABLE_PRODUCTS')->setAsYesNo();
$item->nameText = $langs->trans("AiAssistantEnableProducts");

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

$form = new Form($db);

llxHeader('', $langs->trans("AiAssistantSetup"), '', '', 0, 0, '', '', '', 'mod-aiassistant page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("AiAssistantSetup"), $linkback, 'title_setup');

$head = aiassistantAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("AiAssistantSetup"), -1, "fa-magic");

print '<span class="opacitymedium">'.$langs->trans("AiAssistantSetupPage").'</span><br><br>';

if (isModEnabled('ai')) {
	print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/ai/admin/setup.php">'.$langs->trans("AiAssistantNativeAiSetup").'</a></p>';
} else {
	print '<div class="warning">'.$langs->trans("AiAssistantNeedAiModule").'</div>';
}

print $formSetup->generateOutput(true);

print dol_get_fiche_end();

llxFooter();
$db->close();
