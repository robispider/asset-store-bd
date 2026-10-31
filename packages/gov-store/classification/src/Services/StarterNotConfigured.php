<?php

namespace GovStore\Classification\Services;

use RuntimeException;

/** No starter collection is set up for the office type yet: a setup gap, not a failed run. */
class StarterNotConfigured extends RuntimeException
{
}
