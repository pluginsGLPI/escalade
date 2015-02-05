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

function redefineDropdown(id, url, tickets_id) {

$('#' + id).select2({
   width: '80%',
   minimumInputLength: 0,
   quietMillis: 100,
   minimumResultsForSearch: 50,
   closeOnSelect: false,
   ajax: {
      url: url,
      dataType: 'json',
      data: function (term, page) {
         return {
            ticket_id: tickets_id,
            itemtype: "Group",
            display_emptychoice: 1,
            displaywith: [],
            emptylabel: "-----",
            condition: "8791f22d6279ae77180198b33b4cc0f0e3b49513",
            used: [],
            toadd: [],
            entity_restrict: 0,
            limit: "50",
            permit_select_parent: 0,
            specific_tags: [],
            searchText: term,
            page_limit: 100, // page size
            page: page, // page number
               };
            },
            results: function (data, page) {
               var more = (data.count >= 100);
               return {results: data.results, more: more};
            }
         },
         initSelection: function (element, callback) {
            var id=$(element).val();
            var defaultid = '0';
            if (id !== '') {
               // No ajax call for first item
               if (id === defaultid) {
                 var data = {id: 0,
                           text: "-----"};
                  callback(data);
               } else {
                  $.ajax(url, {
                  data: {
                     ticket_id: tickets_id,
                     itemtype: "Group",
                     display_emptychoice: true,
                     displaywith: [],
                     emptylabel: "-----",
                     condition: "8791f22d6279ae77180198b33b4cc0f0e3b49513",
                     used: [],
                     toadd: [],
                     entity_restrict: 0,
                     limit: "50",
                     permit_select_parent: false,
                     specific_tags: [],
                     _one_id: id},
               dataType: 'json',
               }).done(function(data) { callback(data); });
            }
         }

      },
      formatResult: function(result, container, query, escapeMarkup) {
         var markup=[];
         window.Select2.util.markMatch(result.text, query.term, markup, escapeMarkup);
         if (result.level) {
            var a='';
            var i=result.level;
            while (i>1) {
               a = a+'&nbsp;&nbsp;&nbsp;';
               i=i-1;
            }
            return a+'&raquo;'+markup.join('');
         }
         return markup.join('');
      }
   });
}

$(document).ready(function() {

   if (tickets_id == undefined) {
      // -----------------------
      // ---- Create Ticket ---- 
      // -----------------------

      setTimeout(function() {
         var assign_select_dom_id = $("*[name='_groups_id_assign']")[0].id;
         redefineDropdown(assign_select_dom_id, url, 0);
      }, 300);

   } else {
      // -----------------------
      // ---- Update Ticket ----
      // -----------------------
      
      $(document).ajaxSend(function( event, jqxhr, settings ) {
         if (settings.url.indexOf("dropdownItilActors.php") > 0 
            && settings.data.indexOf("group") > 0
               && settings.data.indexOf("assign") > 0
            ) {
            //delay the execution (ajax requestcomplete event fired before dom loading)
            setTimeout(function() {
               if ($("*[name='_itil_assign[groups_id]']").length) {
                  var assign_select_dom_id = $("*[name='_itil_assign[groups_id]']")[0].id;
                  redefineDropdown(assign_select_dom_id, url, tickets_id);
               }
            }, 300);
            
         }
      });

   }
});
JAVASCRIPT;
echo $JS;
