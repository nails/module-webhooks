<?php

namespace Nails\Webhooks\Service;

use Nails\Components;
use Nails\Webhooks\Definition;
use Nails\Webhooks\Exception\DuplicateSlugException;
use Nails\Webhooks\Exception\UnknownWebhookException;
use Nails\Webhooks\Interfaces\Catalogue;
use Nails\Webhooks\Interfaces\Webhook as WebhookHandler;

class Webhook implements Catalogue
{
    /** @var array<string, Definition> */
    private array $aDefinitions = [];

    public function __construct(bool $bDiscover = true)
    {
        if ($bDiscover) {
            $this->discover();
        }
    }

    public function discover(): void
    {
        foreach (Components::available() as $oComponent) {
            $aClasses = $oComponent
                ->findClasses('Webhooks')
                ->whichImplement(WebhookHandler::class)
                ->whichCanBeInstantiated();

            foreach ($aClasses as $sClass) {
                $this->add(
                    (string) $oComponent->slug,
                    (string) $oComponent->namespace,
                    $sClass,
                    (string) $oComponent->name,
                );
            }
        }
    }

    public function add(
        string $sComponentSlug,
        string $sNamespace,
        string $sClass,
        string $sComponentName = '',
    ): void {
        $sSlug = self::slugFor($sComponentSlug, $sNamespace, $sClass);
        if (isset($this->aDefinitions[$sSlug])) {
            throw new DuplicateSlugException(sprintf(
                'Webhook slug "%s" is already registered by %s',
                $sSlug,
                $this->aDefinitions[$sSlug]->class,
            ));
        }

        $oHandler = new $sClass();
        if (!$oHandler instanceof WebhookHandler) {
            throw new \InvalidArgumentException(sprintf(
                '%s does not implement %s',
                $sClass,
                WebhookHandler::class,
            ));
        }

        $this->aDefinitions[$sSlug] = new Definition(
            $sClass,
            $sSlug,
            $sComponentSlug,
            $sComponentName !== '' ? $sComponentName : $sComponentSlug,
            $oHandler,
        );
    }

    public static function slugFor(string $sComponentSlug, string $sNamespace, string $sClass): string
    {
        $sNamespace = trim($sNamespace, '\\');
        $sClass     = ltrim($sClass, '\\');
        $sPrefix    = $sNamespace . '\\Webhooks\\';

        if (!str_starts_with($sClass, $sPrefix)) {
            throw new \InvalidArgumentException(sprintf(
                '%s is not in the %s namespace',
                $sClass,
                $sPrefix,
            ));
        }

        $aSegments = array_map(
            [self::class, 'kebab'],
            array_filter(explode('\\', substr($sClass, strlen($sPrefix)))),
        );

        return trim($sComponentSlug, '/') . '/' . implode('/', $aSegments);
    }

    public static function kebab(string $sSegment): string
    {
        $sSegment = preg_replace('/(?<!^)[A-Z]/', '-$0', $sSegment) ?? $sSegment;

        return strtolower($sSegment);
    }

    public function has(string $sSlug): bool
    {
        return isset($this->aDefinitions[$sSlug]);
    }

    public function get(string $sSlug): Definition
    {
        return $this->aDefinitions[$sSlug] ?? throw new UnknownWebhookException($sSlug);
    }

    /**
     * @return Definition[]
     */
    public function all(): array
    {
        return array_values($this->aDefinitions);
    }

    /**
     * @return Definition[]
     */
    public function configurable(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn(Definition $oDefinition): bool => $oDefinition->isConfigurable(),
        ));
    }
}
