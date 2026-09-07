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
 * \file    htdocs/custom/aiassistant/ajax/chat.php
 * \ingroup aiassistant
 * \brief   Analyse a user question and return proposed actions
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

$aiassistantdir = dirname(__DIR__);
if (file_exists($aiassistantdir.'/lib/aiassistant.lib.php')) {
	require_once $aiassistantdir.'/lib/aiassistant.lib.php';
	require_once $aiassistantdir.'/class/aicontext.class.php';
	require_once $aiassistantdir.'/class/aiaction.class.php';
} else {
	dol_include_once('/aiassistant/lib/aiassistant.lib.php');
	dol_include_once('/aiassistant/class/aicontext.class.php');
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
function aiassistantJsonExit(array $payload, $code = 200)
{
	http_response_code($code);
	$payload['token'] = currentToken();
	print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if (empty($user->id)) {
	aiassistantJsonExit(array('success' => false, 'error' => 'Forbidden'), 403);
}
if (!isModEnabled('aiassistant')) {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiAssistant")), 403);
}
if (!aiassistantCanRead($user)) {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantPermissionDenied")), 403);
}
if (!isModEnabled('ai')) {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiModule")), 403);
}
if (!aiassistantIsNativeAiConfigured()) {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantNeedAiConfig")), 400);
}

$rawData = file_get_contents('php://input');
$jsonData = json_decode($rawData, true);
if (!is_array($jsonData)) {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantErrorGeneric")), 400);
}

$question = trim(dol_string_nohtmltag((string) ($jsonData['question'] ?? ''), 1, 'UTF-8'));
if ($question === '') {
	aiassistantJsonExit(array('success' => false, 'error' => $langs->trans("AiAssistantEmptyQuestion")), 400);
}

$contextBuilder = new AiContext($db);
$context = $contextBuilder->collect($question, $user);
$instructions = $contextBuilder->buildInstructions($question, $context, $langs);

dol_syslog('AiAssistant chat question='.dol_trunc($question, 180), LOG_INFO);

$ai = new Ai($db);
$generated = $ai->generateContent($instructions, 'auto', 'textgeneration', '');
if (is_array($generated) && !empty($generated['error'])) {
	$code = !empty($generated['code']) ? (int) $generated['code'] : 500;
	aiassistantJsonExit(array('success' => false, 'error' => $generated['message'] ?: $langs->trans("AiAssistantErrorGeneric")), $code >= 400 ? $code : 500);
}

$parsed = aiassistantParseModelJson((string) $generated);
$actionEngine = new AiAction($db);
$safeActions = $actionEngine->storeProposedActions($parsed['actions'], $user, $question);
if (!aiassistantCanWrite($user)) {
	foreach ($safeActions as $i => $action) {
		$safeActions[$i]['can_write'] = 0;
	}
} else {
	foreach ($safeActions as $i => $action) {
		$safeActions[$i]['can_write'] = 1;
	}
}

aiassistantJsonExit(array(
	'success' => true,
	'analysis' => $parsed['analysis'],
	'actions' => $safeActions,
));
