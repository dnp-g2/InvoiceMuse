CREATE TABLE IF NOT EXISTS ip_service_properties (
 property_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 client_id INT NOT NULL,
 label VARCHAR(100) NOT NULL DEFAULT '',
 address_1 VARCHAR(150) NOT NULL,
 address_2 VARCHAR(150) NOT NULL DEFAULT '',
 city VARCHAR(100) NOT NULL,
 state VARCHAR(100) NOT NULL,
 zip VARCHAR(20) NOT NULL,
 country VARCHAR(2) NOT NULL DEFAULT 'US',
 active TINYINT NOT NULL DEFAULT 1,
 address_hash CHAR(64) NOT NULL,
 source_reference VARCHAR(40) DEFAULT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 KEY client_active (client_id, active),
 UNIQUE KEY source_property (client_id, source_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ip_property_documents (
 document_type VARCHAR(7) NOT NULL,
 document_id INT NOT NULL,
 client_id INT NOT NULL,
 billing_snapshot LONGTEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 save_state VARCHAR(10) NOT NULL DEFAULT 'ready',
 published TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY (document_type, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ip_property_installation (
 singleton TINYINT NOT NULL PRIMARY KEY,
 invoice_cutoff INT NOT NULL,
 quote_cutoff INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
