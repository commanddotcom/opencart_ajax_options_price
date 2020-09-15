<?php /* Copyright 2020, Yevgen Shevchenko - https://github.com/commanddotcom/opencart_ajax_options_price

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation  and/or other materials provided with the distribution.
3. Neither the names of the copyright holders nor the names of any
   contributors may be used to endorse or promote products derived from this
   software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE. */

class ControllerExtensionModuleAjaxoptionprice extends Controller {

	private $error = array();
	private $version = '2.1';

	public function index() {
		$this->load->language('extension/module/ajaxoptionprice');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_ajaxoptionprice', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/ajaxoptionprice', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/ajaxoptionprice', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_ajaxoptionprice_status'])) {
			$data['module_ajaxoptionprice_status'] = $this->request->post['module_ajaxoptionprice_status'];
		} else {
			$data['module_ajaxoptionprice_status'] = $this->config->get('module_ajaxoptionprice_status');
		}

		$data['license'] = $this->language->get('license');
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ajaxoptionprice', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ajaxoptionprice')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install(){

		# Add event like: 
		$this->load->model('setting/event');
		$this->model_setting_event->addEvent('ajaxoptionprice', 'catalog/view/product/product/after', 'extension/module/ajaxoptionprice/edit_product_page');
		$this->model_setting_event->addEvent('ajaxoptionprice_hideFromDesignLayoutForm', 'admin/view/design/layout_form/before', 'extension/module/ajaxoptionprice/hideFromDesignLayoutForm');
		
		# Enable by default
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_ajaxoptionprice', ['module_ajaxoptionprice_status' => 1]);

	}
	public function uninstall() {

		# Remove event like: 
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('ajaxoptionprice');
		$this->model_setting_event->deleteEventByCode('ajaxoptionprice_hideFromDesignLayoutForm');

	}
	
	/**
	 * Hide module from the list on Layouts page in admin panel
	 * https://forum.opencart.com/viewtopic.php?p=799279#p799279
	 */
	public function hideFromDesignLayoutForm(&$route, &$data, &$template=null) {
		foreach ($data['extensions'] as $key=>$extension) {
			if ($extension['code'] == 'ajaxoptionprice') {
				unset($data['extensions'][$key]);
			}
		}
		return null;
	}

}

