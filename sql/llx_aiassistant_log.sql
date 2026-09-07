-- Copyright (C) 2026 Liam Esteffe
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_aiassistant_log(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity INTEGER DEFAULT 1 NOT NULL,
	datec DATETIME NOT NULL,
	fk_user INTEGER NOT NULL,
	question VARCHAR(255),
	action_type VARCHAR(64) NOT NULL,
	payload_summary TEXT,
	status VARCHAR(16) NOT NULL,
	error_message TEXT,
	object_type VARCHAR(32),
	fk_object INTEGER,
	object_ref VARCHAR(128)
) ENGINE=innodb;
