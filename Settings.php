<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiClient as API;
use com\checkout\ApiServices\Charges\ChargeService;
use Magento\Framework\App\ScopeInterface;
class Settings extends \Df\Core\Settings {
	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a New Customer»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function actionForNew($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a Returned Customer»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function actionForReturned($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return API
	 */
	public function api($s = null) {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = new API($this->secretKey($s), $this->test($s) ? 'sandbox' : 'live');
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return ChargeService
	 */
	public function apiCharge($s = null) {return $this->api($s)->chargeService();}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Description»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function description($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-02-27
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Enable?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function enable($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-03-14
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Metadata»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string[]
	 */
	public function metadata($s = null) {return $this->csv(__FUNCTION__, $s);}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string|false
	 */
	public function prefill($s = null) {return $this->bv(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function publishableKey($s = null) {
		return $this->test($s) ? $this->testPublishableKey($s) : $this->livePublishableKey($s);
	}

	/**
	 * 2016-03-02
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function secretKey($s = null) {
		return $this->test($s) ? $this->testSecretKey($s) : $this->liveSecretKey($s);
	}

	/**
	 * @override
	 * @used-by \Df\Core\Settings::v()
	 * @return string
	 */
	protected function prefix() {return 'df_payment/checkout_com/';}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Publishable Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function livePublishableKey($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Secret Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function liveSecretKey($s = null) {return $this->p(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Mode?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function test($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Publishable Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function testPublishableKey($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Secret Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function testSecretKey($s = null) {return $this->p(__FUNCTION__, $s);}

	/** @return $this */
	public static function s() {static $r; return $r ? $r : $r = df_o(__CLASS__);}
}


