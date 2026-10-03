<?php

namespace App\Exceptions;

use DomainException;

/** A distribution rule refused the action; the message is shown to the user. */
class InterventionRuleViolation extends DomainException {}
