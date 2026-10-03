<?php

namespace App\Exceptions;

/** Not enough stock for a movement or a claim; a rule break, so Phase 4 screens show it like any other. */
class InsufficientStock extends InterventionRuleViolation {}
