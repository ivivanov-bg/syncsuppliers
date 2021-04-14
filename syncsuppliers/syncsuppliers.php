<?php
/**
 * Sync Products with Suppliers
 * @category sync
 *
 * @author Ivaylo Ivanov
 * @copyright Ivaylo Ivanov
 * @version 1.0
 */

class SyncSuppliers extends Module
{
	public function __construct()
	{
		$this->name = 'syncsuppliers';
		$this->tab = 'administration';
		$this->version = '1.0.0';
		$this->displayName = 'Sync with Suppliers';
		$this->author = 'Ivaylo Ivanov';
		$this->description = $this->l('A module to sync all products with predefined suppliers.');

		parent::__construct();
	}

	public function install()
	{
		$this->installController('AdminSyncSuppliers', 'Sync with Suppliers');
		return parent::install();

	}

	private function installController($controllerName, $name) {
		$tab_admin_order_id = Tab::getIdFromClassName('AdminTools');
        $tab = new Tab();
        $tab->class_name = $controllerName;
        $tab->id_parent = $tab_admin_order_id;
        $tab->module = $this->name;
        $languages = Language::getLanguages(false);
        foreach($languages as $lang){
            $tab->name[$lang['id_lang']] = $name;
        }
    	$tab->save();
	}

	public function uninstall()
	{
		$this->uninstallController('AdminSyncSuppliers');
		return parent::uninstall();
	}

	public function uninstallController($controllerName) {
		$tab_controller_main_id = TabCore::getIdFromClassName($controllerName);
		$tab_controller_main = new Tab($tab_controller_main_id);
		$tab_controller_main->delete();
	}

}
