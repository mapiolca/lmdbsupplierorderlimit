-- Transactional mutexes. Keys: entity:<id> and project:<id>; entity is the scope owner.
CREATE TABLE IF NOT EXISTS llx_lmdbsupplierorderlimit_lock
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	reconciled tinyint DEFAULT 0 NOT NULL,
	scope_key varchar(64) NOT NULL,
	UNIQUE KEY uk_lmdbsol_lock_scope (scope_key),
	INDEX idx_lmdbsol_lock_entity (entity)
) ENGINE=innodb;
