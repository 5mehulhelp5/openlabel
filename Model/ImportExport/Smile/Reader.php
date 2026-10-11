<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\ImportExport\Smile;

use Iranimij\OpenLabel\Model\ImportExport\ScopeCodes;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

/**
 * Reads the Smile product label tables directly (the Smile module itself does not need to be installed or enabled):
 * labels with their attribute code, store view codes and image file.
 */
class Reader
{
    private const TABLE = 'smile_productlabel';
    private const STORE_TABLE = 'smile_productlabel_store';

    /**
     * @param ResourceConnection $resource
     * @param ScopeCodes $scopeCodes
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeCodes $scopeCodes,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @return bool
     */
    public function hasTables(): bool
    {
        return $this->resource->getConnection()->isTableExists($this->resource->getTableName(self::TABLE));
    }

    /**
     * @return array<int, array<string, mixed>> rows for Converter::convert()
     */
    public function read(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName(self::TABLE)])
            ->joinLeft(
                ['a' => $this->resource->getTableName('eav_attribute')],
                'a.attribute_id = l.attribute_id',
                ['attribute_code']
            )
            ->order('l.product_label_id');
        $rows = $connection->fetchAll($select);

        $stores = [];
        $storeTable = $this->resource->getTableName(self::STORE_TABLE);
        if ($connection->isTableExists($storeTable)) {
            foreach ($connection->fetchAll($connection->select()->from($storeTable)) as $link) {
                $stores[(int) $link['product_label_id']][] = (int) $link['store_id'];
            }
        }

        $this->scopeCodes->reset();
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        foreach ($rows as &$row) {
            $storeIds = $stores[(int) $row['product_label_id']] ?? [0];
            $row['store_codes'] = in_array(0, $storeIds, true)
                ? []
                : array_map(fn (int $id): string => $this->scopeCodes->storeCode($id), $storeIds);
            $file = Converter::MEDIA_DIR . '/' . ltrim((string) $row['image'], '/');
            $row['image_contents'] = (string) $row['image'] !== '' && $media->isFile($file) ? $media->readFile($file) : null;
            $row['image'] = substr((string) $row['image'], (int) strrpos('/' . (string) $row['image'], '/'));
        }

        return $rows;
    }
}
