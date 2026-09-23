-- Isolated invoiceplane_test database only; also requires the property integration fixtures.
INSERT INTO ip_clients (client_name,client_surname,client_active,client_date_created,client_date_modified,client_phone,client_email)
SELECT 'Directory Fixture Active','',1,NOW(),NOW(),'+12025550143','directory@example.invalid'
WHERE NOT EXISTS (SELECT 1 FROM ip_clients WHERE client_name='Directory Fixture Active');
INSERT INTO ip_clients (client_name,client_surname,client_active,client_date_created,client_date_modified)
SELECT 'Directory Fixture Inactive','',0,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM ip_clients WHERE client_name='Directory Fixture Inactive');
