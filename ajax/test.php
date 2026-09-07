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
 * \file    htdocs/custom/aiassistant/ajax/test.php
 * \ingroup aiassistant
 * \brief   Ping the native AI service
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

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

require_once DOL_DOCUMENT_ROOT.'/ai/class/ai.class.php';
dol_include_once('/aiassistant/lib/aiassistant.lib.php');

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->load("aiassistant@aiassistant");

top_httphead('application/json');

function aiassistantTestJsonExit(array $payload, $code = 200)
{
	http_response_code($code);
	$payload['token'] = currentToken();
	print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if (empty($user->id) || empty($user->admin)) {
	aiassistantTestJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantPermissionDenied")), 403);
}
if (!isModEnabled('aiassistant') || !isModEnabled('ai')) {
	aiassistantTestJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiModule")), 403);
}
if (!aiassistantIsNativeAiConfigured()) {
	aiassistantTestJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiConfig")), 400);
}

$ai = new Ai($db);
$generated = $ai->generateContent('Reply with OK', 'auto', 'textgeneration', '');
if (is_array($generated) && !empty($generated['error'])) {
	aiassistantTestJsonExit(array('success' => false, 'error' => $generated['message'] ?: $langs->trans("AiAssistantErrorGeneric")), 500);
}

aiassistantTestJsonExit(array(
	'success' => true,
	'message' => $langs->trans("AiAssistantTestOk"),
	'reply' => dol_trunc(trim((string) $generated), 120),
));
