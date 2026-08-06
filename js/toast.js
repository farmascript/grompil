/**
 *
 * @file        toast.js
 * @author      lm
 * @dateCreated Wed 2026-08-05 18:28:15
 * @dateLastMod Wed 2026-08-05 21:22:42
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".toast").forEach(toast => {
        bootstrap.Toast.getOrCreateInstance(toast).show();
    });
});
