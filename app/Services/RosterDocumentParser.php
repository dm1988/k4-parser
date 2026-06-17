<?php

namespace App\Services;

class RosterDocumentParser
{
    public function __construct(
        private readonly RosterParser $tripInformationParser,
        private readonly PublishedRosterParser $publishedRosterParser,
    ) {
    }

    public function parse(string $text, ?string $documentType = null): array
    {
        return match ($documentType) {
            RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER => $this->publishedRosterParser->parse($text),
            RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION,
            null => $this->tripInformationParser->parse($text),
            default => $this->tripInformationParser->parse($text),
        };
    }
}
