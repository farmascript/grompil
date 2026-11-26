<?php
/**
 * Multilingual release notes for authenticated Grompil users.
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/mailer.php';
require_once GROMPIL_ROOT . '/includes/dokuwiki.php';

$user = require_login();
$language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
    $language = 'NL';
}
$languages = ['NL', 'FR', 'EN', 'DE'];
$isPublisher = is_platform_operator($user);
$notice = '';
$error = '';
$subscribed = false;
$postedMessages = [];
$postedWikiPageId = '';
$email = trim((string) ($user['email'] ?? ''));

$translateMessage = static function (array $messages, string $preferred): array {
    foreach ([$preferred, 'NL', 'EN', 'FR', 'DE'] as $candidate) {
        $message = $messages[$candidate] ?? null;
        if (is_array($message) && isset($message['html']) && is_string($message['html'])) {
            $html = fn_releaseNotesSanitizeHtml($message['html']);
            $text = fn_releaseNotesPlainText($html);
            if ($text !== '') {
                return ['html' => $html, 'text' => $text];
            }
        } elseif (is_string($message) && trim($message) !== '') {
            $text = trim($message);
            return ['html' => fn_releaseNotesLegacyHtml($text), 'text' => $text];
        }
    }
    foreach ($messages as $message) {
        if (is_array($message) && isset($message['html']) && is_string($message['html'])) {
            $html = fn_releaseNotesSanitizeHtml($message['html']);
            $text = fn_releaseNotesPlainText($html);
            if ($text !== '') {
                return ['html' => $html, 'text' => $text];
            }
        } elseif (is_string($message) && trim($message) !== '') {
            $text = trim($message);
            return ['html' => fn_releaseNotesLegacyHtml($text), 'text' => $text];
        }
    }
    return ['html' => '', 'text' => ''];
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
    if ($action === 'subscribe' || $action === 'unsubscribe') {
        try {
            if ($action === 'subscribe') {
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $error = 'release_notes_subscription_need_email';
                } else {
                    db_query_params(
                        'release_notes.subscription_add',
                        'INSERT INTO dt_user_release_subscriptions (user_code, language_code, subscribed_at)
                         VALUES ($1, $2, now())
                         ON CONFLICT (user_code) DO UPDATE
                         SET language_code = EXCLUDED.language_code, subscribed_at = now()',
                        [(string) $user['user_code'], $language]
                    );
                    $notice = 'release_notes_subscribed';
                }
            } else {
                db_query_params(
                    'release_notes.subscription_remove',
                    'DELETE FROM dt_user_release_subscriptions WHERE user_code = $1',
                    [(string) $user['user_code']]
                );
                $notice = 'release_notes_unsubscribed';
            }
        } catch (GrompilDatabaseQueryException) {
            $error = 'release_notes_subscription_failed';
        }
    } elseif ($action === 'publish' && $isPublisher) {
        $wikiPageId = is_scalar($_POST['wiki_page_id'] ?? null)
            ? trim((string) $_POST['wiki_page_id']) : '';
        $postedWikiPageId = $wikiPageId;
        $messages = [];
        $hasActiveLanguage = false;
        $messageTooLong = false;
        foreach ($languages as $code) {
            $raw = $_POST['message_' . strtolower($code)] ?? '';
            $messageHtml = is_scalar($raw) ? fn_releaseNotesSanitizeHtml((string) $raw) : '';
            $messageText = fn_releaseNotesPlainText($messageHtml);
            $postedMessages[$code] = $messageHtml;
            $messageLength = function_exists('mb_strlen') ? mb_strlen($messageText, 'UTF-8') : strlen($messageText);
            $messageTooLong = $messageTooLong || $messageLength > 2000;
            if ($messageText !== '') {
                $messages[$code] = ['format' => 'html', 'html' => $messageHtml, 'text' => $messageText];
                if ($code === $language) {
                    $hasActiveLanguage = true;
                }
            }
        }
        $messagesJson = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $wikiPageIdIsValid = $wikiPageId === '' || preg_match('/^[0-9]{1,12}$/D', $wikiPageId) === 1;
        if (!$hasActiveLanguage || $messageTooLong || !$wikiPageIdIsValid || !is_string($messagesJson)) {
            $error = 'release_notes_publish_invalid';
        } else {
            try {
                $result = db_query_params(
                    'release_notes.publish',
                    'INSERT INTO ft_release_notes (messages, wiki_page_id, created_by_user_code)
                     VALUES ($1::jsonb, $2, $3)
                     RETURNING id',
                    [$messagesJson, $wikiPageId === '' ? null : $wikiPageId, (string) $user['user_code']]
                );
                $noteId = (int) pg_fetch_result($result, 0, 'id');
                $mailFailures = 0;
                try {
                    $recipientsResult = db_query_params(
                        'release_notes.recipients',
                        'SELECT email_address, language_code FROM fn_release_update_recipients()'
                    );
                    while ($recipient = pg_fetch_assoc($recipientsResult)) {
                        $recipientEmail = trim((string) ($recipient['email_address'] ?? ''));
                        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
                            continue;
                        }
                        $recipientLanguage = strtoupper((string) ($recipient['language_code'] ?? 'NL'));
                        if (!in_array($recipientLanguage, $languages, true)) {
                            $recipientLanguage = 'NL';
                        }
                        $localizedMessage = $translateMessage($messages, $recipientLanguage);
                        $subject = fn_translationForLanguage('release_notes_email_subject', $recipientLanguage);
                        $heading = fn_translationForLanguage('release_notes_email_heading', $recipientLanguage);
                        $footer = fn_translationForLanguage('release_notes_email_footer', $recipientLanguage);
                        $body = '<h1>' . e($heading) . '</h1>' . $localizedMessage['html']
                            . '<p>' . e($footer) . '</p>';
                        try {
                            grompil_send_mail($recipientEmail, $subject, $body, $heading . "\n\n" . $localizedMessage['text'] . "\n\n" . $footer);
                        } catch (Throwable $mailError) {
                            $mailFailures++;
                            error_log('Release note notification failed: ' . get_class($mailError));
                        }
                    }
                } catch (GrompilDatabaseQueryException) {
                    $mailFailures++;
                }
                $query = '?published=' . $noteId;
                if ($mailFailures > 0) {
                    $query .= '&mail_failures=' . $mailFailures;
                }
                redirect('release-notes.php' . $query);
            } catch (GrompilDatabaseQueryException) {
                $error = 'release_notes_publish_failed';
            }
        }
    } else {
        $error = 'release_notes_publish_invalid';
    }
}

try {
    $subscriptionResult = db_query_params(
        'release_notes.subscription_status',
        'SELECT 1 FROM dt_user_release_subscriptions WHERE user_code = $1',
        [(string) $user['user_code']]
    );
    $subscribed = pg_num_rows($subscriptionResult) > 0;
} catch (GrompilDatabaseQueryException) {
    $error = 'release_notes_unavailable';
}

$notes = [];
try {
    $notesResult = db_query_params(
        'release_notes.list',
        'SELECT id, messages, wiki_page_id, created_at
         FROM ft_release_notes
         ORDER BY created_at DESC, id DESC'
    );
    while ($row = pg_fetch_assoc($notesResult)) {
        $messages = json_decode((string) ($row['messages'] ?? '{}'), true);
        $messages = is_array($messages) ? $messages : [];
        $row['message'] = $translateMessage($messages, $language);
        if ($row['message']['text'] !== '') {
            $notes[] = $row;
        }
    }
} catch (GrompilDatabaseQueryException) {
    $error = 'release_notes_unavailable';
}

if (isset($_GET['published'])) {
    $mailFailures = max(0, (int) ($_GET['mail_failures'] ?? 0));
    $notice = $mailFailures > 0 ? 'release_notes_published_mail_warning' : 'release_notes_published';
}

$languageFieldLabels = [
    'NL' => fn_t('release_notes_language_nl'),
    'FR' => fn_t('release_notes_language_fr'),
    'EN' => fn_t('release_notes_language_en'),
    'DE' => fn_t('release_notes_language_de'),
];
$returnUrl = fn_releaseNotesReturnUrl(
    is_scalar($_GET['return_to'] ?? null)
        ? (string) $_GET['return_to']
        : (string) ($_SERVER['HTTP_REFERER'] ?? ''),
    fn_appBaseHref()
);

page_start(fn_t('release_notes_title'), $user);
if ($isPublisher) {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">'
        . '<link rel="stylesheet" href="' . e(fn_appBaseHref() . 'assets/release-notes-editor.css') . '">';
}
echo '<a class="btn btn-outline-secondary btn-sm mb-3" href="' . e($returnUrl) . '">'
    . '<span aria-hidden="true">←</span> ' . e(fn_t('release_notes_return_previous_module')) . '</a>';
echo '<header class="mb-4"><h1>' . e(fn_t('release_notes_title')) . '</h1><p class="text-secondary">'
    . e(fn_t('release_notes_intro')) . '</p></header>';

if ($error !== '') {
    echo '<div class="alert alert-danger" role="alert">' . e(fn_t($error)) . '</div>';
}
if ($notice !== '') {
    $noticeText = fn_t($notice);
    if ($notice === 'release_notes_published_mail_warning') {
        $noticeText = sprintf($noticeText, max(0, (int) ($_GET['mail_failures'] ?? 0)));
    }
    echo '<div class="alert alert-success" role="status">' . e($noticeText) . '</div>';
}

echo '<section class="card mb-4"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">'
    . '<div><strong>' . e(fn_t('release_notes_subscription_email')) . '</strong><br><span>'
    . e($email !== '' ? $email : fn_t('release_notes_no_email')) . '</span></div>'
    . '<form method="post" class="m-0">' . csrf_field()
    . '<input type="hidden" name="action" value="' . ($subscribed ? 'unsubscribe' : 'subscribe') . '">'
    . '<button class="btn ' . ($subscribed ? 'btn-outline-secondary' : 'btn-grompil') . '" type="submit"'
    . ($email === '' ? ' disabled' : '') . '>'
    . e(fn_t($subscribed ? 'release_notes_unsubscribe' : 'release_notes_subscribe')) . '</button></form></div></section>';

if ($isPublisher) {
    $languageFields = '';
    foreach ($languages as $code) {
        $active = $code === $language;
        $languageFields .= '<div class="mb-3 release-note-editor-field"><label class="form-label" for="release-message-' . strtolower($code) . '">'
            . e($languageFieldLabels[$code]) . ($active ? ' · ' . e(fn_t('release_notes_current_language')) : '') . '</label>'
            . '<textarea class="form-control" id="release-message-' . strtolower($code) . '" name="message_' . strtolower($code)
            . '" rows="4"' . ($active ? ' required autofocus' : '') . '>'
            . e((string) ($postedMessages[$code] ?? '')) . '</textarea></div>';
    }
    echo '<section class="card mb-4"><div class="card-body"><h2 class="h5">' . e(fn_t('release_notes_publish_title')) . '</h2><p class="text-secondary">'
        . e(fn_t('release_notes_publish_help')) . '</p><form method="post">' . csrf_field()
        . '<input type="hidden" name="action" value="publish">' . $languageFields
        . '<div class="mb-3"><label class="form-label" for="release-wiki-page">' . e(fn_t('release_notes_wiki_page_id'))
        . '</label><input class="form-control" type="text" inputmode="numeric" pattern="[0-9]{1,12}" maxlength="12"'
        . ' id="release-wiki-page" name="wiki_page_id" value="' . e($postedWikiPageId) . '"><div class="form-text">'
        . e(fn_t('release_notes_wiki_page_id_help')) . '</div></div>'
        . '<button class="btn btn-grompil" type="submit">' . e(fn_t('release_notes_publish')) . '</button></form></div></section>'
        . '<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>'
        . '<script src="' . e(fn_appBaseHref() . 'assets/release-notes-editor.js') . '" defer></script>';
}

echo '<section aria-label="' . e(fn_t('release_notes_title')) . '">';
if ($notes === []) {
    echo '<div class="card"><div class="card-body text-secondary">' . e(fn_t('release_notes_empty')) . '</div></div>';
} else {
    foreach ($notes as $note) {
        $wikiPageId = trim((string) ($note['wiki_page_id'] ?? ''));
        $displayId = $wikiPageId !== '' ? $wikiPageId : (string) $note['id'];
        $wikiLink = $wikiPageId !== '' ? fn_dokuwikiIcon('users', $wikiPageId) : '';
        $messageHtml = (string) $note['message']['html'];
        if ($wikiLink !== '') {
            $messageHtml = preg_replace('~</(p|li)>~i', $wikiLink . '</$1>', $messageHtml) ?? $messageHtml;
            if (!preg_match('~</(?:p|li)>~i', $messageHtml)) {
                $messageHtml .= $wikiLink;
            }
        }
        echo '<article class="card mb-3"><div class="card-body"><div class="small text-secondary mb-2">'
            . '<strong class="text-body">' . e($displayId) . '</strong> · '
            . e(fn_formatDateDisplay((string) $note['created_at'])) . ' · ' . e($language)
            . '</div><div class="release-note-message">' . $messageHtml . '</div></div></article>';
    }
}
echo '</section>';
page_end();

/** Load a stock translation independent of the publisher's active language. */
function fn_translationForLanguage(string $key, string $language): string
{
    static $translations = [];
    $language = strtoupper($language);
    if (!isset($translations[$language])) {
        $translations[$language] = fn_readSourceLanguage($language);
    }
    return (string) ($translations[$language][$key] ?? $key);
}

/**
 * Return only to an internal page under this installation's application path.
 */
function fn_releaseNotesReturnUrl(string $candidate, string $appBasePath): string
{
    $fallback = rtrim($appBasePath, '/') . '/dashboard.php';
    $parts = parse_url(trim($candidate));
    if (!is_array($parts)) {
        return $fallback;
    }

    if (isset($parts['host'])) {
        $candidateHost = strtolower((string) $parts['host'])
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
        $currentHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($currentHost === '' || $candidateHost !== $currentHost) {
            return $fallback;
        }
    } elseif (isset($parts['scheme']) || str_starts_with($candidate, '//')) {
        return $fallback;
    }

    $path = (string) ($parts['path'] ?? '');
    $prefix = rtrim($appBasePath, '/') . '/';
    if ($path === '' || !str_starts_with($path, $prefix)
        || preg_match('~[^A-Za-z0-9_./-]~', $path) === 1
        || preg_match('~(?:^|/)\.\.?(/|$)~', substr($path, strlen($prefix))) === 1) {
        return $fallback;
    }

    $relativePath = substr($path, strlen($prefix));
    $isApplicationPage = preg_match('/^[A-Za-z0-9_-]+\.php$/D', $relativePath) === 1
        && !in_array($relativePath, ['release-notes.php', 'settings_local.php', 'setup-admin.php'], true)
        && is_file(GROMPIL_ROOT . '/' . $relativePath);
    $isUrlManager = $relativePath === 'url-manager/index.html'
        && is_file(GROMPIL_ROOT . '/url-manager/index.html');
    if ($relativePath === '' || $relativePath === 'release-notes.php'
        || (!$isApplicationPage && !$isUrlManager)) {
        return $fallback;
    }

    $url = $prefix . $relativePath;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $url .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $url .= '#' . $parts['fragment'];
    }
    return $url;
}

/** Sanitize editor HTML to the small formatting subset used by release notes. */
function fn_releaseNotesSanitizeHtml(string $input): string
{
    if (trim($input) === '') {
        return '';
    }
    if (!class_exists(DOMDocument::class)) {
        return '<p>' . nl2br(e(trim(strip_tags($input))), false) . '</p>';
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body><div id="release-root">'
        . $input . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }
    $root = $document->getElementById('release-root');
    if (!$root instanceof DOMElement) {
        return '';
    }
    return fn_releaseNotesSerializeChildren($root);
}

/** Recursively serialize only approved tags and attributes. */
function fn_releaseNotesSerializeChildren(DOMNode $parent): string
{
    $html = '';
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMText) {
            $html .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($child->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
            continue;
        }
        $content = fn_releaseNotesSerializeChildren($child);
        if ($tag === 'br') {
            $html .= '<br>';
        } elseif (in_array($tag, ['p', 'strong', 'b', 'em', 'i', 'u', 'ol', 'ul', 'li'], true)) {
            $normalizedTag = match ($tag) {
                'b' => 'strong', 'i' => 'em', default => $tag,
            };
            if ($tag === 'ol') {
                foreach ($child->childNodes as $listItem) {
                    if ($listItem instanceof DOMElement && strtolower($listItem->tagName) === 'li'
                        && $listItem->getAttribute('data-list') === 'bullet') {
                        $normalizedTag = 'ul';
                        break;
                    }
                }
            }
            $html .= '<' . $normalizedTag . '>' . $content . '</' . $normalizedTag . '>';
        } elseif ($tag === 'a') {
            $href = trim((string) $child->getAttribute('href'));
            $scheme = parse_url($href, PHP_URL_SCHEME);
            $safeHref = $href !== '' && !str_starts_with($href, '//')
                && ($scheme === null || in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true));
            $html .= $safeHref
                ? '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer">' . $content . '</a>'
                : $content;
        } else {
            $html .= $content;
        }
    }
    return $html;
}

/** Convert sanitized rich text to a readable plain-text email alternative. */
function fn_releaseNotesPlainText(string $html): string
{
    $text = preg_replace('~<(?:br|/p|/li|/ul|/ol)>~i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_map('trim', preg_split('/\R/u', $text) ?: []);
    return trim(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== '')));
}

/** Render legacy plain-text release note values without interpreting markup. */
function fn_releaseNotesLegacyHtml(string $text): string
{
    $lines = preg_split('/\R/u', trim($text)) ?: [trim($text)];
    return implode('', array_map(static fn (string $line): string => '<p>' . e($line) . '</p>', $lines));
}
