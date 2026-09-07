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

	$head[$h][0] = dol_buildpath("/aiassistant/admin/log.php", 1);
	$head[$h][1] = $langs->trans("AiAssistantLog");
	$head[$h][2] = 'log';
	$h++;

	$head[$h][0] = dol_buildpath("/aiassistant/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
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

/**
 * True if the user can use the assistant widget.
 *
 * @param	User	$user	Current user
 * @return	bool
 */
function aiassistantCanRead(User $user)
{
	return !empty($user->admin) || $user->hasRight('aiassistant', 'assistant', 'read');
}

/**
 * True if the user can confirm and execute actions.
 *
 * @param	User	$user	Current user
 * @return	bool
 */
function aiassistantCanWrite(User $user)
{
	return !empty($user->admin) || $user->hasRight('aiassistant', 'assistant', 'write');
}

/**
 * Shared chat markup for the Home widget and the dedicated assistant page.
 *
 * @param	array{variant?:string,show_open_full?:int|bool,include_assets?:int|bool}	$options	Render options
 * @return	string
 */
function aiassistantRenderChat(array $options = array())
{
	global $langs, $user;

	$langs->load("aiassistant@aiassistant");

	$variant = !empty($options['variant']) ? (string) $options['variant'] : 'widget';
	$showOpenFull = !empty($options['show_open_full']);
	$includeAssets = !array_key_exists('include_assets', $options) || !empty($options['include_assets']);

	$ready = isModEnabled('ai') && isModEnabled('aiassistant') && aiassistantIsNativeAiConfigured();
	$canWrite = aiassistantCanWrite($user);
	$warning = '';
	if (!isModEnabled('ai')) {
		$warning = $langs->trans("AiAssistantNeedAiModule").' '.$langs->trans("AiAssistantSetupHint");
	} elseif (!aiassistantIsNativeAiConfigured()) {
		$warning = $langs->trans("AiAssistantNeedAiConfig").' '.$langs->trans("AiAssistantSetupHint");
	}

	$cssurl = dol_buildpath('/aiassistant/css/aiassistant.css', 1);
	$jsurl = dol_buildpath('/aiassistant/js/aiassistant.js', 1);
	$chaturl = dol_buildpath('/aiassistant/ajax/chat.php', 1);
	$execurl = dol_buildpath('/aiassistant/ajax/execute.php', 1);
	$pageurl = dol_buildpath('/aiassistant/assistant.php', 1);
	$token = currentToken();

	$prompts = array(
		$langs->trans("AiAssistantPromptUnpaid"),
		$langs->trans("AiAssistantPromptStock"),
		$langs->trans("AiAssistantPromptOrders"),
		$langs->trans("AiAssistantPromptCustomer"),
		$langs->trans("AiAssistantPromptPropal"),
		$langs->trans("AiAssistantPromptTicket"),
		$langs->trans("AiAssistantPromptReminder"),
	);

	$classes = 'aiassistant-widget';
	if ($variant === 'page') {
		$classes .= ' aiassistant-widget--page';
	}

	$html = '';
	if ($includeAssets) {
		$html .= '<link rel="stylesheet" href="'.dol_escape_htmltag($cssurl).'">';
	}
	if ($showOpenFull) {
		$html .= '<div class="aiassistant-openfull"><a href="'.dol_escape_htmltag($pageurl).'">'.dol_escape_htmltag($langs->trans("AiAssistantOpenFull")).'</a></div>';
	}
	$html .= '<div class="'.$classes.'"';
	$html .= ' data-chat-url="'.dol_escape_htmltag($chaturl).'"';
	$html .= ' data-execute-url="'.dol_escape_htmltag($execurl).'"';
	$html .= ' data-token="'.dol_escape_htmltag($token).'"';
	$html .= ' data-can-write="'.($canWrite ? '1' : '0').'"';
	$html .= ' data-lang-send="'.dol_escape_htmltag($langs->trans("AiAssistantSend")).'"';
	$html .= ' data-lang-confirm="'.dol_escape_htmltag($langs->trans("AiAssistantConfirm")).'"';
	$html .= ' data-lang-execute="'.dol_escape_htmltag($langs->trans("AiAssistantExecute")).'"';
	$html .= ' data-lang-cancel="'.dol_escape_htmltag($langs->trans("AiAssistantCancel")).'"';
	$html .= ' data-lang-preview="'.dol_escape_htmltag($langs->trans("AiAssistantPreviewHelp")).'"';
	$html .= ' data-lang-loading="'.dol_escape_htmltag($langs->trans("AiAssistantLoading")).'"';
	$html .= ' data-lang-executing="'.dol_escape_htmltag($langs->trans("AiAssistantExecuting")).'"';
	$html .= ' data-lang-empty="'.dol_escape_htmltag($langs->trans("AiAssistantEmptyQuestion")).'"';
	$html .= ' data-lang-error="'.dol_escape_htmltag($langs->trans("AiAssistantErrorGeneric")).'"';
	$html .= '>';
	$html .= '<div class="aiassistant-messages">';
	if ($warning) {
		$html .= '<div class="aiassistant-msg aiassistant-msg-error">'.dol_escape_htmltag($warning).'</div>';
	} else {
		$html .= '<div class="aiassistant-msg aiassistant-msg-bot">'.dol_escape_htmltag($langs->trans("AiAssistantWelcome")).'</div>';
	}
	$html .= '</div>';
	if ($ready) {
		$html .= '<div class="aiassistant-quickbar">';
		foreach ($prompts as $label) {
			$html .= '<button type="button" class="butAction aiassistant-quick" data-prompt="'.dol_escape_htmltag($label).'">'.dol_escape_htmltag($label).'</button>';
		}
		$html .= '</div>';
	}
	$html .= '<form class="aiassistant-form" action="#" method="POST">';
	$html .= '<textarea class="aiassistant-input flat" rows="'.($variant === 'page' ? '4' : '2').'" placeholder="'.dol_escape_htmltag($langs->trans("AiAssistantPlaceholder")).'"'.($ready ? '' : ' disabled').'></textarea>';
	$html .= '<button type="submit" class="button aiassistant-send"'.($ready ? '' : ' disabled').'>'.dol_escape_htmltag($langs->trans("AiAssistantSend")).'</button>';
	$html .= '</form>';
	$html .= '</div>';
	if ($includeAssets) {
		$html .= '<script src="'.dol_escape_htmltag($jsurl).'"></script>';
	}

	return $html;
}
