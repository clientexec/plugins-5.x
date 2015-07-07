<?php

class SystemCheckGateway extends NE_Model
{

	public function checkBasicNeeds()
	{

		$items = array();

		$item = array();
		$item['name'] = $this->user->lang('Create Products');
		$item['url'] = 'index.php?fuse=admin&view=products&controller=products';
		$item['desc'] = $this->user->lang('Create the products you want to make available to your customers');
		if ($this->has_created_products()) {
			$item['completed'] = true;
		} else {
			$item['completed'] = false;
		}
		$items[] = $item;

		$item['name'] = $this->user->lang('Configure Payment Plugin');
		$item['url'] = 'index.php?fuse=admin&controller=settings&view=plugins&settings=plugins_gateways&type=gateways';
		$item['desc'] = $this->user->lang('Your company name');
		if($this->has_payment_plugin_in_signup()){
			$item['completed'] = true;
		} else {
			$item['completed'] = false;
		}
		$items[] = $item;

		// $item['name'] = 'Enable Routing Rules';
		// $item['url'] = 'index.php?fuse=admin&view=emailrouting&controller=settings&settings=support';
		// $item['desc'] = 'This is the Email address that is visible to users after receiving an invoice payment request via email';
		// $item['completed'] = false;
		// $items[] = $item;

		// $item['name'] = 'Support Email';
		// $item['url'] = 'index.php?fuse=admin&controller=settings&view=viewsettings&settings=support_support';
		// $item['desc'] = 'Email address you want all support questions sent to. This email address is used in template based emails, such as the welcome email template';
		// $items[] = $item;

		return $items;
	}

	public function disable_snapin()
	{
		$sql = "update setting set value = 0 where name ='plugin_systemcheck_Enabled'";
		$this->db->query($sql);

		//let's disable this plugin
        include_once 'modules/admin/models/PluginGateway.php';
        $pg = new PluginGateway($this->user);
        $pg->deleteMappersFromSession("systemcheck");

	}

	private function has_payment_plugin_in_signup()
	{

		$sql = "Select id from setting where name like 'plugin_%_In Signup' and value=1";
		$result = $this->db->query($sql);
		if ($result->getNumRows() > 0) {
			return true;
		} else {
			return false;
		}

	}

	private function has_created_products()
	{

		$sql = "Select max(id) from package";
		$result = $this->db->query($sql);
		list($id) = $result->fetch();
		if ($id == "") {
			return false;
		} else {
			return true;
		}

	}

}