<?php

declare(strict_types=1);

/** @var \Core\Hook $hook */
$hook->filter('plugin.trace', static fn (array $trace): array => [...$trace, 'pluginA']);
