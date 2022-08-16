<?php

namespace AppDistributionTool\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function UserScriptable\formatDescription;

/**
 * Class DescriptionMiddlewareExtension
 * @package AppDistributionTool\Extension
 */
class DescriptionMiddlewareExtension extends AbstractExtension
{
    /**
     * @return array|TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('descr_middleware', [$this, 'applyFormatting'])
        ];
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public function applyFormatting(string $text): string
    {
        return formatDescription($text);
    }
}
