<?php
/**
 * 2019 TokoPini
*
*  @author    TokoPini <support@tokopini.com>
*  @copyright 2009 TokoPini
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_'))
	exit;

class tokopiniapplyvoucher extends Module
{

	protected $configOptions = array(
		'TOKOPINI_APPLYVOUCHER_APIVERIFYURL',
		'TOKOPINI_APPLYVOUCHER_APIUSEURL',
		'TOKOPINI_APPLYVOUCHER_APIKEY',
		'TOKOPINI_ENABLE_APPLY_VOUCHER',
		'TOKOPINI_ENABLE_APPLY_VOUCHER_ERROR_LOG'
	);

    protected $jsonReviewContent;
	
	public function __construct()
	{
		$this->name = 'tokopiniapplyvoucher';
		$this->tab = 'others';
		$this->version = '1.0.1';
		$this->author = 'TokoPini Integrations';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		$this->displayName = $this->l('TokoPini - Apply Voucher');
		$this->description = $this->l('Sends Voucher to TokoPini');
		$this->confirmUninstall = $this->l('No TokoPini Integration? :(');

		if (($this->isConfigVarNad('TOKOPINI_APPLYVOUCHER_APIKEY') + ($this->isConfigVarNad('TOKOPINI_APPLYVOUCHER_APIURL'))) < 3){
			$this->warning = $this->l('Make sure that your API URL and API KEY are set.');
        }

		parent::__construct();
    }

	public function isConfigVarNad($config_var)
	{
		if (!Configuration::get($config_var) || (string)Configuration::get($config_var) == ''){
			return 2;
        } else {
			return 0;
        }
    }

	public function install()
	{
		
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);
			
		return parent::install() && 
				$this->registerHook('actionValidateOrder') && $this->registerHook('actionOrderStatusUpdate') && $this->registerHook('displayHeader');
	}

	public function uninstall()
	{
		foreach($this->configOptions as $configOption){
			Configuration::deleteByName($configOption);
		}

		return parent::uninstall() &&
                $this->unregisterHook('actionValidateOrder') && $this->unregisterHook('actionOrderStatusUpdate') && $this->unregisterHook('displayHeader');
	}

	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name)){
			foreach($this->configOptions as $updateOption){
				$val = (string)Tools::getValue($updateOption);
				if(!empty($val)){
					Configuration::updateValue($updateOption, $val);
				}
			}

			$output = $this->displayConfirmation($this->l('Settings Updated'));
		}

		return $output.$this->displayForm();
	}

	protected function yesNoOption($name, $title){
		$yes_no_options = array(array('id_option' => 1, 'name' => 'Yes'), array('id_option' => 2,	'name' => 'No'));
		return array('type' => 'select',
				'label' => $this->l($title),
				'name' => $name,
				'required' => true,
				'options' => array(
					'query' => $yes_no_options,
					'id' => 'id_option',
					'name' => 'name'
				)
		);
	}

	public function displayForm()
	{
		$fields_form = array();
		
		$fields_form[0]['form'] = array(
				'legend' => array('title' => $this->l('Settings')),
				'input' => array(
						$this->yesNoOption('TOKOPINI_ENABLE_APPLY_VOUCHER', 'Enable TokoPini Apply Vouchers:'),
						$this->yesNoOption('TOKOPINI_ENABLE_APPLY_VOUCHER_ERROR_LOG', 'Enable Error Logging:'),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API Verify Coupon URL'),
								'name' => 'TOKOPINI_APPLYVOUCHER_APIVERIFYURL',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API Use Coupon URL'),
								'name' => 'TOKOPINI_APPLYVOUCHER_APIUSEURL',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API Key'),
								'name' => 'TOKOPINI_APPLYVOUCHER_APIKEY',
								'size' => 20,
								'required' => true
						),

				),
				'submit' => array(
						'title' => $this->l('Save'),
						'class' => 'button'
				)
		);

		$helper = new HelperForm();

		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
				'save' =>
				array(
						'desc' => $this->l('Save'),
						'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
						'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
				'back' => array(
						'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
						'desc' => $this->l('Back to list')
				)
		);

		foreach($this->configOptions as $configOption){
			$helper->fields_value[$configOption] = Configuration::get($configOption);
		}

		return $helper->generateForm($fields_form);
	}
	
    public function hookactionValidateOrder($params) {
	
	    if (Configuration::get('TOKOPINI_ENABLE_APPLY_VOUCHER') == '1')
	    {
			$orderCouponCode = "";
			$order = $params['order'];
			$rules = $order->getCartRules();
			foreach($rules as $rule){
				$cartRule =  new CartRule($rule['id_cart_rule']);
				if(substr($cartRule->code, 0, 3) == 'TKP') {
					$orderCouponCode = $cartRule->code;
				}
			}
			
			if($orderCouponCode!="") { 
				$returnData = $this->useCoupon($orderCouponCode);
				if($returnData['http_status'] == '200'){
					
				} else {
					if(Configuration::get('TOKOPINI_ENABLE_APPLY_VOUCHER_ERROR_LOG') == '1') {
						$message = "Error Using Voucher: ". print_r($returnData['data'], true);
						PrestaShopLogger::addLog($message, 3);
						$logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
						$logger->setFilename(_PS_ROOT_DIR_."/log/tokopini_debug.log");
						$logger->logDebug("Voucher Code Used: ". $orderCouponCode);
						$logger->logDebug("Error Using Voucher: ". print_r($returnData['data'], true));
					}
				}
				$this->removeCouponRule($orderCouponCode); 
			}
		}
    }
    public function hookDisplayHeader($params)
    {
	    if (Configuration::get('TOKOPINI_ENABLE_APPLY_VOUCHER') == '1')
	    {
			// if order controller then apply voucher
			if (Tools::getValue('controller') == 'orderopc' || Tools::getValue('controller') == 'order') {
				//CartRules was not apply, cart not found
				if ($this->context->controller instanceof OrderController && Tools::getValue('discount_name')) {
					//check Coupon Code with TokoPini
					$returnData = $this->checkCoupon(Tools::getValue('discount_name'));
					if($returnData['http_status'] == '200'){
						$rewardCoupon['coupon_code'] = Tools::getValue('discount_name');
						$rewardCoupon['voucher_amount'] = (string)$returnData['data']->credit;
						if($this->generateAndApplyCouponRule($rewardCoupon)) {
							$this->context->controller->errors = array();
							$this->context->controller->success[] = $this->l('The following TokoPini voucher has successfully been applied:') . ' ' . Tools::getValue('discount_name');
						}
					} else {
						$this->context->controller->errors[] = $this->l('The following TokoPini voucher could not be applied:') . ' ' . Tools::getValue('discount_name');
					}
				}
			}
		}
	}
	 /* Called when Payment is confirmed */
    public function hookActionOrderStatusUpdate($params)
    {
	    if (Configuration::get('TOKOPINI_ENABLE_APPLY_VOUCHER') == '1')
	    {
			$new_status = $params['newOrderStatus'];
			// Execute request to add beans points to the user card.
			if($new_status->paid){
				//$order = new Order($params['id_order']);
			}
		}
    }
	    /* Convert Beans Reward to cartRule and add to cart */
    protected function generateAndApplyCouponRule($reward)
    {   
        $cartRule = new CartRule();
		$cartRuleId = $cartRule->getIdByCode($reward['coupon_code']);
		if(!$cartRuleId>0) {
			$cartRule->code = $reward['coupon_code'];
			$cartRule->id_customer = $this->context->customer->id;
			$cartRule->reduction_amount = $reward['voucher_amount'];
			$cartRule->reduction_percent = 0;
			$cartRule->quantity = 1;
			$cartRule->quantity_per_user = 1;
			$cartRule->cart_rule_restriction = 1;
			$cartRule->reduction_tax = 1;
			$cartRule->partial_use = 0;
			$cartRule->active = 1;
			$cartRule->minimum_amount = 0;
			$cartRule->priority = 100;
			$cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 day', time()));
			$cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day', time()));
			$cartRule->description = 'TokoPini Generated Apply Voucher Code: '. $reward['couponcode'];
			$cartRule->name = array();
			foreach (Language::getLanguages(true) as $lang) {
				$cartRule->name[(int)$lang['id_lang']] = 'TokoPini Generated Apply Voucher Code: '. $reward['coupon_code'];
			}
			$cartRule->add();
			$this->context->cart->addCartRule($cartRule->id);
			$this->context->cart->update();
			return true;
		}
		return false;
    }
	protected function removeCouponRule($couponcode)
	{
        $cartRule = new CartRule();
		$cartRuleId = $cartRule->getIdByCode($couponcode);
		if(!$cartRuleId>0) {
			$this->context->cart->removeCartRule($cartRuleId);
			return true;
		}
		/*
		$rows = $this->context->cart->getCartRules();
        foreach($rows as &$row){
			// Remove the Rule For now. It will be re-added when they re-add the coupon code at a later time.
			if(substr($row['obj']->code, 0, 3) == 'TKP') {
				$this->context->cart->removeCartRule($row['id_cart_rule']);
				return true;
			}
		}
		*/
		return false;
	}
    protected function checkCoupon($couponcode){
		$couponCodeData = array();
		$couponCodeData['code'] = $couponcode;
		$couponCodeData['token'] = $this->getApiToken();
		return $this->apiPostRequest($this->getApiVerifyUrl(), $couponCodeData);
    }
	protected function useCoupon($couponcode)
	{
		$couponCodeData = array();
		$couponCodeData['code'] = $couponcode;
		$couponCodeData['token'] = $this->getApiToken();
		return $this->apiPostRequest($this->getApiUseUrl(), $couponCodeData);
	}
	protected function apiPostRequest($url, $postData){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
		$data = json_decode($data);
		return array('data'=>$data, 'http_status'=>$http_status);
    }
    protected function getApiVerifyUrl(){
        return Configuration::get('TOKOPINI_APPLYVOUCHER_APIVERIFYURL');
    }
    protected function getApiUseUrl(){
        return Configuration::get('TOKOPINI_APPLYVOUCHER_APIUSEURL');
    }
    protected function getApiToken(){
        return Configuration::get('TOKOPINI_APPLYVOUCHER_APIKEY');
    }
}
