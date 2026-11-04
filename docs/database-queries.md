# Centrale databasequery-helper

`includes/database_queries.php` bevat `db_query_params($operation, $sql, $params)`. De helper gebruikt dezelfde PostgreSQL-verbinding als de rest van de applicatie. Daardoor blijft de bestaande tenant- en row-level-securitycontext op die verbinding van toepassing.

Gebruik een stabiele, herkenbare bewerkingsnaam als `$operation`, bijvoorbeeld `dd_varia.options`. Bij een PostgreSQL-fout registreert de helper die naam en de SQLSTATE in de serverlog en gooit hij `GrompilDatabaseQueryException`. De helper logt geen SQL-parameters en kiest zelf geen gebruikersmelding; de aanroepende module bepaalt hoe de fout functioneel wordt afgehandeld.

Voorbeeld:

```php
try {
    $result = db_query_params(
        'dd_varia.options',
        'SELECT id FROM dd_varia WHERE list_key = $1',
        [$listKey]
    );
} catch (GrompilDatabaseQueryException) {
    // Kies hier de passende afhandeling voor deze module.
}
```

De helper wordt geleidelijk ingevoerd. `dd_varia_get_options()` gebruikt hem als eerste proef; overige `pg_query_params()`-aanroepen zijn nog niet omgezet.
