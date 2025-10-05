-- Copyright (C) 2025 Pierre ARDOIN
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_timesheetweek_timesheetweek(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(50) NOT NULL, 
	fk_user integer NOT NULL, 
	year smallint NOT NULL, 
	week smallint NOT NULL, 
	status smallint NOT NULL, 
	note text, 
	date_creation datetime, 
	date_validation datetime, 
	fk_user_valid integer, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
