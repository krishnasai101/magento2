<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Quote\Guest;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\GraphQl\Controller\GraphQl;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Tests SetPaymentMethod mutation for guest via authorizeNet payment
 *
 * @magentoAppArea graphql
 * @magentoDbIsolation disabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SetAuthorizeNetPaymentMethodOnGuestCartTest extends \PHPUnit\Framework\TestCase
{
    const CONTENT_TYPE = 'application/json';

    /** @var \Magento\Framework\ObjectManagerInterface */
    private $objectManager;

    /** @var  GetMaskedQuoteIdByReservedOrderId */
    private $getMaskedQuoteIdByReservedOrderId;

    /** @var GraphQl */
    private $graphql;

    /** @var SerializerInterface */
    private $jsonSerializer;

    /** @var Http */
    private $request;

    protected function setUp() : void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->graphql = $this->objectManager->get(\Magento\GraphQl\Controller\GraphQl::class);
        $this->jsonSerializer = $this->objectManager->get(SerializerInterface::class);
        $this->request = $this->objectManager->get(Http::class);
        $this->getMaskedQuoteIdByReservedOrderId = $this->objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
    }

    /**
     * @magentoConfigFixture default_store payment/authorizenet_acceptjs/active 1
     * @magentoConfigFixture default_store payment/authorizenet_acceptjs/environment sandbox
     * @magentoConfigFixture default_store payment/authorizenet_acceptjs/login someusername
     * @magentoConfigFixture default_store payment/authorizenet_acceptjs/trans_key somepassword
     * @magentoConfigFixture default_store payment/authorizenet_acceptjs/trans_signature_key abc
     * @magentoDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     */
    public function testDispatchToSetPaymentMethodWithAuthorizenet(): void
    {
        $methodCode = 'authorizenet_acceptjs';
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $query
            = <<<QUERY
 mutation {
  setPaymentMethodOnCart(input: {
      cart_id: "$maskedQuoteId"
      payment_method: {
          code: "$methodCode"
          additional_data:
         {authorizenet_acceptjs: 
            {opaque_data_descriptor: "COMMON.ACCEPT.INAPP.PAYMENT",
             opaque_data_value: "abx",
             cc_last_4: 1111}}
      }
  }) {    
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
QUERY;
        $postData = [
            'query' => $query,
            'variables' => null,
            'operationName' => null
        ];
        $this->request->setPathInfo('/graphql');
        $this->request->setMethod('POST');
        $this->request->setContent(json_encode($postData));
        $headers = $this->objectManager->create(\Zend\Http\Headers::class)
            ->addHeaders(['Content-Type' => 'application/json']);
        $this->request->setHeaders($headers);
        $response = $this->graphql->dispatch($this->request);
        $output = $this->jsonSerializer->unserialize($response->getContent());
        $this->assertArrayNotHasKey('errors', $output, 'Response has errors');
        $this->assertArrayHasKey('setPaymentMethodOnCart', $output['data']);
        $selectedPaymentMethod = $output['data']['setPaymentMethodOnCart']['cart']['selected_payment_method'];
        $this->assertEquals($methodCode, $selectedPaymentMethod['code']);
    }
}
