<?php

namespace Wienkit\BolPlazaClient\Entities;

/**
 * Class BolPlazaOfferResponse
 * @package Wienkit\BolPlazaClient\Entities
 *
 * @property BolPlazaRetailerOffer[] RetailerOffers
 */
class BolPlazaOfferResponse extends BaseModel {

    protected $xmlEntityName = 'OfferResponse';

    protected $childEntities = [
        'RetailerOffers' => [
            'childName' => 'RetailerOffer',
            'entityClass' => 'BolPlazaRetailerOffer'
        ]
    ];
}