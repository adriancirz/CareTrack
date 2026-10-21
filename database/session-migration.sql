CREATE TABLE app_sessions (
    session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_sessions_activity (last_activity)
) ENGINE=InnoDB;
