# Centrale databasequery-helper

`includes/database_queries.php` bevat `db_query_params($operation, $sql, $params, $connection = null)` en `db_query_raw($operation, $sql, $connection = null)`. Zonder expliciete verbinding gebruiken ze de gedeelde PostgreSQL-verbinding van de applicatie. Daardoor blijft de bestaande tenant- en row-level-securitycontext op die verbinding van toepassing. Een expliciete verbinding is bedoeld voor geïsoleerde CLI-hulpscripts die bijvoorbeeld tijdelijke tabellen gebruiken. `db_query_raw()` is alleen bedoeld voor statements zonder parameters of scripts met meerdere SQL-statements; gewone queries gebruiken `db_query_params()`.

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

`dd_varia_get_options()` was de eerste proef. Alle application queries in `src/` en `includes/` gebruiken nu de centrale helpers. Ook de vertaalscripts, de templatecontrole, de databaseverbindingsprobe en de password-reset fixture gebruiken ze. De twee helpers zelf zijn de enige bestanden die `pg_query_params()` respectievelijk `pg_query()` rechtstreeks aanroepen.
