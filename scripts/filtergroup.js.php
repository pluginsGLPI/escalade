<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

$JS = <<<JAVASCRIPT
if (location.pathname.indexOf('ticket.form.php') == 0) {
   exit;
}

// only in ticket form

var url = '{$CFG_GLPI['root_doc']}/plugins/escalade/ajax/group_values.php';
var tickets_id = getUrlParameter('id');

function getValueOfSelectName(str) {
   console.log("getValueOfSelectName("+str+")"); //DEBUG
   console.log("select[name*='" + str + "']");
   if ($("select[name*='" + str + "']").length > 0) {
      return $("select[name*='" + str + "']")[0].attributes.getNamedItem('id').value;
   } else {
      console.log("Non trouvé");
      return false;
   }
}

//replace groups select by ajax response
function remplaceGroupSelectByAjaxResponse(id, options) {
   //var el = $("#" + id);
   
   var el = document.getElementById(id);
   el.outerHTML = el.outerHTML.replace(el.innerHTML + '',options + '');
}

$(document).ready(function() {
   console.log("READY");

   if (tickets_id == undefined) {
      // -----------------------
      // ---- Create Ticket ---- 
      // -----------------------

     //perform an ajax request to get the new options for the group list
      $.ajax({
         url: url,
         data: {'ticket_id': 0},
         success: function(response, opts) {

            setTimeout(function() {
               
               /*
               var selectvalue = $("*[name='_groups_id_assign']")[0].value;
               var id = $("*[name='_groups_id_assign']")[0].id; //"dropdown__groups_id_assign1186369991"
               
               $('#dropdown__groups_id_assign1186369991').select2(({ajax: {
                  url: url}})
               );
               */
               
               /*
               var assign_select_dom_id = getValueOfSelectName("_groups_id_assign");
               remplaceGroupSelectByAjaxResponse(assign_select_dom_id, response.responseText);
               */
            }, 300);
         },
         fail: function(response, opts) {
            console.log('server-side failure with status code ' + response.status);
         }
      });

   } else {
      // -----------------------
      // ---- Update Ticket ----
      // -----------------------

      Ext.Ajax.on('requestcomplete', function(conn, response, option) {
      	//trigger the filter only on actor(group) selected
         if (option.url.indexOf('dropdownItilActors.php') > 0 
         	&& option.params.indexOf("group") > 0
               && option.params.indexOf("assign") > 0
            ) {

 				//delay the execution (ajax requestcomplete event fired before dom loading)
            setTimeout(function() {

            	$.ajax({
	               url: url,
	               data: {'ticket_id': tickets_id},
	               success: function(response, opts) {

	                  var assign_select_dom_id = getValueOfSelectName("_itil_assign[groups_id]");

	                  var nb_id = assign_select_dom_id.replace("dropdown__itil_assign[groups_id]", "");

	                  //remove search input (only in GLPI AJAX mode)
	                  $("#search_"+nb_id).remove();

	                  remplaceGroupSelectByAjaxResponse(assign_select_dom_id, response.responseText);
	               }
	            });

            }, 300);
			}

      }, this);

      
   }
});
JAVASCRIPT;
echo $JS;
