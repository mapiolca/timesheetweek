# TIMESHEETWEEK FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## 🇫🇷 Présentation

TimesheetWeek ajoute une gestion hebdomadaire des feuilles de temps fidèle à l'expérience Dolibarr. Le module renforce les cycles de validation, propose des compteurs opérationnels (zones, paniers, heures supplémentaires) et respecte les standards graphiques pour les écrans administratifs et les modèles de documents.

### Fonctionnalités principales

- Statut « Scellée » pour verrouiller les feuilles approuvées et empêcher toute modification ultérieure, avec les permissions associées.
- Redirection automatique vers la feuille existante en cas de tentative de doublon afin d'éviter les saisies multiples.
- Suivi des compteurs hebdomadaires de zones et de paniers directement sur les feuilles et recalcul automatique à chaque enregistrement.
- Saisie dédiée pour les salariés en forfait jour grâce à des sélecteurs Journée/Matin/Après-midi convertissant automatiquement les heures.
- Rappel hebdomadaire automatique par email configurable (activation, jour, heure, modèle) avec tâche planifiée dédiée et bouton d'envoi de test administrateur.
- Scellement automatique des feuilles approuvées après un délai configurable via une tâche planifiée Dolibarr native.
- Stocke l'utilisateur et la date de scellement dans des colonnes dédiées pour faciliter le suivi.
- Affichage des compteurs dans la liste hebdomadaire et ajout du libellé « Zone » sur chaque sélecteur quotidien pour clarifier la saisie.
- Capture les heures au contrat au moment de la soumission pour figer le calcul des heures supplémentaires et les PDF, même si le contrat salarié évolue ensuite.
- Ligne de total en bas de la liste hebdomadaire pour additionner heures, zones, paniers et afficher la colonne de date de validation.
- Création rapide d'une feuille d'heures via le raccourci « Ajouter » du menu supérieur.
- Compatibilité Multicompany pour partager les feuilles et leur numérotation, avec options de partage dédiées et filtres multi-sélection harmonisés à l'interface native.
- Affichage de l'entité dans les listes et fiches en environnement Multicompany, accompagné d'un badge visuel sous la référence lorsque l'entité diffère.
- Sécurisation des requêtes SQL par entité et filtres multi-entités alignés sur les pratiques Dolibarr.
- Harmonisation du filtre de semaine avec un sélecteur ISO multi-sélection permettant de regrouper plusieurs périodes.
- Inversion des couleurs des statuts « Scellée » et « Refusée » pour respecter les codes couleur Dolibarr.
- Refonte complète de la page de configuration inspirée du module DiffusionPlans pour gérer les masques de numérotation et les modèles PDF selon les codes graphiques Dolibarr.
- Sélection du masque de numérotation via des commutateurs natifs directement depuis la configuration Dolibarr.
- Génération du PDF de la feuille directement depuis la fiche hebdomadaire avec le widget Documents et respect du modèle configuré dans l'administration.
- Événements Agenda et Notifications exposés dans les écrans natifs Dolibarr, avec substitutions disponibles pour les modèles d'e-mails.
- Notifications métier par étape (soumission, approbation, refus, retour en brouillon, scellement, descellement) déclarées comme événements natifs à la manière du module Diffusion, avec pictogramme TimesheetWeek et contenu personnalisable.
- Chemins documentaires centralisés par entité propriétaire et onglet Compatibilité détaillant les dépendances Dolibarr/PHP ainsi que les diagnostics Agenda.
- Onglet « À propos » dédié pour retrouver la version, l'éditeur et les ressources utiles du module.
- README bilingue (FR/EN) pour faciliter le déploiement et l'adoption.

### Installation

1. **Pré-requis** : disposer d'une instance Dolibarr fonctionnelle. Les versions supportées correspondent à celles indiquées dans le fichier `modTimesheetWeek.class.php`.
2. **Déploiement via l'interface** : depuis `Accueil > Configuration > Modules > Déployer un module externe`, importez l'archive `module_timesheetweek-x.y.z.zip` téléchargée sur [Dolistore](https://www.dolistore.com) ou obtenue via votre circuit de diffusion.
3. **Déploiement manuel** : copiez le répertoire du module dans `htdocs/custom/timesheetweek`, puis purgez le cache des modules depuis l'administration Dolibarr.
4. **Activation** : connectez-vous en tant que super administrateur, activez le module dans `Configuration > Modules > Projets/Temps`, puis exécutez le script `sql/update_all.sql` pour ajouter les compteurs aux données existantes.

### Configuration

- Rendez-vous dans `Configuration > Modules > TimesheetWeek` pour activer le masque de numérotation via les commutateurs natifs et sélectionner les modèles PDF souhaités.
- Configurez le scellement automatique (activation, délai et utilisateur responsable) depuis la section dédiée afin de sceller automatiquement les feuilles approuvées.
- Ajustez les options Multicompany via les onglets de configuration dédiés si vous partagez les feuilles de temps entre plusieurs entités.
- Utilisez les pages natives Agenda et Notifications de Dolibarr pour activer les événements automatiques et les notifications liés aux feuilles hebdomadaires.
- Dans la page native Notifications, configurez les événements métier `TIMESHEETWEEK_CREATE`, `TIMESHEETWEEK_SUBMIT`, `TIMESHEETWEEK_APPROVE`, `TIMESHEETWEEK_REFUSE`, `TIMESHEETWEEK_SETDRAFT`, `TIMESHEETWEEK_SEAL`, `TIMESHEETWEEK_UNSEAL` et `TIMESHEETWEEK_DELETE` avec les modèles de type `timesheetweek@timesheetweek`; les anciens modèles `timesheetweek_notification` sont migrés vers ce type visible.
- Consultez l'onglet « Compatibilité » pour vérifier les fonctionnalités disponibles selon la version Dolibarr/PHP courante.
- L'onglet « À propos » récapitule la version du module, l'éditeur et les liens de support.

### Traductions

Les fichiers de traduction sont disponibles dans `langs/en_US`, `langs/fr_FR`, `langs/de_DE`, `langs/es_ES` et `langs/it_IT`. Toute nouvelle chaîne doit être renseignée simultanément dans ces langues conformément aux pratiques Dolibarr.

## 🇬🇧 Overview

TimesheetWeek delivers weekly timesheet management that follows Dolibarr design guidelines. It enhances approval workflows, exposes operational counters (zones, meal allowances, overtime) and keeps the administration area consistent with native modules.

### Main features

- Statut « Scellée » (Sealed status) to lock approved timesheets together with the related permissions.
- Automatic redirect to the existing timesheet when a duplicate creation is attempted.
- Weekly counters for zones and meal allowances with automatic recomputation on each save.
- Dedicated input for daily rate employees with Full day/Morning/Afternoon selectors that automatically convert hours.
- Configurable automatic weekly email reminder (enablement, weekday, time, template) with a dedicated scheduled task and admin test send button.
- Automatic sealing of approved timesheets after a configurable delay through a native Dolibarr scheduled task.
- Stores seal user and seal date in dedicated columns for easier tracking.
- Counter display inside the weekly list plus a « Zone » caption on each daily selector for better input guidance.
- Snapshots contract hours at submission so overtime calculations and PDFs stay aligned even if the employee contract changes later.
- Total row at the bottom of the weekly list to sum hours, zones, meals and expose the validation date column.
- Quick creation shortcut available from the top-right « Add » menu.
- Multicompany compatibility for sharing timesheets and numbering sequences, with dedicated sharing options and native-aligned multi-select filters.
- Entity details shown on lists and cards in Multicompany environments with a badge under the reference when the entity differs.
- Entity-scoped SQL queries and Multicompany filters harmonised with Dolibarr best practices.
- ISO week selector shared between list and card views, now supporting multi-selection to combine several periods.
- Swapped colours for « Scellée » and « Refusée » statuses to match Dolibarr visual cues.
- Fully redesigned setup page inspired by the DiffusionPlans module to drive numbering masks and PDF templates with Dolibarr's graphical and functional patterns.
- Numbering mask selection driven by native toggle switches directly inside Dolibarr's configuration.
- PDF generation available directly from the weekly sheet through the Documents widget, honouring the template configured in the administration area.
- Agenda events and Notifications are exposed in native Dolibarr screens, with substitutions available for email templates.
- Business step notifications (submission, approval, refusal, revert to draft, seal, unseal) are declared as native events like in the Diffusion module, with the TimesheetWeek pictogram and customizable content.
- Agenda, Notifications and navigation history use the stable external element type `timesheetweek@timesheetweek`.
- Native scheduled job settings are preserved when the module is disabled and re-enabled.
- Document paths are centralized on the owner entity and the Compatibility tab details Dolibarr/PHP dependencies and Agenda diagnostics.
- Dedicated « À propos » tab exposing the module version, publisher and handy resources.
- Bilingual (FR/EN) README to streamline rollout and user onboarding.

### Installation

1. **Prerequisites**: a running Dolibarr instance that matches the compatibility range declared in `modTimesheetWeek.class.php`.
2. **Deploy from the GUI**: go to `Home > Setup > Modules > Deploy external module` and upload the `module_timesheetweek-x.y.z.zip` archive from [Dolistore](https://www.dolistore.com) or your distribution channel.
3. **Manual deployment**: copy the module directory into `htdocs/custom/timesheetweek`, then refresh the module cache from Dolibarr's administration area.
4. **Activation**: log in as a super administrator, enable the module from `Setup > Modules > Projects/Timesheets`, and run the `sql/update_all.sql` script so legacy timesheets gain the new counters.

### Configuration

- Visit `Setup > Modules > TimesheetWeek` to switch on the numbering mask and enable the PDF templates you want to expose.
- Configure automatic sealing (enablement, delay, and responsible user) from the dedicated section to seal approved timesheets automatically.
- In Multicompany contexts, tune the sharing preferences through the dedicated configuration tabs.
- Use the native Dolibarr Agenda and Notifications pages to enable automatic events and notifications related to weekly timesheets.
- In the native Notifications page, configure business events `TIMESHEETWEEK_CREATE`, `TIMESHEETWEEK_SUBMIT`, `TIMESHEETWEEK_APPROVE`, `TIMESHEETWEEK_REFUSE`, `TIMESHEETWEEK_SETDRAFT`, `TIMESHEETWEEK_SEAL`, `TIMESHEETWEEK_UNSEAL` and `TIMESHEETWEEK_DELETE` with `timesheetweek@timesheetweek` templates; legacy `timesheetweek_notification` templates are migrated to this visible type.
- Open the Compatibility tab to check feature availability for the current Dolibarr/PHP version.
- The « À propos » tab summarises the module version, publisher and support links.

### Translations

Translation sources are stored under `langs/en_US`, `langs/fr_FR`, `langs/de_DE`, `langs/es_ES` and `langs/it_IT`. Please keep these locales aligned for every new string to stay compatible with Dolibarr's translation workflow.

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and README files are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
