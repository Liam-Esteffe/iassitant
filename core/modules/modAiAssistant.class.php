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
 * \defgroup   aiassistant     Module AiAssistant
 * \brief      AI business assistant widget for the Dolibarr home dashboard.
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
		$this->editor_name = 'AiAssistant';
		$this->editor_url = '';
		$this->version = '1.0.0';
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
		$this->menu = array();
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param	string		$options	Options when enabling module ('', 'noboxes')
	 * @return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
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
