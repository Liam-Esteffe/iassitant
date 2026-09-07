# Assistant IA métier (AiAssistant)

Module Dolibarr externe qui ajoute un **widget conversationnel** sur l’accueil. L’IA analyse les factures, commandes, tiers et produits, puis propose des actions métier exécutées **uniquement après confirmation**.

Version **1.1.0** : droits, journal d’audit, aperçu avant écriture, prompts rapides, historique de session, setup guidé.

Il réutilise le module IA natif de Dolibarr (`modAi`) pour les appels API (ChatGPT, Groq, Mistral ou endpoint custom). Aucune requête SQL n’est générée par le modèle.

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
5. Attribuer les droits **Utiliser l’assistant IA** et, si besoin, **Exécuter les actions**.
6. Ouvrir **Accueil** : le widget apparaît dans une des deux colonnes du tableau de bord.

S’il n’est pas visible, l’ajouter via le combo **Ajouter un widget** → **Assistant IA**.

Après une mise à jour, **désactivez puis réactivez** le module pour créer la table `llx_aiassistant_log` et les nouveaux droits.

## Utilisation (v1.1)

1. Poser une question ou cliquer un **prompt rapide** (impayés, stock, commandes, créer un client).
2. Lire l’analyse et le **récapitulatif des champs** de chaque action proposée.
3. Cliquer **Voir le récapitulatif**, vérifier, puis **Exécuter**. Rien n’est écrit avant cette étape.
4. L’admin consulte **Configuration > Journal**.

Les factures et commandes créées restent **en brouillon**.

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

**Accueil > Configuration > Modules > Assistant IA métier**

- nombre maximum de lignes de contexte
- activation par famille (factures, commandes, tiers, produits)
- bouton **Tester l’appel IA**
- onglets **Journal** et **À propos**

La clé API se configure dans le module IA natif.

## Sécurité

- droits `aiassistant / assistant / read` et `write`
- session authentifiée + jeton CSRF
- droits métier Dolibarr revérifiés à l’exécution
- payload whitelisté, stocké en session (pas renvoyé au navigateur)
- aperçu des champs avant écriture
- journal `llx_aiassistant_log` + `dol_syslog`

## Structure

```
aiassistant/
├── admin/setup.php, log.php, about.php
├── ajax/chat.php, execute.php, test.php
├── class/aicontext.class.php, aiaction.class.php
├── core/modules/modAiAssistant.class.php
├── core/boxes/aiassistantwidget.php
├── sql/llx_aiassistant_log.sql
├── css/, js/, langs/, lib/
├── ChangeLog.md
└── COPYING
```

- Descripteur : numéro `500100`, version `1.1.0`, dépendance `modAi`
- Éditeur : Liam Esteffe — https://github.com/Liam-Esteffe/iassitant

## Licence

GNU General Public License v3. Voir `COPYING`.
