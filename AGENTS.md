# Consignes locales — TimesheetWeek

Ce module suit le référentiel canonique du dépôt `mapiolca/dolibarr_agent.md`,
complété par les présentes consignes locales.

## Conformité des paquets Dolibarr et Dolistore

Toujours respecter l'ensemble des [règles officielles de packaging et de validation
Dolistore](https://wiki.dolibarr.org/index.php/Modules_-_Packaging_rules_and_Dolistore_validation_rules).
Consulter leur version courante avant toute préparation ou livraison de paquet.

- Contrôler le ZIP réellement livré : nom `module_timesheetweek-X.Y.Z.zip`,
  racine unique `timesheetweek/` et version cohérente avec le descripteur.
- Tout point d'entrée chargeant `main.inc.php` ou `master.inc.php`, y compris
  un aperçu ou un test présent dans le paquet, doit proposer au moins deux
  tentatives adaptées à sa profondeur pour fonctionner sous `htdocs/timesheetweek`
  et `htdocs/custom/timesheetweek`. Reprendre le pattern ModuleBuilder natif.
- Vérifier les deux dispositions et l'arrêt propre si l'environnement est absent.
- Avant publication Dolistore, tester le déploiement du ZIP par l'installateur
  natif. Distinguer les contrôles locaux de l'acceptation réelle par Dolistore.

Cette référence engage toutes les règles officielles applicables ; les points
ci-dessus ne constituent pas une liste exhaustive.
