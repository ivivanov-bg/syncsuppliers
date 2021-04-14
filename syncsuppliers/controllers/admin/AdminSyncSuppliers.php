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


	
	private $submit_result;

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
		    $html .= '<div class="defaultForm">';
		    $html .= '<div>Action: ' . $resultParts[0] . '</div>';
		    $html .= '<div>Result: ' . $resultParts[1] . ', ' . $resultParts[2] . ', ' . $resultParts[3] . ', ' . $resultParts[4] . '</div><br>';
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

        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = $submitAction;
        $helper->currentIndex = self::$currentIndex;
        $helper->token = Tools::getAdminTokenLite('AdminSyncSuppliers');

        $fields['form']['legend']['title'] = $this->l($title);
        return $helper->generateForm(array($fields));
    }

	public function postProcess()
	{
	    $feed_url = Tools::getValue('feed_url');
	    
	    // F:\\prj\smehurko.com\\prestashop_modules\\syncsuppliers\\testData\\dataExport_partial.xml
	    
        if (Tools::isSubmit('syncMoni')) {
            $strFileName = addslashes($feed_url);
            $xml_data_file = fopen($strFileName, 'r');
            $xml_data_str = fread($xml_data_file, filesize($strFileName));
            fclose($xml_data_file);
            
            $xml_data = simplexml_load_string($xml_data_str);
            
            //print_r($xml_data);
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            
            $products = SupplierCore::getProducts(2, $id_lang, 1000, 1);
            
//             print_r($products);
//             print('<br><br><br>' );

            $I = 0;
            $U = 0;
            $D = 0;
            $K = 0;
            
            foreach ($xml_data-> {'product'} as $supplier_product) {
                
                // Get suplier properties 
                $supplierReference = (string) $supplier_product->{ 'internet_code' };
                $supplier_price = (double) $supplier_product->{ 'retail_price_with_vat' };
                $supplier_availability = (string) $supplier_product->{ 'availability' };
                
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
                
                $key = 'sr_'.$supplierReference;
                
                $current_product = $products[$key];
                
                if ($current_product != null) {
                    $isUpdated = false;
                    
                    // update price and availability
                    $p = new Product($current_product['id_product'], true, $id_lang);

                    $current_price = (double) $p->price;
                    $current_quantity = (int) $p->quantity;

                    if ($current_price != $supplier_price) {
                        $p->price = $supplier_price;
                        $isUpdated = true;
                    }
                    
                    if($supplier_quantity != $current_quantity) {
                        $p->quantity = $supplier_quantity;
                    }

                    if ($isUpdated) {
                        $U++;
                        $p->save();
                    } else {
                        $K++;
                    }
                } else {
                    $p = new Product(0, false);
                    
                    
                    $p->id_supplier = 2;
                    $p->supplier_reference = $supplierReference;
                    $p->price = $supplier_price;
                    $p->quantity = $supplier_quantity;
                    
                    $p->name = array (
                        $id_lang => (string) $supplier_product->{ 'name' },
                    );
                    $p->link_rewrite = array (
                        $id_lang => Tools::str2url($p->name)
                    );
                    
                    $p->description = (string) $supplier_product->{ 'description' };
                    $p->id_category_default = 2;
                    
                    $p->save();
                    
                    $p->updateCategories(array(2));
                    $p->addSupplierReference(2, 0, $supplierReference);
                    $p->setAdvancedStockManagement(1);
                    
                    $I++;
                    $p->save();
                }
            }
            
            $this->submit_result = 'syncMoni:I=' . $I . ':U=' . $U . ':D=' . $D . ':K=' . $K;
        }

        if (Tools::isSubmit('syncMouseToys')) {
            $f = fopen('php://output', 'w');
            fputcsv($f, array("syncMouseToys", $feed_url), ";", '"');
            fclose($f);
            die();
        }

	}

	public function initContent()
	{
		$this->content = $this->renderView();
		parent::initContent();
	}
}
