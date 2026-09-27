<?php

namespace Nails\Webhooks\Traits;

/**
 * Marker: every verified request calls handle(), including a repeat of the same body.
 */
trait AllowsDuplicates
{
}
