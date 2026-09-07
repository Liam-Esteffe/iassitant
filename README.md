# Assistant IA métier (AiAssistant)

Module Dolibarr externe qui ajoute une **page Assistant** et un **widget** sur l’accueil. L’IA analyse les factures, devis, commandes, tickets, tiers et produits, puis propose des actions métier exécutées **uniquement après confirmation**.

Version **1.2.0** : page dédiée, devis et tickets, relance facture préremplie puis envoyée.

Il réutilise le module IA natif de Dolibarr (`modAi`) pour les appels API (ChatGPT, Groq, Mistral ou endpoint custom). Aucune requête SQL n’est générée par le modèle.

## Prérequis

- Dolibarr 19+
- PHP 7.1+
- Module **IA** activé et configuré (clé API ou URL custom)
- Pour les devis / tickets : modules **Propales** et **Tickets** activés
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
6. Ouvrir le menu **Assistant IA métier**, ou l’accueil (widget) puis **Ouvrir en grand**.

S’il n’est pas visible sur l’accueil, l’ajouter via le combo **Ajouter un widget** → **Assistant IA**.

Après une mise à jour, **désactivez puis réactivez** le module pour enregistrer les menus, constantes et droits.

## Utilisation (v1.2)

1. Poser une question ou cliquer un **prompt rapide** (impayés, stock, commandes, client, devis, ticket, relance).
2. Lire l’analyse et le **récapitulatif des champs** de chaque action proposée (pour une relance : destinataire, sujet, corps).
3. Cliquer **Voir le récapitulatif**, vérifier, puis **Exécuter**. Rien n’est écrit ni envoyé avant cette étape.
4. L’admin consulte **Configuration > Journal**.

Les factures, commandes et devis créés restent **en brouillon**. Une relance n’est proposée que pour une facture **validée, impayée, avec e-mail**.

## Actions autorisées

| Code | Effet |
|---|---|
| `thirdparty.create` / `thirdparty.update` | Créer ou mettre à jour un tiers |
| `product.create` | Créer un produit ou un service |
| `invoice.create_draft` | Créer une facture brouillon |
| `order.create_draft` | Créer une commande brouillon |
| `propal.create_draft` | Créer un devis brouillon |
| `ticket.create` | Créer un ticket |
| `invoice.send_reminder` | Préremplir puis envoyer une relance (alias : `invoice.draft_reminder`) |

Hors périmètre : validation comptable, paiements, suppressions, SQL libre, clôture de ticket.

## Configuration

**Accueil > Configuration > Modules > Assistant IA métier**

- nombre maximum de lignes de contexte
- activation par famille (factures, commandes, devis, tickets, tiers, produits)
- bouton **Tester l’appel IA**
- onglets **Journal** et **À propos**

La clé API se configure dans le module IA natif. L’envoi de relance utilise l’expéditeur `MAIN_MAIL_EMAIL_FROM`.

## Sécurité

- droits `aiassistant / assistant / read` et `write`
- session authentifiée + jeton CSRF
- droits métier Dolibarr revérifiés à l’exécution
- payload whitelisté, stocké en session (pas renvoyé au navigateur)
- aperçu des champs avant écriture ou envoi
- destinataire de relance figé (contacts facturation, sinon e-mail du tiers)
- journal `llx_aiassistant_log` + événement agenda `AC_EMAIL` + `dol_syslog`

## Structure

```
aiassistant/
├── assistant.php
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

- Descripteur : numéro `500100`, version `1.2.0`, dépendance `modAi`
- Éditeur : Liam Esteffe — https://github.com/Liam-Esteffe/iassitant

## Licence

GNU General Public License v3. Voir `COPYING`.
