<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

//not executed in self-service interface & right verification
if ($_SESSION['glpiactiveprofile']['interface'] == "central"
   && (Session::haveRight("ticket", CREATE)
      || Session::haveRight("ticket", UPDATE))) {

   $locale_group_view = __('Group View');

   $JS = <<<JAVASCRIPT

   var doOnCentralPage = function() {
      //intercept ajax load of group tab
      $(document).ajaxComplete(function(event, jqxhr, option) {
         if (option.url == "../plugins/escalade/ajax/central.php") {
            return;
         }

         if (option.url.indexOf('common.tabs.php') > 0) {
            //delay the execution (ajax requestcomplete event fired before dom loading)
            setTimeout(function () {

               var suffix = "";
               var selector = "#ui-tabs-2 .tab_cadre_central .top:last" +
                  ", .alltab:contains('$locale_group_view') + .tab_cadre_central .top:last";

               // get central list for plugin and insert in group tab
               $(selector).each(function(){
                  if (this.innerHTML.indexOf('escalade_block') < 0) {
                     if (option.url.indexOf("-1") > 0) { //option.params
                        suffix = "_all";
                     }

                     //prepare a span element to load new elements
                     $(this).prepend("<span id='escalade_block" + suffix + "'>test</span>");

                     //ajax request
                     $("#escalade_block" + suffix).load('../plugins/escalade/ajax/central.php');
                  }
               });
            }, 300);
         }
      });
   }

   $(document).ready(function() {
      $(".ui-tabs-panel:visible").ready(function() {
         doOnCentralPage();
      })

      $("#tabspanel + div.ui-tabs").on("tabsload", function() {
         setTimeout(function() {
            doOnCentralPage();
         }, 300);
      });
   });

JAVASCRIPT;
   echo $JS;
}
