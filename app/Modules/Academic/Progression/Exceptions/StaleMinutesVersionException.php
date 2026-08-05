<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Exceptions;

/**
 * The client confirmed against a minutes_version that no longer matches the
 * dossier (the minutes were edited since it fetched them). The transport layer
 * maps this to HTTP 409 so the client refetches and re-confirms the current
 * version — never binds an acknowledgement to stale content.
 */
final class StaleMinutesVersionException extends \DomainException
{
    public function __construct(
        public readonly int $submittedVersion,
        public readonly int $currentVersion,
    ) {
        // The version numbers stay on the exception for the transport layer and
        // logs; the sentence itself is read by a student, so it says what to do
        // rather than which version was submitted.
        parent::__construct(
            'These notes were updated after you opened them. Please reload the page and read the latest version before answering.',
        );
    }
}
