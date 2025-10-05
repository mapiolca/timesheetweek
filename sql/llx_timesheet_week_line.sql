CREATE TABLE llx_timesheet_week_line (
  rowid              INT AUTO_INCREMENT PRIMARY KEY,
  fk_timesheet_week  INT NOT NULL,        -- lien vers llx_timesheet_week
  fk_task            INT NOT NULL,        -- lien vers llx_projet_task
  day_date           DATE NOT NULL,       -- jour concerné
  hours              DECIMAL(6,2) NOT NULL DEFAULT 0, -- nb d'heures (ex: 7.50)
  zone               TINYINT NOT NULL DEFAULT 0,      -- zone de déplacement (1 à 5)
  meal               TINYINT NOT NULL DEFAULT 0,      -- panier repas (0/1)
  note               TEXT,                 -- commentaire optionnel
  
  date_creation      DATETIME DEFAULT CURRENT_TIMESTAMP,
  tms                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat      INT DEFAULT NULL,     -- utilisateur créateur
  fk_user_modif      INT DEFAULT NULL,     -- dernier modificateur

  INDEX idx_timesheet_line_week (fk_timesheet_week),
  INDEX idx_timesheet_line_task (fk_task),
  INDEX idx_timesheet_line_day (day_date),

  CONSTRAINT fk_timesheet_week_line_timesheet
    FOREIGN KEY (fk_timesheet_week) REFERENCES llx_timesheet_week(rowid) ON DELETE CASCADE,
  CONSTRAINT fk_timesheet_week_line_task
    FOREIGN KEY (fk_task) REFERENCES llx_projet_task(rowid) ON DELETE CASCADE
) ENGINE=innodb;
