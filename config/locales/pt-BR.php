<?php
// All three locales share the same source keys.
$messages = [];
foreach (file(__DIR__ . '/messages.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$source] = explode('|', $line, 3);
    $messages[$source] = $source;
}
return $messages;