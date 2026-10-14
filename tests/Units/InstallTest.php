<?php

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

namespace GlpiPlugin\Escalade\Tests\Units;

use CommonITILObject;
use GlpiPlugin\Escalade\Tests\EscaladeTestCase;
use Plugin;
use PluginEscaladeConfig;

final class InstallTest extends EscaladeTestCase
{
    public function testUpdateKeepsTicketLastStatus(): void
    {
        include_once(Plugin::getPhpDir('escalade') . '/hook.php');

        $config = new PluginEscaladeConfig();
        $this->assertTrue($config->getFromDB(1));
        $this->assertTrue($config->update([
            'id'                 => 1,
            'ticket_last_status' => CommonITILObject::ASSIGNED,
        ]));

        $this->assertTrue(plugin_escalade_install());

        $this->assertTrue($config->getFromDB(1));
        $this->assertEquals(CommonITILObject::ASSIGNED, $config->fields['ticket_last_status']);
    }
}
