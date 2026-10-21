<?php

declare(strict_types=1);

use Clicalmani\Core\Maker\Application;
use Clicalmani\Core\Providers\ContainerServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';

// Convention du framework : évite que Response écrive directement sur la sortie
// et ferme les buffers de PHPUnit.
define('TEST_ENV', true);

// Les helpers du framework (app(), config(), request(), response(), ...) ne sont
// jamais chargés par l'autoload PSR-4.
require_once dirname(__DIR__) . '/vendor/clicalmani/foundation/src/helpers.php';

if (!defined('TESTS_FIXTURE_ROOT')) {
    define('TESTS_FIXTURE_ROOT', __DIR__ . '/Fixtures/app');
}

// Amorçage minimal : pas de kernel HTTP ni de console. Le conteneur DI fournit
// les services config, response, logger, etc. utilisés par les bridges.
Application::getInstance(TESTS_FIXTURE_ROOT);
(new ContainerServiceProvider)->boot();
