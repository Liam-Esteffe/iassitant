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
 * \file    htdocs/custom/aiassistant/class/aiaction.class.php
 * \ingroup aiassistant
 * \brief   Whitelisted business actions for the AI assistant.
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/ai/class/ai.class.php';

/**
 * Validate and execute confirmed assistant actions.
 */
class AiAction
{
	const SESSION_KEY = 'aiassistant_pending_actions';
	const ACTION_TTL = 1800;

	/**
	 * @var DoliDB
	 */
	public $db;

	/**
	 * @var string
	 */
	public $error = '';

	/**
	 * @var string[]
	 */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return string[]
	 */
	public function getAllowedTypes()
	{
		$types = array();
		if (getDolGlobalInt('AIASSISTANT_ENABLE_THIRDPARTY', 1)) {
			$types[] = 'thirdparty.create';
			$types[] = 'thirdparty.update';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_PRODUCTS', 1)) {
			$types[] = 'product.create';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_INVOICES', 1)) {
			$types[] = 'invoice.create_draft';
			$types[] = 'invoice.draft_reminder';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_ORDERS', 1)) {
			$types[] = 'order.create_draft';
		}
		return $types;
	}

	/**
	 * Keep only allowed actions and store them in session.
	 *
	 * @param	array<int,array<string,mixed>>	$actions	Actions proposed by the model
	 * @param	User							$user		Current user
	 * @return	array<int,array{id:string,type:string,label:string}>
	 */
	public function storeProposedActions(array $actions, User $user)
	{
		$allowed = $this->getAllowedTypes();
		$safe = array();

		if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
			$_SESSION[self::SESSION_KEY] = array();
		}

		$this->purgeExpiredActions();

		foreach ($actions as $action) {
			$type = (string) ($action['type'] ?? '');
			if (!in_array($type, $allowed, true)) {
				continue;
			}
			$payload = (isset($action['payload']) && is_array($action['payload'])) ? $action['payload'] : array();
			$id = dol_hash(uniqid((string) $user->id, true).mt_rand(), 'md5');
			$_SESSION[self::SESSION_KEY][$id] = array(
				'type' => $type,
				'payload' => $payload,
				'userid' => (int) $user->id,
				'tms' => dol_now(),
			);
			$safe[] = array(
				'id' => $id,
				'type' => $type,
				'label' => !empty($action['label']) ? (string) $action['label'] : $type,
			);
		}

		return $safe;
	}

	/**
	 * Execute a previously stored action.
	 *
	 * @param	string		$actionId	Session action id
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	public function executeStoredAction($actionId, User $user, Translate $langs)
	{
		$this->purgeExpiredActions();

		if (empty($_SESSION[self::SESSION_KEY][$actionId])) {
			$this->error = $langs->trans("AiAssistantObjectNotFound");
			return -1;
		}

		$stored = $_SESSION[self::SESSION_KEY][$actionId];
		if ((int) $stored['userid'] !== (int) $user->id) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$result = $this->execute($stored['type'], $stored['payload'], $user, $langs);
		if (is_array($result) && !empty($result['success'])) {
			unset($_SESSION[self::SESSION_KEY][$actionId]);
		}

		return $result;
	}

	/**
	 * @param	string		$type		Whitelisted action type
	 * @param	array		$payload	Action payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	public function execute($type, array $payload, User $user, Translate $langs)
	{
		if (!in_array($type, $this->getAllowedTypes(), true)) {
			$this->error = $langs->trans("AiAssistantUnknownAction");
			return -1;
		}

		switch ($type) {
			case 'thirdparty.create':
				return $this->createThirdparty($payload, $user, $langs);
			case 'thirdparty.update':
				return $this->updateThirdparty($payload, $user, $langs);
			case 'product.create':
				return $this->createProduct($payload, $user, $langs);
			case 'invoice.create_draft':
				return $this->createInvoiceDraft($payload, $user, $langs);
			case 'order.create_draft':
				return $this->createOrderDraft($payload, $user, $langs);
			case 'invoice.draft_reminder':
				return $this->draftInvoiceReminder($payload, $user, $langs);
			default:
				$this->error = $langs->trans("AiAssistantUnknownAction");
				return -1;
		}
	}

	/**
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function createThirdparty(array $payload, User $user, Translate $langs)
	{
		if (!isModEnabled('societe') || !$user->hasRight('societe', 'creer')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$name = $this->sanitizeString($payload['name'] ?? ($payload['nom'] ?? ''));
		if ($name === '') {
			$this->error = $langs->trans("AiAssistantMissingField");
			return -1;
		}

		$soc = new Societe($this->db);
		$soc->name = $name;
		$soc->nom = $name;
		$soc->email = $this->sanitizeString($payload['email'] ?? '');
		$soc->phone = $this->sanitizeString($payload['phone'] ?? ($payload['tel'] ?? ''));
		$soc->address = $this->sanitizeString($payload['address'] ?? '');
		$soc->zip = $this->sanitizeString($payload['zip'] ?? '');
		$soc->town = $this->sanitizeString($payload['town'] ?? ($payload['city'] ?? ''));
		$soc->client = isset($payload['client']) ? ((int) $payload['client'] ? 1 : 0) : 1;
		$soc->fournisseur = !empty($payload['fournisseur']) ? 1 : 0;
		if ($soc->client) {
			$soc->code_client = 'auto';
		}
		if ($soc->fournisseur) {
			$soc->code_fournisseur = 'auto';
		}

		$id = $soc->create($user);
		if ($id <= 0) {
			$this->error = $soc->error ?: $langs->trans("AiAssistantErrorGeneric");
			$this->errors = $soc->errors;
			return -1;
		}

		$soc->fetch($id);
		dol_syslog('AiAssistant thirdparty.create id='.$id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantCreated"),
			'url' => $soc->getNomUrl(1),
		);
	}

	/**
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function updateThirdparty(array $payload, User $user, Translate $langs)
	{
		if (!isModEnabled('societe') || !$user->hasRight('societe', 'creer')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$soc = $this->findThirdparty($payload, $user);
		if (empty($soc->id)) {
			$this->error = $langs->trans("AiAssistantObjectNotFound");
			return -1;
		}

		$map = array(
			'name' => 'name',
			'nom' => 'name',
			'email' => 'email',
			'phone' => 'phone',
			'tel' => 'phone',
			'address' => 'address',
			'zip' => 'zip',
			'town' => 'town',
			'city' => 'town',
		);
		foreach ($map as $from => $field) {
			if (array_key_exists($from, $payload) && $payload[$from] !== '') {
				$soc->$field = $this->sanitizeString($payload[$from]);
			}
		}
		if (isset($payload['client'])) {
			$soc->client = ((int) $payload['client'] ? 1 : 0);
		}
		if (isset($payload['fournisseur'])) {
			$soc->fournisseur = ((int) $payload['fournisseur'] ? 1 : 0);
		}
		if (!empty($soc->name)) {
			$soc->nom = $soc->name;
		}

		$result = $soc->update($soc->id, $user);
		if ($result <= 0) {
			$this->error = $soc->error ?: $langs->trans("AiAssistantErrorGeneric");
			$this->errors = $soc->errors;
			return -1;
		}

		$soc->fetch($soc->id);
		dol_syslog('AiAssistant thirdparty.update id='.$soc->id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantUpdated"),
			'url' => $soc->getNomUrl(1),
		);
	}

	/**
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function createProduct(array $payload, User $user, Translate $langs)
	{
		$type = isset($payload['type']) ? (int) $payload['type'] : 0;
		if ($type !== 1) {
			$type = 0;
		}

		if ($type === 1) {
			if (!isModEnabled('service') || !$user->hasRight('service', 'creer')) {
				$this->error = $langs->trans("AiAssistantPermissionDenied");
				return -1;
			}
		} elseif (!isModEnabled('product') || !$user->hasRight('produit', 'creer')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$ref = $this->sanitizeString($payload['ref'] ?? '');
		$label = $this->sanitizeString($payload['label'] ?? ($payload['name'] ?? ''));
		if ($ref === '' || $label === '') {
			$this->error = $langs->trans("AiAssistantMissingField");
			return -1;
		}

		$product = new Product($this->db);
		$product->ref = $ref;
		$product->label = $label;
		$product->type = $type;
		$product->status = 1;
		$product->tosell = 1;
		$product->status_buy = 0;
		$product->price_base_type = 'HT';
		$product->price = price2num($payload['price'] ?? 0);
		$product->tva_tx = price2num($payload['tva_tx'] ?? 0);

		$id = $product->create($user);
		if ($id <= 0) {
			$this->error = $product->error ?: $langs->trans("AiAssistantErrorGeneric");
			$this->errors = $product->errors;
			return -1;
		}

		if ($product->price > 0) {
			$product->updatePrice($product->price, 'HT', $user, $product->tva_tx);
		}

		$product->fetch($id);
		dol_syslog('AiAssistant product.create id='.$id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantCreated"),
			'url' => $product->getNomUrl(1),
		);
	}

	/**
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function createInvoiceDraft(array $payload, User $user, Translate $langs)
	{
		if (!isModEnabled('facture') || !$user->hasRight('facture', 'creer')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$soc = $this->findThirdparty($payload, $user);
		if (empty($soc->id)) {
			$this->error = $langs->trans("AiAssistantObjectNotFound");
			return -1;
		}

		$facture = new Facture($this->db);
		$facture->socid = $soc->id;
		$facture->date = dol_now();
		$facture->type = Facture::TYPE_STANDARD;
		$facture->cond_reglement_id = $soc->cond_reglement_id;
		$facture->mode_reglement_id = $soc->mode_reglement_id;

		$id = $facture->create($user);
		if ($id <= 0) {
			$this->error = $facture->error ?: $langs->trans("AiAssistantErrorGeneric");
			$this->errors = $facture->errors;
			return -1;
		}

		$lines = $this->normalizeLines($payload['lines'] ?? array());
		foreach ($lines as $line) {
			$result = $facture->addline(
				$line['desc'],
				$line['price'],
				$line['qty'],
				$line['tva_tx'],
				0,
				0,
				$line['fk_product']
			);
			if ($result < 0) {
				$this->error = $facture->error ?: $langs->trans("AiAssistantErrorGeneric");
				return -1;
			}
		}

		$facture->fetch($id);
		dol_syslog('AiAssistant invoice.create_draft id='.$id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantCreated"),
			'url' => $facture->getNomUrl(1),
		);
	}

	/**
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function createOrderDraft(array $payload, User $user, Translate $langs)
	{
		if (!isModEnabled('commande') || !$user->hasRight('commande', 'creer')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$soc = $this->findThirdparty($payload, $user);
		if (empty($soc->id)) {
			$this->error = $langs->trans("AiAssistantObjectNotFound");
			return -1;
		}

		$commande = new Commande($this->db);
		$commande->socid = $soc->id;
		$commande->date = dol_now();
		$commande->date_commande = dol_now();
		$commande->source = 0;
		$commande->cond_reglement_id = $soc->cond_reglement_id;
		$commande->mode_reglement_id = $soc->mode_reglement_id;

		$id = $commande->create($user);
		if ($id <= 0) {
			$this->error = $commande->error ?: $langs->trans("AiAssistantErrorGeneric");
			$this->errors = $commande->errors;
			return -1;
		}

		$lines = $this->normalizeLines($payload['lines'] ?? array());
		foreach ($lines as $line) {
			$result = $commande->addline(
				$line['desc'],
				$line['price'],
				$line['qty'],
				$line['tva_tx'],
				0,
				0,
				$line['fk_product']
			);
			if ($result < 0) {
				$this->error = $commande->error ?: $langs->trans("AiAssistantErrorGeneric");
				return -1;
			}
		}

		$commande->fetch($id);
		dol_syslog('AiAssistant order.create_draft id='.$id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantCreated"),
			'url' => $commande->getNomUrl(1),
		);
	}

	/**
	 * Generate a reminder text. No email is sent.
	 *
	 * @param	array		$payload	Payload
	 * @param	User		$user		Current user
	 * @param	Translate	$langs		Language handler
	 * @return	array{success:bool,message:string,url?:string}|int<-1,-1>
	 */
	protected function draftInvoiceReminder(array $payload, User $user, Translate $langs)
	{
		if (!isModEnabled('facture') || !$user->hasRight('facture', 'lire')) {
			$this->error = $langs->trans("AiAssistantPermissionDenied");
			return -1;
		}

		$facture = new Facture($this->db);
		$id = (int) ($payload['invoice_id'] ?? ($payload['id'] ?? 0));
		$ref = $this->sanitizeString($payload['ref'] ?? '');
		$result = 0;
		if ($id > 0) {
			$result = $facture->fetch($id);
		} elseif ($ref !== '') {
			$result = $facture->fetch(0, $ref);
		}
		if ($result <= 0) {
			$this->error = $langs->trans("AiAssistantObjectNotFound");
			return -1;
		}

		$soc = new Societe($this->db);
		$soc->fetch($facture->socid);

		$instructions = "Write a short polite payment reminder email in the user language (".$langs->defaultlang."). ";
		$instructions .= "Do not add explanations. Invoice ref: ".$facture->ref.". ";
		$instructions .= "Third party: ".$soc->name.". Total TTC: ".$facture->total_ttc.". ";
		$instructions .= "Due date: ".dol_print_date($facture->date_lim_reglement, 'day').".";

		$ai = new Ai($this->db);
		$generated = $ai->generateContent($instructions, 'auto', 'textgenerationemail', 'text');
		if (is_array($generated) && !empty($generated['error'])) {
			$this->error = $generated['message'] ?: $langs->trans("AiAssistantErrorGeneric");
			return -1;
		}

		dol_syslog('AiAssistant invoice.draft_reminder id='.$facture->id.' user='.$user->id, LOG_INFO);
		return array(
			'success' => true,
			'message' => $langs->trans("AiAssistantReminderTitle")."\n".trim((string) $generated),
			'url' => $facture->getNomUrl(1),
		);
	}

	/**
	 * @param	array	$payload	Payload with id/socid/name
	 * @param	User	$user		Current user
	 * @return	Societe
	 */
	protected function findThirdparty(array $payload, User $user)
	{
		$soc = new Societe($this->db);
		$id = (int) ($payload['socid'] ?? ($payload['id'] ?? ($payload['fk_soc'] ?? 0)));
		if ($id > 0 && $soc->fetch($id) > 0) {
			return $soc;
		}

		$name = $this->sanitizeString($payload['thirdparty_name'] ?? ($payload['name'] ?? ($payload['nom'] ?? ($payload['ref'] ?? ''))));
		if ($name === '') {
			return $soc;
		}

		$sql = "SELECT s.rowid FROM ".$this->db->prefix()."societe as s";
		if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".$this->db->prefix()."societe_commerciaux as sc ON sc.fk_soc = s.rowid AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE s.entity IN (".getEntity('societe').")";
		$sql .= " AND (s.nom = '".$this->db->escape($name)."' OR s.code_client = '".$this->db->escape($name)."')";
		if (!empty($user->socid)) {
			$sql .= " AND s.rowid = ".((int) $user->socid);
		}
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$soc->fetch((int) $obj->rowid);
		}

		return $soc;
	}

	/**
	 * @param	mixed	$lines	Raw lines
	 * @return	array<int,array{desc:string,qty:float,price:float,tva_tx:float,fk_product:int}>
	 */
	protected function normalizeLines($lines)
	{
		$normalized = array();
		if (!is_array($lines)) {
			return $normalized;
		}
		foreach ($lines as $line) {
			if (!is_array($line)) {
				continue;
			}
			$desc = $this->sanitizeString($line['desc'] ?? ($line['label'] ?? ($line['description'] ?? '')));
			$qty = (float) price2num($line['qty'] ?? 1);
			$price = (float) price2num($line['price'] ?? ($line['pu_ht'] ?? 0));
			$tva = (float) price2num($line['tva_tx'] ?? ($line['vat'] ?? 0));
			$fkProduct = (int) ($line['fk_product'] ?? ($line['product_id'] ?? 0));
			if ($qty <= 0) {
				$qty = 1;
			}
			if ($desc === '' && $fkProduct <= 0) {
				continue;
			}
			if ($desc === '') {
				$desc = 'Line';
			}
			$normalized[] = array(
				'desc' => $desc,
				'qty' => $qty,
				'price' => $price,
				'tva_tx' => $tva,
				'fk_product' => $fkProduct,
			);
		}
		return $normalized;
	}

	/**
	 * @param	mixed	$value	Raw string
	 * @return	string
	 */
	protected function sanitizeString($value)
	{
		return trim(dol_string_nohtmltag((string) $value, 1, 'UTF-8'));
	}

	/**
	 * Drop expired pending actions.
	 *
	 * @return void
	 */
	protected function purgeExpiredActions()
	{
		if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
			return;
		}
		$now = dol_now();
		foreach ($_SESSION[self::SESSION_KEY] as $id => $action) {
			if (empty($action['tms']) || ($now - (int) $action['tms']) > self::ACTION_TTL) {
				unset($_SESSION[self::SESSION_KEY][$id]);
			}
		}
	}
}
