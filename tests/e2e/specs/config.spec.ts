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

import { test, expect } from '../fixtures/escalade_fixture';
import { Profiles } from '../../../../../tests/e2e/utils/Profiles';

test('Displays the Escalade configuration form', async ({ page, profile }) => {
    await profile.set(Profiles.SuperAdmin);

    const response_superadmin = await page.goto('/plugins/escalade/front/config.form.php');
    expect(response_superadmin?.status()).toBe(200);

    await expect(page.getByRole('navigation', { name: 'Breadcrumbs' }).getByRole('link', { name: 'Escalade' })).toBeVisible();

    await profile.set(Profiles.SelfService);

    const response_selfservice = await page.goto('/plugins/escalade/front/config.form.php');
    expect(response_selfservice?.status()).toBe(403);
});

test('Displays all Escalade configuration fields', async ({ page, profile }) => {
    await profile.set(Profiles.SuperAdmin);
    await page.goto('/plugins/escalade/front/config.form.php');

    const checkbox_fields = [
        'Remove old assign group on new group assign',
        'Show group assign history visually',
        'Assign ticket to initial group on solve ticket',
        'Assign the technical group on ticket category change',
        'Enable filtering on the groups assignment',
        'Remove technician(s) on escalation',
        'Assign the technical manager on ticket category change',
        'Remove requester(s) on escalation',
        'Assign me as observer by default',
        'Escalation history in tasks',
        'Escalation task is private ?',
        'Clone tickets',
        'Close cloned tickets at the same time',
    ];
    for (const label of checkbox_fields) {
        await expect(page.getByRole('checkbox', { name: label })).toBeVisible();
    }

    const dropdown_labels = [
        "Use the technician's group",
        'Ticket status after an escalation',
    ];
    for (const label of dropdown_labels) {
        await expect(page.getByText(label)).toBeVisible();
    }

    await expect(page.getByRole('checkbox', { name: 'at creation time' })).toBeHidden();
    await expect(page.getByRole('checkbox', { name: 'at modification time' })).toBeHidden();

    const delete_button_fields = [
        'remove_delete_requester_user_btn',
        'remove_delete_watcher_user_btn',
        'remove_delete_assign_user_btn',
        'remove_delete_requester_group_btn',
        'remove_delete_watcher_group_btn',
        'remove_delete_assign_group_btn',
        'remove_delete_assign_supplier_btn',
    ];
    for (const name of delete_button_fields) {
        await expect(page.locator(`input[type="checkbox"][name="${name}"]`)).toBeVisible();
    }

    await expect(page.getByRole('button', { name: 'Save' })).toBeVisible();
});

test('Toggles technician group fields based on "Use the technician\'s group" value', async ({ page, profile }) => {
    await profile.set(Profiles.SuperAdmin);
    await page.goto('/plugins/escalade/front/config.form.php');

    const dropdown = page.getByTestId('form-field-use_assign_user_group').getByRole('combobox');
    const creation_checkbox = page.getByRole('checkbox', { name: 'at creation time' });
    const modification_checkbox = page.getByRole('checkbox', { name: 'at modification time' });

    await expect(creation_checkbox).toBeHidden();
    await expect(modification_checkbox).toBeHidden();

    for (const value of ['First', 'Last']) {
        await dropdown.click();
        await page.getByRole('listbox').getByRole('option', { name: value, exact: true }).click();

        await expect(creation_checkbox).toBeVisible();
        await expect(modification_checkbox).toBeVisible();
    }

    await dropdown.click();
    await page.getByRole('listbox').getByRole('option', { name: 'No', exact: true }).click();

    await expect(creation_checkbox).toBeHidden();
    await expect(modification_checkbox).toBeHidden();
});

test('Toggles group fields based on "Remove old assign group on new group assign" value', async ({ page, profile }) => {
    await profile.set(Profiles.SuperAdmin);
    await page.goto('/plugins/escalade/front/config.form.php');

    const remove_group_checkbox = page.getByRole('checkbox', { name: 'Remove old assign group on new group assign' });
    const show_history_checkbox = page.getByRole('checkbox', { name: 'Show group assign history visually' });
    const solve_return_group_checkbox = page.getByRole('checkbox', { name: 'Assign ticket to initial group on solve ticket' });

    // Default value (checked): the group fields are visible.
    await expect(remove_group_checkbox).toBeChecked();
    await expect(show_history_checkbox).toBeVisible();
    await expect(solve_return_group_checkbox).toBeVisible();

    await remove_group_checkbox.click();
    await expect(show_history_checkbox).toBeHidden();
    await expect(solve_return_group_checkbox).toBeHidden();

    await remove_group_checkbox.click();
    await expect(show_history_checkbox).toBeVisible();
    await expect(solve_return_group_checkbox).toBeVisible();
});
