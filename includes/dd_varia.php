<?php
/**
 *
 * @date_cre: Fri 2026-10-30
 * @date_mut: Fri 2026-10-30
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides localized access to platform-managed lookup values.
 *
 */

declare(strict_types=1);

/**
 * Return the request-local cache used for dd_varia lookup results.
 *
 * @return array
 */
function &dd_varia_options_cache(): array
{
    static $cache = [];
    return $cache;
}

/**
 * Clear cached lookup values after a platform administrator changes them.
 *
 * @return void
 */
function dd_varia_clear_options_cache(): void
{
    $cache =& dd_varia_options_cache();
    $cache = [];
}

/**
 * Return localized options directly below a parent in a lookup list.
 *
 * The list root is selected by passing a null parent key. The content column
 * stores an @translation_key@ token, while the returned label is translated.
 *
 * @param string $listKey The lookup-list identifier.
 * @param ?string $parentKey The parent's stable key, or null for root rows.
 * @param bool $includeInactive Whether inactive values should be included.
 * @return array<int, array<string, mixed>>
 */
function dd_varia_get_options(string $listKey, ?string $parentKey = null, bool $includeInactive = false): array
{
    $listKey = strtolower(trim($listKey));
    if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $listKey)) {
        throw new InvalidArgumentException('Invalid dd_varia list key.');
    }
    if ($parentKey !== null) {
        $parentKey = strtolower(trim($parentKey));
        if (!preg_match('/^(_root|[a-z][a-z0-9_-]{0,79})$/', $parentKey)) {
            throw new InvalidArgumentException('Invalid dd_varia parent key.');
        }
    }
    if ($includeInactive) {
        $user = current_user();
        if ($user === null || ($user['company_id'] ?? null) !== null || !is_platform_operator($user)) {
            return [];
        }
    }

    $cache =& dd_varia_options_cache();
    $cacheKey = json_encode([$listKey, $parentKey, $includeInactive], JSON_THROW_ON_ERROR);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $result = pg_query_params(
        db(),
        'SELECT item.id, item.list_key, item."key", item.content, item.parent_id,
                parent."key" AS parent_key, item.info, item.sort_order, item.is_active,
                item.updated_at
         FROM dd_varia AS item
         LEFT JOIN dd_varia AS parent
           ON parent.list_key = item.list_key AND parent.id = item.parent_id
         WHERE item.list_key = $1
           AND (($2::VARCHAR IS NULL AND item.parent_id IS NULL) OR parent."key" = $2)
           AND ($3::BOOLEAN OR item.is_active)
         ORDER BY item.sort_order, item."key", item.id',
        [$listKey, $parentKey, $includeInactive ? 'true' : 'false']
    );
    if (!$result) {
        error_log('Platform lookup values could not be loaded.');
        return $cache[$cacheKey] = [];
    }

    $options = [];
    while ($row = pg_fetch_assoc($result)) {
        $token = (string) $row['content'];
        $translationKey = preg_match('/^@([A-Za-z0-9_.-]{1,120})@$/', $token, $match) === 1
            ? $match[1]
            : $token;
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = $row['is_active'] === 't';
        $row['label'] = fn_t($translationKey);
        $row['translation_key'] = $translationKey;
        $options[] = $row;
    }

    return $cache[$cacheKey] = $options;
}
