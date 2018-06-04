<?php

namespace Picqer\BolPlazaClient\Entities;

/**
 * Class BolPlazaOfferUpdate
 * @package Picqer\BolPlazaClient\Entities
 *
 * @property string $Published
 * @property string $ErrorCode
 * @property string $ErrorMessage
 */
class BolPlazaOfferStatus extends BaseModel {

    protected $xmlEntityName = 'RetailerOfferStatus';

    protected $attributes = [
		'Published',
		'ErrorCode',
    	'ErrorMessage'
    ];
}
