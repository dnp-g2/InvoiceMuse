<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DockerFeatureSetupTest extends TestCase
{
    #[Test]
    public function it_installs_transactional_customer_prerequisites_before_serving_requests(): void
    {
        $script = file_get_contents(__DIR__ . '/../resources/docker/entrypoint.sh');
        self::assertStringContainsString('set -e', $script);
        $previous = -1;
        foreach ([
            'php index.php setup/cli/migrate',
            'php index.php service_properties cli install',
            'php index.php client_updates cli install',
            'php index.php client_updates cli upgrade_review',
            'php index.php clients cli install_note_archive',
            'php index.php setup/cli/create_default_user',
            'exec "$@"',
        ] as $command) {
            $position = strpos($script, $command);
            self::assertNotFalse($position, 'Missing startup step: ' . $command);
            self::assertGreaterThan($previous, $position, 'Incorrect startup order: ' . $command);
            $previous = $position;
        }
    }
}
