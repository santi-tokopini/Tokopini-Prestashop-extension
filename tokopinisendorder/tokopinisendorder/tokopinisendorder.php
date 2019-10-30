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

class tokopinisendorder extends Module
{

	protected $configOptions = array(
		'TOKOPINI_SENDORDER_APIURL',
		'TOKOPINI_SENDORDER_APIKEY',
		'TOKOPINI_ENABLE_SEND_ORDER',
		'TOKOPINI_ENABLE_EXCLUSIONS',
		'TOKOPINI_SENDORDER_CUSTOMER_GROUP_EXCLUSIONS',
		'TOKOPINI_SENDORDER_KEYWORD_EXCLUSIONS'
	);

    protected $jsonReviewContent;
	
	public function __construct()
	{
		$this->name = 'tokopinisendorder';
		$this->tab = 'others';
		$this->version = '1.0.1';
		$this->author = 'TokoPini Integrations';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		$this->displayName = $this->l('TokoPini - Send Order');
		$this->description = $this->l('Sends Order Data to TokoPini');
		$this->confirmUninstall = $this->l('No TokoPini Integration? :(');

		if (($this->isConfigVarNad('TOKOPINI_SENDORDER_APIKEY') + ($this->isConfigVarNad('TOKOPINI_SENDORDER_APIURL'))) < 3){
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
				$this->registerHook('actionValidateOrder');
	}

	public function uninstall()
	{
		foreach($this->configOptions as $configOption){
			Configuration::deleteByName($configOption);
		}

		return parent::uninstall() &&
                $this->unregisterHook('actionValidateOrder');
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
            if ($customer_groups = Tools::getValue('customer_group_exclusions'))
                Configuration::updateValue('TOKOPINI_SENDORDER_CUSTOMER_GROUP_EXCLUSIONS', implode(';', $customer_groups));

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

	protected function showCustomerGroups($title){
		//$custs = Customer::getCustomers();
		$groups = array(array('id_option' => 1, 'name' => 'Visitor'), array('id_option' => 2,	'name' => 'Guest'), array('id_option' => 3,	'name' => 'Customer'));
		return array('type' => 'select',
				'label' => $this->l($title),
				'name' => 'customer_group_exclusions[]',
    			'multiple' => true,
				'options' => array(
					'query' => $groups,
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
						$this->yesNoOption('TOKOPINI_ENABLE_SEND_ORDER', 'Enable Send Order To TokoPini:'),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API URL'),
								'name' => 'TOKOPINI_SENDORDER_APIURL',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API Key'),
								'name' => 'TOKOPINI_SENDORDER_APIKEY',
								'size' => 20,
								'required' => true
						),
						$this->yesNoOption('TOKOPINI_ENABLE_EXCLUSIONS', 'Exclusions (Enable/Disable):'),
						$this->showCustomerGroups('Select Customer Groups To Be Excluded:'),
						array(
								'type' => 'text',
								'label' => $this->l('Keyword Exclusions'),
								'name' => 'TOKOPINI_SENDORDER_KEYWORD_EXCLUSIONS',
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
		$helper->fields_value['customer_group_exclusions[]'] = explode(';',Configuration::get('TOKOPINI_SENDORDER_CUSTOMER_GROUP_EXCLUSIONS'));

		return $helper->generateForm($fields_form);
	}
	
    public function hookactionValidateOrder($params) {
	
	    if (Configuration::get('TOKOPINI_ENABLE_SEND_ORDER') == '1')
	    {
			$order = $params['order'];
			$id_order = intval($order->id);	
			//$cart = $params['cart'];
			$customer = new Customer((int)$order->id_customer);
			$address = new Address((int)$order->id_address_invoice);//id_address_delivery
			
			//EXCLUSIONS
			if (Configuration::get('TOKOPINI_ENABLE_EXCLUSIONS') == '1')
			{
				$customergroupIdsToCheck = explode(";",Configuration::get('TOKOPINI_SENDORDER_CUSTOMER_GROUP_EXCLUSIONS'));
				foreach($customergroupIdsToCheck as $customergroupID) {
					if($customergroupID == $order->id_shop_group) {
						return;
					}
				}
				$exclusionKeywordsToCheck = explode(",",Configuration::get('TOKOPINI_SENDORDER_KEYWORD_EXCLUSIONS'));
				foreach($exclusionKeywordsToCheck as $exclusionKeyword) {
					 if (strpos($customer->email, $exclusionKeyword) !== false) {
						return;
					 }
				}
				
			}
			//EXCLUSIONS
			/*
			$params = '{"cart":{"id":22,"id_shop_group":"1","id_shop":"1","id_address_delivery":"5","id_address_invoice":"5","id_currency":"1","id_customer":"2","id_guest":"2","id_lang":"1","recyclable":"0","gift":"0","gift_message":"","mobile_theme":"0","date_add":"2015-03-17 22:44:16","secure_key":"6d5b5bfa04c0855d060108981a1bb3d7","id_carrier":"1","date_upd":"2015-03-17 22:45:02","checkedTos":false,"pictures":null,"textFields":null,"delivery_option":"a:1:{i:5;s:2:\"1,\";}","allow_seperated_package":"0","id_shop_list":null,"force_id":false},"order":{"id_address_delivery":5,"id_address_invoice":5,"id_shop_group":1,"id_shop":1,"id_cart":22,"id_currency":1,"id_lang":1,"id_customer":2,"id_carrier":1,"current_state":null,"secure_key":"6d5b5bfa04c0855d060108981a1bb3d7","payment":"Virement bancaire","module":"bankwire","conversion_rate":"1.000000","recyclable":"0","gift":0,"gift_message":"","mobile_theme":"0","shipping_number":null,"total_discounts":0,"total_discounts_tax_incl":0,"total_discounts_tax_excl":0,"total_paid":4.8,"total_paid_tax_incl":4.8,"total_paid_tax_excl":4,"total_paid_real":0,"total_products":4,"total_products_wt":4.8,"total_shipping":0,"total_shipping_tax_incl":0,"total_shipping_tax_excl":0,"carrier_tax_rate":20,"total_wrapping":0,"total_wrapping_tax_incl":0,"total_wrapping_tax_excl":0,"invoice_number":null,"delivery_number":null,"invoice_date":"0000-00-00 00:00:00","delivery_date":"0000-00-00 00:00:00","valid":null,"date_add":"2015-03-17 22:45:30","date_upd":"2015-03-17 22:45:30","reference":"SCMTMSHPD","id":"11","id_shop_list":null,"force_id":false,"product_list":[{"id_product_attribute":"0","id_product":"19","cart_quantity":"1","id_shop":"1","name":"Bi\u00e8re Blanche 33cl","is_virtual":"0","description_short":"","available_now":"","available_later":"","id_category_default":"8","id_supplier":"0","id_manufacturer":"0","on_sale":"0","ecotax":"0.000000","additional_shipping_cost":"0.00","available_for_order":"1","price":4,"active":"1","unity":"","unit_price_ratio":"0.000000","quantity_available":"0","width":"0.000000","height":"0.000000","depth":"0.000000","out_of_stock":"2","weight":"0.000000","date_add":"2015-03-16 00:05:06","date_upd":"2015-03-16 00:05:06","quantity":1,"link_rewrite":"biere-blanche-33cl","category":"robes","unique_id":"000000001900000000005","id_address_delivery":"5","wholesale_price":"0.000000","advanced_stock_management":"0","supplier_reference":null,"reduction_type":"0","customization_quantity":null,"id_customization":null,"price_attribute":null,"ecotax_attr":null,"reference":"112","weight_attribute":null,"ean13":"","upc":"","pai_id_image":null,"pai_legend":null,"minimal_quantity":"1","stock_quantity":0,"price_wt":4.8,"total_wt":4.8,"total":4,"reduction_applies":false,"quantity_discount_applies":false,"id_image":"fr-default","allow_oosp":true,"features":[],"rate":20,"tax_name":"TVA FR 20%","warehouse_list":[null],"in_stock":false,"carrier_list":["1","2"]}]},"customer":{"id":2,"id_shop":"1","id_shop_group":"1","secure_key":"6d5b5bfa04c0855d060108981a1bb3d7","note":null,"id_gender":"1","id_default_group":"3","id_lang":"1","lastname":"zeppetella","firstname":"Xavier","birthday":"1975-07-01","email":"xavier@zeppetella.com","newsletter":"0","ip_registration_newsletter":null,"newsletter_date_add":"0000-00-00 00:00:00","optin":"0","website":null,"company":null,"siret":null,"ape":null,"outstanding_allow_amount":"0.000000","show_public_prices":"0","id_risk":"0","max_payment_days":"0","passwd":"89d3fe2f84752c1727dfd7d4ad428062","last_passwd_gen":"2014-09-15 05:12:46","active":"1","is_guest":"0","deleted":"0","date_add":"2014-09-15 11:12:46","date_upd":"2014-09-15 11:12:46","years":null,"days":null,"months":null,"geoloc_id_country":null,"geoloc_id_state":null,"geoloc_postcode":null,"logged":0,"id_guest":null,"groupBox":null,"id_shop_list":null,"force_id":false},"currency":{"id":1,"name":"Euro","iso_code":"EUR","iso_code_num":"978","sign":"\u20ac","blank":"1","conversion_rate":"1.000000","deleted":"0","format":"2","decimals":"1","active":"1","prefix":"","suffix":" \u20ac","id_shop_list":null,"force_id":false},"orderStatus":{"name":"En attente du paiement par virement bancaire","template":"bankwire","send_email":"1","module_name":"bankwire","invoice":"0","color":"#4169E1","unremovable":"1","logable":"0","delivery":"0","hidden":"0","shipped":"0","paid":"0","deleted":"0","id":10,"id_shop_list":null,"force_id":false},"cookie":{},"altern":2}';
			*/
			$invoiceData = array();
			$invoiceData['email'] = $customer->email;
			$invoiceData['phone'] = $address->phone;	
			//$items = $cart->getProducts();	
			$itemsOrdered = "";
			foreach ($order->product_list as $item)
			{
				if($item['id_product'] !="") {
					$itemsOrdered .= $item->name . " " . $item->quantity . ";";
				}
			}
			$invoiceData['description'] = $itemsOrdered;
			$invoiceData['reference'] = (string)$order->getUniqReference();	
			$invoiceData['total_buy'] = (string)$order->total_paid;
			$invoiceData['token'] = $this->getApiToken();
			
			$rules = $order->getCartRules();
			foreach($rules as $rule){
				$cartRule =  new CartRule($rule['id_cart_rule']);
				if(substr($cartRule->code, 0, 3) == 'TKP') {
					$invoiceData['code'] = $cartRule->code;
				}
			}
			
			$returnData = $this->apiPostRequest($this->getApiUrl(), $invoiceData);
			
			if($returnData['http_status'] == '200'){
				/*
				$message = "Success Order: ". print_r($returnData['data'], true);
				PrestaShopLogger::addLog($message, 3);
				$msg = new Message();
				$msg->message = "Success Order: ". print_r($returnData['data'], true);
				$msg->id_order = $id_order;
				$msg->private = 1;
				$msg->add();
				*/
			} else {
				$message = "Error Order: ". print_r($returnData['data'], true);
				PrestaShopLogger::addLog($message, 3);
				/*
				$msg = new Message();
				$msg->message = "Error Order: ". print_r($returnData['data'], true);
				$msg->id_order = $id_order;
				$msg->private = 1;
				$msg->add();
				*/
			}
		}
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
    protected function getApiUrl(){
        return Configuration::get('TOKOPINI_SENDORDER_APIURL');
    }
	
    protected function getApiToken(){
        return Configuration::get('TOKOPINI_SENDORDER_APIKEY');
    }
}
