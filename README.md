# TIMESHEETWEEK FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## 🇫🇷 Présentation

TimesheetWeek ajoute une gestion hebdomadaire des feuilles de temps fidèle à l'expérience Dolibarr. Le module renforce les cycles de validation, propose des compteurs opérationnels (zones, paniers, heures supplémentaires) et respecte les standards graphiques pour les écrans administratifs et les modèles de documents.

### Fonctionnalités principales

- Statut « Scellée » pour verrouiller les feuilles approuvées et empêcher toute modification ultérieure, avec les permissions associées.
- Redirection automatique vers la feuille existante en cas de tentative de doublon afin d'éviter les saisies multiples.
- Suivi des compteurs hebdomadaires de zones et de paniers directement sur les feuilles et recalcul automatique à chaque enregistrement.
- Affichage des compteurs dans la liste hebdomadaire et ajout du libellé « Zone » sur chaque sélecteur quotidien pour clarifier la saisie.
- Création rapide d'une feuille d'heures via le raccourci « Ajouter » du menu supérieur.
- Compatibilité Multicompany pour partager les feuilles et leur numérotation, avec options de partage dédiées et filtres multi-sélection harmonisés à l'interface native.
- Affichage de l'entité dans les listes et fiches en environnement Multicompany, accompagné d'un badge visuel sous la référence lorsque l'entité diffère.
- Sécurisation des requêtes SQL par entité et filtres multi-entités alignés sur les pratiques Dolibarr.
- Harmonisation du filtre de semaine avec un sélecteur ISO multi-sélection permettant de regrouper plusieurs périodes.
- Inversion des couleurs des statuts « Scellée » et « Refusée » pour respecter les codes couleur Dolibarr.
- Refonte complète de la page de configuration pour la gestion des masques de numérotation et des modèles PDF selon les codes graphiques Dolibarr.
- README bilingue (FR/EN) pour faciliter le déploiement et l'adoption.

### Installation

1. **Pré-requis** : disposer d'une instance Dolibarr fonctionnelle. Les versions supportées correspondent à celles indiquées dans le fichier `modTimesheetWeek.class.php`.
2. **Déploiement via l'interface** : depuis `Accueil > Configuration > Modules > Déployer un module externe`, importez l'archive `module_timesheetweek-x.y.z.zip` téléchargée sur [Dolistore](https://www.dolistore.com) ou obtenue via votre circuit de diffusion.
3. **Déploiement manuel** : copiez le répertoire du module dans `htdocs/custom/timesheetweek`, puis purgez le cache des modules depuis l'administration Dolibarr.
4. **Activation** : connectez-vous en tant que super administrateur, activez le module dans `Configuration > Modules > Projets/Temps`, puis exécutez le script `sql/update_all.sql` pour ajouter les compteurs aux données existantes.

### Configuration

- Rendez-vous dans `Configuration > Modules > TimesheetWeek` pour choisir le masque de numérotation actif et activer les modèles PDF souhaités.
- Ajustez les options Multicompany via les onglets de configuration dédiés si vous partagez les feuilles de temps entre plusieurs entités.

### Traductions

Les fichiers de traduction sont disponibles dans `langs/en_US` et `langs/fr_FR`. Toute nouvelle chaîne doit être renseignée simultanément dans les deux langues conformément aux pratiques Dolibarr.

## 🇬🇧 Overview

TimesheetWeek delivers weekly timesheet management that follows Dolibarr design guidelines. It enhances approval workflows, exposes operational counters (zones, meal allowances, overtime) and keeps the administration area consistent with native modules.

### Main features

- Statut « Scellée » (Sealed status) to lock approved timesheets together with the related permissions.
- Automatic redirect to the existing timesheet when a duplicate creation is attempted.
- Weekly counters for zones and meal allowances with automatic recomputation on each save.
- Counter display inside the weekly list plus a « Zone » caption on each daily selector for better input guidance.
- Quick creation shortcut available from the top-right « Add » menu.
- Multicompany compatibility for sharing timesheets and numbering sequences, with dedicated sharing options and native-aligned multi-select filters.
- Entity details shown on lists and cards in Multicompany environments with a badge under the reference when the entity differs.
- Entity-scoped SQL queries and Multicompany filters harmonised with Dolibarr best practices.
- ISO week selector shared between list and card views, now supporting multi-selection to combine several periods.
- Swapped colours for « Scellée » and « Refusée » statuses to match Dolibarr visual cues.
- Fully redesigned setup page for numbering masks and PDF templates, using Dolibarr's graphical and functional patterns.
- Bilingual (FR/EN) README to streamline rollout and user onboarding.

### Installation

1. **Prerequisites**: a running Dolibarr instance that matches the compatibility range declared in `modTimesheetWeek.class.php`.
2. **Deploy from the GUI**: go to `Home > Setup > Modules > Deploy external module` and upload the `module_timesheetweek-x.y.z.zip` archive from [Dolistore](https://www.dolistore.com) or your distribution channel.
3. **Manual deployment**: copy the module directory into `htdocs/custom/timesheetweek`, then refresh the module cache from Dolibarr's administration area.
4. **Activation**: log in as a super administrator, enable the module from `Setup > Modules > Projects/Timesheets`, and run the `sql/update_all.sql` script so legacy timesheets gain the new counters.

### Configuration

- Visit `Setup > Modules > TimesheetWeek` to select the numbering mask and to activate the PDF models you want to expose to users.
- In Multicompany contexts, tune the sharing preferences through the dedicated configuration tabs.

### Translations

Translation sources are stored under `langs/en_US` and `langs/fr_FR`. Please keep both locales aligned for every new string to stay compatible with Dolibarr's translation workflow.

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and README files are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
