-- ============================================================================
-- Copyright (C) 2025 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ============================================================================

CREATE TABLE llx_timesheet_week (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(50) NOT NULL UNIQUE,
  fk_user INT NOT NULL,
  year SMALLINT NOT NULL,
  week SMALLINT NOT NULL,
  status SMALLINT NOT NULL DEFAULT 0,
  note TEXT,
  date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_validation DATETIME DEFAULT NULL,
  fk_user_valid INT DEFAULT NULL,
  tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

CREATE TABLE llx_timesheet_weekline (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_timesheet_week INT NOT NULL,
    fk_task INT NOT NULL,
    work_date DATE NOT NULL,
    hours DOUBLE(24,8) DEFAULT 0,
    zone INT DEFAULT NULL,
    panier TINYINT(1) DEFAULT 0,
    note TEXT,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
