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
 * \file    htdocs/custom/aiassistant/ajax/execute.php
 * \ingroup aiassistant
 * \brief   Execute a confirmed assistant action
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

$aiassistantdir = dirname(__DIR__);
if (file_exists($aiassistantdir.'/lib/aiassistant.lib.php')) {
	require_once $aiassistantdir.'/lib/aiassistant.lib.php';
	require_once $aiassistantdir.'/class/aiaction.class.php';
} else {
	dol_include_once('/aiassistant/lib/aiassistant.lib.php');
	dol_include_once('/aiassistant/class/aiaction.class.php');
}

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->load("aiassistant@aiassistant");

top_httphead('application/json');

/**
 * @param	array<string,mixed>	$payload	JSON payload
 * @param	int					$code		HTTP code
 * @return	void
 */
function aiassistantExecuteJsonExit(array $payload, $code = 200)
{
	http_response_code($code);
	$payload['token'] = currentToken();
	print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if (empty($user->id)) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => 'Forbidden'), 403);
}
if (!isModEnabled('aiassistant')) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiAssistant")), 403);
}
if (!aiassistantCanWrite($user)) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantPermissionDenied")), 403);
}
if (!isModEnabled('ai')) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiModule")), 403);
}

$rawData = file_get_contents('php://input');
$jsonData = json_decode($rawData, true);
if (!is_array($jsonData)) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantErrorGeneric")), 400);
}

$actionId = trim((string) ($jsonData['action_id'] ?? ''));
if ($actionId === '' || !preg_match('/^[a-f0-9]{32}$/', $actionId)) {
	aiassistantExecuteJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantUnknownAction")), 400);
}

$engine = new AiAction($db);
$result = $engine->executeStoredAction($actionId, $user, $langs);
if (!is_array($result) || empty($result['success'])) {
	aiassistantExecuteJsonExit(array(
		'success' => false,
		'error' => $engine->error ?: $langs->trans("AiAssistantErrorGeneric"),
	), 400);
}

aiassistantExecuteJsonExit(array(
	'success' => true,
	'message' => $result['message'],
	'url' => $result['url'] ?? '',
));
