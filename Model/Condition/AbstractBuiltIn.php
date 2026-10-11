<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Iranimij\OpenLabel\Model\Rule\Rule;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Phrase;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Base of the OpenLabel built-in conditions. Each built-in offers a few attributes, validates one product in PHP
 * and contributes a SQL expression (plus joins) that the core Sql Builder attaches to the product collection.
 *
 * Context (store, website, customer group) comes from the owning Rule.
 *
 * @method string|null getAttribute()
 * @method $this setAttribute(string $attribute)
 * @method string|null getOperator()
 * @method $this setOperator(string $operator)
 * @method $this setValue(mixed $value)
 * @method $this setType(string $type)
 * @method mixed getRule()
 * @method $this setRule(mixed $rule)
 * @method array<string, \Magento\Framework\Phrase> getAttributeOption()
 * @method $this setAttributeOption(array<string, \Magento\Framework\Phrase> $options)
 */
abstract class AbstractBuiltIn extends AbstractCondition
{
    /**
     * @param Context $context
     * @param array<string, mixed> $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
        $this->setType(static::class);
    }

    /**
     * Attribute code => label for the condition tree.
     *
     * @return array<string, Phrase>
     */
    abstract protected function attributeOptions(): array;

    /**
     * Attribute codes answered with Yes/No.
     *
     * @return string[]
     */
    protected function booleanAttributes(): array
    {
        return [];
    }

    /**
     * The value of the current attribute for one product, in PHP (admin preview, unit tests).
     *
     * @param Product $product
     * @return mixed null when the product cannot be evaluated
     */
    abstract protected function productValue(Product $product): mixed;

    /**
     * Whether the SQL depends on the customer group (price index). The indexer then writes one row per group.
     *
     * @return bool
     */
    public function requiresCustomerGroup(): bool
    {
        return false;
    }

    /**
     * Whether the result changes with time, so the daily cron must reindex labels using it.
     *
     * @return bool
     */
    public function isDateRelative(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption($this->attributeOptions());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInputType()
    {
        return $this->isBoolean() ? 'select' : 'numeric';
    }

    /**
     * @inheritDoc
     */
    public function getValueElementType()
    {
        return $this->isBoolean() ? 'select' : 'text';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getValueSelectOptions()
    {
        if (!$this->isBoolean()) {
            return [];
        }

        return [
            ['value' => '1', 'label' => __('Yes')],
            ['value' => '0', 'label' => __('No')],
        ];
    }

    /**
     * @return array<string, array<string, mixed>> alias => ['name' => table, 'condition' => sql, 'columns' => []]
     */
    public function getTablesToJoin()
    {
        return [];
    }

    /**
     * SQL that is true when the product has the data this condition compares (a price index row, a rating, a
     * stock row). Without it the condition never matches, whatever the operator, "is not" included. Applied by
     * Model\Rule\SqlBuilder, so it behaves the same on every Magento version.
     *
     * @return string|null null when the value always exists
     */
    public function getSqlGuard(): ?string
    {
        return null;
    }

    /**
     * Yes/no conditions offer "is" and "is not" only (the core "is undefined" means nothing here); numeric ones
     * offer every comparison and "is one of" / "is not one of".
     *
     * @return array<string, string[]>
     */
    public function getDefaultOperatorInputByType()
    {
        $types = parent::getDefaultOperatorInputByType();
        $types['select'] = ['==', '!='];

        return $types;
    }

    /**
     * Hook for joins that need the collection itself; built-ins declare joins via getTablesToJoin().
     *
     * @param ProductCollection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes(ProductCollection $productCollection): self
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validate(AbstractModel $model)
    {
        if (!$model instanceof Product) {
            return false;
        }
        $value = $this->productValue($model);
        if ($value === null) {
            return false;
        }

        return $this->validateAttribute($value);
    }

    /**
     * @return bool
     */
    protected function isBoolean(): bool
    {
        return in_array((string) $this->getAttribute(), $this->booleanAttributes(), true);
    }

    /**
     * @return int
     */
    protected function storeId(): int
    {
        return (int) $this->ruleData('store_id');
    }

    /**
     * @return int
     */
    protected function websiteId(): int
    {
        return (int) $this->ruleData('website_id');
    }

    /**
     * @return int
     */
    protected function customerGroupId(): int
    {
        return (int) $this->ruleData('customer_group_id');
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function ruleData(string $key): mixed
    {
        $rule = $this->getRule();
        if (!$rule instanceof Rule) {
            return null;
        }

        return match ($key) {
            'store_id' => $rule->getStoreId(),
            'website_id' => $rule->getWebsiteId(),
            default => $rule->getCustomerGroupId(),
        };
    }
}
