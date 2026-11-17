-- Anonymise a prod dump for local dev. Run after import, BEFORE the wordpress
-- container serves a page. Then run search-replace + plugin deactivation (db/README.md).
-- Kept: AHPRA numbers (user_login / ahpra_id), state/city/zip, course + form answers.
-- Admins keep their logins; every admin password becomes 'staging123'.

-- Users
UPDATE tbstwp_users SET
  user_email = CONCAT('user', ID, '@example.invalid'),
  user_url   = '',
  user_pass  = '$P$Blocalhashnotvalidforlogin00000.';

UPDATE tbstwp_users u
JOIN tbstwp_usermeta m ON m.user_id = u.ID AND m.meta_key = 'tbstwp_capabilities'
SET u.user_pass = MD5('staging123')
WHERE m.meta_value LIKE '%administrator%';

UPDATE tbstwp_users u
LEFT JOIN tbstwp_usermeta m ON m.user_id = u.ID AND m.meta_key = 'tbstwp_capabilities' AND m.meta_value LIKE '%administrator%'
SET u.display_name  = CONCAT('User ', u.ID),
    u.user_nicename = CONCAT('user-', u.ID),
    u.user_login    = IF(u.user_login LIKE '%@%', CONCAT('user', u.ID), u.user_login)
WHERE m.user_id IS NULL;

UPDATE tbstwp_usermeta SET meta_value = 'User'                 WHERE meta_key = 'first_name';
UPDATE tbstwp_usermeta SET meta_value = user_id                WHERE meta_key = 'last_name';
UPDATE tbstwp_usermeta SET meta_value = CONCAT('user', user_id) WHERE meta_key = 'nickname';
UPDATE tbstwp_usermeta SET meta_value = ''
  WHERE meta_key IN ('description', 'street_address', 'phone_number', 'mobile', 'ahpra_details');
UPDATE tbstwp_usermeta SET meta_value = CONCAT('Practice ', user_id) WHERE meta_key = 'practice_name';
UPDATE tbstwp_usermeta SET meta_value = CONCAT('user', user_id, '@example.invalid')
  WHERE meta_value LIKE '%@%.%' AND meta_value NOT LIKE '% %' AND meta_value NOT LIKE '%{%';

-- Formidable entries: identifying fields only, answers kept
UPDATE tbstwp_frm_item_metas im
JOIN tbstwp_frm_fields f ON f.id = im.field_id
SET im.meta_value = CONCAT('entry', im.item_id, '@example.invalid')
WHERE f.type = 'email';

UPDATE tbstwp_frm_item_metas im
JOIN tbstwp_frm_fields f ON f.id = im.field_id
SET im.meta_value = CONCAT('redacted-', im.item_id)
WHERE f.type IN ('phone', 'name')
   OR f.id IN (95, 662, 1542)
   OR (f.type IN ('text', 'textarea', 'hidden')
       AND LOWER(f.name) REGEXP 'name|surname|address|street|suburb|phone|mobile|email|practice|pharmacy|clinic|ahpra');

UPDATE tbstwp_frm_item_metas SET meta_value = 'redacted@example.invalid'
  WHERE meta_value LIKE '%@%.%' AND meta_value NOT LIKE '% %' AND meta_value NOT LIKE '%{%';

UPDATE tbstwp_frm_item_metas
SET meta_value = REGEXP_REPLACE(meta_value, '[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+\\.[A-Za-z0-9.-]+', 'redacted@example.invalid')
WHERE meta_value LIKE '%@%.%' AND meta_value NOT LIKE 'a:%';

UPDATE tbstwp_frm_items SET name = CONCAT('Entry ', id), ip = '';

-- Other personal data
UPDATE tbstwp_comments SET comment_author_email = '', comment_author_IP = '';
UPDATE tbstwp_dlm_order_customer SET first_name = '', last_name = '', email = '', phone = '',
  address_1 = '', address_2 = '', ip_address = '';
UPDATE tbstwp_mlw_results SET name = '', email = '', phone = '', user_ip = '';
UPDATE tbstwp_learndash_pro_quiz_toplist SET name = '', email = '', ip = '';
UPDATE tbstwp_hcp_consent_log SET ip_address = '', user_agent = '';
UPDATE tbstwp_rcp_customers SET ips = '';
UPDATE tbstwp_redirection_404 SET ip = NULL;

-- Logs and mail queues (contain recipients and bodies)
TRUNCATE tbstwp_wpmailsmtp_emails_log;
TRUNCATE tbstwp_wpmailsmtp_debug_events;
TRUNCATE tbstwp_wpml_mails;
TRUNCATE tbstwp_ld_notifications_delayed_emails;
TRUNCATE tbstwp_wfhits;
TRUNCATE tbstwp_wflogins;
TRUNCATE tbstwp_wfblockediplog;

-- Credentials and licence keys (LearnDash key is single-site, must never activate locally)
UPDATE tbstwp_options SET option_value = ''
WHERE option_name IN (
  'wp_mail_smtp', 'tbstwp_mail_smtp', 'mailserver_pass', 'tinypng_api_key',
  'frm_api_key', 'frm_connect_token', 'mcp_jwt_secret', 'tbstwp_api_key',
  'object_sync_for_salesforce_consumer_key', 'object_sync_for_salesforce_consumer_secret',
  'mwp_communication_keys', 'mwp_service_key', 'mwp_potential_key', 'mwp_public_keys',
  'acf_pro_license', 'nss_plugin_license_sfwd_lms', 'nss_plugin_license_email_sfwd_lms',
  'lw_harbor_unified_license_key', 'learndash_hub_license_result',
  'learndash_legacy_license_keys_last_known_good', 'learndash_license_checker_last_known_good',
  'ithemes-updater-keys', 'frmpro-credentials', 'imagify_settings'
);

UPDATE tbstwp_options SET option_value = 'admin@example.invalid'
WHERE option_name IN ('admin_email', 'new_admin_email', 'mailserver_login', 'tinypng_email_address');

-- Wordfence emails an alert on deactivation; blank its recipients first
UPDATE tbstwp_wfconfig SET val = '' WHERE name IN ('alertEmails', 'lastAdminLogin');

DELETE FROM tbstwp_options WHERE option_name = 'rewrite_rules';
