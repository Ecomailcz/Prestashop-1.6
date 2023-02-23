/**
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
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

$(document).ready(function () {
  if (typeof api_key_input === 'undefined' || !api_key_input) {
    $(".valid_api").hide();
  } else {
    $("#alert_info").hide();
  }

  $("#submit_key").click(function () {
    validApiKey();
  });

  $("#api_key").focus(function () {
    $(this).removeClass("input-error");
  });

  function validApiKey() {
    var api_key = $("#api_key").val();
    if (api_key.length > 0) {
      $("#api_key").removeClass("input-error");
      $("#submit_key").prop("disabled", true);
      $("#submit_key").addClass("button-loading");
      $.ajax({
        type: "POST",
        url: ajax_link,
        data: {ajax: true, action: 'saveApi', apikey: api_key},
        dataType: "json",
        success: function (response) {
          if (response.error){
            $.growl.error({
              title: "Error",
              message: response.error,
            });
            $("#submit_key").prop("disabled", false);
            $("#submit_key").removeClass("button-loading");
          } else {
            $.growl.notice({
              title: message_success_ajax,
              message: submit_message_success.api,
            });
            $("#alert_info").hide();
            $(".valid_api").show();
            $("#submit_key").prop("disabled", false);
            $("#submit_key").removeClass("button-loading");
            location.reload();
          }
        },
      });
    } else {
      $("#api_key").addClass("input-error");
    }
  }
})
