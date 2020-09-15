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

	public $options_container 	= '#product';
	public $special_price_container = 'special-price-tag';
	public $price_container		= 'price-tag';
	public $use_cache		= true;
	public $fade_out_time		= 150;
	public $fade_in_time		= 50;
	
	private $error = array(); 
	
	public function index() { 

		if (!$this->config->get('module_ajaxoptionprice_status')) {
			die();
		}

		$json = array();
		$update_cache = false;
		$options_price = 0;
		
		$product_id = intval($this->request->get['pid'] ?? 0);
		$options = $this->request->post['option'] ?? [];

		if (!$product_id) {
			die('Invalid input params');
		}

		$this->language->load('product/product');
		$this->load->model('catalog/product');

		if ($options && is_array($options)) {
			$options_hash = serialize($options); // for cache name
		} else {
			$options_hash = '';
		}

		$cache_id = 'ajax_options_'. md5($product_id . $options_hash .$this->session->data['currency'] . $this->session->data['language']);

		if (!$this->use_cache || (!$json = $this->cache->get($cache_id))) {

			$product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store p2s LEFT JOIN " . DB_PREFIX . "product p ON (p2s.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p2s.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p.status = '1'");

			// Prepare data
			if ($product_query->num_rows) {

				$update_cache = true;

				$product_price = $product_query->row['price'];

				if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
					$product_price = $this->tax->calculate($product_price, $product_query->row['tax_class_id'], $this->config->get('config_tax'));
				} else {
					$product_price = false;
				}
				
				$product_special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

				if ($product_special_query->num_rows) {
					$product_special_price = $product_special_query->row['price'];
				} else {
					$product_special_price = false;
				}

				// If some options are selected
				if ($options) {
					foreach ($this->model_catalog_product->getProductOptions($product_id) as $option) { 
						foreach ($option['product_option_value'] as $option_value) {
							$product_options_posted = $this->request->post['option'][$option['product_option_id']] ?? 0;
							
							if ($product_options_posted && (($product_options_posted == $option_value['product_option_value_id']) || (is_array($product_options_posted) && in_array($option_value['product_option_value_id'], $product_options_posted))) ) {
									if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
										if ($option_value['price']) {
											if ($option_value['price_prefix'] === '+') {
												$options_price += (float)$option_value['price'];
											} else {
												$options_price -= (float)$option_value['price'];
											}
										}
									}
							}
						}
					}
				}

				if ($product_price) {
					$json['new_price']['price'] = $this->currency->format(
						$this->tax->calculate(
							($product_price + $options_price), 
							$product_query->row['tax_class_id'], 
							$this->config->get('config_tax')
						), 
						$this->config->get('config_currency')
					);
				} else {
					$json['new_price']['price'] = false;
				}

				if ($product_special_price) {
					$json['new_price']['special_price'] = $this->currency->format(
						$this->tax->calculate(
							($product_special_price + $options_price), 
							$product_query->row['tax_class_id'], 
							$this->config->get('config_tax')
						), 
						$this->config->get('config_currency')
					);
				} else {
					$json['new_price']['special_price'] = false;
				}

				$json['success'] = true;

			} else {
				die('Failed to get product');
			}

		}

		if ($update_cache && $this->use_cache) {
			$this->cache->set($cache_id, $json);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	function js() {

		header('Content-Type: application/javascript'); 

		if (!$this->config->get('module_ajaxoptionprice_status')) {
			die();
		}

		$product_id = intval($this->request->get['pid']);

		if (!$product_id) {
			die('Invalid input params');
		}

		$js = <<<HTML

			var price_with_options_ajax_call = function() {
				$.ajax({
					type: 'post',
					url: 'index.php?route=extension/module/ajaxoptionprice/index&pid=$product_id',
					data: $('{$this->options_container} input[type=\'text\'], {$this->options_container} input[type=\'hidden\'], {$this->options_container} input[type=\'radio\']:checked, {$this->options_container} input[type=\'checkbox\']:checked, {$this->options_container} select, {$this->options_container} textarea'),
					dataType: 'json',
					success: function(json) {
						if (json.success) {
							if ($('{$this->special_price_container}').length > 0 && json.new_price.special_price) {
								animation_on_change_price_with_options('{$this->special_price_container}', json.new_price.special_price);
							}
							if ($('{$this->price_container}').length > 0 && json.new_price.price) {
								animation_on_change_price_with_options('{$this->price_container}', json.new_price.price);
							}
						}
					},
					error: function(error) {
						console.log(error);
					}
				});
			}

			var animation_on_change_price_with_options = function(selector, value) {
				$(selector).fadeOut({$this->fade_out_time}, function() {
					$(this).html(value).fadeIn({$this->fade_in_time});
				});
			}

				
			$('{$this->options_container}').on('change', 'input[type=\'text\'], input[type=\'hidden\'], input[type=\'radio\'], input[type=\'checkbox\'], select, textarea', price_with_options_ajax_call);

HTML;

		echo $js;
		exit;
	}

	public function edit_product_page(&$route = '', &$data = array(), &$output = '') {

		if (!$this->config->get('module_ajaxoptionprice_status')) {
			return null;
		}

		$product_id = intval($this->request->get['product_id'] ?? 0);

		if ($product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);
			if ($product_info) {
				$pattern = '/'.preg_quote($data['price']).'/';
				$replacement = '<price-tag>'. addcslashes($data['price'], '\\$') .'</price-tag>';
				$output = preg_replace($pattern, $replacement, $output, 1);

				$pattern = '/'.preg_quote($data['special']).'/';
				$replacement = '<special-price-tag>'. addcslashes($data['special'], '\\$') .'</special-price-tag>';
				$output = preg_replace($pattern, $replacement, $output, 1);

				$js = '<script type="text/javascript" src="index.php?route=extension/module/ajaxoptionprice/js&pid='. $product_id .'"></script>';
				$output = str_replace('</body>', $js.'</body>', $output);
			}
		}
	}

}