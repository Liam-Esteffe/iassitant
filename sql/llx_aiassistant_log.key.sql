-- Copyright (C) 2026 Liam Esteffe
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_aiassistant_log ADD INDEX idx_aiassistant_log_datec (datec);
ALTER TABLE llx_aiassistant_log ADD INDEX idx_aiassistant_log_fk_user (fk_user);
ALTER TABLE llx_aiassistant_log ADD INDEX idx_aiassistant_log_entity (entity);
ALTER TABLE llx_aiassistant_log ADD INDEX idx_aiassistant_log_action_type (action_type);
