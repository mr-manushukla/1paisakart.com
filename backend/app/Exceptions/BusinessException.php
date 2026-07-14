<?php

namespace App\Exceptions;

use RuntimeException;

/** A rule violation the user can fix (out of stock, already entered, empty cart…). Rendered as HTTP 422. */
class BusinessException extends RuntimeException
{
}
