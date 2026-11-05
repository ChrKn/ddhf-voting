<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fachlicher Fehler im Abstimmungsablauf. Die Meldung ist für Nutzer:innen
 * bestimmt und kann direkt angezeigt werden.
 */
class VotingException extends RuntimeException {}
