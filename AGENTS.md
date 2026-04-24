# AGENTS.md — Développement de modules externes Dolibarr

> Ce fichier guide les agents IA (Claude Code, Copilot, Cursor…) travaillant sur des modules
> externes Dolibarr ERP/CRM. **Lis-le intégralement avant d'écrire la moindre ligne de code.**

## Mandat fondamental

Le module produit doit être **indiscernable d'un module natif Dolibarr** : design, UX,
interactions, multi-entité, compatibilité. Un utilisateur, un intégrateur ou un développeur
Dolibarr expérimenté ne doit trouver aucune différence de qualité avec les modules fournis
par l'équipe core.

**Version cible minimum : Dolibarr v20.0.** Tout le code produit doit fonctionner sur v20
sans dégradation. Si une fonctionnalité nécessite un rétroport, celui-ci est implémenté ou,
s'il est impossible, **documenté explicitement** dans le code et dans `COMPATIBILITY.md`.

---

## 1. Contexte du projet

Ce dépôt contient un **module externe Dolibarr** installé dans `htdocs/custom/<monmodule>/`.
Dolibarr est un ERP/CRM PHP + MySQL/MariaDB/PostgreSQL open source. Les modules externes ne
modifient **jamais** le core : toute personnalisation passe par les mécanismes officiels
(hooks, triggers, descripteur de module, API REST).

---

## 2. Compatibilité versions — Protocole obligatoire

> Cette section s'applique à **tout le code**, pas seulement à MultiCompany.
> L'agent doit la consulter avant d'utiliser n'importe quelle API Dolibarr.

### 2.1 Procédure systématique avant tout usage d'API Dolibarr

```
1. Identifier la version minimum du projet (v20.0 dans ce dépôt)
2. Consulter le changelog officiel :
   https://github.com/Dolibarr/dolibarr/blob/develop/ChangeLog
3. Pour chaque fonction utilisée, vérifier depuis quelle version elle existe
4. Si la fonction n'existe pas en v20 :
   a. Implémenter un backport → annoter // @BACKPORT vX→v20
   b. Si le backport est impossible → documenter dans COMPATIBILITY.md
5. Tester sur v20 (version cible)
```

### 2.2 Tableau de compatibilité des APIs critiques

| Fonction / Pattern | Intro. | Remplace | Statut v20 |
|---|---|---|---|
| `isModEnabled('xxx')` | v15 | `!empty($conf->xxx->enabled)` | ✅ Natif |
| `$user->hasRight('m','o','p')` | v19 | `$user->rights->m->o->p` | ✅ Natif |
| `getDolGlobalString('K')` | v15 | `$conf->global->K` | ✅ Natif |
| `getDolGlobalInt('K')` | v15 | `(int)$conf->global->K` | ✅ Natif |
| `GETPOSTINT('k')` | v16 | `(int)GETPOST('k','int')` | ✅ Natif |
| `GETPOSTISSET('k')` | v15 | `isset($_POST['k'])` | ✅ Natif |
| `dolGetButtonTitle()` | v12 | HTML `<a>` brut | ✅ Natif |
| `dolGetButtonAction()` | v10 | HTML `<a>` brut | ✅ Natif |
| `setEventMessages()` | v8 | `setEventMessage()` (dépr.) | ✅ Natif |
| `getEntity('xxx', 1)` | v3 | `entity = X` en dur | ✅ Natif |
| `dol_eval()` | v14 | `eval()` brut | ✅ Natif |
| `dol_include_once()` | v7 | `require_once` pour modules | ✅ Natif |
| `multidir_output[$entity]` | v6 | Chemin fixe | ✅ Natif |
| `restrictedArea()` | v3 | Vérification manuelle | ✅ Natif |
| `$form->showInputField()` | v14 | HTML brut | ✅ Natif |
| `$form->showOutputField()` | v14 | HTML brut | ✅ Natif |
| `img_picto()` | v3 | `<img>` brut | ✅ Natif |
| `dol_print_date()` | v3 | `date()` PHP | ✅ Natif |
| `price()` / `price2num()` | v3 | `number_format()` PHP | ✅ Natif |
| `natural_search()` | v8 | `LIKE '%x%'` brut | ✅ Natif |
| `dol_buildpath()` | v8 | Chemin relatif brut | ✅ Natif |
| `$object->setVarsFromFetchObj()` | v14 | Affectation manuelle | ✅ Natif |
| `$object->getFieldList('t')` | v14 | `SELECT t.a, t.b, ...` | ✅ Natif |
| `dol_sort_array()` | v6 | `usort()` manuel | ✅ Natif |
| `$conf->use_javascript_ajax` | v3 | Détection JS manuelle | ✅ Natif |

### 2.3 Pattern de rétroport

```php
// Annotation obligatoire sur tout rétroport
// @BACKPORT v19→v20 : hasRight() n'existe pas avant v19
if (!method_exists($user, 'hasRight')) {
    $permissiontoread = !empty($user->rights->monmodule->monobjet->read);
} else {
    $permissiontoread = $user->hasRight('monmodule', 'monobjet', 'read');
}
```

### 2.4 Si le rétroport est impossible — Documentation obligatoire

Créer ou mettre à jour `COMPATIBILITY.md` à la racine du module :

```markdown
# COMPATIBILITY.md

## Version minimum : Dolibarr v20.0

### Fonctionnalités sans rétroport possible

| Fonctionnalité | Version requise | Raison | Impact si version inférieure |
|---|---|---|---|
| Génération PDF via `ModelePDF` v2 | v20.0 | API incompatible v19 | PDF non générable |
| ExtraFields type `link` | v19.0 | Type inexistant avant | Champ ignoré silencieusement |

### Fonctionnalités avec rétroport

| Fonctionnalité | Version native | Rétroport implémenté | Fichier |
|---|---|---|---|
| `getDolGlobalString()` | v15 | Oui | `lib/monmodule.lib.php` |
```

### 2.5 Vérification ciblée des changelogs

Quand un comportement est incertain :

```
# 1. Changelog officiel (par version)
https://github.com/Dolibarr/dolibarr/blob/develop/ChangeLog

# 2. Doxygen — signature actuelle d'une fonction
https://doxygen.dolibarr.org/

# 3. Recherche dans les commits GitHub
https://github.com/Dolibarr/dolibarr/commits/develop -- htdocs/core/lib/functions.lib.php

# 4. Vérification dans le code source v20
https://github.com/Dolibarr/dolibarr/tree/20.0/htdocs
```

---

## 3. Architecture obligatoire

### 3.1 Arborescence standard

```
htdocs/custom/monmodule/
├── core/
│   ├── modules/
│   │   └── modMonModule.class.php          # Descripteur (OBLIGATOIRE)
│   ├── triggers/
│   │   └── interface_modMonModule_MonModuleTriggers.class.php
│   ├── boxes/
│   │   └── box_monmodule.php
│   └── substitutions/
│       └── functions_monmodule.lib.php
├── class/
│   ├── monobjet.class.php                  # Classe métier (hérite CommonObject)
│   ├── api_monobjet.class.php              # Endpoint REST
│   └── actions_monmodule.class.php         # Hooks
├── sql/
│   ├── llx_monmodule_monobjet.sql          # Création de la table
│   ├── llx_monmodule_monobjet.key.sql      # Index et contraintes
│   └── data.sql                            # Données initiales (guillemets simples)
├── lib/
│   └── monmodule.lib.php                   # Fonctions utilitaires
├── langs/
│   ├── fr_FR/monmodule.lang
│   └── en_US/monmodule.lang
├── img/
│   └── object_monobjet.png                 # Icône 16×16
├── css/
│   └── monmodule.css
├── admin/
│   └── setup.php                           # Page de configuration
├── card.php                                # Fiche (vue/édition)
├── list.php                                # Liste avec filtres
└── tpl/
    └── linkedobjectblock.tpl.php
```

### 3.2 Conventions de nommage

| Élément            | Convention                                         | Exemple                              |
|--------------------|----------------------------------------------------|--------------------------------------|
| Tables SQL         | `llx_` + snake_case                                | `llx_monmodule_document`             |
| Clé primaire       | toujours `rowid` (jamais `id`)                     | `rowid integer NOT NULL AUTO_INCREMENT` |
| Clés étrangères    | `fk_` + table + `_` + champ                        | `fk_monobjet_fk_soc`                 |
| Index              | `idx_` + table + `_` + champ                       | `idx_llx_monmodule_document_ref`     |
| Clés uniques       | `uk_` + table + `_` + description                  | `uk_llx_monmodule_document_ref`      |
| Classes PHP        | PascalCase                                         | `MonObjet`                           |
| Fichiers classe    | snake_case + `.class.php`                          | `monobjet.class.php`                 |
| Descripteur module | `modNomModule.class.php`                           | `modMonModule.class.php`             |
| Fichier API        | `api_monobjet.class.php`                           |                                      |
| Fichier hooks      | `actions_monmodule.class.php`                      |                                      |
| Fichier triggers   | `interface_modMonModule_MonModuleTriggers.class.php` |                                    |

---

## 4. Règles de code — PHP

### 3.1 Standards généraux (PSR-12 + exceptions Dolibarr)

- **Uniquement `<?php`** — jamais `<?` ni `<?=`
- Tabulations autorisées (ne pas convertir en espaces)
- Longueur de ligne : soft limit 120 chars, hard limit 1 000 chars
- Fichiers Unix **LF** uniquement (jamais CR/LF)
- Copyright header Dolibarr à jour sur chaque fichier

### 3.2 Inclusions

```php
// Classes et libs : include_once
include_once DOL_DOCUMENT_ROOT.'/custom/monmodule/class/monobjet.class.php';
include_once DOL_DOCUMENT_ROOT.'/custom/monmodule/lib/monmodule.lib.php';

// Fichiers inc et templates : include
include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
```

### 3.3 Entrées utilisateur — OBLIGATOIRE

```php
// TOUJOURS utiliser GETPOST — JAMAIS $_GET / $_POST / $_REQUEST
$id     = GETPOST('id', 'int');
$ref    = GETPOST('ref', 'alphanohtml');
$search = GETPOST('search_label', 'alpha');
$action = GETPOST('action', 'aZ09');
```

Types disponibles : `int`, `alpha`, `alphanohtml`, `aZ09`, `san_alpha`, `nohtml`, `email`, `restricthtml`.

### 3.4 Valeurs de retour

```php
// Succès : >= 0  |  Erreur : < 0
public function create(User $user): int
{
    // ...
    if ($error) {
        $this->error  = 'ErrorMessage';
        $this->errors[] = 'ErrorMessage';
        return -1;
    }
    return $this->id;
}
```

### 3.5 Variables globales (disponibles après `main.inc.php`)

```php
$db          // Connexion base de données
$conf        // Configuration Dolibarr
$user        // Utilisateur courant
$langs       // Traductions
$mysoc       // Société propriétaire
$hookmanager // Gestionnaire de hooks
```

### 3.6 Chaînes de caractères

```php
// Variables hors des guillemets
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_document WHERE ref = '".$db->escape($ref)."'";

// Traductions
$langs->load('monmodule@monmodule');
echo $langs->trans('MyKey');
```

---

## 5. Règles de code — SQL

### 4.1 Interdictions absolues

| Interdit                        | Alternative Dolibarr                    |
|---------------------------------|-----------------------------------------|
| `SELECT *`                      | Lister tous les champs explicitement    |
| `NOW()`, `SYSDATE()`, `DATEDIFF()` | `$db->idate(dol_now())`              |
| `DELETE CASCADE` / `ON UPDATE CASCADE` | Gestion manuelle dans le PHP     |
| Database triggers               | Triggers Dolibarr (`interface_...`)     |
| Stored procedures               | PHP                                     |
| `GROUP_CONCAT`                  | Traitement PHP                          |
| `WITH ROLLUP`                   | Calculs PHP                             |
| `ENUM`                          | `smallint` + constantes PHP             |
| Foreign keys vers tables core   | Gestion dans le code PHP                |
| Guillemets autour des numériques | `WHERE rowid = `.$id (sans guillemets) |
| Création de tables à l'exécution | Tables créées à l'activation du module |

### 4.2 Types SQL autorisés (compatibilité MySQL/MariaDB/PostgreSQL)

| Type              | Usage                                      |
|-------------------|--------------------------------------------|
| `integer`         | Clés primaires, entiers, FK               |
| `smallint`        | Statuts, booléens, petits entiers          |
| `double(24,8)`    | Montants financiers                        |
| `double(6,3)`     | Taux de TVA                               |
| `real`            | Quantités                                  |
| `varchar(n)`      | Chaînes (même longueur 1 — pas de `char`) |
| `timestamp`       | Date+heure avec auto-update               |
| `datetime`        | Date+heure                                 |
| `date`            | Date seule                                 |
| `text`/`mediumtext` | Champs longs (non indexables)           |

### 4.3 Structure minimale d'une table

```sql
-- llx_monmodule_monobjet.sql
CREATE TABLE llx_monmodule_monobjet (
  rowid          integer      NOT NULL AUTO_INCREMENT,
  ref            varchar(30)  NOT NULL,
  entity         integer      DEFAULT 1 NOT NULL,
  ref_ext        varchar(255),
  label          varchar(255),
  fk_soc         integer,
  amount         double(24,8),
  note_public    text,
  note_private   text,
  datec          datetime,
  tms            timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat  integer,
  fk_user_modif  integer,
  import_key     varchar(14),
  status         smallint     DEFAULT 0
) ENGINE=InnoDB;

-- llx_monmodule_monobjet.key.sql
ALTER TABLE llx_monmodule_monobjet ADD PRIMARY KEY (rowid);
ALTER TABLE llx_monmodule_monobjet ADD UNIQUE INDEX uk_llx_monmodule_monobjet_ref (ref, entity);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_llx_monmodule_monobjet_fk_soc (fk_soc);
```

---

## 6. Classe métier (CommonObject)

### 5.1 Squelette minimal

```php
<?php
/**
 * Copyright (C) YYYY  Prénom Nom <email>
 *
 * This program is free software: ...
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class MonObjet extends CommonObject
{
    public $element      = 'monobjet';
    public $table_element = 'monmodule_monobjet';
    public $picto        = 'monobjet@monmodule';
    public $ismultientitymanaged = 1;

    // Propriétés standard
    public $ref;
    public $label;
    public $status;
    public $date_creation;
    public $date_modification;
    public $fk_user_creat;
    public $fk_user_modif;
    public $note_public;
    public $note_private;

    // Descripteur des champs (utilisé par CommonObject::createCommon, fetchCommon…)
    public $fields = [
        'rowid'  => ['type'=>'integer',    'label'=>'ID',     'enabled'=>1, 'position'=>1,  'notnull'=>1, 'visible'=>0, 'primary'=>1],
        'ref'    => ['type'=>'varchar(30)','label'=>'Ref',    'enabled'=>1, 'position'=>10, 'notnull'=>1, 'visible'=>1, 'searchall'=>1],
        'label'  => ['type'=>'varchar(255)','label'=>'Label', 'enabled'=>1, 'position'=>20, 'notnull'=>0, 'visible'=>1, 'searchall'=>1],
        'status' => ['type'=>'smallint',   'label'=>'Status', 'enabled'=>1, 'position'=>500,'notnull'=>1, 'visible'=>1, 'arrayofkeyval'=>[0=>'Draft',1=>'Active',-1=>'Cancelled']],
    ];

    public function __construct(DoliDB $db)
    {
        parent::__construct($db);
    }

    public function create(User $user, int $notrigger = 0): int
    {
        return $this->createCommon($user, $notrigger);
    }

    public function fetch(int $id, string $ref = ''): int
    {
        return $this->fetchCommon($id, $ref);
    }

    public function update(User $user, int $notrigger = 0): int
    {
        return $this->updateCommon($user, $notrigger);
    }

    public function delete(User $user, int $notrigger = 0): int
    {
        return $this->deleteCommon($user, $notrigger);
    }
}
```

---

## 7. Hooks

```php
<?php
class ActionsMonModule
{
    public $results  = [];
    public $resprints = '';
    public $errors   = [];

    /**
     * Surcharge d'un bloc HTML
     */
    public function formObjectOptions(array $parameters, &$object, &$action, HookManager $hookmanager): int
    {
        $error = 0;

        if (in_array('cardpage', explode(':', $parameters['currentcontext']))) {
            $this->resprints = '<div>Mon contenu injecté</div>';
        }

        return ($error ? -1 : 0);   // TOUJOURS retourner 0 ou < 0
    }
}
```

**Règle** : les hooks retournent toujours `0` (succès/pas de remplacement) ou `< 0` (erreur).
Utiliser `$this->resprints` pour injecter du HTML, `$this->results` pour passer des données.

---

## 8. Triggers

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceModMonModuleMonModuleTriggers extends DolibarrTriggers
{
    public function __construct(DoliDB $db)
    {
        parent::__construct($db);
        $this->name        = 'MonModuleTriggers';
        $this->description = 'Triggers du module MonModule';
        $this->version     = '1.0.0';
        $this->picto       = 'monobjet@monmodule';
    }

    public function runTrigger(string $action, &$object, User $user, Translate $langs, Conf $conf): int
    {
        if (!isModEnabled('monmodule')) {
            return 0;
        }

        switch ($action) {
            case 'MONOBJET_CREATE':
                dol_syslog(__METHOD__." action=".$action, LOG_DEBUG);
                // logique métier
                break;
            case 'MONOBJET_MODIFY':
                break;
            case 'MONOBJET_DELETE':
                break;
        }

        return 0;
    }
}
```

---

## 9. Design & UX natif — Standards visuels Dolibarr

> Toute page du module doit être visuellement et fonctionnellement identique à une page native.
> Un utilisateur habitué à Dolibarr ne doit jamais ressentir de rupture d'expérience.

### 9.1 Structure de page — Fiche objet (`{objet}_card.php`)

Ordre strict et non négociable des blocs visuels :

```php
// 1. llxHeader — ouverture HTML, menus, CSS, JS
llxHeader('', $langs->trans("MonObjet"), '', '', 0, 0, array(), array(), '', 'mod-monmodule page-card');
//                                                                                 ↑ classe CSS pour ciblage JS/CSS

// 2. Onglets de la fiche
$head = monobjetPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans("MonObjet"), -1, $object->picto);

// 3. Dialogue de confirmation (suppression, validation…)
if ($action == 'delete') {
    $formconfirm = $form->formconfirm(
        $_SERVER['PHP_SELF'].'?id='.$object->id,
        $langs->trans('DeleteMonObjet'),
        $langs->trans('ConfirmDeleteObject', $object->ref),
        'confirm_delete', '', 0, 1
    );
    print $formconfirm;
}

// 4. Bannière objet (ref, statut, tiers, projet…)
$linkback = '<a href="'.dol_buildpath('/monmodule/monobjet_list.php', 1)
    .'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'
    .$langs->trans("BackToList").'</a>';

$morehtmlref = '<div class="refidno">';
// Tiers lié (si applicable)
if (!empty($object->fk_soc) && isModEnabled('societe')) {
    $soc = new Societe($db);
    $soc->fetch($object->fk_soc);
    $morehtmlref .= $langs->trans('ThirdParty').' : '.$soc->getNomUrl(1);
}
// Projet lié (si applicable)
if (!empty($object->fk_project) && isModEnabled('project')) {
    $proj = new Project($db);
    $proj->fetch($object->fk_project);
    $morehtmlref .= '<br>'.$langs->trans('Project').' : '.$proj->getNomUrl(1);
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

// 5. Corps de la fiche
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">'."\n";
// Champs via showOutputField (vue) ou showInputField (édition)
foreach ($object->fields as $key => $val) {
    if (abs((int) dol_eval((string) $val['visible'], 1)) != 1) continue;
    print '<tr class="field_'.$key.'">';
    print '<td class="titlefield">'.$langs->trans($val['label']).'</td>';
    if ($action == 'edit') {
        print '<td>'.$object->showInputField($val, $key, $object->$key, '', '', '', 0).'</td>';
    } else {
        print '<td>'.$object->showOutputField($val, $key, $object->$key, '', '', '', 0).'</td>';
    }
    print '</tr>';
}
print '</table>';
print '</div>';
print dol_get_fiche_end();

// 6. Boutons d'action (tabsAction)
print '<div class="tabsAction">'."\n";
if ($user->hasRight('monmodule', 'monobjet', 'write') && $object->status == MonObjet::STATUS_DRAFT) {
    print dolGetButtonAction($langs->trans('Validate'), '', 'default',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&token='.newToken(), '', true);
}
if ($user->hasRight('monmodule', 'monobjet', 'write')) {
    print dolGetButtonAction($langs->trans('Modify'), '', 'default',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '', true);
}
if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
    print dolGetButtonAction($langs->trans('Delete'), '', 'delete',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', true);
}
print '</div>';

// 7. Blocs secondaires (notes, contacts, documents, objets liés…)
// → voir §9.4

// 8. llxFooter — fermeture HTML
llxFooter();
$db->close();
```

### 9.2 Boutons d'action — Règles impératives

```php
// Sur une FICHE : dolGetButtonAction() — jamais de <a> brut
print dolGetButtonAction(
    $langs->trans('MyAction'),  // Label bouton
    '',                          // Tooltip (vide = utilise label)
    'default',                   // Style : 'default', 'delete', 'danger', 'info'
    $url,                        // URL ou '#' si désactivé
    '',                          // id HTML (optionnel)
    $enabled                     // true/false — si false, bouton grisé non cliquable
);

// Sur une LISTE : dolGetButtonTitle() — pour le bouton "Nouveau" et les actions de barre
print dolGetButtonTitle(
    $langs->trans('New'),
    '',                          // Tooltip
    'fa fa-plus-circle',         // Classe Font Awesome
    dol_buildpath('/monmodule/monobjet_card.php', 1).'?action=create',
    '',                          // id HTML
    1                            // 1=actif, 0=inactif/grisé, -1=caché
);

// Dans les LISTES (colonne actions) : img_ functions
print '<a href="'.$url_edit.'">'.img_edit().'</a>';
print '<a href="'.$url_delete.'" class="marginleftonly">'.img_delete().'</a>';
```

**Jamais** :
- `<a href="..." class="butAction">` — utiliser `dolGetButtonAction()`
- `<button>` HTML brut — utiliser les helpers Dolibarr
- Boutons sans vérification de droits

### 9.3 Notifications utilisateur

```php
// ✅ Succès
setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');

// ✅ Avertissement
setEventMessages($langs->trans('WarningMessage'), null, 'warnings');

// ✅ Erreur
setEventMessages($object->error, $object->errors, 'errors');

// ✅ Plusieurs messages
setEventMessages($langs->trans('Done'), array($langs->trans('Detail1'), $langs->trans('Detail2')), 'mesgs');
```

**Jamais** `setEventMessage()` (singulier, déprécié v20).

### 9.4 Blocs secondaires — Onglets standards

#### Notes

```php
// Dans prepareHead() — ajouter l'onglet Notes
$head[$h][0] = dol_buildpath('/monmodule/monobjet_note.php', 1).'?id='.$object->id;
$head[$h][1] = $langs->trans('Notes');
if (!empty($object->note_private) || !empty($object->note_public)) {
    $head[$h][1] .= '<span class="badge marginleftonlyshort">!</span>';
}
$head[$h][2] = 'note';
$h++;

// Dans monobjet_note.php — bloc standard
print dol_get_fiche_head($head, 'note', ...);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Note publique
print '<label class="fiscalamount">'.$langs->trans('NotePublic').'</label><br>';
if ($action == 'edit') {
    $doleditor = new DolEditor('note_public', $object->note_public, '', 200, 'dolibarr_notes', '', false, true, 1, ROWS_8, '90%');
    $doleditor->Create();
} else {
    print '<div class="note clearboth">'.dol_htmlentitiesbr($object->note_public).'</div>';
}

// Note privée (masquée aux contacts externes)
if (empty($user->socid)) {
    print '<label class="fiscalamount">'.$langs->trans('NotePrivate').'</label><br>';
    if ($action == 'edit') {
        $doleditor = new DolEditor('note_private', $object->note_private, '', 200, 'dolibarr_notes', '', false, true, 1, ROWS_8, '90%');
        $doleditor->Create();
    } else {
        print '<div class="note clearboth">'.dol_htmlentitiesbr($object->note_private).'</div>';
    }
}
print '</div>';
```

#### Contacts / Intervenants

```php
// Dans prepareHead()
if (isModEnabled('contact')) {
    $nbContact = count($object->liste_contact(-1, 'internal')) + count($object->liste_contact(-1, 'external'));
    $head[$h][0] = dol_buildpath('/monmodule/monobjet_contact.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans('ContactsAddresses');
    if ($nbContact > 0) {
        $head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbContact.'</span>';
    }
    $head[$h][2] = 'contact';
    $h++;
}

// Dans monobjet_contact.php — bloc standard
include DOL_DOCUMENT_ROOT.'/core/tpl/contacts.tpl.php';
```

#### Documents

```php
// Dans prepareHead()
$head[$h][0] = dol_buildpath('/monmodule/monobjet_document.php', 1).'?id='.$object->id;
$head[$h][1] = $langs->trans('Documents');
$filesdirtocount = $conf->monmodule->multidir_output[$conf->entity].'/'.$object->element.'/'.dol_sanitizeFileName($object->ref);
$nbFiles = count(dol_dir_list($filesdirtocount, 'files', 0));
if ($nbFiles > 0) {
    $head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbFiles.'</span>';
}
$head[$h][2] = 'document';
$h++;

// Dans monobjet_document.php — bloc standard
$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
```

#### Objets liés (`fetchObjectLinked`)

```php
// Récupérer les objets liés
$object->fetchObjectLinked();

// Afficher le bloc "Objets liés"
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="5">'.$langs->trans('LinkedObjects').'</td></tr>';

foreach ($object->linkedObjects as $objecttype => $objectarray) {
    foreach ($objectarray as $linkedobject) {
        print '<tr class="oddeven">';
        print '<td>'.$linkedobject->getNomUrl(1).'</td>';
        // ... autres colonnes
        print '</tr>';
    }
}
print '</table>';
print '</div>';

// Ajouter un lien
$object->add_object_linked($objecttype, $objectid);
// Supprimer un lien
$object->deleteObjectLinked($objectid, $objecttype);
```

#### Informations techniques (onglet Info)

```php
// Dans prepareHead()
$head[$h][0] = dol_buildpath('/monmodule/monobjet_info.php', 1).'?id='.$object->id;
$head[$h][1] = $langs->trans('Info');
$head[$h][2] = 'info';
$h++;

// Dans monobjet_info.php
$object->info($object->id);
dol_print_object_info($object, 1);
// Affiche : créé par, créé le, modifié par, modifié le, validé par, validé le
```

### 9.5 Affichage des données — Fonctions natives obligatoires

```php
// Dates — jamais date() PHP ou strftime()
print dol_print_date($object->date_creation, 'day');       // "25/04/2026"
print dol_print_date($object->date_creation, 'dayhour');   // "25/04/2026 14:30"
print dol_print_date($object->date_creation, 'dayhoursec');

// Montants — jamais number_format() PHP
print price($object->amount);                  // "1 234,56 €" formaté selon la devise
print price2num($object->amount, 'MT');        // Arrondi selon param monnaie

// Statut — afficher le badge natif
print $object->getLibStatut(3);               // Badge coloré (1=court, 3=long, 4=long+picto, 5=picto seul)

// Liens objets — jamais d'URL construite manuellement
print $soc->getNomUrl(1);                     // Lien cliquable avec picto
print $proj->getNomUrl(1);
print $user->getNomUrl(1, '', 80, 0, 24);     // Avec photo avatar

// Booléens / Oui-Non
print yn($object->myboolfield);               // "Oui" / "Non" traduit

// Entiers sans HTML
print dol_escape_htmltag((string) $object->qty);   // Échapper avant affichage

// URLs sécurisées
print dol_buildpath('/monmodule/monobjet_card.php', 1).'?id='.$object->id;
```

### 9.6 Pictos et images — Fonctions natives obligatoires

```php
// Picto objet du module
print img_picto('', 'monobjet@monmodule');             // <img> avec chemin auto
print img_picto($langs->trans('Tooltip'), 'object_monobjet@monmodule');

// Pictos actions standards
print img_edit($langs->trans('Modify'), 0);            // Crayon
print img_delete($langs->trans('Delete'), 0);          // Corbeille
print img_view($langs->trans('View'), 0);              // Œil
print img_picto('', 'check', 'class="size15"');        // Coche
print img_warning($langs->trans('Warning'));            // Triangle orange

// Pictos Font Awesome (via img_picto)
print img_picto('', 'fa-download', 'class="size15"');
print img_picto('', 'fa-envelope');

// Pictogramme inline (pour badges, boutons)
print '<span class="fa fa-plus-circle"></span>';       // Dans les dolGetButtonTitle uniquement
```

### 9.7 Classes CSS natives — Standards de mise en forme

#### Structure de tableau

```html
<!-- Liste standard -->
<table class="tagtable nobottomiftotal liste">
<!-- Liste avec filtre avant -->
<table class="tagtable nobottomiftotal liste listwithfilterbefore">
<!-- Tableau de fiche -->
<table class="border centpercent tableforfield">
<!-- Tableau sans bordure -->
<table class="noborder centpercent">
```

#### Lignes et colonnes

```html
<!-- En-tête de liste -->
<tr class="liste_titre">
<!-- Ligne de filtre -->
<tr class="liste_titre_filter">
<!-- Lignes alternées -->
<tr class="oddeven">
<!-- Ligne de total -->
<tr class="liste_total">

<!-- Colonnes standards -->
<td class="titlefield">          <!-- Label dans une fiche -->
<td class="titlefieldcreate">    <!-- Label obligatoire (gras) dans formulaire -->
<td class="nowrap">              <!-- Pas de retour à la ligne -->
<td class="nowraponall">         <!-- Pas de retour à la ligne + overflow hidden -->
<td class="right">               <!-- Aligné à droite (montants) -->
<td class="center">              <!-- Centré (statuts, dates) -->
<td class="tdoverflowmax150">    <!-- Tronqué à 150px avec tooltip -->
<td class="tdoverflowmax250">    <!-- Tronqué à 250px -->
<td class="maxwidthsearch">      <!-- Colonne bouton filtre -->
<td class="linecol">             <!-- Colonne de ligne dans un formulaire de saisie -->
```

#### Champs de formulaire

```html
<!-- Champ standard -->
<input type="text" class="flat maxwidth500" name="label" value="...">
<!-- Champ court -->
<input type="number" class="flat maxwidth100 right" name="qty" value="...">
<!-- Champ pleine largeur -->
<input type="text" class="flat minwidth500 widthcentpercentminusxx" name="desc">
<!-- Zone de texte -->
<textarea class="flat" name="note" rows="4" cols="80"></textarea>
```

### 9.8 Formulaires — Composants natifs obligatoires

```php
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$form = new Form($db);

// Sélecteur de date — jamais <input type="date"> brut
print $form->selectDate($object->date_deadline, 'date_deadline', 0, 0, 0, 'myform', 1, 1);

// Liste déroulante simple
print $form->selectarray('status', array(0=>'Draft', 1=>'Validated'), $object->status, 1);

// Sélecteur utilisateur
print $form->select_dolusers($object->fk_user_assign, 'fk_user_assign', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth200');

// Sélecteur de tiers
print $form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300');

// Sélecteur de pays
print $form->select_country($object->country_code, 'country_code');

// Dialogue de confirmation
$formconfirm = $form->formconfirm(
    $_SERVER['PHP_SELF'].'?id='.$object->id,   // URL de soumission
    $langs->trans('ConfirmTitle'),              // Titre
    $langs->trans('ConfirmQuestion'),           // Question
    'confirm_myaction',                         // Action envoyée si confirmé
    array(                                      // Champs supplémentaires (optionnel)
        array('type'=>'checkbox', 'name'=>'also_delete_linked', 'label'=>$langs->trans('AlsoDeleteLinked'), 'value'=>0),
    ),
    0,   // 0=non, 1=oui par défaut
    1    // 1=formulaire centré
);
print $formconfirm;
```

### 9.9 Workflow objet — Statuts et transitions

Un objet natif Dolibarr suit un cycle de vie strict avec des constantes de statut :

```php
// Dans la classe métier
const STATUS_DRAFT     = 0;
const STATUS_VALIDATED = 1;
const STATUS_SENT      = 2;
const STATUS_CLOSED    = 3;
const STATUS_CANCELLED = 9;

// Libellés de statut (obligatoire)
public function getLibStatut(int $mode = 0): string
{
    return $this->LibStatut($this->status, $mode);
}

public function LibStatut(int $status, int $mode = 0): string
{
    $langs->load('monmodule@monmodule');

    $labelStatus = array(
        self::STATUS_DRAFT     => $langs->transnoentitiesnoconv('Draft'),
        self::STATUS_VALIDATED => $langs->transnoentitiesnoconv('Validated'),
        self::STATUS_SENT      => $langs->transnoentitiesnoconv('Sent'),
        self::STATUS_CANCELLED => $langs->transnoentitiesnoconv('Cancelled'),
    );
    $labelStatusShort = array(
        self::STATUS_DRAFT     => $langs->transnoentitiesnoconv('Draft'),
        self::STATUS_VALIDATED => $langs->transnoentitiesnoconv('Validated'),
        self::STATUS_SENT      => $langs->transnoentitiesnoconv('Sent'),
        self::STATUS_CANCELLED => $langs->transnoentitiesnoconv('Cancelled'),
    );

    $statusType = array(
        self::STATUS_DRAFT     => 'status0',   // Gris
        self::STATUS_VALIDATED => 'status4',   // Bleu
        self::STATUS_SENT      => 'status6',   // Violet
        self::STATUS_CANCELLED => 'status9',   // Rouge
    );

    return dolGetStatus(
        $labelStatus[$status] ?? '',
        $labelStatusShort[$status] ?? '',
        '',
        $statusType[$status] ?? 'status0',
        $mode
    );
}
```

**Classes de statut** (couleurs standards) :

| Classe | Couleur | Usage |
|---|---|---|
| `status0` | Gris | Brouillon |
| `status1` | Vert | Actif / Validé |
| `status4` | Bleu | En cours |
| `status6` | Violet | Envoyé / Transmis |
| `status8` | Orange | En attente |
| `status9` | Rouge | Annulé / Refusé |

### 9.10 JavaScript et AJAX — Règles

```php
// TOUJOURS conditionner le JS à $conf->use_javascript_ajax
if (!empty($conf->use_javascript_ajax)) {
    print '<script type="text/javascript">'."\n";
    print 'jQuery(document).ready(function() {'."\n";
    print '    // code JS'."\n";
    print '});'."\n";
    print '</script>'."\n";
}

// Endpoints AJAX : dans ajax/
// htdocs/custom/monmodule/ajax/monaction.php
// → retourner du JSON via json_encode(), jamais du HTML

// Token CSRF dans les appels AJAX POST
// $.ajax({ data: { token: "'.newToken().'", ... } })
```

### 9.11 Règles HTML strictes

- **HTML standard** — jamais XHTML, jamais `/>` sur les balises non auto-fermantes
- **Attributs en minuscules** entre guillemets doubles : `class="..."` jamais `CLASS='...'`
- **Largeurs fixes interdites** sur les colonnes de tableau — utiliser les classes CSS
- **Popups interdits** sauf tooltips natifs (`dolGetFirstLastname`, `img_picto` avec title)
- **`<br>` sans slash** — `<br>` jamais `<br/>`
- **JavaScript conditionnel** — toujours `if (!empty($conf->use_javascript_ajax))`
- **Liens internes** via `dol_buildpath()` — jamais d'URL relative brute

---

## 10. HTML / Templates (règles complémentaires)

```php
// Liens internes
echo '<a href="'.dol_buildpath('/monmodule/monobjet_card.php', 1).'?id='.$object->id.'">Lien</a>';

// Pictos
echo img_picto('', 'monobjet@monmodule');
echo img_picto('Tooltip', 'edit');

// JavaScript : toujours conditionnel
if (!empty($conf->use_javascript_ajax)) {
    print '<script>/* ... */</script>';
}

// Vérification des droits avant tout affichage/action sensible
if (!$user->hasRight('monmodule', 'monobjet', 'write')) {
    accessforbidden();
}
```

---

## 11. Pages liste (`{objet}_list.php`) — Standards Dolibarr

> **Référence** : `diffusion_list.php` dans [mapiolca/diffusion](https://github.com/mapiolca/diffusion).
> Une page liste Dolibarr suit un ordre de blocs **strict et non négociable**. Tout écart
> rompt la pagination, les filtres persistants, le choix de colonnes ou les mass actions.

### 9.1 Structure globale — Ordre obligatoire

```
1. Includes + chargement des langues
2. Récupération des paramètres GET/POST (action, massaction, pagination, tri, filtres)
3. Initialisation des objets techniques (objet métier, ExtraFields, HookManager)
4. Tri par défaut (si non défini)
5. Initialisation du tableau $search[] depuis $object->fields
6. Construction de $arrayfields (choix de colonnes)
7. Vérification des droits
8. === BLOC ACTIONS ===
   a. Gestion du cancel
   b. Hook doActions
   c. include actions_changeselectedfields.inc.php
   d. Purge des filtres (button_removefilter)
   e. include actions_massactions.inc.php
9. === BLOC VUE ===
   a. Construction et exécution du SELECT SQL
   b. Comptage pour la pagination ($nbtotalofrecords)
   c. Ajout ORDER BY + LIMIT/OFFSET
   d. llxHeader()
   e. print_barre_liste() — titre + pagination + bouton créer
   f. Formulaire de recherche globale + boutons filtre
   g. En-têtes de colonnes triables (getTitleFieldOfList)
   h. Ligne de filtres (ligne <tr> de recherche)
   i. Boucle sur les résultats
   j. Ligne de totaux (optionnel)
   k. Pied du tableau (mass actions + pagination basse)
   l. llxFooter()
```

### 9.2 Paramètres standards à initialiser

```php
// Paramètres de navigation et de masse
$action      = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$massaction  = GETPOST('massaction', 'alpha');
$show_files  = GETPOSTINT('show_files');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'alpha');
$toselect    = GETPOST('toselect', 'array:int');   // IDs cochés pour les mass actions
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ')
    : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php'));
$backtopage  = GETPOST('backtopage', 'alpha');
$optioncss   = GETPOST('optioncss', 'aZ');
$mode        = GETPOST('mode', 'aZ');              // 'list', 'kanban', etc.

// Pagination
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0
    || GETPOST('button_search', 'alpha')
    || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;  // Reset si nouveau filtre ou effacement
}
$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
```

### 9.3 Tri par défaut

```php
// APRÈS avoir chargé $object->fields et AVANT les filtres
if (!$sortfield) {
    reset($object->fields);          // Obligatoire pour que key() retourne le 1er élément
    $sortfield = "t.".key($object->fields);   // 1er champ défini = tri par défaut
}
if (!$sortorder) {
    $sortorder = "ASC";
}
```

### 9.4 Initialisation des filtres

```php
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search     = array();

foreach ($object->fields as $key => $val) {
    if (GETPOST('search_'.$key, 'alpha') !== '') {
        $search[$key] = GETPOST('search_'.$key, 'alpha');
    }
    // Pour les champs date : plage dtstart / dtend
    if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
        $search[$key.'_dtstart'] = dol_mktime(0,  0,  0,
            GETPOSTINT('search_'.$key.'_dtstartmonth'),
            GETPOSTINT('search_'.$key.'_dtstartday'),
            GETPOSTINT('search_'.$key.'_dtstartyear'));
        $search[$key.'_dtend'] = dol_mktime(23, 59, 59,
            GETPOSTINT('search_'.$key.'_dtendmonth'),
            GETPOSTINT('search_'.$key.'_dtendday'),
            GETPOSTINT('search_'.$key.'_dtendyear'));
    }
}
```

### 9.5 Construction de `$arrayfields` (choix de colonnes)

```php
$tableprefix = 't';
$arrayfields = array();

foreach ($object->fields as $key => $val) {
    if (!empty($val['visible'])) {
        $visible = (int) dol_eval((string) $val['visible'], 1);
        $arrayfields[$tableprefix.'.'.$key] = array(
            'label'    => $val['label'],
            'checked'  => (($visible < 0) ? 0 : 1),    // < 0 = décoché par défaut
            'enabled'  => (abs($visible) != 3 && (bool) dol_eval($val['enabled'], 1)),
            'position' => $val['position'],
            'help'     => isset($val['help']) ? $val['help'] : '',
        );
    }
}

// Ajouter les ExtraFields dans $arrayfields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

// Trier $object->fields et $arrayfields par position
$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields    = dol_sort_array($arrayfields, 'position');
```

**Valeurs de `visible` dans `$fields`** :

| Valeur | Signification                                      |
|--------|----------------------------------------------------|
| `1`    | Colonne affichée et cochée par défaut              |
| `-1`   | Colonne disponible mais décochée par défaut        |
| `2`    | Colonne affichée en vue liste et fiche             |
| `3`    | Formulaire seulement (jamais en liste)             |
| `0`    | Jamais affiché                                     |

### 9.6 Bloc Actions (contrôleur)

```php
// 1. Annulation de mass action
if (GETPOST('cancel', 'alpha')) {
    $action = 'list';
    $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
    $massaction = '';
}

// 2. Hook doActions
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // 3. Sauvegarde du choix de colonnes en session
    include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

    // 4. Purge des filtres
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
        foreach ($object->fields as $key => $val) {
            $search[$key] = '';
            if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
                $search[$key.'_dtstart'] = '';
                $search[$key.'_dtend']   = '';
            }
        }
        $search_all          = '';
        $toselect            = array();
        $search_array_options = array();
    }

    // Reset massaction si nouvelle recherche
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
        || GETPOST('button_search_x', 'alpha')    || GETPOST('button_search.x', 'alpha')    || GETPOST('button_search', 'alpha')) {
        $massaction = '';
    }

    // 5. Mass actions (delete, generate PDF, send mail, export…)
    $objectclass = 'MonObjet';                        // Nom exact de la classe PHP
    $objectlabel = 'MonObjet';                        // Clé de traduction
    $uploaddir   = $conf->monmodule->multidir_output[$conf->entity];
    include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}
```

### 9.7 Construction de la requête SQL

```php
// SELECT
$sql  = "SELECT";
$sql .= " ".$object->getFieldList('t');   // Génère "t.rowid, t.ref, t.label, ..."

// Champs ExtraFields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
    foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
        $sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate'
            ? ", ef.".$key." as options_".$key : "");
    }
}

// Hook SELECT
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

$sqlfields = $sql;   // Sauvegarde du SELECT pour le COUNT (sans FROM)

// FROM
$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";

// JOIN ExtraFields
if (isset($extrafields->attributes[$object->table_element]['label'])
    && is_array($extrafields->attributes[$object->table_element]['label'])
    && count($extrafields->attributes[$object->table_element]['label'])) {
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef ON (t.rowid = ef.fk_object)";
}

// Hook FROM (JOIN supplémentaires)
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

// WHERE — entité (TOUJOURS en premier)
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";

// WHERE — filtres de recherche
foreach ($search as $key => $val) {
    if (array_key_exists($key, $object->fields)) {
        if ($key == 'status' && $search[$key] == -1) continue;

        $mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);

        if (strpos($object->fields[$key]['type'], 'integer:') === 0
            || strpos($object->fields[$key]['type'], 'sellist:') === 0
            || !empty($object->fields[$key]['arrayofkeyval'])) {
            if ($search[$key] == '-1' || $search[$key] === '0') $search[$key] = '';
            $mode_search = 2;
        }

        if ($search[$key] != '') {
            $sql .= natural_search("t.".$db->escape($key), $search[$key],
                (($key == 'status') ? 2 : $mode_search));
        }
    } else {
        // Dates (plage dtstart / dtend)
        if (preg_match('/(\_dtstart|\_dtend)$/', $key) && $search[$key] != '') {
            $columnName = preg_replace('/(\_dtstart|\_dtend)$/', '', $key);
            if (preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
                $sql .= preg_match('/_dtstart$/', $key)
                    ? " AND t.".$db->sanitize($columnName)." >= '".$db->idate($search[$key])."'"
                    : " AND t.".$db->sanitize($columnName)." <= '".$db->idate($search[$key])."'";
            }
        }
    }
}

// Recherche globale (search_all)
if ($search_all) {
    $sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}

// Hook WHERE
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

// ExtraFields WHERE
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
```

### 9.8 Comptage et pagination

```php
// COUNT pour la pagination (remplacer le SELECT par COUNT)
$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nb', $sql);
$resql = $db->query($sqlforcount);
if ($resql) {
    $objcount = $db->fetch_object($resql);
    $nbtotalofrecords = $objcount->nb;
    $db->free($resql);
}

// Correction de $page si dépassement
if (($page * $limit) > $nbtotalofrecords) {
    $page   = 0;
    $offset = 0;
}

// ORDER BY + LIMIT
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);   // +1 pour détecter s'il y a une page suivante

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}
$num = $db->num_rows($resql);
```

### 9.9 Rendu visuel

```php
// --- En-tête de page
$title = $langs->trans("ListOfMonObjets");
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'bodyforlist');

// --- Barre de titre avec pagination haute et bouton "Créer"
$newcardbutton = '';
if ($user->hasRight('monmodule', 'monobjet', 'write')) {
    $newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/monmodule/monobjet_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']));
}

print_barre_liste(
    $title,                     // Titre
    $page,                      // Page courante
    $_SERVER["PHP_SELF"],       // URL de la page
    $param,                     // Paramètres URL (filtres actifs)
    $sortfield,                 // Champ de tri
    $sortorder,                 // Ordre de tri
    '',                         // Moyen de tri central (vide)
    $num,                       // Nombre de résultats (pour "suivant")
    $nbtotalofrecords,          // Total de résultats
    'object_monobjet@monmodule',// Picto
    0,                          // Affichage complet (0 = oui)
    $newcardbutton,             // Bouton droit (Nouveau)
    '',                         // Bouton gauche
    $limit,                     // Limite par page
    0,                          // Pas de pagination directe
    0                           // Pas de "hide_numero"
);

// --- Formulaire principal (encapsule filtres + tableau + mass actions)
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

// --- Début du tableau
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// --- Ligne des filtres (avant les en-têtes)
print '<tr class="liste_titre_filter">';
foreach ($object->fields as $key => $val) {
    if (!empty($arrayfields['t.'.$key]['checked'])) {
        if ($val['type'] == 'date' || preg_match('/^(timestamp|datetime)/', $val['type'])) {
            // Champ date : sélecteur de plage
            print '<td class="liste_titre center">';
            print $form->selectDate($search[$key.'_dtstart'] ? $search[$key.'_dtstart'] : '', 'search_'.$key.'_dtstart', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans("From"));
            print $form->selectDate($search[$key.'_dtend']   ? $search[$key.'_dtend']   : '', 'search_'.$key.'_dtend',   0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans("To"));
            print '</td>';
        } elseif (!empty($val['arrayofkeyval'])) {
            // Champ avec valeurs fixes : liste déroulante
            print '<td class="liste_titre">';
            print $form->selectarray('search_'.$key, $val['arrayofkeyval'], $search[$key], 1);
            print '</td>';
        } else {
            // Champ texte / numérique
            print '<td class="liste_titre">';
            print '<input type="search" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag($search[$key]).'">';
            print '</td>';
        }
    }
}
// Colonne actions (sans filtre)
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

// --- En-têtes de colonnes triables
print '<tr class="liste_titre">';
foreach ($object->fields as $key => $val) {
    if (!empty($arrayfields['t.'.$key]['checked'])) {
        print getTitleFieldOfList(
            $arrayfields['t.'.$key]['label'],  // Label (clé de traduction)
            0,                                  // Pas de picto
            $_SERVER['PHP_SELF'],               // URL
            't.'.$key,                          // Champ SQL pour le tri
            '',                                 // Prefix
            $param,                             // Paramètres URL actifs
            '',                                 // Suffix
            $sortfield,                         // Tri actuel
            $sortorder,                         // Ordre actuel
            ''                                  // Classe CSS
        )."\n";
    }
}
// Colonne sélection de colonnes (toujours en dernier)
print getTitleFieldOfList($selectedfields, 0, $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>';

// --- Boucle sur les résultats
$i = 0;
$totalarray = array();
$totalarray['nbfield'] = 0;

while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (empty($obj)) break;

    $object->setVarsFromFetchObj($obj);

    print '<tr class="oddeven">';

    foreach ($object->fields as $key => $val) {
        if (!empty($arrayfields['t.'.$key]['checked'])) {
            print '<td>';
            print $object->showOutputField($val, $key, $obj->$key, '', '', '', 0);
            print '</td>';
            if (!empty($val['isameasure'])) {
                $totalarray['val']['t.'.$key] = ($totalarray['val']['t.'.$key] ?? 0) + $obj->$key;
            }
        }
    }

    // Colonne picto actions (modifier / supprimer)
    print '<td class="nowraponall">';
    if ($user->hasRight('monmodule', 'monobjet', 'write')) {
        print '<a href="'.dol_buildpath('/monmodule/monobjet_card.php', 1).'?id='.$object->id.'&action=edit&token='.newToken().'&backtopage='.urlencode($_SERVER['PHP_SELF']).'">';
        print img_edit();
        print '</a> ';
    }
    if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
        print '<a href="'.dol_buildpath('/monmodule/monobjet_card.php', 1).'?id='.$object->id.'&action=delete&token='.newToken().'&backtopage='.urlencode($_SERVER['PHP_SELF']).'">';
        print img_delete();
        print '</a>';
    }
    print '</td>';

    print '</tr>';
    $i++;
}

// --- Ligne de totaux (si mesures définies)
// include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';

// --- Pied de tableau : mass actions + pagination basse
print '</table>';
print '</div>';

// Sélection de colonnes (picto engrenage)
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<a href="'.dol_buildpath('/monmodule/monobjet_card.php', 1).'?action=create"></a>';
print '</div></div>';

// Mass actions (bas de page)
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($massaction == 'delete') {
    $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('ConfirmMassDelete'),
        $langs->trans('ConfirmMassDeleteQuestion', count($toselect)), 'confirm_delete', null, 0, 1);
    print $formconfirm;
}

// Hook pour mass actions personnalisées
$parameters = array('arrayfields' => $arrayfields, 'array_selectedfields' => $selectedfields);
$reshook = $hookmanager->executeHooks('addMoreMassActions', $parameters, $object, $action);

include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_post.tpl.php';

print '</form>';

llxFooter();
$db->close();
```

### 9.10 Paramètre `$param` — Construction des paramètres URL persistants

Le paramètre `$param` doit contenir **tous les filtres actifs** pour que la pagination et
les liens de tri les conservent :

```php
$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
    $param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
    $param .= '&limit='.((int) $limit);
}
if ($optioncss != '') {
    $param .= '&optioncss='.urlencode($optioncss);
}
// Ajouter tous les filtres actifs
foreach ($search as $key => $val) {
    if (is_array($val) && count($val)) {
        foreach ($val as $skey => $sval) {
            $param .= '&search_'.$key.'[]='.urlencode($sval);
        }
    } elseif (trim($val) != '') {
        $param .= '&search_'.$key.'='.urlencode($val);
    }
}
// ExtraFields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
// Hook
$parameters = array('param' => &$param);
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action);
$param .= $hookmanager->resPrint;
```

### 9.11 Mass actions disponibles (via `actions_massactions.inc.php`)

Le fichier `actions_massactions.inc.php` gère automatiquement les actions suivantes si
`$objectclass` et `$objectlabel` sont définis :

| Action          | Condition requise                                  |
|-----------------|----------------------------------------------------|
| `delete`        | `$permissiontodelete`                              |
| `generate_doc`  | Modèles PDF définis dans le descripteur            |
| `presend`       | Module mail activé                                 |
| `close`         | Méthode `close()` présente dans la classe          |
| `reopen`        | Méthode `reopen()` présente dans la classe         |

Pour ajouter des mass actions personnalisées :

```php
// APRÈS l'include actions_massactions.inc.php, dans le bloc if (empty($reshook)) :
if ($action == 'myaction' && $permissiontoadd) {
    foreach ($toselect as $toselectid) {
        $objecttmp = new MonObjet($db);
        if ($objecttmp->fetch($toselectid) > 0) {
            $result = $objecttmp->myMethod($user);
            if ($result < 0) {
                setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
            }
        }
    }
}
```

### 9.12 Règles et pièges

- **Ne jamais** écrire de SQL sans `getEntity()` dans le WHERE — risque de fuite inter-entités
- **`$sortfield` par défaut** : toujours initialiser via `reset()` + `key()` sur `$object->fields`
- **`natural_search()`** : utiliser cette fonction pour les filtres — jamais de `LIKE '%'.$val.'%'` manuel
- **`$sqlfields`** : sauvegarder le SELECT avant le FROM pour le COUNT (évite de réécrire la requête)
- **`+1` dans `plimit`** : toujours `$limit + 1` pour détecter la page suivante sans COUNT supplémentaire
- **`actions_changeselectedfields.inc.php`** : doit être inclus **avant** la purge des filtres, jamais après
- **`actions_massactions.inc.php`** : nécessite `$objectclass`, `$objectlabel` et `$uploaddir` définis
- **Formulaire unique** : tout le tableau doit être dans un seul `<form>` — les cases à cocher `toselect[]` et les mass actions partagent ce formulaire
- **Lignes d'extrafields** : toujours inclure les trois templates `extrafields_list_array_fields`, `extrafields_list_search_sql`, `extrafields_list_search_param`
- **`$db->plimit()`** : jamais de `LIMIT` SQL brut — utiliser `$db->plimit($limit + 1, $offset)`

---

## 12. API REST

```php
<?php
/**
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class MonObjets extends DolibarrApi
{
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Récupère un objet par son ID
     *
     * @param int $id ID de l'objet
     * @return array Données de l'objet
     *
     * @url GET /monobjets/{id}
     */
    public function get(int $id): array
    {
        if (!DolibarrApiAccess::$user->hasRight('monmodule', 'monobjet', 'read')) {
            throw new RestException(403);
        }
        $obj = new MonObjet($this->db);
        if ($obj->fetch($id) <= 0) {
            throw new RestException(404, 'Objet non trouvé');
        }
        return $this->_cleanObjectDatas($obj);
    }
}
```

---

## 13. Traductions

```ini
; langs/fr_FR/monmodule.lang
MyModuleTitle = Mon Module
MyObject      = Mon Objet
MyObjects     = Mes Objets
ErrorNotFound = Objet introuvable
```

- Fichiers obligatoires : `fr_FR` et `en_US` au minimum
- Chargement : `$langs->load('monmodule@monmodule');`
- Usage : `$langs->trans('MyKey')` ou `$langs->transnoentitiesnoconv('MyKey')`

---

## 14. Sécurité — Checklist obligatoire

Avant chaque écriture ou lecture :

```php
// 1. Toujours vérifier les droits
if (!$user->hasRight('monmodule', 'monobjet', 'write')) {
    accessforbidden();
}

// 2. Token anti-CSRF sur les formulaires POST
print '<input type="hidden" name="token" value="'.newToken().'">';
// Vérification à la réception :
checkToken();

// 3. Échapper toutes les valeurs dans les requêtes SQL
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
$sql .= " WHERE ref = '".$db->escape($ref)."'";
$sql .= " AND entity = ".$conf->entity;

// 4. XSS : échapper l'output HTML
echo dol_escape_htmltag($object->label);
echo dol_htmlentities($object->note_public);
```

---

## 15. Intégration MultiCompany — Qualité module natif

> **Objectif** : l'intégration MultiCompany doit être **indiscernable d'un module core Dolibarr**.
> Un utilisateur ou un intégrateur ne doit jamais remarquer que le module est externe.
> Version cible minimum : **Dolibarr v20.0** — voir §2 pour le protocole de compatibilité.

---

### 15.1 Philosophie "module natif"

Un module natif Dolibarr respecte **tous** ces points sans exception :

| Critère                              | Implémentation requise                                       |
|--------------------------------------|--------------------------------------------------------------|
| Filtrage entité dans TOUTES les listes | `getEntity('monobjet')` dans chaque WHERE SQL              |
| Champ `entity` dans toutes les tables  | `entity integer DEFAULT 1 NOT NULL`                        |
| Objet conscient de l'entité          | `$this->ismultientitymanaged = 1`                           |
| Fichiers isolés par entité           | `$conf->monmodule->multidir_output[$conf->entity]`          |
| Constantes isolées par entité        | `dolibarr_set_const(..., $conf->entity)`                    |
| ExtraFields multi-entité             | `$this->isextrafieldmanaged = 1`                            |
| Partages déclarés à MultiCompany     | `MULTICOMPANY_EXTERNAL_MODULES_SHARING` dans `init()`       |
| Clonage inter-entités                | Méthode `createFromClone()` respectant `$conf->entity`      |
| Recherche globale filtrée            | `getEntity()` dans les requêtes `searchall`                 |

---

### 15.2 Tables SQL — Champs obligatoires

Toute table principale **doit** contenir le champ `entity` et être filtrée par `getEntity()`.

```sql
-- llx_monmodule_monobjet.sql
CREATE TABLE llx_monmodule_monobjet (
  rowid          integer      NOT NULL AUTO_INCREMENT,
  ref            varchar(30)  NOT NULL,
  entity         integer      DEFAULT 1 NOT NULL,    -- ← OBLIGATOIRE, jamais nullable
  -- ... autres champs ...
  status         smallint     DEFAULT 0
) ENGINE=InnoDB;

-- llx_monmodule_monobjet.key.sql
ALTER TABLE llx_monmodule_monobjet ADD PRIMARY KEY (rowid);
ALTER TABLE llx_monmodule_monobjet ADD UNIQUE INDEX uk_llx_monmodule_monobjet_ref (ref, entity);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_llx_monmodule_monobjet_entity (entity);
```

> L'index sur `entity` est **obligatoire** sur toutes les tables soumises à `getEntity()`.
> La contrainte unique `(ref, entity)` permet d'avoir la même ref dans des entités différentes.

---

### 15.3 Classe métier — Configuration multi-entité

```php
class MonObjet extends CommonObject
{
    public $ismultientitymanaged = 1;   // 1 = filtré par entity, 0 = global, -1 = partagé
    public $isextrafieldmanaged  = 1;   // ExtraFields par entité

    // Propriété entity exposée (héritée de CommonObject mais à déclarer dans $fields)
    public $fields = [
        'entity' => [
            'type'     => 'integer',
            'label'    => 'Entity',
            'enabled'  => 1,
            'visible'  => 0,           // Invisible en UI mais présent en SQL
            'notnull'  => 1,
            'default'  => 1,
            'position' => 5,
            'index'    => 1,
        ],
        // ... autres champs
    ];

    public function __construct(DoliDB $db)
    {
        parent::__construct($db);

        // Désactiver le champ entity en interface si multicompany désactivé
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }
    }
}
```

**Valeurs de `$ismultientitymanaged`** :

| Valeur | Comportement                                                            |
|--------|-------------------------------------------------------------------------|
| `1`    | Objet appartient à une entité précise — le plus courant                 |
| `0`    | Objet global, visible de toutes les entités (ex: types de paiement)    |
| `-1`   | Géré manuellement — la classe implémente sa propre logique de partage  |

---

### 15.4 `getEntity()` — Règle d'or dans tous les SQL

**Aucune requête SQL sur une table multi-entité ne doit exister sans `getEntity()`.**

```php
// ✅ CORRECT — toujours utiliser getEntity()
$sql  = "SELECT t.rowid, t.ref, t.label";
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet as t";
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";

// ❌ INTERDIT — filtrage manuel de l'entité
$sql .= " WHERE t.entity = ".$conf->entity;

// ❌ INTERDIT — aucun filtrage entité
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet as t";
// (sans WHERE entity)
```

**Signature de `getEntity()`** :

```php
getEntity(string $element, int $shared = 1, object $currentobject = null): string
// $element  : nom de l'élément (correspond à la clé dans MULTICOMPANY_EXTERNAL_MODULES_SHARING)
// $shared   : 1 = inclure les entités partagées, 0 = entité courante uniquement
// Retourne  : chaîne SQL ex: "1,3,0" à injecter dans IN(...)
```

Cas d'usage selon le contexte :

```php
// Liste standard (inclut les partages)
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";

// Création (entité courante uniquement)
$this->entity = $conf->entity;

// Vérification d'appartenance stricte
$sql .= " WHERE t.entity = ".((int) $conf->entity);

// Recherche avec option entité courante seulement (bouton dans list.php)
$shared = GETPOSTINT('search_current_entity') ? 0 : 1;
$sql .= " WHERE t.entity IN (".getEntity('monobjet', $shared).")";
```

---

### 15.5 Stockage de fichiers par entité

```php
// Dans modMonModule.class.php — __construct()
// Répertoires de données (un par entité)
$this->dirs = array("/monmodule/temp");

// Dans init() — créer le répertoire pour l'entité courante
public function init(string $options = ''): int
{
    global $conf;

    // Initialiser multidir_output si absent (pattern natif)
    if (!isset($conf->monmodule) || !is_object($conf->monmodule)) {
        $conf->monmodule = new stdClass();
    }
    if (empty($conf->monmodule->multidir_output) || !is_array($conf->monmodule->multidir_output)) {
        $conf->monmodule->multidir_output = array();
    }
    if (empty($conf->monmodule->multidir_output[$conf->entity])) {
        $conf->monmodule->multidir_output[$conf->entity] =
            DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/monmodule';
    }

    // ... reste de init()
    return $this->_init($sql, $options);
}
```

Utilisation dans les pages :

```php
// Chemin de stockage pour l'entité courante
$upload_dir = $conf->monmodule->multidir_output[$conf->entity].'/'.$object->element.'/'.dol_sanitizeFileName($object->ref);

// Répertoire temporaire pour les mass actions
$diroutputmassaction = $conf->monmodule->multidir_output[$conf->entity].'/temp/massgeneration/'.$user->id;
```

---

### 15.6 Constantes par entité

Les constantes du module doivent être **isolées par entité** (`entity` = `$conf->entity`, pas `0`).

```php
// Dans init() — constantes spécifiques à l'entité
dolibarr_set_const($this->db, 'MONMODULE_MYPARAM', '1', 'chaine', 0, '', $conf->entity);
//                                                                         ↑ entity, pas 0

// Dans data.sql — utiliser __ENTITY__ comme placeholder
DELETE FROM llx_const WHERE name='MONMODULE_MYPARAM' AND entity='__ENTITY__';
INSERT INTO llx_const (name, value, type, visible, entity)
  VALUES ('MONMODULE_MYPARAM', '1', 'chaine', 1, '__ENTITY__');

// Lecture moderne (v15+, cible v20)
$value = getDolGlobalString('MONMODULE_MYPARAM');  // Lit $conf->global->MONMODULE_MYPARAM
```

**Constantes globales** (entity = 0) uniquement pour les paramètres vraiment transversaux
(ex : URL de service externe partagée par toutes les entités).

---

### 15.7 Colonne entité dans list.php

Afficher la colonne "Entité" uniquement quand le partage est actif et pertinent :

```php
// Dans {objet}_list.php — après initialisation de $object
$showentitycolumn = false;
if (isModEnabled('multicompany')) {
    $sharedentities     = getEntity('monobjet', 1);
    $currententityonly  = getEntity('monobjet', 0);
    $partagemonobjetactif = ($sharedentities !== $currententityonly);

    $sharedentityids = array_filter(array_map('intval', explode(',', (string) $sharedentities)));
    $receivesshared  = (in_array((int) $conf->entity, $sharedentityids, true)
                        && count(array_diff($sharedentityids, array((int) $conf->entity))) > 0);

    $showentitycolumn = ($partagemonobjetactif && $receivesshared);
}

// Dans le SELECT SQL
if ($showentitycolumn) {
    $sql .= ", t.entity as entity, e.label as entity_label";
}

// Dans le FROM SQL
if ($showentitycolumn) {
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON (e.rowid = t.entity)";
}

// Dans $arrayfields
if ($showentitycolumn) {
    $arrayfields['t.entity'] = array(
        'label'    => $langs->trans('Entity'),
        'checked'  => 1,
        'enabled'  => 1,
        'position' => 1,      // Après le picto, avant la ref
    );
}

// Dans la boucle de rendu
if ($showentitycolumn && !empty($arrayfields['t.entity']['checked'])) {
    print '<td class="tdoverflowmax150">';
    print dol_escape_htmltag($obj->entity_label);
    print '</td>';
}
```

---

### 15.8 Partages déclarés — `MULTICOMPANY_EXTERNAL_MODULES_SHARING`

#### Structure complète de `$params`

```php
$params = array(
    'monmodule' => array(                           // Clé unique, stable, = nom du module

        'sharingelements' => array(                 // Partages 'element' et 'object'

            // --- Partage principal (element) ---
            'monobjet' => array(                    // Valeur passée à getEntity()
                'type'    => 'element',
                'icon'    => 'my-icon',             // Icône Font Awesome (sans le préfixe fa-)
                'lang'    => 'monmodule@monmodule', // Fichier de langue
                // 'tooltip' => 'MyTooltipKey',     // Omettre si pas de tooltip
                'input'   => array(
                    'global' => array(
                        'showhide' => true,         // Affiche/cache selon partage global
                        'hide'     => true,         // Cache si partage global désactivé
                        'del'      => true,         // Supprime la constante si désactivé
                    ),
                ),
            ),

            // --- Partage dépendant d'un partage parent ---
            'monobjetdetail' => array(
                'type'    => 'element',
                'icon'    => 'list',
                'lang'    => 'monmodule@monmodule',
                'enable'  => '! empty($conf->monmodule->enabled)',  // Expression PHP (string)
                'display' => '! empty($conf->global->MULTICOMPANY_MONOBJET_SHARING_ENABLED)',
                'input'   => array(
                    'global' => array(
                        'hide' => true,
                        'del'  => true,
                    ),
                    'monobjet' => array(            // Réaction au partage parent
                        'showhide' => true,
                        'del'      => true,
                    ),
                ),
                // 'disable' => true,              // Décommenter si en dev
            ),

            // --- Partage d'objet lié à un partage principal ---
            'monobjetdoc' => array(
                'type'      => 'object',
                'icon'      => 'file-pdf-o',
                'lang'      => 'monmodule@monmodule',
                'mandatory' => 'monobjet',          // Partage principal requis
                'enable'    => '! empty($conf->monmodule->enabled)',
                'display'   => '! empty($conf->global->MULTICOMPANY_MONOBJET_SHARING_ENABLED)',
                'input'     => array(
                    'global' => array(
                        'hide' => true,
                        'del'  => true,
                    ),
                    'monobjet' => array(
                        'showhide' => true,
                        'hide'     => true,
                        'del'      => true,
                    ),
                ),
            ),
        ),

        'sharingmodulename' => array(               // Correspondance partage → module Dolibarr
            'monobjet'       => 'monmodule',
            'monobjetdetail' => 'monmodule',
            'monobjetdoc'    => 'monmodule',
        ),

        'addzero' => array(                         // Partages qui ajoutent entity=0 (tous)
            // 'monobjet',                          // Décommenter si les objets partagés
        ),                                          // doivent inclure entity=0

        'dictionary' => array(                      // Dictionnaires par entité
            'c_monmodule_mydict' => array(
                'type'     => 'dictionary',
                'icon'     => 'book',
                'transkey' => 'MyDictionaryLabel',
                'lang'     => 'monmodule@monmodule',
                'filepath' => '/monmodule/sql/init/llx_c_monmodule_mydict.sql',
            ),
        ),
    ),
);
```

#### Référence complète des clés

| Clé        | Oblig.     | Défaut                                                  | Description                                           |
|------------|------------|---------------------------------------------------------|-------------------------------------------------------|
| `type`     | ✅          | —                                                       | `element`, `object` ou `dictionary`                  |
| `icon`     | ✅          | —                                                       | Icône Font Awesome (sans préfixe `fa-`)              |
| `lang`     | ✅          | —                                                       | `fichier@module`                                     |
| `transkey` | ✅ (dict.) | —                                                       | Clé de traduction (type `dictionary` uniquement)     |
| `filepath` | ✅ (dict.) | —                                                       | Chemin SQL du dictionnaire                           |
| `tooltip`  | ❌          | *(aucun)*                                               | Clé de traduction — **omettre** si pas de tooltip    |
| `enable`   | ❌          | `! empty($conf->mymodule->enabled)`                    | Expression PHP string évaluée par MultiCompany       |
| `display`  | ❌          | `! empty($conf->global->MULTICOMPANY_SHARINGS_ENABLED)`| Expression PHP string — condition d'affichage        |
| `mandatory`| ❌          | —                                                       | Nom du partage parent requis (type `object`)         |
| `disable`  | ❌          | `false`                                                 | `true` = neutralise le partage (dev en cours)        |
| `input`    | ❌          | *(aucune réaction)*                                     | Comportement du toggle selon le contexte             |

**Clés `input`** (par contexte `global` ou nom du partage parent) :

| Clé        | Description                                                        |
|------------|--------------------------------------------------------------------|
| `showhide` | Affiche/cache le bloc selon l'état du contexte                     |
| `hide`     | Cache le bloc à la désactivation                                   |
| `del`      | Supprime la constante de partage à la désactivation                |

---

### 15.9 Enregistrement dans le descripteur — `init()` et `remove()`

```php
// modMonModule.class.php

public function init(string $options = ''): int
{
    global $conf;

    $sql = array();

    // --- Enregistrement MultiCompany ---
    if (isModEnabled('multicompany')) {
        $params = array( /* ... structure §13.8 ... */ );

        $existing = array();
        if (!empty($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING)) {
            $decoded = json_decode($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        // array_merge : nos clés écrasent l'ancienne version de notre module
        $existing = array_merge($existing, $params);
        dolibarr_set_const(
            $this->db,
            'MULTICOMPANY_EXTERNAL_MODULES_SHARING',
            json_encode($existing),
            'chaine',
            0,   // visible = 0 (non affiché dans la liste des constantes)
            '',  // note
            0    // entity = 0 (globale — MultiCompany lit cette constante globalement)
        );
    }

    return $this->_init($sql, $options);
}

public function remove(string $options = ''): int
{
    global $conf;

    $sql = array();

    // --- Désenregistrement MultiCompany ---
    if (isModEnabled('multicompany') && !empty($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING)) {
        $existing = json_decode($conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING, true);
        if (is_array($existing) && array_key_exists('monmodule', $existing)) {
            unset($existing['monmodule']);
            dolibarr_set_const(
                $this->db,
                'MULTICOMPANY_EXTERNAL_MODULES_SHARING',
                json_encode($existing),
                'chaine', 0, '', 0
            );
        }
    }

    return $this->_remove($sql, $options);
}
```

> ⚠️ La constante `MULTICOMPANY_EXTERNAL_MODULES_SHARING` est **globale** (`entity = 0`).
> Ne jamais passer `$conf->entity` ici — MultiCompany la lit une seule fois pour toutes les entités.

---

### 15.10 Page d'administration multi-entité

La page `admin/setup.php` doit gérer le contexte multi-entité nativement :

```php
// admin/setup.php
global $conf, $user, $langs, $db;

// Superadmin : peut voir/modifier toutes les entités
// Admin entité : ne voit que les constantes de son entité
$entity = $conf->entity;

// Afficher le sélecteur d'entité pour le superadmin (pattern natif)
if (!empty($user->admin) && isModEnabled('multicompany')) {
    // Lire l'entité demandée dans l'URL
    $entity = GETPOSTINT('entity') ? GETPOSTINT('entity') : $conf->entity;
}

// Lire une constante propre à l'entité
$myparam = getDolGlobalString('MONMODULE_MYPARAM');   // Lit depuis $conf->entity

// Sauvegarder une constante propre à l'entité
if ($action == 'update') {
    $myparam = GETPOST('MONMODULE_MYPARAM', 'alphanohtml');
    dolibarr_set_const($db, 'MONMODULE_MYPARAM', $myparam, 'chaine', 0, '', $conf->entity);
    // ↑ $conf->entity, pas 0 — constante propre à l'entité
}
```

---

### 15.11 Vérification de compatibilité — Checklist version v20

Avant de livrer le code, l'agent **doit vérifier** chacun de ces points :

```php
// ✅ v20 : isModEnabled() — jamais !empty($conf->xxx->enabled)
isModEnabled('monmodule')

// ✅ v20 : hasRight() — jamais $user->rights->xxx
$user->hasRight('monmodule', 'monobjet', 'read')

// ✅ v20 : getDolGlobalString() / getDolGlobalInt() — jamais $conf->global->XXX direct
getDolGlobalString('MONMODULE_MYPARAM')
getDolGlobalInt('MONMODULE_COUNTER')

// ✅ v20 : GETPOSTINT() — jamais (int)GETPOST('x','int')
GETPOSTINT('id')

// ✅ v20 : dol_include_once() pour les fichiers du module
dol_include_once('/monmodule/class/monobjet.class.php')

// ✅ v20 : getEntity() — jamais "entity = $conf->entity" dans les WHERE
getEntity('monobjet')

// ✅ v20 : dolGetButtonTitle() — jamais <a href>...</a> pour les boutons d'action
dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $url)

// ✅ v20 : setEventMessages() — jamais setEventMessage() au singulier (deprecated)
setEventMessages($langs->trans('Saved'), null, 'mesgs')
```

#### Procédure de consultation du changelog

Si un comportement semble avoir changé entre versions, l'agent doit :

```
1. Vérifier https://github.com/Dolibarr/dolibarr/blob/develop/ChangeLog
2. Chercher la fonction ou le comportement dans les commits GitHub :
   https://github.com/Dolibarr/dolibarr/search?q=FUNCTION_NAME&type=commits
3. Vérifier la Doxygen pour la signature actuelle :
   https://doxygen.dolibarr.org/
4. Si un backport est nécessaire, l'implémenter et le commenter :
   // @BACKPORT v19→v20 : getDolGlobalString n'existe pas avant v15
5. Ajouter une entrée dans la section "Compatibilité" du README.md du module
```

---

### 15.12 Règles absolues

- **`getEntity()`** dans **chaque** WHERE SQL sur une table ayant le champ `entity` — sans exception
- **`entity = 0`** uniquement pour `dolibarr_set_const` sur `MULTICOMPANY_EXTERNAL_MODULES_SHARING`
- **`$conf->entity`** pour toutes les autres constantes du module
- **Jamais** `WHERE entity = $conf->entity` en dur — toujours `getEntity()`
- **Jamais** régénérer `$params` à chaque requête — le construire une fois dans `init()`
- **`array_merge`** et non `array_replace` lors de l'enregistrement — pour ne pas écraser d'autres modules
- **Tester** l'activation/désactivation du module MultiCompany **séparément** du module
- **Tester** depuis une entité secondaire que les données de l'entité principale ne fuient pas
- Les expressions `enable` et `display` sont des **strings évaluées** par MultiCompany — syntaxe PHP valide obligatoire, uniquement `$conf` et `$user`
- `'disable' => true` pour neutraliser un partage en dev sans retirer la structure
- Les fichiers SQL de dictionnaires (`filepath`) : uniquement des `INSERT` avec guillemets simples, compatibles PostgreSQL

---



## 16. Intégrations avec les modules Dolibarr

---

### 15.1 Agenda (ActionComm)

#### Principe

Le module Agenda gère les événements/actions via la classe `ActionComm`. Un module externe
peut **créer des événements liés à ses objets**, **afficher l'agenda dans une page dédiée**
et **réagir aux événements** via triggers.

> **Référence** : `diffusion_agenda.php` dans [mapiolca/diffusion](https://github.com/mapiolca/diffusion)
> est l'implémentation de référence à suivre.

#### Page agenda dédiée (`monobjet_agenda.php`)

La bonne pratique est de créer une **page dédiée** (onglet "Agenda") plutôt que d'injecter
le bloc dans `card.php`. Le hook context doit être `$object->element.'agenda'` :

```php
// monobjet_agenda.php
dol_include_once('/monmodule/class/monobjet.class.php');
dol_include_once('/monmodule/lib/monmodule_monobjet.lib.php');

$langs->loadLangs(array("monmodule@monmodule", "other"));

$object = new MonObjet($db);

// Contexte hook : {element}agenda (ex: 'monobjetdoc@monmodule' pour l'elementtype)
$hookmanager->initHooks(array($object->element.'agenda', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Vérification des droits
if (!isModEnabled('monmodule')) {
    accessforbidden();
}
if (!$permissiontoread) {
    accessforbidden();
}
```

#### `elementtype` — Format obligatoire

L'`elementtype` d'un `ActionComm` lié à un objet externe doit suivre le format
**`'{element}@{module}'`** et non pas simplement `$object->element` :

```php
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

$actioncomm               = new ActionComm($db);
$actioncomm->type_code    = 'AC_OTH_AUTO';
$actioncomm->label        = $langs->trans('MyEventLabel');
$actioncomm->datep        = dol_now();
$actioncomm->fk_element   = $object->id;
$actioncomm->elementtype  = $object->element.'@monmodule'; // ← '{element}@{module}' OBLIGATOIRE
$actioncomm->socid        = $object->fk_soc ?? 0;
$actioncomm->userownerid  = $user->id;
$actioncomm->percentage   = -1;    // -1 = sans progression
$actioncomm->visibility   = 0;     // 0 = public

$result = $actioncomm->create($user);
if ($result <= 0) {
    dol_print_error($db, $actioncomm->error);
}
```

#### Compter les événements (avec cache)

Utiliser le cache Dolibarr pour ne pas requêter la base à chaque affichage :

```php
require_once DOL_DOCUMENT_ROOT.'/core/lib/memory.lib.php';

$nbEvent  = 0;
$cachekey = 'count_events_monmodule_'.$object->id;
$dataretrieved = dol_getcache($cachekey);

if (!is_null($dataretrieved)) {
    $nbEvent = (int) $dataretrieved;
} else {
    $sql  = "SELECT COUNT(a.id) as nb";
    $sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as a";
    $sql .= " WHERE a.fk_element = ".((int) $object->id);
    $sql .= " AND a.elementtype IN ('".$db->escape($object->element.'@monmodule')."')";
    $resql = $db->query($sql);
    if ($resql) {
        $obj     = $db->fetch_object($resql);
        $nbEvent = (int) $obj->nb;
    }
    dol_setcache($cachekey, $nbEvent, 120);    // Cache 2 minutes
}
```

#### Afficher la liste des événements

```php
if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {

    // Bouton "Ajouter une action"
    $out  = '&origin='.urlencode($object->element.(property_exists($object, 'module') ? '@'.$object->module : ''));
    $out .= '&originid='.urlencode((string) $object->id);
    $out .= '&backtopage='.urlencode($_SERVER['PHP_SELF'].'?id='.$object->id);

    $morehtmlright = '';
    if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
        $morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out);
    }

    $titlelist = $langs->trans("Actions").'<span class="opacitymedium colorblack paddingleft">('.$nbEvent.')</span>';
    print_barre_liste($titlelist, 0, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 0);

    // Affichage de la liste avec show_actions_done (et non show_actions_messaging)
    $filters = array(
        'search_agenda_label' => $search_agenda_label,
        'search_rowid'        => $search_rowid,
    );
    show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder, property_exists($object, 'module') ? $object->module : '');
}
```

#### Déclarer l'onglet Agenda dans `prepareHead()`

```php
// lib/monmodule_monobjet.lib.php
function monobjetPrepareHead($object)
{
    global $langs, $conf, $user;
    $langs->load("monmodule@monmodule");

    $h   = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/monmodule/monobjet_card.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

    if (isModEnabled('agenda')) {
        $head[$h][0] = dol_buildpath('/monmodule/monobjet_agenda.php', 1).'?id='.$object->id;
        $head[$h][1] = $langs->trans("Agenda");
        // Ajouter le compteur en badge
        $nbEvent = 0; // Récupérer depuis le cache (voir ci-dessus)
        if ($nbEvent > 0) {
            $head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbEvent.'</span>';
        }
        $head[$h][2] = 'agenda';
        $h++;
    }

    return $head;
}
```

#### Triggers Agenda à écouter

```php
case 'ACTION_CREATE':
case 'ACTION_MODIFY':
case 'ACTION_DELETE':
    // $object est une instance de ActionComm
    // Filtrer sur elementtype pour n'agir que sur les événements de votre module
    if ($object->elementtype === 'monobjet@monmodule') {
        // Réagir aux événements liés à vos objets
    }
    break;
```

#### Règles

- `elementtype` = **`'{element}@{module}'`** — jamais juste `$object->element`
- Hook context de la page agenda = **`$object->element.'agenda'`** (pas `'agendacard'`)
- Utiliser **`show_actions_done()`** et non `show_actions_messaging()` pour la liste
- Utiliser **`dol_getcache()` / `dol_setcache()`** pour le comptage d'événements
- Bouton "Ajouter" via **`dolGetButtonTitle()`** avec `fa fa-plus-circle`
- Ne jamais créer d'événements ActionComm dans des hooks — uniquement dans les triggers ou méthodes métier
- `type_code` doit être une valeur valide du dictionnaire `llx_c_actioncomm`

---

### 15.2 Notifications

#### Principe

Le module `notify` permet d'envoyer des notifications automatiques (e-mail, etc.) en réaction
à des triggers. Un module externe peut **déclarer ses propres événements notifiables** et
**déclencher des notifications** depuis ses triggers.

#### Déclarer les événements notifiables dans le descripteur

```php
// modMonModule.class.php — dans __construct()
$this->cronjobs = [];

// Événements déclenchant des notifications
// Ces constantes doivent correspondre exactement aux codes de triggers utilisés
$this->const = [
    // ...
];

// Déclarer les codes d'événements pour le module notify
// Ils apparaîtront dans la liste "Notifications par défaut" de l'admin
$this->notifications = [
    'MONOBJET_CREATE'   => 'SendMailOnMonObjetCreate',    // Clé de traduction
    'MONOBJET_VALIDATE' => 'SendMailOnMonObjetValidate',
];
```

#### Envoyer une notification depuis un trigger

```php
// Dans runTrigger(), après avoir traité l'action métier :
if (isModEnabled('notify')) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
    $notify = new Notify($db);
    $notify->send('MONOBJET_CREATE', $object);   // Code trigger + objet métier
    // Retourne le nombre de notifications envoyées ou < 0 en cas d'erreur
}
```

#### Créer un template d'e-mail pour votre module

Les templates sont stockés dans `llx_c_email_templates`. Insérer via `data.sql` :

```sql
DELETE FROM llx_c_email_templates WHERE label = 'MonObjet - Création' AND entity = '__ENTITY__';
INSERT INTO llx_c_email_templates
  (entity, type_template, lang, private, fk_user, datec, label, position, enabled, topic, content, content_lines)
  VALUES
  ('__ENTITY__', 'monobjet', '', 1, NULL, NOW(), 'MonObjet - Création', 10, 1,
   '[__[MAIN_INFO_SOCIETE_NOM]__] Nouvel objet créé : __REF__',
   'Bonjour,<br><br>L\'objet __REF__ a été créé.<br><br>Cordialement.',
   NULL);
```

Variables de substitution disponibles dans les templates :
`__REF__`, `__LABEL__`, `__COMPANY__`, `__USER_FULLNAME__`, `__MYCOMPANY_NAME__`, etc.

#### Règles

- Toujours vérifier `isModEnabled('notify')` avant d'instancier `Notify`
- Le code trigger passé à `$notify->send()` doit correspondre exactement au `$action` du trigger
- Ne pas appeler `$notify->send()` dans les hooks, uniquement dans les triggers
- Les templates `data.sql` : utiliser `NOW()` est interdit → utiliser `$db->idate(dol_now())` en PHP, ou omettre `datec` si la colonne a une valeur par défaut

---

### 15.3 WYSIWYG (éditeur HTML enrichi)

#### Principe

Dolibarr intègre un éditeur WYSIWYG (TinyMCE) pour les champs de type `html` (`note_public`,
`note_private`, `description`…). L'activation est conditionnée par `$conf->use_javascript_ajax`
et le module `fckeditor`.

> **Référence** : `diffusion_card.php` inclut `doleditor.class.php` et utilise le pattern
> `DolEditor` avec `showInputField` pour les champs HTML.

#### Include obligatoire dans les pages

```php
// Toujours inclure DolEditor explicitement dans la page qui utilise WYSIWYG
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
```

#### Déclarer un champ HTML dans `$fields`

```php
// Dans la classe métier
public $fields = [
    'note_public' => [
        'type'    => 'html',           // ← type 'html' active le rendu WYSIWYG
        'label'   => 'NotePublic',
        'enabled' => 1,
        'visible' => 3,                // 3 = édition + vue
        'position'=> 61,
        'notnull' => 0,
    ],
    'note_private' => [
        'type'    => 'html',
        'label'   => 'NotePrivate',
        'enabled' => 1,
        'visible' => 3,
        'position'=> 62,
        'notnull' => 0,
    ],
];
```

#### Afficher l'éditeur WYSIWYG dans un formulaire

Via `DolEditor` directement :

```php
$doleditor = new DolEditor(
    'note_public',          // Nom du champ HTML (name=)
    $object->note_public,   // Valeur initiale
    '',                     // Hauteur CSS ('' = auto)
    200,                    // Hauteur en pixels
    'dolibarr_notes',       // Jeu de boutons ('dolibarr_notes', 'dolibarr_details', etc.)
    '',                     // Alignement
    false,                  // Lecture seule
    true,                   // Activer l'éditeur si JS dispo
    getDolGlobalString('FCKEDITOR_ENABLE_NOTES', 1),   // Forcer WYSIWYG (0 = textarea simple)
    ROWS_8,                 // Nombre de lignes si fallback textarea
    '90%'                   // Largeur CSS
);
$doleditor->Create();
```

Via `showInputField` (recommandé pour les fiches générées — gère automatiquement le type `html`) :

```php
// En édition : showInputField détecte le type 'html' et instancie DolEditor
print $object->showInputField($object->fields['note_public'], 'note_public', $object->note_public, '', '', '', 0);
```

#### Afficher un champ HTML en lecture seule

```php
print '<div class="note clearboth">';
print dol_htmlentitiesbr($object->note_public);   // \n → <br> + échappe les entités
print '</div>';
```

#### Input utilisateur pour un champ HTML

```php
// Toujours utiliser 'restricthtml' pour les champs WYSIWYG — jamais 'alpha' ni 'nohtml'
$object->note_public = GETPOST('note_public', 'restricthtml');
```

#### Règles

- Toujours `require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php'` dans la page
- `GETPOST('field', 'restricthtml')` pour les champs HTML — jamais `'alpha'` ni `'nohtml'`
- Les champs `html` sont stockés en `text`/`mediumtext` en SQL — non indexables
- Échapper avec `dol_htmlentitiesbr()` en affichage, `dol_htmlentities()` pour les attributs
- Ne jamais mettre `type => 'html'` sur un champ de moins de 255 caractères — utiliser `varchar` + `type => 'varchar'`

---

### 15.4 Projets et tâches

#### Principe

Un objet métier peut être **lié à un projet** (via `fk_project`) et/ou **à une tâche**.
Le module externe peut aussi **créer des tâches**, **importer les contacts du projet**
et **réagir aux événements projet** via triggers.

> **Référence** : `diffusion_card.php` montre le pattern complet d'intégration projet,
> incluant le sélecteur, l'import de contacts, et la gestion multi-entités.

#### Includes obligatoires

```php
// Dans card.php — inclure explicitement ces classes
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';   // FormProjets
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';         // Project + FormProjets
```

> ⚠️ `FormProjets` n'est **pas** dans `html.form.class.php` — il faut `html.formprojet.class.php`.

#### Ajouter le champ `fk_project` à un objet

Dans `$fields` de la classe métier :

```php
'fk_project' => [
    'type'     => 'integer:Project:projet/class/project.class.php:1:(fk_statut:=:1)',
    'label'    => 'Project',
    'enabled'  => "isModEnabled('project')",   // Champ actif seulement si module Projets activé
    'visible'  => 1,
    'position' => 40,
    'notnull'  => -1,
    'index'    => 1,
    'css'      => 'maxwidth500',
],
```

Et dans la table SQL :

```sql
-- llx_monmodule_monobjet.sql
fk_project  integer,

-- llx_monmodule_monobjet.key.sql
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_llx_monmodule_monobjet_fk_project (fk_project);
```

#### Sélecteur de projet dans un formulaire

```php
if (isModEnabled('project')) {
    $formproject = new FormProjets($db);
    print '<tr><td>'.$langs->trans('Project').'</td><td>';
    $formproject->select_projects(
        $object->fk_soc ?? -1,     // Filtrer par tiers (-1 = tous)
        $object->fk_project,        // Valeur sélectionnée
        'fk_project',               // name= du champ HTML
        0,                          // maxlength
        0,                          // forceonlyone : 0 = permettre vide
        1,                          // show_empty : 1 = ligne vide en tête
        0,                          // discard_closed : 0 = inclure projets fermés
        1,                          // forceaddingprojectlink
        0,                          // show_links
        0,                          // fk_project_task
        0,                          // limittoompanyid
        '',                         // morecss
        1                           // limittoprojectorphasewhere
    );
    print '</td></tr>';
}
```

#### Import de contacts depuis le projet lié

Pattern utilisé dans diffusion pour importer les contacts du projet en un clic :

```php
// Dans la section actions de card.php
if ($action === 'importprojectcontacts' && !empty($object->fk_project)) {
    if (isModEnabled('project')) {
        $proj = new Project($db);
        if ($proj->fetch($object->fk_project) > 0) {
            $contactsroles = $proj->liste_contact(-1, 'internal');
            foreach ($contactsroles as $contactdata) {
                // Ajouter le contact à l'objet
                $object->add_contact($contactdata['id'], $contactdata['fk_c_type_contact'], 'internal');
            }
            $contactsroles = $proj->liste_contact(-1, 'external');
            foreach ($contactsroles as $contactdata) {
                $object->add_contact($contactdata['id'], $contactdata['fk_c_type_contact'], 'external');
            }
        }
    }
}
```

#### Créer une tâche liée à un objet

```php
if (isModEnabled('project')) {
    require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

    $task                  = new Task($db);
    $task->fk_project      = $object->fk_project;
    $task->label           = $langs->trans('MyTaskLabel');
    $task->date_start      = dol_now();
    $task->planned_workload = 3600;         // En secondes
    $task->fk_user_creat   = $user->id;
    $task->progress        = 0;

    $result = $task->create($user);
    if ($result <= 0) {
        dol_print_error($db, $task->error);
    }
}
```

#### Hooks Projets à déclarer

```php
// Dans le descripteur
$this->module_parts = [
    'hooks' => [
        'data' => [
            'projectcard',        // Fiche projet
            'taskcard',           // Fiche tâche
            'projecttaskscard',   // Onglet tâches d'un projet
        ],
    ],
];
```

#### Triggers Projets à écouter

```php
case 'PROJECT_CREATE':
case 'PROJECT_MODIFY':
case 'PROJECT_DELETE':
case 'PROJECT_CLOSE':
case 'TASK_CREATE':
case 'TASK_MODIFY':
case 'TASK_DELETE':
    break;
```

#### Règles

- Toujours vérifier `isModEnabled('project')` avant tout appel projet
- `FormProjets` est dans `html.formprojet.class.php` — pas dans `html.form.class.php`
- Le type `integer:Project:projet/class/project.class.php:1:(fk_statut:=:1)` dans `$fields` active le sélecteur automatique
- Ne jamais créer de FK SQL vers `llx_projet` — gérer en PHP
- Toujours indexer `fk_project` en SQL

---

### 15.5 ModuleBuilder

#### Principe

Le **ModuleBuilder** est un outil intégré à Dolibarr qui génère le squelette complet d'un
module externe. Il produit des fichiers conformes aux standards du projet, qu'il faut ensuite
**compléter** sans jamais supprimer la structure générée.

> **Référence** : le module diffusion contient un fichier `modulebuilder.txt` à la racine —
> c'est le **marqueur officiel** indiquant que le module a été généré (et peut être ré-édité)
> par ModuleBuilder.

#### Le fichier `modulebuilder.txt` — À ne jamais supprimer

```
# DO NOT DELETE THIS FILE MANUALLY
# File to flag module built using official module template.
# When this file is present into a module directory, you can edit it with the module builder tool.
```

Ce fichier doit **toujours** être présent à la racine du module. Sa suppression empêche
ModuleBuilder de reconnaître le module et de le ré-éditer. Ne jamais l'ignorer dans `.gitignore`.

#### Activer et utiliser ModuleBuilder

1. Activer le module **ModuleBuilder** dans Accueil → Configuration → Modules
2. Accéder à `/modulebuilder/index.php`
3. Renseigner : nom du module, numéro unique, objets métier, champs
4. Générer → les fichiers sont créés dans `htdocs/custom/<monmodule>/`

#### Ce que ModuleBuilder génère

```
modMonModule.class.php          ← Descripteur complet (droits, menus, hooks, SQL)
class/monobjet.class.php        ← Classe métier avec $fields et CRUD
class/api_monobjet.class.php    ← API REST documentée
class/actions_monmodule.class.php ← Fichier hooks (vide, prêt à compléter)
core/triggers/interface_*.php   ← Fichier triggers (vide, prêt à compléter)
sql/llx_*.sql + *.key.sql       ← Tables et index
monobjet_card.php               ← Page fiche (nommée {objet}_card.php, pas card.php)
monobjet_list.php               ← Page liste (nommée {objet}_list.php)
monobjet_agenda.php             ← Page agenda dédiée (onglet Agenda)
monobjet_document.php           ← Page documents (onglet Documents)
admin/setup.php                 ← Page de configuration
langs/fr_FR + en_US             ← Fichiers de langue avec toutes les clés
lib/monmodule_monobjet.lib.php  ← Fonctions utilitaires (prepareHead, etc.)
modulebuilder.txt               ← Marqueur ModuleBuilder (NE PAS SUPPRIMER)
```

> ⚠️ Les pages générées sont nommées **`{objet}_card.php`**, **`{objet}_list.php`**,
> **`{objet}_agenda.php`** — pas `card.php` / `list.php` génériques.
> C'est le pattern du module diffusion (`diffusion_card.php`, `diffusion_agenda.php`…).

#### Workflow après génération

```
1. Vérifier le numéro de module ($this->numero) — s'assurer qu'il est unique et réservé
2. Compléter $fields dans la classe métier (types, visible, notnull, css, searchall…)
3. Ajouter les méthodes métier spécifiques (validate(), cancel(), setDraft(), etc.)
4. Implémenter les hooks dans actions_monmodule.class.php
5. Implémenter les triggers dans interface_*.class.php
6. Personnaliser {objet}_card.php et {objet}_list.php (sections spécifiques, onglets)
7. Enrichir {objet}_agenda.php selon le pattern §13.1
8. Enrichir admin/setup.php avec les constantes de configuration
9. Compléter les fichiers .lang avec les traductions métier
```

#### Points critiques post-génération

```php
// 1. Vérifier ismultientitymanaged dans la classe métier
$this->ismultientitymanaged = 1;    // 1 si l'objet est filtré par entity
$this->isextrafieldmanaged  = 1;    // 1 si les ExtraFields sont supportés

// 2. Vérifier le guard dans les triggers
if (!isModEnabled('monmodule')) {
    return 0;   // ← TOUJOURS présent — ModuleBuilder peut l'omettre
}

// 3. Vérifier la cohérence element / table_element / fk_element
public $element       = 'monobjet';           // Utilisé par ActionComm, liens objets
public $table_element = 'monmodule_monobjet'; // Sans préfixe llx_
public $fk_element    = 'fk_monobjet';        // Clé étrangère dans les tables de liens

// 4. S'assurer que les menus ont le bon enabled/perms
'enabled' => 'isModEnabled("monmodule")',
'perms'   => '$user->hasRight("monmodule", "monobjet", "read")',

// 5. Utiliser dol_include_once pour les fichiers du module (pas require_once)
dol_include_once('/monmodule/class/monobjet.class.php');
dol_include_once('/monmodule/lib/monmodule_monobjet.lib.php');
```

#### Ne pas faire après génération

- ❌ Ne jamais régénérer avec ModuleBuilder si vous avez déjà personnalisé les fichiers — cela **écrase tout**
- ❌ Ne pas supprimer `modulebuilder.txt` — c'est le marqueur permettant la ré-édition
- ❌ Ne pas utiliser ModuleBuilder en production — générer en dev, puis déployer proprement
- ❌ Ne pas laisser `$this->numero = 0` — affecter un numéro unique avant le premier test
- ❌ Ne pas renommer `{objet}_card.php` en `card.php` — garder la convention de nommage ModuleBuilder

#### Compléter `$fields` après génération

ModuleBuilder génère souvent des `$fields` minimaux. Enrichir systématiquement :

```php
'label' => [
    'type'              => 'varchar(255)',
    'label'             => 'Label',
    'enabled'           => 1,
    'position'          => 30,
    'notnull'           => 1,
    'visible'           => 1,
    'searchall'         => 1,        // ← Inclus dans la recherche globale
    'showoncombobox'    => 2,        // ← Affiché dans les listes déroulantes
    'css'               => 'minwidth300',
    'autofocusoncreate' => 1,
],
'fk_soc' => [
    'type'    => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__shared_entities__))',
    'label'   => 'ThirdParty',
    'enabled' => 'isModEnabled("societe")',
    'position'=> 50,
    'notnull' => -1,
    'visible' => 1,
    'index'   => 1,
    'css'     => 'maxwidth500 widthcentpercentminusxx',
],
```

---



## 17. Workflow de développement

1. **Scaffolding** — Générer avec ModuleBuilder (`/modulebuilder/`), puis appliquer les corrections post-génération (§13.5)
2. **Descripteur** — Configurer `modMonModule.class.php` (numéro unique ≥ 500000, droits, menus, hooks, tables)
3. **SQL** — Créer `.sql` + `.key.sql` dans `sql/` ; ajouter `fk_project` si intégration Projets (§13.4)
4. **Classe métier** — Hériter de `CommonObject`, enrichir `$fields`, implémenter CRUD et méthodes métier
5. **Pages** — `{objet}_card.php` + `{objet}_list.php` en respectant le pattern §9 (ordre des blocs, pagination, filtres, mass actions) ; intégrer le bloc Agenda (§14.1) et le sélecteur de projet (§14.4) si besoin
6. **Éditeur WYSIWYG** — Utiliser `DolEditor` pour les champs de type `html` (§13.3)
7. **Hooks/Triggers** — Étendre le core ; déclarer les triggers Agenda/Projets à écouter (§13.1, §13.4)
8. **Notifications** — Déclarer les événements notifiables et appeler `Notify::send()` dans les triggers (§13.2)
9. **MultiCompany** — Si applicable, enregistrer `$params` dans `init()` et le retirer dans `remove()` (§12)
10. **API REST** — Ajouter `api_monobjet.class.php` si besoin
11. **Traductions** — Fichiers `.lang` pour fr_FR + en_US (clés MultiCompany, Agenda, Notifications si applicable)
12. **Tests** — Activer le module, tester CRUD, vérifier les droits, activer/désactiver plusieurs fois
13. **Packaging** — Préparer pour DoliStore ou déploiement direct

---

## 18. Checklist avant tout commit

### Qualité module natif — Design & UX
- [ ] Toutes les pages utilisent `llxHeader()` / `llxFooter()` avec la classe CSS `mod-monmodule page-xxx`
- [ ] Les fiches utilisent `dol_get_fiche_head()`, `dol_banner_tab()`, `dol_get_fiche_end()`
- [ ] Les boutons d'action sur fiches utilisent `dolGetButtonAction()` — jamais de `<a class="butAction">`
- [ ] Les boutons sur listes utilisent `dolGetButtonTitle()` avec icône Font Awesome
- [ ] Les pictos utilisent `img_picto()`, `img_edit()`, `img_delete()`, `img_view()` — jamais `<img>` brut
- [ ] Les dates sont affichées via `dol_print_date()` — jamais `date()` PHP
- [ ] Les montants sont affichés via `price()` — jamais `number_format()`
- [ ] Les statuts utilisent `getLibStatut(n)` / `dolGetStatus()` avec les classes `status0` à `status9`
- [ ] Les liens objets utilisent `getNomUrl(1)` — jamais d'URL HTML brute
- [ ] Les notifications utilisent `setEventMessages()` — jamais `setEventMessage()` (déprécié)
- [ ] Les dialogues de confirmation utilisent `$form->formconfirm()` — jamais de `confirm()` JS
- [ ] Les dates de formulaire utilisent `$form->selectDate()` — jamais `<input type="date">`
- [ ] Les listes déroulantes utilisent `$form->selectarray()` ou équivalent natif
- [ ] Le JavaScript est conditionné par `!empty($conf->use_javascript_ajax)`
- [ ] Les onglets standard sont présents : Card, Note, Contact, Document, Agenda, Info
- [ ] Les blocs Note utilisent `DolEditor` pour les champs HTML et `dol_htmlentitiesbr()` en lecture
- [ ] L'onglet Info affiche `dol_print_object_info($object, 1)`
- [ ] Le HTML est standard (pas XHTML, attributs minuscules, pas de `/>` sur balises non auto-fermantes)
- [ ] Aucune largeur fixe sur les colonnes de tableau (sauf pictos)

### Compatibilité v20
- [ ] `isModEnabled()` partout — jamais `!empty($conf->xxx->enabled)`
- [ ] `$user->hasRight()` partout — jamais `$user->rights->xxx`
- [ ] `getDolGlobalString()` / `getDolGlobalInt()` — jamais `$conf->global->XXX` direct
- [ ] `GETPOSTINT()` — jamais `(int)GETPOST('x','int')`
- [ ] `setEventMessages()` — jamais `setEventMessage()` au singulier
- [ ] `dol_include_once()` pour les fichiers du module — jamais `require_once`
- [ ] Tout backport annoté `// @BACKPORT vX→v20` dans le code
- [ ] Si un backport est impossible : documenté dans `COMPATIBILITY.md` avec impact
- [ ] Changelog Dolibarr consulté pour toute API incertaine (voir §2.5)

### Qualité du code
- [ ] Aucun fichier core modifié (`htdocs/` hors `htdocs/custom/`)
- [ ] Le module s'active et se désactive sans erreur PHP
- [ ] Les tables sont créées à l'activation et supprimées à la désactivation
- [ ] Les droits sont définis dans le descripteur et vérifiés via `$user->hasRight()`
- [ ] Toutes les entrées utilisateur passent par `GETPOST()` avec le bon type
- [ ] Aucune requête SQL n'utilise `SELECT *`, `NOW()`, `ENUM`, `GROUP_CONCAT`…
- [ ] Les types SQL utilisés sont dans la liste des types autorisés (§5)
- [ ] `data.sql` utilise des guillemets simples (compatibilité PostgreSQL)
- [ ] Les hooks retournent `0` ou `< 0` (jamais `1` ni `true`)
- [ ] Les traductions existent en `fr_FR` et `en_US`
- [ ] L'API REST est documentée avec les annotations Restler
- [ ] Le code respecte PSR-12 avec les exceptions Dolibarr
- [ ] Les fichiers sont encodés en UTF-8 sans BOM, fins de ligne LF
- [ ] Aucun `var_dump()` / `print_r()` / `die()` de debug laissé en production

### Pages liste (`{objet}_list.php`)
- [ ] Ordre des blocs respecté : paramètres → tri → filtres → `$arrayfields` → droits → actions → SQL → vue
- [ ] `$sortfield` initialisé via `reset()` + `key($object->fields)` si non fourni
- [ ] `getEntity()` présent dans le WHERE SQL
- [ ] `natural_search()` utilisé pour tous les filtres texte/statut
- [ ] `$db->plimit($limit + 1, $offset)` — pas de LIMIT SQL brut
- [ ] Les trois templates ExtraFields inclus (`array_fields`, `search_sql`, `search_param`)
- [ ] `$param` construit avec tous les filtres actifs pour les liens de pagination et de tri
- [ ] `actions_changeselectedfields.inc.php` inclus avant la purge des filtres
- [ ] `$objectclass`, `$objectlabel`, `$uploaddir` définis avant `actions_massactions.inc.php`

### MultiCompany
- [ ] `entity integer DEFAULT 1 NOT NULL` dans toutes les tables, indexé
- [ ] `getEntity('monobjet')` dans **chaque** WHERE SQL sur table multi-entité
- [ ] `$this->ismultientitymanaged = 1` dans la classe métier
- [ ] `$conf->monmodule->multidir_output[$conf->entity]` pour les fichiers
- [ ] Constantes module avec `$conf->entity`, sauf `MULTICOMPANY_EXTERNAL_MODULES_SHARING` (entity=0)
- [ ] `$params` enregistré dans `init()` avec `array_merge`, retiré dans `remove()` avec `unset`
- [ ] `isModEnabled('multicompany')` vérifié avant tout accès à `MULTICOMPANY_EXTERNAL_MODULES_SHARING`
- [ ] JSON décodé en array vérifié (`is_array`) avant `array_merge` ou `unset`
- [ ] Colonne entité affichée dans list.php uniquement quand le partage est actif
- [ ] Test : données entité 1 non visibles depuis entité 2 sans partage activé

### Intégrations modules (si applicable)
- [ ] **Agenda** : `elementtype` = `'{element}@{module}'`, page `_agenda.php` dédiée, `show_actions_done()`
- [ ] **Notifications** : `isModEnabled('notify')` vérifié, événements déclarés dans le descripteur
- [ ] **WYSIWYG** : `DolEditor` pour champs `html`, `GETPOST('field', 'restricthtml')`
- [ ] **Projets** : `isModEnabled('project')` vérifié, `fk_project` indexé, `FormProjets` depuis `html.formprojet.class.php`
- [ ] **ModuleBuilder** : `modulebuilder.txt` présent, numéro module unique, guard `isModEnabled` dans les triggers

---

## 19. Ressources de référence

| Ressource                        | URL / Chemin                                                    |
|----------------------------------|-----------------------------------------------------------------|
| Wiki développeur officiel        | https://wiki.dolibarr.org/index.php/Developer_documentation     |
| Coding standards                 | https://wiki.dolibarr.org/index.php/Coding_standards            |
| Hooks disponibles                | https://wiki.dolibarr.org/index.php/Hooks_system               |
| Dépôt GitHub                     | https://github.com/Dolibarr/dolibarr                           |
| ModuleBuilder intégré            | `/modulebuilder/index.php` (activer le module ModuleBuilder)    |
| Templates de module              | `htdocs/modulebuilder/templates/`                               |
| Module de référence (diffusion)  | https://github.com/mapiolca/diffusion                           |
| ChangeLog officiel Dolibarr      | https://github.com/Dolibarr/dolibarr/blob/develop/ChangeLog    |
| Doxygen (API PHP)                | https://doxygen.dolibarr.org/                                   |
| Recherche commits GitHub         | https://github.com/Dolibarr/dolibarr/search?type=commits       |
| Skill Dolibarr (Claude interne)  | `/mnt/skills/user/dolibarr-dev/SKILL.md`                        |
