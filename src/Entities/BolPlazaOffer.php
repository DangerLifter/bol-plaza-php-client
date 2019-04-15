<?php

namespace Wienkit\BolPlazaClient\Entities;

/**
 * Class BolPlazaOfferUpdate
 * @package Wienkit\BolPlazaClient\Entities
 *
 * @property string $EAN
 * @property string $Price
 * @property string $DeliveryCode
 * @property string $QuantityInStock
 * @property string $UnreservedStock
 * @property string $Publish
 * @property string $ReferenceCode
 * @property string $Description
 * @property string $Title
 * @property string $FulfillmentMethod
 * @property string $Status
 */
class BolPlazaOffer extends BaseModel {

    protected $xmlEntityName = 'RetailerOffer';

	protected $nestedEntities = [
		'Status' => 'BolPlazaOfferStatus'
	];

    protected $attributes = [
		'EAN',
		'Condition',
    	'Price',
        'DeliveryCode',
		'QuantityInStock',
		'UnreservedStock',
        'Publish',
        'ReferenceCode',
        'Description',
		'Title',
		'FulfillmentMethod',
    ];
}
