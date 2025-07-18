<?php

declare(strict_types=1);

namespace EDD\Vendor\Square\Models\Builders;

use EDD\Vendor\Core\Utils\CoreHelper;
use EDD\Vendor\Square\Models\InventoryAdjustment;
use EDD\Vendor\Square\Models\InventoryAdjustmentGroup;
use EDD\Vendor\Square\Models\Money;
use EDD\Vendor\Square\Models\SourceApplication;

/**
 * Builder for model InventoryAdjustment
 *
 * @see InventoryAdjustment
 */
class InventoryAdjustmentBuilder
{
    /**
     * @var InventoryAdjustment
     */
    private $instance;

    private function __construct(InventoryAdjustment $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new Inventory Adjustment Builder object.
     */
    public static function init(): self
    {
        return new self(new InventoryAdjustment());
    }

    /**
     * Sets id field.
     *
     * @param string|null $value
     */
    public function id(?string $value): self
    {
        $this->instance->setId($value);
        return $this;
    }

    /**
     * Sets reference id field.
     *
     * @param string|null $value
     */
    public function referenceId(?string $value): self
    {
        $this->instance->setReferenceId($value);
        return $this;
    }

    /**
     * Unsets reference id field.
     */
    public function unsetReferenceId(): self
    {
        $this->instance->unsetReferenceId();
        return $this;
    }

    /**
     * Sets from state field.
     *
     * @param string|null $value
     */
    public function fromState(?string $value): self
    {
        $this->instance->setFromState($value);
        return $this;
    }

    /**
     * Sets to state field.
     *
     * @param string|null $value
     */
    public function toState(?string $value): self
    {
        $this->instance->setToState($value);
        return $this;
    }

    /**
     * Sets location id field.
     *
     * @param string|null $value
     */
    public function locationId(?string $value): self
    {
        $this->instance->setLocationId($value);
        return $this;
    }

    /**
     * Unsets location id field.
     */
    public function unsetLocationId(): self
    {
        $this->instance->unsetLocationId();
        return $this;
    }

    /**
     * Sets catalog object id field.
     *
     * @param string|null $value
     */
    public function catalogObjectId(?string $value): self
    {
        $this->instance->setCatalogObjectId($value);
        return $this;
    }

    /**
     * Unsets catalog object id field.
     */
    public function unsetCatalogObjectId(): self
    {
        $this->instance->unsetCatalogObjectId();
        return $this;
    }

    /**
     * Sets catalog object type field.
     *
     * @param string|null $value
     */
    public function catalogObjectType(?string $value): self
    {
        $this->instance->setCatalogObjectType($value);
        return $this;
    }

    /**
     * Unsets catalog object type field.
     */
    public function unsetCatalogObjectType(): self
    {
        $this->instance->unsetCatalogObjectType();
        return $this;
    }

    /**
     * Sets quantity field.
     *
     * @param string|null $value
     */
    public function quantity(?string $value): self
    {
        $this->instance->setQuantity($value);
        return $this;
    }

    /**
     * Unsets quantity field.
     */
    public function unsetQuantity(): self
    {
        $this->instance->unsetQuantity();
        return $this;
    }

    /**
     * Sets total price money field.
     *
     * @param Money|null $value
     */
    public function totalPriceMoney(?Money $value): self
    {
        $this->instance->setTotalPriceMoney($value);
        return $this;
    }

    /**
     * Sets occurred at field.
     *
     * @param string|null $value
     */
    public function occurredAt(?string $value): self
    {
        $this->instance->setOccurredAt($value);
        return $this;
    }

    /**
     * Unsets occurred at field.
     */
    public function unsetOccurredAt(): self
    {
        $this->instance->unsetOccurredAt();
        return $this;
    }

    /**
     * Sets created at field.
     *
     * @param string|null $value
     */
    public function createdAt(?string $value): self
    {
        $this->instance->setCreatedAt($value);
        return $this;
    }

    /**
     * Sets source field.
     *
     * @param SourceApplication|null $value
     */
    public function source(?SourceApplication $value): self
    {
        $this->instance->setSource($value);
        return $this;
    }

    /**
     * Sets employee id field.
     *
     * @param string|null $value
     */
    public function employeeId(?string $value): self
    {
        $this->instance->setEmployeeId($value);
        return $this;
    }

    /**
     * Unsets employee id field.
     */
    public function unsetEmployeeId(): self
    {
        $this->instance->unsetEmployeeId();
        return $this;
    }

    /**
     * Sets team member id field.
     *
     * @param string|null $value
     */
    public function teamMemberId(?string $value): self
    {
        $this->instance->setTeamMemberId($value);
        return $this;
    }

    /**
     * Unsets team member id field.
     */
    public function unsetTeamMemberId(): self
    {
        $this->instance->unsetTeamMemberId();
        return $this;
    }

    /**
     * Sets transaction id field.
     *
     * @param string|null $value
     */
    public function transactionId(?string $value): self
    {
        $this->instance->setTransactionId($value);
        return $this;
    }

    /**
     * Sets refund id field.
     *
     * @param string|null $value
     */
    public function refundId(?string $value): self
    {
        $this->instance->setRefundId($value);
        return $this;
    }

    /**
     * Sets purchase order id field.
     *
     * @param string|null $value
     */
    public function purchaseOrderId(?string $value): self
    {
        $this->instance->setPurchaseOrderId($value);
        return $this;
    }

    /**
     * Sets goods receipt id field.
     *
     * @param string|null $value
     */
    public function goodsReceiptId(?string $value): self
    {
        $this->instance->setGoodsReceiptId($value);
        return $this;
    }

    /**
     * Sets adjustment group field.
     *
     * @param InventoryAdjustmentGroup|null $value
     */
    public function adjustmentGroup(?InventoryAdjustmentGroup $value): self
    {
        $this->instance->setAdjustmentGroup($value);
        return $this;
    }

    /**
     * Initializes a new Inventory Adjustment object.
     */
    public function build(): InventoryAdjustment
    {
        return CoreHelper::clone($this->instance);
    }
}
