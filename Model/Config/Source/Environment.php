<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Config\Source;

use Faccelerate\Connect\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

final class Environment implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ENV_PRODUCTION, 'label' => __('Production')],
            ['value' => Config::ENV_STAGING, 'label' => __('Staging')],
            ['value' => Config::ENV_CUSTOM, 'label' => __('Custom (local development)')],
        ];
    }
}
