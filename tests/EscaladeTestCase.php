<?php

namespace GlpiPlugin\Escalade\Tests;

use Auth;
use PHPUnit\Framework\TestCase;
use Session;

abstract class EscaladeTestCase extends TestCase
{
    protected function setUp(): void
    {
        global $DB;
        $DB->beginTransaction();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        global $DB;
        $DB->rollback();
        parent::tearDown();
    }

    protected function login(
        string $user_name = TU_USER,
        string $user_pass = TU_PASS,
        bool $noauto = true,
        bool $expected = true
    ): Auth {
        Session::destroy();
        Session::start();

        $auth = new Auth();
        $this->assertEquals($expected, $auth->login($user_name, $user_pass, $noauto));

        return $auth;
    }

    protected function logOut()
    {
        $ctime = $_SESSION['glpi_currenttime'];
        Session::destroy();
        $_SESSION['glpi_currenttime'] = $ctime;
    }
}
