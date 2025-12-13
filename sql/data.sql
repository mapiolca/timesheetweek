INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
    VALUES (
        '$conf->entity','timesheetweek','actioncomm_send','fr_FR', 0,NULL, NOW(),
        'Rappel du vendredi soir',
        100,
        1,
        'isModEnabled(\"timesheetweek\")',
        NULL,
        "Rappel Feuilles d\'heures hebodmadaires",
        "Bonjour,<div style=\"margin-left:40px\"><br>Merci de soumettre vos feuilles d\'heures de la semaine pour lundi matin 8h.</div><div style=\"margin-left:80px\"><br>Bon week-end.</div>"
    );
