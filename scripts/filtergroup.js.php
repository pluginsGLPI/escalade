<?php
include ("../../../inc/includes.php");

//change mimetype
header("Content-type: application/javascript");

$JS = <<<JAVASCRIPT
Ext.onReady(function() {
   // only in ticket form
   if (location.pathname.indexOf('ticket.form.php') > 0) {

      // separating the GET parameters from the current URL
      var getParams = document.URL.split("?");
      // transforming the GET parameters into a dictionnary
      var url_params = Ext.urlDecode(getParams[getParams.length - 1]);
      // get tickets_id
      var tickets_id = url_params['id'];

      if(tickets_id == undefined) {
         // -----------------------
         // ---- Create Ticket ---- 
         // -----------------------

        //perform an ajax request to get the new options for the group list
         Ext.Ajax.request({
            url: '{$CFG_GLPI['root_doc']}/plugins/escalade/ajax/group_values.php',
            params: {
               'ticket_id': 0
            },
            success: function(response, opts) {
               var options = response.responseText;

               setTimeout(function() {  
	               var assign_select_dom_id = Ext.select("select[name=_groups_id_assign]")
	                     .elements[0].attributes.getNamedItem('id').nodeValue;

	               //replace groups select by ajax response
	               var el = document.getElementById(assign_select_dom_id);
	               el.outerHTML = el.outerHTML.replace(el.innerHTML + '',options + '');
	            }, 200);
            },
            failure: function(response, opts) {
               console.log('server-side failure with status code ' + response.status);
            }
         });

      } else {
         // -----------------------
         // ---- Update Ticket ---- 
         // -----------------------

         //remove # in ticket_id
         tickets_id = parseInt(tickets_id);

         //get id of itilactor select
         var actor_select_dom_id = Ext.select("select[name*=_itil_assign\[_type]")
            .elements[0].attributes.getNamedItem('id').nodeValue;


         Ext.Ajax.on('requestcomplete', function(conn, response, option) {
         	//trigger the filter only on actor(group) selected
            if (option.url.indexOf('dropdownItilActors.php') > 0 
            	&& (
                  option.params.indexOf("group") > 0
                  && option.params.indexOf("assign") > 0
               )) {

	 				//delay the execution (ajax requestcomplete event fired before dom loading)
               setTimeout( function () {

               	Ext.Ajax.request({
		               url: '{$CFG_GLPI['root_doc']}/plugins/escalade/ajax/group_values.php',
		               params: {
		                  'ticket_id': tickets_id
		               },
		               success: function(response, opts) {
		                  var options = response.responseText;

		                  var assign_select_dom_id = Ext.select("select[name*=_itil_assign\[groups_id]")
		                     .elements[0].attributes.getNamedItem('id').nodeValue;

		                  var nb_id = assign_select_dom_id.replace("dropdown__itil_assign[groups_id]", "");

		                  //remove search input (only in glpi ajax mode)
		                  if (Ext.get("search_"+nb_id) != null) {
		                  	Ext.get("search_"+nb_id).remove();		
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
