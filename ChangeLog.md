# CHANGELOG MODULE TIMESHEETWEEK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.6
- Réorganise le menu gauche pour afficher « Nouvelle feuille » avant « Liste ». / Reorders the left menu to display "New sheet" before "List".
- Ajoute les entrées « TimesheetWeek » dans les menus principaux Agenda et Projet. / Adds the "TimesheetWeek" entries under the Agenda and Project main menus.
- Conserve la sélection de limite dans la liste lors des filtrages. / Keeps the list limit selection when filters are submitted.
- Rafraîchit automatiquement la liste lors d'un changement de nombre de lignes. / Automatically refreshes the list when the line count selector changes.
- Aligne le sélecteur de limite sur l'implémentation DiffusionPlans pour respecter l'expérience Dolibarr. / Aligns the limit selector with the DiffusionPlans implementation to honour Dolibarr experience.

## 1.0.5
- Renomme le script SQL d'installation en `llx_timesheet_week.sql` pour garantir la création de base lors de l'activation du module. / Renames the install SQL script to `llx_timesheet_week.sql` to ensure database creation when the module is enabled.

## 1.0.4
- Assure le chargement de la librairie admin Dolibarr avant l'appel à `dolibarr_set_const` dans le compteur FH. / Ensures the Dolibarr admin library loads before calling `dolibarr_set_const` in the FH counter.

## 1.0.3
- Corrige l'initialisation du compteur FH en chargeant les librairies Dolibarr adéquates. / Fixes FH counter initialisation by loading the appropriate Dolibarr libraries.
- Sécurise les droits hiérarchiques pour lire, créer, modifier, supprimer et valider les feuilles des seuls collaborateurs gérés. / Secures hierarchy rights to read, create, update, delete and validate only managed employees' timesheets.
- Restreint la liste hebdomadaire aux salariés autorisés et ajoute des filtres compatibles Multicompany. / Restricts the weekly list to authorised employees and adds Multicompany-compatible filters.
- Limite le filtre salarié aux collaborateurs visibles par l'utilisateur et respecte le périmètre Multicompany. / Limits the employee filter to collaborators visible to the user and honours the Multicompany scope.
- Protège l'endpoint AJAX contre les mises à jour hors périmètre hiérarchique avec un retour localisé. / Protects the AJAX endpoint against out-of-scope updates with a localised response.
- Corrige l'affichage en renvoyant une erreur JSON cohérente lors d'un refus de permission. / Fixes the display by returning a consistent JSON error when permission is denied.
- Déclenche la vérification d'accès dès le chargement de la liste pour stopper immédiatement les utilisateurs non autorisés. / Triggers the access check as soon as the list loads to stop unauthorised users immediately.
- Corrige l'affichage des photos et statuts utilisateurs dans les listes, filtres et formulaires en respectant le périmètre hiérarchique. / Fixes user pictures and statuses within lists, filters and forms while respecting the managerial scope.
- Masque l'identifiant interne dans le filtre salarié pour un rendu aligné sur Dolibarr. / Hides the internal ID in the employee filter for a Dolibarr-aligned rendering.
- Corrige le filtre salarié pour éviter les erreurs SQL tout en respectant le périmètre visible. / Fixes the employee filter to prevent SQL errors while respecting the visible scope.
- Évite le double affichage des menus lors d'un refus de permission sur la fiche hebdomadaire. / Prevents double menu rendering when the weekly card access is denied.
- Affiche les avatars des salariés et validateurs sur la fiche hebdomadaire pour un rendu cohérent. / Displays employee and validator avatars on the weekly card for a consistent rendering.

## 1.0.2
- Corrige un problème dans les options de partage du module "Multicompany". / Fixes an issue in the "Multicompany" module sharing options.
- Repositionne les totaux correctement. / Correctly repositions totals.
- Inversion de position de "Nouvelle Feuille d'heure" et "Liste" dans le menu gauche. / Swaps the position of "New Timesheet" and "List" in the left menu.
- Corrige un problème pouvant masquer les tâches clôturées dans les Fiches passées. / Fix an issue that could hide closed tasks in passed timesheet.

## 1.0.1

- Affichage complet des jours de la semaine sur la feuille hebdomadaire grâce aux nouvelles traductions. / Full display of weekdays on the weekly sheet thanks to new translations.
- Alignement vertical des cellules de jours, zones, paniers, temps et totaux pour une lecture homogène. / Vertical alignment of day, zone, meal, time and total cells for consistent readability.
- Alignement centré (horizontal et vertical) de toutes les cellules de saisie du jour au total pour une grille plus lisible. / Centered alignment (horizontal and vertical) of every entry cell from day to total for a more readable grid.

## 1.0

- Initial version. / Initial version.
- Ajout du statut "Scellée" / "Sealed" et des permissions associées. / Added "Sealed" status and associated permissions.
- Ajout des compteurs de zones et de paniers dans l'entête des feuilles hebdomadaires. / Added zone and meal counters to weekly timesheet headers.
- Recalcul automatique des compteurs de zones et de paniers à chaque enregistrement d'une feuille hebdomadaire. / Automatic recalculation of zone and meal counters at each weekly timesheet save.
- Affichage des compteurs de zones et de paniers dans la liste des feuilles hebdomadaires. / Display of zone and meal counters in the weekly timesheet list.
- Ligne de total dans la liste hebdomadaire pour additionner heures, heures supplémentaires, zones et paniers, plus affichage de la date de validation. / Total row in weekly list to sum hours, overtime, zones and meals, plus validation date display.
- Affichage du libellé "Zone" devant chaque sélecteur quotidien. / Display of "Zone" label before each daily selector.
- Ajout de la traduction "Meals" en "Repas". / Added translation "Meals" to "Repas".
- Affichage des jours de la semaine en toutes lettres avec leur traduction complète. / Display of weekdays in full letters with complete translation.
- Ajout du script de mise à jour SQL (`sql/update_all.sql`) pour créer les compteurs hebdomadaires sur les données existantes. / Added SQL update script (`sql/update_all.sql`) to create weekly counters on existing data.
- Redirection automatique vers la feuille existante en cas de création en doublon. / Automatic redirect to the existing sheet when attempting a duplicate creation.
- Ajout d'un accès rapide à la création de feuille d'heures via le menu supérieur. / Added quick access to timesheet creation via the top menu.
- Compatibilité Multicompany pour partager les feuilles de temps et leur numérotation. / Multicompany compatibility to share weekly timesheets and numbering sequences.
- Inscription et retrait automatiques de la configuration Multicompany lors de l'activation/désactivation du module. / Automatic registration and cleanup of the Multicompany configuration on module enable/disable.
- Affichage de l'entité dans la liste et la fiche lorsqu'on utilise Multicompany, avec badge natif sous la référence en cas d'entité différente. / Display entity details on list and card when using Multicompany, with a native badge under the reference if a different entity.
- Harmonisation du filtre de semaine avec celui de la fiche via le sélecteur ISO. / Harmonized week filter with the card using the ISO selector.
- Passage du filtre de semaine en multi-sélection pour regrouper plusieurs périodes dans la liste. / Week filter upgraded to multi-select to group several periods in the list.
- Sécurisation des requêtes SQL sur les feuilles et lignes par entité pour Multicompany. / Secured timesheet and line SQL queries with entity scoping for Multicompany.
- Filtre Multicompany de l'environnement aligné sur le multiselect natif dans la liste. / Multicompany environment filter aligned with the native multiselect in the list.
- Réorganisation des options de partage Multicompany pour séparer les feuilles et la numérotation avec les pictogrammes adaptés. / Reorganised Multicompany sharing options to separate sheets and numbering with adapted pictograms.
- Inversion des couleurs des statuts "Scellée" et "Refusée" pour reprendre les repères Dolibarr. / Swapped colors of "Sealed" and "Refused" statuses to match Dolibarr visual cues.
- Refonte de la page de configuration en suivant le modèle DiffusionPlans pour harmoniser la gestion des masques de numérotation et des modèles PDF avec Dolibarr. / Setup page redesigned following the DiffusionPlans model to harmonize numbering masks and PDF templates management with Dolibarr.
- Activation des masques de numérotation via des commutateurs Dolibarr natifs. / Numbering masks activated through native Dolibarr switches.
- Ajout d'un onglet « À propos » récapitulant version, éditeur et ressources du module. / Added an « About » tab listing version, publisher and module resources.
- README bilingue (FR/EN) entièrement mis à jour. / Fully refreshed bilingual (FR/EN) README.
- Notification d'approbation en français corrigée pour utiliser l'accent « approuvée » sans entité HTML. / French approval notification updated to use the plain "approuvée" accent instead of an HTML entity.
