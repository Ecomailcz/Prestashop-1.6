{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{* Ecomail starts *}
<script type="text/javascript">
  (function(p,l,o,w,i,n,g){
    if(!p[i]){
      p.GlobalSnowplowNamespace=p.GlobalSnowplowNamespace||[];
      p.GlobalSnowplowNamespace.push(i);
      p[i]=function(){
        (p[i].q=p[i].q||[]).push(arguments)
      };
      p[i].q=p[i].q||[];
      n=l.createElement(o);
      g=l.getElementsByTagName(o)[0];
      n.async=1;
      n.src=w;
      g.parentNode.insertBefore(n,g)
    }
  }(window,document,"script","//d70shl7vidtft.cloudfront.net/ecmtr-2.4.2.js","ecotrack"));
  window.ecotrack('newTracker', 'cf', 'd2dpiwfhf3tz0r.cloudfront.net', { /* Initialise a tracker */  appId: '{$ECOMAIL_APP_ID|escape:'javascript':'UTF-8'}'});

  if ('{$EMAIL|escape:'javascript':'UTF-8'}' !== 'empty'){
    window.ecotrack('setUserId', '{$EMAIL|escape:'javascript':'UTF-8'}');
  } else {
    window.ecotrack('setUserIdFromLocation', 'ecmid');
  }
  window.ecotrack('trackPageView');
</script>
{* Ecomail stops *}
{* Ecomail form start *}
<script>
  (function (w,d,s,o,f,js,fjs) {
    w['ecm-widget']=o;w[o] = w[o] || function () { (w[o].q = w[o].q || []).push(arguments) };
    js = d.createElement(s), fjs = d.getElementsByTagName(s)[0];
    js.id = '{$ECOMAIL_FORM_ID|escape:'javascript':'UTF-8'}'; js.dataset.a = '{$ECOMAIL_FORM_ACCOUNT|escape:'javascript':'UTF-8'}'; js.src = f; js.async = 1; fjs.parentNode.insertBefore(js, fjs);
  }(window, document, 'script', 'ecmwidget', 'https://d70shl7vidtft.cloudfront.net/widget.js'));
</script>
{* Ecomail form end *}
