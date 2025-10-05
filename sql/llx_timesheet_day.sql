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


CREATE TABLE llx_timesheet_day (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  fk_timesheet_week INT NOT NULL,
  date_day DATE NOT NULL,
  hours DECIMAL(5,2) DEFAULT 0,
  zone TINYINT UNSIGNED DEFAULT 1,
  meal TINYINT(1) DEFAULT 0,
  fk_task INT DEFAULT NULL,
  comment VARCHAR(255),
  CONSTRAINT fk_tsweek FOREIGN KEY (fk_timesheet_week) REFERENCES llx_timesheet_week(rowid) ON DELETE CASCADE
) ENGINE=innodb;