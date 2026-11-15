/**
 *
 * @date_cre: Sun 2026-11-15 20:28:00
 * @date_mut: Sun 2026-11-15 20:28:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Adds localized labels for contact types used by the contact manager.
 *
 */
BEGIN;

INSERT INTO ft_substitutions (company_id, "key", "value", code)
VALUES
    (0, 'contact_type_email', 'E-mail', 'NL'),
    (0, 'contact_type_email', 'E-mail', 'FR'),
    (0, 'contact_type_email', 'Email', 'EN'),
    (0, 'contact_type_email', 'E-Mail', 'DE'),
    (0, 'contact_type_phone', 'Telefoon', 'NL'),
    (0, 'contact_type_phone', 'Téléphone', 'FR'),
    (0, 'contact_type_phone', 'Telephone', 'EN'),
    (0, 'contact_type_phone', 'Telefon', 'DE'),
    (0, 'contact_type_mobile', 'Gsm', 'NL'),
    (0, 'contact_type_mobile', 'Mobile', 'FR'),
    (0, 'contact_type_mobile', 'Mobile', 'EN'),
    (0, 'contact_type_mobile', 'Mobiltelefon', 'DE'),
    (0, 'contact_type_fax', 'Fax', 'NL'),
    (0, 'contact_type_fax', 'Fax', 'FR'),
    (0, 'contact_type_fax', 'Fax', 'EN'),
    (0, 'contact_type_fax', 'Fax', 'DE'),
    (0, 'contact_type_website', 'Website', 'NL'),
    (0, 'contact_type_website', 'Site web', 'FR'),
    (0, 'contact_type_website', 'Website', 'EN'),
    (0, 'contact_type_website', 'Webseite', 'DE'),
    (0, 'contact_type_facebook', 'Facebook', 'NL'),
    (0, 'contact_type_facebook', 'Facebook', 'FR'),
    (0, 'contact_type_facebook', 'Facebook', 'EN'),
    (0, 'contact_type_facebook', 'Facebook', 'DE'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'NL'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'FR'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'EN'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'DE'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'NL'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'FR'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'EN'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'DE'),
    (0, 'contact_type_other', 'Overig contact', 'NL'),
    (0, 'contact_type_other', 'Autre contact', 'FR'),
    (0, 'contact_type_other', 'Other contact', 'EN'),
    (0, 'contact_type_other', 'Sonstiger Kontakt', 'DE')
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value";

COMMIT;