<?php

namespace Wienkit\BolPlazaClient\Entities;

/**
 * Class BolPlazaOrderItem
 * @package Wienkit\BolPlazaClient\Entities
 *
 * @param string $OrderItemId
 * @param string $OrderId
 * @param string $OrderDate
 * @param string $EAN
 * @param string $OfferReference
 * @param string $Title
 * @param string $Quantity
 * @param string $OfferPrice
 * @param string $PromisedDeliveryDate
 * @param string $TransactionFee
 * @param string $OfferCondition
 * @param string $CancelRequest
 */
class BolPlazaOrderItem extends BaseModel {

    protected $xmlEntityName = 'OrderItem';

    protected $attributes = [
        'OrderItemId',
		'OrderId',
		'OrderDate',
        'EAN',
        'OfferReference',
        'Title',
        'Quantity',
        'OfferPrice',
        'TransactionFee',
        'LatestDeliveryDate',
        'FulfilmentMethod',
        'PromisedDeliveryDate',
        'OfferCondition',
        'CancelRequest'
    ];

}
