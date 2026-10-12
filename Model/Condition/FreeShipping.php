<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenLabel\Model\Condition;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rule\Model\Condition\Context;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\SalesRule\Model\Rule as CartRule;
use Magento\Store\Model\ScopeInterface;

/**
 * "Ships free": the product alone gets free shipping, because
 * - an active cart price rule without coupon and without conditions gives free shipping to the label's website and
 *   customer group (then every product with a price qualifies), or
 * - the free shipping method is enabled and the product's final price (price index, per customer group) reaches
 *   its "Minimum Order Amount".
 * Rules with conditions or a coupon are not considered: whether they apply depends on the cart.
 */
class FreeShipping extends AbstractBuiltIn
{
    use PriceIndexJoin;

    public const FREE_SHIPPING = 'free_shipping';

    private const XML_ACTIVE = 'carriers/freeshipping/active';
    private const XML_SUBTOTAL = 'carriers/freeshipping/free_shipping_subtotal';

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CartRuleCollectionFactory $cartRules
     * @param Json $json
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CartRuleCollectionFactory $cartRules,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function attributeOptions(): array
    {
        return [self::FREE_SHIPPING => __('Ships free')];
    }

    /**
     * @inheritDoc
     */
    protected function booleanAttributes(): array
    {
        return [self::FREE_SHIPPING];
    }

    /**
     * Cart rules have dates, so the answer can change from one day to the next.
     *
     * @return bool
     */
    public function isDateRelative(): bool
    {
        return true;
    }

    /**
     * The core Sql Builder accepts expressions only as Zend_Db_Expr instances, hence the widened return type.
     *
     * @return \Zend_Db_Expr
     * @phpstan-ignore method.childReturnType, method.childReturnType (AbstractCondition and ConditionInterface both declare string)
     */
    public function getMappedSqlField()
    {
        if ($this->hasRuleForEveryone()) {
            return new \Zend_Db_Expr('1');
        }
        $threshold = $this->threshold();
        if ($threshold === null) {
            return new \Zend_Db_Expr('0');
        }

        return new \Zend_Db_Expr(sprintf(
            '(CASE WHEN %s.final_price >= %F THEN 1 ELSE 0 END)',
            self::PRICE_ALIAS,
            $threshold
        ));
    }

    /**
     * @inheritDoc
     */
    protected function productValue(Product $product): mixed
    {
        if ($this->hasRuleForEveryone()) {
            return 1;
        }
        $threshold = $this->threshold();
        [, $final] = $this->prices($product);

        return $threshold !== null && $final >= $threshold ? 1 : 0;
    }

    /**
     * The free shipping method's minimum order amount, or null when the method is off or has no minimum.
     *
     * @return float|null
     */
    private function threshold(): ?float
    {
        $store = $this->storeId();
        if (!$this->scopeConfig->isSetFlag(self::XML_ACTIVE, ScopeInterface::SCOPE_STORE, $store)) {
            return null;
        }
        $subtotal = $this->scopeConfig->getValue(self::XML_SUBTOTAL, ScopeInterface::SCOPE_STORE, $store);

        return is_numeric($subtotal) && (float) $subtotal > 0 ? (float) $subtotal : null;
    }

    /**
     * @return bool
     */
    private function hasRuleForEveryone(): bool
    {
        $rules = $this->cartRules->create()
            ->addWebsiteGroupDateFilter($this->websiteId(), $this->customerGroupId())
            ->addFieldToFilter('is_active', '1')
            ->addFieldToFilter('coupon_type', (string) CartRule::COUPON_TYPE_NO_COUPON);
        foreach ($rules as $rule) {
            if ((int) $rule->getData('simple_free_shipping') > 0
                && !$this->hasConditions($rule->getData('conditions_serialized'))
                && !$this->hasConditions($rule->getData('actions_serialized'))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $serialized
     * @return bool
     */
    private function hasConditions(mixed $serialized): bool
    {
        if (!is_string($serialized) || $serialized === '') {
            return false;
        }
        try {
            $tree = $this->json->unserialize($serialized);
        } catch (\InvalidArgumentException) {
            return true;
        }

        return is_array($tree) && !empty($tree['conditions']);
    }
}
