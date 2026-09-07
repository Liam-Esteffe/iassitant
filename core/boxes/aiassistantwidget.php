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
 * \file    htdocs/custom/aiassistant/core/boxes/aiassistantwidget.php
 * \ingroup aiassistant
 * \brief   Home widget for the AI business assistant
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";
if (!function_exists('aiassistantIsNativeAiConfigured')) {
	$aiassistantlib = dirname(__FILE__).'/../../lib/aiassistant.lib.php';
	if (file_exists($aiassistantlib)) {
		require_once $aiassistantlib;
	} else {
		dol_include_once('/aiassistant/lib/aiassistant.lib.php');
	}
}

/**
 * Class to manage the AI assistant box
 */
class aiassistantwidget extends ModeleBoxes
{
	public $boxcode = "aiassistantbox";
	public $boximg = "fa-magic";
	public $boxlabel = 'AiAssistantWidgetLabel';
	public $lang = 'aiassistant@aiassistant';
	public $depends = array('aiassistant', 'ai');
	public $widgettype = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db    Database handler
	 * @param string $param More parameters
	 */
	public function __construct(DoliDB $db, $param = '')
	{
		global $user;

		parent::__construct($db, $param);

		$this->hidden = !isModEnabled('aiassistant') || !isModEnabled('ai') || !empty($user->socid);
	}

	/**
	 * Load data into info_box_contents array to show array later.
	 *
	 * @param	int<0,max>	$max	Maximum number of records to load
	 * @return	void
	 */
	public function loadBox($max = 5)
	{
		global $langs;

		$langs->load("aiassistant@aiassistant");

		$this->max = $max;
		$this->info_box_head = array(
			'text' => $langs->trans("AiAssistantWidgetTitle"),
			'limit' => 0,
		);

		$ready = isModEnabled('ai') && isModEnabled('aiassistant') && aiassistantIsNativeAiConfigured();
		$warning = '';
		if (!isModEnabled('ai')) {
			$warning = $langs->trans("AiAssistantNeedAiModule");
		} elseif (!aiassistantIsNativeAiConfigured()) {
			$warning = $langs->trans("AiAssistantNeedAiConfig");
		}

		$cssurl = dol_buildpath('/aiassistant/css/aiassistant.css', 1);
		$jsurl = dol_buildpath('/aiassistant/js/aiassistant.js', 1);
		$chaturl = dol_buildpath('/aiassistant/ajax/chat.php', 1);
		$execurl = dol_buildpath('/aiassistant/ajax/execute.php', 1);
		$token = currentToken();

		$html = '<div class="aiassistant-widget"';
		$html .= ' data-chat-url="'.dol_escape_htmltag($chaturl).'"';
		$html .= ' data-execute-url="'.dol_escape_htmltag($execurl).'"';
		$html .= ' data-token="'.dol_escape_htmltag($token).'"';
		$html .= ' data-lang-send="'.dol_escape_htmltag($langs->trans("AiAssistantSend")).'"';
		$html .= ' data-lang-confirm="'.dol_escape_htmltag($langs->trans("AiAssistantConfirm")).'"';
		$html .= ' data-lang-cancel="'.dol_escape_htmltag($langs->trans("AiAssistantCancel")).'"';
		$html .= ' data-lang-loading="'.dol_escape_htmltag($langs->trans("AiAssistantLoading")).'"';
		$html .= ' data-lang-executing="'.dol_escape_htmltag($langs->trans("AiAssistantExecuting")).'"';
		$html .= ' data-lang-empty="'.dol_escape_htmltag($langs->trans("AiAssistantEmptyQuestion")).'"';
		$html .= ' data-lang-error="'.dol_escape_htmltag($langs->trans("AiAssistantErrorGeneric")).'"';
		$html .= '>';
		$html .= '<link rel="stylesheet" href="'.dol_escape_htmltag($cssurl).'">';
		$html .= '<div class="aiassistant-messages">';
		if ($warning) {
			$html .= '<div class="aiassistant-msg aiassistant-msg-error">'.dol_escape_htmltag($warning).'</div>';
		} else {
			$html .= '<div class="aiassistant-msg aiassistant-msg-bot">'.dol_escape_htmltag($langs->trans("AiAssistantWelcome")).'</div>';
		}
		$html .= '</div>';
		$html .= '<form class="aiassistant-form" action="#" method="POST">';
		$html .= '<textarea class="aiassistant-input flat" rows="2" placeholder="'.dol_escape_htmltag($langs->trans("AiAssistantPlaceholder")).'"'.($ready ? '' : ' disabled').'></textarea>';
		$html .= '<button type="submit" class="button aiassistant-send"'.($ready ? '' : ' disabled').'>'.dol_escape_htmltag($langs->trans("AiAssistantSend")).'</button>';
		$html .= '</form>';
		$html .= '</div>';
		$html .= '<script src="'.dol_escape_htmltag($jsurl).'"></script>';

		$this->info_box_contents[0][0] = array(
			'td' => 'class="nobottom nopaddingleft nopaddingright"',
			'text' => $html,
			'asis' => 1,
		);
	}

	/**
	 * Method to show box.
	 *
	 * @param	?array<array{text?:string,sublink?:string,subtext?:string,subpicto?:?string,picto?:string,nbcol?:int,limit?:int,subclass?:string,graph?:int<0,1>,target?:string}>   $head       Array with properties of box title
	 * @param	?array<array{tr?:string,td?:string,target?:string,text?:string,text2?:string,textnoformat?:string,tooltip?:string,logo?:string,url?:string,maxlength?:int,asis?:int<0,1>}>   $contents   Array with properties of box lines
	 * @param	int<0,1>	$nooutput	No print, only return string
	 * @return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
