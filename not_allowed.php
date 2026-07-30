<?

/**
 *
 * @file        not_allowed.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 14:52:23
 * @dateLastMod Thu 2026-07-30 14:52:34
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


$__id = time();

print <<< _EOT_
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>* forbidden *</title>
  </head>
  <body>
  <pre>
  #892938 - unix epoch:$__id
  Operation not allowed.
  </pre>
  </body>
</html>
_EOT_;

exit(1);
