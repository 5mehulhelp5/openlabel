<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Css;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Writes the generated stylesheet for every store view (on design and label saves, from the CLI and the settings
 * button) or, on a fresh install, for one store on its first storefront request.
 */
class Regenerator
{
    /**
     * @param StylesheetGenerator $generator
     * @param Storage $storage
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StylesheetGenerator $generator,
        private readonly Storage $storage,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string|null $globalCss the custom CSS just saved in the admin, which the configuration does not return
     *                               until the next request; null reads it from the configuration
     * @return array<int, string> store id => path relative to the media directory
     */
    public function regenerateAll(?string $globalCss = null): array
    {
        $css = $this->generator->build($globalCss);
        $paths = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            $paths[$storeId] = $this->storage->write($storeId, $css);
        }
        ksort($paths);

        return $paths;
    }

    /**
     * @param int $storeId
     * @return string the current stylesheet of the store, generated if there is none yet
     */
    public function ensure(int $storeId): string
    {
        return $this->storage->current($storeId) ?? $this->storage->write($storeId, $this->generator->build());
    }
}
