<?php
namespace Bitrix\Sale\Exchange\Entity;

use Bitrix\Main;
use Bitrix\Main\Error;
use Bitrix\Sale;
use Bitrix\Sale\Internals;
use Bitrix\Sale\Exchange;
use Bitrix\Sale\Exchange\EntityCollisionType;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Order;
use Bitrix\Sale\Shipment;
use Bitrix\Sale\Delivery\Services\Manager;

IncludeModuleLangFile(__FILE__);

/**
 * Class ShipmentImport
 * @package Bitrix\Sale\Exchange\Entity
 * @internal
 */
class ShipmentImport extends EntityImport
{
    public function __construct($parentEntityContext = null)
    {
        parent::__construct($parentEntityContext);
    }

    /**
     * @return int
     */
    public function getOwnerTypeId()
    {
        return Exchange\EntityType::SHIPMENT;
    }

    /**
     * @param Internals\Entity $entity
     * @throws Main\ArgumentException
     */
    public function setEntity(Internals\Entity $entity)
    {
        if(!($entity instanceof Shipment))
            throw new Main\ArgumentException("Entity must be instanceof Shipment");

        $this->entity = $entity;
    }

    /**
     * @param array $fields
     * @return Sale\Result
     */
    protected function checkFields(array $fields)
    {
        $result = new Sale\Result();

        if(intval($fields['ORDER_ID'])<=0 &&
            !$this->isLoadedParentEntity()
        )
        {
            $result->addError(new Error('ORDER_ID is not defined',''));
        }

        return $result;
    }

	/**
	 * @return Main\Entity\AddResult|Main\Entity\UpdateResult|Sale\Result|mixed
	 */
	public function save()
    {
        /** @var Order $parentEntity */
        $parentEntity = $this->getParentEntity();
        return $parentEntity->save();
    }

    /**
     * @param array $params
     * @return Sale\Result
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentTypeException
     * @throws Main\NotSupportedException
     */
    public function add(array $params)
    {
        /** @var Order $parentEntity */
        $parentEntity = $this->getParentEntity();

        if(!$this->isLoadedParentEntity())
        {
            throw new Main\ArgumentNullException("order is not loaded");
        }

        $shipmentServiceId = $this->settings->shipmentServiceFor($this->getOwnerTypeId());
        $shipmentService = Manager::getObjectById($shipmentServiceId);

        $shipmentCollection = $parentEntity->getShipmentCollection();
        $shipment = $shipmentCollection->createItem($shipmentService);
        $shipment->setField('DELIVERY_NAME', $shipmentService->getName());

        $basket = $parentEntity->getBasket();
        $result = $this->fillShipmentItems($shipment, $basket, $params);
        if(!$result->isSuccess())
        {
            return $result;
        }

        $fields = $params['TRAITS'];
        $result = $shipment->setFields($fields);

        if($result->isSuccess())
        {
            $this->setEntity($shipment);
        }

        return $result;
    }

    /**
     * @param array $params
     * @return Sale\Result
     * @throws Main\ArgumentNullException
     */
    public function update(array $params)
    {
        /** @var Sale\Shipment $shipment */
        $shipment = $this->getEntity();

        /** @var Order $parentEntity */
        $parentEntity = $this->getParentEntity();

        if(!$this->isLoadedParentEntity())
        {
            throw new Main\ArgumentNullException("order is not loaded");
        }

        $criterion = $this->getCurrentCriterion($this->getEntity());

        $fields = $params['TRAITS'];
        if($criterion->equals($fields))
        {
            $basket = $parentEntity->getBasket();
            $result = $this->fillShipmentItems($shipment, $basket, $params);
            if(!$result->isSuccess())
            {
                return $result;
            }
        }

        $result = $shipment->setFields($fields);

        return $result;
    }

    /**
     * @param array|null $params
     * @return Sale\Result
     * @throws Main\ObjectNotFoundException
     */
    public function delete(array $params = null)
    {
        /** @var Shipment $entity */
        $entity = $this->getEntity();
        $result = $entity->delete();
        if($result->isSuccess())
        {
            //$this->setCollisions(EntityCollisionType::OrderShipmentDeleted, $this->getParentEntity());
        }
        else
        {
            $this->setCollisions(EntityCollisionType::OrderShipmentDeletedError, $this->getParentEntity(), implode(',', $result->getErrorMessages()));
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getExternalFieldName()
    {
        return 'EXTERNAL_DELIVERY';
    }

    /**
     * @param array $fields
	 * @return Sale\Result
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     */
    public function load(array $fields)
    {
        $r = $this->checkFields($fields);
        if(!$r->isSuccess())
        {
            throw new Main\ArgumentException('ORDER_ID is not defined');
        }

        if(!$this->isLoadedParentEntity() && !empty($fields['ORDER_ID']))
        {
            $this->setParentEntity(Order::load($fields['ORDER_ID']));
        }

        if($this->isLoadedParentEntity())
        {
            $parentEntity = $this->getParentEntity();

            if(!empty($fields['ID']))
            {
                $shipment = $parentEntity->getShipmentCollection()->getItemById($fields['ID']);
            }

            /** @var Shipment $shipment*/
            if(!empty($shipment) && !$shipment->isSystem())
            {
                $this->setEntity($shipment);
            }
            else
            {
                $this->setExternal();
            }
        }
		return new Sale\Result();
    }

    /**
     * @param Order $order
     * @param Sale\BasketItem $basketItem
     * @param $fields
     * @return int
     * @throws Main\ObjectNotFoundException
     */
    private function getBasketItemQuantity(Order $order, Sale\BasketItem $basketItem, $fields)
    {
        $allQuantity = 0;
        /** @var Shipment $shipment */
        foreach ($order->getShipmentCollection() as $shipment)
        {
            $criterion = $this->getCurrentCriterion($shipment);

            if (!$criterion->equalsForList($fields))
                continue;

            $allQuantity += (int)$shipment->getBasketItemQuantity($basketItem);
        }

        return $allQuantity;
    }

    /**
     * @param Shipment $shipment
     * @param Basket $basket
     * @param array $params
     * @return Sale\Result
     * @throws Main\ObjectNotFoundException
     */
    private function fillShipmentItems(Shipment $shipment, Basket $basket, array $params)
    {
        $result = new Sale\Result();

        $order = $basket->getOrder();

        $fields = $params['TRAITS'];
        $fieldsBasketItems = $params['ITEMS'];

        if(is_array($fieldsBasketItems))
        {
            foreach($fieldsBasketItems as $items)
            {
                foreach($items as $productXML_ID => $item)
                {
                    if($productXML_ID == 'ORDER_DELIVERY')
                    	continue;

                	if($item['TYPE'] == Exchange\ImportBase::ITEM_ITEM)
                    {
                        if($basketItem = OrderImport::getBasketItemByItem($basket, $item))
                        {
                            /** @var Sale\BasketItem $basketItem */
                            $basketItemQuantity = $this->getBasketItemQuantity($order, $basketItem, $fields);

                            $shipmentItem = self::getShipmentItem($shipment, $basketItem);

                            $deltaQuantity = $item['QUANTITY'] - $shipmentItem->getQuantity();

                            if($deltaQuantity < 0)
                            {
                                $this->fillShipmentItem($shipmentItem, 0, $deltaQuantity);
                            }
                            elseif($deltaQuantity > 0)
                            {
                                if($basketItemQuantity >= $item['QUANTITY'])
                                {
                                    /** @var Sale\Shipment $systemShipment */
                                    $systemShipment = $order->getShipmentCollection()->getSystemShipment();
                                    $systemBasketQuantity = $systemShipment->getBasketItemQuantity($basketItem);

                                    if($systemBasketQuantity >= $deltaQuantity)
                                    {
                                        $this->fillShipmentItem($shipmentItem, $item['QUANTITY'], $shipmentItem->getQuantity());
                                    }
                                    else
                                    {
                                        $needQuantity = $deltaQuantity - $systemBasketQuantity;

                                        $r = $this->synchronizeQuantityShipmentItems($basketItem, $needQuantity, $fields);
                                        if($r->isSuccess())
                                        {
                                            $this->fillShipmentItem($shipmentItem, $item['QUANTITY'], $shipmentItem->getQuantity());
                                        }
                                        else
                                        {
                                            $this->setCollisions(EntityCollisionType::ShipmentBasketItemsModifyError, $shipment);
                                        }
                                    }
                                }
                                else
                                {
                                    $this->setCollisions(EntityCollisionType::ShipmentBasketItemQuantityError, $shipment, $item['NAME']);
                                }
                            }
                        }
                        else
                        {
                            $this->setCollisions(EntityCollisionType::ShipmentBasketItemNotFound, $shipment);
                        }
                    }
                    else
					{
						$this->setCollisions(EntityCollisionType::OrderBasketItemTypeError, $shipment, $item['NAME']);
					}
                }
            }
        }
        return $result;
    }

    /**
     * @param Shipment $shipment
     * @param Sale\BasketItem $basketItem
     * @return Sale\ShipmentItem|null
     * @throws Main\ObjectNotFoundException
     */
    private static function getShipmentItem(Sale\Shipment $shipment, Sale\BasketItem $basketItem)
    {
        /** @var Sale\ShipmentItemCollection $shipmentItemCollection */
        if (!$shipmentItemCollection = $shipment->getShipmentItemCollection())
        {
            throw new Main\ObjectNotFoundException('Entity "ShipmentItemCollection" not found');
        }

        $shipmentItem = $shipmentItemCollection->getItemByBasketCode($basketItem->getBasketCode());
        if (empty($shipmentItem))
        {
            $shipmentItem = $shipmentItemCollection->createItem($basketItem);
        }
        return $shipmentItem;
    }

    /**
     * @param Sale\ShipmentItem $shipmentItem
     * @param $value
     * @param $oldValue
     * @return Sale\Result
     */
    private function fillShipmentItem(Sale\ShipmentItem $shipmentItem, $value, $oldValue)
    {
        $result = new Sale\Result();

        $deltaQuantity = $value - $oldValue;

        if($shipmentItem->getQuantity() + $deltaQuantity == 0)
        {
            $r = $shipmentItem->delete();
        }
        else
        {
            $r = $shipmentItem->setField(
                "QUANTITY",
                $shipmentItem->getQuantity() + $deltaQuantity
            );
        }

        /** @var Sale\ShipmentItemCollection $shipmentItemCollection */
        $shipmentItemCollection = $shipmentItem->getCollection();

        /** @var Shipment $shipment */
        if (!$shipment = $shipmentItemCollection->getShipment())
        {
            if(!$r->isSuccess())
            {
                $result->addErrors($r->getErrors());
                $this->setCollisions(EntityCollisionType::OrderShipmentItemsModifyError, $shipment, implode(',', $r->getErrorMessages()));
            }
            else
            {
                $this->setCollisions(EntityCollisionType::OrderShipmentItemsModify, $shipment);
            }
        }

        return $result;
    }

    /**
     * Decrease total product quantity existing across all shipments by the specified value.
     * Difference between the required decrease of quantity of shipped product and quantity existing in the system shipment.
     * System shipment will specify the quantity required to remove the product from the cart or update the selected shipment
     * Pass the decrease value to system shipment.
     * Thus we decrease product quantity in the shipments and add it to the system shipment.
     * We can decrease quantity for the shipments containing the product except the current shipment if it was selected and other shipments containing the product and matching selection citeria (Exchange\IShipmentCriterion implementation).
     * If we decrease quantity relative to a specific shipment, we assume the quantity relocated to the system shipment will later be added to the selected shipment.
     * @param Sale\BasketItem $basketItem
     * @param $needQuantity
     * @param array $fields
     * @return Sale\Result
     * @throws Main\ObjectNotFoundException
     * @internal
     */
    public function synchronizeQuantityShipmentItems(Sale\BasketItem $basketItem, $needQuantity, array $fields = array())
    {
        $result = new Sale\Result();

        if(intval($needQuantity) <= 0)
        {
            return $result;
        }

        $entity = $this->getEntity();

        /** @var Sale\Order $order */
        $order = $this->getParentEntity();
        $shipmentCollection = $order->getShipmentCollection();

        /** @var Sale\Shipment $entity */
        foreach ($shipmentCollection as $shipment)
        {
            /** @var Sale\Shipment $shipment */
            if(!empty($entity) && $entity->getId() == $shipment->getId())
                continue;

            $criterion = $this->getCurrentCriterion($shipment);

            if (!$criterion->equalsForList($fields, false))
                continue;

            $basketQuantity = $shipment->getBasketItemQuantity($basketItem);
            if(empty($basketQuantity))
                continue;

            $shipmentItem = self::getShipmentItem($shipment, $basketItem);

            if($basketQuantity >= $needQuantity)
            {
                $this->fillShipmentItem($shipmentItem, 0, $needQuantity);
                $needQuantity = 0;
            }
            else
            {
                $this->fillShipmentItem($shipmentItem, 0, $basketQuantity);
                $needQuantity -= $basketQuantity;
            }

            $this->setCollisions(EntityCollisionType::ShipmentBasketItemsModify, $shipment);

            if($needQuantity == 0)
                break;
        }

        if($needQuantity != 0)
            $result->addError(new Error(GetMessage('SALE_EXCHANGE_ENTITY_SHIPMENT_SYNCHRONIZE_QUANTITY_ERROR'), 'SYNCHRONIZE_QUANTITY_ERROR'));

        return $result;
    }

    /**
     * @param $fields
     * @return array
     */
    public function prepareFieldsDeliveryService($fields)
    {
        $result = array();
        foreach($fields["ITEMS"] as $items)
        {
            foreach($items as $item)
            {
                if($item['TYPE'] == Exchange\ImportBase::ITEM_SERVICE)
                {
                    $result = array(
                        "CUSTOM_PRICE_DELIVERY" => "Y",
                        "BASE_PRICE_DELIVERY" => $item["PRICE"],
                        "CURRENCY" => $this->settings->getCurrency()
                    );
                    break 2;
                }
            }
        }

        return $result;
    }

    /**
     * @param array $fields
     */
    public function refreshData(array $fields)
    {
        /** @var Sale\Shipment $entity */
        $entity = $this->getEntity();
        if(!empty($entity) && $entity->isShipped())
        {
            if($fields['DEDUCTED'] == 'N')
                $entity->setField('DEDUCTED', 'N');
        }
    }

    /**
     * @param Internals\Entity $shipment
     * @return int
     * @throws Main\ArgumentException
     */
    public static function resolveEntityTypeId(Internals\Entity $shipment)
    {
        if(!($shipment instanceof Shipment))
            throw new Main\ArgumentException("Entity must be instanceof Shipment");

        return Exchange\EntityType::SHIPMENT;
    }

	/**
	 * @param Exchange\ICriterionShipment $criterion
	 * @throws Main\ArgumentException
	 */
	public function loadCriterion($criterion)
    {
        if(!($criterion instanceof Exchange\ICriterionShipment))
            throw new Main\ArgumentException("Criterion must be instanceof ICriterionShipment");

        $this->loadCriterion = $criterion;
    }
}