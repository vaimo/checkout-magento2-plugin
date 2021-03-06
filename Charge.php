<?php
namespace Dfe\CheckoutCom;
use Df\Config\Source\NoWhiteBlack as NWB;
use Dfe\CheckoutCom\Patch\CardTokenChargeCreate;
use com\checkout\ApiServices\SharedModels\Address as CAddress;
use com\checkout\ApiServices\SharedModels\Phone as CPhone;
use com\checkout\ApiServices\SharedModels\Product as CProduct;
use Dfe\CheckoutCom\Settings as S;
use libphonenumber\PhoneNumberUtil as PhoneParser;
use libphonenumber\PhoneNumber as ParsedPhone;
use Magento\Payment\Model\Info;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Store\Model\Store;
class Charge extends \Df\Payment\Charge\WithToken {
	/**
	 * 2016-05-06
	 * @return CardTokenChargeCreate
	 */
	private function _build() {
		/** @var CardTokenChargeCreate $result */
		$result = new CardTokenChargeCreate;
		df_assert($this->o()->getIncrementId());
		/**
		 * 2016-05-08
		 * How To Use Billing Descriptors to Decrease Chargebacks
		 * https://www.checkout.com/blog/billing-descriptors/
		 */
		$result->setDescriptorDf(S::s()->statement());
		/**
		 * 2016-04-21
		 * «Order tracking id generated by the merchant.
		 * Max length of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 * 2016-05-03
		 * It is not required, but it is pleasant to have the order number in the «Track ID» row
		 * instead of «Unknown» value.
		 *
		 * 2016-05-08
		 * Since now, the «Track ID» is vital for us,
		 * because it is used for the payment identification
		 * when the customer is returned to the store after 3D-Secure verification.
		 *
		 * My previous attempt was $result->setUdf1($this->payment()->getId());
		 * but it is wrong, because the order does not have ID on its placement,
		 * it is not saved in the database yet.
		 * But Increment ID is pregenerated, and we can rely on it.
		 *
		 * My pre-previous attept was to record a custom transaction to the database,
		 * but Magento 2 has a fixed number of transaction types,
		 * and it would take a lot of effort to add a new transaction type.
		 *
		 * 2016-05-08 (addition)
		 * After thinking more deeply I understand,
		 * that the linking a Checkout.com Charge to Magento Order is not required,
		 * because an order placement and 3D-Secure verification is done
		 * in the context of the current customer session,
		 * and we can get the order information from the session
		 * on the customer return from 3D-Secure verification.
		 * So we can just call @see \Magento\Checkout\Model\Session::getLastRealOrder()
		 * How to get the last order programmatically? https://mage2.pro/t/1528
		 */
		$result->setTrackId($this->o()->getIncrementId());
		$result->setCustomerName($this->addressSB()->getName());
		/**
		 * 2016-04-21
		 * «The authorised charge must captured within 7 days
		 * or the charge will be automatically voided by the system
		 * and the reserved funds will be released.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/capture-card-charge
		 *
		 * «Accepted values either 'y' or 'n'.
		 * Default is is set to 'y'.
		 * Defines if the charge will be authorised ('n') or captured ('y').
		 * Authorisations will expire in 7 days.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 * Even though the documentation states 'y' and 'n' — in lowercase,
		 * examples always use uppercase.
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#request-example
		 *
		 * 2016-05-09
		 * It seems that if the payment gateway returns a «Flagged» transaction,
		 * then the autoCapture param is ignored,
		 * and we need to seperately do a Capture transaction.
		 * https://mage2.pro/t/1565
		 * It is then a good idea to do a Review procedure on such transactions.
		 */
		$result->setAutoCapture($this->needCapture() ? 'Y' : 'N');
		/**
		 * 2016-04-21
		 * «Delayed capture time in hours between 0 and 168 inclusive
		 * that corresponds to 7 days (7x24).
		 * E.g. 0.5 interpreted as 30 mins.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setAutoCapTime(0);
		/**
		 * 2016-04-21
		 * «A valid charge mode: 1 for No 3D, 2 for 3D, 3 Local Payment.
		 * Default is 1 if not provided.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 *
		 * 2016-05-03
		 * In the risk settings dashboard
		 * 3D-Secure is forced for transactions above 150 USD.
		 */
		$result->setChargeMode($this->use3DS() ? 2 : 1);
		/**
		 * 2016-04-21
		 * How are an order's getCustomerEmail() and setCustomerEmail() methods
		 * implemented and used?
		 * https://mage2.pro/t/1308
		 *
		 * «The email address or customer id of the customer.»
		 * «Either email or customerId required.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setEmail($this->o()->getCustomerEmail());
		/**
		 * 2016-04-23
		 * Email and CustomerId cannot be used simultaneously 
		 * And the products field is only sent when using email
		 * https://github.com/CKOTech/checkout-php-library/blob/7c9312e9/com/checkout/ApiServices/Charges/ChargesMapper.php#L142
		 */
		/*if ($order->getCustomerId()) {
			$request->setCustomerId($order->getCustomerId());
		} */
		/**
		 * 2016-04-21
		 * «A description that can be added to this object.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setDescription($this->text(S::s()->description()));
		/**
		 * 2016-04-21
		 * «Expressed as a non-zero positive integer
		 * (i.e. decimal figures not allowed).
		 * Divide Bahraini Dinars (BHD), Kuwaiti Dinars (KWD),
		 * Omani Rials (OMR) and Jordanian Dinars (JOD) into 1000 units
		 * (e.g. "value = 1000" is equivalent to 1 Bahraini Dinar).
		 * Divide all other currencies into 100 units
		 * (e.g. "value = 100" is equivalent to 1 US Dollar).
		 * Checkout.com will perform the proper conversions for currencies
		 * that do not support fractional values.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setValue($this->cAmount());
		/**
		 * 2016-04-21
		 * «Three-letter ISO currency code
		 * representing the currency in which the charge was made.
		 * (refer to currency codes and names)»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCurrency($this->currencyCode());
		/**
		 * 2016-04-21
		 * «Transaction indicator. 1 for regular, 2 for recurring, 3 for MOTO.
		 * Defaults to 1 if not specified.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setTransactionIndicator(1);
		/**
		 * 2016-04-21
		 * «Customer/Card holder Ip.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCustomerIp($this->o()->getRemoteIp());
		/**
		 * 2016-04-21
		 * «A valid card token (with prefix card_tok_)»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setCardToken($this->token());
		$this->setProducts($result);
		/**
		 * 2016-04-23
		 * «Shipping address details.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setShippingDetails($this->cAddress());
		/**
		 * 2016-04-23
		 * «A hash of FieldName and value pairs e.g. {'keys1': 'Value1'}.
		 * Max length of key(s) and value(s) is 100 each.
		 * A max. of 10 KVP are allowed.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setMetadata($this->metaData());
		return $result;
	}

	/**
	 * 2016-05-06
	 * @return CAddress
	 */
	private function cAddress() {
		if (!isset($this->{__METHOD__})) {
			/** @var OrderAddress $a */
			$a = $this->addressSB();
			/** @var CAddress $result */
			$result = new CAddress;
			/**
			 * 2016-04-23
			 * «Address field line 1. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setAddressLine1($a->getStreetLine(1));
			/**
			 * 2016-04-23
			 * «Address field line 2. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setAddressLine2($a->getStreetLine(2));
			/**
			 * 2016-04-23
			 * «Address postcode. Max. length of 50 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setPostcode($a->getPostcode());
			/**
			 * 2016-04-23
			 * «The country ISO2 code e.g. US.
			 * See provided list of supported ISO formatted countries.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setCountry($a->getCountryId());
			/**
			 * 2016-04-23
			 * «Address city. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setCity($a->getCity());
			/**
			 * 2016-04-23
			 * «Address state. Max length of 100 characters.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setState($a->getRegion());
			/**
			 * 2016-04-23
			 * «Contact phone object for the card holder.
			 * If provided, it will contain the countryCode and number properties
			 * e.g. 'phone':{'countryCode': '44' , 'number':'12345678'}.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$result->setPhone($this->cPhone());
			/**
			 * 2016-04-23
			 * «Shipping address details.»
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
			 */
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-06
	 * @param float|null $amount
	 * @return int
	 */
	private function cAmount($amount = null) {
		return Method::amount($this->payment(), $amount ? $amount : $this->amount());
	}

	/**
	 * 2016-05-06
	 * @return CPhone
	 */
	private function cPhone() {
		if (!isset($this->{__METHOD__})) {
			/**
			 * 2016-05-03
			 * https://github.com/giggsey/libphonenumber-for-php#quick-examples
			 * @var PhoneParser $phoneParser
			 */
			$phoneParser = PhoneParser::getInstance();
			/** @var CPhone $result */
			$result = new CPhone;
			try {
				/** @var ParsedPhone $parsedPhone */
			    $parsedPhone = $phoneParser->parse(
					$this->addressSB()->getTelephone(), $this->addressSB()->getCountryId()
				);
				/**
				 * 2016-04-23
				 * «Contact phone number for the card holder.
				 * Its length should be between 6 and 25 characters.
				 * Allowed characters are: numbers, +, (,) ,/ and ' '.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$result->setNumber($parsedPhone->getNationalNumber());
				/**
				 * 2016-04-23
				 * «Country code for the phone number of the card holder
				 * e.g. 44 for United Kingdom.
				 * Please refer to Country ISO and Code section
				 * in the Other Codes menu option.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$result->setCountryCode($parsedPhone->getCountryCode());
			} catch (\libphonenumber\NumberParseException $e) {}
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-06
	 * @param OrderItem $item
	 * @return CProduct
	 */
	private function cProduct(OrderItem $item) {
		/** @var CProduct $result */
		$result = new CProduct;
		/**
		 * 2016-04-23
		 * «Name of product. Max of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		// Simple options have name similar to «New Very Prive-36-Almond»,
		// we'd rather see 'normal' names
		// like a custom product «New Very Prive»).
		$result->setName(
			$item->getParentItem()
			? $item->getParentItem()->getName()
			: $item->getName()
		);
		$result->setProductId($item->getProductId());
		/**
		 * 2016-04-23
		 * «Description of the product.Max of 500 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setDescription($item->getDescription());
		/**
		 * 2016-04-23
		 * «Stock Unit Identifier.
		 * Unique product identifier.
		 * Max length of 100 characters.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setSku($item->getSku());
		/**
		 * 2016-04-23
		 * «Product price per unit. Max. of 6 digits.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 *
		 * 2016-05-03
		 * Here we do not use @see \Dfe\CheckoutCom\Method::amount(),
		 * because we're in a situation where we have to 
		 * send rubles rather than kopeks
		 * (couldn't find anything about that from the Checkout.com dashboard).
		 */
		$result->setPrice(df_order_item_price($item));
		/**
		 * 2016-04-23
		 * «Units of the product to be shipped. Max length of 3 digits.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setQuantity($item->getQtyOrdered());
		/**
		 * 2016-04-23
		 * «image link to product on merchant website.»
		 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
		 */
		$result->setImage(df_product_image_url($item->getProduct()));
		return $result;
	}

	/**
	 * 2016-06-25
	 * https://github.com/CKOTech/checkout-magento2-plugin/issues/1
	 * @return array(string => string)
	 */
	private function metaData() {return [
		'server' => implode(' / ', [dfa($_SERVER, 'SERVER_SOFTWARE'), dfa($_SERVER, 'HTTP_USER_AGENT')])
		,'quote_id' => $this->o()->getIncrementId()
		// 2016-06-25
		// Magento version
		,'magento_version' => df_magento_version()
		// 2016-06-26
		// The version of the your Magento/Checkout plugin the merchant is using
		,'plugin_version' => df_package_version('mage2pro/checkout.com')
		// 2016-06-25
		// The version of our PHP core library (if you are using the our PHP core library)
		,'lib_version' => \CheckoutApi_Client_Constant::LIB_VERSION
		// 2016-06-25
		// JS/API/Kit
		,'integration_type' => 'Kit'
		// 2016-06-25
		// Merchant\'s server time
		// Something like "2015-02-11T06:16:47+0100" (ISO 8601)
		,'time' => df_now('Y-m-d\TH:i:sO', 'Europe/London')
	];}

	/** @return bool */
	private function needCapture() {return $this[self::$P__NEED_CAPTURE];}

	/**
	 * 2016-05-06
	 * @param CardTokenChargeCreate $request
	 * @return void
	 */
	private function setProducts(CardTokenChargeCreate $request) {
		foreach ($this->o()->getItems() as $item) {
			/** @var OrderItem $item */
			/**
			 * 2016-03-24
			 * If the item is customisable then use
			 * @uses \Magento\Sales\Model\Order::getItems()
			 * It will include the customised product and its simple version.
			 */
			if (!$item->getChildrenItems()) {
				/**
				 * 2016-04-23
				 * «An array of Product details»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setProducts($this->cProduct($item));
			}
		}
	}

	/**
	 * 2016-05-13
	 * @return bool
	 */
	private function use3DS() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} =
				S::s()->force3DS_forAll()
				|| S::s()->force3DS_forNew() && df_customer_is_new($this->o()->getCustomerId())
				|| S::s()->force3DS_forShippingDestinations($this->addressSB()->getCountryId())
				/**
				 * 2016-05-31
				 * Today it seems that the PHP request to freegeoip.net stopped returning any value,
				 * whereas it still returns results when the request is sent from the browser.
				 * Apparently, freegeoip.net banned my User-Agent?
				 * In all cases, we cannot rely on freegeoip.net and risk getting an empty response.
				 * @uses df_visitor()
				 */
				|| S::s()->force3DS_forIPs(df_visitor()->iso2() ?: $this->addressSB()->getCountryId())
			;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-06
	 * @override
	 * @return void
	 */
	protected function _construct() {
		parent::_construct();
		$this->_prop(self::$P__NEED_CAPTURE, RM_V_BOOL, false);
	}

	/** @var string */
	private static $P__NEED_CAPTURE = 'need_capture';

	/**
	 * 2016-05-06
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param string $token
	 * @param float|null $amount [optional]
	 * @param bool $capture [optional]
	 * @return CardTokenChargeCreate
	 */
	public static function build(InfoInterface $payment, $token, $amount = null, $capture = true) {
		return (new self([
			self::$P__AMOUNT => $amount
			, self::$P__NEED_CAPTURE => $capture
			, self::$P__PAYMENT => $payment
			, self::$P__TOKEN => $token
		]))->_build();
	}
}