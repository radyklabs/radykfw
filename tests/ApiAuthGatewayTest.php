<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;

/**
 * ApiController::respond()/error() and the Auth gateway flow.
 *
 * Both paths call exit(), which would kill the PHPUnit process, so each
 * scenario runs in a subprocess (tests/support/subprocess.php) and the
 * assertions check the marker lines on stdout:
 *   __STATUS__=<code>   final http_response_code()
 *   __REDIRECT__=<url>  wp_safe_redirect() calls
 */
final class ApiAuthGatewayTest extends TestCase
{
    private array $registryData;
    private bool $registryWasInitialized;

    protected function setUp (): void
    {
        $registry = new \ReflectionClass (Registry::class);
        $this->registryWasInitialized = $registry->getProperty ('initialized')->getValue ();
        $this->registryData = $registry->getProperty ('data')->getValue ();
        $registry->getProperty ('data')->setValue (null, ['nsmod' => 'Modules\\']);

        if (PHP_OS_FAMILY !== 'Windows')
        {
            $this->markTestSkipped ('Subprocess execution is unavailable on Windows.');
        }
    }

    protected function tearDown (): void
    {
        $registry = new \ReflectionClass (Registry::class);
        $registry->getProperty ('data')->setValue (null, $this->registryData);
        $registry->getProperty ('initialized')->setValue (null, $this->registryWasInitialized);
    }

    private function marker (string $output, string $name): string
    {
        // Markers may appear inline after JSON output (no leading newline), so
        // match anywhere in the line, not just at the start.
        foreach (preg_split ('/\R/', $output) as $line)
        {
            $line = trim ($line);
            if (str_contains ($line, $name . '='))
            {
                $pos = strpos ($line, $name . '=');
                return substr ($line, $pos + strlen ($name) + 1);
            }
        }
        return '';
    }

    private function runScenario (string $scenario): string
    {
        $php = PHP_BINARY;
        $script = realpath (__DIR__ . '/support/subprocess.php');
        $output = shell_exec (
            escapeshellarg ($php)
            . ' -d extension=mbstring '
            . escapeshellarg ($script) . ' ' . escapeshellarg ($scenario) . ' 2>&1'
        );

        $this->assertNotNull ($output, "subprocess produced no output for {$scenario}");
        return $output;
    }

    public function testApiHealthRespondsOkJsonWithVersion (): void
    {
        $output = $this->runScenario ('api-health');

        $this->assertSame ('200', $this->marker ($output, '__STATUS__'));
        $this->assertStringContainsString ('"status":"ok"', $output);
        $this->assertStringContainsString ('"version"', $output);
    }

    public function testApiUserGuestGets401 (): void
    {
        $output = $this->runScenario ('api-user-guest');

        $this->assertSame ('401', $this->marker ($output, '__STATUS__'));
        $this->assertStringContainsString ('"error":"Unauthorized"', $output);
    }

    public function testApiUserAuthenticatedEchoesCurrentUserData (): void
    {
        $output = $this->runScenario ('api-user-authenticated');

        $this->assertSame ('200', $this->marker ($output, '__STATUS__'));
        $this->assertStringContainsString ('"id":7', $output);
        $this->assertStringContainsString ('"name":"Ada"', $output);
        $this->assertStringContainsString ('"email":"ada@example.test"', $output);
    }

    public function testAuthGuestIsRedirectedToLoginRoute (): void
    {
        $output = $this->runScenario ('auth-guest');

        $redirect = $this->marker ($output, '__REDIRECT__');
        $this->assertNotSame ('', $redirect, 'guest auth must issue a redirect');
        $this->assertStringContainsString ('login', $redirect);
    }

    public function testAuthenticatedAuthUserIsRedirectedToDashboard (): void
    {
        $output = $this->runScenario ('auth-user');

        $redirect = $this->marker ($output, '__REDIRECT__');
        $this->assertStringContainsString ('dashboard', $redirect);
    }
}
