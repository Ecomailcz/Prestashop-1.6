{*
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel container">
	<div class="panel-heading"><i class="icon-info"></i> {l s='Ecomail info' mod='ecomailemailmarketing'}</div>
	<div class="row" style="display:flex;justify-content:center;align-items:center;">
		<div class="col-md-6" style="display:flex;justify-content:center;align-items:center;padding:24px;flex-direction:column;">
			<p>
				<strong>{l s='Ecomail email marketing helps you grow your business.' mod='ecomailemailmarketing'}</strong>
			</p>
			<p>
				{l s='The intuitive app helps you create an ' mod='ecomailemailmarketing'} <strong>{l s='effective email marketing strategy ' mod='ecomailemailmarketing'}</strong>{l s='while saving precious time. Advanced features like email automation and content personalization will boost your sales immediately.' mod='ecomailemailmarketing'}
			</p>
			<br>
			<p>
				<strong>{l s='Installation guide' mod='ecomailemailmarketing'}</strong>
			</p>
			<p>
				{l s='Please follow this guide to connect your Prestashop store with Ecomail: ' mod='ecomailemailmarketing'}<a href="{l s='https://support.ecomail.app/en/articles/6983944-integrate-with-prestashop' mod='ecomailemailmarketing'}" target="_blank">{l s='Integrate with Prestashop' mod='ecomailemailmarketing'}</a>
			</p>
			<p>
				{l s='If you have any questions or need a helping hand, reach out to our support at: support@ecomail.app' mod='ecomailemailmarketing'}
			</p>

		</div>
		<div class="col-md-6" style="display:flex;justify-content:center;align-items:center;">
			<iframe class="tag-es" width="400" height="250" src="https://www.youtube.com/embed/2QI4Gt3LnnU" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
		</div>
	</div>
</div>

<div class="panel container">
	<div class="panel-heading"><i class="icon-search"></i> {l s='General configuration' mod='ecomailemailmarketing'}</div>
	<div class="row">
		<div class="col-md-12">
			<div class="form-group">
				<label for="api_key">API KEY</label>
				<input type="text" class="form-control" id="api_key" aria-describedby="apiHelp"
					   placeholder="{l s='Enter the API KEY' mod='ecomailemailmarketing'}">
				<small id="apiHelp"
					   class="form-text text-muted tag-es">{l s='If you dont have an account,' mod='ecomailemailmarketing'} <a href="{l s='https://www.ecomail.app/' mod='ecomailemailmarketing'}" target="_blank">{l s='create it here' mod='ecomailemailmarketing'}</a></small>
			</div>
		</div>
		<div class="col-md-12">
			<button type="button" class="btn btn-primary pull-right" id="submit_key">{l s='Save' mod='ecomailemailmarketing'}</button>
		</div>
	</div>
</div>

<script>
  const message_error_ajax = "{l s='Connection error' mod='ecomailemailmarketing'}";
  const message_success_ajax = "{l s='Registered Successfully' mod='ecomailemailmarketing'}";
  const submit_message_success = {
    api: "{l s='Linked api key' mod='ecomailemailmarketing'}",
  }
  const api_key_input = "{$api_key_input|escape:'javascript':'UTF-8'}";
</script>