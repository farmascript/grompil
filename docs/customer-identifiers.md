# Customer identifiers

Identifiers that are not part of the ordinary customer profile belong in
`dt_customer_identifiers`, not in `bt_customers`. Each row is scoped to one
company and customer and has an `identifier_type_key`, so other typed
identifiers can be added later without expanding the customer master table.

The Belgian National Register number (`national_register`) is encrypted with
libsodium before it reaches the database. The table contains the ciphertext
and a separately derived keyed HMAC fingerprint used only to detect duplicates
within a company; it does not contain the number in plaintext. The dispensing customer editor
decrypts it only to prefill the field, and the helper
`grompil_customer_national_register_number_get()` is the application interface
for later authorized workflows such as electronic-prescription processing.

## Per-installation encryption key

Before using this feature, add a dedicated 32-byte Sodium secretbox key to the
ignored `settings_local.php` file for each installation. Generate a fresh key
with:

```sh
php -r "echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

Add the generated value to the settings array:

```php
'customer_identifier_encryption_key' => 'BASE64_KEY_FOR_THIS_INSTALLATION',
```

Use a different key for local, test, and production environments. Never commit
the key or paste it into logs. Store a protected backup with the installation's
database backup: if the key is lost, existing encrypted identifiers cannot be
decrypted. Rotating the key requires decrypting and re-encrypting every stored
identifier before replacing the setting.

## Database setup

Run `migrations/20261127_131915_customer_identifiers.sql` once as the database
owner before deploying code that stores or reads identifiers. The migration
adds company-scoped row-level security and grants the application role access
to the table. New databases receive the same objects from `schema.sql`.

The current RRN field remains optional. A supplied number is validated, used
to derive the date of birth, then saved encrypted with the customer record in
the same database transaction. A repeated RRN for another customer in the same
company is rejected. Leaving the field blank stores no number for a new
customer; on edit, the existing value is shown for review.
