<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza al intentar modificar o borrar una venta emitida fuera de la anulación (§3.1, §3.3).
 */
class ImmutableSaleException extends RuntimeException {}
