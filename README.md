# Assistant IA métier (AiAssistant)

Module Dolibarr externe qui ajoute un **widget conversationnel** sur l’accueil. L’IA analyse les factures, commandes, tiers et produits, puis propose des actions métier exécutées **uniquement après confirmation**.

Il réutilise le [module IA natif](../../ai/README.md) (`modAi`) pour les appels API (ChatGPT, Groq, Mistral ou endpoint custom). Aucune requête SQL n’est générée par le modèle.

## Prérequis

- Dolibarr 19+
- PHP 7.1+
- Module **IA** activé et configuré (clé API ou URL custom)
- Scan du répertoire `custom` actif dans `htdocs/conf/conf.php` :

```php
$dolibarr_main_url_root_alt = '/custom';
$dolibarr_main_document_root_alt = '/chemin/vers/dolibarr/htdocs/custom';
```

## Installation

1. Copier le dossier `aiassistant` dans `htdocs/custom/`.
2. Aller dans **Accueil > Configuration > Modules**.
3. Activer **IA**, puis renseigner la clé dans sa page de configuration.
4. Activer **Assistant IA métier**.
5. Ouvrir **Accueil** : le widget apparaît dans une des deux colonnes du tableau de bord.

S’il n’est pas visible, l’ajouter via le combo **Ajouter un widget** → **Assistant IA**.  
Il peut ensuite être déplacé par glisser-déposer. La gestion globale se fait dans **Accueil > Configuration > Widgets**.

## Utilisation

Le flux est en deux temps :

1. **Analyse** — poser une question, par exemple :
   - « Quelles factures sont en retard ? »
   - « Y a-t-il des alertes de stock ? »
   - « Crée le client ACME »
2. **Exécution** — si l’IA propose une action, cliquer sur **Confirmer**. Rien n’est écrit en base avant cette étape.

Les factures et commandes créées restent **en brouillon** (pas de validation ni de paiement automatique).

## Actions autorisées

| Code | Effet |
|---|---|
| `thirdparty.create` / `thirdparty.update` | Créer ou mettre à jour un tiers |
| `product.create` | Créer un produit ou un service |
| `invoice.create_draft` | Créer une facture brouillon |
| `order.create_draft` | Créer une commande brouillon |
| `invoice.draft_reminder` | Générer un texte de relance (aucun e-mail envoyé) |

Hors périmètre : validation comptable, paiements, suppressions, SQL libre.

## Configuration

**Accueil > Configuration > Modules > Assistant IA métier > Configuration**

- nombre maximum de lignes de contexte envoyées au modèle
- activation / désactivation par famille (factures, commandes, tiers, produits)

La clé API se configure uniquement dans le module IA natif.

## Sécurité

- session authentifiée obligatoire
- jeton CSRF
- droits Dolibarr revérifiés à l’exécution
- payload limité à une whitelist de champs
- actions proposées stockées en session (pas renvoyées au navigateur)
- journalisation via `dol_syslog`

## Structure

```
aiassistant/
├── admin/setup.php
├── ajax/chat.php
├── ajax/execute.php
├── class/aicontext.class.php
├── class/aiaction.class.php
├── core/modules/modAiAssistant.class.php
├── core/boxes/aiassistantwidget.php
├── css/aiassistant.css
├── js/aiassistant.js
├── langs/fr_FR/aiassistant.lang
├── langs/en_US/aiassistant.lang
└── lib/aiassistant.lib.php
```

- Descripteur : numéro `500100`, dépendance `modAi`
- Widget Home : `aiassistantwidget.php@aiassistant`

## Licence

GNU General Public License v3, comme Dolibarr.
