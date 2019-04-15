<?php

namespace Wienkit\BolPlazaClient\Entities;

/**
 * Class BolPlazaInventory
 * @package Wienkit\BolPlazaClient\Entities
 *
 * @property string $TotalCount
 * @property string $TotalPageCount
 * @property BolPlazaInventoryOffer[] $Offers
 */
class BolPlazaInventory extends BaseModel {

    protected $xmlEntityName = 'InventoryResponse';
    

    protected $attributes = [
        'TotalCount',
        'TotalPageCount'
    ];

    protected $childEntities = [
        'Offers' => [
            'childName' => 'Offer',
            'entityClass' => 'BolPlazaInventoryOffer'
        ]
    ];
    
}