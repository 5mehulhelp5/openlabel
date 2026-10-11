<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Iranimij\OpenLabel\Model\Condition\Stock\StockDataResolver;
use Magento\Catalog\Model\Product;
use Magento\Rule\Model\Condition\Context;

/**
 * Stock: salable (MSI-aware, per website stock) and salable quantity, with the legacy fallback (06 · F6).
 */
class Stock extends AbstractBuiltIn
{
    public const IS_SALABLE = 'is_salable';
    public const SALABLE_QTY = 'salable_qty';

    private const ALIAS = 'ol_stock';

    /** Product types that carry a quantity of their own; composites get quantity labels through apply_to_parent. */
    private const QUANTITY_TYPES = ['simple', 'virtual', 'downloadable'];

    /**
     * @param Context $context
     * @param StockDataResolver $stockDataResolver
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly StockDataResolver $stockDataResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [
            self::IS_SALABLE => __('In stock (salable)'),
            self::SALABLE_QTY => __('Salable quantity'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function booleanAttributes(): array
    {
        return [self::IS_SALABLE];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTablesToJoin()
    {
        $stock = $this->stockDataResolver->get();

        return [
            self::ALIAS => [
                'name' => $stock->getTableName($this->websiteId()),
                'condition' => $stock->getJoinCondition(self::ALIAS, $this->websiteId()),
                'columns' => [],
            ],
        ];
    }

    /**
     * Quantity comparisons apply to products with a quantity of their own; composites yield NULL and never match.
     *
     * @return \Zend_Db_Expr|string
     */
    public function getMappedSqlField()
    {
        $stock = $this->stockDataResolver->get();
        if ((string) $this->getAttribute() !== self::SALABLE_QTY) {
            return self::ALIAS . '.' . $stock->getSalableColumn();
        }

        return self::ALIAS . '.' . $stock->getQtyColumn();
    }

    /**
     * Products without a stock row never match; salable quantity applies to simple, virtual and downloadable
     * products only (composites through "apply to parents").
     *
     * @return string
     */
    public function getSqlGuard(): ?string
    {
        $stock = $this->stockDataResolver->get();
        if ((string) $this->getAttribute() !== self::SALABLE_QTY) {
            return self::ALIAS . '.' . $stock->getSalableColumn() . ' IS NOT NULL';
        }

        return sprintf(
            "e.type_id IN ('%s') AND %s.%s IS NOT NULL",
            implode("','", self::QUANTITY_TYPES),
            self::ALIAS,
            $stock->getQtyColumn()
        );
    }

    /**
     * @inheritDoc
     */
    protected function productValue(Product $product): mixed
    {
        $stock = $this->stockDataResolver->get();
        if ((string) $this->getAttribute() === self::SALABLE_QTY) {
            $type = (string) $product->getTypeId();
            if ($type !== '' && !in_array($type, self::QUANTITY_TYPES, true)) {
                return null;
            }

            return $stock->getSalableQty($product, $this->websiteId());
        }

        return $stock->isSalable($product, $this->websiteId()) ? 1 : 0;
    }
}
