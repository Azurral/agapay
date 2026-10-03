<?php

namespace App\Exceptions;

use RuntimeException;

/** The uploaded file as a whole can't be imported; the message is shown on the upload card. */
class ImportFileException extends RuntimeException {}
