<?php

namespace App\Scoring;

/** A visit, InBody scan or weigh-in. Categories needing a document count only once it's approved. */
final readonly class OtherEvent
{
    public function __construct(
        public string $id,
        public string $date,
        public string $category,
        public bool $documentApproved = false,
    ) {}
}
