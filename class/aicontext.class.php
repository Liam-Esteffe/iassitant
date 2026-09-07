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
 * \file    htdocs/custom/aiassistant/class/aicontext.class.php
 * \ingroup aiassistant
 * \brief   Collect bounded Dolibarr business data for the AI assistant.
 */

/**
 * Build a permission-aware, volume-capped business context.
 */
class AiContext
{
	/**
	 * @var DoliDB
	 */
	public $db;

	/**
	 * @var string
	 */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Detect intents and collect related business rows.
	 *
	 * @param	string	$question	User question
	 * @param	User	$user		Current user
	 * @return	array<string,mixed>
	 */
	public function collect($question, User $user)
	{
		$limit = getDolGlobalInt('AIASSISTANT_CONTEXT_LIMIT', 20);
		if ($limit < 1) {
			$limit = 5;
		}
		if ($limit > 50) {
			$limit = 50;
		}

		$intents = $this->detectIntents($question);
		$context = array(
			'intents' => array_keys(array_filter($intents)),
			'invoices' => array(),
			'orders' => array(),
			'thirdparties' => array(),
			'products' => array(),
		);

		if ($intents['invoices'] && getDolGlobalInt('AIASSISTANT_ENABLE_INVOICES', 1) && isModEnabled('facture') && $user->hasRight('facture', 'lire')) {
			$context['invoices'] = $this->collectInvoices($user, $limit);
		}
		if ($intents['orders'] && getDolGlobalInt('AIASSISTANT_ENABLE_ORDERS', 1) && isModEnabled('commande') && $user->hasRight('commande', 'lire')) {
			$context['orders'] = $this->collectOrders($user, $limit);
		}
		if ($intents['thirdparties'] && getDolGlobalInt('AIASSISTANT_ENABLE_THIRDPARTY', 1) && isModEnabled('societe') && $user->hasRight('societe', 'lire')) {
			$context['thirdparties'] = $this->collectThirdparties($user, $question, $limit);
		}
		if ($intents['products'] && getDolGlobalInt('AIASSISTANT_ENABLE_PRODUCTS', 1) && (isModEnabled('product') || isModEnabled('service')) && ($user->hasRight('produit', 'lire') || $user->hasRight('service', 'lire'))) {
			$context['products'] = $this->collectProducts($user, $question, $limit);
		}

		return $context;
	}

	/**
	 * Build the instruction sent to the native AI module.
	 *
	 * @param	string				$question	User question
	 * @param	array<string,mixed>	$context	Collected context
	 * @param	Translate			$langs		Language handler
	 * @return	string
	 */
	public function buildInstructions($question, array $context, Translate $langs)
	{
		$allowed = array();
		if (getDolGlobalInt('AIASSISTANT_ENABLE_THIRDPARTY', 1)) {
			$allowed[] = 'thirdparty.create';
			$allowed[] = 'thirdparty.update';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_PRODUCTS', 1)) {
			$allowed[] = 'product.create';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_INVOICES', 1)) {
			$allowed[] = 'invoice.create_draft';
			$allowed[] = 'invoice.draft_reminder';
		}
		if (getDolGlobalInt('AIASSISTANT_ENABLE_ORDERS', 1)) {
			$allowed[] = 'order.create_draft';
		}

		$payloadHints = array(
			'thirdparty.create' => '{name, email?, phone?, address?, zip?, town?, client?:1|0, fournisseur?:1|0}',
			'thirdparty.update' => '{id|socid|ref, name?, email?, phone?, address?, zip?, town?, client?, fournisseur?}',
			'product.create' => '{ref, label, price?, tva_tx?, type?:0|1}',
			'invoice.create_draft' => '{socid|thirdparty_name, lines:[{desc|label, qty, price, tva_tx?, fk_product?}]}',
			'order.create_draft' => '{socid|thirdparty_name, lines:[{desc|label, qty, price, tva_tx?, fk_product?}]}',
			'invoice.draft_reminder' => '{invoice_id|ref}',
		);

		$langcode = !empty($langs->defaultlang) ? $langs->defaultlang : 'en_US';

		$instructions = "You are a Dolibarr ERP business assistant.\n";
		$instructions .= "Answer in the same language as the user question (user language hint: ".$langcode.").\n";
		$instructions .= "Return ONLY valid JSON with this exact shape:\n";
		$instructions .= '{"analysis":"plain text for the user","actions":[{"type":"action.code","label":"short confirm label","payload":{}}]}'."\n";
		$instructions .= "If no database action is needed, return an empty actions array.\n";
		$instructions .= "Never invent IDs that are not in the context. Prefer analysis over action when data is missing.\n";
		$instructions .= "Never propose invoice validation, payments, deletions or SQL.\n";
		$instructions .= "Financial documents must stay drafts.\n";
		$instructions .= "Allowed action types: ".implode(', ', $allowed).".\n";
		$instructions .= "Payload schemas:\n";
		foreach ($payloadHints as $type => $schema) {
			if (in_array($type, $allowed, true)) {
				$instructions .= "- ".$type.": ".$schema."\n";
			}
		}
		$instructions .= "\nBusiness context (JSON):\n";
		$instructions .= json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$instructions .= "\n\nUser question:\n".$question;

		return $instructions;
	}

	/**
	 * Detect which families are relevant. Unknown questions load every enabled family.
	 *
	 * @param	string	$question	User question
	 * @return	array{invoices:bool,orders:bool,thirdparties:bool,products:bool}
	 */
	protected function detectIntents($question)
	{
		$q = dol_strtolower((string) $question);

		$intents = array(
			'invoices' => (bool) preg_match('/facture|invoice|impay|unpaid|relance|reminder|avoir|bill|encours client/i', $q),
			'orders' => (bool) preg_match('/commande|order|commandé|a livrer|to ship/i', $q),
			'thirdparties' => (bool) preg_match('/tiers|client|customer|fournisseur|supplier|societ|company|prospect|third\s*part/i', $q),
			'products' => (bool) preg_match('/produit|product|stock|service|article|sku|rupture|alert/i', $q),
		);

		$any = false;
		foreach ($intents as $flag) {
			if ($flag) {
				$any = true;
				break;
			}
		}
		if (!$any) {
			$intents = array(
				'invoices' => true,
				'orders' => true,
				'thirdparties' => true,
				'products' => true,
			);
		}

		return $intents;
	}

	/**
	 * @param	User	$user	Current user
	 * @param	int		$limit	Max rows
	 * @return	array<int,array<string,mixed>>
	 */
	protected function collectInvoices(User $user, $limit)
	{
		$sql = "SELECT f.rowid, f.ref, f.datef, f.date_lim_reglement, f.total_ttc, f.paye, f.fk_statut as status, s.nom as thirdparty";
		$sql .= " FROM ".$this->db->prefix()."facture as f";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe as s ON s.rowid = f.fk_soc";
		$sql .= $this->joinCommercialRestriction($user, 's');
		$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
		$sql .= " AND (f.fk_statut = 0 OR (f.fk_statut = 1 AND f.paye = 0))";
		$sql .= $this->whereExternalUserRestriction($user, 's');
		$sql .= " ORDER BY f.fk_statut ASC, f.date_lim_reglement ASC, f.rowid DESC";
		$sql .= $this->db->plimit($limit);

		return $this->fetchRows($sql, function ($obj) {
			$overdue = (!empty($obj->date_lim_reglement) && $obj->status == 1 && $obj->paye == 0 && $this->db->jdate($obj->date_lim_reglement) < dol_now());
			return array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'thirdparty' => $obj->thirdparty,
				'date' => $obj->datef,
				'due_date' => $obj->date_lim_reglement,
				'total_ttc' => (float) $obj->total_ttc,
				'status' => ((int) $obj->status === 0 ? 'draft' : 'unpaid'),
				'overdue' => $overdue ? 1 : 0,
			);
		});
	}

	/**
	 * @param	User	$user	Current user
	 * @param	int		$limit	Max rows
	 * @return	array<int,array<string,mixed>>
	 */
	protected function collectOrders(User $user, $limit)
	{
		$sql = "SELECT c.rowid, c.ref, c.date_commande, c.total_ttc, c.fk_statut as status, s.nom as thirdparty";
		$sql .= " FROM ".$this->db->prefix()."commande as c";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe as s ON s.rowid = c.fk_soc";
		$sql .= $this->joinCommercialRestriction($user, 's');
		$sql .= " WHERE c.entity IN (".getEntity('commande').")";
		$sql .= " AND c.fk_statut IN (0, 1, 2)";
		$sql .= $this->whereExternalUserRestriction($user, 's');
		$sql .= " ORDER BY c.fk_statut ASC, c.date_commande DESC, c.rowid DESC";
		$sql .= $this->db->plimit($limit);

		return $this->fetchRows($sql, function ($obj) {
			$statusmap = array(0 => 'draft', 1 => 'validated', 2 => 'shipment_in_progress');
			return array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'thirdparty' => $obj->thirdparty,
				'date' => $obj->date_commande,
				'total_ttc' => (float) $obj->total_ttc,
				'status' => $statusmap[(int) $obj->status] ?? (string) $obj->status,
			);
		});
	}

	/**
	 * @param	User	$user		Current user
	 * @param	string	$question	User question
	 * @param	int		$limit		Max rows
	 * @return	array<int,array<string,mixed>>
	 */
	protected function collectThirdparties(User $user, $question, $limit)
	{
		$terms = $this->extractSearchTerms($question);

		$sql = "SELECT s.rowid, s.nom as name, s.code_client, s.email, s.client, s.fournisseur, s.town, s.zip";
		$sql .= " FROM ".$this->db->prefix()."societe as s";
		$sql .= $this->joinCommercialRestriction($user, 's');
		$sql .= " WHERE s.entity IN (".getEntity('societe').")";
		$sql .= " AND s.status = 1";
		$sql .= $this->whereExternalUserRestriction($user, 's');
		if (!empty($terms)) {
			$likes = array();
			foreach ($terms as $term) {
				$safe = $this->db->escape($this->db->escapeforlike($term));
				$likes[] = "s.nom LIKE '%".$safe."%'";
				$likes[] = "s.code_client LIKE '%".$safe."%'";
			}
			$sql .= " AND (".implode(' OR ', $likes).")";
		}
		$sql .= " ORDER BY s.tms DESC, s.rowid DESC";
		$sql .= $this->db->plimit($limit);

		return $this->fetchRows($sql, function ($obj) {
			return array(
				'id' => (int) $obj->rowid,
				'name' => $obj->name,
				'code_client' => $obj->code_client,
				'email' => $obj->email,
				'client' => (int) $obj->client,
				'fournisseur' => (int) $obj->fournisseur,
				'zip' => $obj->zip,
				'town' => $obj->town,
			);
		});
	}

	/**
	 * @param	User	$user		Current user
	 * @param	string	$question	User question
	 * @param	int		$limit		Max rows
	 * @return	array<int,array<string,mixed>>
	 */
	protected function collectProducts(User $user, $question, $limit)
	{
		$terms = $this->extractSearchTerms($question);
		$stockEnabled = isModEnabled('stock') && $user->hasRight('stock', 'lire');

		$sql = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type, p.tosell, p.tobuy, p.seuil_stock_alerte";
		if ($stockEnabled) {
			$sql .= ", SUM(".$this->db->ifsql("ps.reel IS NULL", "0", "ps.reel").") as stock";
		}
		$sql .= " FROM ".$this->db->prefix()."product as p";
		if ($stockEnabled) {
			$sql .= " LEFT JOIN ".$this->db->prefix()."product_stock as ps ON ps.fk_product = p.rowid";
		}
		$sql .= " WHERE p.entity IN (".getEntity('product').")";
		if (!$user->hasRight('produit', 'lire')) {
			$sql .= " AND p.fk_product_type = 1";
		}
		if (!$user->hasRight('service', 'lire')) {
			$sql .= " AND p.fk_product_type = 0";
		}
		if (!empty($terms)) {
			$likes = array();
			foreach ($terms as $term) {
				$safe = $this->db->escape($this->db->escapeforlike($term));
				$likes[] = "p.ref LIKE '%".$safe."%'";
				$likes[] = "p.label LIKE '%".$safe."%'";
			}
			$sql .= " AND (".implode(' OR ', $likes).")";
		}
		if ($stockEnabled) {
			$sql .= " GROUP BY p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type, p.tosell, p.tobuy, p.seuil_stock_alerte";
		}
		$sql .= " ORDER BY p.tms DESC, p.rowid DESC";
		$sql .= $this->db->plimit($limit);

		return $this->fetchRows($sql, function ($obj) use ($stockEnabled) {
			$row = array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'label' => $obj->label,
				'price' => (float) $obj->price,
				'tva_tx' => (float) $obj->tva_tx,
				'type' => ((int) $obj->fk_product_type === 1 ? 'service' : 'product'),
				'tosell' => (int) $obj->tosell,
				'tobuy' => (int) $obj->tobuy,
			);
			if ($stockEnabled) {
				$row['stock'] = (float) $obj->stock;
				$row['stock_alert'] = (float) $obj->seuil_stock_alerte;
				$row['stock_alert_reached'] = ($obj->seuil_stock_alerte > 0 && $obj->stock < $obj->seuil_stock_alerte) ? 1 : 0;
			}
			return $row;
		});
	}

	/**
	 * Quoted strings or nothing (avoid sending the whole sentence as a LIKE).
	 *
	 * @param	string	$question	User question
	 * @return	string[]
	 */
	protected function extractSearchTerms($question)
	{
		$terms = array();
		if (preg_match_all('/"([^"]{2,80})"/', (string) $question, $matches)) {
			foreach ($matches[1] as $term) {
				$terms[] = $term;
			}
		}
		return array_values(array_unique($terms));
	}

	/**
	 * Restrict to third parties assigned to the user when they cannot see all customers.
	 *
	 * @param	User	$user	Current user
	 * @param	string	$alias	Societe table alias
	 * @return	string
	 */
	protected function joinCommercialRestriction(User $user, $alias)
	{
		if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
			return " INNER JOIN ".$this->db->prefix()."societe_commerciaux as sc ON sc.fk_soc = ".$alias.".rowid AND sc.fk_user = ".((int) $user->id);
		}
		return '';
	}

	/**
	 * Restrict to the external user's own company.
	 *
	 * @param	User	$user	Current user
	 * @param	string	$alias	Societe table alias
	 * @return	string
	 */
	protected function whereExternalUserRestriction(User $user, $alias)
	{
		if (!empty($user->socid)) {
			return " AND ".$alias.".rowid = ".((int) $user->socid);
		}
		return '';
	}

	/**
	 * @param	string		$sql		SQL query
	 * @param	callable	$mapper		Maps a DB object to an array
	 * @return	array<int,array<string,mixed>>
	 */
	protected function fetchRows($sql, $mapper)
	{
		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return $rows;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $mapper($obj);
		}
		$this->db->free($resql);
		return $rows;
	}
}
