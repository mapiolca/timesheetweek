INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,joinfiles,topic,content)
    VALUES (
        0,'timesheetweek','actioncomm_send','fr_FR', 0,NULL, NOW(),
        'Rappel du vendredi soir',
        100,
        'isModEnabled("timesheetweek")',
        NULL,
        "Rappel Feuilles d\'heures hebodmadaires",
        "Bonjour,<br /> Merci de soumettre vos feuilles d\'heures de la semaine pour lundi matin 8h.<br />Bon week-end.");
