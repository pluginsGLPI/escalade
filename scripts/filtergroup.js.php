<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

$JS = <<<JAVASCRIPT
$(document).ready(function() {
   // only in ticket form
   if (location.pathname.indexOf('ticket.form.php') > 0) {

      var tickets_id = getUrlParameter('id');

      if (tickets_id == undefined) {
         // -----------------------
         // ---- Create Ticket ---- 
         // -----------------------

        //perform an ajax request to get the new options for the group list
         $.ajax({
            url: '{$CFG_GLPI['root_doc']}/plugins/escalade/ajax/group_values.php',
            data: {
               'ticket_id': 0
            },
            success: function(response, opts) {
               var options = response.responseText;

               setTimeout(function() {  
                  //Tested with name=type : --Emmanuel
	               var assign_select_dom_id = $("select[name=_groups_id_assign]")[0].attributes.getNamedItem('id').value; 

	               //replace groups select by AJAX response
	               var el = document.getElementById(assign_select_dom_id);
	               el.outerHTML = el.outerHTML.replace(el.innerHTML + '', options + '');
	            }, 200);
            },
            fail: function(response, opts) {
               console.log('server-side failure with status code ' + response.status);
            }
         });

      } else {
         // -----------------------
         // ---- Update Ticket ----
         // -----------------------

         //get id of itilactor select
         //selector tested  --Emmanuel
         var actor_select_dom_id = $("select[name*='_itil_assign[_type]']")[0].attributes.getNamedItem('id').value;


         Ext.Ajax.on('requestcomplete', function(conn, response, option) {
         	//trigger the filter only on actor(group) selected
            if (option.url.indexOf('dropdownItilActors.php') > 0 
            	&& (
                  option.params.indexOf("group") > 0
                  && option.params.indexOf("assign") > 0
               )) {

	 				//delay the execution (ajax requestcomplete event fired before dom loading)
               setTimeout( function() {

               	$.ajax({
		               url: '{$CFG_GLPI['root_doc']}/plugins/escalade/ajax/group_values.php',
		               data: {
		                  'ticket_id': tickets_id
		               },
		               success: function(response, opts) {
		                  var options = response.responseText;

		                  var assign_select_dom_id = $("select[name*='_itil_assign[groups_id]']")[0].attributes.getNamedItem('id').value;

		                  var nb_id = assign_select_dom_id.replace("dropdown__itil_assign[groups_id]", "");

		                  //remove search input (only in GLPI AJAX mode)
		                  if ($("#search_"+nb_id).length != 0) {
		                  	$("#search_"+nb_id).remove();
		                  }

		                  //replace groups select by ajax response
               			var el = document.getElementById(assign_select_dom_id);
			               el.outerHTML = el.outerHTML.replace(el.innerHTML + '',options + '');
		               }
		            });

               }, 300);
				}

         }, this);

         
      }
   }
});
JAVASCRIPT;
echo $JS;
