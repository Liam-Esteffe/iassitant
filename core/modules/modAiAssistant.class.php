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
 * \defgroup   aiassistant     Module AiAssistant
 * \brief      AI business assistant page and home widget for Dolibarr.
 *
 * \file       htdocs/custom/aiassistant/core/modules/modAiAssistant.class.php
 * \ingroup    aiassistant
 * \brief      Description and activation file for module AiAssistant
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module AiAssistant
 */
class modAiAssistant extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->numero = 500100;
		$this->rights_class = 'aiassistant';
		$this->family = "interface";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "AiAssistantDescription";
		$this->descriptionlong = "AiAssistantDescriptionLong";
		$this->editor_name = 'Liam Esteffe';
		$this->editor_url = 'https://github.com/Liam-Esteffe/iassitant';
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-magic';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array("/aiassistant/temp");
		$this->config_page_url = array("setup.php@aiassistant");

		$this->hidden = false;
		$this->depends = array('modAi');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("aiassistant@aiassistant");

		$this->phpmin = array(7, 1);
		$this->need_dolibarr_version = array(19, 0);
		$this->need_javascript_ajax = 1;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array(
			1 => array('AIASSISTANT_CONTEXT_LIMIT', 'chaine', '20', 'Maximum number of business rows sent to the AI', 0),
			2 => array('AIASSISTANT_ENABLE_INVOICES', 'chaine', '1', 'Allow invoice analysis and draft actions', 0),
			3 => array('AIASSISTANT_ENABLE_ORDERS', 'chaine', '1', 'Allow order analysis and draft actions', 0),
			4 => array('AIASSISTANT_ENABLE_THIRDPARTY', 'chaine', '1', 'Allow third-party analysis and actions', 0),
			5 => array('AIASSISTANT_ENABLE_PRODUCTS', 'chaine', '1', 'Allow product analysis and actions', 0),
			6 => array('AIASSISTANT_ENABLE_PROPAL', 'chaine', '1', 'Allow proposal analysis and draft actions', 0),
			7 => array('AIASSISTANT_ENABLE_TICKETS', 'chaine', '1', 'Allow ticket analysis and create actions', 0),
		);

		if (!isModEnabled("aiassistant")) {
			$conf->aiassistant = new stdClass();
			$conf->aiassistant->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();

		$this->boxes = array(
			0 => array(
				'file' => 'aiassistantwidget.php@aiassistant',
				'note' => 'AI business assistant',
				'enabledbydefaulton' => 'Home',
			),
		);

		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", 1);
		$this->rights[$r][1] = 'Use the AI assistant widget';
		$this->rights[$r][4] = 'assistant';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", 2);
		$this->rights[$r][1] = 'Execute confirmed AI assistant actions';
		$this->rights[$r][4] = 'assistant';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'ModuleAiAssistantName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'aiassistant',
			'leftmenu' => '',
			'url' => '/aiassistant/assistant.php',
			'langs' => 'aiassistant@aiassistant',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("aiassistant")',
			'perms' => '$user->hasRight("aiassistant", "assistant", "read")',
			'target' => '',
			'user' => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=aiassistant',
			'type' => 'left',
			'titre' => 'AiAssistantMenuAssistant',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'aiassistant',
			'leftmenu' => 'aiassistant',
			'url' => '/aiassistant/assistant.php',
			'langs' => 'aiassistant@aiassistant',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("aiassistant")',
			'perms' => '$user->hasRight("aiassistant", "assistant", "read")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param	string		$options	Options when enabling module ('', 'noboxes')
	 * @return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/aiassistant/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param	string		$options	Options when enabling module ('', 'noboxes')
	 * @return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
