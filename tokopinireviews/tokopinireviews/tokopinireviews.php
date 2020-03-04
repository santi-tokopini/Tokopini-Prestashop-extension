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

class tokopinireviews extends Module
{

	protected $configOptions = array(
		'TOKOPINI_CONFIG_APIURL',
		'TOKOPINI_CONFIG_APIKEY',
		'TOKOPINI_CONFIG_DISPLAY_PRODUCT_WIDGET',
		'TOKOPINI_CONFIG_WIDGET_COLOR'
	);

    protected $jsonReviewContent;
    protected $jsonReviewTabContent;
	
	public function __construct()
	{
		$this->name = 'tokopinireviews';
		$this->tab = 'others';
		$this->version = '1.0.1';
		$this->author = 'TokoPini Integrations';
		$this->module_key = '7f216a86f806f343c2888324f3504ecf';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		$this->displayName = $this->l('TokoPini - Display Reviews');
		$this->description = $this->l('Automatically Displays Product reviews on your website');
		$this->confirmUninstall = $this->l('No TokoPini Integration? :(');

		if (($this->isConfigVarNad('TOKOPINI_CONFIG_APIKEY') + ($this->isConfigVarNad('TOKOPINI_CONFIG_APIURL'))) < 3){
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
		/*
		if (!function_exists('curl_init')){
			$this->setError($this->l('TokoPini requires cURL.'));
        }

		if (Shop::isFeatureActive()){
			Shop::setContext(Shop::CONTEXT_ALL);
        }

		if (!parent::install() || !$this->registerHook('displayFooter')){
			return false;
        } else {
			return parent::install() && $this->registerHook('displayFooter');
        }
		*/
		return parent::install() && $this->registerHook('displayHeader') && $this->registerHook('displayFooter');
	}

	public function uninstall()
	{
		foreach($this->configOptions as $configOption){
			Configuration::deleteByName($configOption);
		}

		return parent::uninstall();
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
						$this->yesNoOption('TOKOPINI_CONFIG_DISPLAY_PRODUCT_WIDGET', 'Display the product reviews widget:'),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API URL'),
								'name' => 'TOKOPINI_CONFIG_APIURL',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->l('Your TokoPini API Key'),
								'name' => 'TOKOPINI_CONFIG_APIKEY',
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

	protected function get_web_page( $url )
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER         => false,    // don't return headers
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_USERAGENT      => "spider", // who am i
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
		);
	
		$ch      = curl_init( $url );
		curl_setopt_array( $ch, $options );
		$content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$header  = curl_getinfo( $ch );
		curl_close( $ch );
	
		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['content'] = $content;
		return $header;
	}
	
	public function hookDisplayHeader()
	{
	   if (Configuration::get('TOKOPINI_CONFIG_DISPLAY_PRODUCT_WIDGET') == '1')
	   {
		   $this->context->controller->addJS($this->_path.'/views/js/dfwidget.js');
		   $this->context->controller->addCSS($this->_path.'views/css/dfwidget.css');
	   }
	}
	public function hookDisplayFooter($params){
		if (Configuration::get('TOKOPINI_CONFIG_DISPLAY_PRODUCT_WIDGET') == '1')
		{
			$url = $this->getApiUrl().'?token='.$this->getApiToken();
			if($result=$this->get_web_page($url)) {
				$json = $result['content'];
				$reviewContent = json_decode($json, true);
				
				//Get Rating For Review Tab
				$reviewAvg = $reviewContent['data']['averagePunctuation']['average'];
				$puntuation = $reviewAvg / 2;
				if($puntuation !="") {
					$starrating = ($puntuation / 10) * 2 * 100;
				} else {
					$starrating = "0";
				}
				//Get Rating For Review Tab

				foreach ($reviewContent['data']['feedbacks'] as $_review) {
					$jsonEncodeReviews[]['tokopiniReview'] = $_review;
				}
				$json = json_encode($jsonEncodeReviews);
				if($json!="") { $this->jsonReviewContent = $json; } else { $this->jsonReviewContent = "No Reviews Found."; }
				if($reviewAvg!="") { $this->jsonReviewTabContent = "REVIEWS " . $reviewAvg . " / 10"; } else { $this->jsonReviewTabContent = "No Reviews Found."; }

			} else {
				$this->jsonReviewContent = "No Reviews Found.";
				$this->jsonReviewTabContent = "No Reviews Found.";
			}

			$this->jsonReviewTabContent = $this->jsonReviewTabContent . "&nbsp;<div class='star-ratings-sprite-vertical'><span style='height:".$starrating."%' class='star-ratings-sprite-rating-vertical'></span></div>";

			$data = $this->jsonReviewContent;
			$tabContent = $this->jsonReviewTabContent;
			$smarty = $this->context->smarty;
			$smarty->assign(array('data' => $data));
			$smarty->assign(array('tabContent' => $tabContent));
			return $this->display(__FILE__, 'views/templates/front/widget.tpl');
		}
	}
	
    protected function getApiUrl(){
        return Configuration::get('TOKOPINI_CONFIG_APIURL');
    }
    protected function getApiToken(){
        return Configuration::get('TOKOPINI_CONFIG_APIKEY');
    }
}
