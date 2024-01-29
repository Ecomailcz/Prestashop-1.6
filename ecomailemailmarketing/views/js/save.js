/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
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
