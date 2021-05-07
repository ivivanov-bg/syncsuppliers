<?php
/**
 * Sync Products with Suppliers
 * @category sync
 *
 * @author Ivaylo Ivanov
 * @copyright Ivaylo Ivanov
 * @version 1.0
 */

class AdminSyncSuppliersController extends ModuleAdminController {


	
	private $submit_result = null;
	private $submit_result_data = null;

	public function __construct()
	{
		$this->bootstrap = true;

		$this->meta_title = $this->l('Sync Suppliers');
		parent::__construct();
		if (! $this->module->active) {
			Tools::redirectAdmin($this->context->link->getAdminLink('AdminHome'));
		}
		
		$this->submit_result = null;
		$this->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
	}
	
	public function initContent()
	{
		$this->content = $this->renderView();
		parent::initContent();
	}

	public function renderView()
	{
		$products = Supplier::getProductsForSync(1, $this->id_lang, 1000000, 1);
		
		$logger = new FileLoggerCore(0);
		$logger->setFilename(_PS_ROOT_DIR_.'/log/product_debug.log');
		
		
		
		foreach ($products as $current_product) {
			$p = new Product($current_product['id_product'], true, $this->id_lang);
			
			$p_attrs = $p->getProductAttributesIds($p->id);
			
			foreach($p_attrs as $key => $p_attr) {
			   
			  
				$productDetails = Supplier::getProductInformationsBySupplier(1, $p->id, $p_attr['id_product_attribute']);
			
			
			
				$logger->logDebug($productDetails);
			}
		}
		
			
		
		return $this->renderConfigurationForm();
	}

	public function renderConfigurationForm()
	{
		$form_fields = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Sync with Moni'),
					'icon'  => 'icon-cogs'
				),
				'input'  => array(
					array(
						'type'  => 'text',
						'label' => $this->l('Feed URL'),
						'name'  => 'feed_url',
						'value' => '',
						'desc'  => $this->l('The URL for fetching the data feed for supplier ')
					)
				),
				'submit' => array('title' => $this->l('Sync'))
			),
		);

		$html = '';
		
		if ($this->submit_result != null) {
			$resultParts = explode(':', $this->submit_result);
			
// 		    print_r($resultParts);
			$html .= '<div>';
			$html .= '<div>Action: ' . $resultParts[0] . '</div>';
			$html .= '<div>Result: ' . $resultParts[1] . ', ' . $resultParts[2] . ', ' . $resultParts[3] . ', ' . $resultParts[4] . '</div><br>';
			$html .= '</div>';
		}
		if ($this->submit_result_data != null) {
			
			$html .= '<div>';
			$html .=  $this->submit_result_data;
			$html .= '</div>';
		}
		
		// Moni: http://195.162.72.127:83/MW/MoniTradeExport2.aspx
		$html .= $this->getForm('Sync with Moni', $form_fields, 'syncMoni');
		$html .= $this->getForm('Sync with Mouse Toys', $form_fields, 'syncMouseToys');

		return $html;
	}

	public function getForm($title, $fields, $submitAction) {
		$helper = new HelperForm();
		$helper->show_toolbar = false;

		$helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->identifier = $this->identifier;
		$helper->submit_action = $submitAction;
		$helper->currentIndex = self::$currentIndex;
		$helper->token = Tools::getAdminTokenLite('AdminSyncSuppliers');
		$helper->tpl_vars = array(
			'fields_value' => array('feed_url' => '')
		);

		$fields['form']['legend']['title'] = $this->l($title);
		return $helper->generateForm(array($fields));
	}

	public function postProcess()
	{	    
		$xml_feed_url = Tools::getValue('feed_url');
		$id_supplier = Tools::getValue('supplier');
		$log_lvl = Tools::getValue('log_lvl');
		
		if (Tools::isSubmit('syncMoni')) {
			$id_supplier   = 2;
			
		} else if (Tools::isSubmit('syncMouseToys')) {
			$id_supplier   = 4;
		}
		
		switch($id_supplier) {
			case 2: // Moni
				$xpath_products = '//product';
				$log_filename  = _PS_ROOT_DIR_.'/log/product_sync_moni.log';
				break;
			case 4:
				$xpath_products = '//df:Product';
				$log_filename  = _PS_ROOT_DIR_.'/log/product_sync_mouseToys.log';
				break;
			case 5:
				$xpath_products = '//product';
				$log_filename  = _PS_ROOT_DIR_.'/log/product_sync_brightToys.log';
				break;
			default:
				return;
		}
		
		$this->syncProducts($id_supplier, $xml_feed_url, $xpath_products, $log_filename, $log_lvl);
	}
	
	protected function syncProducts($id_supplier, $xml_feed_url, $xpath_products, $log_filename, $log_debug_lvl = 1) {
		
		$logger = new FileLogger($log_debug_lvl);
		$logger->setFilename($log_filename);
		
		$logger->logInfo('Starting Sync for id_supplier = '.$id_supplier.', src = "'. $xml_feed_url .'"');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $xml_feed_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		
		$xml_data_str = curl_exec($ch);
		
		curl_close($ch); 
		
		$xml_data = $this->prepareXmlObject($xml_data_str, $logger);
		
		$sync_products = $xml_data->xpath($xpath_products);
		$sync_products_cnt = count($sync_products);
		
		$logger->logInfo('Products to sync: '. $sync_products_cnt . ' for selector "' . $xpath_products . '"');
		
		$products = Supplier::getProductsForSync($id_supplier, $this->id_lang, 1000000, 1);
		$products_cnt = count($products);
		
		$logger->logInfo("Products in database: ". $products_cnt . " for supplier " . $id_supplier . " with 'supplier_reference'");
		
		$I = 0; $U = 0; $D = 0; $K = 0;
		foreach ($sync_products as $key => $sync_prd) {
			
			$sync_prd_fields = $this->getFieldsBySupplier($id_supplier, $sync_prd, $logger);
			
			if (!$sync_prd_fields['status']) {
				$logger->logWarning('Skipping ' . ($key + 1) . ' of ' . $sync_products_cnt . ': ' . $sync_prd_fields['status_msg']);
				continue;
			} else if (!$sync_prd_fields) {
				$logger->logWarning('Skipping ' . ($key + 1) . ' of ' . $sync_products_cnt . ': Product fields are empty');
				continue;
			}
			
			if (isset($products[$sync_prd_fields['sync_prd_ref_key']])) {
				$current_product = $products[$sync_prd_fields['sync_prd_ref_key']];
			} else {
				$current_product = null;
			}
			
			if ($current_product == null && !$sync_prd_fields['sync_prd_insert_allowed']) {
				$logger->logDebug('Skipping ' . ($key + 1) . ' of ' . $sync_products_cnt . ($current_product != null ? ': ' : ': (NEW) ') . $sync_prd_fields['sync_prd_ref'] . ' ' . $sync_prd_fields['sync_prd_name_bg']);
				continue;
			}

			$sync_prd_result = $this->processSyncProduct($id_supplier, $sync_prd_fields, $current_product, $logger, $key, $sync_products_cnt);

			if (!$sync_prd_result) {
				$logger->logError('Failed syncing product ' . $sync_prd_fields['sync_prd_ref']. ' ' . $sync_prd_fields['sync_prd_name']);
			} else {
				switch ($sync_prd_result) {
					case 'I': // Inserted
						$I++;
						break;
					case 'U': // Updated
						$U++;
						break;
					case 'K': // Kept
						$K++;
						break;
					case 'D': // Deleted
						$D++;
						break;
					default:
						$logger->logWarning('Unknown sync result "' . $sync_prd_result .'"');
						break;
				}
			}
		}
		
		$this->submit_result = $id_supplier . ':I=' . $I . ':U=' . $U . ':D=' . $D . ':K=' . $K;
		$this->submit_result_data = '';
		$logger->logInfo($this->submit_result);
	}
	
	protected function prepareXmlObject($xml_data_str, $logger) {
		$xml_data = simplexml_load_string($xml_data_str);
		
		if (!$xml_data) {
			$logger->logError('Failed loading file "'. $xml_data_str . '"');
		}
		
		$logger->logDebug('Found '. count($xml_data) . ' Nodes in XML.');
		
		foreach ($xml_data->getNamespaces(true) as $key => $value) {
			if (!$key) {
				$key = 'df';
			}
			
			$logger->logInfo('Found XML schema: "'. $key . '=' . $value .'"');
			$xml_data->registerXPathNamespace($key, $value);
		}
		
		return $xml_data;
	}
	
	protected function processSyncProduct($id_supplier, $sync_prd_fields, $current_product, $logger, $key, $sync_products_cnt) {

		$result = false;
		
		$id_shop = (int)$this->context->shop->id;
		
		$logStatus = "";

		if ($current_product != null) {
			// Update existing product
			// Price, Availability

			$isUpdated = false;

			// update price and availability
			$p = new Product($current_product['id_product'], true, $this->id_lang);
			$p_attr = $current_product['pid_product_attribute'];

			$prd_price = (double) $p->price;
			$prd_quantity = (int) StockAvailable::getQuantityAvailableByProduct($p->id, $p_attr);
			
			if ($prd_price != $sync_prd_fields['sync_prd_price']) {
				$logStatus .= 'Price ' . $prd_price . ' -> ' . $sync_prd_fields['sync_prd_price'] . ', ';
				$p->price = $sync_prd_fields['sync_prd_price'];
				$isUpdated = true;
			}

			if($prd_quantity != $sync_prd_fields['sync_prd_quantity']) {				
				$logStatus .= 'Quantity ' . $prd_quantity . ' -> ' . $sync_prd_fields['sync_prd_quantity'] . ', ';
				StockAvailable::setQuantity($p->id, $p_attr, $sync_prd_fields['sync_prd_quantity']);
				$isUpdated = true;
			}
			
			if ($isUpdated) {
				$result = 'U';
				$p->save();
			} else {
				$result = 'K';
			}
		} else {
			$p = new Product(0, false);
			
			$p->id_supplier         = $id_supplier;
			$p->supplier_reference  = $sync_prd_fields['sync_prd_ref'];
			$p->price               = $sync_prd_fields['sync_prd_price'];
			$p->quantity            = $sync_prd_fields['sync_prd_quantity'];
			$p->id_category_default = 2;
			$p->id_manufacturer     = AdminSyncSuppliersController::getManufacturerIdByName($sync_prd_fields['sync_prd_manufacturer_name'], $logger);
			
			$p->name[2]             = $sync_prd_fields['sync_prd_name_bg'];
			$p->link_rewrite[2]     = Tools::str2url($sync_prd_fields['sync_prd_name_bg']);
			$p->description[2]      = $sync_prd_fields['sync_prd_description_bg'];
			$p->description[1]      = $sync_prd_fields['sync_prd_description_en'];
			
			$p->save();
			
			$p->updateCategories(array(2));
			$p->addSupplierReference($id_supplier, 0, $sync_prd_fields['sync_prd_ref']);
			$p->setAdvancedStockManagement(1);
			StockAvailable::setQuantity($p->id, null, $sync_prd_fields['sync_prd_quantity']);
			
			
			$has_cover = false;
			
			foreach ($sync_prd_fields['sync_prd_image_links'] as $image_link) {
				$url = (string) $image_link;
				
				if (substr( $url, 0, 4 ) !== "http") {
					$url = 'http://'.$url;
				}
				
				$image = new Image();
				$image->id_product = $p->id;
				$image->position = Image::getHighestPosition($p->id) + 1;
				if ($has_cover) {
					$image->cover = false;
				} else {
					$has_cover = true;
					$image->cover = true;
				}
				
				if (($image->validateFields(false, true)) === true &&
					($image->validateFieldsLang(false, true)) === true && $image->add())
				{
					$image->associateTo($id_shop);
					if (!AdminImportController::copyImg($p->id, $image->id, $url, 'products', false))
					{
						$image->delete();
					}
				}
			}
			
			$p->save();
			$result = 'I';
		}
		
		if ($result == 'K') {
			$logger->logDebug('Processed ' . ($key + 1) . ' of ' . $sync_products_cnt . ': [' . $result . '] ' . ($current_product != null ? '('. $current_product['id_product'] . ') ' : '(NEW) ')
				. $sync_prd_fields['sync_prd_ref'] . ' ' . $sync_prd_fields['sync_prd_name_bg'] . ' (' . $logStatus . ')');
		} else {
			$logger->logInfo('Processed ' . ($key + 1) . ' of ' . $sync_products_cnt . ': [' . $result . '] ' . ($current_product != null ? '('. $current_product['id_product'] . ') ' : '(NEW) ')
				. $sync_prd_fields['sync_prd_ref'] . ' ' . $sync_prd_fields['sync_prd_name_bg'] . ' (' . $logStatus . ')');
		}

		return $result;
	}
	
	protected function getFieldsBySupplier($id_supplier, $sync_prd_xml_node, $logger) {
		$result = null;
		
		switch($id_supplier) {
			case 2: // Moni
				$result = $this->getFieldsForMoni($sync_prd_xml_node, $logger);
				break;
			case 4: // Mouse Toys
				$result =  $this->getFieldsForMouseToys($sync_prd_xml_node, $logger);
				break;
			case 5: // Bright Toys
				$result =  $this->getFieldsForBrightToys($sync_prd_xml_node, $logger);
				break;
			default:
				$logger->logWarning('Unknown supplier id "'. $id_supplier . '"');
				$result =  false;
		}
		
		return $result;
	}
	
	protected function getFieldsForBrightToys($sync_prd_xml_node, $logger) {
		$result = array();
		
		$result['sync_prd_name_bg']           = (string) $sync_prd_xml_node->Name;
		$result['sync_prd_ref']               = (string) $sync_prd_xml_node->Reference;
		
		if(!$result['sync_prd_ref']) {
			$result['sync_prd_ref']           = (string) $sync_prd_xml_node->EAN;
		}

//=======================================================================================================
		if(!$result['sync_prd_ref']) {
			$result['status'] = false;
			$result['status_msg'] = 'Missing ProductCode for: ' . $result['sync_prd_name_bg'];
			
			return $result;
		}
//=======================================================================================================

		$result['sync_prd_ref']               = (string) trim($result['sync_prd_ref']);
		$result['sync_prd_ref_key']           = (string) 'sr_'.$result['sync_prd_ref'];
		
		$result['sync_prd_price']             = (double) $sync_prd_xml_node->RRP;
		$result['sync_prd_quantity']          = (string) $sync_prd_xml_node->Quantity;
		$result['sync_prd_manufacturer_name'] = (string) $sync_prd_xml_node->Brand;
		$result['sync_prd_description_bg']    = null;
		$result['sync_prd_description_en']    = null;
		$result['sync_prd_image_links']       = array();
		
		$result['sync_prd_insert_allowed']           = false;
		
		//AdminSyncSuppliersController::getManufacturerIdByName($result['sync_prd_manufacturer_name'], $logger);
		//return false;
		
		$result['status'] = true;
		
		return $result;
	}
	
	protected function getFieldsForMouseToys($sync_prd_xml_node, $logger) {
		$result = array();
		
		$result['sync_prd_name_bg']           = (string) $sync_prd_xml_node->ProductName->BG;
		$result['sync_prd_ref']               = (string) $sync_prd_xml_node->ProductCode;
		
		if(!$result['sync_prd_ref']) {
			$result['sync_prd_ref']           = (string) $sync_prd_xml_node->ProductID;
		}

//=======================================================================================================
		if(!$result['sync_prd_ref']) {
			$result['status'] = false;
			$result['status_msg'] = 'Missing ProductCode for: ' . $result['sync_prd_name_bg'];
			
			return $result;
		}
//=======================================================================================================

		$result['sync_prd_ref']               = (string) trim($result['sync_prd_ref']);
		$result['sync_prd_ref_key']           = (string) 'sr_'.$result['sync_prd_ref'];
		$result['sync_prd_price']             = (double) $sync_prd_xml_node->ProductPrice;
		$result['sync_prd_availability']      = (string) $sync_prd_xml_node->AvailabilityLabel;
		
		
		if ((int) $sync_prd_xml_node->ProductQuantity > 10) {
		    $result['sync_prd_quantity'] = (int) $sync_prd_xml_node->ProductQuantity;
		} else {
		    $result['sync_prd_quantity'] = 3;
		}
		
		switch($result['sync_prd_availability']) {
			case 'В НАЛИЧНОСТ':
			case 'НА СКЛАД':
				break;
			default:
				$logger->logWarning('Unknown sync_prd_availability "'. $result['sync_prd_availability'] . '"');
				break;
		}
		
		$result['sync_prd_manufacturer_name'] = (string) $sync_prd_xml_node->BrandName->BG;
		$result['sync_prd_description_bg']    = (string) $sync_prd_xml_node->ProductDetailedDescription;
		$result['sync_prd_description_en']    = null;
		
		$result['sync_prd_image_links'] = array();
		foreach ($sync_prd_xml_node->ProductImages->ProductImage as $ProductImage) {
			array_push($result['sync_prd_image_links'], $ProductImage->ImagePath);
		}
		
		$result['sync_prd_insert_allowed']           = false;
		
		//AdminSyncSuppliersController::getManufacturerIdByName($result['sync_prd_manufacturer_name'], $logger);
		//return false;
		
		$result['status'] = true;
		
		return $result;
	}
	
	/**
	 * 
	 * @param unknown $sync_prd_xml_node
	 * @param unknown $logger
	 * @return boolean|string[]|number[]|NULL[]|boolean[]
	 *   array with the field values for the Moni suplier
	 *   -3 in case the internet_code is missing
	 */
	protected function getFieldsForMoni($sync_prd_xml_node, $logger) {
		$result = array();
		
		$result['sync_prd_name_bg']           = (string) $sync_prd_xml_node->name;
		$result['sync_prd_ref']               = (string) $sync_prd_xml_node->internet_code;
		
		if(!$result['sync_prd_ref']) {
			$result['sync_prd_ref']           = (string) $sync_prd_xml_node->code;
		}
		
		if(!$result['sync_prd_ref']) {
			$result['sync_prd_ref']           = (string) $sync_prd_xml_node->barcode;
		}

//=======================================================================================================		
		if(!$result['sync_prd_ref']) {
			
		    $result['status'] = false;
			$result['status_msg'] = 'Missing internet_code for: ' . $result['sync_prd_name_bg'];
			
			return $result;
		} else {
			$result['sync_prd_ref']           = trim($result['sync_prd_ref']);
		}
//=======================================================================================================

		$result['sync_prd_ref_key']           = (string) 'sr_'.$result['sync_prd_ref'];
		$result['sync_prd_price']             = (double) $sync_prd_xml_node->retail_price_with_vat;
		$result['sync_prd_availability']      = (string) $sync_prd_xml_node->availability;
		
		switch($result['sync_prd_availability']) {
			case 'In stock':
				$result['sync_prd_quantity'] = 99;
				break;
			case 'Low Quantity':
				$result['sync_prd_quantity'] = 3;
				break;
			case 'Out of stock':
				$result['sync_prd_quantity'] = 0;
				break;
			default:
				$result['sync_prd_quantity'] = 5;
				$logger->logWarning('Unknown sync_prd_availability "'. $result['sync_prd_availability'] . '"');
				break;
		}
		
		$result['sync_prd_manufacturer_name'] = (string) $sync_prd_xml_node->brand;
		$result['sync_prd_description_bg']    = (string) $sync_prd_xml_node->description;
		$result['sync_prd_image_links']       =          $sync_prd_xml_node->image_link;
		$result['sync_prd_description_en']    = null;
		
		foreach ($sync_prd_xml_node->characteristics->characteristic as $chr) {
			switch($chr['externalCode']) {
				case 202: // Опаковка В/cm
					break;
				case 203: // Опаковка Ш/cm
					break;
				case 210: // Опаковка Д/cm
					break;
				case 213: // Продукт Д/cm
					break;
				case 216: // Продукт Ш/cm
					break;
				case 225: // NW /kgs
					break;
				case 231: // Описание на английски
					$result['sync_prd_description_en'] = (string) $chr;
					break;
				case 233: // НАИМЕНОВАНИЕ НА АНГЛИЙСКИ
					$result['sync_prd_name_en'] = (string) $chr;
					//$p->name[1] = $chr;
					//$p->link_rewrite[1] = Tools::str2url($chr);
					break;
				case 235: // Размер на продукта
					break;
				case 236: // Размер на кашона
					break;
				case 251: // ИНСТРУКЦИИ
					break;
				case 252: // Продукт В/cm
					break;
				case 360: // Видео материал
					break;
				default:
					$logger->logWarning('Unknown characteristic => case ' . $chr['externalCode'] . ': // ' . $chr['Name']);
					break;
			}
		}
		
		//	            $result['sync_prd_description_en']    = (string) ($sync_prd_xml_node->xpath('characteristics/characteristic[@externalCode="231"]')[0]);
		//	            $result['sync_prd_name_en']           = (string) ($sync_prd_xml_node->xpath('characteristics/characteristic[@externalCode=233]')[0]);
		
		$result['sync_prd_insert_allowed']           = false;
		
		//AdminSyncSuppliersController::getManufacturerIdByName($result['sync_prd_manufacturer_name'], $logger);
		//return false;
		
		$result['status'] = true;
		
		return $result;
	}
	
	public static function getManufacturerIdByName($name, $logger)
	{
		$name = trim($name);
		if (strlen($name) == 0) {
			$logger->logError('Manufacturer name not provided ' . $name);
			return null;
		}
		
		$name_stripped = null;
		switch(strtoupper($name)) {
			case 'MONI':
			case 'MONI TOYS':
			case 'MONI GARDEN':
				$name_stripped = 'MONI';
				break;
			case 'UCAR':
			case 'UCAR TOYS':
			case 'UCARTOYS':
				$name_stripped = 'UCAR';
				break;
			case 'MOLTO/POLESIE':
			case 'MAMMOET/POLESIE':
				$name_stripped = 'POLESIE';
				break;
			default:
				$name_stripped = $name;
		}
		
		$result = Db::getInstance()->getRow('
			SELECT `id_manufacturer`
			FROM `'._DB_PREFIX_.'manufacturer`
			WHERE `name` = \''.pSQL($name_stripped).'\'
			   OR `name` = \''.pSQL($name_stripped).' TOYS\'
		');
		
		if (isset($result['id_manufacturer'])) {
			return (int)$result['id_manufacturer'];
		}
		
		$logger->logWarning('Missing manufacturer ' . $name);
		
		return null;
	}
}
