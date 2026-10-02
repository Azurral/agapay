<?php

namespace App\Exceptions;

use DomainException;

/** A workflow step that is not allowed from the record's current RSBSA status; the message is shown to the user. */
class InvalidRsbsaTransition extends DomainException {}
