<?php
$messages = [];
foreach (file(__DIR__ . '/messages.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$source, $en, $es] = explode('|', $line, 3);
    $messages[$source] = $es;
}
return $messages;
