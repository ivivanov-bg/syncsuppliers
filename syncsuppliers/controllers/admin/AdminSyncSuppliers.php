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
	}
	
	public function initContent()
	{
	    $this->content = $this->renderView();
	    parent::initContent();
	}

	public function renderView()
	{
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
	    //print_r($xml_data);
	    $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
	    $id_shop = (int)$this->context->shop->id;
	    
	    $feedUrl = Tools::getValue('feed_url');
	    
	    // F:\\prj\\smehurko.com\\prestashop_modules\\syncsuppliers\\testData\\dataExport_partial.xml
	    
        if (Tools::isSubmit('syncMoni')) {
            $strFileName = addslashes($feedUrl);
            $xml_data_file = fopen($strFileName, 'r');
            $xml_data_str = fread($xml_data_file, filesize($strFileName));
            fclose($xml_data_file);
            
            $logger = new FileLogger(1); //0 == debug level, logDebug() won’t work without this.
            $logger->setFilename(_PS_ROOT_DIR_.'/log/product_sync_moni_' . date('Y-m-d') . '.log');
            
            $xml_data = simplexml_load_string($xml_data_str);
            
            $logger->logInfo('Start processing: '. count($xml_data) . ' products in XML.');
            
            $products = SupplierCore::getProducts(2, $id_lang, 1000000, 1);

            $I = 0;
            $U = 0;
            $D = 0;
            $K = 0;
            
            foreach ($xml_data->product as $supplier_product) {
                
                // Get suplier properties 
                $supplier_reference = (string) $supplier_product->internet_code;
                
                if(!$supplier_reference) {
                    $supplier_reference = (string) $supplier_product->code;
                }
                
                if(!$supplier_reference) {
                    $supplier_reference = (string) $supplier_product->barcode;
                }
                
                if(!$supplier_reference) {
                    $logger->logWarning('Missing internet_code for: ' . $supplier_product->name);
                    continue;
                } else {
                    $supplier_reference = trim($supplier_reference);
                }
                
                $supplier_price = (double) $supplier_product->retail_price_with_vat;
                $supplier_availability = (string) $supplier_product->availability;
                $supplier_name = (string) $supplier_product->name;
                
                $supplier_quantity = 99;
                switch($supplier_availability) {
                    case 'In stock':
                        $supplier_quantity = 99;
                        break;
                    case 'Low Quantity':
                        $supplier_quantity = 5;
                        break;
                    case 'Out of stock':
                        $supplier_quantity = 0;
                        break;
                    default:
                        $supplier_quantity = 3;
                        break;
                }
                // ========================
                
                $key = 'sr_'.$supplier_reference;
                
                if (isset($products[$key])) {
                    $current_product = $products[$key];
                } else {
                    $current_product = null;
                }
                
                $logger->logDebug('Processing ' . $key . ' ' . ($current_product != null ? 'E' : 'N'));
                
                if ($current_product != null) { 
                    // Update existing product
                    // Price, Availability
                    
                    $isUpdated = false;
                    
                    // update price and availability
                    $p = new Product($current_product['id_product'], true, $id_lang);

                    $current_price = (double) $p->price;
                    $current_quantity = (int) $p->quantity;

                    if ($current_price != $supplier_price) {
                        $logger->logDebug('Updated price from ' . $current_price . ' to ' . $supplier_price);
                        $p->price = $supplier_price;
                        $isUpdated = true;
                    }
                    
                    if($current_quantity != $supplier_quantity) {
                        $logger->logDebug('Updated quantity from ' . $current_quantity . ' to ' . $supplier_quantity);
                        StockAvailable::setQuantity($p->id, null, $supplier_availability);
                        $isUpdated = true;
                    }
                    
                    AdminSyncSuppliersController::getManufacturerIdByName($supplier_product->brand, $logger);
                    
                    if ($isUpdated) {
                        $U++;
                        $p->save();
                    } else {
                        $K++;
                    }
                } else { // Add new product
                    $p = new Product(0, false);

                    $p->id_supplier         = 2;
                    $p->supplier_reference  = $supplier_reference;
                    $p->price               = $supplier_price;
                    $p->quantity            = $supplier_quantity;
                    $p->id_category_default = 2;
                    $p->id_manufacturer     = AdminSyncSuppliersController::getManufacturerIdByName($supplier_product->brand, $logger);
                    
                    $p->name[2]             = $supplier_name;
                    $p->link_rewrite[2]     = Tools::str2url($supplier_name);
                    $p->description[2]      = (string) $supplier_product->description;
                    
                    foreach ($supplier_product->characteristics->characteristic as $chr) {
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
                                $p->description[1] = $chr;
                                break;
                            case 233: // НАИМЕНОВАНИЕ НА АНГЛИЙСКИ
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
                    
                    $p->save();
                    
                    $p->updateCategories(array(2));
                    $p->addSupplierReference(2, 0, $supplier_reference);
                    $p->setAdvancedStockManagement(1);
                    StockAvailable::setQuantity($p->id, null, $supplier_quantity);
                    
                    
                    $has_cover = false;
                    foreach ($supplier_product->image_link as $image_link) {
                        $url = 'http://' . $image_link;
                        
                        $image = new Image();
                        $image->id_product = $p->id;
                        $image->position = ImageCore::getHighestPosition($p->id) + 1;
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
                    
                    
                    $I++;
                    $p->save();
                }
            }
            
            $this->submit_result = 'syncMoni:I=' . $I . ':U=' . $U . ':D=' . $D . ':K=' . $K;
            $this->submit_result_data = '';
            $logger->logInfo($this->submit_result);
        }

        if (Tools::isSubmit('syncMouseToys')) {
            $f = fopen('php://output', 'w');
            fputcsv($f, array("syncMouseToys", $feedUrl), ";", '"');
            fclose($f);
            die();
        }

	}
	
	public static function getManufacturerIdByName($name, $logger)
	{
	    $name = trim($name);
	    if (strlen($name) == 0) {
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
