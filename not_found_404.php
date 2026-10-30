<?php

/**
 *
 * @file        not_found_404.php
 * @author      lm
 * @dateCreated Sat 2018-12-29 21:08:13
 * @dateLastMod Sun 2026-10-25 21:03:53
 *
 */


$template = <<<_EOF
<!-- 251181 b-->

<!doctype html>
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/svg+xml" href="media/favicon.svg">
        <title>not found 404 · Grompil</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
            .error-card { width: min(100%, 38rem); }
        </style>
    </head>
    <body class="bg-light">
        <main class="error-card card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="text-primary fw-semibold mb-2">Grompil</div>
                <h1 class="h3 mb-3">not found</h1>
                <div class="alert border-0 mb-4" role="status">
					<img src="../not_found_404_image.png" alt="not found 404">
                </div>
                <a class="btn btn-outline-secondary ms-2" href="/index.php">start page</a>
            </div>
        </main>
    </body>
</html>

<!-- 251181 e-->
_EOF;

print $template;


exit(1);
