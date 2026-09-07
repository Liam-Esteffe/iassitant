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

		$this->hidden = !isModEnabled('aiassistant') || !isModEnabled('ai') || !empty($user->socid) || !aiassistantCanRead($user);
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

		$html = aiassistantRenderChat(array(
			'variant' => 'widget',
			'show_open_full' => 1,
		));

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
