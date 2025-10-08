# CHANGELOG MODULE TIMESHEETWEEK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Non publié

- Ajout des compteurs de zones et de paniers dans l'entête des feuilles hebdomadaires.
- Recalcul automatique des compteurs de zones et de paniers à chaque enregistrement d'une feuille hebdomadaire.
- Affichage des compteurs de zones et de paniers dans la liste des feuilles hebdomadaires.
- Affichage du libellé "Zone" devant chaque sélecteur quotidien.
- Ajout de la traduction "Meals" en "Repas".
- Ajout du script de mise à jour SQL (`sql/update_all.sql`) pour créer les compteurs hebdomadaires sur les données existantes.
- Redirection automatique vers la feuille existante en cas de création en doublon / Automatic redirect to the existing sheet when attempting a duplicate creation.
- Ajout d'un accès rapide à la création de feuille d'heures via le menu supérieur.
- Compatibilité Multicompany pour partager les feuilles de temps et leur numérotation / Multicompany compatibility to share weekly timesheets and numbering sequences.
- Inscription et retrait automatiques de la configuration Multicompany lors de l'activation/désactivation du module / Automatic registration and cleanup of the Multicompany configuration on module enable/disable.
- Affichage de l'entité dans la liste et la fiche lorsqu'on utilise Multicompany / Display entity details on list and card when Multicompany is active.
- Harmonisation du filtre de semaine avec celui de la fiche via le sélecteur ISO / Harmonized week filter with the card using the ISO selector.
- Passage du filtre de semaine en multi-sélection pour regrouper plusieurs périodes dans la liste / Week filter upgraded to multi-select to group several periods in the list.

## 1.0

- Ajout du statut "Scellée" / "Sealed" et des permissions associées.
- Initial version
