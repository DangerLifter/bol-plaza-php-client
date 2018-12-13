<?php

namespace Wienkit\BolPlazaClient;

use Wienkit\BolPlazaClient\Entities\BolPlazaCommission;
use Wienkit\BolPlazaClient\Entities\BolPlazaDeliveryWindowTimeSlot;
use Wienkit\BolPlazaClient\Entities\BolPlazaOfferResponse;
use Wienkit\BolPlazaClient\Entities\BolPlazaReductionList;
use Wienkit\BolPlazaClient\Entities\BolPlazaRetailerOfferIdentifier;
use Wienkit\BolPlazaClient\Requests\BolPlazaDeleteBulkRequest;
use Wienkit\BolPlazaClient\Requests\BolPlazaUpsertRequest;
use Wienkit\BolPlazaClient\Entities\BolPlazaReturnItem;
use Wienkit\BolPlazaClient\Entities\BolPlazaReturnItemStatusUpdate;
use Wienkit\BolPlazaClient\Entities\BolPlazaProcessStatus;
use Wienkit\BolPlazaClient\Entities\BolPlazaOrderItem;
use Wienkit\BolPlazaClient\Entities\BolPlazaCancellation;
use Wienkit\BolPlazaClient\Entities\BolPlazaOfferFile;
use Wienkit\BolPlazaClient\Entities\BolPlazaShipment;
use Wienkit\BolPlazaClient\Entities\BolPlazaChangeTransportRequest;
use Wienkit\BolPlazaClient\Entities\BolPlazaShipmentRequest;
use Wienkit\BolPlazaClient\Entities\BolPlazaInventory;
use Wienkit\BolPlazaClient\Entities\BolPlazaInboundRequest;
use Wienkit\BolPlazaClient\Entities\BolPlazaInbound;
use Wienkit\BolPlazaClient\Entities\BolPlazaInboundProductlabelsRequest;
use Wienkit\BolPlazaClient\Exceptions\BolPlazaClientException;
use Wienkit\BolPlazaClient\Exceptions\BolPlazaClientRateLimitException;
use Wienkit\BolPlazaClient\Requests\CurlHttpRequest;

class BolPlazaClient
{
    const URL_LIVE = 'https://plazaapi.bol.com';
    const URL_TEST = 'https://test-plazaapi.bol.com';
    const API_VERSION = 'v2';
    const OFFER_API_VERSION = 'v2';

    const HTTP_TIMEOUT = 60;
    const HTTP_USER_AGENT = "'Wienkit BolPlaza PHP Client (wienkit.com)'";

    private $testMode = false;
    private $skipSslVerification = false;
    // parse response headers
    private $parseHeaders = false;

    private $publicKey;
    private $privateKey;

    // response headers placeholder
    private $responseHeaders = [];

    /**
     * BolPlazaClient constructor.
     * @param $publicKey
     * @param $privateKey
     */
    public function __construct($publicKey, $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * Enable or disable testmode (default disabled)
     * @param $mode boolean
     */
    public function setTestMode($mode)
    {
        $this->testMode = $mode;
    }

    /**
     * Skip SSL verification in communication with server, only use in test cases
     * @param bool|true $mode
     */
    public function setSkipSslVerification($mode = true)
    {
        $this->skipSslVerification = $mode;
    }

    /**
     * Get list of orders
     * @return array
     */
    public function getOrders($page = 1, $fulfilmentMethod = 'FBR')
    {
        $parameters = [
            'page' => $page,
            'fulfilment-method' => $fulfilmentMethod
        ];

        $url = '/services/rest/orders/' . self::API_VERSION;

        $apiResult = $this->makeRequest('GET', $url, $parameters, ['Accept: application/vnd.orders-v2.1+xml']);
        $orders = BolPlazaDataParser::createCollectionFromResponse('BolPlazaOrder', $apiResult);
        return $orders;
    }

    /**
     * Get single order
     * @param string $orderId
     * @return Mixed BolPlazaOrder|null
     */
    public function getOrder($orderId)
    {
        $url = sprintf(
            "/services/rest/orders/%s/%s",
            self::API_VERSION,
            $orderId
        );
        $apiResult = $this->makeRequest('GET', $url, null, ['Accept: application/vnd.orders-v2.1+xml']);
        $order = BolPlazaDataParser::createCollectionFromResponse('BolPlazaOrder', $apiResult);
        return isset($order[0]) ? $order[0] : null;
    }

    /**
     * Get list of shipments
     * @param int $page The page of the set of shipments
     * @param string $fulfilmentMethod
     * @param string|null $orderId
     * @return array
     */
    public function getShipments($page = 1, $fulfilmentMethod = 'FBR', $orderId = null)
    {
        $parameters = [
            'page' => $page,
            'fulfilment-method' => $fulfilmentMethod,
        ];

        if (!is_null($orderId)) {
            $parameters['order-id'] = $orderId;
        }

        $url = '/services/rest/shipments/' . self::API_VERSION;
        $apiResult = $this->makeRequest('GET', $url, $parameters, ['Accept: application/vnd.shipments-v2.1+xml']);
        $shipments = BolPlazaDataParser::createCollectionFromResponse('BolPlazaShipment', $apiResult);
        return $shipments;
    }

    /**
     * Get list of BolPlazaReturnItem entities
     * @return array
     */
    public function getReturnItems()
    {
        $url = '/services/rest/return-items/' . self::API_VERSION . '/unhandled';
        $apiResult = $this->makeRequest('GET', $url);
        $returnItems = BolPlazaDataParser::createCollectionFromResponse('BolPlazaReturnItem', $apiResult);
        return $returnItems;
    }

    /**
     * Get list of BolPlazaPayment entities
     * @return array
     */
    public function getPayments($period)
    {
        $url = '/services/rest/payments/' . self::API_VERSION . '/' . $period;
        $apiResult = $this->makeRequest('GET', $url);
        $payments = BolPlazaDataParser::createCollectionFromResponse('BolPlazaPayment', $apiResult);
        return $payments;
    }

    /**
     * Handle a BolPlazaReturnItem
     * @param BolPlazaReturnItem $returnItem
     * @param BolPlazaReturnItemStatusUpdate $status
     * @return BolPlazaProcessStatus
     */
    public function handleReturnItem(Entities\BolPlazaReturnItem $returnItem, Entities\BolPlazaReturnItemStatusUpdate $status)
    {
        $url = '/services/rest/return-items/' . self::API_VERSION . '/' . $returnItem->ReturnNumber . '/handle';
        $xmlData = BolPlazaDataParser::createXmlFromEntity($status);
        $apiResult = $this->makeRequest('PUT', $url, $xmlData);
        /** @var BolPlazaProcessStatus $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
        return $result;
    }

    /**
     * Cancel an OrderItem
     * @param BolPlazaOrderItem $orderItem
     * @param BolPlazaCancellation $cancellation
     * @return BolPlazaProcessStatus
     */
    public function cancelOrderItem(Entities\BolPlazaOrderItem $orderItem, Entities\BolPlazaCancellation $cancellation)
    {
        $url = '/services/rest/order-items/' . self::API_VERSION . '/' . $orderItem->OrderItemId . '/cancellation';
        $xmlData = BolPlazaDataParser::createXmlFromEntity($cancellation);
        $apiResult = $this->makeRequest('PUT', $url, $xmlData);
        /** @var BolPlazaProcessStatus $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
        return $result;
    }

    /**
     * Change Transport
     * @param BolPlazaShipment $shipment
     * @param BolPlazaChangeTransportRequest $changeRequest
     * @return BolPlazaProcessStatus
     */
    public function changeTransport(Entities\BolPlazaShipment $shipment, Entities\BolPlazaChangeTransportRequest $changeRequest)
    {
        $url = '/services/rest/transports/' . self::API_VERSION . '/' . $shipment->Transport->TransportId;
        $xmlData = BolPlazaDataParser::createXmlFromEntity($changeRequest);
        $apiResult = $this->makeRequest('PUT', $url, $xmlData);
        /** @var BolPlazaProcessStatus $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
        return $result;
    }

    /**
     * Add a shipment
     * @see https://developers.bol.com/shipments-2-1/#Create_a_shipment_21
     * @param BolPlazaShipmentRequest $shipmentRequest
     * @return BolPlazaProcessStatus
     */
    public function processShipment(Entities\BolPlazaShipmentRequest $shipmentRequest)
    {
        $url = '/services/rest/shipments/' . self::API_VERSION;
        $xmlData = BolPlazaDataParser::createXmlFromEntity($shipmentRequest, '2.1');
        $apiResult = $this->makeRequest('POST', $url, $xmlData, ['Accept: application/vnd.shipments-v2.1+xml']);
        /** @var BolPlazaProcessStatus $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
        return $result;
    }

    /**
     * Get the ProcessStatus
     * @param string $processStatusId
     * @return BolPlazaProcessStatus
     */
    public function getProcessStatus($processStatusId)
    {
      $url = '/services/rest/process-status/' . self::API_VERSION . '/' . $processStatusId;
      $apiResult = $this->makeRequest('GET', $url);
      /** @var BolPlazaProcessStatus $result */
      $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
      return $result;
    }

    /**
     * Create an offer
     * @param BolPlazaUpsertRequest $upsertRequest
     * @return string
     */
    public function createOffer(BolPlazaUpsertRequest $upsertRequest)
    {
        return $this->updateOffer($upsertRequest);
    }

    /**
     * Update an offer
     * @param BolPlazaUpsertRequest $upsertRequest
     * @return string
     */
    public function updateOffer(BolPlazaUpsertRequest $upsertRequest)
    {
        $url = '/offers/' . self::OFFER_API_VERSION . '/';
        $xmlData = BolPlazaDataParser::createOfferXmlFromEntity($upsertRequest);
        $apiResult = $this->makeRequest('PUT', $url, $xmlData);
        return $apiResult;
    }

    /**
     * Update an offer stock
     *
     * @param BolPlazaUpsertRequest $upsertRequest
     * @return string
     */
    public function updateOfferStock(BolPlazaUpsertRequest $upsertRequest)
    {
        return $this->updateOffer($upsertRequest);
    }

    /**
     * Delete an offer
     * @param $ean
     * @param $condition
     * @return string
     */
    public function deleteOffer($ean, $condition)
    {
        $retailerOfferIdentifier = new BolPlazaRetailerOfferIdentifier();
        $retailerOfferIdentifier->EAN = $ean;
        $retailerOfferIdentifier->Condition = $condition;
        $request = new BolPlazaDeleteBulkRequest();
        $request->RetailerOfferIdentifier = $retailerOfferIdentifier;
        return $this->deleteOffers($request);
    }

    /**
     * Delete (bulk) offer(s)
     * @param BolPlazaDeleteBulkRequest $deleteBulkRequest
     * @return string
     */
    public function deleteOffers(BolPlazaDeleteBulkRequest $deleteBulkRequest)
    {
        $url = '/offers/' . self::OFFER_API_VERSION . '/';
        $xmlData = BolPlazaDataParser::createOfferXmlFromEntity($deleteBulkRequest);
        $apiResult = $this->makeRequest('DELETE', $url, $xmlData);
        return $apiResult;
    }

    /**
     * @param $ean
     * @param $condition
     * @return BolPlazaOfferResponse
     */
    public function getSingleOffer($ean, $condition = false)
    {
        $url = '/offers/' . self::OFFER_API_VERSION . '/' . $ean;
        if ($condition) {
            $url .= '?condition=' . $condition;
        }
        $apiResult = $this->makeRequest('GET', $url);
        /** @var BolPlazaOfferResponse $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaOfferResponse', $apiResult);
        return $result;
    }

    /**
     * Retrieve the commission
     * @param $ean
     * @param $condition
     * @param $price
     * @return BolPlazaCommission
     */
    public function getCommission($ean, $condition = false, $price = false)
    {
        $url = '/commission/' . self::OFFER_API_VERSION . '/' . $ean;
        $params = [];
        if ($condition) {
            $params['Condition'] = $condition;
        }
        if ($price) {
            $params['price'] = $price;
        }
        $apiResult = $this->makeRequest('GET', $url, $params);
        /** @var BolPlazaCommission $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaCommission', $apiResult);
        return $result;
    }

    /**
     * Get own offers file path
     * @param string $filter
     * @return BolPlazaOfferFile
     */
    public function getOwnOffers($filter = '')
    {
        $url = '/offers/' . self::OFFER_API_VERSION . '/export';
        $data = [];
        if(!empty($filter)) {
            $data['filter'] = $filter;
        }
        $apiResult = $this->makeRequest('GET', $url, $data);
        /** @var BolPlazaOfferFile $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaOfferFile', $apiResult);
        return $result;
    }

    /**
     * Get the own offers file contents
     * @param string $path
     * @return string
     */
    public function getOwnOffersResult($path = '')
    {
        $path = str_replace(self::URL_TEST, '', $path);
        $path = str_replace(self::URL_LIVE, '', $path);
        $apiResult = $this->makeRequest('GET', $path);
        return $apiResult;
    }

    /**
     * Get Latest Reductions filename
     * @see https://developers.bol.com/reductions-list/
     *
     * @access public
     * @return string
     */
    public function getLatestReductionsFilename()
    {
        $url = '/reductions/latest';
        $apiResult = $this->makeRequest('GET', $url);
        return $apiResult;
    }

    /**
     * Get Reductions
     *
     * @see https://developers.bol.com/reductions-list/
     *
     * @access public
     * @return BolPlazaReductionList
     */
    public function getReductions()
    {
        $url = '/reductions';
        $this->parseHeaders = true;
        $apiResult = $this->makeRequest('GET', $url);
        $filename = "";
        // get filename from header.
        if (isset($this->responseHeaders['Content-Disposition'])) {
            if (preg_match("/\w+\.\w+/", $this->responseHeaders['Content-Disposition'], $matches)) {
                $filename = isset($matches[0]) ? $matches[0] : null;
            }
        }
        return new BolPlazaReductionList($apiResult, $filename);
    }
    /**
     * Get inventory
     *
     * The inventory endpoint is a specific LVB/FBB endpoint. Meaning this only provides information
     * about your Fulfillment by bol.com Inventory. This endpoint does not provide information on your
     * own stock.
     *
     * @see https://developers.bol.com/get-inventory/
     * @access public
     * @param int $page
     * @param string/int $quantity
     * @param string $stock - valid options: sufficient, insufficient
     * @param string $state - valid options: saleable, unsaleable
     * @param string $query
     *
     * @return BolPlazaInventory
     */
    public function getInventory($page = 1, $quantity=null, $stock=null, $state=null, $query=null)
    {
        $url = '/services/rest/inventory';
        $params = ['page' => (int)$page];
        // append parameters.
        if (!is_null($quantity)) {
            $params['quantity'] = $quantity;
        }
        if (!is_null($stock) && in_array($stock, ['sufficient', 'insufficient'])) {
            $params['stock'] = $stock;
        }
        if (!is_null($state) && in_array($state, ['saleable', 'unsaleable'])) {
            $params['state'] = $state;
        }
        if (!is_null($query)) {
            $params['query'] = $query;
        }
        $apiResult = $this->makeRequest('GET', $url, $params);
        /** @var BolPlazaInventory $result */
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaInventory', $apiResult);
        return $result;
    }

    /**
     * Create LvB Inbound
     *
     * @see https://developers.bol.com/create-inbound/
     *
     * @access public
     * @param BolPlazaInboundRequest $inboundRequest
     * @return type
     */
    public function createInbound(BolPlazaInboundRequest $inboundRequest)
    {
        $url = '/services/rest/inbounds';
        $xmlData = BolPlazaDataParser::createXmlFromEntity($inboundRequest, '1');
        $apiResult = $this->makeRequest('POST', $url, $xmlData);
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaProcessStatus', $apiResult);
        return $result;

    }

    /**
     * Get delivery windows for specific date (LvB)
     *
     * @see https://developers.bol.com/get-delivery-window/
     * @param \DateTime $deliveryDate
     * @param int $qty
     * @return BolPlazaDeliveryWindowTimeSlot[]
     */
    public function getDeliveryWindows(\DateTime $deliveryDate, $qty)
    {
        $apiResult = $this->makeRequest('GET', '/services/rest/inbounds/delivery-windows', [
            'delivery-date' => $deliveryDate->format('Y-m-d'),
            'items-to-send' => (int)$qty
        ]);
        $timeSlots = BolPlazaDataParser::createCollectionFromResponse('BolPlazaDeliveryWindowTimeSlot', $apiResult);
        return $timeSlots;
    }

    /**
     * Get inbound details
     *
     * @see https://developers.bol.com/single-inbound/
     *
     * @access public
     * @param int $id
     * @return BolPlazaInbound
     */
    public function getSingleInbound($id)
    {
        $apiResult = $this->makeRequest('GET', '/services/rest/inbounds/' . (int)$id);
        return BolPlazaDataParser::createEntityFromResponse('BolPlazaInbound', $apiResult);
    }

    /**
     * Get (Inbound) Product labels
     *
     * @see https://developers.bol.com/productlabels/
     *
     * @access public
     * @param BolPlazaInboundProductlabelsRequest $request
     * @param string $format (AVERY_J8159, AVERY_J8160, AVERY_3474, DYMO_99012, BROTHER_DK11208D, ZEBRA_Z_PERFORM_1000T)
     * @return string - pdf contents
     */
    public function getProductLabels(BolPlazaInboundProductlabelsRequest $request, $format = null)
    {
        $xmlData = BolPlazaDataParser::createXmlFromEntity($request, '1');
        return $this->makeRequest('POST', "/services/rest/inbounds/productlabels?format={$format}", $xmlData);
    }

    /**
     * Get (Inbound) Packinglist
     *
     * @see https://developers.bol.com/packing-list-details/
     *
     * @access public
     * @param int $id
     * @return string - pdf contents
     */
    public function getPackinglist($id)
    {
        return $this->makeRequest('GET', "/services/rest/inbounds/" . (int)$id . "/packinglistdetails");
    }

    /**
     * Get Inbound list
     *
     * @see https://developers.bol.com/inbound-list/
     *
     * @access public
     * @param int $page
     * @return BolPlazaInbound[]
     */
    public function getInboundList($page = 1)
    {
        $apiResult = $this->makeRequest('GET', '/services/rest/inbounds', [
            'page' => (int)$page
        ]);
        $xml = BolPlazaDataParser::parseXmlResponse($apiResult);
        // parse inbound elements.
        if ($xml->Inbound) {
            $inbounds = [];
            foreach ($xml->Inbound as $simpleXmlInbound) {
                $inbounds[] = BolPlazaDataParser::createEntityFromResponse('BolPlazaInbound', $simpleXmlInbound);
            }
        }
        $result = BolPlazaDataParser::createEntityFromResponse('BolPlazaInbounds', $apiResult);
        // copy actual inbounds
        $result->Inbound = $inbounds;
        return $result;

    }

    /**
     * Makes the request to the server and processes errors
     *
     * @param string $method GET
     * @param string $endpoint URI of the resource
     * @param null|string $data POST data
     * @param array $headers Additional HTTP Headers
     * @return string XML
     * @throws BolPlazaClientException
     * @throws BolPlazaClientRateLimitException
     */
    protected function makeRequest($method = 'GET', $endpoint, $data = null, $headers = [])
    {
        $date = gmdate('D, d M Y H:i:s T');
        $contentType = 'application/xml';
        $url = $this->getUrlFromEndpoint($endpoint);

        // set new endpoint (endpoint without arguments, signature is based on endpoint without arguments)
        $parsedUrl = parse_url($url);
        $endpoint = isset($parsedUrl['path']) ? $parsedUrl['path'] : $endpoint;

        $signature = $this->getSignature($method, $contentType, $date, $endpoint);

        $headers = array_merge($headers, [
            'Content-type: ' . $contentType,
            'X-BOL-Date: ' . $date,
            'X-BOL-Authorization: ' . $signature
        ]);

        $httpRequest = $this->createHttpRequest($url);
        $httpRequest->setOption(CURLOPT_CUSTOMREQUEST, $method);
        $httpRequest->setOption(CURLOPT_RETURNTRANSFER, true);
        $httpRequest->setOption(CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
        $httpRequest->setOption(CURLOPT_HEADER, false);
        $httpRequest->setOption(CURLOPT_USERAGENT, self::HTTP_USER_AGENT);
        $httpRequest->setOption(CURLOPT_HTTPHEADER, $headers);

        if (in_array($method, ['POST', 'PUT', 'DELETE']) && ! is_null($data)) {
            $httpRequest->setOption(CURLOPT_POSTFIELDS, $data);
        } elseif ($method == 'GET' && !empty($data)) {
            $httpRequest->setOption(CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        if ($this->skipSslVerification) {
            $httpRequest->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $httpRequest->setOption(CURLOPT_SSL_VERIFYHOST, false);
        }
        if ($this->parseHeaders) {
            $httpRequest->setOption(CURLOPT_HEADERFUNCTION, function($curl, $line) {
                if (stristr($line, ":")) {
                    list($key,$value) = explode(":", $line);
                    $this->responseHeaders[$key] = $value;
                }
                return strlen($line);
            });
        }
        $result = $httpRequest->execute();
        $headerInfo = $httpRequest->getInfo();

        // check for errors in results
        $this->checkForErrors($httpRequest, $headerInfo, $result);

        $httpRequest->close();

        return $result;
    }

    /**
     * Get URL from endpoint
     *
     * @param string $endpoint
     * @return string
     */
    protected function getUrlFromEndpoint($endpoint)
    {
        if ($this->testMode) {
            return self::URL_TEST . $endpoint;
        } else {
            return self::URL_LIVE . $endpoint;
        }
    }

    /**
     * Calculates signature for request
     *
     * @param string $method HTTP method
     * @param string $contentType Probably only application/xml
     * @param string $date Current time (can only be 15 mins apart from Bol servers)
     * @param string $endpoint Endpoint without url
     * @return string
     */
    protected function getSignature($method, $contentType, $date, $endpoint)
    {
        $signatureBase = $method . "\n\n";
        $signatureBase .= $contentType . "\n";
        $signatureBase .= $date . "\n";
        $signatureBase .= 'x-bol-date:' . $date . "\n";
        $signatureBase .= $endpoint;

        $signature = $this->publicKey . ':' . base64_encode(hash_hmac('SHA256', $signatureBase, $this->privateKey, true));

        return $signature;
    }

    /**
     * Check if the API returned any errors
     *
     * @see https://plazaapi.bol.com/services/xsd/serviceerror-1.5.xsd
     * @see https://developers.bol.com/documentatie/plaza-api/developer-guide-plaza-api/error-codes-messages/
     * @param resource $httpRequest The CURL resource of the request
     * @param array $headerInfo
     * @param string $result
     * @throws BolPlazaClientException
     * @throws BolPlazaClientRateLimitException
     */
    protected function checkForErrors($httpRequest, $headerInfo, $result)
    {
        if ($httpRequest->getErrorNumber()) {
            throw new BolPlazaClientException($httpRequest->getErrorNumber());
        }

        if (intval($headerInfo['http_code']) < 200 || intval($headerInfo['http_code']) > 226) {
            if ($headerInfo['http_code'] == '409') {
                throw new BolPlazaClientRateLimitException();
            }
            if (!empty($result)) {
                $xmlObject = BolPlazaDataParser::parseXmlResponse($result);
                if (property_exists($xmlObject, 'ServiceErrors')) {
                    if (property_exists($xmlObject->ServiceErrors, 'ServiceError')) {
                        throw new BolPlazaClientException(
                            $xmlObject->ServiceErrors->ServiceError->ErrorMessage,
                            (int)$xmlObject->ServiceErrors->ServiceError->ErrorCode
                        );
                    }
                }
                if (property_exists($xmlObject, 'ValidationErrors')) {
                    if (property_exists($xmlObject->ValidationErrors, 'ValidationError')) {
                        throw new BolPlazaClientException(
                            $xmlObject->ValidationErrors->ValidationError->ErrorMessage,
                            (int)$xmlObject->ValidationErrors->ValidationError->ErrorCode
                        );
                    }
                }
                if (isset($xmlObject->ErrorCode) && !empty($xmlObject->ErrorCode)) {
                    throw new BolPlazaClientException($xmlObject->ErrorMessage, (int)$xmlObject->ErrorCode);
                }
                if (isset($xmlObject->errorCode) && !empty($xmlObject->errorCode)) {
                    throw new BolPlazaClientException($xmlObject->errorMessage, (int)$xmlObject->errorCode);
                }
            }
        }
    }

    /**
     * @param string $url
     * @return CurlHttpRequest
     */
    protected function createHttpRequest($url)
    {
        return new CurlHttpRequest($url);
    }
}