<?php

namespace Nails\Webhooks\Interfaces;

use Nails\Webhooks\Definition;

interface Catalogue
{
    public function has(string $sSlug): bool;

    public function get(string $sSlug): Definition;

    /**
     * @return Definition[]
     */
    public function all(): array;
}
