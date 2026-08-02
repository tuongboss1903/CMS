<?php

declare(strict_types=1);

/** @var \Core\Hook $hook */
$definedVars = \array_keys(\get_defined_vars());
$hook->filter('plugin.scope_vars', static fn (): array => $definedVars);
