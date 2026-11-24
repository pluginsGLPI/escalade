/**
 * -------------------------------------------------------------------------
 * Escalade plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Escalade.
 *
 * Escalade is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Escalade is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Escalade. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2015-2023 by Escalade plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/escalade
 * -------------------------------------------------------------------------
 */

$(document).ready(function() {
   $(document).off('change.escaladeTechGroup').on('change.escaladeTechGroup', '[data-escalade-hide-technician-group]', function() {
      hide_technician_group(this.value);
   });
   const remove_group_checkbox = document.querySelector('[name="remove_group"][type="checkbox"]');
   const remove_group_elements = document.querySelectorAll('[for*="show-history"], [for*="solve-return-group"]');
   remove_group_checkbox.addEventListener('click', function() {
      const selectValue = document.querySelector('[name="use_assign_user_group"]').value;
      hide_show_hidden_fields(selectValue);
      remove_group_elements.forEach(function(element) {
         const parentDiv = element.closest('.form-field');
         if (parentDiv.classList.contains('d-none')) {
            parentDiv.classList.remove('d-none');
         } else {
            parentDiv.classList.add('d-none');
         }
      });
      const remove_group_border = document.querySelectorAll('[name*="remove_group_border"]');
      remove_group_border.forEach(function(element) {
         if (element.classList.contains('d-none')) {
            element.classList.remove('d-none');
         } else {
            element.classList.add('d-none');
         }
      });
   });
});

function hide_technician_group(val) {
   const technician_group_elements = document.querySelectorAll('[for*="use-assign-user-group-modification"], [for*="use-assign-user-group-creation"]');
   hide_show_hidden_fields(val);
   technician_group_elements.forEach(function(element) {
      const parentDiv = element.closest('.form-field');
      if (parentDiv.classList.contains('d-none')) {
         parentDiv.classList.remove('d-none');
      } else {
         if (val == 0) {
            parentDiv.classList.add('d-none');
         }
      }
   });
   const tech_group_border = document.querySelectorAll('[name*="tech_group_border"]');
   tech_group_border.forEach(function(element) {
      if (element.classList.contains('d-none')) {
         element.classList.remove('d-none');
      } else {
         if (val == 0) {
            element.classList.add('d-none');
         }
      }
   });
}
function hide_show_hidden_fields(val) {
   const remove_group_checkbox = document.querySelector('[name="remove_group"][type="checkbox"]');
   const hidden_elements = document.querySelectorAll('[for*="splitfield"]');
   if ((!remove_group_checkbox.checked && val == 0) || (remove_group_checkbox.checked && val != 0)) {
      hidden_elements.forEach(function(element) {
         const parentDiv = element.closest('.form-field');
         parentDiv.classList.add('d-none');
      });
   } else {
      hidden_elements.forEach(function(element) {
         const parentDiv = element.closest('.form-field');
         parentDiv.classList.remove('d-none');
      });
   }
}
