# CHANGELOG MODULE TIMESHEETWEEK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

- Ajout du statut "Scellée" / "Sealed" et des permissions associées.
- Initial version.
- Ajout des compteurs de zones et de paniers dans l'entête des feuilles hebdomadaires.
- Recalcul automatique des compteurs de zones et de paniers à chaque enregistrement d'une feuille hebdomadaire.
- Affichage des compteurs de zones et de paniers dans la liste des feuilles hebdomadaires.
- Ligne de total dans la liste hebdomadaire pour additionner heures, heures supplémentaires, zones et paniers, plus affichage de la date de validation.
- Affichage du libellé "Zone" devant chaque sélecteur quotidien.
- Ajout de la traduction "Meals" en "Repas".
- Ajout du script de mise à jour SQL (`sql/update_all.sql`) pour créer les compteurs hebdomadaires sur les données existantes.
- Redirection automatique vers la feuille existante en cas de création en doublon / Automatic redirect to the existing sheet when attempting a duplicate creation.
- Ajout d'un accès rapide à la création de feuille d'heures via le menu supérieur.
- Compatibilité Multicompany pour partager les feuilles de temps et leur numérotation / Multicompany compatibility to share weekly timesheets and numbering sequences.
- Inscription et retrait automatiques de la configuration Multicompany lors de l'activation/désactivation du module / Automatic registration and cleanup of the Multicompany configuration on module enable/disable.
- Affichage de l'entité dans la liste et la fiche lorsqu'on utilise Multicompany, avec badge natif sous la référence en cas d'entité différente / Display entity details on list and card when Multicompany is active, with a native badge under the reference when the entity differs.
- Harmonisation du filtre de semaine avec celui de la fiche via le sélecteur ISO / Harmonized week filter with the card using the ISO selector.
- Passage du filtre de semaine en multi-sélection pour regrouper plusieurs périodes dans la liste / Week filter upgraded to multi-select to group several periods in the list.
- Sécurisation des requêtes SQL sur les feuilles et lignes par entité pour Multicompany / Secured timesheet and line SQL queries with entity scoping for Multicompany.
- Filtre Multicompany de l'environnement aligné sur le multiselect natif dans la liste / Multicompany environment filter aligned with the native multiselect in the list.
- Réorganisation des options de partage Multicompany pour séparer les feuilles et la numérotation avec les pictogrammes adaptés / Reorganised Multicompany sharing options to separate sheets and numbering with suitable pictograms.
- Inversion des couleurs des statuts "Scellée" et "Refusée" pour reprendre les repères Dolibarr / Swapped colors of "Sealed" and "Refused" statuses to match Dolibarr visual cues.
- Refonte de la page de configuration pour harmoniser la gestion des masques de numérotation et des modèles PDF avec Dolibarr / Setup page redesigned to align numbering masks and PDF templates with Dolibarr standards.
- README bilingue (FR/EN) entièrement mis à jour / Fully refreshed bilingual (FR/EN) README.
- Notification d'approbation en français corrigée pour utiliser l'accent « approuvée » sans entité HTML / French approval notification updated to use the plain "approuvée" accent instead of an HTML entity.
