<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una serie no puede emitir: inactiva, agotada o fuera de rango (§3.2).
 */
class SeriesUnavailableException extends RuntimeException {}
