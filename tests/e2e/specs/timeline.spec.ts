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
import { getWorkerEntityId } from '../../../../../tests/e2e/utils/WorkerEntities';
import { TicketPage } from '../../../../../tests/e2e/pages/TicketPage';

test('Can escalate a ticket using the timeline "Escalate" button', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);

    const group_name = `Escalade group ${crypto.randomUUID()}`;
    const group_id = await api.createItem('Group', {
        name: group_name,
        entities_id: getWorkerEntityId(),
        is_assign: 1,
    });

    const ticket_id = await api.createItem('Ticket', {
        name: `Escalade ticket ${crypto.randomUUID()}`,
        content: 'Content',
        entities_id: getWorkerEntityId(),
    });

    const ticket = new TicketPage(page);
    await ticket.goto(ticket_id);

    const escalate_action = page.getByRole('link', { name: 'Escalate', exact: true });
    await page.getByRole('button', { name: 'View other actions' }).click();
    await escalate_action.click();

    const escalation_panel = page.getByTestId('new-action-escalation-block');
    await expect(escalation_panel).toBeVisible();

    await ticket.initRichTextByLabel('Comment', escalation_panel);
    const comment_field = ticket.getRichTextByLabel('Comment', escalation_panel);
    await comment_field.fill('Escalating this ticket');

    await comment_field.press(' ');
    await comment_field.press('Backspace');

    const group_dropdown = escalation_panel.getByLabel('Group').locator('+ span').getByRole('combobox');
    await ticket.doSetDropdownValue(group_dropdown, group_name);

    await escalation_panel.getByRole('button', { name: 'Add', exact: true }).click();

    await expect(async () => {
        const assigned_groups = await api.getSubItems('Ticket', ticket_id, 'Group_Ticket');
        const is_escalated = assigned_groups.some(
            (group: { groups_id: number, type: number }) => group.groups_id === group_id && group.type === 2 /* CommonITILActor::ASSIGN */
        );
        expect(is_escalated).toBe(true);
    }).toPass();
});
