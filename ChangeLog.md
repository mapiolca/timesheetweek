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

## 1.0

- Ajout du statut "Scellée" / "Sealed" et des permissions associées.
- Initial version
