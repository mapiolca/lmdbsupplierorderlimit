-- Commercial approval snapshot. Native orders remain the source of live business data.
CREATE TABLE IF NOT EXISTS llx_lmdbsupplierorderlimit_consumption
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_supplier_order integer NOT NULL,
	fk_user integer NULL,
	fk_project integer NULL,
	date_approval datetime NULL,
	snapshot_amount_ht decimal(24,8) NOT NULL,
	active tinyint DEFAULT 1 NOT NULL,
	unresolved tinyint DEFAULT 0 NOT NULL,
	UNIQUE KEY uk_lmdbsol_consumption_order (entity, fk_supplier_order),
	INDEX idx_lmdbsol_consumption_user (entity, fk_user, active, date_approval),
	INDEX idx_lmdbsol_consumption_project (fk_project, active, entity),
	INDEX idx_lmdbsol_consumption_unresolved (entity, unresolved, active)
) ENGINE=innodb;
