<?php

/**
 * The storefront copy the CMS is expected to serve.
 *
 * Captured from the database at the moment content moved out of the engine, so
 * the landing and static-page tests keep asserting the same sentences an editor
 * would see. Only published sections appear here — the CMS never sends a draft,
 * so neither does the fixture.
 *
 * Stored as JSON rather than a PHP array because the content is large, deeply
 * nested and full of typographic quotes; a JSON file survives a round trip
 * through an editor, a PHP literal does not.
 *
 * @return array{0: array<string, array<string, mixed>>, 1: array<string, string>}
 */
return (static function (): array {
    $data = json_decode(
        (string) file_get_contents(__DIR__.'/content.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return [$data['sections'], $data['settings']];
})();
