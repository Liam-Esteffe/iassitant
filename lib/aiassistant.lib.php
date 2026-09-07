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
 * \file    htdocs/custom/aiassistant/lib/aiassistant.lib.php
 * \ingroup aiassistant
 * \brief   Library files with common functions for AiAssistant
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{0:string,1:string,2:string}>
 */
function aiassistantAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("aiassistant@aiassistant");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/aiassistant/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'aiassistant@aiassistant');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'aiassistant@aiassistant', 'remove');

	return $head;
}

/**
 * Return true when the native AI service has a usable configuration.
 *
 * @return bool
 */
function aiassistantIsNativeAiConfigured()
{
	$service = getDolGlobalString('AI_API_SERVICE', 'chatgpt');
	if ($service == 'custom') {
		return (getDolGlobalString('AI_API_CUSTOM_URL') !== '');
	}

	return (getDolGlobalString('AI_API_'.strtoupper($service).'_KEY') !== '');
}

/**
 * Extract a structured assistant payload from a raw AI answer.
 *
 * @param	string	$raw	Raw model output
 * @return	array{analysis:string,actions:array<int,array<string,mixed>>}
 */
function aiassistantParseModelJson($raw)
{
	$result = array(
		'analysis' => '',
		'actions' => array(),
	);

	$text = trim((string) $raw);
	if ($text === '') {
		return $result;
	}

	if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $matches)) {
		$text = $matches[1];
	} else {
		$start = strpos($text, '{');
		$end = strrpos($text, '}');
		if ($start !== false && $end !== false && $end > $start) {
			$text = substr($text, $start, $end - $start + 1);
		}
	}

	$decoded = json_decode($text, true);
	if (!is_array($decoded)) {
		$result['analysis'] = trim((string) $raw);
		return $result;
	}

	$result['analysis'] = trim((string) ($decoded['analysis'] ?? ''));
	if ($result['analysis'] === '' && !empty($decoded['message'])) {
		$result['analysis'] = trim((string) $decoded['message']);
	}

	if (!empty($decoded['actions']) && is_array($decoded['actions'])) {
		foreach ($decoded['actions'] as $action) {
			if (!is_array($action) || empty($action['type'])) {
				continue;
			}
			$result['actions'][] = array(
				'type' => (string) $action['type'],
				'label' => !empty($action['label']) ? (string) $action['label'] : (string) $action['type'],
				'payload' => (isset($action['payload']) && is_array($action['payload'])) ? $action['payload'] : array(),
			);
		}
	}

	if ($result['analysis'] === '') {
		$result['analysis'] = trim((string) $raw);
	}

	return $result;
}
