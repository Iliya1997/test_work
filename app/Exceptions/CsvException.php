<?php

namespace App\Exceptions;

use Exception;

class CsvException extends Exception
{
    const INVALID_HEADER    = 1;
    const INVALID_EXTENSION = 2;
}